<?php
/**
 * Gestione campagne QR
 */
if (!defined('ABSPATH')) {
    exit;
}
class WSP_Campaigns {
    
    public static function create_campaign($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wsp_campaigns';
        
        $campaign_data = array(
            'campaign_id' => 'campaign_' . time() . '_' . wp_rand(1000, 9999),
            'campaign_name' => $data['name'] ?? 'Nuova Campagna',
            'campaign_type' => $data['type'] ?? 'qr',
            'welcome_message' => $data['message'] ?? get_option('wsp_welcome_message'),
            'is_active' => 1,
            'total_scans' => 0,
            'total_registrations' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Genera URL landing page
        $campaign_data['landing_page_url'] = home_url('/wsp-campaign/' . $campaign_data['campaign_id']);
        
        // Genera QR Code URL
        $campaign_data['qr_code_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . 
                                        urlencode($campaign_data['landing_page_url']);
        
        $wpdb->insert($table, $campaign_data);
        
        return $campaign_data;
    }
    
    public static function ajax_create_campaign() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $campaign = self::create_campaign($_POST);
        
        wp_send_json_success($campaign);
    }
    
    public static function ajax_get_campaigns() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_campaigns';
        
        $campaigns = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        
        wp_send_json_success($campaigns);
    }
    
    public static function ajax_get_campaign_stats() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        $campaign_id = sanitize_text_field($_POST['campaign_id'] ?? '');
        
        if (empty($campaign_id)) {
            wp_send_json_error('Campaign ID mancante');
        }
        
        global $wpdb;
        $table_campaigns = $wpdb->prefix . 'wsp_campaigns';
        $table_numbers = $wpdb->prefix . 'wsp_whatsapp_numbers';
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_campaigns WHERE campaign_id = %s",
            $campaign_id
        ));
        
        if (!$campaign) {
            wp_send_json_error('Campagna non trovata');
        }
        
        $registrations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_numbers WHERE campaign_id = %s",
            $campaign_id
        ));
        
        wp_send_json_success(array(
            'campaign' => $campaign,
            'registrations' => $registrations
        ));
    }
    
    public static function ajax_delete_campaign() {
        check_ajax_referer('wsp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $campaign_id = sanitize_text_field($_POST['campaign_id'] ?? '');
        
        if (empty($campaign_id)) {
            wp_send_json_error('Campaign ID mancante');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_campaigns';
        
        $result = $wpdb->delete($table, array('campaign_id' => $campaign_id));
        
        if ($result) {
            wp_send_json_success('Campagna eliminata');
        } else {
            wp_send_json_error('Errore eliminazione campagna');
        }
    }
    
    public static function render_qr_code($campaign_id, $size = 250, $color = '#000000') {
        global $wpdb;
        $table = $wpdb->prefix . 'wsp_campaigns';
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE campaign_id = %s",
            $campaign_id
        ));
        
        if (!$campaign) {
            echo '<p>Campagna non trovata</p>';
            return;
        }
        
        ?>
        <div class="wsp-qr-container">
            <img src="<?php echo esc_url($campaign->qr_code_url); ?>" 
                 alt="QR Code <?php echo esc_attr($campaign->campaign_name); ?>"
                 width="<?php echo esc_attr($size); ?>"
                 height="<?php echo esc_attr($size); ?>">
            <p><?php echo esc_html($campaign->campaign_name); ?></p>
        </div>
        <?php
    }
}
--------------------------------------------------------------------------------