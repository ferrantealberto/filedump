<?php
/**
 * Classe per la gestione delle API REST
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 3.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_API {
    
    /**
     * Verifica API Key
     */
    public static function verify_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            $api_key = $request->get_param('api_key');
        }
        
        if (empty($api_key)) {
            $api_key = $request->get_param('apiKey');
        }
        
        $stored_key = get_option('wsp_api_key', 'demo-api-key-9lz721sv0xTjFNVA');
        
        return $api_key === $stored_key;
    }
    
    /**
     * Handle webhook n8n
     */
    public static function handle_webhook($request) {
        try {
            $data = $request->get_json_params();
            
            if (empty($data)) {
                return new WP_Error('no_data', 'Nessun dato ricevuto', array('status' => 400));
            }
            
            // Log webhook ricevuto (con gestione errori)
            try {
                WSP_Database::log_activity(
                    'webhook_received',
                    'Webhook ricevuto da n8n',
                    $data
                );
            } catch (Exception $e) {
                // Se il logging fallisce, continuiamo comunque
                error_log('WSP: Errore logging webhook: ' . $e->getMessage());
            }
            
            // Processa numeri se presenti
            if (isset($data['numbers']) && is_array($data['numbers'])) {
                $processed = self::process_numbers($data['numbers'], $data['campaign'] ?? null);
                
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Webhook processato',
                    'processed' => $processed
                ));
            }
            
            // Processa singolo numero
            if (isset($data['phone'])) {
                $result = self::process_single_number($data);
                
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Numero processato',
                    'result' => $result
                ));
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Webhook ricevuto',
                'data' => $data
            ));
            
        } catch (Exception $e) {
            return new WP_Error(
                'webhook_error',
                'Errore nel processare il webhook: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Ricevi numeri WhatsApp
     */
    public static function receive_numbers($request) {
        $data = $request->get_json_params();
        
        if (empty($data)) {
            $data = $request->get_params();
        }
        
        // Estrai numeri dal payload
        $numbers = array();
        
        if (isset($data['numbers'])) {
            $numbers = $data['numbers'];
        } elseif (isset($data['phone'])) {
            $numbers[] = array(
                'phone' => $data['phone'],
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? ''
            );
        }
        
        if (empty($numbers)) {
            return new WP_Error('no_numbers', 'Nessun numero fornito', array('status' => 400));
        }
        
        $campaign_id = $data['campaign_id'] ?? $data['campaign'] ?? null;
        $processed = self::process_numbers($numbers, $campaign_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'processed' => $processed['total'],
            'new' => $processed['new'],
            'updated' => $processed['updated'],
            'details' => $processed
        ));
    }
    
    /**
     * Invia messaggio WhatsApp
     */
    public static function send_message($request) {
        $data = $request->get_json_params();
        
        if (empty($data)) {
            $data = $request->get_params();
        }
        
        $phone = $data['phone'] ?? $data['to'] ?? null;
        $message = $data['message'] ?? $data['text'] ?? null;
        
        if (empty($phone) || empty($message)) {
            return new WP_Error(
                'missing_params',
                'Parametri mancanti: phone e message sono richiesti',
                array('status' => 400)
            );
        }
        
        // Invia messaggio
        $mail2wa = new WSP_Mail2Wa();
        $result = $mail2wa->send_message($phone, $message);
        
        if ($result['success']) {
            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Messaggio inviato',
                'credits_remaining' => $result['credits_remaining'] ?? null
            ));
        } else {
            return new WP_Error(
                'send_failed',
                $result['message'],
                array('status' => 500)
            );
        }
    }
    
    /**
     * Ottieni statistiche
     */
    public static function get_stats($request) {
        $period = $request->get_param('period') ?? 'today';
        
        $stats = WSP_Database::get_statistics($period);
        
        return rest_ensure_response(array(
            'success' => true,
            'period' => $period,
            'stats' => $stats,
            'credits' => WSP_Credits::get_balance()
        ));
    }
    
    /**
     * Test endpoint
     */
    public static function test_endpoint($request) {
        $method = $request->get_method();
        $params = $request->get_params();
        $headers = $request->get_headers();
        
        // Test di base
        $tests = array(
            'method' => $method,
            'database' => self::test_database(),
            'mail2wa' => self::test_mail2wa(),
            'credits' => WSP_Credits::get_balance()
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'API Test Endpoint',
            'version' => WSP_VERSION,
            'tests' => $tests,
            'request' => array(
                'method' => $method,
                'params' => $params,
                'headers' => array_keys($headers)
            )
        ));
    }
    
    /**
     * Processa array di numeri
     */
    private static function process_numbers($numbers, $campaign_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
        $results = array(
            'total' => 0,
            'new' => 0,
            'updated' => 0,
            'errors' => array()
        );
        
        // Verifica che la tabella esista
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$table_exists) {
            WSP_Database::create_tables();
        }
        
        // Verifica che la colonna campaign_id esista
        $has_campaign_id = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'campaign_id'");
        
        foreach ($numbers as $number_data) {
            try {
                if (is_string($number_data)) {
                    $number_data = array('phone' => $number_data);
                }
                
                $phone = $number_data['phone'] ?? null;
                
                if (empty($phone)) {
                    $results['errors'][] = 'Numero vuoto saltato';
                    continue;
                }
                
                // Normalizza numero
                $mail2wa = new WSP_Mail2Wa();
                $phone = $mail2wa->normalize_phone($phone);
                
                // Controlla se esiste
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table WHERE sender_number = %s OR sender_formatted = %s",
                    str_replace('+', '', $phone),
                    $phone
                ));
                
                $data = array(
                    'sender_number' => str_replace('+', '', $phone),
                    'sender_formatted' => $phone,
                    'sender_name' => $number_data['name'] ?? '',
                    'sender_email' => $number_data['email'] ?? '',
                    'source' => 'api',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );
                
                // Aggiungi campaign_id solo se la colonna esiste
                if ($has_campaign_id) {
                    $data['campaign_id'] = $campaign_id ?: '';
                }
                
                if ($existing) {
                    // Aggiorna
                    unset($data['created_at']); // Non aggiornare created_at
                    $wpdb->update($table, $data, array('id' => $existing->id));
                    $results['updated']++;
                } else {
                    // Inserisci nuovo
                    $wpdb->insert($table, $data);
                    $results['new']++;
                    
                    // Invia messaggio di benvenuto se configurato
                    try {
                        if (get_option('wsp_send_welcome_on_new', false)) {
                            $mail2wa->send_welcome_message($phone, $data['sender_name']);
                        }
                    } catch (Exception $e) {
                        // Log errore ma continua
                        error_log('WSP: Errore invio messaggio benvenuto: ' . $e->getMessage());
                    }
                }
                
                $results['total']++;
                
            } catch (Exception $e) {
                $results['errors'][] = 'Errore per numero ' . ($phone ?? 'unknown') . ': ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Processa singolo numero
     */
    private static function process_single_number($data) {
        $numbers = array($data);
        return self::process_numbers($numbers, $data['campaign'] ?? null);
    }
    
    /**
     * Test database
     */
    private static function test_database() {
        global $wpdb;
        
        $tables = array(
            'wsp_whatsapp_numbers',
            'wsp_messages',
            'wsp_campaigns'
        );
        
        $all_ok = true;
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $all_ok = false;
                break;
            }
        }
        
        return $all_ok ? 'OK' : 'ERROR';
    }
    
    /**
     * Test Mail2Wa
     */
    private static function test_mail2wa() {
        $mail2wa = new WSP_Mail2Wa();
        $result = $mail2wa->test_configuration();
        
        return $result['success'] ? 'OK' : 'ERROR';
    }
    
    /**
     * Handle email extraction webhook
     */
    public static function handle_email_extraction($request) {
        $data = $request->get_json_params();
        
        if (empty($data)) {
            return new WP_Error('no_data', 'Nessun dato ricevuto', array('status' => 400));
        }
        
        // Estrai dati dall'email
        $subject = $data['subject'] ?? '';
        $body = $data['body'] ?? '';
        $from = $data['from'] ?? '';
        
        // Usa la classe Gmail per estrarre i dati
        $gmail = new WSP_Gmail();
        $extracted = $gmail->extract_whatsapp_data($subject . ' ' . $body);
        
        if ($extracted) {
            // Salva nel database
            global $wpdb;
            $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
            
            $number_data = array(
                'sender_number' => $extracted['number'],
                'sender_name' => $extracted['name'] ?? '',
                'sender_formatted' => '+' . $extracted['number'],
                'sender_email' => $from,
                'email_subject' => $subject,
                'source' => 'email_webhook',
                'campaign_id' => 'email_' . date('Ymd')
            );
            
            // Controlla se esiste
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $table WHERE sender_number = %s",
                $extracted['number']
            ));
            
            if ($existing) {
                $wpdb->update($table, $number_data, array('id' => $existing->id));
                $action = 'updated';
            } else {
                $wpdb->insert($table, $number_data);
                $action = 'created';
                
                // Invia messaggio di benvenuto
                if (get_option('wsp_send_welcome_on_new', true)) {
                    $mail2wa = new WSP_Mail2Wa();
                    $mail2wa->send_welcome_message(
                        $number_data['sender_formatted'],
                        $number_data['sender_name']
                    );
                }
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'action' => $action,
                'extracted' => $extracted
            ));
        }
        
        return new WP_Error(
            'extraction_failed',
            'Impossibile estrarre dati dall\'email',
            array('status' => 400)
        );
    }
}
--------------------------------------------------------------------------------