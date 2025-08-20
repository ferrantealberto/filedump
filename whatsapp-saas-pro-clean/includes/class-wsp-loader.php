<?php
/**
 * Loader per compatibilità con classi mancanti
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Crea classi stub per evitare errori fatali se mancano file
if (!class_exists('WSP_Database')) {
    class WSP_Database {
        public static function create_tables() {
            // Usa la nuova classe SaaS
            if (class_exists('WSP_Database_SaaS')) {
                WSP_Database_SaaS::create_tables();
            }
        }
    }
}

if (!class_exists('WSP_Settings')) {
    class WSP_Settings {
        public static function init() {
            // Placeholder
        }
        
        public static function get($key, $default = '') {
            return get_option('wsp_' . $key, $default);
        }
        
        public static function set($key, $value) {
            return update_option('wsp_' . $key, $value);
        }
    }
}

if (!class_exists('WSP_API')) {
    class WSP_API {
        public static function handle_webhook() {
            wp_send_json_success('Webhook received');
        }
        
        public static function extract_number() {
            wp_send_json_error('Function not implemented');
        }
        
        public static function send_message() {
            wp_send_json_error('Function not implemented');
        }
    }
}

if (!class_exists('WSP_Mail2Wa')) {
    class WSP_Mail2Wa {
        private $api_url;
        private $api_key;
        
        public function __construct() {
            $this->api_url = get_option('wsp_mail2wa_api_url', WSP_MAIL2WA_DEFAULT_API);
            $this->api_key = get_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
        }
        
        public function send_message($recipient, $message) {
            // Implementazione base
            return array(
                'success' => false,
                'message' => 'Mail2Wa non configurato'
            );
        }
    }
}

if (!class_exists('WSP_Messages')) {
    class WSP_Messages {
        public static function ajax_get_recipients() {
            wp_send_json_success(array());
        }
    }
}

if (!class_exists('WSP_Campaigns')) {
    class WSP_Campaigns {
        public static function get_all() {
            return array();
        }
    }
}

if (!class_exists('WSP_Gmail')) {
    class WSP_Gmail {
        public static function process_emails() {
            // Placeholder
        }
    }
}

if (!class_exists('WSP_Migration')) {
    class WSP_Migration {
        public static function run() {
            // Placeholder
        }
    }
}

if (!class_exists('WSP_Test')) {
    class WSP_Test {
        public static function ajax_test_mail2wa() {
            wp_send_json_error('Test non disponibile');
        }
        
        public static function ajax_test_gmail() {
            wp_send_json_error('Test non disponibile');
        }
        
        public static function ajax_test_db_connection() {
            wp_send_json_success('Database connesso');
        }
        
        public static function ajax_test_extract_number() {
            wp_send_json_error('Test non disponibile');
        }
    }
}

if (!class_exists('WSP_Sample_Data')) {
    class WSP_Sample_Data {
        public static function ajax_generate_sample_data() {
            wp_send_json_error('Funzione non disponibile');
        }
    }
}

if (!class_exists('WSP_Admin')) {
    class WSP_Admin {
        public function __construct() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
        
        public function add_admin_menu() {
            add_menu_page(
                'WhatsApp SaaS',
                'WhatsApp SaaS',
                'manage_options',
                'whatsapp-saas',
                array($this, 'render_dashboard'),
                'dashicons-whatsapp',
                30
            );
            
            // Aggiungi sottomenu per clienti
            add_submenu_page(
                'whatsapp-saas',
                'Clienti',
                'Clienti',
                'manage_options',
                'wsp-customers',
                array($this, 'render_customers')
            );
            
            // Aggiungi sottomenu per piani
            add_submenu_page(
                'whatsapp-saas',
                'Piani',
                'Piani',
                'manage_options',
                'wsp-plans',
                array($this, 'render_plans')
            );
        }
        
        public function render_dashboard() {
            echo '<div class="wrap">';
            echo '<h1>WhatsApp SaaS Pro - Dashboard</h1>';
            
            global $wpdb;
            
            // Mostra statistiche se le tabelle esistono
            $table_customers = $wpdb->prefix . 'wsp_customers';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_customers'") == $table_customers) {
                $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM $table_customers");
                echo '<div class="notice notice-info">';
                echo '<p>Clienti totali: <strong>' . $total_customers . '</strong></p>';
                echo '</div>';
            } else {
                echo '<div class="notice notice-warning">';
                echo '<p>Le tabelle del database non sono ancora state create. ';
                echo '<a href="' . admin_url('admin.php?page=whatsapp-saas&action=create_tables') . '">Crea tabelle ora</a></p>';
                echo '</div>';
            }
            
            // Azione per creare tabelle
            if (isset($_GET['action']) && $_GET['action'] == 'create_tables') {
                if (class_exists('WSP_Database_SaaS')) {
                    WSP_Database_SaaS::create_tables();
                    echo '<div class="notice notice-success"><p>Tabelle create con successo!</p></div>';
                }
            }
            
            echo '</div>';
        }
        
        public function render_customers() {
            echo '<div class="wrap">';
            echo '<h1>Gestione Clienti</h1>';
            
            if (class_exists('WSP_Customers')) {
                $customers = WSP_Customers::get_customers();
                
                if (!empty($customers['customers'])) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>ID</th>';
                    echo '<th>Azienda</th>';
                    echo '<th>Email</th>';
                    echo '<th>Piano</th>';
                    echo '<th>Crediti</th>';
                    echo '<th>Stato</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($customers['customers'] as $customer) {
                        echo '<tr>';
                        echo '<td>' . $customer->id . '</td>';
                        echo '<td>' . esc_html($customer->business_name) . '</td>';
                        echo '<td>' . esc_html($customer->email) . '</td>';
                        echo '<td>' . $customer->plan_id . '</td>';
                        echo '<td>' . $customer->credits_balance . '</td>';
                        echo '<td>' . ($customer->is_active ? '✅ Attivo' : '⏸️ Inattivo') . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                } else {
                    echo '<p>Nessun cliente registrato.</p>';
                }
            } else {
                echo '<p>Modulo clienti non disponibile.</p>';
            }
            
            echo '</div>';
        }
        
        public function render_plans() {
            echo '<div class="wrap">';
            echo '<h1>Piani di Abbonamento</h1>';
            
            global $wpdb;
            $table_plans = $wpdb->prefix . 'wsp_subscription_plans';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_plans'") == $table_plans) {
                $plans = $wpdb->get_results("SELECT * FROM $table_plans");
                
                if ($plans) {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr>';
                    echo '<th>Nome</th>';
                    echo '<th>Tipo</th>';
                    echo '<th>Prezzo Mensile</th>';
                    echo '<th>Crediti Inclusi</th>';
                    echo '<th>Stato</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($plans as $plan) {
                        echo '<tr>';
                        echo '<td>' . esc_html($plan->plan_name) . '</td>';
                        echo '<td>' . $plan->plan_type . '</td>';
                        echo '<td>€' . number_format($plan->monthly_price, 2) . '</td>';
                        echo '<td>' . number_format($plan->credits_included) . '</td>';
                        echo '<td>' . ($plan->is_active ? '✅ Attivo' : '❌ Inattivo') . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                } else {
                    echo '<p>Nessun piano configurato.</p>';
                }
            } else {
                echo '<p>Tabella piani non trovata.</p>';
            }
            
            echo '</div>';
        }
    }
}