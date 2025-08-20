<?php
/**
 * Diagnostica Sistema SaaS
 * 
 * Carica questo file direttamente per verificare lo stato del sistema
 */

// Carica WordPress
require_once('../../../wp-load.php');

// Verifica se sei admin
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato. Devi essere amministratore.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostica WhatsApp SaaS Pro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .diagnostic-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            overflow-x: auto;
        }
        .test-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <h1>🔍 Diagnostica WhatsApp SaaS Pro</h1>
    
    <?php
    global $wpdb;
    
    // 1. Verifica Plugin Attivo
    echo '<div class="diagnostic-box">';
    echo '<h2>1. Stato Plugin</h2>';
    
    $plugin_file = 'whatsapp-saas-pro/whatsapp-saas-plugin-v4.php';
    $alt_plugin_file = 'whatsapp-saas-pro/whatsapp-saas-plugin.php';
    
    if (is_plugin_active($plugin_file)) {
        echo '<p class="success">✅ Plugin v4 attivo: ' . $plugin_file . '</p>';
    } elseif (is_plugin_active($alt_plugin_file)) {
        echo '<p class="warning">⚠️ Plugin v3 attivo (versione vecchia): ' . $alt_plugin_file . '</p>';
        echo '<p>Dovresti attivare la versione v4 del plugin!</p>';
    } else {
        echo '<p class="error">❌ Plugin non attivo</p>';
    }
    
    // Verifica costanti
    echo '<h3>Costanti definite:</h3>';
    $constants = array('WSP_VERSION', 'WSP_PLUGIN_DIR', 'WSP_PLUGIN_URL', 'WSP_PLUGIN_FILE');
    foreach ($constants as $const) {
        if (defined($const)) {
            echo '<div class="test-item">✅ ' . $const . ' = ' . constant($const) . '</div>';
        } else {
            echo '<div class="test-item">❌ ' . $const . ' non definita</div>';
        }
    }
    echo '</div>';
    
    // 2. Verifica File SaaS
    echo '<div class="diagnostic-box">';
    echo '<h2>2. File SaaS</h2>';
    
    $saas_files = array(
        'includes/class-wsp-database-saas.php' => 'Database SaaS',
        'includes/class-wsp-customers.php' => 'Gestione Clienti',
        'includes/class-wsp-reports.php' => 'Sistema Report',
        'includes/class-wsp-integrations.php' => 'Integrazioni',
        'includes/class-wsp-activity-log.php' => 'Activity Log',
        'admin/class-wsp-admin-test-saas.php' => 'Test Suite',
        'assets/js/test-saas.js' => 'JavaScript Test',
        'n8n-workflows/wsp-report-workflow.json' => 'Workflow N8N',
        'install/database-schema.sql' => 'Schema Database'
    );
    
    $plugin_dir = WP_PLUGIN_DIR . '/whatsapp-saas-pro/';
    
    foreach ($saas_files as $file => $desc) {
        $full_path = $plugin_dir . $file;
        if (file_exists($full_path)) {
            $size = filesize($full_path);
            echo '<div class="test-item">✅ ' . $desc . ' (' . $file . ') - ' . round($size/1024, 2) . ' KB</div>';
        } else {
            echo '<div class="test-item">❌ ' . $desc . ' (' . $file . ') - MANCANTE!</div>';
        }
    }
    echo '</div>';
    
    // 3. Verifica Classi Caricate
    echo '<div class="diagnostic-box">';
    echo '<h2>3. Classi PHP Caricate</h2>';
    
    $saas_classes = array(
        'WSP_Database_SaaS' => 'Database Multi-Tenant',
        'WSP_Customers' => 'Gestione Clienti',
        'WSP_Reports' => 'Sistema Report',
        'WSP_Integrations' => 'Integrazioni',
        'WSP_Activity_Log' => 'Activity Log',
        'WSP_Admin_Test_SaaS' => 'Test Suite Admin'
    );
    
    foreach ($saas_classes as $class => $desc) {
        if (class_exists($class)) {
            echo '<div class="test-item">✅ ' . $desc . ' (class ' . $class . ')</div>';
        } else {
            echo '<div class="test-item">❌ ' . $desc . ' (class ' . $class . ') - NON CARICATA!</div>';
        }
    }
    echo '</div>';
    
    // 4. Verifica Tabelle Database
    echo '<div class="diagnostic-box">';
    echo '<h2>4. Tabelle Database SaaS</h2>';
    
    $tables = array(
        'wsp_customers' => 'Clienti/Destinatari',
        'wsp_subscription_plans' => 'Piani Abbonamento',
        'wsp_customer_subscriptions' => 'Sottoscrizioni',
        'wsp_whatsapp_numbers' => 'Numeri WhatsApp',
        'wsp_campaigns' => 'Campagne',
        'wsp_messages' => 'Messaggi',
        'wsp_credits_transactions' => 'Transazioni Crediti',
        'wsp_reports' => 'Report',
        'wsp_activity_log' => 'Log Attività',
        'wsp_webhook_events' => 'Eventi Webhook',
        'wsp_integrations' => 'Integrazioni'
    );
    
    $missing_tables = array();
    $existing_tables = array();
    
    foreach ($tables as $table => $desc) {
        $table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if ($exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<div class="test-item">✅ ' . $desc . ' (' . $table_name . ') - ' . $count . ' record</div>';
            $existing_tables[] = $table_name;
        } else {
            echo '<div class="test-item">❌ ' . $desc . ' (' . $table_name . ') - MANCANTE!</div>';
            $missing_tables[] = $table_name;
        }
    }
    
    if (!empty($missing_tables)) {
        echo '<div style="margin-top:20px;padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">';
        echo '<strong>⚠️ Tabelle mancanti!</strong><br>';
        echo 'Per creare le tabelle mancanti, disattiva e riattiva il plugin, oppure ';
        echo '<button onclick="createTables()" style="margin-top:10px;">Crea Tabelle Ora</button>';
        echo '</div>';
    }
    echo '</div>';
    
    // 5. Verifica Piani Abbonamento
    echo '<div class="diagnostic-box">';
    echo '<h2>5. Piani Abbonamento</h2>';
    
    $plans_table = $wpdb->prefix . 'wsp_subscription_plans';
    if ($wpdb->get_var("SHOW TABLES LIKE '$plans_table'") == $plans_table) {
        $plans = $wpdb->get_results("SELECT * FROM $plans_table");
        if ($plans) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Prezzo</th><th>Crediti</th><th>Attivo</th></tr>';
            foreach ($plans as $plan) {
                echo '<tr>';
                echo '<td>' . $plan->id . '</td>';
                echo '<td>' . $plan->plan_name . '</td>';
                echo '<td>' . $plan->plan_type . '</td>';
                echo '<td>€' . $plan->monthly_price . '</td>';
                echo '<td>' . $plan->credits_included . '</td>';
                echo '<td>' . ($plan->is_active ? '✅' : '❌') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="warning">⚠️ Nessun piano trovato. ';
            echo '<button onclick="insertDefaultPlans()">Inserisci Piani Predefiniti</button></p>';
        }
    } else {
        echo '<p class="error">❌ Tabella piani non esiste</p>';
    }
    echo '</div>';
    
    // 6. Test Funzionalità
    echo '<div class="diagnostic-box">';
    echo '<h2>6. Test Funzionalità Base</h2>';
    
    // Test creazione cliente
    if (class_exists('WSP_Customers')) {
        echo '<div class="test-item">✅ Classe WSP_Customers disponibile</div>';
        
        // Verifica metodi
        $methods = array('register_customer', 'activate_customer', 'get_customer_by_email', 'verify_api_key');
        foreach ($methods as $method) {
            if (method_exists('WSP_Customers', $method)) {
                echo '<div class="test-item" style="margin-left:20px;">✅ Metodo ' . $method . '()</div>';
            } else {
                echo '<div class="test-item" style="margin-left:20px;">❌ Metodo ' . $method . '() mancante</div>';
            }
        }
    } else {
        echo '<div class="test-item">❌ Classe WSP_Customers non disponibile</div>';
    }
    echo '</div>';
    
    // 7. Informazioni Sistema
    echo '<div class="diagnostic-box">';
    echo '<h2>7. Informazioni Sistema</h2>';
    echo '<table>';
    echo '<tr><td>PHP Version:</td><td>' . phpversion() . '</td></tr>';
    echo '<tr><td>WordPress Version:</td><td>' . get_bloginfo('version') . '</td></tr>';
    echo '<tr><td>MySQL Version:</td><td>' . $wpdb->db_version() . '</td></tr>';
    echo '<tr><td>Plugin Directory:</td><td>' . WP_PLUGIN_DIR . '/whatsapp-saas-pro/</td></tr>';
    echo '<tr><td>Site URL:</td><td>' . site_url() . '</td></tr>';
    echo '<tr><td>Admin Email:</td><td>' . get_option('admin_email') . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // 8. Azioni Correttive
    echo '<div class="diagnostic-box">';
    echo '<h2>8. Azioni Correttive</h2>';
    
    if (!empty($missing_tables) || empty($existing_tables)) {
        echo '<button onclick="window.location.href=\'?action=create_tables\'" class="button button-primary">🔧 Crea Tabelle Mancanti</button> ';
    }
    
    echo '<button onclick="window.location.href=\'?action=activate_v4\'" class="button">🔄 Attiva Plugin v4</button> ';
    echo '<button onclick="window.location.href=\'?action=clear_cache\'" class="button">🗑️ Pulisci Cache</button> ';
    echo '<button onclick="window.location.reload()" class="button">🔄 Ricarica Pagina</button>';
    
    // Esegui azioni se richieste
    if (isset($_GET['action'])) {
        echo '<div style="margin-top:20px;padding:10px;background:#d4edda;border:1px solid #c3e6cb;border-radius:4px;">';
        
        switch ($_GET['action']) {
            case 'create_tables':
                if (class_exists('WSP_Database_SaaS')) {
                    WSP_Database_SaaS::create_tables();
                    echo '✅ Tabelle create con successo! Ricarica la pagina per verificare.';
                } else {
                    // Carica manualmente la classe
                    $db_file = WP_PLUGIN_DIR . '/whatsapp-saas-pro/includes/class-wsp-database-saas.php';
                    if (file_exists($db_file)) {
                        require_once($db_file);
                        WSP_Database_SaaS::create_tables();
                        echo '✅ Tabelle create con successo! Ricarica la pagina per verificare.';
                    } else {
                        echo '❌ File class-wsp-database-saas.php non trovato!';
                    }
                }
                break;
                
            case 'activate_v4':
                deactivate_plugins('whatsapp-saas-pro/whatsapp-saas-plugin.php');
                activate_plugin('whatsapp-saas-pro/whatsapp-saas-plugin-v4.php');
                echo '✅ Plugin v4 attivato!';
                break;
                
            case 'clear_cache':
                wp_cache_flush();
                echo '✅ Cache pulita!';
                break;
        }
        
        echo '</div>';
    }
    echo '</div>';
    
    // 9. File di configurazione principale
    echo '<div class="diagnostic-box">';
    echo '<h2>9. Verifica File Plugin Principale</h2>';
    
    $main_files = array(
        'whatsapp-saas-plugin-v4.php',
        'whatsapp-saas-plugin.php'
    );
    
    foreach ($main_files as $file) {
        $full_path = WP_PLUGIN_DIR . '/whatsapp-saas-pro/' . $file;
        if (file_exists($full_path)) {
            echo '<h3>' . $file . '</h3>';
            echo '<pre style="max-height:200px;overflow-y:auto;">';
            
            // Mostra prime 30 righe del file
            $lines = file($full_path);
            for ($i = 0; $i < min(30, count($lines)); $i++) {
                echo htmlspecialchars($lines[$i]);
            }
            
            echo '</pre>';
            
            // Cerca le inclusioni delle classi SaaS
            $content = file_get_contents($full_path);
            if (strpos($content, 'class-wsp-database-saas.php') !== false) {
                echo '<p class="success">✅ Include class-wsp-database-saas.php</p>';
            } else {
                echo '<p class="error">❌ NON include class-wsp-database-saas.php</p>';
            }
            
            if (strpos($content, 'class-wsp-customers.php') !== false) {
                echo '<p class="success">✅ Include class-wsp-customers.php</p>';
            } else {
                echo '<p class="error">❌ NON include class-wsp-customers.php</p>';
            }
        } else {
            echo '<p class="error">❌ File ' . $file . ' non trovato</p>';
        }
    }
    echo '</div>';
    ?>
    
    <script>
    function createTables() {
        if (confirm('Vuoi creare le tabelle mancanti?')) {
            window.location.href = '?action=create_tables';
        }
    }
    
    function insertDefaultPlans() {
        if (confirm('Vuoi inserire i piani predefiniti?')) {
            window.location.href = '?action=insert_plans';
        }
    }
    </script>
</body>
</html>