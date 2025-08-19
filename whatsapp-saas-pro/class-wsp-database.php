<?php
/**
 * Classe per la gestione del database
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 3.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Database {
    
    /**
     * Crea le tabelle del database
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella numeri WhatsApp
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $sql_numbers = "CREATE TABLE IF NOT EXISTS $table_numbers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sender_number (sender_number),
            KEY idx_recipient_number (recipient_number),
            KEY idx_campaign (campaign_id),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // Tabella messaggi
        $table_messages = $wpdb->prefix . 'wsp_messages';
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            whatsapp_number_id bigint(20) DEFAULT NULL,
            recipient_number varchar(20) NOT NULL,
            message_content text NOT NULL,
            message_type varchar(50) DEFAULT 'manual',
            delivery_status varchar(50) DEFAULT 'pending',
            api_response text,
            credits_used int(11) DEFAULT 1,
            campaign_id varchar(100) DEFAULT '',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_number_id (whatsapp_number_id),
            KEY idx_recipient (recipient_number),
            KEY idx_status (delivery_status),
            KEY idx_sent (sent_at)
        ) $charset_collate;";
        
        // Tabella campagne
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        $sql_campaigns = "CREATE TABLE IF NOT EXISTS $table_campaigns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id varchar(100) NOT NULL,
            campaign_name varchar(255) NOT NULL,
            campaign_type varchar(50) DEFAULT 'qr',
            qr_code_url text,
            landing_page_url text,
            welcome_message text,
            total_scans int(11) DEFAULT 0,
            total_registrations int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_campaign_id (campaign_id),
            KEY idx_active (is_active)
        ) $charset_collate;";
        
        // Tabella transazioni crediti
        $table_credits = $wpdb->prefix . 'wsp_credits_transactions';
        $sql_credits = "CREATE TABLE IF NOT EXISTS $table_credits (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            transaction_type varchar(50) NOT NULL,
            amount int(11) NOT NULL,
            balance_after int(11) NOT NULL,
            description text,
            reference_id varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (transaction_type),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // Tabella log attività
        $table_logs = $wpdb->prefix . 'wsp_activity_log';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            log_message text NOT NULL,
            log_data longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_type (log_type),
            KEY idx_user (user_id),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_numbers);
        dbDelta($sql_messages);
        dbDelta($sql_campaigns);
        dbDelta($sql_credits);
        dbDelta($sql_logs);
        
        // Aggiorna versione database
        update_option('wsp_db_version', '3.0.0');
    }
    
    /**
     * Aggiorna la struttura delle tabelle esistenti
     */
    public static function update_table_structure() {
        global $wpdb;
        
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Verifica e aggiungi colonne mancanti
        $columns_to_check = array(
            'campaign_id' => "ALTER TABLE $table_numbers ADD COLUMN campaign_id varchar(100) DEFAULT ''",
            'source' => "ALTER TABLE $table_numbers ADD COLUMN source varchar(50) DEFAULT 'manual'",
            'sender_formatted' => "ALTER TABLE $table_numbers ADD COLUMN sender_formatted varchar(20) DEFAULT ''",
            'recipient_number' => "ALTER TABLE $table_numbers ADD COLUMN recipient_number varchar(20) DEFAULT ''",
            'recipient_name' => "ALTER TABLE $table_numbers ADD COLUMN recipient_name varchar(255) DEFAULT ''",
            'recipient_email' => "ALTER TABLE $table_numbers ADD COLUMN recipient_email varchar(255) DEFAULT ''",
            'email_subject' => "ALTER TABLE $table_numbers ADD COLUMN email_subject text",
            'updated_at' => "ALTER TABLE $table_numbers ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP"
        );
        
        foreach ($columns_to_check as $column => $sql) {
            $column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_numbers LIKE '$column'");
            if (!$column_exists) {
                $wpdb->query($sql);
            }
        }
    }
    
    /**
     * Ottieni statistiche
     */
    public static function get_statistics($period = 'all') {
        global $wpdb;
        
        $stats = array();
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $table_messages = $wpdb->prefix . 'wsp_messages';
        
        // Totale numeri
        $stats['total_numbers'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
        
        // Numeri oggi
        $today = current_time('Y-m-d');
        $stats['numbers_today'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_numbers WHERE DATE(created_at) = %s",
                $today
            )
        );
        
        // Messaggi inviati
        $stats['messages_sent'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_messages WHERE delivery_status = 'sent'"
        );
        
        // Messaggi oggi
        $stats['messages_today'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_messages WHERE DATE(sent_at) = %s",
                $today
            )
        );
        
        // Per periodo specifico
        if ($period !== 'all') {
            $date_condition = '';
            
            switch ($period) {
                case 'today':
                    $date_condition = "DATE(created_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $date_condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
            
            if ($date_condition) {
                $stats['period_numbers'] = $wpdb->get_var(
                    "SELECT COUNT(*) FROM $table_numbers WHERE $date_condition"
                );
                $stats['period_messages'] = $wpdb->get_var(
                    "SELECT COUNT(*) FROM $table_messages WHERE " . str_replace('created_at', 'sent_at', $date_condition)
                );
            }
        }
        
        return $stats;
    }
    
    /**
     * Log attività
     */
    public static function log_activity($type, $message, $data = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_activity_log';
        
        // Verifica che la tabella esista
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            // Se la tabella non esiste, proviamo a crearla
            self::create_tables();
        }
        
        $log_data = array(
            'log_type' => $type,
            'log_message' => $message,
            'log_data' => $data ? json_encode($data) : null,
            'user_id' => get_current_user_id() ?: 0,  // Usa 0 se non c'è utente loggato
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $log_data);
        
        if ($result === false) {
            error_log('WSP: Errore inserimento log: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Esporta CSV
     */
    public static function ajax_export_csv() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }
        
        global $wpdb;
        
        $period = $_GET['period'] ?? 'all';
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        $where = '';
        if ($period === 'today') {
            $where = "WHERE DATE(created_at) = CURDATE()";
        } elseif ($period === 'yesterday') {
            $where = "WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        }
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // Headers CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=whatsapp_numbers_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Intestazioni
        if (!empty($results)) {
            fputcsv($output, array_keys($results[0]));
        }
        
        // Dati
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get stats via AJAX
     */
    public static function ajax_get_stats() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        $period = $_POST['period'] ?? 'all';
        $stats = self::get_statistics($period);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Ottieni numeri recenti
     */
    public static function get_recent_numbers($limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Ottieni messaggi recenti
     */
    public static function get_recent_messages($limit = 10) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_messages';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY sent_at DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Cerca numero
     */
    public static function find_number($phone) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $phone = str_replace('+', '', $phone);
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE sender_number = %s OR sender_formatted = %s OR sender_formatted = %s",
                $phone,
                '+' . $phone,
                $phone
            )
        );
    }
    
    /**
     * Salva numero
     */
    public static function save_number($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Controlla se esiste
        $existing = self::find_number($data['sender_number'] ?? $data['phone'] ?? '');
        
        if ($existing) {
            // Aggiorna
            $wpdb->update(
                $table,
                $data,
                array('id' => $existing->id)
            );
            return $existing->id;
        } else {
            // Inserisci
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }
}
--------------------------------------------------------------------------------
--------------------------------------------------------------------------------