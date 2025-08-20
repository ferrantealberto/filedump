<?php
/**
 * Classe per la gestione del database SaaS Multi-Tenant
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSP_Database_SaaS {
    
    /**
     * Crea tutte le tabelle necessarie per il sistema SaaS
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Tabella Clienti/Destinatari (Aziende che acquistano il servizio)
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $sql_customers = "CREATE TABLE IF NOT EXISTS $table_customers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_code varchar(50) NOT NULL,
            business_name varchar(255) NOT NULL,
            contact_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) DEFAULT '',
            whatsapp_number varchar(20) DEFAULT '',
            vat_number varchar(50) DEFAULT '',
            address text,
            city varchar(100) DEFAULT '',
            postal_code varchar(20) DEFAULT '',
            country varchar(100) DEFAULT 'Italia',
            plan_id bigint(20) DEFAULT NULL,
            credits_balance int(11) DEFAULT 0,
            qr_code_url text,
            api_key varchar(255) DEFAULT '',
            webhook_url text,
            is_active tinyint(1) DEFAULT 1,
            activation_date datetime DEFAULT NULL,
            expiration_date datetime DEFAULT NULL,
            last_login datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_customer_code (customer_code),
            UNIQUE KEY idx_email (email),
            KEY idx_plan (plan_id),
            KEY idx_active (is_active),
            KEY idx_whatsapp (whatsapp_number)
        ) $charset_collate;";
        
        // 2. Tabella Piani di Abbonamento
        $table_plans = $wpdb->prefix . 'wsp_subscription_plans';
        $sql_plans = "CREATE TABLE IF NOT EXISTS $table_plans (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            plan_name varchar(100) NOT NULL,
            plan_type enum('standard','avanzato','plus','custom') DEFAULT 'standard',
            billing_type enum('mensile','crediti','entrambi') DEFAULT 'mensile',
            monthly_price decimal(10,2) DEFAULT 0.00,
            credits_included int(11) DEFAULT 0,
            extra_credit_price decimal(10,2) DEFAULT 0.00,
            max_campaigns int(11) DEFAULT -1,
            max_messages_per_month int(11) DEFAULT -1,
            features text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (plan_type),
            KEY idx_active (is_active)
        ) $charset_collate;";
        
        // 3. Tabella Sottoscrizioni Clienti
        $table_subscriptions = $wpdb->prefix . 'wsp_customer_subscriptions';
        $sql_subscriptions = "CREATE TABLE IF NOT EXISTS $table_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            plan_id bigint(20) NOT NULL,
            subscription_status enum('active','suspended','cancelled','expired') DEFAULT 'active',
            start_date datetime NOT NULL,
            end_date datetime DEFAULT NULL,
            renewal_date datetime DEFAULT NULL,
            credits_remaining int(11) DEFAULT 0,
            messages_sent_this_period int(11) DEFAULT 0,
            auto_renew tinyint(1) DEFAULT 1,
            payment_method varchar(50) DEFAULT '',
            last_payment_date datetime DEFAULT NULL,
            next_payment_date datetime DEFAULT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_plan (plan_id),
            KEY idx_status (subscription_status),
            KEY idx_renewal (renewal_date),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES {$table_plans}(id)
        ) $charset_collate;";
        
        // 4. Tabella Numeri WhatsApp (Mittenti) per ogni Cliente
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $sql_numbers = "CREATE TABLE IF NOT EXISTS $table_numbers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            sender_number varchar(20) NOT NULL,
            sender_name varchar(255) DEFAULT '',
            sender_formatted varchar(20) DEFAULT '',
            sender_email varchar(255) DEFAULT '',
            recipient_number varchar(20) DEFAULT '',
            recipient_name varchar(255) DEFAULT '',
            recipient_email varchar(255) DEFAULT '',
            email_subject text,
            source varchar(50) DEFAULT 'manual',
            campaign_id varchar(100) DEFAULT '',
            opt_in_date datetime DEFAULT NULL,
            opt_out_date datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            tags text,
            custom_fields longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_sender_number (sender_number),
            KEY idx_recipient_number (recipient_number),
            KEY idx_campaign (campaign_id),
            KEY idx_created (created_at),
            KEY idx_active (is_active),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 5. Tabella Campagne per Cliente
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            campaign_id varchar(100) NOT NULL,
            campaign_name varchar(255) NOT NULL,
            campaign_type varchar(50) DEFAULT 'qr',
            qr_code_url text,
            landing_page_url text,
            welcome_message text,
            total_scans int(11) DEFAULT 0,
            total_registrations int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            budget decimal(10,2) DEFAULT 0.00,
            spent decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_campaign_id (campaign_id),
            KEY idx_customer (customer_id),
            KEY idx_active (is_active),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 6. Tabella Messaggi
        $table_messages = $wpdb->prefix . 'wsp_messages';
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            whatsapp_number_id bigint(20) DEFAULT NULL,
            recipient_number varchar(20) NOT NULL,
            message_content text NOT NULL,
            message_type varchar(50) DEFAULT 'manual',
            delivery_status varchar(50) DEFAULT 'pending',
            api_response text,
            credits_used int(11) DEFAULT 1,
            campaign_id varchar(100) DEFAULT '',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            delivered_at datetime DEFAULT NULL,
            read_at datetime DEFAULT NULL,
            error_message text,
            retry_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_number_id (whatsapp_number_id),
            KEY idx_recipient (recipient_number),
            KEY idx_status (delivery_status),
            KEY idx_sent (sent_at),
            KEY idx_campaign (campaign_id),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 7. Tabella Transazioni Crediti per Cliente
        $table_credits = $wpdb->prefix . 'wsp_credits_transactions';
        $sql_credits = "CREATE TABLE IF NOT EXISTS $table_credits (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            transaction_type enum('purchase','usage','refund','bonus','expired') NOT NULL,
            amount int(11) NOT NULL,
            balance_before int(11) NOT NULL,
            balance_after int(11) NOT NULL,
            description text,
            reference_id varchar(100) DEFAULT '',
            payment_id varchar(100) DEFAULT '',
            invoice_number varchar(50) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_type (transaction_type),
            KEY idx_created (created_at),
            KEY idx_reference (reference_id),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 8. Tabella Report Richiesti
        $table_reports = $wpdb->prefix . 'wsp_reports';
        $sql_reports = "CREATE TABLE IF NOT EXISTS $table_reports (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            report_type varchar(50) NOT NULL,
            request_source varchar(50) DEFAULT 'whatsapp',
            request_message text,
            report_data longtext,
            report_url text,
            status enum('pending','processing','completed','failed') DEFAULT 'pending',
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            sent_via varchar(50) DEFAULT '',
            error_message text,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_status (status),
            KEY idx_requested (requested_at),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // 9. Tabella Log Attività
        $table_logs = $wpdb->prefix . 'wsp_activity_log';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) DEFAULT NULL,
            log_type varchar(50) NOT NULL,
            log_message text NOT NULL,
            log_data longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_type (log_type),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // 10. Tabella Webhook Events
        $table_webhooks = $wpdb->prefix . 'wsp_webhook_events';
        $sql_webhooks = "CREATE TABLE IF NOT EXISTS $table_webhooks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) DEFAULT NULL,
            event_type varchar(100) NOT NULL,
            payload longtext,
            response longtext,
            status enum('pending','success','failed') DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_type (event_type),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // 11. Tabella Integrazioni Esterne
        $table_integrations = $wpdb->prefix . 'wsp_integrations';
        $sql_integrations = "CREATE TABLE IF NOT EXISTS $table_integrations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL,
            integration_type varchar(50) NOT NULL,
            integration_name varchar(255) NOT NULL,
            api_endpoint text,
            api_key text,
            api_secret text,
            settings longtext,
            is_active tinyint(1) DEFAULT 1,
            last_sync datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer (customer_id),
            KEY idx_type (integration_type),
            KEY idx_active (is_active),
            FOREIGN KEY (customer_id) REFERENCES {$table_customers}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Crea tutte le tabelle
        dbDelta($sql_customers);
        dbDelta($sql_plans);
        dbDelta($sql_subscriptions);
        dbDelta($sql_numbers);
        dbDelta($sql_campaigns);
        dbDelta($sql_messages);
        dbDelta($sql_credits);
        dbDelta($sql_reports);
        dbDelta($sql_logs);
        dbDelta($sql_webhooks);
        dbDelta($sql_integrations);
        
        // Inserisci i piani predefiniti
        self::insert_default_plans();
        
        // Aggiorna versione database
        update_option('wsp_db_version', '4.0.0');
    }
    
    /**
     * Inserisce i piani di abbonamento predefiniti
     */
    public static function insert_default_plans() {
        global $wpdb;
        
        $table_plans = $wpdb->prefix . 'wsp_subscription_plans';
        
        // Verifica se i piani esistono già
        $existing_plans = $wpdb->get_var("SELECT COUNT(*) FROM $table_plans");
        
        if ($existing_plans == 0) {
            $plans = array(
                array(
                    'plan_name' => 'Piano Standard',
                    'plan_type' => 'standard',
                    'billing_type' => 'mensile',
                    'monthly_price' => 29.00,
                    'credits_included' => 1000,
                    'extra_credit_price' => 0.03,
                    'max_campaigns' => 5,
                    'max_messages_per_month' => 1000,
                    'features' => json_encode(array(
                        'qr_codes' => true,
                        'welcome_messages' => true,
                        'basic_reports' => true,
                        'email_support' => true,
                        'api_access' => false,
                        'custom_branding' => false,
                        'priority_support' => false
                    )),
                    'is_active' => 1
                ),
                array(
                    'plan_name' => 'Piano Avanzato',
                    'plan_type' => 'avanzato',
                    'billing_type' => 'entrambi',
                    'monthly_price' => 79.00,
                    'credits_included' => 5000,
                    'extra_credit_price' => 0.025,
                    'max_campaigns' => 20,
                    'max_messages_per_month' => 5000,
                    'features' => json_encode(array(
                        'qr_codes' => true,
                        'welcome_messages' => true,
                        'advanced_reports' => true,
                        'email_support' => true,
                        'phone_support' => true,
                        'api_access' => true,
                        'custom_branding' => false,
                        'priority_support' => true,
                        'webhooks' => true,
                        'bulk_import' => true
                    )),
                    'is_active' => 1
                ),
                array(
                    'plan_name' => 'Piano Plus',
                    'plan_type' => 'plus',
                    'billing_type' => 'entrambi',
                    'monthly_price' => 199.00,
                    'credits_included' => 20000,
                    'extra_credit_price' => 0.02,
                    'max_campaigns' => -1, // Illimitato
                    'max_messages_per_month' => -1, // Illimitato
                    'features' => json_encode(array(
                        'qr_codes' => true,
                        'welcome_messages' => true,
                        'advanced_reports' => true,
                        'realtime_analytics' => true,
                        'email_support' => true,
                        'phone_support' => true,
                        'dedicated_support' => true,
                        'api_access' => true,
                        'custom_branding' => true,
                        'white_label' => true,
                        'priority_support' => true,
                        'webhooks' => true,
                        'bulk_import' => true,
                        'custom_integrations' => true,
                        'sla_guarantee' => true
                    )),
                    'is_active' => 1
                )
            );
            
            foreach ($plans as $plan) {
                $wpdb->insert($table_plans, $plan);
            }
        }
    }
    
    /**
     * Migra i dati esistenti al nuovo schema multi-tenant
     */
    public static function migrate_existing_data() {
        global $wpdb;
        
        // Crea un cliente predefinito per i dati esistenti
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $default_customer = array(
            'customer_code' => 'DEFAULT001',
            'business_name' => 'Cliente Predefinito',
            'contact_name' => 'Admin',
            'email' => get_option('admin_email'),
            'phone' => '',
            'whatsapp_number' => get_option('wsp_recipient_number', ''),
            'plan_id' => 1, // Piano Standard
            'credits_balance' => get_option('wsp_credits_balance', 0),
            'is_active' => 1,
            'activation_date' => current_time('mysql')
        );
        
        $wpdb->insert($table_customers, $default_customer);
        $customer_id = $wpdb->insert_id;
        
        // Migra i numeri WhatsApp esistenti
        $old_table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table_numbers'") == $old_table_numbers) {
            $wpdb->query("UPDATE $old_table_numbers SET customer_id = $customer_id WHERE customer_id IS NULL");
        }
        
        // Migra le campagne esistenti
        $old_table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table_campaigns'") == $old_table_campaigns) {
            $wpdb->query("UPDATE $old_table_campaigns SET customer_id = $customer_id WHERE customer_id IS NULL");
        }
        
        // Migra i messaggi esistenti
        $old_table_messages = $wpdb->prefix . 'wsp_messages';
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table_messages'") == $old_table_messages) {
            $wpdb->query("UPDATE $old_table_messages SET customer_id = $customer_id WHERE customer_id IS NULL");
        }
        
        update_option('wsp_migration_completed', '4.0.0');
    }
    
    /**
     * Verifica l'integrità del database
     */
    public static function check_database_integrity() {
        global $wpdb;
        
        $issues = array();
        $tables = array(
            'wsp_customers',
            'wsp_subscription_plans',
            'wsp_customer_subscriptions',
            'wsp_whatsapp_numbers',
            'wsp_campaigns',
            'wsp_messages',
            'wsp_credits_transactions',
            'wsp_reports',
            'wsp_activity_log',
            'wsp_webhook_events',
            'wsp_integrations'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $issues[] = "Tabella mancante: $table_name";
            }
        }
        
        return $issues;
    }
}