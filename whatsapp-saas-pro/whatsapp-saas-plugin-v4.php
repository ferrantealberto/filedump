<?php
/**
 * Plugin Name: WhatsApp SaaS Pro - Multi-Tenant
 * Plugin URI: https://wapower.it
 * Description: Sistema SaaS completo per la gestione multi-tenant di campagne WhatsApp con integrazione Mail2Wa, QR Code, Report automatici e gestione piani
 * Version: 4.0.0
 * Author: WaPower Team
 * Author URI: https://wapower.it
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
define('WSP_VERSION', '4.0.0');
define('WSP_PLUGIN_FILE', __FILE__);
define('WSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Configurazione API Mail2Wa predefinita
define('WSP_MAIL2WA_DEFAULT_API', 'https://api.Mail2Wa.it');
define('WSP_MAIL2WA_DEFAULT_KEY', '1f06d5c8bd0cd19f7c99b660b504bb25');

// Configurazione QR Code Service
define('WSP_QR_SERVICE_URL', 'https://qr.wapower.it');

// Configurazione Form Registrazione
define('WSP_REGISTRATION_FORM_URL', 'https://upgradeservizi.eu/external_add_user/');

/**
 * Classe principale del plugin SaaS Multi-Tenant
 */
class WhatsAppSaasPluginPro {
    
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
        $this->check_requirements();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Verifica requisiti minimi
     */
    private function check_requirements() {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo 'WhatsApp SaaS Pro richiede PHP 7.4 o superiore.';
                echo '</p></div>';
            });
            return false;
        }
        
        if (!function_exists('curl_init')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo 'WhatsApp SaaS Pro richiede l\'estensione cURL di PHP.';
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Carica le dipendenze
     */
    private function load_dependencies() {
        // Classi core esistenti
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
        
        // Nuove classi SaaS
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-database-saas.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-customers.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-reports.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-integrations.php';
        require_once WSP_PLUGIN_DIR . 'includes/class-wsp-activity-log.php';
        
        // Admin
        if (is_admin()) {
            require_once WSP_PLUGIN_DIR . 'admin/class-wsp-admin.php';
            // Aggiungi admin SaaS se necessario
            if (file_exists(WSP_PLUGIN_DIR . 'admin/class-wsp-admin-saas.php')) {
                require_once WSP_PLUGIN_DIR . 'admin/class-wsp-admin-saas.php';
            }
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
        
        // Inizializza integrazioni
        WSP_Integrations::init();
        
        // API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // AJAX handlers
        $this->register_ajax_handlers();
        
        // Cron jobs
        add_action('wsp_process_emails', array('WSP_Gmail', 'process_emails'));
        add_action('wsp_send_daily_reports', array($this, 'send_daily_reports'));
        add_action('wsp_check_subscriptions', array($this, 'check_subscriptions'));
        add_action('wsp_cleanup_logs', array($this, 'cleanup_logs'));
        
        // WhatsApp command processor
        add_action('wsp_process_whatsapp_command', array('WSP_Reports', 'process_whatsapp_command'), 10, 3);
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Registra post types se necessario
        $this->register_post_types();
        
        // Registra taxonomies se necessario
        $this->register_taxonomies();
        
        // Flush rewrite rules se necessario
        if (get_option('wsp_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('wsp_flush_rewrite_rules');
        }
    }
    
    /**
     * Registra post types personalizzati
     */
    private function register_post_types() {
        // Qui puoi aggiungere custom post types se necessario
    }
    
    /**
     * Registra taxonomies personalizzate
     */
    private function register_taxonomies() {
        // Qui puoi aggiungere custom taxonomies se necessario
    }
    
    /**
     * Carica traduzioni
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wsp',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Crea/aggiorna tabelle database
        WSP_Database_SaaS::create_tables();
        
        // Migra dati esistenti se necessario
        if (get_option('wsp_db_version') && get_option('wsp_db_version') < '4.0.0') {
            WSP_Database_SaaS::migrate_existing_data();
        }
        
        // Imposta opzioni predefinite
        $this->set_default_options();
        
        // Schedula cron jobs
        $this->schedule_cron_jobs();
        
        // Flag per flush rewrite rules
        update_option('wsp_flush_rewrite_rules', true);
        
        // Log attivazione
        WSP_Activity_Log::log('plugin_activated', array(
            'version' => WSP_VERSION
        ));
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        // Rimuovi cron jobs
        $this->unschedule_cron_jobs();
        
        // Log disattivazione
        WSP_Activity_Log::log('plugin_deactivated', array(
            'version' => WSP_VERSION
        ));
    }
    
    /**
     * Imposta opzioni predefinite
     */
    private function set_default_options() {
        // Opzioni Mail2Wa
        add_option('wsp_mail2wa_api_url', WSP_MAIL2WA_DEFAULT_API);
        add_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
        
        // Opzioni QR Service
        add_option('wsp_qr_service_url', WSP_QR_SERVICE_URL);
        
        // Opzioni Form Registrazione
        add_option('wsp_registration_form_url', WSP_REGISTRATION_FORM_URL);
        
        // Opzioni N8N
        add_option('wsp_n8n_webhook_url', '');
        
        // Opzioni generali
        add_option('wsp_enable_auto_reports', true);
        add_option('wsp_enable_whatsapp_commands', true);
        add_option('wsp_log_retention_days', 90);
        add_option('wsp_default_plan_id', 1);
    }
    
    /**
     * Schedula cron jobs
     */
    private function schedule_cron_jobs() {
        // Processa email ogni 5 minuti
        if (!wp_next_scheduled('wsp_process_emails')) {
            wp_schedule_event(time(), 'wsp_five_minutes', 'wsp_process_emails');
        }
        
        // Report giornalieri
        if (!wp_next_scheduled('wsp_send_daily_reports')) {
            wp_schedule_event(strtotime('tomorrow 9:00:00'), 'daily', 'wsp_send_daily_reports');
        }
        
        // Controlla sottoscrizioni ogni ora
        if (!wp_next_scheduled('wsp_check_subscriptions')) {
            wp_schedule_event(time(), 'hourly', 'wsp_check_subscriptions');
        }
        
        // Pulizia log settimanale
        if (!wp_next_scheduled('wsp_cleanup_logs')) {
            wp_schedule_event(strtotime('next sunday 03:00:00'), 'weekly', 'wsp_cleanup_logs');
        }
    }
    
    /**
     * Rimuovi cron jobs
     */
    private function unschedule_cron_jobs() {
        wp_clear_scheduled_hook('wsp_process_emails');
        wp_clear_scheduled_hook('wsp_send_daily_reports');
        wp_clear_scheduled_hook('wsp_check_subscriptions');
        wp_clear_scheduled_hook('wsp_cleanup_logs');
    }
    
    /**
     * Registra API REST routes
     */
    public function register_rest_routes() {
        register_rest_route('wsp/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array('WSP_API', 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('wsp/v1', '/extract', array(
            'methods' => 'POST',
            'callback' => array('WSP_API', 'extract_number'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        register_rest_route('wsp/v1', '/send-message', array(
            'methods' => 'POST',
            'callback' => array('WSP_API', 'send_message'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        register_rest_route('wsp/v1', '/customer/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_stats_api'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        register_rest_route('wsp/v1', '/customer/numbers', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_numbers_api'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        register_rest_route('wsp/v1', '/report-callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_report_callback'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('wsp/v1', '/whatsapp-command', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_whatsapp_command'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Verifica API key per richieste REST
     */
    public function verify_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key mancante', array('status' => 401));
        }
        
        $customer = WSP_Customers::verify_api_key($api_key);
        
        if (!$customer) {
            return new WP_Error('invalid_api_key', 'API key non valida', array('status' => 401));
        }
        
        // Salva customer nel request per uso successivo
        $request->set_param('_customer', $customer);
        
        return true;
    }
    
    /**
     * API: Ottieni statistiche cliente
     */
    public function get_customer_stats_api($request) {
        $customer = $request->get_param('_customer');
        $stats = WSP_Customers::get_customer_stats($customer->id);
        
        return rest_ensure_response($stats);
    }
    
    /**
     * API: Ottieni numeri cliente
     */
    public function get_customer_numbers_api($request) {
        global $wpdb;
        
        $customer = $request->get_param('_customer');
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        $numbers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_numbers WHERE customer_id = %d ORDER BY created_at DESC",
            $customer->id
        ));
        
        return rest_ensure_response($numbers);
    }
    
    /**
     * Gestisci callback report da N8N
     */
    public function handle_report_callback($request) {
        $customer_id = $request->get_param('customer_id');
        $status = $request->get_param('status');
        
        WSP_Activity_Log::log('report_callback', array(
            'customer_id' => $customer_id,
            'status' => $status
        ), $customer_id);
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Gestisci comando WhatsApp
     */
    public function handle_whatsapp_command($request) {
        $from = $request->get_param('from');
        $body = $request->get_param('body');
        
        $result = WSP_Reports::process_whatsapp_command($from, $body);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Registra AJAX handlers
     */
    private function register_ajax_handlers() {
        // Handlers esistenti
        add_action('wp_ajax_wsp_test_mail2wa', array('WSP_Test', 'ajax_test_mail2wa'));
        add_action('wp_ajax_wsp_test_gmail', array('WSP_Test', 'ajax_test_gmail'));
        add_action('wp_ajax_wsp_test_db_connection', array('WSP_Test', 'ajax_test_db_connection'));
        add_action('wp_ajax_wsp_test_extract_number', array('WSP_Test', 'ajax_test_extract_number'));
        add_action('wp_ajax_wsp_generate_sample_data', array('WSP_Sample_Data', 'ajax_generate_sample_data'));
        
        // Nuovi handlers SaaS
        add_action('wp_ajax_wsp_get_customers', array($this, 'ajax_get_customers'));
        add_action('wp_ajax_wsp_activate_customer', array($this, 'ajax_activate_customer'));
        add_action('wp_ajax_wsp_deactivate_customer', array($this, 'ajax_deactivate_customer'));
        add_action('wp_ajax_wsp_change_customer_plan', array($this, 'ajax_change_customer_plan'));
        add_action('wp_ajax_wsp_generate_customer_report', array($this, 'ajax_generate_customer_report'));
    }
    
    /**
     * AJAX: Ottieni lista clienti
     */
    public function ajax_get_customers() {
        check_ajax_referer('wsp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Non autorizzato');
        }
        
        $args = array(
            'status' => $_POST['status'] ?? 'all',
            'search' => $_POST['search'] ?? '',
            'limit' => intval($_POST['limit'] ?? 20),
            'offset' => intval($_POST['offset'] ?? 0)
        );
        
        $result = WSP_Customers::get_customers($args);
        
        wp_send_json_success($result);
    }
    
    /**
     * Invia report giornalieri automatici
     */
    public function send_daily_reports() {
        if (!get_option('wsp_enable_auto_reports')) {
            return;
        }
        
        global $wpdb;
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        // Ottieni clienti attivi con report automatici abilitati
        $customers = $wpdb->get_results(
            "SELECT * FROM $table_customers WHERE is_active = 1"
        );
        
        foreach ($customers as $customer) {
            WSP_Reports::generate_numbers_report($customer->id);
        }
    }
    
    /**
     * Controlla sottoscrizioni in scadenza
     */
    public function check_subscriptions() {
        global $wpdb;
        
        $table_subscriptions = $wpdb->prefix . 'wsp_customer_subscriptions';
        
        // Trova sottoscrizioni in scadenza nei prossimi 3 giorni
        $expiring = $wpdb->get_results(
            "SELECT * FROM $table_subscriptions 
            WHERE subscription_status = 'active' 
            AND renewal_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)"
        );
        
        foreach ($expiring as $subscription) {
            // Invia notifica al cliente
            $this->send_expiration_notice($subscription);
        }
    }
    
    /**
     * Pulizia log vecchi
     */
    public function cleanup_logs() {
        $days = get_option('wsp_log_retention_days', 90);
        WSP_Activity_Log::cleanup_old_logs($days);
    }
    
    /**
     * Invia notifica scadenza
     */
    private function send_expiration_notice($subscription) {
        // Implementa invio notifica via email o WhatsApp
    }
}

// Aggiungi custom cron schedule
add_filter('cron_schedules', function($schedules) {
    $schedules['wsp_five_minutes'] = array(
        'interval' => 300,
        'display' => 'Ogni 5 minuti'
    );
    $schedules['weekly'] = array(
        'interval' => 604800,
        'display' => 'Settimanale'
    );
    return $schedules;
});

// Inizializza il plugin
function wsp_init() {
    return WhatsAppSaasPluginPro::get_instance();
}

// Avvia il plugin
add_action('plugins_loaded', 'wsp_init');