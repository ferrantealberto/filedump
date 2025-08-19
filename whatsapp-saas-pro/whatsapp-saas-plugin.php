<?php
/**
 * Plugin Name: WhatsApp SaaS Pro - Fixed
 * Plugin URI: https://tuositoweb.com/whatsapp-saas-pro
 * Description: Sistema completo per la gestione di campagne WhatsApp con integrazione Mail2Wa, QR Code e test completi
 * Version: 3.0.0
 * Author: Il Tuo Nome
 * Author URI: https://tuositoweb.com
 * License: GPL v2 or later
 * Text Domain: wsp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */
// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}
// Definizioni costanti
define('WSP_VERSION', '3.0.0');
define('WSP_PLUGIN_FILE', __FILE__);
define('WSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSP_PLUGIN_BASENAME', plugin_basename(__FILE__));
// Configurazione API Mail2Wa predefinita
define('WSP_MAIL2WA_DEFAULT_API', 'https://api.Mail2Wa.it');
define('WSP_MAIL2WA_DEFAULT_KEY', '1f06d5c8bd0cd19f7c99b660b504bb25');
/**
 * Classe principale del plugin
 */
class WhatsAppSaasPlugin {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Costruttore
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Carica le dipendenze
     */
    private function load_dependencies() {
        // Classi core
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-database.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-settings.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-api.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-mail2wa.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-messages.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-campaigns.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-credits.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-gmail.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-migration.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-test.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-sample-data.php';
        
        // Admin
        if (is_admin()) {
            require_once WSP_PLUGIN_DIR . 'admin/class-wsp-admin.php';
        }
    }
    
    /**
     * Inizializza gli hook
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(WSP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WSP_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Init
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Admin
        if (is_admin()) {
            new WSP_Admin();
        }
        
        // API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // AJAX handlers
        $this->register_ajax_handlers();
        
        // Cron jobs
        add_action('wsp_process_emails', array('WSP_Gmail', 'process_emails'));
        add_action('wsp_daily_report', array('WSP_Messages', 'send_daily_report'));
        
        // Shortcodes
        add_shortcode('wsp_qr_campaign', array($this, 'render_qr_campaign'));
        add_shortcode('wsp_test_form', array($this, 'render_test_form'));
    }
    
    /**
     * Registra gli handler AJAX
     */
    private function register_ajax_handlers() {
        // Test handlers
        add_action('wp_ajax_wsp_test_api', array('WSP_Test', 'ajax_test_api'));
        add_action('wp_ajax_wsp_test_extraction', array('WSP_Test', 'ajax_test_extraction'));
        add_action('wp_ajax_wsp_get_api_key', array('WSP_Test', 'ajax_get_api_key'));
        add_action('wp_ajax_wsp_test_database', array('WSP_Test', 'ajax_test_database'));
        add_action('wp_ajax_wsp_test_mail2wa_send', array('WSP_Test', 'ajax_test_mail2wa_send'));
        add_action('wp_ajax_wsp_test_email_processing', array('WSP_Test', 'ajax_test_email_processing'));
        add_action('wp_ajax_wsp_simulate_n8n_webhook', array('WSP_Test', 'ajax_simulate_n8n_webhook'));
        
        // Campaign handlers
        add_action('wp_ajax_wsp_create_campaign', array('WSP_Campaigns', 'ajax_create_campaign'));
        add_action('wp_ajax_wsp_get_campaigns', array('WSP_Campaigns', 'ajax_get_campaigns'));
        add_action('wp_ajax_wsp_get_campaign_stats', array('WSP_Campaigns', 'ajax_get_campaign_stats'));
        add_action('wp_ajax_wsp_delete_campaign', array('WSP_Campaigns', 'ajax_delete_campaign'));
        
        // Message handlers
        add_action('wp_ajax_wsp_send_message', array('WSP_Messages', 'ajax_send_message'));
        add_action('wp_ajax_wsp_send_bulk', array('WSP_Messages', 'ajax_send_bulk'));
        add_action('wp_ajax_wsp_send_welcome', array('WSP_Messages', 'ajax_send_welcome'));
        add_action('wp_ajax_wsp_get_recipients', array('WSP_Messages', 'ajax_get_recipients'));
        
        // Stats handlers
        add_action('wp_ajax_wsp_get_stats', array('WSP_Database', 'ajax_get_stats'));
        add_action('wp_ajax_wsp_export_csv', array('WSP_Database', 'ajax_export_csv'));
        
        // Credits handlers
        add_action('wp_ajax_wsp_add_credits', array('WSP_Credits', 'ajax_add_credits'));
        add_action('wp_ajax_wsp_check_balance', array('WSP_Credits', 'ajax_check_balance'));
        
        // Numbers handlers
        add_action('wp_ajax_wsp_add_number', array($this, 'ajax_add_number'));
        
        // Database fix handlers
        add_action('wp_ajax_wsp_fix_database', array($this, 'ajax_fix_database'));
        add_action('wp_ajax_wsp_fix_campaign_id', array($this, 'ajax_fix_campaign_id'));
        add_action('wp_ajax_wsp_reset_tables', array($this, 'ajax_reset_tables'));
        add_action('wp_ajax_wsp_save_option', array($this, 'ajax_save_option'));
    }
    
    /**
     * Registra le route REST API
     */
    public function register_rest_routes() {
        // Webhook per n8n
        register_rest_route('wsp/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array('WSP_API', 'handle_webhook'),
            'permission_callback' => array('WSP_API', 'verify_api_key')
        ));
        
        // Endpoint per ricevere numeri WhatsApp
        register_rest_route('wsp/v1', '/numbers', array(
            'methods' => 'POST',
            'callback' => array('WSP_API', 'receive_numbers'),
            'permission_callback' => array('WSP_API', 'verify_api_key')
        ));
        
        // Endpoint per inviare messaggi
        register_rest_route('wsp/v1', '/send', array(
            'methods' => 'POST',
            'callback' => array('WSP_API', 'send_message'),
            'permission_callback' => array('WSP_API', 'verify_api_key')
        ));
        
        // Endpoint per statistiche
        register_rest_route('wsp/v1', '/stats', array(
            'methods' => 'GET',
            'callback' => array('WSP_API', 'get_stats'),
            'permission_callback' => array('WSP_API', 'verify_api_key')
        ));
        
        // Endpoint per test
        register_rest_route('wsp/v1', '/test', array(
            'methods' => 'GET,POST',
            'callback' => array('WSP_API', 'test_endpoint'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Init
     */
    public function init() {
        // Check for database updates
        WSP_Migration::check_and_migrate();
        
        // Ensure table structure is up to date
        WSP_Database::update_table_structure();
        
        // Schedule cron events
        if (!wp_next_scheduled('wsp_process_emails')) {
            wp_schedule_event(time(), 'hourly', 'wsp_process_emails');
        }
        
        if (!wp_next_scheduled('wsp_daily_report')) {
            $report_time = get_option('wsp_report_time', '18:00');
            $timestamp = strtotime('today ' . $report_time);
            if ($timestamp < time()) {
                $timestamp = strtotime('tomorrow ' . $report_time);
            }
            wp_schedule_event($timestamp, 'daily', 'wsp_daily_report');
        }
    }
    
    /**
     * Load textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wsp', false, dirname(WSP_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Crea tabelle database
        WSP_Database::create_tables();
        
        // Aggiorna struttura tabelle esistenti
        WSP_Database::update_table_structure();
        
        // Imposta opzioni predefinite
        $this->set_default_options();
        
        // Inserisci dati di esempio se è la prima installazione
        if (get_option('wsp_first_install', true)) {
            WSP_Sample_Data::insert_sample_data();
            update_option('wsp_first_install', false);
        }
        
        // Pulisci cache
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        // Rimuovi cron jobs
        wp_clear_scheduled_hook('wsp_process_emails');
        wp_clear_scheduled_hook('wsp_daily_report');
        
        // Pulisci cache
        flush_rewrite_rules();
    }
    
    /**
     * Imposta opzioni predefinite
     */
    private function set_default_options() {
        // API Keys
        add_option('wsp_api_key', 'demo-api-key-9lz721sv0xTjFNVA');
        add_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
        
        // Mail2Wa settings
        add_option('wsp_mail2wa_base_url', WSP_MAIL2WA_DEFAULT_API);
        add_option('wsp_mail2wa_endpoint_path', '/');
        add_option('wsp_mail2wa_method', 'POST');
        add_option('wsp_mail2wa_content_type', 'json');
        add_option('wsp_mail2wa_auth_method', 'query');
        add_option('wsp_mail2wa_phone_param', 'to');
        add_option('wsp_mail2wa_message_param', 'message');
        add_option('wsp_mail2wa_api_key_param', 'apiKey');
        add_option('wsp_mail2wa_extra_params', '{"action":"send"}');
        add_option('wsp_mail2wa_email_fallback', true);
        add_option('wsp_mail2wa_timeout', 30);
        
        // Credits
        add_option('wsp_credits_balance', 100);
        
        // Messages
        add_option('wsp_welcome_message', '🎉 Benvenuto! Il tuo numero {{numero}} è stato registrato con successo.');
        
        // Report
        add_option('wsp_report_email', get_option('admin_email'));
        add_option('wsp_report_time', '18:00');
        add_option('wsp_report_enabled', true);
        
        // Gmail settings
        add_option('wsp_gmail_email', '');
        add_option('wsp_gmail_password', '');
        add_option('wsp_gmail_from_filter', 'upgradeservizi.eu');
        
        // Version
        add_option('wsp_version', WSP_VERSION);
        add_option('wsp_db_version', '2.0.0');
    }
    
    /**
     * Render QR Campaign shortcode
     */
    public function render_qr_campaign($atts) {
        $atts = shortcode_atts(array(
            'campaign_id' => '',
            'size' => 250,
            'color' => '#000000'
        ), $atts);
        
        ob_start();
        WSP_Campaigns::render_qr_code($atts['campaign_id'], $atts['size'], $atts['color']);
        return ob_get_clean();
    }
    
    /**
     * Render Test Form shortcode
     */
    public function render_test_form($atts) {
        ob_start();
        WSP_Test::render_test_form();
        return ob_get_clean();
    }
    
    /**
     * AJAX handler per aggiungere un numero
     */
    public function ajax_add_number() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsp_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Sanitizza input
        $number = sanitize_text_field($_POST['number']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $campaign = sanitize_text_field($_POST['campaign']);
        
        // Valida numero
        if (empty($number)) {
            wp_send_json_error('Il numero è richiesto');
            return;
        }
        
        // Formatta numero
        $formatted = $number;
        if (!strpos($formatted, '+') === 0) {
            $formatted = '+' . $formatted;
        }
        
        // Inserisci nel database
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        $result = $wpdb->insert($table, array(
            'sender_number' => str_replace('+', '', $number),
            'sender_formatted' => $formatted,
            'sender_name' => $name,
            'sender_email' => $email,
            'campaign_id' => $campaign,
            'source' => 'manual',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        
        if ($result) {
            wp_send_json_success('Numero aggiunto con successo');
        } else {
            wp_send_json_error('Errore durante l\'inserimento del numero');
        }
    }
    
    /**
     * AJAX handler per fix database
     */
    public function ajax_fix_database() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsp_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        global $wpdb;
        $results = array();
        
        // Fix tabella numeri - aggiungi campaign_id se manca
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        
        // Verifica esistenza tabella campaigns
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_campaigns'");
        if (!$table_exists) {
            WSP_Database::create_tables();
            $results[] = "✅ Creata tabella campaigns";
        }
        
        // Verifica colonna campaign_id in tabella numeri
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_numbers LIKE 'campaign_id'");
        
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE $table_numbers ADD COLUMN campaign_id varchar(100) DEFAULT ''");
            $wpdb->query("ALTER TABLE $table_numbers ADD INDEX idx_campaign (campaign_id)");
            $results[] = "✅ Aggiunta colonna campaign_id a tabella numeri";
        } else {
            $results[] = "✓ Colonna campaign_id già presente in tabella numeri";
        }
        
        // Verifica colonna campaign_id in tabella campaigns
        $campaign_id_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_campaigns LIKE 'campaign_id'");
        if (!$campaign_id_exists) {
            $wpdb->query("ALTER TABLE $table_campaigns ADD COLUMN campaign_id varchar(100) NOT NULL AFTER id");
            $wpdb->query("ALTER TABLE $table_campaigns ADD UNIQUE KEY idx_campaign_id (campaign_id)");
            $results[] = "✅ Aggiunta colonna campaign_id a tabella campaigns";
        } else {
            $results[] = "✓ Colonna campaign_id già presente in tabella campaigns";
        }
        
        // Fix altre colonne mancanti
        WSP_Database::update_table_structure();
        $results[] = "✅ Struttura tabelle aggiornata";
        
        // Ricrea tabelle se necessario
        WSP_Database::create_tables();
        $results[] = "✅ Tabelle verificate/create";
        
        // Inserisci dati di esempio se vuoto
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
        if ($count == 0) {
            WSP_Sample_Data::insert_sample_data();
            $results[] = "✅ Dati di esempio inseriti";
        }
        
        wp_send_json_success(array(
            'message' => 'Database fixato con successo!',
            'details' => implode("\n", $results)
        ));
    }
    
    /**
     * AJAX handler per fix solo campaign_id
     */
    public function ajax_fix_campaign_id() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsp_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        global $wpdb;
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Verifica se la colonna esiste
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_numbers LIKE 'campaign_id'");
        
        if (!$column_exists) {
            // Aggiungi la colonna
            $result = $wpdb->query("ALTER TABLE $table_numbers ADD COLUMN campaign_id varchar(100) DEFAULT '' AFTER source");
            
            if ($result !== false) {
                // Aggiungi anche l'indice
                $wpdb->query("ALTER TABLE $table_numbers ADD INDEX idx_campaign (campaign_id)");
                wp_send_json_success('Colonna campaign_id aggiunta con successo! La pagina verrà ricaricata...');
            } else {
                wp_send_json_error('Errore nell\'aggiunta della colonna: ' . $wpdb->last_error);
            }
        } else {
            wp_send_json_success('La colonna campaign_id esiste già.');
        }
    }
    
    /**
     * AJAX handler per reset completo tabelle
     */
    public function ajax_reset_tables() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsp_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        global $wpdb;
        
        // Lista tabelle del plugin
        $tables = array(
            $wpdb->prefix . 'wsp_whatsapp_numbers',
            $wpdb->prefix . 'wsp_messages',
            $wpdb->prefix . 'wsp_campaigns',
            $wpdb->prefix . 'wsp_credits_transactions',
            $wpdb->prefix . 'wsp_activity_log'
        );
        
        // Drop di tutte le tabelle
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Ricrea le tabelle
        WSP_Database::create_tables();
        
        // Inserisci dati di esempio
        WSP_Sample_Data::insert_sample_data();
        
        // Reset opzioni
        update_option('wsp_credits_balance', 100);
        update_option('wsp_first_install', true);
        
        wp_send_json_success('Tabelle resettate con successo! Ricaricamento pagina...');
    }
    
    /**
     * AJAX handler per salvare opzioni
     */
    public function ajax_save_option() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsp_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
            return;
        }
        
        $option = sanitize_text_field($_POST['option']);
        $value = sanitize_text_field($_POST['value']);
        
        // Lista opzioni consentite
        $allowed_options = array(
            'wsp_mail2wa_api_key',
            'wsp_mail2wa_base_url',
            'wsp_welcome_message',
            'wsp_credits_per_message'
        );
        
        if (in_array($option, $allowed_options)) {
            update_option($option, $value);
            wp_send_json_success('Opzione salvata');
        } else {
            wp_send_json_error('Opzione non consentita');
        }
    }
}
// Inizializza il plugin
function wsp_init() {
    return WhatsAppSaasPlugin::get_instance();
}
// Avvia il plugin
add_action('plugins_loaded', 'wsp_init');
// Funzioni helper globali
function wsp_log($message, $type = 'info') {
    WSP_Database::log_activity($type, $message);
}
function wsp_send_whatsapp($phone, $message) {
    $mail2wa = new WSP_Mail2Wa();
    return $mail2wa->send_message($phone, $message);
}
function wsp_get_credits() {
    return WSP_Credits::get_balance();
}
--------------------------------------------------------------------------------
================================================================================
FINE DUMP - Generato da Plugin Dumper v1.0
================================================================================