<?php
/**
 * Complete WhatsApp SaaS Pro Diagnostic Tool
 * 
 * This comprehensive diagnostic tool verifies all aspects of the WhatsApp SaaS Pro plugin
 * including database structure, file integrity, API connections, and multi-tenant functionality.
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.2.0
 */

// WordPress bootstrap
if (!defined('ABSPATH')) {
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');
}

// Check admin privileges
if (!current_user_can('manage_options')) {
    wp_die(__('Accesso non autorizzato', 'whatsapp-saas-pro'));
}

// Start diagnostic
$diagnostic_results = array(
    'timestamp' => current_time('mysql'),
    'wordpress_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
    'plugin_version' => defined('WSP_VERSION') ? WSP_VERSION : 'Not defined',
    'tests' => array()
);

/**
 * Test 1: Plugin Activation Status
 */
function test_plugin_activation() {
    $results = array(
        'name' => 'Plugin Activation',
        'status' => 'error',
        'details' => array()
    );
    
    $active_plugins = get_option('active_plugins', array());
    $plugin_found = false;
    
    foreach ($active_plugins as $plugin) {
        if (strpos($plugin, 'whatsapp-saas') !== false) {
            $plugin_found = true;
            $results['details'][] = "✅ Plugin attivo: $plugin";
            break;
        }
    }
    
    if ($plugin_found) {
        $results['status'] = 'success';
        
        // Check main class
        if (class_exists('WhatsAppSaasPluginPro')) {
            $results['details'][] = '✅ Classe principale WhatsAppSaasPluginPro caricata';
        } else {
            $results['details'][] = '❌ Classe principale WhatsAppSaasPluginPro non trovata';
            $results['status'] = 'warning';
        }
    } else {
        $results['details'][] = '❌ Plugin non attivo';
    }
    
    return $results;
}

/**
 * Test 2: Database Tables
 */
function test_database_tables() {
    global $wpdb;
    
    $results = array(
        'name' => 'Database Tables',
        'status' => 'success',
        'details' => array()
    );
    
    $required_tables = array(
        'wsp_customers' => 'Customers table',
        'wsp_subscription_plans' => 'Subscription plans',
        'wsp_customer_subscriptions' => 'Customer subscriptions',
        'wsp_whatsapp_numbers' => 'WhatsApp numbers',
        'wsp_campaigns' => 'Campaigns',
        'wsp_messages' => 'Messages',
        'wsp_credits_transactions' => 'Credits transactions',
        'wsp_reports' => 'Reports',
        'wsp_activity_log' => 'Activity log',
        'wsp_webhook_events' => 'Webhook events',
        'wsp_integrations' => 'Integrations'
    );
    
    $missing_tables = array();
    
    foreach ($required_tables as $table => $description) {
        $table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $results['details'][] = "✅ $table ($description): $count records";
        } else {
            $results['details'][] = "❌ $table ($description): MISSING";
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        $results['status'] = 'error';
        $results['details'][] = "⚠️ Missing tables: " . implode(', ', $missing_tables);
        $results['details'][] = "Run: WSP_Database_SaaS::create_tables()";
    }
    
    return $results;
}

/**
 * Test 3: Subscription Plans
 */
function test_subscription_plans() {
    global $wpdb;
    
    $results = array(
        'name' => 'Subscription Plans',
        'status' => 'success',
        'details' => array()
    );
    
    $table_name = $wpdb->prefix . 'wsp_subscription_plans';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        $results['status'] = 'error';
        $results['details'][] = '❌ Plans table does not exist';
        return $results;
    }
    
    $plans = $wpdb->get_results("SELECT * FROM $table_name ORDER BY price ASC");
    
    if (empty($plans)) {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ No subscription plans found';
        $results['details'][] = 'Creating default plans...';
        
        // Create default plans
        if (class_exists('WSP_Database_SaaS')) {
            WSP_Database_SaaS::insert_default_plans();
            $plans = $wpdb->get_results("SELECT * FROM $table_name ORDER BY price ASC");
        }
    }
    
    foreach ($plans as $plan) {
        $results['details'][] = sprintf(
            "✅ %s: €%.2f/month, %d credits, %d numbers limit",
            $plan->name,
            $plan->price,
            $plan->credits_included,
            $plan->max_numbers
        );
    }
    
    return $results;
}

/**
 * Test 4: API Configuration
 */
function test_api_configuration() {
    $results = array(
        'name' => 'API Configuration',
        'status' => 'success',
        'details' => array()
    );
    
    // Check Mail2Wa configuration
    $api_url = get_option('wsp_mail2wa_api_url', '');
    $api_key = get_option('wsp_mail2wa_api_key', '');
    
    if (empty($api_url)) {
        $api_url = defined('WSP_MAIL2WA_DEFAULT_API') ? WSP_MAIL2WA_DEFAULT_API : '';
    }
    
    if (empty($api_key)) {
        $api_key = defined('WSP_MAIL2WA_DEFAULT_KEY') ? WSP_MAIL2WA_DEFAULT_KEY : '';
    }
    
    if (!empty($api_url) && !empty($api_key)) {
        $results['details'][] = "✅ Mail2Wa API URL: $api_url";
        $results['details'][] = "✅ Mail2Wa API Key: " . substr($api_key, 0, 10) . "...";
        
        // Test API connection
        $test_response = wp_remote_get($api_url . '/status', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 10
        ));
        
        if (!is_wp_error($test_response)) {
            $results['details'][] = '✅ API connection test successful';
        } else {
            $results['details'][] = '⚠️ API connection test failed: ' . $test_response->get_error_message();
            $results['status'] = 'warning';
        }
    } else {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ Mail2Wa API not configured';
    }
    
    // Check N8N webhook configuration
    $n8n_webhook = get_option('wsp_n8n_webhook_url', '');
    if (!empty($n8n_webhook)) {
        $results['details'][] = "✅ N8N Webhook: $n8n_webhook";
    } else {
        $results['details'][] = '⚠️ N8N Webhook not configured (optional)';
    }
    
    return $results;
}

/**
 * Test 5: File Integrity
 */
function test_file_integrity() {
    $results = array(
        'name' => 'File Integrity',
        'status' => 'success',
        'details' => array()
    );
    
    $plugin_dir = WP_PLUGIN_DIR . '/whatsapp-saas-pro/';
    if (!is_dir($plugin_dir)) {
        $plugin_dir = WP_PLUGIN_DIR . '/whatsapp-saas-pro-clean/';
    }
    
    $required_files = array(
        'whatsapp-saas-pro.php' => 'Main plugin file',
        'includes/class-wsp-database-saas.php' => 'Database management',
        'includes/class-wsp-customers.php' => 'Customer management',
        'includes/class-wsp-reports.php' => 'Report generation',
        'includes/class-wsp-integrations.php' => 'External integrations',
        'includes/class-wsp-activity-log.php' => 'Activity logging',
        'admin/class-wsp-admin-customers.php' => 'Admin customers UI',
        'admin/class-wsp-admin-reports.php' => 'Admin reports UI',
        'admin/class-wsp-admin-test-saas.php' => 'Test suite'
    );
    
    $missing_files = array();
    
    foreach ($required_files as $file => $description) {
        $filepath = $plugin_dir . $file;
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            $results['details'][] = "✅ $file ($description): " . round($size / 1024, 1) . " KB";
        } else {
            $results['details'][] = "❌ $file ($description): MISSING";
            $missing_files[] = $file;
        }
    }
    
    if (!empty($missing_files)) {
        $results['status'] = 'error';
        $results['details'][] = "⚠️ Missing files: " . count($missing_files);
    }
    
    return $results;
}

/**
 * Test 6: Customer Functionality
 */
function test_customer_functionality() {
    $results = array(
        'name' => 'Customer Functionality',
        'status' => 'success',
        'details' => array()
    );
    
    if (!class_exists('WSP_Customers')) {
        $results['status'] = 'error';
        $results['details'][] = '❌ WSP_Customers class not found';
        return $results;
    }
    
    // Test customer creation
    $test_data = array(
        'business_name' => 'Test Company ' . time(),
        'contact_name' => 'Test User',
        'email' => 'test' . time() . '@example.com',
        'whatsapp_number' => '+39' . rand(1000000000, 9999999999),
        'plan_id' => 1
    );
    
    $result = WSP_Customers::register_customer($test_data);
    
    if ($result['success']) {
        $results['details'][] = '✅ Customer creation successful';
        $results['details'][] = '  - Customer ID: ' . $result['customer_id'];
        $results['details'][] = '  - Customer Code: ' . $result['customer_code'];
        $results['details'][] = '  - API Key: ' . substr($result['api_key'], 0, 20) . '...';
        
        // Test customer retrieval
        $customer = WSP_Customers::get_customer($result['customer_id']);
        if ($customer) {
            $results['details'][] = '✅ Customer retrieval successful';
        } else {
            $results['details'][] = '❌ Customer retrieval failed';
            $results['status'] = 'warning';
        }
        
        // Clean up test customer
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'wsp_customers', array('id' => $result['customer_id']));
        $results['details'][] = '✅ Test customer cleaned up';
    } else {
        $results['status'] = 'error';
        $results['details'][] = '❌ Customer creation failed: ' . $result['message'];
    }
    
    return $results;
}

/**
 * Test 7: Report Generation
 */
function test_report_generation() {
    $results = array(
        'name' => 'Report Generation',
        'status' => 'success',
        'details' => array()
    );
    
    if (!class_exists('WSP_Reports')) {
        $results['status'] = 'error';
        $results['details'][] = '❌ WSP_Reports class not found';
        return $results;
    }
    
    // Get a test customer
    global $wpdb;
    $customer = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wsp_customers LIMIT 1");
    
    if (!$customer) {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ No customers found for report test';
        return $results;
    }
    
    // Generate test report
    $report_id = WSP_Reports::generate_report(
        $customer->id,
        'daily',
        date('Y-m-d', strtotime('-1 day')),
        date('Y-m-d')
    );
    
    if ($report_id) {
        $results['details'][] = '✅ Report generation successful';
        $results['details'][] = '  - Report ID: ' . $report_id;
        
        // Test WhatsApp command processing
        $command_result = WSP_Reports::process_whatsapp_command(
            $customer->whatsapp_number,
            'REPORT NUMERI',
            $customer->id
        );
        
        if ($command_result['success']) {
            $results['details'][] = '✅ WhatsApp command processing successful';
        } else {
            $results['details'][] = '⚠️ WhatsApp command processing: ' . $command_result['message'];
        }
        
        // Clean up test report
        $wpdb->delete($wpdb->prefix . 'wsp_reports', array('id' => $report_id));
        $results['details'][] = '✅ Test report cleaned up';
    } else {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ Report generation failed';
    }
    
    return $results;
}

/**
 * Test 8: Cron Jobs
 */
function test_cron_jobs() {
    $results = array(
        'name' => 'Cron Jobs',
        'status' => 'success',
        'details' => array()
    );
    
    $expected_crons = array(
        'wsp_process_emails' => 'Process incoming emails',
        'wsp_send_daily_reports' => 'Send daily reports',
        'wsp_check_subscriptions' => 'Check subscription status',
        'wsp_cleanup_logs' => 'Clean up old logs'
    );
    
    $crons = _get_cron_array();
    $missing_crons = array();
    
    foreach ($expected_crons as $hook => $description) {
        $found = false;
        foreach ($crons as $timestamp => $cron) {
            if (isset($cron[$hook])) {
                $found = true;
                $next_run = date('Y-m-d H:i:s', $timestamp);
                $results['details'][] = "✅ $hook ($description): Next run at $next_run";
                break;
            }
        }
        
        if (!$found) {
            $results['details'][] = "⚠️ $hook ($description): Not scheduled";
            $missing_crons[] = $hook;
        }
    }
    
    if (!empty($missing_crons)) {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ Some cron jobs are not scheduled';
    }
    
    return $results;
}

/**
 * Test 9: Permissions
 */
function test_permissions() {
    $results = array(
        'name' => 'Permissions',
        'status' => 'success',
        'details' => array()
    );
    
    $upload_dir = wp_upload_dir();
    $wsp_upload_dir = $upload_dir['basedir'] . '/wsp-uploads/';
    
    // Check upload directory
    if (!is_dir($wsp_upload_dir)) {
        wp_mkdir_p($wsp_upload_dir);
        $results['details'][] = '✅ Created upload directory';
    }
    
    if (is_writable($wsp_upload_dir)) {
        $results['details'][] = '✅ Upload directory is writable';
    } else {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ Upload directory is not writable';
    }
    
    // Check log directory
    $log_dir = $upload_dir['basedir'] . '/wsp-logs/';
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
        $results['details'][] = '✅ Created log directory';
    }
    
    if (is_writable($log_dir)) {
        $results['details'][] = '✅ Log directory is writable';
    } else {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ Log directory is not writable';
    }
    
    return $results;
}

/**
 * Test 10: Integration Test
 */
function test_integration() {
    $results = array(
        'name' => 'Integration Test',
        'status' => 'success',
        'details' => array()
    );
    
    // Test QR code service
    $qr_url = defined('WSP_QR_SERVICE_URL') ? WSP_QR_SERVICE_URL : 'https://qr.wapower.it';
    $test_url = $qr_url . '?email=test@example.com';
    
    $response = wp_remote_head($test_url, array('timeout' => 5));
    if (!is_wp_error($response)) {
        $results['details'][] = '✅ QR code service is accessible';
    } else {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ QR code service not accessible: ' . $response->get_error_message();
    }
    
    // Test registration form URL
    $reg_url = defined('WSP_REGISTRATION_FORM_URL') ? WSP_REGISTRATION_FORM_URL : 'https://upgradeservizi.eu/register';
    $response = wp_remote_head($reg_url, array('timeout' => 5));
    if (!is_wp_error($response)) {
        $results['details'][] = '✅ Registration form URL is accessible';
    } else {
        $results['status'] = 'warning';
        $results['details'][] = '⚠️ Registration form not accessible: ' . $response->get_error_message();
    }
    
    return $results;
}

// Run all tests
$diagnostic_results['tests'][] = test_plugin_activation();
$diagnostic_results['tests'][] = test_database_tables();
$diagnostic_results['tests'][] = test_subscription_plans();
$diagnostic_results['tests'][] = test_api_configuration();
$diagnostic_results['tests'][] = test_file_integrity();
$diagnostic_results['tests'][] = test_customer_functionality();
$diagnostic_results['tests'][] = test_report_generation();
$diagnostic_results['tests'][] = test_cron_jobs();
$diagnostic_results['tests'][] = test_permissions();
$diagnostic_results['tests'][] = test_integration();

// Calculate overall status
$error_count = 0;
$warning_count = 0;
$success_count = 0;

foreach ($diagnostic_results['tests'] as $test) {
    switch ($test['status']) {
        case 'error':
            $error_count++;
            break;
        case 'warning':
            $warning_count++;
            break;
        case 'success':
            $success_count++;
            break;
    }
}

$diagnostic_results['summary'] = array(
    'total_tests' => count($diagnostic_results['tests']),
    'success' => $success_count,
    'warnings' => $warning_count,
    'errors' => $error_count,
    'overall_status' => $error_count > 0 ? 'error' : ($warning_count > 0 ? 'warning' : 'success')
);

// Output results
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp SaaS Pro - Complete Diagnostic</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #1e293b;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            color: #475569;
            font-size: 14px;
            text-transform: uppercase;
        }
        .summary-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
        }
        .test-section {
            margin-bottom: 25px;
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
        }
        .test-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .test-name {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            flex: 1;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-success {
            background: #10b981;
            color: white;
        }
        .status-warning {
            background: #f59e0b;
            color: white;
        }
        .status-error {
            background: #ef4444;
            color: white;
        }
        .test-details {
            background: white;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.8;
        }
        .test-details div {
            padding: 2px 0;
        }
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .button-primary {
            background: #3b82f6;
            color: white;
        }
        .button-secondary {
            background: #64748b;
            color: white;
        }
        .button:hover {
            opacity: 0.9;
        }
        .meta-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 WhatsApp SaaS Pro - Complete Diagnostic</h1>
        
        <div class="summary">
            <div class="summary-card">
                <h3>Total Tests</h3>
                <div class="value"><?php echo $diagnostic_results['summary']['total_tests']; ?></div>
            </div>
            <div class="summary-card">
                <h3>Successful</h3>
                <div class="value" style="color: #10b981;"><?php echo $diagnostic_results['summary']['success']; ?></div>
            </div>
            <div class="summary-card">
                <h3>Warnings</h3>
                <div class="value" style="color: #f59e0b;"><?php echo $diagnostic_results['summary']['warnings']; ?></div>
            </div>
            <div class="summary-card">
                <h3>Errors</h3>
                <div class="value" style="color: #ef4444;"><?php echo $diagnostic_results['summary']['errors']; ?></div>
            </div>
        </div>
        
        <?php foreach ($diagnostic_results['tests'] as $test): ?>
            <div class="test-section">
                <div class="test-header">
                    <div class="test-name"><?php echo $test['name']; ?></div>
                    <div class="status-badge status-<?php echo $test['status']; ?>">
                        <?php echo $test['status']; ?>
                    </div>
                </div>
                <div class="test-details">
                    <?php foreach ($test['details'] as $detail): ?>
                        <div><?php echo $detail; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=whatsapp-saas'); ?>" class="button button-primary">
                Go to Plugin Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=wsp-test'); ?>" class="button button-secondary">
                Run Advanced Tests
            </a>
            <button onclick="window.location.reload();" class="button button-secondary">
                Run Diagnostic Again
            </button>
            <button onclick="window.print();" class="button button-secondary">
                Print Report
            </button>
        </div>
        
        <div class="meta-info">
            <p><strong>Diagnostic Information:</strong></p>
            <ul>
                <li>Timestamp: <?php echo $diagnostic_results['timestamp']; ?></li>
                <li>WordPress Version: <?php echo $diagnostic_results['wordpress_version']; ?></li>
                <li>PHP Version: <?php echo $diagnostic_results['php_version']; ?></li>
                <li>Plugin Version: <?php echo $diagnostic_results['plugin_version']; ?></li>
                <li>Server: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></li>
            </ul>
        </div>
    </div>
    
    <script>
    // Auto-save diagnostic results
    const results = <?php echo json_encode($diagnostic_results); ?>;
    console.log('Diagnostic Results:', results);
    
    // Save to localStorage for debugging
    localStorage.setItem('wsp_diagnostic_' + Date.now(), JSON.stringify(results));
    </script>
</body>
</html>