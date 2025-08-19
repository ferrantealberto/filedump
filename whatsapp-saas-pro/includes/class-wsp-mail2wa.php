<?php
/**
 * Classe per la gestione dell'integrazione Mail2Wa
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 3.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Mail2Wa {
    
    private $api_key;
    private $base_url;
    private $endpoint_path;
    private $method;
    private $content_type;
    private $auth_method;
    private $timeout;
    
    /**
     * Costruttore
     */
    public function __construct() {
        $this->load_settings();
    }
    
    /**
     * Carica le impostazioni
     */
    private function load_settings() {
        $this->api_key = get_option('wsp_mail2wa_api_key', WSP_MAIL2WA_DEFAULT_KEY);
        $this->base_url = get_option('wsp_mail2wa_base_url', WSP_MAIL2WA_DEFAULT_API);
        $this->endpoint_path = get_option('wsp_mail2wa_endpoint_path', '/');
        $this->method = get_option('wsp_mail2wa_method', 'POST');
        $this->content_type = get_option('wsp_mail2wa_content_type', 'json');
        $this->auth_method = get_option('wsp_mail2wa_auth_method', 'query');
        $this->timeout = get_option('wsp_mail2wa_timeout', 30);
    }
    
    /**
     * Invia messaggio WhatsApp
     */
    public function send_message($phone, $message, $media_url = null) {
        // Normalizza numero
        $phone = $this->normalize_phone($phone);
        
        // Verifica crediti
        $credits = WSP_Credits::get_balance();
        if ($credits <= 0) {
            return array(
                'success' => false,
                'message' => 'Crediti insufficienti',
                'credits_remaining' => 0
            );
        }
        
        // Prova prima con API
        $result = $this->send_via_api($phone, $message, $media_url);
        
        // Se fallisce e il fallback email è abilitato
        if (!$result['success'] && get_option('wsp_mail2wa_email_fallback', true)) {
            $result = $this->send_via_email($phone, $message);
        }
        
        // Decrementa crediti se successo
        if ($result['success']) {
            WSP_Credits::deduct(1);
            $result['credits_remaining'] = WSP_Credits::get_balance();
        }
        
        // Log attività
        WSP_Database::log_activity(
            $result['success'] ? 'message_sent' : 'message_failed',
            'Invio messaggio a ' . $phone,
            $result
        );
        
        return $result;
    }
    
    /**
     * Invia via API
     */
    private function send_via_api($phone, $message, $media_url = null) {
        // Costruisci URL endpoint
        $url = rtrim($this->base_url, '/');
        if (!empty($this->endpoint_path) && $this->endpoint_path !== '/') {
            $url .= '/' . ltrim($this->endpoint_path, '/');
        }
        
        // Prepara parametri
        $params = $this->prepare_params($phone, $message, $media_url);
        
        // Prepara headers
        $headers = $this->prepare_headers();
        
        // Gestisci in base al metodo HTTP
        if ($this->method === 'GET') {
            $url = $this->build_get_url($url, $params);
            $body = null;
        } else {
            $body = $this->prepare_body($params);
        }
        
        // Esegui richiesta
        $args = array(
            'method' => $this->method,
            'headers' => $headers,
            'timeout' => $this->timeout,
            'sslverify' => false
        );
        
        if ($body !== null) {
            $args['body'] = $body;
        }
        
        $response = wp_remote_request($url, $args);
        
        // Gestisci risposta
        return $this->handle_response($response);
    }
    
    /**
     * Prepara i parametri per la richiesta
     */
    private function prepare_params($phone, $message, $media_url = null) {
        $phone_param = get_option('wsp_mail2wa_phone_param', 'to');
        $message_param = get_option('wsp_mail2wa_message_param', 'message');
        $api_key_param = get_option('wsp_mail2wa_api_key_param', 'apiKey');
        
        $params = array(
            $phone_param => $phone,
            $message_param => $message
        );
        
        // Aggiungi action=send per compatibilità Mail2Wa standard
        $params['action'] = 'send';
        
        // Aggiungi media se presente
        if ($media_url) {
            $params['media'] = $media_url;
        }
        
        // Aggiungi API key se nel body o query
        if ($this->auth_method !== 'header') {
            $params[$api_key_param] = $this->api_key;
        }
        
        // Aggiungi parametri extra
        $extra_params = get_option('wsp_mail2wa_extra_params', '');
        if (!empty($extra_params)) {
            $extra = json_decode($extra_params, true);
            if (is_array($extra)) {
                $params = array_merge($params, $extra);
            }
        }
        
        return $params;
    }
    
    /**
     * Prepara gli headers
     */
    private function prepare_headers() {
        $headers = array(
            'User-Agent' => 'WordPress WhatsApp SaaS Plugin/' . WSP_VERSION
        );
        
        // Content-Type
        if ($this->content_type === 'json') {
            $headers['Content-Type'] = 'application/json';
        } else {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        
        // Autenticazione nell'header
        if ($this->auth_method === 'header') {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
        }
        
        return $headers;
    }
    
    /**
     * Costruisce URL per GET
     */
    private function build_get_url($base_url, $params) {
        $query = http_build_query($params);
        $separator = strpos($base_url, '?') !== false ? '&' : '?';
        return $base_url . $separator . $query;
    }
    
    /**
     * Prepara il body della richiesta
     */
    private function prepare_body($params) {
        if ($this->content_type === 'json') {
            return json_encode($params);
        } else {
            return http_build_query($params);
        }
    }
    
    /**
     * Gestisce la risposta API
     */
    private function handle_response($response) {
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Errore connessione: ' . $response->get_error_message(),
                'error_code' => $response->get_error_code()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Prova a decodificare JSON
        $json_response = json_decode($body, true);
        
        // Considera successo codici 200-299
        if ($status_code >= 200 && $status_code < 300) {
            $message = 'Messaggio inviato con successo';
            
            // Estrai messaggio dalla risposta se disponibile
            if (is_array($json_response)) {
                if (isset($json_response['message'])) {
                    $message = $json_response['message'];
                } elseif (isset($json_response['status'])) {
                    $message = $json_response['status'];
                }
            }
            
            return array(
                'success' => true,
                'message' => $message,
                'status_code' => $status_code,
                'response' => $json_response ?: $body
            );
        } else {
            $error_message = 'Errore API: HTTP ' . $status_code;
            
            // Estrai messaggio di errore dalla risposta
            if (is_array($json_response)) {
                if (isset($json_response['error'])) {
                    $error_message = $json_response['error'];
                } elseif (isset($json_response['message'])) {
                    $error_message = $json_response['message'];
                }
            }
            
            return array(
                'success' => false,
                'message' => $error_message,
                'status_code' => $status_code,
                'response' => $json_response ?: $body
            );
        }
    }
    
    /**
     * Invia via email (fallback)
     */
    private function send_via_email($phone, $message) {
        $clean_number = str_replace('+', '', $phone);
        $to_email = $clean_number . '@mail2wa.it';
        
        $subject = 'WhatsApp Message';
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
        );
        
        // Aggiungi API key al messaggio se configurata
        if (!empty($this->api_key)) {
            $message .= "\n\n[API:" . $this->api_key . "]";
        }
        
        $sent = wp_mail($to_email, $subject, $message, $headers);
        
        if ($sent) {
            return array(
                'success' => true,
                'message' => 'Messaggio inviato via email fallback',
                'method' => 'email',
                'to' => $to_email
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Fallimento invio email',
                'method' => 'email'
            );
        }
    }
    
    /**
     * Normalizza numero di telefono
     */
    public function normalize_phone($phone) {
        // Rimuovi tutti i caratteri non numerici tranne il +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Se non inizia con +, aggiungi + se sembra un numero internazionale
        if (strpos($phone, '+') !== 0) {
            // Se inizia con 39 e ha 12 cifre, è italiano
            if (preg_match('/^39\d{10}$/', $phone)) {
                $phone = '+' . $phone;
            }
            // Se ha 10 cifre e inizia con 3, è italiano mobile
            elseif (preg_match('/^3\d{9}$/', $phone)) {
                $phone = '+39' . $phone;
            }
            // Altrimenti aggiungi + se ha almeno 10 cifre
            elseif (strlen($phone) >= 10) {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Testa la configurazione corrente
     */
    public function test_configuration() {
        // Test con numero fittizio
        $test_phone = '+391234567890';
        $test_message = 'Test configurazione API';
        
        // Costruisci URL di test
        $url = rtrim($this->base_url, '/');
        if (!empty($this->endpoint_path) && $this->endpoint_path !== '/') {
            $url .= '/' . ltrim($this->endpoint_path, '/');
        }
        
        // Aggiungi action=test per non consumare crediti
        $params = array(
            'action' => 'test',
            'apiKey' => $this->api_key
        );
        
        if ($this->method === 'GET') {
            $url = $this->build_get_url($url, $params);
            $args = array(
                'method' => 'GET',
                'timeout' => 10,
                'sslverify' => false
            );
        } else {
            $args = array(
                'method' => 'POST',
                'headers' => $this->prepare_headers(),
                'body' => $this->prepare_body($params),
                'timeout' => 10,
                'sslverify' => false
            );
        }
        
        $response = wp_remote_request($url, $args);
        
        return $this->handle_response($response);
    }
    
    /**
     * Trova automaticamente l'endpoint corretto
     */
    public function find_endpoint() {
        $possible_endpoints = array(
            '/',
            '/send',
            '/api/send',
            '/messages',
            '/api/messages',
            '/whatsapp/send',
            '/api/whatsapp/send'
        );
        
        $working_endpoint = null;
        
        foreach ($possible_endpoints as $endpoint) {
            update_option('wsp_mail2wa_endpoint_path', $endpoint);
            $this->endpoint_path = $endpoint;
            
            $result = $this->test_configuration();
            
            if ($result['success'] || (isset($result['status_code']) && $result['status_code'] == 401)) {
                // 401 significa che l'endpoint esiste ma l'API key non è valida
                $working_endpoint = $endpoint;
                break;
            }
        }
        
        if ($working_endpoint) {
            return array(
                'success' => true,
                'endpoint' => $working_endpoint,
                'message' => 'Endpoint trovato: ' . $working_endpoint
            );
        } else {
            // Ripristina endpoint di default
            update_option('wsp_mail2wa_endpoint_path', '/');
            return array(
                'success' => false,
                'message' => 'Nessun endpoint valido trovato'
            );
        }
    }
    
    /**
     * Invia messaggio di benvenuto
     */
    public function send_welcome_message($phone, $name = '') {
        $template = get_option('wsp_welcome_message', '🎉 Benvenuto! Il tuo numero {{numero}} è stato registrato con successo.');
        
        // Sostituisci placeholder
        $message = str_replace('{{numero}}', $phone, $template);
        $message = str_replace('{{nome}}', $name ?: 'Cliente', $message);
        $message = str_replace('{numero}', $phone, $message);
        $message = str_replace('{nome}', $name ?: 'Cliente', $message);
        
        return $this->send_message($phone, $message);
    }
    
    /**
     * Invia messaggio bulk
     */
    public function send_bulk_messages($recipients, $message, $campaign_id = null) {
        $results = array(
            'sent' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
            $name = is_array($recipient) ? ($recipient['name'] ?? '') : '';
            
            // Personalizza messaggio
            $personalized = str_replace('{{nome}}', $name, $message);
            $personalized = str_replace('{{numero}}', $phone, $personalized);
            
            $result = $this->send_message($phone, $personalized);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $phone . ': ' . $result['message'];
            }
            
            // Pausa tra invii per evitare rate limiting
            usleep(500000); // 0.5 secondi
        }
        
        return $results;
    }
}
--------------------------------------------------------------------------------