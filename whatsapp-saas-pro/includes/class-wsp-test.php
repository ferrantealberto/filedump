<?php
/**
 * Classe per la gestione dei test del sistema
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 3.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Test {
    
    /**
     * Test ping API
     */
    public static function ajax_test_api() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Test connessione base all'API Mail2Wa
        $api_key = get_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
        $base_url = get_option('wsp_mail2wa_base_url', WSP_MAIL2WA_DEFAULT_API);
        
        // Costruisci URL per test
        $test_url = rtrim($base_url, '/') . '/?action=test&apiKey=' . $api_key;
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Errore connessione: ' . $response->get_error_message()
            ));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(array(
                'message' => 'API connessa correttamente',
                'status_code' => $status_code,
                'response' => $body
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Errore API: HTTP ' . $status_code,
                'response' => $body
            ));
        }
    }
    
    /**
     * Test estrazione email
     */
    public static function ajax_test_extraction() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Test estrazione da email di esempio
        $test_cases = array(
            array(
                'subject' => 'Test User, 393351234567@g.us',
                'body' => '2025-08-18 15:30:45: Messaggio di test, Test User, 393351234567@g.us',
                'expected_number' => '393351234567',
                'expected_name' => 'Test User'
            ),
            array(
                'subject' => 'Mario Rossi, +39 335 1234567',
                'body' => 'Messaggio ricevuto da Mario Rossi - +39 335 1234567',
                'expected_number' => '393351234567',
                'expected_name' => 'Mario Rossi'
            )
        );
        
        $results = array();
        
        foreach ($test_cases as $test) {
            // Estrai numero dal subject
            $pattern = '/(\+?\d{10,15})|(\d{10,15}@g\.us)/';
            preg_match($pattern, $test['subject'] . ' ' . $test['body'], $matches);
            
            $extracted_number = '';
            if (!empty($matches[0])) {
                $extracted_number = preg_replace('/[^\d]/', '', $matches[0]);
            }
            
            // Estrai nome
            $extracted_name = '';
            if (preg_match('/([A-Za-z\s]+),/', $test['subject'], $name_matches)) {
                $extracted_name = trim($name_matches[1]);
            }
            
            $results[] = array(
                'test' => $test['subject'],
                'extracted_number' => $extracted_number,
                'expected_number' => $test['expected_number'],
                'extracted_name' => $extracted_name,
                'expected_name' => $test['expected_name'],
                'success' => ($extracted_number == $test['expected_number'])
            );
        }
        
        // Salva log test
        WSP_Database::log_activity(
            'test_extraction',
            'Test estrazione completato',
            $results
        );
        
        wp_send_json_success(array(
            'message' => 'Test estrazione completato',
            'results' => $results
        ));
    }
    
    /**
     * Ottieni API Key
     */
    public static function ajax_get_api_key() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $api_key = get_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
        $base_url = get_option('wsp_mail2wa_base_url', WSP_MAIL2WA_DEFAULT_API);
        
        // Se l'URL contiene Mail2Wa.it, usa l'API key corretta
        if (strpos($base_url, 'Mail2Wa.it') !== false || strpos($base_url, 'mail2wa.it') !== false) {
            $api_key = '1f06d5c8bd0cd19f7c99b660b504bb25';
            $base_url = 'https://api.Mail2Wa.it';
        }
        
        // Verifica validità API key
        $test_url = rtrim($base_url, '/') . '/?action=validate&apiKey=' . $api_key;
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        $is_valid = false;
        $error_message = '';
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Considera valida se riceve 200-299 o 401 (che indica che l'API esiste ma la key è errata)
            if ($status_code >= 200 && $status_code < 300) {
                $is_valid = true;
            } else if ($status_code == 401) {
                $error_message = 'API Key non valida o scaduta';
                
                // Prova a decodificare la risposta JSON per maggiori dettagli
                $json_response = json_decode($body, true);
                if (isset($json_response['message'])) {
                    $error_message = $json_response['message'];
                }
            } else {
                $error_message = 'Errore HTTP ' . $status_code;
            }
        } else {
            $error_message = 'Errore connessione: ' . $response->get_error_message();
        }
        
        if ($is_valid) {
            wp_send_json_success(array(
                'api_key' => $api_key,
                'base_url' => $base_url,
                'message' => 'API Key valida e funzionante'
            ));
        } else {
            // Se l'API key non è valida, proviamo con quella di default
            if ($api_key !== WSP_MAIL2WA_DEFAULT_KEY) {
                update_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
                $api_key = WSP_MAIL2WA_DEFAULT_KEY;
                
                wp_send_json_success(array(
                    'api_key' => $api_key,
                    'base_url' => $base_url,
                    'message' => 'API Key ripristinata a quella di default',
                    'warning' => $error_message
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $error_message,
                    'code' => 'rest_forbidden',
                    'data' => array(
                        'status' => 401
                    )
                ));
            }
        }
    }
    
    /**
     * Test database
     */
    public static function ajax_test_database() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        global $wpdb;
        
        $tables = array(
            'wsp_whatsapp_numbers' => 'Numeri WhatsApp',
            'wsp_messages' => 'Messaggi',
            'wsp_campaigns' => 'Campagne',
            'wsp_credits_transactions' => 'Transazioni crediti',
            'wsp_activity_log' => 'Log attività'
        );
        
        $results = array();
        $all_ok = true;
        
        foreach ($tables as $table => $name) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $results[] = array(
                    'table' => $name,
                    'status' => 'OK',
                    'records' => $count
                );
            } else {
                $results[] = array(
                    'table' => $name,
                    'status' => 'MANCANTE',
                    'records' => 0
                );
                $all_ok = false;
            }
        }
        
        // Se mancano tabelle, prova a crearle
        if (!$all_ok) {
            WSP_Database::create_tables();
            
            // Ricontrolla
            $recheck_ok = true;
            foreach ($tables as $table => $name) {
                $table_name = $wpdb->prefix . $table;
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                    $recheck_ok = false;
                    break;
                }
            }
            
            if ($recheck_ok) {
                wp_send_json_success(array(
                    'message' => 'Tabelle create con successo',
                    'tables' => $results,
                    'fixed' => true
                ));
            }
        }
        
        if ($all_ok) {
            wp_send_json_success(array(
                'message' => 'Database OK',
                'tables' => $results
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Problemi con il database',
                'tables' => $results
            ));
        }
    }
    
    /**
     * Test invio Mail2Wa
     */
    public static function ajax_test_mail2wa_send() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? 'Test messaggio da WhatsApp SaaS Plugin');
        
        if (empty($phone)) {
            wp_send_json_error('Numero telefono mancante');
        }
        
        // Usa la classe Mail2Wa per inviare
        $mail2wa = new WSP_Mail2Wa();
        $result = $mail2wa->send_message($phone, $message);
        
        // Log del test
        WSP_Database::log_activity(
            'test_mail2wa',
            'Test invio messaggio',
            array(
                'phone' => $phone,
                'success' => $result['success'],
                'response' => $result
            )
        );
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Test processamento email
     */
    public static function ajax_test_email_processing() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Simula processamento email
        $test_email = array(
            'from' => 'test@upgradeservizi.eu',
            'subject' => 'Test User, 393351234567@g.us',
            'body' => '2025-08-18 15:30:45: Messaggio di test, Test User, 393351234567@g.us',
            'date' => current_time('mysql')
        );
        
        // Estrai dati
        $gmail = new WSP_Gmail();
        $extracted = $gmail->extract_whatsapp_data($test_email['subject'] . ' ' . $test_email['body']);
        
        if ($extracted) {
            // Salva nel database
            global $wpdb;
            $table = $wpdb->prefix . 'wsp_whatsapp_numbers';
            
            $data = array(
                'sender_number' => $extracted['number'],
                'sender_name' => $extracted['name'] ?? '',
                'sender_formatted' => '+' . $extracted['number'],
                'email_subject' => $test_email['subject'],
                'source' => 'test',
                'campaign_id' => 'test_' . time()
            );
            
            $wpdb->insert($table, $data);
            $insert_id = $wpdb->insert_id;
            
            wp_send_json_success(array(
                'message' => 'Email processata con successo',
                'extracted' => $extracted,
                'record_id' => $insert_id
            ));
        } else {
            wp_send_json_error('Impossibile estrarre dati dall\'email');
        }
    }
    
    /**
     * Simula webhook n8n
     */
    public static function ajax_simulate_n8n_webhook() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Dati di test
        $webhook_data = array(
            'numbers' => array(
                array(
                    'phone' => '+393351234567',
                    'name' => 'Test User',
                    'email' => 'test@example.com'
                ),
                array(
                    'phone' => '+393887654321',
                    'name' => 'Mario Rossi',
                    'email' => 'mario@example.com'
                )
            ),
            'campaign' => 'test_campaign_' . time(),
            'source' => 'n8n_test'
        );
        
        // Simula chiamata al webhook
        $api_key = get_option('wsp_api_key');
        $webhook_url = rest_url('wsp/v1/webhook');
        
        $response = wp_remote_post($webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key
            ),
            'body' => json_encode($webhook_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Errore webhook: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(array(
                'message' => 'Webhook simulato con successo',
                'response' => json_decode($body, true)
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Errore webhook: HTTP ' . $status_code,
                'response' => $body
            ));
        }
    }
    
    /**
     * Render form di test
     */
    public static function render_test_form() {
        ?>
        <div class="wsp-test-form">
            <h3>Test Invio WhatsApp</h3>
            <form id="wsp-test-send-form">
                <p>
                    <label>Numero Telefono:</label><br>
                    <input type="tel" id="wsp-test-phone" placeholder="+393351234567" required>
                </p>
                <p>
                    <label>Messaggio:</label><br>
                    <textarea id="wsp-test-message" rows="4" placeholder="Inserisci il messaggio di test">Test messaggio da WhatsApp SaaS Plugin</textarea>
                </p>
                <p>
                    <button type="submit" class="button button-primary">Invia Test</button>
                </p>
                <div id="wsp-test-result"></div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wsp-test-send-form').on('submit', function(e) {
                e.preventDefault();
                
                var phone = $('#wsp-test-phone').val();
                var message = $('#wsp-test-message').val();
                
                $('#wsp-test-result').html('<p>Invio in corso...</p>');
                
                $.post(ajaxurl, {
                    action: 'wsp_test_mail2wa_send',
                    nonce: '<?php echo wp_create_nonce('wsp_nonce'); ?>',
                    phone: phone,
                    message: message
                }, function(response) {
                    if (response.success) {
                        $('#wsp-test-result').html('<p style="color:green;">✅ ' + response.data.message + '</p>');
                    } else {
                        $('#wsp-test-result').html('<p style="color:red;">❌ ' + response.data.message + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
--------------------------------------------------------------------------------