<?php
/**
 * Admin Customers Management Interface
 * 
 * @package WhatsApp_SaaS_Pro
 * @subpackage Admin
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin customers management class
 */
class WSP_Admin_Customers {
    
    /**
     * Initialize the admin customers interface
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_wsp_toggle_customer_status', array(__CLASS__, 'ajax_toggle_status'));
        add_action('wp_ajax_wsp_regenerate_api_key', array(__CLASS__, 'ajax_regenerate_api_key'));
        add_action('wp_ajax_wsp_get_customer_details', array(__CLASS__, 'ajax_get_customer_details'));
        add_action('wp_ajax_wsp_update_customer_credits', array(__CLASS__, 'ajax_update_credits'));
    }
    
    /**
     * Add menu pages
     */
    public static function add_menu_pages() {
        add_submenu_page(
            'whatsapp-saas',
            __('Clienti', 'whatsapp-saas-pro'),
            __('Clienti', 'whatsapp-saas-pro'),
            'manage_options',
            'wsp-customers',
            array(__CLASS__, 'render_customers_page')
        );
        
        add_submenu_page(
            'whatsapp-saas',
            __('Nuovo Cliente', 'whatsapp-saas-pro'),
            __('Nuovo Cliente', 'whatsapp-saas-pro'),
            'manage_options',
            'wsp-add-customer',
            array(__CLASS__, 'render_add_customer_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'wsp-customers') === false && strpos($hook, 'wsp-add-customer') === false) {
            return;
        }
        
        wp_enqueue_style('wsp-admin-customers', WSP_PLUGIN_URL . 'assets/css/admin-customers.css', array(), WSP_VERSION);
        wp_enqueue_script('wsp-admin-customers', WSP_PLUGIN_URL . 'assets/js/admin-customers.js', array('jquery'), WSP_VERSION, true);
        
        wp_localize_script('wsp-admin-customers', 'wsp_customers', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsp_customers_nonce'),
            'confirm_regenerate' => __('Sei sicuro di voler rigenerare l\'API key?', 'whatsapp-saas-pro'),
            'confirm_deactivate' => __('Sei sicuro di voler disattivare questo cliente?', 'whatsapp-saas-pro'),
            'confirm_activate' => __('Sei sicuro di voler riattivare questo cliente?', 'whatsapp-saas-pro')
        ));
    }
    
    /**
     * Render customers list page
     */
    public static function render_customers_page() {
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            self::handle_bulk_action($_POST['action'], $_POST['customers'] ?? array());
        }
        
        // Get customers with filters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_plan = isset($_GET['plan']) ? intval($_GET['plan']) : 0;
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $args = array(
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'plan_id' => $filter_plan,
            'status' => $filter_status
        );
        
        $customers = WSP_Customers::get_customers($args);
        $total_customers = WSP_Customers::count_customers($args);
        $total_pages = ceil($total_customers / $per_page);
        
        // Get plans for filter
        $plans = WSP_Customers::get_subscription_plans();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Gestione Clienti', 'whatsapp-saas-pro'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=wsp-add-customer'); ?>" class="page-title-action">
                <?php _e('Aggiungi Nuovo', 'whatsapp-saas-pro'); ?>
            </a>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(self::get_message($_GET['message'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="wsp-customers">
                    
                    <div class="alignleft actions">
                        <select name="plan">
                            <option value="0"><?php _e('Tutti i piani', 'whatsapp-saas-pro'); ?></option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan->id; ?>" <?php selected($filter_plan, $plan->id); ?>>
                                    <?php echo esc_html($plan->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status">
                            <option value=""><?php _e('Tutti gli stati', 'whatsapp-saas-pro'); ?></option>
                            <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Attivi', 'whatsapp-saas-pro'); ?></option>
                            <option value="inactive" <?php selected($filter_status, 'inactive'); ?>><?php _e('Inattivi', 'whatsapp-saas-pro'); ?></option>
                            <option value="suspended" <?php selected($filter_status, 'suspended'); ?>><?php _e('Sospesi', 'whatsapp-saas-pro'); ?></option>
                        </select>
                        
                        <input type="submit" class="button" value="<?php _e('Filtra', 'whatsapp-saas-pro'); ?>">
                    </div>
                    
                    <div class="alignright">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Cerca clienti...', 'whatsapp-saas-pro'); ?>">
                        <input type="submit" class="button" value="<?php _e('Cerca', 'whatsapp-saas-pro'); ?>">
                    </div>
                </form>
            </div>
            
            <!-- Customers Table -->
            <form method="post">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox">
                            </td>
                            <th><?php _e('Codice', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Azienda', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Contatto', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Piano', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Crediti', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Numeri', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Stato', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Registrato', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Azioni', 'whatsapp-saas-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers): ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr data-customer-id="<?php echo $customer->id; ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="customers[]" value="<?php echo $customer->id; ?>">
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($customer->customer_code); ?></strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="#" class="view-details" data-customer-id="<?php echo $customer->id; ?>">
                                                    <?php _e('Dettagli', 'whatsapp-saas-pro'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($customer->business_name); ?></strong>
                                        <br><small><?php echo esc_html($customer->email); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html($customer->contact_name); ?>
                                        <br><small><?php echo esc_html($customer->whatsapp_number); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        $subscription = WSP_Customers::get_active_subscription($customer->id);
                                        if ($subscription) {
                                            echo '<span class="plan-badge plan-' . strtolower($subscription->plan_name) . '">';
                                            echo esc_html($subscription->plan_name);
                                            echo '</span>';
                                        } else {
                                            echo '<span class="plan-badge plan-none">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="credits-display">
                                            <?php echo number_format($customer->available_credits); ?>
                                        </span>
                                        <button type="button" class="button-link add-credits" data-customer-id="<?php echo $customer->id; ?>">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        $numbers_count = WSP_Customers::count_customer_numbers($customer->id);
                                        echo number_format($numbers_count);
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'status-' . $customer->status;
                                        $status_text = ucfirst($customer->status);
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($customer->created_at)); ?>
                                    </td>
                                    <td class="actions-column">
                                        <button type="button" class="button-link edit-customer" data-customer-id="<?php echo $customer->id; ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        
                                        <button type="button" class="button-link regenerate-api-key" 
                                                data-customer-id="<?php echo $customer->id; ?>"
                                                title="<?php _e('Rigenera API Key', 'whatsapp-saas-pro'); ?>">
                                            <span class="dashicons dashicons-admin-network"></span>
                                        </button>
                                        
                                        <button type="button" class="button-link toggle-status" 
                                                data-customer-id="<?php echo $customer->id; ?>"
                                                data-current-status="<?php echo $customer->status; ?>">
                                            <?php if ($customer->status === 'active'): ?>
                                                <span class="dashicons dashicons-pause" title="<?php _e('Sospendi', 'whatsapp-saas-pro'); ?>"></span>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-controls-play" title="<?php _e('Attiva', 'whatsapp-saas-pro'); ?>"></span>
                                            <?php endif; ?>
                                        </button>
                                        
                                        <button type="button" class="button-link view-qr" 
                                                data-customer-email="<?php echo esc_attr($customer->email); ?>"
                                                title="<?php _e('Visualizza QR Code', 'whatsapp-saas-pro'); ?>">
                                            <span class="dashicons dashicons-smartphone"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-items">
                                    <?php _e('Nessun cliente trovato.', 'whatsapp-saas-pro'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Bulk Actions -->
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <select name="action">
                            <option value="-1"><?php _e('Azioni di massa', 'whatsapp-saas-pro'); ?></option>
                            <option value="activate"><?php _e('Attiva', 'whatsapp-saas-pro'); ?></option>
                            <option value="deactivate"><?php _e('Disattiva', 'whatsapp-saas-pro'); ?></option>
                            <option value="suspend"><?php _e('Sospendi', 'whatsapp-saas-pro'); ?></option>
                            <option value="export"><?php _e('Esporta', 'whatsapp-saas-pro'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Applica', 'whatsapp-saas-pro'); ?>">
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(__('%d elementi', 'whatsapp-saas-pro'), $total_customers); ?>
                            </span>
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page
                            ));
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Customer Details Modal -->
        <div id="customer-details-modal" class="wsp-modal" style="display:none;">
            <div class="wsp-modal-content">
                <span class="wsp-modal-close">&times;</span>
                <h2><?php _e('Dettagli Cliente', 'whatsapp-saas-pro'); ?></h2>
                <div id="customer-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Add Credits Modal -->
        <div id="add-credits-modal" class="wsp-modal" style="display:none;">
            <div class="wsp-modal-content">
                <span class="wsp-modal-close">&times;</span>
                <h2><?php _e('Aggiungi Crediti', 'whatsapp-saas-pro'); ?></h2>
                <form id="add-credits-form">
                    <input type="hidden" id="credits-customer-id" name="customer_id">
                    <p>
                        <label><?php _e('Numero Crediti:', 'whatsapp-saas-pro'); ?></label>
                        <input type="number" name="credits" min="1" required>
                    </p>
                    <p>
                        <label><?php _e('Motivo:', 'whatsapp-saas-pro'); ?></label>
                        <textarea name="reason" rows="3"></textarea>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Aggiungi Crediti', 'whatsapp-saas-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add customer page
     */
    public static function render_add_customer_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            $result = self::handle_add_customer($_POST);
            if ($result['success']) {
                wp_redirect(admin_url('admin.php?page=wsp-customers&message=customer_added'));
                exit;
            } else {
                $error = $result['message'];
            }
        }
        
        $plans = WSP_Customers::get_subscription_plans();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Nuovo Cliente', 'whatsapp-saas-pro'); ?></h1>
            
            <?php if (isset($error)): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($error); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" class="wsp-customer-form">
                <?php wp_nonce_field('add_customer', 'wsp_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="business_name"><?php _e('Nome Azienda', 'whatsapp-saas-pro'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" name="business_name" id="business_name" class="regular-text" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="contact_name"><?php _e('Nome Contatto', 'whatsapp-saas-pro'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" name="contact_name" id="contact_name" class="regular-text" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="email"><?php _e('Email', 'whatsapp-saas-pro'); ?> *</label>
                            </th>
                            <td>
                                <input type="email" name="email" id="email" class="regular-text" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="whatsapp_number"><?php _e('Numero WhatsApp', 'whatsapp-saas-pro'); ?> *</label>
                            </th>
                            <td>
                                <input type="tel" name="whatsapp_number" id="whatsapp_number" class="regular-text" 
                                       placeholder="+39XXXXXXXXXX" required>
                                <p class="description">
                                    <?php _e('Formato internazionale con prefisso (es: +391234567890)', 'whatsapp-saas-pro'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="plan_id"><?php _e('Piano Abbonamento', 'whatsapp-saas-pro'); ?> *</label>
                            </th>
                            <td>
                                <select name="plan_id" id="plan_id" required>
                                    <option value=""><?php _e('Seleziona un piano', 'whatsapp-saas-pro'); ?></option>
                                    <?php foreach ($plans as $plan): ?>
                                        <option value="<?php echo $plan->id; ?>">
                                            <?php echo esc_html($plan->name); ?> - 
                                            €<?php echo number_format($plan->price, 2); ?>/mese - 
                                            <?php echo number_format($plan->credits_included); ?> crediti
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="initial_credits"><?php _e('Crediti Iniziali Extra', 'whatsapp-saas-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="initial_credits" id="initial_credits" min="0" value="0">
                                <p class="description">
                                    <?php _e('Crediti aggiuntivi oltre a quelli inclusi nel piano', 'whatsapp-saas-pro'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="vat_number"><?php _e('Partita IVA', 'whatsapp-saas-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="vat_number" id="vat_number" class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="address"><?php _e('Indirizzo', 'whatsapp-saas-pro'); ?></label>
                            </th>
                            <td>
                                <textarea name="address" id="address" rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="notes"><?php _e('Note', 'whatsapp-saas-pro'); ?></label>
                            </th>
                            <td>
                                <textarea name="notes" id="notes" rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e('Opzioni', 'whatsapp-saas-pro'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_welcome_email" value="1" checked>
                                    <?php _e('Invia email di benvenuto con credenziali', 'whatsapp-saas-pro'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="activate_immediately" value="1" checked>
                                    <?php _e('Attiva immediatamente', 'whatsapp-saas-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" 
                           value="<?php _e('Crea Cliente', 'whatsapp-saas-pro'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=wsp-customers'); ?>" class="button">
                        <?php _e('Annulla', 'whatsapp-saas-pro'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle add customer form submission
     */
    private static function handle_add_customer($data) {
        // Verify nonce
        if (!wp_verify_nonce($data['wsp_nonce'], 'add_customer')) {
            return array('success' => false, 'message' => __('Errore di sicurezza', 'whatsapp-saas-pro'));
        }
        
        // Prepare customer data
        $customer_data = array(
            'business_name' => sanitize_text_field($data['business_name']),
            'contact_name' => sanitize_text_field($data['contact_name']),
            'email' => sanitize_email($data['email']),
            'whatsapp_number' => sanitize_text_field($data['whatsapp_number']),
            'plan_id' => intval($data['plan_id']),
            'vat_number' => sanitize_text_field($data['vat_number'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'initial_credits' => intval($data['initial_credits'] ?? 0),
            'send_welcome_email' => isset($data['send_welcome_email']),
            'activate_immediately' => isset($data['activate_immediately'])
        );
        
        // Register customer
        $result = WSP_Customers::register_customer($customer_data);
        
        if ($result['success']) {
            // Add initial credits if specified
            if ($customer_data['initial_credits'] > 0) {
                WSP_Customers::add_credits(
                    $result['customer_id'],
                    $customer_data['initial_credits'],
                    'Crediti iniziali extra aggiunti dall\'amministratore'
                );
            }
            
            // Log activity
            WSP_Activity_Log::log(
                'customer_created',
                'Cliente creato manualmente dall\'amministratore',
                $result['customer_id'],
                array('admin_user' => wp_get_current_user()->user_login)
            );
        }
        
        return $result;
    }
    
    /**
     * Handle bulk actions
     */
    private static function handle_bulk_action($action, $customer_ids) {
        if (empty($customer_ids)) {
            return;
        }
        
        foreach ($customer_ids as $customer_id) {
            switch ($action) {
                case 'activate':
                    WSP_Customers::update_customer_status($customer_id, 'active');
                    break;
                    
                case 'deactivate':
                    WSP_Customers::update_customer_status($customer_id, 'inactive');
                    break;
                    
                case 'suspend':
                    WSP_Customers::update_customer_status($customer_id, 'suspended');
                    break;
                    
                case 'export':
                    // TODO: Implement export functionality
                    break;
            }
        }
    }
    
    /**
     * AJAX: Toggle customer status
     */
    public static function ajax_toggle_status() {
        check_ajax_referer('wsp_customers_nonce', 'nonce');
        
        $customer_id = intval($_POST['customer_id']);
        $current_status = sanitize_text_field($_POST['current_status']);
        
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        
        if (WSP_Customers::update_customer_status($customer_id, $new_status)) {
            wp_send_json_success(array('new_status' => $new_status));
        } else {
            wp_send_json_error(__('Errore nell\'aggiornamento dello stato', 'whatsapp-saas-pro'));
        }
    }
    
    /**
     * AJAX: Regenerate API key
     */
    public static function ajax_regenerate_api_key() {
        check_ajax_referer('wsp_customers_nonce', 'nonce');
        
        $customer_id = intval($_POST['customer_id']);
        $new_api_key = WSP_Customers::regenerate_api_key($customer_id);
        
        if ($new_api_key) {
            wp_send_json_success(array('api_key' => $new_api_key));
        } else {
            wp_send_json_error(__('Errore nella rigenerazione dell\'API key', 'whatsapp-saas-pro'));
        }
    }
    
    /**
     * AJAX: Get customer details
     */
    public static function ajax_get_customer_details() {
        check_ajax_referer('wsp_customers_nonce', 'nonce');
        
        $customer_id = intval($_POST['customer_id']);
        $customer = WSP_Customers::get_customer($customer_id);
        
        if (!$customer) {
            wp_send_json_error(__('Cliente non trovato', 'whatsapp-saas-pro'));
        }
        
        // Get additional data
        $subscription = WSP_Customers::get_active_subscription($customer_id);
        $numbers_count = WSP_Customers::count_customer_numbers($customer_id);
        $recent_activity = WSP_Activity_Log::get_recent_activity($customer_id, 5);
        
        ob_start();
        ?>
        <div class="customer-details">
            <div class="detail-section">
                <h3><?php _e('Informazioni Base', 'whatsapp-saas-pro'); ?></h3>
                <table class="detail-table">
                    <tr>
                        <th><?php _e('Codice Cliente:', 'whatsapp-saas-pro'); ?></th>
                        <td><?php echo esc_html($customer->customer_code); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('API Key:', 'whatsapp-saas-pro'); ?></th>
                        <td>
                            <code class="api-key-display"><?php echo esc_html($customer->api_key); ?></code>
                            <button type="button" class="button-link copy-api-key">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('QR Code URL:', 'whatsapp-saas-pro'); ?></th>
                        <td>
                            <a href="<?php echo esc_url(WSP_QR_SERVICE_URL . '?email=' . urlencode($customer->email)); ?>" 
                               target="_blank">
                                <?php _e('Visualizza QR Code', 'whatsapp-saas-pro'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php if ($subscription): ?>
                <div class="detail-section">
                    <h3><?php _e('Abbonamento', 'whatsapp-saas-pro'); ?></h3>
                    <table class="detail-table">
                        <tr>
                            <th><?php _e('Piano:', 'whatsapp-saas-pro'); ?></th>
                            <td><?php echo esc_html($subscription->plan_name); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Inizio:', 'whatsapp-saas-pro'); ?></th>
                            <td><?php echo date('d/m/Y', strtotime($subscription->start_date)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Prossimo Rinnovo:', 'whatsapp-saas-pro'); ?></th>
                            <td><?php echo date('d/m/Y', strtotime($subscription->next_billing_date)); ?></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="detail-section">
                <h3><?php _e('Statistiche', 'whatsapp-saas-pro'); ?></h3>
                <table class="detail-table">
                    <tr>
                        <th><?php _e('Crediti Disponibili:', 'whatsapp-saas-pro'); ?></th>
                        <td><?php echo number_format($customer->available_credits); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Numeri Raccolti:', 'whatsapp-saas-pro'); ?></th>
                        <td><?php echo number_format($numbers_count); ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if ($recent_activity): ?>
                <div class="detail-section">
                    <h3><?php _e('Attività Recente', 'whatsapp-saas-pro'); ?></h3>
                    <ul class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                            <li>
                                <span class="activity-date">
                                    <?php echo date('d/m/Y H:i', strtotime($activity->created_at)); ?>
                                </span>
                                <span class="activity-type"><?php echo esc_html($activity->activity_type); ?></span>
                                <span class="activity-description"><?php echo esc_html($activity->description); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Update customer credits
     */
    public static function ajax_update_credits() {
        check_ajax_referer('wsp_customers_nonce', 'nonce');
        
        $customer_id = intval($_POST['customer_id']);
        $credits = intval($_POST['credits']);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        if (WSP_Customers::add_credits($customer_id, $credits, $reason)) {
            $customer = WSP_Customers::get_customer($customer_id);
            wp_send_json_success(array('new_balance' => $customer->available_credits));
        } else {
            wp_send_json_error(__('Errore nell\'aggiornamento dei crediti', 'whatsapp-saas-pro'));
        }
    }
    
    /**
     * Get message text
     */
    private static function get_message($code) {
        $messages = array(
            'customer_added' => __('Cliente aggiunto con successo', 'whatsapp-saas-pro'),
            'customer_updated' => __('Cliente aggiornato con successo', 'whatsapp-saas-pro'),
            'customer_deleted' => __('Cliente eliminato con successo', 'whatsapp-saas-pro'),
            'bulk_action_completed' => __('Azione completata con successo', 'whatsapp-saas-pro')
        );
        
        return $messages[$code] ?? __('Operazione completata', 'whatsapp-saas-pro');
    }
}

// Initialize
WSP_Admin_Customers::init();