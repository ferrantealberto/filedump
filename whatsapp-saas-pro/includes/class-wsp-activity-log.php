<?php
/**
 * Gestione Activity Log
 * 
 * @package WhatsApp_SaaS_Pro  
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSP_Activity_Log {
    
    /**
     * Log di un'attività
     */
    public static function log($type, $data = array(), $customer_id = null) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'wsp_activity_log';
        
        // Prepara dati log
        $log_data = array(
            'customer_id' => $customer_id,
            'log_type' => $type,
            'log_message' => self::get_log_message($type, $data),
            'log_data' => json_encode($data),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        );
        
        return $wpdb->insert($table_logs, $log_data);
    }
    
    /**
     * Ottieni messaggio log basato sul tipo
     */
    private static function get_log_message($type, $data) {
        $messages = array(
            'customer_registration' => 'Nuovo cliente registrato: ' . ($data['email'] ?? ''),
            'customer_activation' => 'Cliente attivato ID: ' . ($data['customer_id'] ?? ''),
            'plan_change' => 'Piano modificato per cliente ID: ' . ($data['customer_id'] ?? ''),
            'report_generated' => 'Report generato tipo: ' . ($data['report_type'] ?? ''),
            'message_sent' => 'Messaggio inviato a: ' . ($data['recipient'] ?? ''),
            'webhook_error' => 'Errore webhook: ' . ($data['error'] ?? ''),
            'credit_purchase' => 'Acquisto crediti: ' . ($data['amount'] ?? 0),
            'campaign_created' => 'Nuova campagna creata: ' . ($data['campaign_name'] ?? ''),
            'number_extracted' => 'Numero estratto: ' . ($data['number'] ?? ''),
            'api_request' => 'Richiesta API: ' . ($data['endpoint'] ?? ''),
            'login' => 'Accesso cliente: ' . ($data['email'] ?? ''),
            'logout' => 'Disconnessione cliente: ' . ($data['email'] ?? ''),
            'error' => 'Errore: ' . ($data['message'] ?? '')
        );
        
        return $messages[$type] ?? "Attività: $type";
    }
    
    /**
     * Ottieni IP del client
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '';
    }
    
    /**
     * Ottieni log per cliente
     */
    public static function get_customer_logs($customer_id, $limit = 100) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'wsp_activity_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_logs 
            WHERE customer_id = %d 
            ORDER BY created_at DESC 
            LIMIT %d",
            $customer_id,
            $limit
        ));
    }
    
    /**
     * Pulisci vecchi log
     */
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . 'wsp_activity_log';
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_logs WHERE created_at < %s",
            $date_threshold
        ));
    }
}