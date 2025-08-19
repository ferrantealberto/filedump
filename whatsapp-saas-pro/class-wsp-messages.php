<?php
/**
 * Gestione messaggi
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Messages {
    
    public static function ajax_send_message() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($phone) || empty($message)) {
            wp_send_json_error('Dati mancanti');
        }
        
        $mail2wa = new WSP_Mail2Wa();
        $result = $mail2wa->send_message($phone, $message);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public static function ajax_send_bulk() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $recipients = $_POST['recipients'] ?? array();
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($recipients) || empty($message)) {
            wp_send_json_error('Dati mancanti');
        }
        
        $mail2wa = new WSP_Mail2Wa();
        $results = $mail2wa->send_bulk_messages($recipients, $message);
        
        wp_send_json_success($results);
    }
    
    public static function ajax_send_welcome() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        
        if (empty($phone)) {
            wp_send_json_error('Numero mancante');
        }
        
        $mail2wa = new WSP_Mail2Wa();
        $result = $mail2wa->send_welcome_message($phone, $name);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public static function ajax_get_recipients() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $query = "SELECT id, sender_number, sender_name, sender_formatted FROM $table";
        if (!empty($search)) {
            $query .= $wpdb->prepare(" WHERE sender_number LIKE %s OR sender_name LIKE %s", 
                '%' . $search . '%', 
                '%' . $search . '%'
            );
        }
        $query .= " ORDER BY created_at DESC LIMIT 50";
        
        $results = $wpdb->get_results($query);
        
        wp_send_json_success($results);
    }
    
    public static function send_daily_report() {
        $email = get_option('wsp_report_email', get_option('admin_email'));
        $stats = WSP_Database::get_statistics('today');
        
        $subject = 'Report Giornaliero WhatsApp - ' . date('d/m/Y');
        
        $message = "Report Giornaliero WhatsApp SaaS\n\n";
        $message .= "Data: " . date('d/m/Y H:i') . "\n\n";
        $message .= "STATISTICHE OGGI:\n";
        $message .= "- Nuovi numeri: " . ($stats['numbers_today'] ?? 0) . "\n";
        $message .= "- Messaggi inviati: " . ($stats['messages_today'] ?? 0) . "\n";
        $message .= "- Crediti rimanenti: " . WSP_Credits::get_balance() . "\n\n";
        $message .= "STATISTICHE TOTALI:\n";
        $message .= "- Totale numeri: " . ($stats['total_numbers'] ?? 0) . "\n";
        $message .= "- Totale messaggi: " . ($stats['messages_sent'] ?? 0) . "\n";
        
        wp_mail($email, $subject, $message);
        
        WSP_Database::log_activity('daily_report', 'Report giornaliero inviato a ' . $email);
    }
}
--------------------------------------------------------------------------------
--------------------------------------------------------------------------------