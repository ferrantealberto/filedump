<?php
/**
 * Gestione Integrazioni Esterne
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WSP_Integrations {
    
    /**
     * Processa registrazione da form esterno
     */
    public static function process_external_registration() {
        // Verifica nonce per sicurezza
        if (!isset($_POST['wsp_registration_nonce']) || 
            !wp_verify_nonce($_POST['wsp_registration_nonce'], 'wsp_external_registration')) {
            wp_die('Richiesta non valida');
        }
        
        // Raccogli dati dal form
        $registration_data = array(
            'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
            'contact_name' => sanitize_text_field($_POST['contact_name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'whatsapp_number' => sanitize_text_field($_POST['whatsapp_number'] ?? $_POST['phone'] ?? ''),
            'vat_number' => sanitize_text_field($_POST['vat_number'] ?? ''),
            'address' => sanitize_textarea_field($_POST['address'] ?? ''),
            'city' => sanitize_text_field($_POST['city'] ?? ''),
            'postal_code' => sanitize_text_field($_POST['postal_code'] ?? ''),
            'country' => sanitize_text_field($_POST['country'] ?? 'Italia'),
            'plan_id' => intval($_POST['plan_id'] ?? 1),
            'source' => 'external_form',
            'referrer' => sanitize_text_field($_POST['referrer'] ?? ''),
            'campaign' => sanitize_text_field($_POST['campaign'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        // Valida dati obbligatori
        $required_fields = array('business_name', 'contact_name', 'email');
        foreach ($required_fields as $field) {
            if (empty($registration_data[$field])) {
                return array(
                    'success' => false,
                    'message' => "Il campo $field è obbligatorio"
                );
            }
        }
        
        // Verifica se email già esistente
        $existing_customer = WSP_Customers::get_customer_by_email($registration_data['email']);
        if ($existing_customer) {
            return array(
                'success' => false,
                'message' => 'Email già registrata nel sistema'
            );
        }
        
        // Registra il cliente
        $result = WSP_Customers::register_customer($registration_data);
        
        if ($result['success']) {
            // Trigger webhook per notifica
            self::trigger_registration_webhook($registration_data, $result);
            
            // Redirect con parametri
            $redirect_url = add_query_arg(array(
                'registration' => 'success',
                'customer_code' => $result['customer_code'],
                'qr_url' => urlencode($result['qr_code_url'])
            ), $_POST['redirect_url'] ?? home_url('/registration-success'));
            
            wp_redirect($redirect_url);
            exit;
        }
        
        return $result;
    }
    
    /**
     * Genera form di registrazione embeddabile
     */
    public static function generate_registration_form($args = array()) {
        $defaults = array(
            'show_plan_selector' => true,
            'default_plan' => 1,
            'show_vat' => true,
            'show_address' => true,
            'redirect_url' => '',
            'campaign' => '',
            'referrer' => '',
            'custom_fields' => array(),
            'button_text' => 'Registrati',
            'terms_url' => '',
            'privacy_url' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        ob_start();
        ?>
        <div class="wsp-registration-form-wrapper">
            <form id="wsp-registration-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="wsp-form">
                <?php wp_nonce_field('wsp_external_registration', 'wsp_registration_nonce'); ?>
                <input type="hidden" name="action" value="wsp_process_registration">
                <input type="hidden" name="redirect_url" value="<?php echo esc_attr($args['redirect_url']); ?>">
                <input type="hidden" name="campaign" value="<?php echo esc_attr($args['campaign']); ?>">
                <input type="hidden" name="referrer" value="<?php echo esc_attr($args['referrer']); ?>">
                
                <div class="wsp-form-section">
                    <h3>Dati Aziendali</h3>
                    
                    <div class="wsp-form-group">
                        <label for="business_name">Ragione Sociale *</label>
                        <input type="text" id="business_name" name="business_name" required>
                    </div>
                    
                    <?php if ($args['show_vat']): ?>
                    <div class="wsp-form-group">
                        <label for="vat_number">Partita IVA</label>
                        <input type="text" id="vat_number" name="vat_number">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($args['show_address']): ?>
                    <div class="wsp-form-group">
                        <label for="address">Indirizzo</label>
                        <input type="text" id="address" name="address">
                    </div>
                    
                    <div class="wsp-form-row">
                        <div class="wsp-form-group">
                            <label for="city">Città</label>
                            <input type="text" id="city" name="city">
                        </div>
                        
                        <div class="wsp-form-group">
                            <label for="postal_code">CAP</label>
                            <input type="text" id="postal_code" name="postal_code">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="wsp-form-section">
                    <h3>Dati di Contatto</h3>
                    
                    <div class="wsp-form-group">
                        <label for="contact_name">Nome e Cognome *</label>
                        <input type="text" id="contact_name" name="contact_name" required>
                    </div>
                    
                    <div class="wsp-form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                        <small>Verrà utilizzata per l'accesso e le comunicazioni</small>
                    </div>
                    
                    <div class="wsp-form-group">
                        <label for="phone">Telefono</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="wsp-form-group">
                        <label for="whatsapp_number">Numero WhatsApp Business *</label>
                        <input type="tel" id="whatsapp_number" name="whatsapp_number" required>
                        <small>Numero che riceverà i report e le notifiche</small>
                    </div>
                </div>
                
                <?php if ($args['show_plan_selector']): ?>
                <div class="wsp-form-section">
                    <h3>Seleziona Piano</h3>
                    
                    <div class="wsp-plans-grid">
                        <?php
                        global $wpdb;
                        $table_plans = $wpdb->prefix . 'wsp_subscription_plans';
                        $plans = $wpdb->get_results("SELECT * FROM $table_plans WHERE is_active = 1 ORDER BY monthly_price ASC");
                        
                        foreach ($plans as $plan):
                            $features = json_decode($plan->features, true);
                        ?>
                        <div class="wsp-plan-card">
                            <input type="radio" id="plan_<?php echo $plan->id; ?>" 
                                   name="plan_id" value="<?php echo $plan->id; ?>"
                                   <?php checked($plan->id, $args['default_plan']); ?>>
                            <label for="plan_<?php echo $plan->id; ?>">
                                <h4><?php echo esc_html($plan->plan_name); ?></h4>
                                <div class="wsp-plan-price">
                                    €<?php echo number_format($plan->monthly_price, 2); ?>/mese
                                </div>
                                <ul class="wsp-plan-features">
                                    <li><?php echo number_format($plan->credits_included); ?> crediti inclusi</li>
                                    <?php if ($plan->max_campaigns > 0): ?>
                                    <li>Max <?php echo $plan->max_campaigns; ?> campagne</li>
                                    <?php else: ?>
                                    <li>Campagne illimitate</li>
                                    <?php endif; ?>
                                    <?php if (!empty($features['api_access'])): ?>
                                    <li>Accesso API</li>
                                    <?php endif; ?>
                                    <?php if (!empty($features['priority_support'])): ?>
                                    <li>Supporto prioritario</li>
                                    <?php endif; ?>
                                </ul>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="plan_id" value="<?php echo $args['default_plan']; ?>">
                <?php endif; ?>
                
                <?php if (!empty($args['custom_fields'])): ?>
                <div class="wsp-form-section">
                    <h3>Informazioni Aggiuntive</h3>
                    <?php foreach ($args['custom_fields'] as $field): ?>
                    <div class="wsp-form-group">
                        <label for="<?php echo $field['name']; ?>">
                            <?php echo $field['label']; ?>
                            <?php if (!empty($field['required'])): ?>*<?php endif; ?>
                        </label>
                        <?php if ($field['type'] === 'textarea'): ?>
                        <textarea id="<?php echo $field['name']; ?>" 
                                  name="custom_<?php echo $field['name']; ?>"
                                  <?php if (!empty($field['required'])): ?>required<?php endif; ?>></textarea>
                        <?php else: ?>
                        <input type="<?php echo $field['type']; ?>" 
                               id="<?php echo $field['name']; ?>" 
                               name="custom_<?php echo $field['name']; ?>"
                               <?php if (!empty($field['required'])): ?>required<?php endif; ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="wsp-form-section">
                    <?php if ($args['terms_url'] || $args['privacy_url']): ?>
                    <div class="wsp-form-group">
                        <label class="wsp-checkbox-label">
                            <input type="checkbox" name="accept_terms" required>
                            Accetto 
                            <?php if ($args['terms_url']): ?>
                            <a href="<?php echo esc_url($args['terms_url']); ?>" target="_blank">i termini e condizioni</a>
                            <?php endif; ?>
                            <?php if ($args['terms_url'] && $args['privacy_url']): ?>e<?php endif; ?>
                            <?php if ($args['privacy_url']): ?>
                            <a href="<?php echo esc_url($args['privacy_url']); ?>" target="_blank">l'informativa privacy</a>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endif; ?>
                    
                    <div class="wsp-form-group">
                        <button type="submit" class="wsp-submit-button">
                            <?php echo esc_html($args['button_text']); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <style>
        .wsp-registration-form-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .wsp-form-section {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .wsp-form-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #25D366;
            padding-bottom: 10px;
        }
        
        .wsp-form-group {
            margin-bottom: 15px;
        }
        
        .wsp-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .wsp-form-group input,
        .wsp-form-group textarea,
        .wsp-form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .wsp-form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        
        .wsp-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .wsp-plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .wsp-plan-card {
            position: relative;
        }
        
        .wsp-plan-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .wsp-plan-card label {
            display: block;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .wsp-plan-card input[type="radio"]:checked + label {
            border-color: #25D366;
            background: #f0fff4;
        }
        
        .wsp-plan-card h4 {
            margin-top: 0;
            color: #333;
        }
        
        .wsp-plan-price {
            font-size: 24px;
            font-weight: bold;
            color: #25D366;
            margin: 10px 0;
        }
        
        .wsp-plan-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .wsp-plan-features li {
            padding: 5px 0;
            color: #666;
        }
        
        .wsp-plan-features li:before {
            content: "✓ ";
            color: #25D366;
            font-weight: bold;
        }
        
        .wsp-checkbox-label {
            display: flex;
            align-items: center;
        }
        
        .wsp-checkbox-label input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .wsp-submit-button {
            background: #25D366;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .wsp-submit-button:hover {
            background: #1da851;
        }
        
        @media (max-width: 600px) {
            .wsp-form-row {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#wsp-registration-form').on('submit', function(e) {
                // Copia numero telefono in WhatsApp se vuoto
                if (!$('#whatsapp_number').val() && $('#phone').val()) {
                    $('#whatsapp_number').val($('#phone').val());
                }
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode per il form di registrazione
     */
    public static function registration_form_shortcode($atts) {
        $args = shortcode_atts(array(
            'show_plans' => 'yes',
            'default_plan' => '1',
            'show_vat' => 'yes',
            'show_address' => 'yes',
            'redirect' => '',
            'campaign' => '',
            'button_text' => 'Registrati Ora',
            'terms_url' => '',
            'privacy_url' => ''
        ), $atts);
        
        return self::generate_registration_form(array(
            'show_plan_selector' => ($args['show_plans'] === 'yes'),
            'default_plan' => intval($args['default_plan']),
            'show_vat' => ($args['show_vat'] === 'yes'),
            'show_address' => ($args['show_address'] === 'yes'),
            'redirect_url' => $args['redirect'],
            'campaign' => $args['campaign'],
            'button_text' => $args['button_text'],
            'terms_url' => $args['terms_url'],
            'privacy_url' => $args['privacy_url']
        ));
    }
    
    /**
     * Trigger webhook per notifica registrazione
     */
    private static function trigger_registration_webhook($data, $result) {
        $webhook_url = get_option('wsp_registration_webhook', '');
        
        if (empty($webhook_url)) {
            return;
        }
        
        $payload = array(
            'event' => 'customer_registered',
            'timestamp' => current_time('mysql'),
            'customer' => array(
                'id' => $result['customer_id'],
                'code' => $result['customer_code'],
                'business_name' => $data['business_name'],
                'email' => $data['email'],
                'api_key' => $result['api_key']
            ),
            'qr_code_url' => $result['qr_code_url'],
            'source' => $data['source'],
            'campaign' => $data['campaign']
        );
        
        wp_remote_post($webhook_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($payload),
            'timeout' => 30
        ));
    }
    
    /**
     * Registra hooks e shortcodes
     */
    public static function init() {
        // Registra action per processare form
        add_action('admin_post_wsp_process_registration', array(__CLASS__, 'process_external_registration'));
        add_action('admin_post_nopriv_wsp_process_registration', array(__CLASS__, 'process_external_registration'));
        
        // Registra shortcode
        add_shortcode('wsp_registration_form', array(__CLASS__, 'registration_form_shortcode'));
    }
}