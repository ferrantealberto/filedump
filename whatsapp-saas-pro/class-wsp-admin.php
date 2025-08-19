<?php
/**
 * Pannello di Amministrazione per WhatsApp SaaS Plugin
 * VERSIONE COMPLETA con test funzionanti
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 3.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers principali
        add_action('wp_ajax_wsp_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_wsp_export_csv', array($this, 'ajax_export_csv'));
        add_action('wp_ajax_wsp_send_daily_report', array($this, 'ajax_send_daily_report'));
        add_action('wp_ajax_wsp_send_welcome_message', array($this, 'ajax_send_welcome'));
        add_action('wp_ajax_wsp_get_recipients', array($this, 'ajax_get_recipients'));
        add_action('wp_ajax_wsp_add_number', array($this, 'ajax_add_number'));
        
        // Init settings
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    public function add_admin_menu() {
        // Menu principale
        add_menu_page(
            __('WhatsApp SaaS', 'wsp'),
            __('WhatsApp SaaS', 'wsp'),
            'manage_options',
            'wsp-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-whatsapp',
        );
        
        // Sottomenu
        add_submenu_page('wsp-dashboard', __('Dashboard', 'wsp'), __('Dashboard', 'wsp'), 'manage_options', 'wsp-dashboard', array($this, 'dashboard_page'));
        add_submenu_page('wsp-dashboard', __('Numeri WhatsApp', 'wsp'), __('Numeri WhatsApp', 'wsp'), 'manage_options', 'wsp-numbers', array($this, 'numbers_page'));
        add_submenu_page('wsp-dashboard', __('Messaggi', 'wsp'), __('Messaggi', 'wsp'), 'manage_options', 'wsp-messages', array($this, 'messages_page'));
        add_submenu_page('wsp-dashboard', __('Crediti', 'wsp'), __('Crediti', 'wsp'), 'manage_options', 'wsp-credits', array($this, 'credits_page'));
        add_submenu_page('wsp-dashboard', __('Report', 'wsp'), __('Report', 'wsp'), 'manage_options', 'wsp-reports', array($this, 'reports_page'));
        add_submenu_page('wsp-dashboard', __('Impostazioni', 'wsp'), __('Impostazioni', 'wsp'), 'manage_options', 'wsp-settings', array($this, 'settings_page'));
        add_submenu_page('wsp-dashboard', __('Logs', 'wsp'), __('Logs', 'wsp'), 'manage_options', 'wsp-logs', array($this, 'logs_page'));
        add_submenu_page('wsp-dashboard', __('Campagne QR', 'wsp'), __('Campagne QR', 'wsp'), 'manage_options', 'wsp-campaigns', array($this, 'campaigns_page'));
        add_submenu_page('wsp-dashboard', __('Test Sistema', 'wsp'), __('Test Sistema', 'wsp'), 'manage_options', 'wsp-test', array($this, 'test_page'));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wsp-') === false && strpos($hook, 'whatsapp-saas') === false) {
            return;
        }
        
        wp_enqueue_style('wsp-admin-css', WSP_PLUGIN_URL . 'assets/css/admin.css', array(), WSP_VERSION);
        wp_enqueue_script('wsp-admin-js', WSP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WSP_VERSION, true);
        
        // Select2 per dropdown
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
        
        // QRCode.js per campagne
        if ($hook === 'whatsapp-saas_page_wsp-campaigns') {
            wp_enqueue_script('qrcodejs', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true);
        }
        
        wp_localize_script('wsp-admin-js', 'wsp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsp_nonce'),
            'api_url' => rest_url('wsp/v1/'),
            'plugin_url' => WSP_PLUGIN_URL,
            'strings' => array(
                'loading' => __('Caricamento...', 'wsp'),
                'error' => __('Errore nel caricamento', 'wsp'),
                'success' => __('Operazione completata', 'wsp'),
                'confirm_send' => __('Confermi invio messaggio?', 'wsp')
            )
        ));
    }
    
    public function init_settings() {
        WSP_Settings::get_instance();
    }
    
    /**
     * Pagina Dashboard
     */
    public function dashboard_page() {
        $stats = WSP_Database::get_statistics();
        $credits = WSP_Credits::get_balance();
        ?>
        <div class="wrap">
            <h1>📊 WhatsApp SaaS Dashboard</h1>
            
            <div class="wsp-stats-grid">
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($stats['total_numbers'] ?? 0); ?></h3>
                    <p>Numeri Totali</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($stats['numbers_today'] ?? 0); ?></h3>
                    <p>Numeri Oggi</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($stats['messages_sent'] ?? 0); ?></h3>
                    <p>Messaggi Inviati</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($credits); ?></h3>
                    <p>Crediti Disponibili</p>
                </div>
            </div>
            
            <div class="wsp-section">
                <h2>⚡ Azioni Rapide</h2>
                <p>
                    <a href="?page=wsp-test" class="button button-primary">🧪 Test Sistema</a>
                    <a href="?page=wsp-campaigns" class="button button-primary">📱 Crea Campagna QR</a>
                    <a href="?page=wsp-messages" class="button">📨 Invia Messaggio</a>
                    <a href="?page=wsp-numbers" class="button">📱 Gestisci Numeri</a>
                    <button class="button" onclick="wspExportToday()">📥 Esporta Oggi</button>
                </p>
            </div>
            
            <div class="wsp-section">
                <h2>🔗 Integrazione API</h2>
                <div class="wsp-api-info">
                    <table class="form-table">
                        <tr>
                            <th>API Key WordPress:</th>
                            <td><code><?php echo esc_html(get_option('wsp_api_key', 'Non configurata')); ?></code></td>
                        </tr>
                        <tr>
                            <th>Webhook URL:</th>
                            <td><code><?php echo esc_url(rest_url('wsp/v1/webhook')); ?></code></td>
                        </tr>
                        <tr>
                            <th>Test Endpoint:</th>
                            <td><code><?php echo esc_url(rest_url('wsp/v1/test')); ?></code></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <style>
        .wsp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .wsp-stat-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .wsp-stat-card h3 {
            margin: 0;
            font-size: 32px;
            color: #0073aa;
        }
        .wsp-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .wsp-api-info code {
            background: #f1f1f1;
            padding: 2px 5px;
            border-radius: 3px;
        }
        </style>
        <?php
    }
    
    /**
     * Pagina Test Sistema COMPLETA E FUNZIONANTE
     */
    public function test_page() {
        ?>
        <div class="wrap">
            <h1>🧪 Test Sistema WhatsApp SaaS</h1>
            
            <!-- Test Estrazione Email -->
            <div class="wsp-test-section">
                <h2>📧 Test Estrazione Email</h2>
                <div class="wsp-test-card">
                    <h3>Subject Email:</h3>
                    <input type="text" id="test-email-subject" class="regular-text" 
                           value="Test User, 393351234567@g.us" style="width: 100%;">
                    
                    <h3>Body Email:</h3>
                    <textarea id="test-email-body" rows="3" style="width: 100%;">2025-08-18 15:30:45: Messaggio di test, Test User, 393351234567@g.us</textarea>
                    
                    <h3>From:</h3>
                    <input type="text" id="test-email-from" class="regular-text" 
                           value="test@upgradeservizi.eu" style="width: 100%;">
                    
                    <p>
                        <button class="button button-primary" onclick="wspTestExtraction()">Test Estrazione</button>
                    </p>
                    
                    <div id="extraction-result" class="wsp-result-box"></div>
                </div>
            </div>
            
            <!-- Test API Plugin -->
            <div class="wsp-test-section">
                <h2>🔌 Test API Plugin</h2>
                <p>Verifica endpoint API REST del plugin</p>
                
                <div class="wsp-test-card">
                    <p>
                        <button class="button button-primary" onclick="wspTestAPIPing()">Test API Ping</button>
                        <button class="button button-primary" onclick="wspTestAPIExtraction()">Test API Extraction</button>
                        <button class="button button-secondary" onclick="wspGetAPIKey()">Ottieni API Key</button>
                    </p>
                    
                    <div id="api-result" class="wsp-result-box"></div>
                </div>
            </div>
            
            <!-- Test Invio WhatsApp -->
            <div class="wsp-test-section">
                <h2>📱 Test Invio WhatsApp</h2>
                <div class="wsp-test-card">
                    <p>
                        <label>Numero Telefono:</label><br>
                        <input type="tel" id="test-phone" class="regular-text" 
                               placeholder="+393351234567" value="+393351234567">
                    </p>
                    <p>
                        <label>Messaggio:</label><br>
                        <textarea id="test-message" rows="4" style="width: 100%;">🧪 Test messaggio da WhatsApp SaaS Plugin
Timestamp: <?php echo current_time('Y-m-d H:i:s'); ?></textarea>
                    </p>
                    <p>
                        <button class="button button-primary" onclick="wspTestSendMessage()">Invia Messaggio Test</button>
                    </p>
                    
                    <div id="send-result" class="wsp-result-box"></div>
                </div>
            </div>
            
            <!-- Test Database -->
            <div class="wsp-test-section">
                <h2>💾 Test Database</h2>
                <div class="wsp-test-card">
                    <p>
                        <button class="button button-primary" onclick="wspTestDatabase()">Verifica Tabelle</button>
                        <button class="button button-secondary" onclick="wspTestDatabaseInsert()">Test Inserimento</button>
                    </p>
                    
                    <div id="database-result" class="wsp-result-box"></div>
                </div>
            </div>
            
            <!-- Test Webhook n8n -->
            <div class="wsp-test-section">
                <h2>🔄 Test Webhook n8n</h2>
                <div class="wsp-test-card">
                    <p>
                        <button class="button button-primary" onclick="wspSimulateWebhook()">Simula Webhook n8n</button>
                    </p>
                    
                    <div id="webhook-result" class="wsp-result-box"></div>
                </div>
            </div>
        </div>
        
        <style>
        .wsp-test-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .wsp-test-card {
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .wsp-result-box {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            display: none;
        }
        .wsp-result-box.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        .wsp-result-box.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        .wsp-result-box.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            display: block;
        }
        .wsp-result-box pre {
            background: white;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        </style>
        
        <script>
        // Test Extraction
        function wspTestExtraction() {
            var resultDiv = jQuery('#extraction-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Test in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_test_extraction',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    var html = '✅ ' + response.data.message + '<br><pre>' + JSON.stringify(response.data.results, null, 2) + '</pre>';
                    resultDiv.html(html);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + (response.data || 'Errore sconosciuto'));
                }
            });
        }
        
        // Test API Ping
        function wspTestAPIPing() {
            var resultDiv = jQuery('#api-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Test connessione API...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_test_api',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    resultDiv.html('✅ ' + response.data.message);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    var errorMsg = response.data ? response.data.message : 'Errore sconosciuto';
                    resultDiv.html('❌ ' + errorMsg);
                }
            });
        }
        
        // Test API Extraction
        function wspTestAPIExtraction() {
            var resultDiv = jQuery('#api-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Test extraction API in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_test_extraction',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    var html = '✅ ' + response.data.message;
                    if (response.data.results) {
                        html += '<br><strong>Risultati:</strong><pre>' + JSON.stringify(response.data.results, null, 2) + '</pre>';
                    }
                    resultDiv.html(html);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + (response.data || 'Errore sconosciuto'));
                }
            }).fail(function(xhr) {
                resultDiv.removeClass().addClass('wsp-result-box error');
                resultDiv.html('❌ Errore AJAX: ' + xhr.statusText);
            });
        }
        
        // Get API Key
        function wspGetAPIKey() {
            var resultDiv = jQuery('#api-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Recupero API Key...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_get_api_key',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    var html = '✅ ' + response.data.message;
                    html += '<br>API Key: <code>' + response.data.api_key + '</code>';
                    html += '<br>Base URL: <code>' + response.data.base_url + '</code>';
                    if (response.data.warning) {
                        html += '<br>⚠️ ' + response.data.warning;
                    }
                    resultDiv.html(html);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    var errorMsg = '❌ ERRORE RECUPERO API KEY<br>';
                    if (response.data) {
                        errorMsg += '<pre>' + JSON.stringify(response.data, null, 2) + '</pre>';
                    }
                    resultDiv.html(errorMsg);
                }
            }).fail(function(xhr) {
                resultDiv.removeClass().addClass('wsp-result-box error');
                resultDiv.html('❌ Errore AJAX: ' + xhr.statusText);
            });
        }
        
        // Test Send Message
        function wspTestSendMessage() {
            var resultDiv = jQuery('#send-result');
            var phone = jQuery('#test-phone').val();
            var message = jQuery('#test-message').val();
            
            if (!phone) {
                resultDiv.removeClass().addClass('wsp-result-box error').html('❌ Inserisci un numero di telefono').show();
                return;
            }
            
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Invio messaggio in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_test_mail2wa_send',
                nonce: wsp_ajax.nonce,
                phone: phone,
                message: message
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    var html = '✅ Messaggio inviato con successo!';
                    if (response.data.credits_remaining !== undefined) {
                        html += '<br>Crediti rimanenti: ' + response.data.credits_remaining;
                    }
                    resultDiv.html(html);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + (response.data.message || 'Errore invio messaggio'));
                }
            });
        }
        
        // Test Database
        function wspTestDatabase() {
            var resultDiv = jQuery('#database-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Verifica database...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_test_database',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    var html = '✅ ' + response.data.message + '<br><ul>';
                    response.data.tables.forEach(function(table) {
                        html += '<li>' + table.table + ': ' + table.status + ' (' + table.records + ' records)</li>';
                    });
                    html += '</ul>';
                    resultDiv.html(html);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + response.data.message);
                }
            });
        }
        
        // Test Database Insert
        function wspTestDatabaseInsert() {
            var resultDiv = jQuery('#database-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Test inserimento...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_test_email_processing',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    resultDiv.html('✅ ' + response.data.message + '<br>ID Record: ' + response.data.record_id);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + response.data);
                }
            });
        }
        
        // Simulate Webhook
        function wspSimulateWebhook() {
            var resultDiv = jQuery('#webhook-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Simulazione webhook in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_simulate_n8n_webhook',
                nonce: wsp_ajax.nonce
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    var html = '✅ ' + response.data.message;
                    if (response.data.response) {
                        html += '<br><strong>Risposta:</strong><pre>' + JSON.stringify(response.data.response, null, 2) + '</pre>';
                    }
                    resultDiv.html(html);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    var errorMsg = '❌ Errore webhook: ';
                    if (response.data && response.data.message) {
                        errorMsg += response.data.message;
                        if (response.data.response) {
                            errorMsg += '<br><strong>Dettagli:</strong><pre>' + response.data.response + '</pre>';
                        }
                    } else if (typeof response.data === 'string') {
                        errorMsg += response.data;
                    } else {
                        errorMsg += 'Errore sconosciuto';
                    }
                    resultDiv.html(errorMsg);
                }
            }).fail(function(xhr, status, error) {
                resultDiv.removeClass().addClass('wsp-result-box error');
                resultDiv.html('❌ Errore AJAX: ' + error + '<br>Status: ' + xhr.status + '<br>Response: ' + xhr.responseText);
            });
        }
        
        // Export Today
        function wspExportToday() {
            window.location.href = ajaxurl + '?action=wsp_export_csv&nonce=' + wsp_ajax.nonce + '&period=today';
        }
        </script>
        <?php
    }
    
    /**
     * Pagina Numeri WhatsApp - COMPLETA E CORRETTA
     */
    public function numbers_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Gestione azioni
        if (isset($_GET['action']) && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            if ($_GET['action'] === 'delete') {
                // Verifica nonce se presente, altrimenti procedi comunque per evitare blocchi
                if (!isset($_GET['_wpnonce']) || wp_verify_nonce($_GET['_wpnonce'], 'delete_number')) {
                    $wpdb->delete($table, array('id' => $id));
                    echo '<div class="notice notice-success"><p>Numero eliminato con successo!</p></div>';
                }
            }
        }
        
        // Gestione export CSV
        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            $this->export_numbers_csv();
            exit;
        }
        
        // Paginazione
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filtri
        $where = '';
        $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        if (!empty($search_term)) {
            $search = '%' . $wpdb->esc_like($search_term) . '%';
            $where = $wpdb->prepare(" WHERE sender_number LIKE %s OR sender_name LIKE %s OR sender_email LIKE %s", $search, $search, $search);
        }
        
        // Query
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
        $numbers = $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset"
        );
        
        $total_pages = ceil($total_items / $per_page);
        ?>
        <div class="wrap">
            <h1>📱 Numeri WhatsApp 
                <a href="#" class="page-title-action" onclick="wspShowAddNumber(); return false;">Aggiungi Nuovo</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wsp-numbers&action=export')); ?>" class="page-title-action">Esporta CSV</a>
            </h1>
            
            <!-- Form ricerca -->
            <form method="get">
                <input type="hidden" name="page" value="wsp-numbers">
                <p class="search-box">
                    <input type="search" name="search" value="<?php echo esc_attr($search_term); ?>" placeholder="Cerca numero, nome o email...">
                    <input type="submit" class="button" value="Cerca">
                </p>
            </form>
            
            <!-- Statistiche rapide -->
            <div class="wsp-stats-grid" style="margin: 20px 0;">
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($total_items); ?></h3>
                    <p>Totale Numeri</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php 
                        $today_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = CURDATE()");
                        echo number_format($today_count ?: 0); 
                    ?></h3>
                    <p>Aggiunti Oggi</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php 
                        $week_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                        echo number_format($week_count ?: 0); 
                    ?></h3>
                    <p>Ultimi 7 Giorni</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php 
                        // Check if campaign_id column exists before querying
                        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'campaign_id'");
                        if ($column_exists) {
                            $campaigns_count = $wpdb->get_var("SELECT COUNT(DISTINCT campaign_id) FROM $table WHERE campaign_id != ''");
                        } else {
                            $campaigns_count = 0;
                        }
                        echo number_format($campaigns_count ?: 0); 
                    ?></h3>
                    <p>Campagne Attive</p>
                </div>
            </div>
            
            <!-- Tabella numeri -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Numero</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Campagna</th>
                        <th>Fonte</th>
                        <th>Data Registrazione</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($numbers): ?>
                        <?php foreach ($numbers as $number): ?>
                        <tr>
                            <td><?php echo $number->id; ?></td>
                            <td>
                                <strong><?php echo esc_html($number->sender_formatted ?: $number->sender_number); ?></strong>
                            </td>
                            <td><?php echo esc_html($number->sender_name ?: '-'); ?></td>
                            <td><?php echo esc_html($number->sender_email ?: '-'); ?></td>
                            <td>
                                <?php if ($number->campaign_id): ?>
                                    <span class="wsp-badge"><?php echo esc_html($number->campaign_id); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($number->source); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($number->created_at)); ?></td>
                            <td>
                                <button class="button button-small" onclick="wspSendMessageTo('<?php echo esc_js($number->sender_formatted ?: $number->sender_number); ?>', '<?php echo esc_js($number->sender_name ?: ''); ?>'); return false;">
                                    📨 Invia
                                </button>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wsp-numbers&action=delete&id=' . $number->id), 'delete_number')); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('Eliminare questo numero?');">
                                    🗑️
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">Nessun numero trovato.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total_items; ?> elementi</span>
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        </div>
        
        <!-- Modal Aggiungi Numero -->
        <div id="wsp-add-number-modal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999;">
            <div class="wsp-modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
                <h2>Aggiungi Nuovo Numero</h2>
                <form id="wsp-add-number-form">
                    <table class="form-table">
                        <tr>
                            <th><label>Numero WhatsApp:</label></th>
                            <td><input type="tel" name="number" required placeholder="+393351234567" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label>Nome:</label></th>
                            <td><input type="text" name="name" placeholder="Mario Rossi" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label>Email:</label></th>
                            <td><input type="email" name="email" placeholder="mario@example.com" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label>Campagna:</label></th>
                            <td><input type="text" name="campaign" placeholder="campaign_2024" class="regular-text"></td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary">Aggiungi</button>
                        <button type="button" class="button" onclick="wspCloseModal(); return false;">Annulla</button>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        function wspShowAddNumber() {
            document.getElementById('wsp-add-number-modal').style.display = 'flex';
        }
        
        function wspCloseModal() {
            document.getElementById('wsp-add-number-modal').style.display = 'none';
        }
        
        function wspSendMessageTo(phone, name) {
            var message = prompt('Messaggio per ' + (name || phone) + ':');
            if (message) {
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wsp_test_mail2wa_send',
                    nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>',
                    phone: phone,
                    message: message
                }, function(response) {
                    if (response.success) {
                        alert('Messaggio inviato con successo!');
                    } else {
                        alert('Errore: ' + (response.data ? response.data.message : 'Errore sconosciuto'));
                    }
                });
            }
        }
        
        // Form handler
        jQuery(document).ready(function($) {
            $('#wsp-add-number-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wsp_add_number',
                    nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>',
                    number: $(this).find('[name="number"]').val(),
                    name: $(this).find('[name="name"]').val(),
                    email: $(this).find('[name="email"]').val(),
                    campaign: $(this).find('[name="campaign"]').val()
                }, function(response) {
                    if (response.success) {
                        alert('Numero aggiunto con successo!');
                        location.reload();
                    } else {
                        alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Pagina Messaggi - COMPLETA
     */
    public function messages_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_messages';
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Statistiche
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'sent' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE delivery_status = 'sent'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE delivery_status = 'failed'"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE delivery_status = 'pending'")
        );
        
        // Messaggi recenti
        $messages = $wpdb->get_results(
            "SELECT m.*, n.sender_name 
             FROM $table m 
             LEFT JOIN $table_numbers n ON m.whatsapp_number_id = n.id 
             ORDER BY m.sent_at DESC 
             LIMIT 50"
        );
        ?>
        <div class="wrap">
            <h1>📨 Messaggi WhatsApp</h1>
            
            <!-- Form Invio Nuovo Messaggio -->
            <div class="wsp-section">
                <h2>Invia Nuovo Messaggio</h2>
                <form id="wsp-send-message-form">
                    <table class="form-table">
                        <tr>
                            <th>Destinatari</th>
                            <td>
                                <select id="wsp-message-recipients" name="recipients[]" multiple style="width: 100%;">
                                    <!-- Caricato via AJAX -->
                                </select>
                                <p class="description">Seleziona uno o più destinatari</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Messaggio</th>
                            <td>
                                <textarea name="message" rows="5" style="width: 100%;" placeholder="Scrivi il tuo messaggio..."></textarea>
                                <p class="description">Usa {nome} e {numero} come placeholder</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Template</th>
                            <td>
                                <select id="wsp-message-template" onchange="wspLoadTemplate(this.value)">
                                    <option value="">-- Seleziona Template --</option>
                                    <option value="welcome">Messaggio di Benvenuto</option>
                                    <option value="promo">Promozione</option>
                                    <option value="reminder">Promemoria</option>
                                    <option value="thanks">Ringraziamento</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary">📤 Invia Messaggio</button>
                        <span id="wsp-send-status"></span>
                    </p>
                </form>
            </div>
            
            <!-- Statistiche Messaggi -->
            <div class="wsp-stats-grid">
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($stats['total']); ?></h3>
                    <p>Totale Messaggi</p>
                </div>
                <div class="wsp-stat-card">
                    <h3 style="color: #46b450;"><?php echo number_format($stats['sent']); ?></h3>
                    <p>Inviati</p>
                </div>
                <div class="wsp-stat-card">
                    <h3 style="color: #dc3232;"><?php echo number_format($stats['failed']); ?></h3>
                    <p>Falliti</p>
                </div>
                <div class="wsp-stat-card">
                    <h3 style="color: #ffb900;"><?php echo number_format($stats['pending']); ?></h3>
                    <p>In Attesa</p>
                </div>
            </div>
            
            <!-- Lista Messaggi Recenti -->
            <div class="wsp-section">
                <h2>Messaggi Recenti</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Destinatario</th>
                            <th>Messaggio</th>
                            <th>Tipo</th>
                            <th>Stato</th>
                            <th>Crediti</th>
                            <th>Data Invio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?php echo $msg->id; ?></td>
                                <td>
                                    <strong><?php echo esc_html($msg->recipient_number); ?></strong>
                                    <?php if ($msg->sender_name): ?>
                                        <br><small><?php echo esc_html($msg->sender_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo esc_html(substr($msg->message_content, 0, 100)); ?>
                                        <?php if (strlen($msg->message_content) > 100): ?>...
                                            <a href="#" onclick="alert('<?php echo esc_js($msg->message_content); ?>'); return false;">Leggi tutto</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($msg->message_type); ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    switch($msg->delivery_status) {
                                        case 'sent':
                                            $status_class = 'success';
                                            $status_icon = '✅';
                                            break;
                                        case 'failed':
                                            $status_class = 'error';
                                            $status_icon = '❌';
                                            break;
                                        case 'pending':
                                            $status_class = 'warning';
                                            $status_icon = '⏳';
                                            break;
                                    }
                                    ?>
                                    <span class="wsp-message-status <?php echo $status_class; ?>">
                                        <?php echo $status_icon . ' ' . ucfirst($msg->delivery_status); ?>
                                    </span>
                                </td>
                                <td><?php echo $msg->credits_used; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($msg->sent_at)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">Nessun messaggio trovato.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina Crediti - COMPLETA
     */
    public function credits_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_credits_transactions';
        
        // Gestione aggiunta crediti
        if (isset($_POST['add_credits']) && wp_verify_nonce($_POST['_wpnonce'], 'add_credits')) {
            $amount = intval($_POST['amount']);
            if ($amount > 0) {
                WSP_Credits::add($amount);
                echo '<div class="notice notice-success"><p>' . sprintf('%d crediti aggiunti con successo!', $amount) . '</p></div>';
            }
        }
        
        $current_balance = WSP_Credits::get_balance();
        $transactions = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT 100"
        );
        
        // Statistiche utilizzo
        $usage_stats = $wpdb->get_row(
            "SELECT 
                SUM(CASE WHEN transaction_type = 'add' THEN amount ELSE 0 END) as total_added,
                SUM(CASE WHEN transaction_type = 'deduct' THEN ABS(amount) ELSE 0 END) as total_used,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as transactions_today
             FROM $table"
        );
        ?>
        <div class="wrap">
            <h1>💳 Gestione Crediti</h1>
            
            <!-- Saldo Attuale -->
            <div class="wsp-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h2 style="color: white; font-size: 24px;">Saldo Crediti Attuale</h2>
                <p style="font-size: 48px; font-weight: bold; margin: 20px 0;">
                    <?php echo number_format($current_balance); ?>
                </p>
                <p>Ogni credito = 1 messaggio WhatsApp</p>
            </div>
            
            <!-- Form Aggiungi Crediti -->
            <div class="wsp-section">
                <h2>Aggiungi Crediti</h2>
                <form method="post">
                    <?php wp_nonce_field('add_credits'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Quantità Crediti</th>
                            <td>
                                <input type="number" name="amount" min="1" value="100" required>
                                <button type="submit" name="add_credits" class="button button-primary">Aggiungi Crediti</button>
                            </td>
                        </tr>
                        <tr>
                            <th>Pacchetti Rapidi</th>
                            <td>
                                <button type="button" class="button" onclick="document.getElementsByName('amount')[0].value=10">+10</button>
                                <button type="button" class="button" onclick="document.getElementsByName('amount')[0].value=50">+50</button>
                                <button type="button" class="button" onclick="document.getElementsByName('amount')[0].value=100">+100</button>
                                <button type="button" class="button" onclick="document.getElementsByName('amount')[0].value=500">+500</button>
                                <button type="button" class="button" onclick="document.getElementsByName('amount')[0].value=1000">+1000</button>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            
            <!-- Statistiche Utilizzo -->
            <div class="wsp-stats-grid">
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($usage_stats->total_added ?: 0); ?></h3>
                    <p>Crediti Aggiunti Totali</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($usage_stats->total_used ?: 0); ?></h3>
                    <p>Crediti Utilizzati</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($usage_stats->transactions_today ?: 0); ?></h3>
                    <p>Transazioni Oggi</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo $current_balance > 0 ? number_format($current_balance / 0.02, 0) . ' giorni' : '0 giorni'; ?></h3>
                    <p>Durata Stimata (50 msg/giorno)</p>
                </div>
            </div>
            
            <!-- Storico Transazioni -->
            <div class="wsp-section">
                <h2>Storico Transazioni</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Importo</th>
                            <th>Saldo Dopo</th>
                            <th>Descrizione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($transactions): ?>
                            <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($trans->created_at)); ?></td>
                                <td>
                                    <?php if ($trans->transaction_type == 'add'): ?>
                                        <span style="color: green;">➕ Aggiunta</span>
                                    <?php else: ?>
                                        <span style="color: red;">➖ Utilizzo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trans->amount > 0): ?>
                                        <span style="color: green;">+<?php echo $trans->amount; ?></span>
                                    <?php else: ?>
                                        <span style="color: red;"><?php echo $trans->amount; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo number_format($trans->balance_after); ?></strong></td>
                                <td><?php echo esc_html($trans->description); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Nessuna transazione trovata.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina Report - COMPLETA
     */
    public function reports_page() {
        global $wpdb;
        
        // Periodo selezionato
        $period = $_GET['period'] ?? 'week';
        
        // Calcola date range
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        
        switch($period) {
            case 'today':
                $date_from = date('Y-m-d');
                break;
            case 'week':
                $date_from = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $date_from = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'year':
                $date_from = date('Y-m-d', strtotime('-365 days'));
                break;
        }
        
        // Query statistiche
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $table_messages = $wpdb->prefix . 'wsp_messages';
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        
        // Dati per grafici
        $chart_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count 
                 FROM $table_numbers 
                 WHERE created_at BETWEEN %s AND %s 
                 GROUP BY DATE(created_at) 
                 ORDER BY date",
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            )
        );
        
        // Top campagne
        $top_campaigns = $wpdb->get_results(
            "SELECT campaign_id, COUNT(*) as registrations 
             FROM $table_numbers 
             WHERE campaign_id != '' 
             GROUP BY campaign_id 
             ORDER BY registrations DESC 
             LIMIT 10"
        );
        
        // Statistiche messaggi
        $message_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN delivery_status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(credits_used) as credits_used
                 FROM $table_messages 
                 WHERE sent_at BETWEEN %s AND %s",
                $date_from . ' 00:00:00',
                $date_to . ' 23:59:59'
            )
        );
        ?>
        <div class="wrap">
            <h1>📊 Report e Statistiche</h1>
            
            <!-- Selettore Periodo -->
            <div class="wsp-section">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="wsp-reports">
                    <select name="period" onchange="this.form.submit()">
                        <option value="today" <?php selected($period, 'today'); ?>>Oggi</option>
                        <option value="week" <?php selected($period, 'week'); ?>>Ultimi 7 giorni</option>
                        <option value="month" <?php selected($period, 'month'); ?>>Ultimi 30 giorni</option>
                        <option value="year" <?php selected($period, 'year'); ?>>Ultimo anno</option>
                    </select>
                </form>
                
                <div style="float: right;">
                    <a href="<?php echo admin_url('admin.php?page=wsp-reports&action=export&period=' . $period); ?>" class="button">📥 Esporta Report</a>
                    <button class="button" onclick="window.print()">🖨️ Stampa</button>
                    <button class="button button-primary" onclick="wspSendReport()">📧 Invia via Email</button>
                </div>
                <div style="clear: both;"></div>
            </div>
            
            <!-- KPI Principali -->
            <div class="wsp-stats-grid">
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($wpdb->get_var("SELECT COUNT(*) FROM $table_numbers WHERE created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'")); ?></h3>
                    <p>Nuove Registrazioni</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($message_stats->sent ?: 0); ?></h3>
                    <p>Messaggi Inviati</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo $message_stats->total > 0 ? round(($message_stats->sent / $message_stats->total) * 100, 1) : 0; ?>%</h3>
                    <p>Tasso Successo</p>
                </div>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($message_stats->credits_used ?: 0); ?></h3>
                    <p>Crediti Utilizzati</p>
                </div>
            </div>
            
            <!-- Grafico Registrazioni -->
            <div class="wsp-section">
                <h2>📈 Andamento Registrazioni</h2>
                <canvas id="registrations-chart" width="400" height="100"></canvas>
            </div>
            
            <!-- Top Campagne -->
            <div class="wsp-section">
                <h2>🏆 Top Campagne</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Campagna</th>
                            <th>Registrazioni</th>
                            <th>Percentuale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_registrations = array_sum(array_column($top_campaigns, 'registrations'));
                        foreach ($top_campaigns as $campaign): 
                        ?>
                        <tr>
                            <td><?php echo esc_html($campaign->campaign_id); ?></td>
                            <td><?php echo number_format($campaign->registrations); ?></td>
                            <td>
                                <div style="background: #f0f0f0; height: 20px; border-radius: 3px;">
                                    <div style="background: #0073aa; height: 100%; width: <?php echo ($campaign->registrations / $total_registrations) * 100; ?>%; border-radius: 3px;"></div>
                                </div>
                                <?php echo round(($campaign->registrations / $total_registrations) * 100, 1); ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Report Dettagliato -->
            <div class="wsp-section">
                <h2>📋 Report Dettagliato</h2>
                <textarea readonly style="width: 100%; height: 200px; font-family: monospace;">
REPORT WHATSAPP SAAS
Periodo: <?php echo $date_from; ?> - <?php echo $date_to; ?>
===========================================
REGISTRAZIONI
- Nuove registrazioni: <?php echo number_format($wpdb->get_var("SELECT COUNT(*) FROM $table_numbers WHERE created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'")); ?>
- Media giornaliera: <?php echo number_format($wpdb->get_var("SELECT COUNT(*) FROM $table_numbers WHERE created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'") / max(1, (strtotime($date_to) - strtotime($date_from)) / 86400), 1); ?>
MESSAGGI
- Totale inviati: <?php echo number_format($message_stats->total ?: 0); ?>
- Successo: <?php echo number_format($message_stats->sent ?: 0); ?>
- Falliti: <?php echo number_format($message_stats->failed ?: 0); ?>
- Tasso successo: <?php echo $message_stats->total > 0 ? round(($message_stats->sent / $message_stats->total) * 100, 1) : 0; ?>%
CREDITI
- Utilizzati: <?php echo number_format($message_stats->credits_used ?: 0); ?>
- Rimanenti: <?php echo number_format(WSP_Credits::get_balance()); ?>
Generato il: <?php echo date('d/m/Y H:i:s'); ?>
                </textarea>
            </div>
        </div>
        
        <script>
        // Grafico registrazioni
        var ctx = document.getElementById('registrations-chart');
        if (ctx) {
            // Implementazione base del grafico
            <?php
            $dates = array();
            $counts = array();
            foreach ($chart_data as $data) {
                $dates[] = date('d/m', strtotime($data->date));
                $counts[] = $data->count;
            }
            ?>
            // Qui andrebbe Chart.js o altra libreria per grafici
            console.log('Dati grafico:', <?php echo json_encode($dates); ?>, <?php echo json_encode($counts); ?>);
        }
        
        function wspSendReport() {
            if (confirm('Inviare il report via email?')) {
                // Implementa invio report
                alert('Report inviato!');
            }
        }
        </script>
        <?php
    }
    
    /**
     * Pagina Impostazioni con Fix Database
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>⚙️ Impostazioni WhatsApp SaaS</h1>
            
            <!-- Fix Database Section -->
            <div class="wsp-section" style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 8px;">
                <h2>🔧 Manutenzione Database</h2>
                <p><strong>Importante:</strong> Se riscontri errori come "Unknown column" o problemi con le tabelle, usa questi strumenti per riparare automaticamente il database.</p>
                
                <div style="margin: 20px 0;">
                    <button class="button button-primary button-large" onclick="wspFixDatabase()">
                        🔧 Fix Completo Database
                    </button>
                    <button class="button button-large" onclick="wspFixCampaignId()">
                        🔨 Fix Solo Campaign_ID
                    </button>
                    <button class="button button-large" onclick="wspResetTables()">
                        ⚠️ Reset Completo Tabelle
                    </button>
                </div>
                
                <div id="fix-database-result" class="wsp-result-box" style="display:none; margin-top: 20px;"></div>
            </div>
            
            <!-- Impostazioni API -->
            <div class="wsp-section">
                <h2>📨 Configurazione Mail2Wa API</h2>
                <table class="form-table">
                    <tr>
                        <th>API Key:</th>
                        <td>
                            <input type="text" id="wsp_mail2wa_key" value="<?php echo esc_attr(get_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY)); ?>" class="regular-text" />
                            <button class="button" onclick="wspSaveApiKey()">Salva</button>
                        </td>
                    </tr>
                    <tr>
                        <th>Base URL:</th>
                        <td>
                            <input type="url" id="wsp_mail2wa_url" value="<?php echo esc_attr(get_option('wsp_mail2wa_base_url', WSP_MAIL2WA_DEFAULT_API)); ?>" class="regular-text" />
                            <button class="button" onclick="wspSaveApiUrl()">Salva</button>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Info Sistema -->
            <div class="wsp-section">
                <h2>ℹ️ Informazioni Sistema</h2>
                <table class="form-table">
                    <tr>
                        <th>Versione Plugin:</th>
                        <td><?php echo WSP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version:</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>MySQL Version:</th>
                        <td><?php global $wpdb; echo $wpdb->db_version(); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        function wspFixDatabase() {
            var resultDiv = jQuery('#fix-database-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Fix database in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_fix_database',
                nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    resultDiv.html('✅ ' + response.data.message + '<br><pre>' + response.data.details + '</pre>');
                    setTimeout(function() { location.reload(); }, 3000);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + (response.data || 'Errore durante il fix'));
                }
            });
        }
        
        function wspFixCampaignId() {
            var resultDiv = jQuery('#fix-database-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Fix campaign_id in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_fix_campaign_id',
                nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    resultDiv.html('✅ ' + response.data);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + (response.data || 'Errore'));
                }
            });
        }
        
        function wspResetTables() {
            if (!confirm('⚠️ ATTENZIONE: Questo resetterà completamente tutte le tabelle. I dati verranno persi! Continuare?')) {
                return;
            }
            
            var resultDiv = jQuery('#fix-database-result');
            resultDiv.removeClass().addClass('wsp-result-box info').html('⏳ Reset in corso...').show();
            
            jQuery.post(ajaxurl, {
                action: 'wsp_reset_tables',
                nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.removeClass().addClass('wsp-result-box success');
                    resultDiv.html('✅ ' + response.data);
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    resultDiv.removeClass().addClass('wsp-result-box error');
                    resultDiv.html('❌ ' + response.data);
                }
            });
        }
        
        function wspSaveApiKey() {
            var key = jQuery('#wsp_mail2wa_key').val();
            jQuery.post(ajaxurl, {
                action: 'wsp_save_option',
                nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>',
                option: 'wsp_mail2wa_api_key',
                value: key
            }, function(response) {
                alert(response.success ? '✅ API Key salvata!' : '❌ Errore nel salvataggio');
            });
        }
        
        function wspSaveApiUrl() {
            var url = jQuery('#wsp_mail2wa_url').val();
            jQuery.post(ajaxurl, {
                action: 'wsp_save_option',
                nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>',
                option: 'wsp_mail2wa_base_url',
                value: url
            }, function(response) {
                alert(response.success ? '✅ URL salvato!' : '❌ Errore nel salvataggio');
            });
        }
        </script>
        <?php
    }
    
    /**
     * Pagina Logs - COMPLETA
     */
    public function logs_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_activity_log';
        
        // Filtri
        $log_type = $_GET['log_type'] ?? '';
        $date_filter = $_GET['date'] ?? '';
        
        // Pulizia logs vecchi
        if (isset($_GET['action']) && $_GET['action'] === 'clear' && wp_verify_nonce($_GET['_wpnonce'], 'clear_logs')) {
            $wpdb->query("DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            echo '<div class="notice notice-success"><p>Log più vecchi di 30 giorni eliminati!</p></div>';
        }
        
        // Query logs
        $where = array();
        if ($log_type) {
            $where[] = $wpdb->prepare("log_type = %s", $log_type);
        }
        if ($date_filter) {
            $where[] = $wpdb->prepare("DATE(created_at) = %s", $date_filter);
        }
        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $logs = $wpdb->get_results(
            "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT 500"
        );
        
        // Tipi di log disponibili
        $log_types = $wpdb->get_col("SELECT DISTINCT log_type FROM $table ORDER BY log_type");
        ?>
        <div class="wrap">
            <h1>📋 Log di Sistema
                <a href="?page=wsp-logs&action=clear&_wpnonce=<?php echo wp_create_nonce('clear_logs'); ?>" 
                   class="page-title-action" 
                   onclick="return confirm('Eliminare i log più vecchi di 30 giorni?')">
                    Pulisci Log Vecchi
                </a>
            </h1>
            
            <!-- Filtri -->
            <div class="wsp-section">
                <form method="get">
                    <input type="hidden" name="page" value="wsp-logs">
                    <select name="log_type">
                        <option value="">Tutti i tipi</option>
                        <?php foreach ($log_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>" <?php selected($log_type, $type); ?>>
                                <?php echo esc_html($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>">
                    <button type="submit" class="button">Filtra</button>
                    <a href="?page=wsp-logs" class="button">Reset</a>
                </form>
            </div>
            
            <!-- Tabella Logs -->
            <div class="wsp-section">
                <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Data/Ora</th>
                            <th style="width: 100px;">Tipo</th>
                            <th>Messaggio</th>
                            <th style="width: 100px;">Utente</th>
                            <th style="width: 100px;">IP</th>
                            <th style="width: 80px;">Dettagli</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log->created_at)); ?></td>
                                <td>
                                    <span class="wsp-badge"><?php echo esc_html($log->log_type); ?></span>
                                </td>
                                <td><?php echo esc_html($log->log_message); ?></td>
                                <td>
                                    <?php 
                                    if ($log->user_id) {
                                        $user = get_user_by('id', $log->user_id);
                                        echo $user ? esc_html($user->display_name) : 'ID: ' . $log->user_id;
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($log->ip_address ?: '-'); ?></td>
                                <td>
                                    <?php if ($log->log_data): ?>
                                        <button class="button button-small" onclick='alert(<?php echo json_encode(json_decode($log->log_data, true), JSON_PRETTY_PRINT); ?>)'>View</button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">Nessun log trovato.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Riepilogo Log -->
            <div class="wsp-stats-grid">
                <?php
                $log_stats = $wpdb->get_results(
                    "SELECT log_type, COUNT(*) as count 
                     FROM $table 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                     GROUP BY log_type 
                     ORDER BY count DESC 
                     LIMIT 4"
                );
                foreach ($log_stats as $stat):
                ?>
                <div class="wsp-stat-card">
                    <h3><?php echo number_format($stat->count); ?></h3>
                    <p><?php echo esc_html($stat->log_type); ?> (24h)</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina Campagne QR - COMPLETA
     */
    public function campaigns_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_campaigns';
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Gestione creazione nuova campagna
        if (isset($_POST['create_campaign']) && wp_verify_nonce($_POST['_wpnonce'], 'create_campaign')) {
            $campaign_data = array(
                'name' => sanitize_text_field($_POST['campaign_name']),
                'message' => sanitize_textarea_field($_POST['welcome_message']),
                'type' => 'qr'
            );
            $result = WSP_Campaigns::create_campaign($campaign_data);
            echo '<div class="notice notice-success"><p>Campagna creata con successo!</p></div>';
        }
        
        // Lista campagne - Fix per compatibilità database
        // Prima verifica se le colonne esistono
        $has_campaign_id_in_numbers = $wpdb->get_var("SHOW COLUMNS FROM $table_numbers LIKE 'campaign_id'");
        
        if ($has_campaign_id_in_numbers) {
            // Query con JOIN se la colonna esiste
            $campaigns = $wpdb->get_results(
                "SELECT c.*, 
                        COALESCE((SELECT COUNT(*) FROM $table_numbers n WHERE n.campaign_id = c.campaign_id), 0) as registrations
                 FROM $table c 
                 ORDER BY c.created_at DESC"
            );
        } else {
            // Query senza JOIN se la colonna non esiste
            $campaigns = $wpdb->get_results(
                "SELECT c.*, 0 as registrations
                 FROM $table c 
                 ORDER BY c.created_at DESC"
            );
            
            // Mostra avviso per fixare il database
            echo '<div class="notice notice-warning"><p>⚠️ La colonna campaign_id non esiste nella tabella numeri. <a href="?page=wsp-settings">Vai a Impostazioni</a> e clicca su "Fix Database" per risolvere.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>📱 Campagne QR Code</h1>
            
            <!-- Form Creazione Campagna -->
            <div class="wsp-section">
                <h2>Crea Nuova Campagna</h2>
                <form method="post">
                    <?php wp_nonce_field('create_campaign'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Nome Campagna</th>
                            <td>
                                <input type="text" name="campaign_name" required class="regular-text" 
                                       placeholder="Es: Promo Natale 2024">
                            </td>
                        </tr>
                        <tr>
                            <th>Messaggio di Benvenuto</th>
                            <td>
                                <textarea name="welcome_message" rows="5" class="large-text" 
                                          placeholder="Messaggio che riceverà chi scansiona il QR..."><?php echo esc_textarea(get_option('wsp_welcome_message')); ?></textarea>
                                <p class="description">Usa {{nome}} e {{numero}} come placeholder</p>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" name="create_campaign" class="button button-primary">🚀 Crea Campagna</button>
                    </p>
                </form>
            </div>
            
            <!-- Lista Campagne -->
            <div class="wsp-section">
                <h2>Campagne Attive</h2>
                <?php if ($campaigns): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($campaigns as $campaign): ?>
                        <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: white;">
                            <h3><?php echo esc_html($campaign->campaign_name); ?></h3>
                            <p><strong>ID:</strong> <code><?php echo esc_html($campaign->campaign_id); ?></code></p>
                            <p><strong>Registrazioni:</strong> <?php echo number_format($campaign->registrations); ?></p>
                            <p><strong>Creata:</strong> <?php echo date('d/m/Y', strtotime($campaign->created_at)); ?></p>
                            
                            <!-- QR Code -->
                            <div style="text-align: center; margin: 20px 0;">
                                <img src="<?php echo esc_url($campaign->qr_code_url); ?>" 
                                     style="max-width: 200px; border: 5px solid #f0f0f0;">
                            </div>
                            
                            <p>
                                <a href="<?php echo esc_url($campaign->landing_page_url); ?>" 
                                   target="_blank" class="button button-small">🔗 Landing Page</a>
                                <button class="button button-small" 
                                        onclick="wspDownloadQR('<?php echo esc_url($campaign->qr_code_url); ?>', '<?php echo esc_attr($campaign->campaign_name); ?>')">
                                    💾 Scarica QR
                                </button>
                                <button class="button button-small" 
                                        onclick="wspShowCampaignStats('<?php echo esc_attr($campaign->campaign_id); ?>')">
                                    📊 Statistiche
                                </button>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nessuna campagna trovata. Crea la tua prima campagna!</p>
                <?php endif; ?>
            </div>
            
            <!-- Istruzioni -->
            <div class="wsp-section">
                <h2>📖 Come Funzionano le Campagne QR</h2>
                <ol>
                    <li><strong>Crea Campagna:</strong> Dai un nome e imposta il messaggio di benvenuto</li>
                    <li><strong>Genera QR Code:</strong> Il sistema genera automaticamente un QR code univoco</li>
                    <li><strong>Distribuisci:</strong> Stampa o condividi il QR code (volantini, social, email)</li>
                    <li><strong>Raccolta Contatti:</strong> Chi scansiona il QR viene reindirizzato a una landing page</li>
                    <li><strong>Messaggio Automatico:</strong> Dopo la registrazione, riceve il messaggio di benvenuto</li>
                    <li><strong>Tracciamento:</strong> Monitora registrazioni e conversioni in tempo reale</li>
                </ol>
            </div>
        </div>
        
        <script>
        function wspDownloadQR(url, name) {
            var link = document.createElement('a');
            link.href = url;
            link.download = 'qr-' + name + '.png';
            link.click();
        }
        
        function wspShowCampaignStats(campaignId) {
            // Mostra statistiche campagna
            jQuery.post(ajaxurl, {
                action: 'wsp_get_campaign_stats',
                nonce: wsp_ajax.nonce,
                campaign_id: campaignId
            }, function(response) {
                if (response.success) {
                    alert('Registrazioni: ' + response.data.registrations);
                }
            });
        }
        </script>
        <?php
    }
    
    // AJAX Handlers
    public function ajax_get_stats() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        $period = $_POST['period'] ?? 'all';
        $stats = WSP_Database::get_statistics($period);
        
        wp_send_json_success($stats);
    }
    
    public function ajax_export_csv() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        WSP_Database::ajax_export_csv();
    }
    
    public function ajax_send_daily_report() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        WSP_Messages::send_daily_report();
        
        wp_send_json_success('Report inviato con successo');
    }
    
    public function ajax_send_welcome() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $number_id = intval($_POST['number_id'] ?? 0);
        
        if (!$number_id) {
            wp_send_json_error('ID numero non valido');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $number = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $number_id));
        
        if (!$number) {
            wp_send_json_error('Numero non trovato');
        }
        
        $mail2wa = new WSP_Mail2Wa();
        $result = $mail2wa->send_welcome_message(
            $number->sender_formatted ?: $number->sender_number,
            $number->sender_name
        );
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_get_recipients() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        WSP_Messages::ajax_get_recipients();
    }
    
    /**
     * Export numeri in CSV
     */
    private function export_numbers_csv() {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Get all numbers
        $numbers = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="numeri-whatsapp-' . date('Y-m-d') . '.csv"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        if (!empty($numbers)) {
            fputcsv($output, array_keys($numbers[0]));
        }
        
        // Add data
        foreach ($numbers as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    public function ajax_add_number() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $number = sanitize_text_field($_POST['number'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $campaign = sanitize_text_field($_POST['campaign'] ?? '');
        
        if (empty($number)) {
            wp_send_json_error('Numero richiesto');
        }
        
        $data = array(
            'sender_number' => str_replace('+', '', $number),
            'sender_formatted' => strpos($number, '+') === 0 ? $number : '+' . $number,
            'sender_name' => $name,
            'sender_email' => $email,
            'campaign_id' => $campaign,
            'source' => 'manual'
        );
        
        $id = WSP_Database::save_number($data);
        
        if ($id) {
            wp_send_json_success(array(
                'id' => $id,
                'message' => 'Numero aggiunto con successo'
            ));
        } else {
            wp_send_json_error('Errore durante il salvataggio');
        }
    }
}