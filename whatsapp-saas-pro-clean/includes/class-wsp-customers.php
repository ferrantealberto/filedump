<?php
/**
 * Gestione Clienti/Destinatari per WhatsApp SaaS
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSP_Customers {
    
    /**
     * Registra un nuovo cliente/destinatario
     */
    public static function register_customer($data) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        // Genera codice cliente univoco
        $customer_code = self::generate_customer_code($data['business_name']);
        
        // Genera API key
        $api_key = self::generate_api_key();
        
        // Prepara i dati del cliente
        $customer_data = array(
            'customer_code' => $customer_code,
            'business_name' => sanitize_text_field($data['business_name']),
            'contact_name' => sanitize_text_field($data['contact_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'whatsapp_number' => self::normalize_phone_number($data['whatsapp_number'] ?? ''),
            'vat_number' => sanitize_text_field($data['vat_number'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'city' => sanitize_text_field($data['city'] ?? ''),
            'postal_code' => sanitize_text_field($data['postal_code'] ?? ''),
            'country' => sanitize_text_field($data['country'] ?? 'Italia'),
            'plan_id' => intval($data['plan_id'] ?? 1),
            'api_key' => $api_key,
            'is_active' => 0, // Inattivo fino alla conferma
            'created_at' => current_time('mysql')
        );
        
        // Inserisci il cliente
        $result = $wpdb->insert($table_customers, $customer_data);
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => 'Errore durante la registrazione del cliente',
                'error' => $wpdb->last_error
            );
        }
        
        $customer_id = $wpdb->insert_id;
        
        // Crea la sottoscrizione
        self::create_subscription($customer_id, $customer_data['plan_id']);
        
        // Genera QR code URL
        $qr_url = self::generate_qr_code_url($customer_data['email']);
        $wpdb->update(
            $table_customers,
            array('qr_code_url' => $qr_url),
            array('id' => $customer_id)
        );
        
        // Invia email di conferma
        self::send_confirmation_email($customer_data, $qr_url);
        
        // Log attività
        WSP_Activity_Log::log('customer_registration', array(
            'customer_id' => $customer_id,
            'customer_code' => $customer_code,
            'email' => $customer_data['email']
        ));
        
        return array(
            'success' => true,
            'customer_id' => $customer_id,
            'customer_code' => $customer_code,
            'api_key' => $api_key,
            'qr_code_url' => $qr_url,
            'message' => 'Cliente registrato con successo. Email di conferma inviata.'
        );
    }
    
    /**
     * Genera un codice cliente univoco
     */
    private static function generate_customer_code($business_name) {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $business_name), 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }
        
        $timestamp = time();
        $random = mt_rand(100, 999);
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * Genera API key sicura
     */
    private static function generate_api_key() {
        return 'wsp_' . bin2hex(random_bytes(32));
    }
    
    /**
     * Normalizza numero di telefono
     */
    private static function normalize_phone_number($phone) {
        // Rimuovi tutti i caratteri non numerici
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Se inizia con 39 (Italia) ma non ha il +, aggiungilo
        if (substr($phone, 0, 2) === '39' && strlen($phone) > 10) {
            $phone = '+' . $phone;
        }
        // Se è un numero italiano senza prefisso internazionale
        elseif (substr($phone, 0, 1) === '3' && strlen($phone) === 10) {
            $phone = '+39' . $phone;
        }
        // Se non ha il + all'inizio, aggiungilo
        elseif (substr($phone, 0, 1) !== '+' && strlen($phone) > 9) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Crea sottoscrizione per il cliente
     */
    private static function create_subscription($customer_id, $plan_id) {
        global $wpdb;
        
        $table_subscriptions = $wpdb->prefix . 'wsp_customer_subscriptions';
        $table_plans = $wpdb->prefix . 'wsp_subscription_plans';
        
        // Ottieni i dettagli del piano
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plans WHERE id = %d",
            $plan_id
        ));
        
        if (!$plan) {
            return false;
        }
        
        // Calcola date di inizio e fine
        $start_date = current_time('mysql');
        $end_date = null;
        $renewal_date = null;
        
        if ($plan->billing_type === 'mensile' || $plan->billing_type === 'entrambi') {
            $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
            $renewal_date = $end_date;
        }
        
        // Crea la sottoscrizione
        $subscription_data = array(
            'customer_id' => $customer_id,
            'plan_id' => $plan_id,
            'subscription_status' => 'active',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'renewal_date' => $renewal_date,
            'credits_remaining' => $plan->credits_included,
            'messages_sent_this_period' => 0,
            'auto_renew' => 1,
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_subscriptions, $subscription_data);
        
        // Aggiorna crediti del cliente
        $wpdb->update(
            $wpdb->prefix . 'wsp_customers',
            array('credits_balance' => $plan->credits_included),
            array('id' => $customer_id)
        );
        
        // Log transazione crediti
        WSP_Credits::add_transaction(
            $customer_id,
            'purchase',
            $plan->credits_included,
            'Crediti iniziali piano ' . $plan->plan_name
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Genera URL QR code per il cliente
     */
    private static function generate_qr_code_url($email) {
        // URL del servizio QR code
        $base_url = 'https://qr.wapower.it/';
        $params = array(
            'email' => $email
        );
        
        return $base_url . '?' . http_build_query($params);
    }
    
    /**
     * Invia email di conferma registrazione
     */
    private static function send_confirmation_email($customer_data, $qr_url) {
        $to = $customer_data['email'];
        $subject = 'Conferma registrazione servizio WaPower';
        
        $message = sprintf(
            'Gentile cliente,<br>' .
            'il tuo profilo del servizio WaPower è stato creato con il piano <strong>%s</strong>.<br>' .
            'Per poter completare la procedura ed inviare messaggi dal tuo numero whatsapp business, ' .
            'è necessario inquadrare, dal tuo cellulare, il qr code presente al seguente link: %s<br><br>' .
            'Di seguito il link alle istruzioni su come fare la scansione:<br>' .
            'https://faq.whatsapp.com/381777293328336/?helpref=faq_content<br><br>' .
            'Potrai accedere al link con il QRcode anche dalla tua area riservata del sito ' .
            'https://www.upgradeservizi.eu con le credenziali ricevute al momento dell\'iscrizione.<br><br>' .
            'COLLEGHIAMO WhatsApp con TUTTO!<br>' .
            'Questo messaggio è stato inviato con WaPower, l\'innovativo servizio di WebLabFactory ' .
            'che collega WhatsApp a qualsiasi gestionale.<br>' .
            'Ideale per tracking ordini, fatture proforma, gestione ticket e solleciti di pagamento.<br>' .
            'Con WaPower integri in 60 secondi WhatsApp con qualsiasi gestionale, CRM ed ERP. ' .
            'Zero codice, massima potenza! ⚡<br>' .
            'Bonus: Funzione bidirezionale inclusa - ricevi automaticamente nel tuo CRM ' .
            'tutti i messaggi WhatsApp dei tuoi clienti con allegati.<br><br>' .
            'Per info: www.wapower.it',
            self::get_plan_name($customer_data['plan_id']),
            $qr_url
        );
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Upgrade servizi <noreply@upgradeservizi.eu>'
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Ottieni nome del piano
     */
    private static function get_plan_name($plan_id) {
        global $wpdb;
        
        $table_plans = $wpdb->prefix . 'wsp_subscription_plans';
        $plan_name = $wpdb->get_var($wpdb->prepare(
            "SELECT plan_name FROM $table_plans WHERE id = %d",
            $plan_id
        ));
        
        return $plan_name ?: 'Standard';
    }
    
    /**
     * Attiva cliente dopo conferma
     */
    public static function activate_customer($customer_id) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        $result = $wpdb->update(
            $table_customers,
            array(
                'is_active' => 1,
                'activation_date' => current_time('mysql')
            ),
            array('id' => $customer_id)
        );
        
        if ($result !== false) {
            // Log attività
            WSP_Activity_Log::log('customer_activation', array(
                'customer_id' => $customer_id
            ));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Ottieni cliente per email
     */
    public static function get_customer_by_email($email) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_customers WHERE email = %s",
            $email
        ));
    }
    
    /**
     * Ottieni cliente per codice
     */
    public static function get_customer_by_code($customer_code) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_customers WHERE customer_code = %s",
            $customer_code
        ));
    }
    
    /**
     * Ottieni cliente per numero WhatsApp
     */
    public static function get_customer_by_whatsapp($whatsapp_number) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $normalized_number = self::normalize_phone_number($whatsapp_number);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_customers WHERE whatsapp_number = %s",
            $normalized_number
        ));
    }
    
    /**
     * Verifica API key
     */
    public static function verify_api_key($api_key) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_customers WHERE api_key = %s AND is_active = 1",
            $api_key
        ));
        
        return $customer ? $customer : false;
    }
    
    /**
     * Aggiorna ultimo accesso
     */
    public static function update_last_login($customer_id) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        $wpdb->update(
            $table_customers,
            array('last_login' => current_time('mysql')),
            array('id' => $customer_id)
        );
    }
    
    /**
     * Ottieni lista clienti con filtri
     */
    public static function get_customers($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'all',
            'plan_id' => null,
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $where = array('1=1');
        
        // Filtro stato
        if ($args['status'] === 'active') {
            $where[] = 'is_active = 1';
        } elseif ($args['status'] === 'inactive') {
            $where[] = 'is_active = 0';
        }
        
        // Filtro piano
        if ($args['plan_id']) {
            $where[] = $wpdb->prepare('plan_id = %d', $args['plan_id']);
        }
        
        // Ricerca
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                '(business_name LIKE %s OR contact_name LIKE %s OR email LIKE %s OR customer_code LIKE %s)',
                $search, $search, $search, $search
            );
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Query totale
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_customers WHERE $where_clause");
        
        // Query risultati
        $query = $wpdb->prepare(
            "SELECT * FROM $table_customers 
            WHERE $where_clause 
            ORDER BY {$args['orderby']} {$args['order']} 
            LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        $customers = $wpdb->get_results($query);
        
        return array(
            'customers' => $customers,
            'total' => $total
        );
    }
    
    /**
     * Ottieni statistiche cliente
     */
    public static function get_customer_stats($customer_id) {
        global $wpdb;
        
        $stats = array();
        
        // Numeri WhatsApp raccolti
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $stats['total_numbers'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_numbers WHERE customer_id = %d",
            $customer_id
        ));
        
        // Messaggi inviati
        $table_messages = $wpdb->prefix . 'wsp_messages';
        $stats['total_messages'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_messages WHERE customer_id = %d",
            $customer_id
        ));
        
        // Campagne attive
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        $stats['active_campaigns'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_campaigns WHERE customer_id = %d AND is_active = 1",
            $customer_id
        ));
        
        // Crediti utilizzati questo mese
        $table_credits = $wpdb->prefix . 'wsp_credits_transactions';
        $stats['credits_used_this_month'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_credits 
            WHERE customer_id = %d 
            AND transaction_type = 'usage' 
            AND MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())",
            $customer_id
        ));
        
        // Crediti rimanenti
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $stats['credits_balance'] = $wpdb->get_var($wpdb->prepare(
            "SELECT credits_balance FROM $table_customers WHERE id = %d",
            $customer_id
        ));
        
        return $stats;
    }
    
    /**
     * Aggiorna piano cliente
     */
    public static function update_customer_plan($customer_id, $new_plan_id) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $table_subscriptions = $wpdb->prefix . 'wsp_customer_subscriptions';
        
        // Aggiorna piano nel cliente
        $wpdb->update(
            $table_customers,
            array('plan_id' => $new_plan_id),
            array('id' => $customer_id)
        );
        
        // Termina sottoscrizione attuale
        $wpdb->update(
            $table_subscriptions,
            array(
                'subscription_status' => 'cancelled',
                'end_date' => current_time('mysql')
            ),
            array(
                'customer_id' => $customer_id,
                'subscription_status' => 'active'
            )
        );
        
        // Crea nuova sottoscrizione
        self::create_subscription($customer_id, $new_plan_id);
        
        // Log attività
        WSP_Activity_Log::log('plan_change', array(
            'customer_id' => $customer_id,
            'new_plan_id' => $new_plan_id
        ));
        
        return true;
    }
}