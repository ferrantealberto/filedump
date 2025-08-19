<?php
/**
 * Pannello Test per funzionalità SaaS
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSP_Admin_Test_SaaS {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_test_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers per test
        add_action('wp_ajax_wsp_test_create_customer', array($this, 'ajax_test_create_customer'));
        add_action('wp_ajax_wsp_test_activate_customer', array($this, 'ajax_test_activate_customer'));
        add_action('wp_ajax_wsp_test_generate_report', array($this, 'ajax_test_generate_report'));
        add_action('wp_ajax_wsp_test_process_command', array($this, 'ajax_test_process_command'));
        add_action('wp_ajax_wsp_test_change_plan', array($this, 'ajax_test_change_plan'));
        add_action('wp_ajax_wsp_test_add_credits', array($this, 'ajax_test_add_credits'));
        add_action('wp_ajax_wsp_test_verify_api', array($this, 'ajax_test_verify_api'));
        add_action('wp_ajax_wsp_test_send_welcome_email', array($this, 'ajax_test_send_welcome_email'));
        add_action('wp_ajax_wsp_test_webhook', array($this, 'ajax_test_webhook'));
        add_action('wp_ajax_wsp_test_database_integrity', array($this, 'ajax_test_database_integrity'));
        add_action('wp_ajax_wsp_reset_test_data', array($this, 'ajax_reset_test_data'));
        add_action('wp_ajax_wsp_test_multi_tenant', array($this, 'ajax_test_multi_tenant'));
    }
    
    public function add_test_menu() {
        add_submenu_page(
            'whatsapp-saas',
            'Test SaaS',
            'Test SaaS 🧪',
            'manage_options',
            'wsp-test-saas',
            array($this, 'render_test_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'whatsapp-saas_page_wsp-test-saas') {
            return;
        }
        
        wp_enqueue_script('wsp-test-saas', WSP_PLUGIN_URL . 'assets/js/test-saas.js', array('jquery'), WSP_VERSION);
        wp_localize_script('wsp-test-saas', 'wsp_test_saas', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsp_test_saas_nonce')
        ));
        
        wp_enqueue_style('wsp-test-saas', WSP_PLUGIN_URL . 'assets/css/test-saas.css', array(), WSP_VERSION);
    }
    
    public function render_test_page() {
        global $wpdb;
        
        // Ottieni statistiche generali
        $stats = $this->get_system_stats();
        ?>
        <div class="wrap wsp-test-saas">
            <h1>🧪 Test Funzionalità SaaS</h1>
            
            <div class="wsp-test-notice notice notice-info">
                <p><strong>⚠️ Ambiente di Test:</strong> Questa sezione è per testare le funzionalità SaaS. I dati creati qui sono reali e verranno salvati nel database.</p>
            </div>
            
            <!-- Statistiche Sistema -->
            <div class="wsp-stats-box">
                <h2>📊 Statistiche Sistema</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Clienti Totali:</span>
                        <span class="stat-value"><?php echo $stats['total_customers']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Clienti Attivi:</span>
                        <span class="stat-value"><?php echo $stats['active_customers']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Piani Disponibili:</span>
                        <span class="stat-value"><?php echo $stats['total_plans']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Numeri Raccolti:</span>
                        <span class="stat-value"><?php echo $stats['total_numbers']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Messaggi Inviati:</span>
                        <span class="stat-value"><?php echo $stats['total_messages']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Report Generati:</span>
                        <span class="stat-value"><?php echo $stats['total_reports']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Test Suite -->
            <div class="wsp-test-suite">
                <h2>🔬 Suite di Test</h2>
                
                <!-- Test 1: Creazione Cliente -->
                <div class="test-section">
                    <h3>1️⃣ Test Creazione Cliente</h3>
                    <p>Crea un nuovo cliente di test con dati predefiniti.</p>
                    <button class="button button-primary test-btn" data-test="create_customer">
                        ➕ Crea Cliente Test
                    </button>
                    <div class="test-result" id="result-create-customer"></div>
                </div>
                
                <!-- Test 2: Attivazione Cliente -->
                <div class="test-section">
                    <h3>2️⃣ Test Attivazione Cliente</h3>
                    <p>Attiva un cliente esistente simulando la scansione del QR code.</p>
                    <select id="customer-to-activate" class="regular-text">
                        <option value="">-- Seleziona Cliente --</option>
                        <?php
                        $customers = $wpdb->get_results("SELECT id, business_name, is_active FROM {$wpdb->prefix}wsp_customers ORDER BY created_at DESC");
                        foreach ($customers as $customer) {
                            $status = $customer->is_active ? '✅' : '⏸️';
                            echo "<option value='{$customer->id}'>{$status} {$customer->business_name}</option>";
                        }
                        ?>
                    </select>
                    <button class="button test-btn" data-test="activate_customer">
                        ✅ Attiva Cliente
                    </button>
                    <div class="test-result" id="result-activate-customer"></div>
                </div>
                
                <!-- Test 3: Generazione Report -->
                <div class="test-section">
                    <h3>3️⃣ Test Generazione Report</h3>
                    <p>Genera un report numeri per un cliente.</p>
                    <select id="customer-for-report" class="regular-text">
                        <option value="">-- Seleziona Cliente --</option>
                        <?php
                        foreach ($customers as $customer) {
                            if ($customer->is_active) {
                                echo "<option value='{$customer->id}'>{$customer->business_name}</option>";
                            }
                        }
                        ?>
                    </select>
                    <button class="button test-btn" data-test="generate_report">
                        📊 Genera Report
                    </button>
                    <div class="test-result" id="result-generate-report"></div>
                </div>
                
                <!-- Test 4: Comandi WhatsApp -->
                <div class="test-section">
                    <h3>4️⃣ Test Comandi WhatsApp</h3>
                    <p>Simula l'invio di un comando WhatsApp.</p>
                    <input type="text" id="whatsapp-from" class="regular-text" placeholder="+391234567890" value="+391234567890">
                    <select id="whatsapp-command" class="regular-text">
                        <option value="REPORT NUMERI">REPORT NUMERI</option>
                        <option value="SALDO">SALDO</option>
                        <option value="STATO">STATO</option>
                        <option value="PIANO">PIANO</option>
                        <option value="STATISTICHE">STATISTICHE</option>
                        <option value="HELP">HELP</option>
                    </select>
                    <button class="button test-btn" data-test="process_command">
                        💬 Invia Comando
                    </button>
                    <div class="test-result" id="result-process-command"></div>
                </div>
                
                <!-- Test 5: Cambio Piano -->
                <div class="test-section">
                    <h3>5️⃣ Test Cambio Piano</h3>
                    <p>Cambia il piano di un cliente.</p>
                    <select id="customer-for-plan" class="regular-text">
                        <option value="">-- Seleziona Cliente --</option>
                        <?php
                        foreach ($customers as $customer) {
                            echo "<option value='{$customer->id}'>{$customer->business_name}</option>";
                        }
                        ?>
                    </select>
                    <select id="new-plan" class="regular-text">
                        <?php
                        $plans = $wpdb->get_results("SELECT id, plan_name FROM {$wpdb->prefix}wsp_subscription_plans WHERE is_active = 1");
                        foreach ($plans as $plan) {
                            echo "<option value='{$plan->id}'>{$plan->plan_name}</option>";
                        }
                        ?>
                    </select>
                    <button class="button test-btn" data-test="change_plan">
                        🔄 Cambia Piano
                    </button>
                    <div class="test-result" id="result-change-plan"></div>
                </div>
                
                <!-- Test 6: Aggiungi Crediti -->
                <div class="test-section">
                    <h3>6️⃣ Test Aggiunta Crediti</h3>
                    <p>Aggiungi crediti a un cliente.</p>
                    <select id="customer-for-credits" class="regular-text">
                        <option value="">-- Seleziona Cliente --</option>
                        <?php
                        foreach ($customers as $customer) {
                            echo "<option value='{$customer->id}'>{$customer->business_name}</option>";
                        }
                        ?>
                    </select>
                    <input type="number" id="credits-amount" class="small-text" value="100" min="1">
                    <button class="button test-btn" data-test="add_credits">
                        💰 Aggiungi Crediti
                    </button>
                    <div class="test-result" id="result-add-credits"></div>
                </div>
                
                <!-- Test 7: Verifica API Key -->
                <div class="test-section">
                    <h3>7️⃣ Test API Key</h3>
                    <p>Verifica funzionamento API key cliente.</p>
                    <input type="text" id="api-key-test" class="regular-text" placeholder="wsp_xxxxx...">
                    <button class="button test-btn" data-test="verify_api">
                        🔑 Verifica API
                    </button>
                    <div class="test-result" id="result-verify-api"></div>
                </div>
                
                <!-- Test 8: Email di Benvenuto -->
                <div class="test-section">
                    <h3>8️⃣ Test Email Benvenuto</h3>
                    <p>Invia email di benvenuto con QR code.</p>
                    <select id="customer-for-email" class="regular-text">
                        <option value="">-- Seleziona Cliente --</option>
                        <?php
                        foreach ($customers as $customer) {
                            echo "<option value='{$customer->id}'>{$customer->business_name}</option>";
                        }
                        ?>
                    </select>
                    <button class="button test-btn" data-test="send_welcome_email">
                        📧 Invia Email
                    </button>
                    <div class="test-result" id="result-send-welcome-email"></div>
                </div>
                
                <!-- Test 9: Webhook -->
                <div class="test-section">
                    <h3>9️⃣ Test Webhook N8N</h3>
                    <p>Testa il webhook per l'invio report.</p>
                    <input type="text" id="webhook-url" class="regular-text" placeholder="https://n8n.example.com/webhook/xxx" 
                           value="<?php echo get_option('wsp_n8n_webhook_url', ''); ?>">
                    <button class="button test-btn" data-test="webhook">
                        🔗 Test Webhook
                    </button>
                    <div class="test-result" id="result-webhook"></div>
                </div>
                
                <!-- Test 10: Integrità Database -->
                <div class="test-section">
                    <h3>🔟 Test Integrità Database</h3>
                    <p>Verifica che tutte le tabelle e relazioni siano corrette.</p>
                    <button class="button test-btn" data-test="database_integrity">
                        🗄️ Verifica Database
                    </button>
                    <div class="test-result" id="result-database-integrity"></div>
                </div>
                
                <!-- Test 11: Multi-Tenant -->
                <div class="test-section">
                    <h3>1️⃣1️⃣ Test Isolamento Multi-Tenant</h3>
                    <p>Verifica che i dati dei clienti siano isolati correttamente.</p>
                    <button class="button test-btn" data-test="multi_tenant">
                        🏢 Test Multi-Tenant
                    </button>
                    <div class="test-result" id="result-multi-tenant"></div>
                </div>
            </div>
            
            <!-- Azioni Globali -->
            <div class="wsp-test-actions">
                <h2>⚡ Azioni Globali</h2>
                
                <div class="action-section">
                    <h3>🗑️ Reset Dati Test</h3>
                    <p><strong>Attenzione:</strong> Questa azione eliminerà TUTTI i clienti di test (quelli con email @test.com).</p>
                    <button class="button button-secondary" id="reset-test-data">
                        🗑️ Elimina Dati Test
                    </button>
                    <div class="test-result" id="result-reset"></div>
                </div>
                
                <div class="action-section">
                    <h3>📝 Log Attività</h3>
                    <p>Ultimi 10 log di sistema:</p>
                    <div class="log-viewer">
                        <?php
                        $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wsp_activity_log ORDER BY created_at DESC LIMIT 10");
                        if ($logs) {
                            echo '<table class="widefat">';
                            echo '<thead><tr><th>Data</th><th>Tipo</th><th>Messaggio</th><th>Cliente</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($logs as $log) {
                                echo '<tr>';
                                echo '<td>' . $log->created_at . '</td>';
                                echo '<td>' . $log->log_type . '</td>';
                                echo '<td>' . $log->log_message . '</td>';
                                echo '<td>' . ($log->customer_id ?: '-') . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        } else {
                            echo '<p>Nessun log disponibile.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wsp-test-saas {
            max-width: 1200px;
        }
        
        .wsp-stats-box {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            padding: 10px;
            background: #f0f0f1;
            border-radius: 4px;
            text-align: center;
        }
        
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        
        .wsp-test-suite, .wsp-test-actions {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .test-section, .action-section {
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background: #fafafa;
        }
        
        .test-section h3, .action-section h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        .test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        
        .test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        
        .test-result.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            display: block;
        }
        
        .test-result pre {
            background: #fff;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        .log-viewer {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #fff;
            margin-top: 10px;
        }
        
        .test-btn {
            margin-left: 10px !important;
        }
        
        select.regular-text {
            width: 250px;
            margin-right: 10px;
        }
        
        input.regular-text {
            width: 250px;
            margin-right: 10px;
        }
        </style>
        <?php
    }
    
    private function get_system_stats() {
        global $wpdb;
        
        $stats = array();
        
        $stats['total_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsp_customers");
        $stats['active_customers'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsp_customers WHERE is_active = 1");
        $stats['total_plans'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsp_subscription_plans WHERE is_active = 1");
        $stats['total_numbers'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsp_whatsapp_numbers");
        $stats['total_messages'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsp_messages");
        $stats['total_reports'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsp_reports");
        
        return $stats;
    }
    
    // AJAX Handler: Crea Cliente Test
    public function ajax_test_create_customer() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        // Genera dati casuali per il test
        $random = rand(1000, 9999);
        $test_data = array(
            'business_name' => 'Test Azienda ' . $random,
            'contact_name' => 'Mario Rossi ' . $random,
            'email' => 'test' . $random . '@test.com',
            'phone' => '+3912345' . $random,
            'whatsapp_number' => '+3912345' . $random,
            'vat_number' => 'IT12345678' . $random,
            'address' => 'Via Test ' . $random,
            'city' => 'Milano',
            'postal_code' => '20100',
            'country' => 'Italia',
            'plan_id' => 1 // Piano Standard
        );
        
        $result = WSP_Customers::register_customer($test_data);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Cliente creato con successo!',
                'data' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'error' => $result['error'] ?? ''
            ));
        }
    }
    
    // AJAX Handler: Attiva Cliente
    public function ajax_test_activate_customer() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $customer_id = intval($_POST['customer_id']);
        
        if (!$customer_id) {
            wp_send_json_error('Seleziona un cliente');
        }
        
        $result = WSP_Customers::activate_customer($customer_id);
        
        if ($result) {
            wp_send_json_success('Cliente attivato con successo!');
        } else {
            wp_send_json_error('Errore durante l\'attivazione');
        }
    }
    
    // AJAX Handler: Genera Report
    public function ajax_test_generate_report() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $customer_id = intval($_POST['customer_id']);
        
        if (!$customer_id) {
            wp_send_json_error('Seleziona un cliente');
        }
        
        // Prima aggiungi alcuni numeri di test se non ce ne sono
        $this->add_test_numbers($customer_id);
        
        $result = WSP_Reports::generate_numbers_report($customer_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Report generato con successo!',
                'data' => $result
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX Handler: Processa Comando WhatsApp
    public function ajax_test_process_command() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $from_number = sanitize_text_field($_POST['from_number']);
        $command = sanitize_text_field($_POST['command']);
        
        $result = WSP_Reports::process_whatsapp_command($from_number, $command);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Comando processato!',
                'response' => $result['message']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX Handler: Cambia Piano
    public function ajax_test_change_plan() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $customer_id = intval($_POST['customer_id']);
        $plan_id = intval($_POST['plan_id']);
        
        if (!$customer_id || !$plan_id) {
            wp_send_json_error('Dati mancanti');
        }
        
        $result = WSP_Customers::update_customer_plan($customer_id, $plan_id);
        
        if ($result) {
            wp_send_json_success('Piano cambiato con successo!');
        } else {
            wp_send_json_error('Errore nel cambio piano');
        }
    }
    
    // AJAX Handler: Aggiungi Crediti
    public function ajax_test_add_credits() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $customer_id = intval($_POST['customer_id']);
        $amount = intval($_POST['amount']);
        
        if (!$customer_id || !$amount) {
            wp_send_json_error('Dati mancanti');
        }
        
        $result = WSP_Credits::add_credits($customer_id, $amount, 'Test crediti aggiunti da admin');
        
        if ($result) {
            $new_balance = WSP_Credits::get_balance($customer_id);
            wp_send_json_success(array(
                'message' => "Aggiunti $amount crediti. Nuovo saldo: $new_balance"
            ));
        } else {
            wp_send_json_error('Errore nell\'aggiunta crediti');
        }
    }
    
    // AJAX Handler: Verifica API Key
    public function ajax_test_verify_api() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error('Inserisci una API key');
        }
        
        $customer = WSP_Customers::verify_api_key($api_key);
        
        if ($customer) {
            wp_send_json_success(array(
                'message' => 'API Key valida!',
                'customer' => array(
                    'id' => $customer->id,
                    'business_name' => $customer->business_name,
                    'email' => $customer->email,
                    'is_active' => $customer->is_active
                )
            ));
        } else {
            wp_send_json_error('API Key non valida o cliente non attivo');
        }
    }
    
    // AJAX Handler: Invia Email Benvenuto
    public function ajax_test_send_welcome_email() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        global $wpdb;
        
        $customer_id = intval($_POST['customer_id']);
        
        if (!$customer_id) {
            wp_send_json_error('Seleziona un cliente');
        }
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wsp_customers WHERE id = %d",
            $customer_id
        ), ARRAY_A);
        
        if (!$customer) {
            wp_send_json_error('Cliente non trovato');
        }
        
        // Invia email
        $to = $customer['email'];
        $subject = 'Benvenuto in WaPower - Il tuo account è pronto!';
        $qr_url = 'https://qr.wapower.it/?email=' . urlencode($customer['email']);
        
        $message = sprintf(
            'Gentile %s,<br><br>' .
            'Il tuo account WaPower per <strong>%s</strong> è stato creato con successo!<br><br>' .
            '<strong>I tuoi dati di accesso:</strong><br>' .
            'Email: %s<br>' .
            'API Key: %s<br>' .
            'QR Code URL: <a href="%s">%s</a><br><br>' .
            'Per completare l\'attivazione, scansiona il QR code dal tuo WhatsApp Business.<br><br>' .
            'Cordiali saluti,<br>' .
            'Il Team WaPower',
            $customer['contact_name'],
            $customer['business_name'],
            $customer['email'],
            $customer['api_key'],
            $qr_url,
            $qr_url
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success('Email inviata a ' . $to);
        } else {
            wp_send_json_error('Errore invio email. Verifica configurazione SMTP.');
        }
    }
    
    // AJAX Handler: Test Webhook
    public function ajax_test_webhook() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $webhook_url = esc_url_raw($_POST['webhook_url']);
        
        if (empty($webhook_url)) {
            wp_send_json_error('Inserisci URL webhook');
        }
        
        // Salva URL webhook
        update_option('wsp_n8n_webhook_url', $webhook_url);
        
        // Prepara payload test
        $payload = array(
            'event' => 'test_webhook',
            'timestamp' => current_time('mysql'),
            'message' => 'Test webhook da WhatsApp SaaS Pro',
            'data' => array(
                'plugin_version' => WSP_VERSION,
                'site_url' => site_url()
            )
        );
        
        // Invia webhook
        $response = wp_remote_post($webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($payload),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Errore: ' . $response->get_error_message());
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            wp_send_json_success(array(
                'message' => 'Webhook inviato!',
                'status_code' => $status_code,
                'response' => $body
            ));
        }
    }
    
    // AJAX Handler: Test Integrità Database
    public function ajax_test_database_integrity() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $issues = WSP_Database_SaaS::check_database_integrity();
        
        if (empty($issues)) {
            // Verifica anche le foreign keys
            global $wpdb;
            $fk_test = array();
            
            // Test inserimento con foreign key
            try {
                $test_customer_id = 999999; // ID che non esiste
                $result = $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}wsp_whatsapp_numbers (customer_id, sender_number) VALUES (%d, %s)",
                    $test_customer_id,
                    'test'
                ));
                
                if ($result !== false) {
                    $fk_test[] = 'Foreign key customer_id non funzionante';
                    // Pulisci test
                    $wpdb->query("DELETE FROM {$wpdb->prefix}wsp_whatsapp_numbers WHERE customer_id = $test_customer_id");
                }
            } catch (Exception $e) {
                // Foreign key funziona correttamente
            }
            
            if (empty($fk_test)) {
                wp_send_json_success(array(
                    'message' => '✅ Database integro! Tutte le tabelle e relazioni sono corrette.',
                    'tables_ok' => true,
                    'foreign_keys_ok' => true
                ));
            } else {
                wp_send_json_success(array(
                    'message' => '⚠️ Database parzialmente integro',
                    'issues' => $fk_test
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => 'Problemi rilevati nel database',
                'issues' => $issues
            ));
        }
    }
    
    // AJAX Handler: Test Multi-Tenant
    public function ajax_test_multi_tenant() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        global $wpdb;
        
        // Crea 2 clienti di test
        $customer1_data = array(
            'business_name' => 'Test Tenant 1',
            'contact_name' => 'Tenant 1',
            'email' => 'tenant1@test.com',
            'whatsapp_number' => '+391111111111',
            'plan_id' => 1
        );
        
        $customer2_data = array(
            'business_name' => 'Test Tenant 2',
            'contact_name' => 'Tenant 2',
            'email' => 'tenant2@test.com',
            'whatsapp_number' => '+392222222222',
            'plan_id' => 2
        );
        
        $customer1 = WSP_Customers::register_customer($customer1_data);
        $customer2 = WSP_Customers::register_customer($customer2_data);
        
        if (!$customer1['success'] || !$customer2['success']) {
            wp_send_json_error('Errore creazione clienti test');
        }
        
        $c1_id = $customer1['customer_id'];
        $c2_id = $customer2['customer_id'];
        
        // Aggiungi numeri per ogni cliente
        $wpdb->insert($wpdb->prefix . 'wsp_whatsapp_numbers', array(
            'customer_id' => $c1_id,
            'sender_number' => '+39333111111',
            'sender_name' => 'Contact C1'
        ));
        
        $wpdb->insert($wpdb->prefix . 'wsp_whatsapp_numbers', array(
            'customer_id' => $c2_id,
            'sender_number' => '+39333222222',
            'sender_name' => 'Contact C2'
        ));
        
        // Verifica isolamento: Cliente 1 non deve vedere dati Cliente 2
        $c1_numbers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wsp_whatsapp_numbers WHERE customer_id = %d",
            $c1_id
        ));
        
        $c2_numbers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wsp_whatsapp_numbers WHERE customer_id = %d",
            $c2_id
        ));
        
        // Test API key univoche
        $c1_api = $customer1['api_key'];
        $c2_api = $customer2['api_key'];
        
        $tests_passed = array();
        $tests_failed = array();
        
        // Test 1: Isolamento dati
        if ($c1_numbers == 1 && $c2_numbers == 1) {
            $tests_passed[] = 'Isolamento dati OK';
        } else {
            $tests_failed[] = 'Isolamento dati fallito';
        }
        
        // Test 2: API key univoche
        if ($c1_api != $c2_api) {
            $tests_passed[] = 'API key univoche OK';
        } else {
            $tests_failed[] = 'API key non univoche';
        }
        
        // Test 3: Verifica accesso con API key
        $verify1 = WSP_Customers::verify_api_key($c1_api);
        $verify2 = WSP_Customers::verify_api_key($c2_api);
        
        if ($verify1->id == $c1_id && $verify2->id == $c2_id) {
            $tests_passed[] = 'Verifica API key OK';
        } else {
            $tests_failed[] = 'Verifica API key fallita';
        }
        
        if (empty($tests_failed)) {
            wp_send_json_success(array(
                'message' => '✅ Test Multi-Tenant superato!',
                'tests_passed' => $tests_passed,
                'customer1' => array(
                    'id' => $c1_id,
                    'api_key' => substr($c1_api, 0, 20) . '...'
                ),
                'customer2' => array(
                    'id' => $c2_id,
                    'api_key' => substr($c2_api, 0, 20) . '...'
                )
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Test Multi-Tenant fallito',
                'tests_failed' => $tests_failed,
                'tests_passed' => $tests_passed
            ));
        }
    }
    
    // AJAX Handler: Reset Dati Test
    public function ajax_reset_test_data() {
        check_ajax_referer('wsp_test_saas_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        global $wpdb;
        
        // Elimina solo clienti con email @test.com
        $test_customers = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}wsp_customers WHERE email LIKE '%@test.com'"
        );
        
        $deleted_count = 0;
        
        foreach ($test_customers as $customer) {
            // Le foreign key con CASCADE elimineranno automaticamente i dati correlati
            $result = $wpdb->delete(
                $wpdb->prefix . 'wsp_customers',
                array('id' => $customer->id)
            );
            
            if ($result) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            wp_send_json_success("Eliminati $deleted_count clienti di test con tutti i dati correlati.");
        } else {
            wp_send_json_success('Nessun cliente di test da eliminare.');
        }
    }
    
    // Helper: Aggiungi numeri test
    private function add_test_numbers($customer_id) {
        global $wpdb;
        
        // Verifica se ci sono già numeri
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wsp_whatsapp_numbers WHERE customer_id = %d",
            $customer_id
        ));
        
        if ($count < 5) {
            // Aggiungi alcuni numeri di test
            for ($i = 1; $i <= 5; $i++) {
                $wpdb->insert(
                    $wpdb->prefix . 'wsp_whatsapp_numbers',
                    array(
                        'customer_id' => $customer_id,
                        'sender_number' => '+3933300000' . $i,
                        'sender_name' => 'Test Contact ' . $i,
                        'sender_email' => 'contact' . $i . '@test.com',
                        'campaign_id' => 'TEST_CAMPAIGN',
                        'source' => 'test',
                        'is_active' => 1
                    )
                );
            }
        }
    }
}