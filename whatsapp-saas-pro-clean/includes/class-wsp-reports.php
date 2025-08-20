<?php
/**
 * Gestione Report e Integrazione N8N
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSP_Reports {
    
    /**
     * Processa comando WhatsApp per report
     */
    public static function process_whatsapp_command($from_number, $message_body, $customer_id = null) {
        global $wpdb;
        
        // Normalizza il messaggio
        $command = strtoupper(trim($message_body));
        
        // Se non viene passato customer_id, trova il cliente dal numero WhatsApp
        if (!$customer_id) {
            $customer = WSP_Customers::get_customer_by_whatsapp($from_number);
            if (!$customer) {
                return array(
                    'success' => false,
                    'message' => 'Cliente non trovato per questo numero WhatsApp'
                );
            }
            $customer_id = $customer->id;
        }
        
        // Verifica comando REPORT NUMERI
        if ($command === 'REPORT NUMERI' || $command === 'REPORT' || $command === 'NUMERI') {
            return self::generate_numbers_report($customer_id, $from_number);
        }
        
        // Altri comandi possibili
        $commands = array(
            'SALDO' => 'check_balance',
            'CREDITI' => 'check_balance',
            'STATO' => 'check_status',
            'HELP' => 'send_help',
            'AIUTO' => 'send_help',
            'PIANO' => 'check_plan',
            'STATISTICHE' => 'send_statistics',
            'STATS' => 'send_statistics'
        );
        
        foreach ($commands as $cmd => $method) {
            if (strpos($command, $cmd) === 0) {
                if (method_exists(__CLASS__, $method)) {
                    return self::$method($customer_id, $from_number);
                }
            }
        }
        
        return array(
            'success' => false,
            'message' => 'Comando non riconosciuto. Invia HELP per la lista dei comandi disponibili.'
        );
    }
    
    /**
     * Genera report numeri WhatsApp raccolti
     */
    public static function generate_numbers_report($customer_id, $request_from = null) {
        global $wpdb;
        
        $table_reports = $wpdb->prefix . 'wsp_reports';
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $table_customers = $wpdb->prefix . 'wsp_customers';
        
        // Ottieni dati cliente
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_customers WHERE id = %d",
            $customer_id
        ));
        
        if (!$customer) {
            return array(
                'success' => false,
                'message' => 'Cliente non trovato'
            );
        }
        
        // Crea record report
        $report_data = array(
            'customer_id' => $customer_id,
            'report_type' => 'numbers',
            'request_source' => 'whatsapp',
            'request_message' => $request_from ? "Richiesto da: $request_from" : '',
            'status' => 'processing',
            'requested_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_reports, $report_data);
        $report_id = $wpdb->insert_id;
        
        try {
            // Ottieni numeri WhatsApp del cliente
            $numbers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_numbers 
                WHERE customer_id = %d 
                ORDER BY created_at DESC",
                $customer_id
            ));
            
            // Prepara dati per il report
            $report_content = array(
                'customer' => array(
                    'id' => $customer->id,
                    'code' => $customer->customer_code,
                    'business_name' => $customer->business_name,
                    'email' => $customer->email
                ),
                'summary' => array(
                    'total_numbers' => count($numbers),
                    'active_numbers' => 0,
                    'by_campaign' => array(),
                    'by_source' => array(),
                    'by_date' => array()
                ),
                'numbers' => array()
            );
            
            // Analizza i numeri
            foreach ($numbers as $number) {
                // Conta numeri attivi
                if ($number->is_active) {
                    $report_content['summary']['active_numbers']++;
                }
                
                // Raggruppa per campagna
                if (!empty($number->campaign_id)) {
                    if (!isset($report_content['summary']['by_campaign'][$number->campaign_id])) {
                        $report_content['summary']['by_campaign'][$number->campaign_id] = 0;
                    }
                    $report_content['summary']['by_campaign'][$number->campaign_id]++;
                }
                
                // Raggruppa per source
                if (!isset($report_content['summary']['by_source'][$number->source])) {
                    $report_content['summary']['by_source'][$number->source] = 0;
                }
                $report_content['summary']['by_source'][$number->source]++;
                
                // Raggruppa per data
                $date = date('Y-m-d', strtotime($number->created_at));
                if (!isset($report_content['summary']['by_date'][$date])) {
                    $report_content['summary']['by_date'][$date] = 0;
                }
                $report_content['summary']['by_date'][$date]++;
                
                // Aggiungi dettagli numero
                $report_content['numbers'][] = array(
                    'sender_number' => $number->sender_number,
                    'sender_name' => $number->sender_name,
                    'sender_email' => $number->sender_email,
                    'campaign_id' => $number->campaign_id,
                    'source' => $number->source,
                    'created_at' => $number->created_at,
                    'is_active' => $number->is_active
                );
            }
            
            // Genera CSV
            $csv_content = self::generate_csv_content($report_content);
            
            // Salva il CSV temporaneamente
            $upload_dir = wp_upload_dir();
            $report_dir = $upload_dir['basedir'] . '/wsp-reports';
            if (!file_exists($report_dir)) {
                wp_mkdir_p($report_dir);
            }
            
            $filename = sprintf(
                'report-numbers-%s-%s.csv',
                $customer->customer_code,
                date('Y-m-d-His')
            );
            $filepath = $report_dir . '/' . $filename;
            file_put_contents($filepath, $csv_content);
            
            $report_url = $upload_dir['baseurl'] . '/wsp-reports/' . $filename;
            
            // Aggiorna record report
            $wpdb->update(
                $table_reports,
                array(
                    'report_data' => json_encode($report_content),
                    'report_url' => $report_url,
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $report_id)
            );
            
            // Trigger webhook N8N
            self::trigger_n8n_webhook($customer, $report_content, $report_url);
            
            // Log attività
            WSP_Activity_Log::log('report_generated', array(
                'customer_id' => $customer_id,
                'report_id' => $report_id,
                'report_type' => 'numbers'
            ));
            
            return array(
                'success' => true,
                'report_id' => $report_id,
                'report_url' => $report_url,
                'message' => 'Report generato con successo. Verrà inviato tramite WhatsApp.',
                'data' => $report_content
            );
            
        } catch (Exception $e) {
            // Aggiorna stato errore
            $wpdb->update(
                $table_reports,
                array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ),
                array('id' => $report_id)
            );
            
            return array(
                'success' => false,
                'message' => 'Errore nella generazione del report: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Genera contenuto CSV
     */
    private static function generate_csv_content($report_data) {
        $csv = array();
        
        // Header
        $csv[] = array(
            'Numero WhatsApp',
            'Nome',
            'Email',
            'Campagna',
            'Fonte',
            'Data Registrazione',
            'Stato'
        );
        
        // Dati
        foreach ($report_data['numbers'] as $number) {
            $csv[] = array(
                $number['sender_number'],
                $number['sender_name'],
                $number['sender_email'],
                $number['campaign_id'],
                $number['source'],
                $number['created_at'],
                $number['is_active'] ? 'Attivo' : 'Inattivo'
            );
        }
        
        // Converti in stringa CSV
        $output = '';
        foreach ($csv as $row) {
            $output .= '"' . implode('","', array_map('addslashes', $row)) . '"' . "\n";
        }
        
        // Aggiungi BOM per Excel
        return "\xEF\xBB\xBF" . $output;
    }
    
    /**
     * Trigger webhook N8N per invio report
     */
    private static function trigger_n8n_webhook($customer, $report_data, $report_url) {
        // URL webhook N8N (da configurare)
        $webhook_url = get_option('wsp_n8n_webhook_url', '');
        
        if (empty($webhook_url)) {
            // Se non configurato, usa webhook interno
            $webhook_url = 'https://your-n8n-instance.com/webhook/wsp-report';
        }
        
        $payload = array(
            'event' => 'report_requested',
            'customer' => array(
                'id' => $customer->id,
                'code' => $customer->customer_code,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'whatsapp_number' => $customer->whatsapp_number
            ),
            'report' => array(
                'type' => 'numbers',
                'url' => $report_url,
                'summary' => $report_data['summary'],
                'generated_at' => current_time('mysql')
            ),
            'mail2wa' => array(
                'recipient' => $customer->whatsapp_number,
                'subject' => 'Report Numeri WhatsApp - ' . $customer->business_name,
                'message' => self::format_whatsapp_report_message($report_data),
                'attachment_url' => $report_url
            )
        );
        
        // Invia webhook
        $response = wp_remote_post($webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-WSP-API-Key' => $customer->api_key
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            WSP_Activity_Log::log('webhook_error', array(
                'customer_id' => $customer->id,
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Formatta messaggio report per WhatsApp
     */
    private static function format_whatsapp_report_message($report_data) {
        $message = "*📊 REPORT NUMERI WHATSAPP*\n\n";
        $message .= "🏢 *Azienda:* {$report_data['customer']['business_name']}\n";
        $message .= "📅 *Data:* " . date('d/m/Y H:i') . "\n\n";
        
        $message .= "*📈 RIEPILOGO*\n";
        $message .= "• Totale numeri: *{$report_data['summary']['total_numbers']}*\n";
        $message .= "• Numeri attivi: *{$report_data['summary']['active_numbers']}*\n\n";
        
        if (!empty($report_data['summary']['by_campaign'])) {
            $message .= "*🎯 PER CAMPAGNA*\n";
            foreach ($report_data['summary']['by_campaign'] as $campaign => $count) {
                $message .= "• $campaign: $count\n";
            }
            $message .= "\n";
        }
        
        if (!empty($report_data['summary']['by_source'])) {
            $message .= "*📱 PER FONTE*\n";
            foreach ($report_data['summary']['by_source'] as $source => $count) {
                $message .= "• " . ucfirst($source) . ": $count\n";
            }
            $message .= "\n";
        }
        
        $message .= "📎 *Il report completo è allegato in formato CSV*\n\n";
        $message .= "Per richiedere un nuovo report, invia:\n";
        $message .= "*REPORT NUMERI*";
        
        return $message;
    }
    
    /**
     * Verifica saldo crediti
     */
    private static function check_balance($customer_id, $from_number) {
        global $wpdb;
        
        $table_customers = $wpdb->prefix . 'wsp_customers';
        $table_subscriptions = $wpdb->prefix . 'wsp_customer_subscriptions';
        
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, s.credits_remaining, s.messages_sent_this_period, p.plan_name
            FROM $table_customers c
            LEFT JOIN $table_subscriptions s ON c.id = s.customer_id AND s.subscription_status = 'active'
            LEFT JOIN {$wpdb->prefix}wsp_subscription_plans p ON s.plan_id = p.id
            WHERE c.id = %d",
            $customer_id
        ));
        
        if (!$customer) {
            return array(
                'success' => false,
                'message' => 'Cliente non trovato'
            );
        }
        
        $message = "*💳 SALDO CREDITI*\n\n";
        $message .= "🏢 *Azienda:* {$customer->business_name}\n";
        $message .= "📦 *Piano:* {$customer->plan_name}\n\n";
        $message .= "💰 *Crediti disponibili:* {$customer->credits_balance}\n";
        $message .= "📊 *Crediti rimanenti periodo:* {$customer->credits_remaining}\n";
        $message .= "📤 *Messaggi inviati questo mese:* {$customer->messages_sent_this_period}\n\n";
        $message .= "Per acquistare crediti aggiuntivi, contatta il supporto.";
        
        // Invia risposta via Mail2Wa
        self::send_whatsapp_response($from_number, $message);
        
        return array(
            'success' => true,
            'message' => $message
        );
    }
    
    /**
     * Mostra help comandi
     */
    private static function send_help($customer_id, $from_number) {
        $message = "*📚 COMANDI DISPONIBILI*\n\n";
        $message .= "Invia uno dei seguenti comandi:\n\n";
        $message .= "*REPORT NUMERI* - Ricevi report numeri raccolti\n";
        $message .= "*SALDO* - Verifica crediti disponibili\n";
        $message .= "*STATO* - Stato account e servizi\n";
        $message .= "*PIANO* - Dettagli piano attivo\n";
        $message .= "*STATISTICHE* - Statistiche utilizzo\n";
        $message .= "*HELP* - Mostra questo messaggio\n\n";
        $message .= "💡 Esempio: invia semplicemente *REPORT NUMERI*";
        
        // Invia risposta via Mail2Wa
        self::send_whatsapp_response($from_number, $message);
        
        return array(
            'success' => true,
            'message' => $message
        );
    }
    
    /**
     * Invia risposta WhatsApp tramite Mail2Wa
     */
    private static function send_whatsapp_response($to_number, $message) {
        // Usa la classe Mail2Wa esistente
        if (class_exists('WSP_Mail2Wa')) {
            $mail2wa = new WSP_Mail2Wa();
            $mail2wa->send_message($to_number, $message);
        }
    }
    
    /**
     * Ottieni report per cliente
     */
    public static function get_customer_reports($customer_id, $limit = 10) {
        global $wpdb;
        
        $table_reports = $wpdb->prefix . 'wsp_reports';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_reports 
            WHERE customer_id = %d 
            ORDER BY requested_at DESC 
            LIMIT %d",
            $customer_id,
            $limit
        ));
    }
    
    /**
     * Genera report statistiche
     */
    private static function send_statistics($customer_id, $from_number) {
        $stats = WSP_Customers::get_customer_stats($customer_id);
        
        $message = "*📊 STATISTICHE ACCOUNT*\n\n";
        $message .= "📱 *Numeri raccolti:* {$stats['total_numbers']}\n";
        $message .= "💬 *Messaggi inviati:* {$stats['total_messages']}\n";
        $message .= "🎯 *Campagne attive:* {$stats['active_campaigns']}\n";
        $message .= "💰 *Crediti utilizzati questo mese:* {$stats['credits_used_this_month']}\n";
        $message .= "💳 *Crediti disponibili:* {$stats['credits_balance']}\n\n";
        $message .= "📅 *Periodo:* " . date('F Y');
        
        self::send_whatsapp_response($from_number, $message);
        
        return array(
            'success' => true,
            'message' => $message
        );
    }
}