<?php
/**
 * Gestione Impostazioni WhatsApp SaaS Plugin
 * 
 * @package WhatsApp_SaaS_Pro
 * @version 3.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function register_settings() {
        // Gruppo principale
        register_setting('wsp_settings_group', 'wsp_api_key');
        register_setting('wsp_settings_group', 'wsp_credits_balance');
        register_setting('wsp_settings_group', 'wsp_welcome_message');
        
        // Mail2Wa settings
        register_setting('wsp_settings_group', 'wsp_mail2wa_api_key');
        register_setting('wsp_settings_group', 'wsp_mail2wa_base_url');
        register_setting('wsp_settings_group', 'wsp_mail2wa_endpoint_path');
        register_setting('wsp_settings_group', 'wsp_mail2wa_method');
        register_setting('wsp_settings_group', 'wsp_mail2wa_content_type');
        register_setting('wsp_settings_group', 'wsp_mail2wa_auth_method');
        register_setting('wsp_settings_group', 'wsp_mail2wa_phone_param');
        register_setting('wsp_settings_group', 'wsp_mail2wa_message_param');
        register_setting('wsp_settings_group', 'wsp_mail2wa_api_key_param');
        register_setting('wsp_settings_group', 'wsp_mail2wa_extra_params');
        register_setting('wsp_settings_group', 'wsp_mail2wa_email_fallback');
        register_setting('wsp_settings_group', 'wsp_mail2wa_timeout');
        
        // Report settings
        register_setting('wsp_settings_group', 'wsp_report_email');
        register_setting('wsp_settings_group', 'wsp_report_time');
        register_setting('wsp_settings_group', 'wsp_report_enabled');
        
        // Gmail settings
        register_setting('wsp_settings_group', 'wsp_gmail_email');
        register_setting('wsp_settings_group', 'wsp_gmail_password');
        register_setting('wsp_settings_group', 'wsp_gmail_from_filter');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wsp_settings_group'); ?>
                
                <h2>🔑 Configurazione API</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key WordPress</th>
                        <td>
                            <input type="text" name="wsp_api_key" 
                                   value="<?php echo esc_attr(get_option('wsp_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Chiave API per l'integrazione con n8n</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Crediti Disponibili</th>
                        <td>
                            <input type="number" name="wsp_credits_balance" 
                                   value="<?php echo esc_attr(get_option('wsp_credits_balance', 0)); ?>" 
                                   class="regular-text" readonly />
                        </td>
                    </tr>
                </table>
                
                <h2>📱 Configurazione Mail2Wa</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Mail2Wa API Key</th>
                        <td>
                            <input type="password" name="wsp_mail2wa_api_key" 
                                   value="<?php echo esc_attr(get_option('wsp_mail2wa_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">La tua chiave API Mail2Wa</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Base URL</th>
                        <td>
                            <input type="url" name="wsp_mail2wa_base_url" 
                                   value="<?php echo esc_attr(get_option('wsp_mail2wa_base_url', WSP_MAIL2WA_DEFAULT_API)); ?>" 
                                   class="regular-text" />
                            <p class="description">URL base delle API Mail2Wa</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Endpoint Path</th>
                        <td>
                            <input type="text" name="wsp_mail2wa_endpoint_path" 
                                   value="<?php echo esc_attr(get_option('wsp_mail2wa_endpoint_path', '/')); ?>" 
                                   class="regular-text" />
                            <button type="button" class="button" onclick="wspTestEndpoint()">Test</button>
                            <p class="description">Path dell'endpoint (es: /, /send, /api/send)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Metodo HTTP</th>
                        <td>
                            <select name="wsp_mail2wa_method">
                                <option value="POST" <?php selected(get_option('wsp_mail2wa_method'), 'POST'); ?>>POST</option>
                                <option value="GET" <?php selected(get_option('wsp_mail2wa_method'), 'GET'); ?>>GET</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Content Type</th>
                        <td>
                            <select name="wsp_mail2wa_content_type">
                                <option value="json" <?php selected(get_option('wsp_mail2wa_content_type'), 'json'); ?>>application/json</option>
                                <option value="form" <?php selected(get_option('wsp_mail2wa_content_type'), 'form'); ?>>application/x-www-form-urlencoded</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Metodo Autenticazione</th>
                        <td>
                            <select name="wsp_mail2wa_auth_method">
                                <option value="query" <?php selected(get_option('wsp_mail2wa_auth_method'), 'query'); ?>>Query String</option>
                                <option value="body" <?php selected(get_option('wsp_mail2wa_auth_method'), 'body'); ?>>Nel Body</option>
                                <option value="header" <?php selected(get_option('wsp_mail2wa_auth_method'), 'header'); ?>>Nell'Header</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Parametro Telefono</th>
                        <td>
                            <input type="text" name="wsp_mail2wa_phone_param" 
                                   value="<?php echo esc_attr(get_option('wsp_mail2wa_phone_param', 'to')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Parametro Messaggio</th>
                        <td>
                            <input type="text" name="wsp_mail2wa_message_param" 
                                   value="<?php echo esc_attr(get_option('wsp_mail2wa_message_param', 'message')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Parametro API Key</th>
                        <td>
                            <input type="text" name="wsp_mail2wa_api_key_param" 
                                   value="<?php echo esc_attr(get_option('wsp_mail2wa_api_key_param', 'apiKey')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Parametri Extra (JSON)</th>
                        <td>
                            <textarea name="wsp_mail2wa_extra_params" rows="3" class="large-text code"><?php 
                                echo esc_textarea(get_option('wsp_mail2wa_extra_params', '{"action":"send"}')); 
                            ?></textarea>
                            <p class="description">Parametri aggiuntivi in formato JSON</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fallback Email</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wsp_mail2wa_email_fallback" value="1" 
                                       <?php checked(get_option('wsp_mail2wa_email_fallback', true)); ?> />
                                Usa email come fallback se l'API fallisce
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2>💬 Messaggi</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Messaggio di Benvenuto</th>
                        <td>
                            <textarea name="wsp_welcome_message" rows="5" class="large-text"><?php 
                                echo esc_textarea(get_option('wsp_welcome_message')); 
                            ?></textarea>
                            <p class="description">Usa {{nome}} e {{numero}} come placeholder</p>
                        </td>
                    </tr>
                </table>
                
                <h2>📊 Report</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Email Report</th>
                        <td>
                            <input type="email" name="wsp_report_email" 
                                   value="<?php echo esc_attr(get_option('wsp_report_email', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Orario Report</th>
                        <td>
                            <input type="time" name="wsp_report_time" 
                                   value="<?php echo esc_attr(get_option('wsp_report_time', '18:00')); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Report Automatico</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wsp_report_enabled" value="1" 
                                       <?php checked(get_option('wsp_report_enabled', true)); ?> />
                                Abilita invio automatico report giornaliero
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        function wspTestEndpoint() {
            alert('Test endpoint in corso...');
            // Implementa test endpoint
        }
        </script>
        <?php
    }
}
--------------------------------------------------------------------------------