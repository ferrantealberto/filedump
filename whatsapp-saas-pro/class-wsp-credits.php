<?php
/**
 * Gestione crediti
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Credits {
    
    public static function get_balance() {
        return (int) get_option('wsp_credits_balance', 0);
    }
    
    public static function set_balance($amount) {
        update_option('wsp_credits_balance', max(0, (int) $amount));
        self::log_transaction('set', $amount, 'Impostazione saldo');
    }
    
    public static function add($amount) {
        $current = self::get_balance();
        $new_balance = $current + (int) $amount;
        update_option('wsp_credits_balance', $new_balance);
        self::log_transaction('add', $amount, 'Aggiunta crediti');
        return $new_balance;
    }
    
    public static function deduct($amount) {
        $current = self::get_balance();
        $new_balance = max(0, $current - (int) $amount);
        update_option('wsp_credits_balance', $new_balance);
        self::log_transaction('deduct', -$amount, 'Deduzione crediti');
        return $new_balance;
    }
    
    public static function has_credits($amount = 1) {
        return self::get_balance() >= $amount;
    }
    
    private static function log_transaction($type, $amount, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_credits_transactions';
        
        $wpdb->insert($table, array(
            'transaction_type' => $type,
            'amount' => $amount,
            'balance_after' => self::get_balance(),
            'description' => $description
        ));
    }
    
    public static function ajax_add_credits() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $amount = (int) ($_POST['amount'] ?? 0);
        
        if ($amount <= 0) {
            wp_send_json_error('Importo non valido');
        }
        
        $new_balance = self::add($amount);
        
        wp_send_json_success(array(
            'new_balance' => $new_balance,
            'message' => sprintf('%d crediti aggiunti con successo', $amount)
        ));
    }
    
    public static function ajax_check_balance() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        wp_send_json_success(array(
            'balance' => self::get_balance()
        ));
    }
}
--------------------------------------------------------------------------------
--------------------------------------------------------------------------------