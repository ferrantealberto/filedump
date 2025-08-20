<?php
/**
 * Classe per inserire dati di esempio
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Sample_Data {
    
    /**
     * Inserisce dati di esempio nel database
     */
    public static function insert_sample_data() {
        global $wpdb;
        
        // Tabella numeri
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        // Controlla se ci sono già dati
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_numbers");
        
        if ($count == 0) {
            // Inserisci numeri di esempio
            $sample_numbers = array(
                array(
                    'sender_number' => '393351234567',
                    'sender_formatted' => '+393351234567',
                    'sender_name' => 'Mario Rossi',
                    'sender_email' => 'mario@example.com',
                    'recipient_number' => '',
                    'recipient_name' => '',
                    'recipient_email' => '',
                    'email_subject' => 'Test registrazione',
                    'campaign_id' => 'campaign_101',
                    'source' => 'manual',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'sender_number' => '393887654321',
                    'sender_formatted' => '+393887654321',
                    'sender_name' => 'Giulia Bianchi',
                    'sender_email' => 'giulia@example.com',
                    'recipient_number' => '',
                    'recipient_name' => '',
                    'recipient_email' => '',
                    'email_subject' => 'Registrazione da QR Code',
                    'campaign_id' => 'campaign_101',
                    'source' => 'qrcode',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'sender_number' => '393401234567',
                    'sender_formatted' => '+393401234567',
                    'sender_name' => 'Luca Verdi',
                    'sender_email' => 'luca@example.com',
                    'recipient_number' => '',
                    'recipient_name' => '',
                    'recipient_email' => '',
                    'email_subject' => 'Registrazione via API',
                    'campaign_id' => 'campaign_102',
                    'source' => 'api',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                ),
                array(
                    'sender_number' => '393771234567',
                    'sender_formatted' => '+393771234567',
                    'sender_name' => 'Anna Neri',
                    'sender_email' => 'anna@example.com',
                    'recipient_number' => '',
                    'recipient_name' => '',
                    'recipient_email' => '',
                    'email_subject' => 'Registrazione via email',
                    'campaign_id' => 'campaign_102',
                    'source' => 'email',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
                ),
                array(
                    'sender_number' => '393921234567',
                    'sender_formatted' => '+393921234567',
                    'sender_name' => 'Paolo Gialli',
                    'sender_email' => 'paolo@example.com',
                    'recipient_number' => '',
                    'recipient_name' => '',
                    'recipient_email' => '',
                    'email_subject' => 'Registrazione manuale',
                    'campaign_id' => '',
                    'source' => 'manual',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
                )
            );
            
            foreach ($sample_numbers as $number) {
                $wpdb->insert($table_numbers, $number);
            }
        }
        
        // Tabella messaggi
        $table_messages = $wpdb->prefix . 'wsp_messages';
        $count_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table_messages");
        
        if ($count_messages == 0) {
            $sample_messages = array(
                array(
                    'whatsapp_number_id' => 1,
                    'recipient_number' => '+393351234567',
                    'message_content' => 'Benvenuto nel nostro servizio WhatsApp!',
                    'message_type' => 'welcome',
                    'delivery_status' => 'sent',
                    'credits_used' => 1,
                    'sent_at' => current_time('mysql')
                ),
                array(
                    'whatsapp_number_id' => 2,
                    'recipient_number' => '+393887654321',
                    'message_content' => 'Grazie per la registrazione!',
                    'message_type' => 'welcome',
                    'delivery_status' => 'sent',
                    'credits_used' => 1,
                    'sent_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ),
                array(
                    'whatsapp_number_id' => 3,
                    'recipient_number' => '+393401234567',
                    'message_content' => 'Promozione speciale per te!',
                    'message_type' => 'promo',
                    'delivery_status' => 'failed',
                    'credits_used' => 0,
                    'sent_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
                )
            );
            
            foreach ($sample_messages as $message) {
                $wpdb->insert($table_messages, $message);
            }
        }
        
        // Tabella campagne
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        $count_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM $table_campaigns");
        
        if ($count_campaigns == 0) {
            $sample_campaigns = array(
                array(
                    'campaign_id' => 'campaign_101',
                    'campaign_name' => 'Campagna Estate 2024',
                    'campaign_type' => 'qr',
                    'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode(home_url()),
                    'landing_page_url' => home_url('/campaign/estate-2024'),
                    'welcome_message' => 'Benvenuto nella campagna Estate 2024!',
                    'total_scans' => 25,
                    'total_registrations' => 2,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
                ),
                array(
                    'campaign_id' => 'campaign_102',
                    'campaign_name' => 'Promo Black Friday',
                    'campaign_type' => 'qr',
                    'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode(home_url()),
                    'landing_page_url' => home_url('/campaign/black-friday'),
                    'welcome_message' => 'Offerte esclusive Black Friday!',
                    'total_scans' => 50,
                    'total_registrations' => 2,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-14 days'))
                )
            );
            
            foreach ($sample_campaigns as $campaign) {
                $wpdb->insert($table_campaigns, $campaign);
            }
        }
        
        // Aggiungi crediti se sono a zero
        $credits = get_option('wsp_credits_balance', 0);
        if ($credits == 0) {
            update_option('wsp_credits_balance', 100);
        }
        
        return true;
    }
    
    /**
     * Rimuove tutti i dati di esempio
     */
    public static function remove_sample_data() {
        global $wpdb;
        
        // Pulisci tutte le tabelle
        $tables = array(
            $wpdb->prefix . 'wsp_whatsapp_numbers',
            $wpdb->prefix . 'wsp_messages',
            $wpdb->prefix . 'wsp_campaigns',
            $wpdb->prefix . 'wsp_credits_transactions',
            $wpdb->prefix . 'wsp_activity_log'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
        
        return true;
    }
}