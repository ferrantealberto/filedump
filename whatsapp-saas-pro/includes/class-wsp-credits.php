<?php
/**
 * Gestione crediti
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Credits {
    
    public static function get_balance($customer_id = null) {
        if ($customer_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'wsp_customers';
            return intval($wpdb->get_var($wpdb->prepare(
                "SELECT credits_balance FROM $table WHERE id = %d",
                $customer_id
            )));
        }
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
    
    /**
     * Aggiungi crediti a un cliente specifico (multi-tenant)
     */
    public static function add_credits($customer_id, $amount, $description = '') {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $table_transactions = $wpdb->prefix . 'wsp_credits_transactions';
        
        // Ottieni saldo attuale
        $current_balance = self::get_balance($customer_id);
        $new_balance = $current_balance + intval($amount);
        
        // Aggiorna saldo cliente
        $wpdb->update(
            $table_customers,
            array('credits_balance' => $new_balance),
            array('id' => $customer_id)
        );
        
        // Registra transazione
        $wpdb->insert($table_transactions, array(
            'customer_id' => $customer_id,
            'transaction_type' => 'purchase',
            'amount' => $amount,
            'balance_before' => $current_balance,
            'balance_after' => $new_balance,
            'description' => $description
        ));
        
        return $new_balance;
    }
    
    /**
     * Deduce crediti da un cliente specifico
     */
    public static function deduct_credits($customer_id, $amount, $description = '') {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $table_transactions = $wpdb->prefix . 'wsp_credits_transactions';
        
        // Ottieni saldo attuale
        $current_balance = self::get_balance($customer_id);
        
        if ($current_balance < $amount) {
            return false; // Crediti insufficienti
        }
        
        $new_balance = $current_balance - intval($amount);
        
        // Aggiorna saldo cliente
        $wpdb->update(
            $table_customers,
            array('credits_balance' => $new_balance),
            array('id' => $customer_id)
        );
        
        // Registra transazione
        $wpdb->insert($table_transactions, array(
            'customer_id' => $customer_id,
            'transaction_type' => 'usage',
            'amount' => -$amount,
            'balance_before' => $current_balance,
            'balance_after' => $new_balance,
            'description' => $description
        ));
        
        return $new_balance;
    }
    
    /**
     * Registra transazione crediti
     */
    public static function add_transaction($customer_id, $type, $amount, $description = '') {
        global $wpdb;
        
        $table_transactions = $wpdb->prefix . 'wsp_credits_transactions';
        
        $current_balance = self::get_balance($customer_id);
        $new_balance = $current_balance + $amount;
        
        return $wpdb->insert($table_transactions, array(
            'customer_id' => $customer_id,
            'transaction_type' => $type,
            'amount' => $amount,
            'balance_before' => $current_balance,
            'balance_after' => $new_balance,
            'description' => $description,
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Ricalcola saldo cliente
     */
    public static function recalculate_balance($customer_id) {
        global $wpdb;
        
        $table_transactions = $wpdb->prefix . 'wsp_credits_transactions';
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        // Somma tutte le transazioni
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_transactions WHERE customer_id = %d",
            $customer_id
        ));
        
        $new_balance = max(0, intval($total));
        
        // Aggiorna saldo
        $wpdb->update(
            $table_customers,
            array('credits_balance' => $new_balance),
            array('id' => $customer_id)
        );
        
        return $new_balance;
    }
}