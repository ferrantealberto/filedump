<?php
/**
 * Admin Reports Management Interface
 * 
 * @package WhatsApp_SaaS_Pro
 * @subpackage Admin
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin reports management class
 */
class WSP_Admin_Reports {
    
    /**
     * Initialize the admin reports interface
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_wsp_get_report_details', array(__CLASS__, 'ajax_get_report_details'));
        add_action('wp_ajax_wsp_send_report', array(__CLASS__, 'ajax_send_report'));
        add_action('wp_ajax_wsp_download_report', array(__CLASS__, 'ajax_download_report'));
        add_action('wp_ajax_wsp_delete_report', array(__CLASS__, 'ajax_delete_report'));
        add_action('wp_ajax_wsp_get_chart_data', array(__CLASS__, 'ajax_get_chart_data'));
    }
    
    /**
     * Add menu pages
     */
    public static function add_menu_pages() {
        add_submenu_page(
            'whatsapp-saas',
            __('Report', 'whatsapp-saas-pro'),
            __('Report', 'whatsapp-saas-pro'),
            'manage_options',
            'wsp-reports',
            array(__CLASS__, 'render_reports_page')
        );
        
        add_submenu_page(
            'whatsapp-saas',
            __('Analytics', 'whatsapp-saas-pro'),
            __('Analytics', 'whatsapp-saas-pro'),
            'manage_options',
            'wsp-analytics',
            array(__CLASS__, 'render_analytics_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if (strpos($hook, 'wsp-reports') === false && strpos($hook, 'wsp-analytics') === false) {
            return;
        }
        
        wp_enqueue_style('wsp-admin-reports', WSP_PLUGIN_URL . 'assets/css/admin-reports.css', array(), WSP_VERSION);
        wp_enqueue_script('wsp-admin-reports', WSP_PLUGIN_URL . 'assets/js/admin-reports.js', array('jquery'), WSP_VERSION, true);
        
        // Add Chart.js for analytics
        if (strpos($hook, 'wsp-analytics') !== false) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        }
        
        wp_localize_script('wsp-admin-reports', 'wsp_reports', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsp_reports_nonce'),
            'confirm_delete' => __('Sei sicuro di voler eliminare questo report?', 'whatsapp-saas-pro'),
            'confirm_send' => __('Sei sicuro di voler inviare questo report?', 'whatsapp-saas-pro'),
            'sending' => __('Invio in corso...', 'whatsapp-saas-pro'),
            'sent' => __('Report inviato con successo!', 'whatsapp-saas-pro'),
            'error' => __('Si è verificato un errore. Riprova.', 'whatsapp-saas-pro')
        ));
    }
    
    /**
     * Render reports list page
     */
    public static function render_reports_page() {
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            self::handle_bulk_action($_POST['action'], $_POST['reports'] ?? array());
        }
        
        // Get filters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $customer_id = isset($_GET['customer']) ? intval($_GET['customer']) : 0;
        $report_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        // Get reports
        global $wpdb;
        $table_name = $wpdb->prefix . 'wsp_reports';
        $where_clauses = array('1=1');
        $where_values = array();
        
        if ($customer_id) {
            $where_clauses[] = 'customer_id = %d';
            $where_values[] = $customer_id;
        }
        
        if ($report_type) {
            $where_clauses[] = 'report_type = %s';
            $where_values[] = $report_type;
        }
        
        if ($date_from) {
            $where_clauses[] = 'DATE(created_at) >= %s';
            $where_values[] = $date_from;
        }
        
        if ($date_to) {
            $where_clauses[] = 'DATE(created_at) <= %s';
            $where_values[] = $date_to;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($where_values)) {
            $total_query = $wpdb->prepare($total_query, $where_values);
        }
        $total_reports = $wpdb->get_var($total_query);
        $total_pages = ceil($total_reports / $per_page);
        
        // Get reports
        $query = "SELECT r.*, c.business_name, c.customer_code 
                  FROM $table_name r
                  LEFT JOIN {$wpdb->prefix}wsp_customers c ON r.customer_id = c.id
                  WHERE $where_sql
                  ORDER BY r.created_at DESC
                  LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($per_page, $offset));
        $reports = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        // Get customers for filter
        $customers = $wpdb->get_results("
            SELECT id, business_name, customer_code 
            FROM {$wpdb->prefix}wsp_customers 
            ORDER BY business_name ASC
        ");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Gestione Report', 'whatsapp-saas-pro'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=wsp-analytics'); ?>" class="page-title-action">
                <?php _e('Visualizza Analytics', 'whatsapp-saas-pro'); ?>
            </a>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(self::get_message($_GET['message'])); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="wsp-reports">
                    
                    <div class="alignleft actions">
                        <select name="customer">
                            <option value="0"><?php _e('Tutti i clienti', 'whatsapp-saas-pro'); ?></option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer->id; ?>" <?php selected($customer_id, $customer->id); ?>>
                                    <?php echo esc_html($customer->business_name); ?> (<?php echo esc_html($customer->customer_code); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="type">
                            <option value=""><?php _e('Tutti i tipi', 'whatsapp-saas-pro'); ?></option>
                            <option value="daily" <?php selected($report_type, 'daily'); ?>><?php _e('Giornaliero', 'whatsapp-saas-pro'); ?></option>
                            <option value="weekly" <?php selected($report_type, 'weekly'); ?>><?php _e('Settimanale', 'whatsapp-saas-pro'); ?></option>
                            <option value="monthly" <?php selected($report_type, 'monthly'); ?>><?php _e('Mensile', 'whatsapp-saas-pro'); ?></option>
                            <option value="custom" <?php selected($report_type, 'custom'); ?>><?php _e('Personalizzato', 'whatsapp-saas-pro'); ?></option>
                            <option value="whatsapp_command" <?php selected($report_type, 'whatsapp_command'); ?>><?php _e('Comando WhatsApp', 'whatsapp-saas-pro'); ?></option>
                        </select>
                        
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="<?php _e('Data inizio', 'whatsapp-saas-pro'); ?>">
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="<?php _e('Data fine', 'whatsapp-saas-pro'); ?>">
                        
                        <input type="submit" class="button" value="<?php _e('Filtra', 'whatsapp-saas-pro'); ?>">
                        
                        <button type="button" class="button" id="generate-report-btn">
                            <?php _e('Genera Report', 'whatsapp-saas-pro'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Reports Table -->
            <form method="post">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox">
                            </td>
                            <th><?php _e('ID', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Cliente', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Tipo', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Periodo', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Numeri', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Messaggi', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Stato', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Creato', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Azioni', 'whatsapp-saas-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports): ?>
                            <?php foreach ($reports as $report): ?>
                                <?php
                                $report_data = json_decode($report->report_data, true);
                                $numbers_count = isset($report_data['total_numbers']) ? $report_data['total_numbers'] : 0;
                                $messages_count = isset($report_data['total_messages']) ? $report_data['total_messages'] : 0;
                                ?>
                                <tr data-report-id="<?php echo $report->id; ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="reports[]" value="<?php echo $report->id; ?>">
                                    </th>
                                    <td>
                                        <strong>#<?php echo $report->id; ?></strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="#" class="view-report" data-report-id="<?php echo $report->id; ?>">
                                                    <?php _e('Visualizza', 'whatsapp-saas-pro'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($report->customer_id): ?>
                                            <strong><?php echo esc_html($report->business_name); ?></strong>
                                            <br><small><?php echo esc_html($report->customer_code); ?></small>
                                        <?php else: ?>
                                            <em><?php _e('Sistema', 'whatsapp-saas-pro'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $type_labels = array(
                                            'daily' => __('Giornaliero', 'whatsapp-saas-pro'),
                                            'weekly' => __('Settimanale', 'whatsapp-saas-pro'),
                                            'monthly' => __('Mensile', 'whatsapp-saas-pro'),
                                            'custom' => __('Personalizzato', 'whatsapp-saas-pro'),
                                            'whatsapp_command' => __('WhatsApp', 'whatsapp-saas-pro')
                                        );
                                        echo '<span class="report-type report-type-' . $report->report_type . '">';
                                        echo esc_html($type_labels[$report->report_type] ?? $report->report_type);
                                        echo '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($report->period_start && $report->period_end) {
                                            echo date('d/m/Y', strtotime($report->period_start));
                                            if ($report->period_start != $report->period_end) {
                                                echo ' - ' . date('d/m/Y', strtotime($report->period_end));
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo number_format($numbers_count); ?></td>
                                    <td><?php echo number_format($messages_count); ?></td>
                                    <td>
                                        <?php if ($report->sent_at): ?>
                                            <span class="status-badge status-sent">
                                                <?php _e('Inviato', 'whatsapp-saas-pro'); ?>
                                            </span>
                                            <br><small><?php echo date('d/m H:i', strtotime($report->sent_at)); ?></small>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <?php _e('Da inviare', 'whatsapp-saas-pro'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($report->created_at)); ?></td>
                                    <td class="actions-column">
                                        <button type="button" class="button-link view-report" 
                                                data-report-id="<?php echo $report->id; ?>"
                                                title="<?php _e('Visualizza Report', 'whatsapp-saas-pro'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                        
                                        <?php if (!$report->sent_at): ?>
                                            <button type="button" class="button-link send-report" 
                                                    data-report-id="<?php echo $report->id; ?>"
                                                    title="<?php _e('Invia Report', 'whatsapp-saas-pro'); ?>">
                                                <span class="dashicons dashicons-email-alt"></span>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="button-link download-report" 
                                                data-report-id="<?php echo $report->id; ?>"
                                                title="<?php _e('Scarica Report', 'whatsapp-saas-pro'); ?>">
                                            <span class="dashicons dashicons-download"></span>
                                        </button>
                                        
                                        <button type="button" class="button-link delete-report" 
                                                data-report-id="<?php echo $report->id; ?>"
                                                title="<?php _e('Elimina Report', 'whatsapp-saas-pro'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-items">
                                    <?php _e('Nessun report trovato.', 'whatsapp-saas-pro'); ?>
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
                            <option value="send"><?php _e('Invia', 'whatsapp-saas-pro'); ?></option>
                            <option value="download"><?php _e('Scarica', 'whatsapp-saas-pro'); ?></option>
                            <option value="delete"><?php _e('Elimina', 'whatsapp-saas-pro'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Applica', 'whatsapp-saas-pro'); ?>">
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(__('%d report', 'whatsapp-saas-pro'), $total_reports); ?>
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
        
        <!-- Report Details Modal -->
        <div id="report-details-modal" class="wsp-modal" style="display:none;">
            <div class="wsp-modal-content wsp-modal-large">
                <span class="wsp-modal-close">&times;</span>
                <h2><?php _e('Dettagli Report', 'whatsapp-saas-pro'); ?></h2>
                <div id="report-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        
        <!-- Generate Report Modal -->
        <div id="generate-report-modal" class="wsp-modal" style="display:none;">
            <div class="wsp-modal-content">
                <span class="wsp-modal-close">&times;</span>
                <h2><?php _e('Genera Nuovo Report', 'whatsapp-saas-pro'); ?></h2>
                <form id="generate-report-form">
                    <p>
                        <label><?php _e('Cliente:', 'whatsapp-saas-pro'); ?></label>
                        <select name="customer_id" required>
                            <option value=""><?php _e('Seleziona cliente', 'whatsapp-saas-pro'); ?></option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer->id; ?>">
                                    <?php echo esc_html($customer->business_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label><?php _e('Tipo Report:', 'whatsapp-saas-pro'); ?></label>
                        <select name="report_type" required>
                            <option value="daily"><?php _e('Giornaliero', 'whatsapp-saas-pro'); ?></option>
                            <option value="weekly"><?php _e('Settimanale', 'whatsapp-saas-pro'); ?></option>
                            <option value="monthly"><?php _e('Mensile', 'whatsapp-saas-pro'); ?></option>
                            <option value="custom"><?php _e('Personalizzato', 'whatsapp-saas-pro'); ?></option>
                        </select>
                    </p>
                    <p class="date-range-fields" style="display:none;">
                        <label><?php _e('Data Inizio:', 'whatsapp-saas-pro'); ?></label>
                        <input type="date" name="date_from">
                    </p>
                    <p class="date-range-fields" style="display:none;">
                        <label><?php _e('Data Fine:', 'whatsapp-saas-pro'); ?></label>
                        <input type="date" name="date_to">
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="send_immediately" value="1">
                            <?php _e('Invia immediatamente via WhatsApp', 'whatsapp-saas-pro'); ?>
                        </label>
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Genera Report', 'whatsapp-saas-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public static function render_analytics_page() {
        global $wpdb;
        
        // Get date range
        $date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '7days';
        $customer_id = isset($_GET['customer']) ? intval($_GET['customer']) : 0;
        
        // Calculate dates based on range
        $end_date = current_time('Y-m-d');
        switch ($date_range) {
            case 'today':
                $start_date = $end_date;
                break;
            case '7days':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-7 days'));
        }
        
        // Get statistics
        $stats = self::get_analytics_stats($start_date, $end_date, $customer_id);
        
        // Get customers for filter
        $customers = $wpdb->get_results("
            SELECT id, business_name, customer_code 
            FROM {$wpdb->prefix}wsp_customers 
            ORDER BY business_name ASC
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics Dashboard', 'whatsapp-saas-pro'); ?></h1>
            
            <!-- Filters -->
            <div class="analytics-filters">
                <form method="get" class="filter-form">
                    <input type="hidden" name="page" value="wsp-analytics">
                    
                    <select name="range">
                        <option value="today" <?php selected($date_range, 'today'); ?>><?php _e('Oggi', 'whatsapp-saas-pro'); ?></option>
                        <option value="7days" <?php selected($date_range, '7days'); ?>><?php _e('Ultimi 7 giorni', 'whatsapp-saas-pro'); ?></option>
                        <option value="30days" <?php selected($date_range, '30days'); ?>><?php _e('Ultimi 30 giorni', 'whatsapp-saas-pro'); ?></option>
                        <option value="90days" <?php selected($date_range, '90days'); ?>><?php _e('Ultimi 90 giorni', 'whatsapp-saas-pro'); ?></option>
                    </select>
                    
                    <select name="customer">
                        <option value="0"><?php _e('Tutti i clienti', 'whatsapp-saas-pro'); ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer->id; ?>" <?php selected($customer_id, $customer->id); ?>>
                                <?php echo esc_html($customer->business_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="submit" class="button" value="<?php _e('Applica Filtri', 'whatsapp-saas-pro'); ?>">
                    
                    <button type="button" class="button button-primary" id="export-analytics">
                        <?php _e('Esporta Report', 'whatsapp-saas-pro'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Statistics Cards -->
            <div class="analytics-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php _e('Clienti Attivi', 'whatsapp-saas-pro'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['active_customers']); ?></div>
                        <div class="stat-change <?php echo $stats['customers_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['customers_change'] >= 0 ? '+' : ''; ?>
                            <?php echo $stats['customers_change']; ?>% <?php _e('vs periodo precedente', 'whatsapp-saas-pro'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-phone"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php _e('Numeri Raccolti', 'whatsapp-saas-pro'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['total_numbers']); ?></div>
                        <div class="stat-change <?php echo $stats['numbers_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['numbers_change'] >= 0 ? '+' : ''; ?>
                            <?php echo $stats['numbers_change']; ?>% <?php _e('vs periodo precedente', 'whatsapp-saas-pro'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-email"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php _e('Messaggi Inviati', 'whatsapp-saas-pro'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['total_messages']); ?></div>
                        <div class="stat-change <?php echo $stats['messages_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['messages_change'] >= 0 ? '+' : ''; ?>
                            <?php echo $stats['messages_change']; ?>% <?php _e('vs periodo precedente', 'whatsapp-saas-pro'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php _e('Tasso Conversione', 'whatsapp-saas-pro'); ?></h3>
                        <div class="stat-value"><?php echo number_format($stats['conversion_rate'], 1); ?>%</div>
                        <div class="stat-change <?php echo $stats['conversion_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['conversion_change'] >= 0 ? '+' : ''; ?>
                            <?php echo $stats['conversion_change']; ?>% <?php _e('vs periodo precedente', 'whatsapp-saas-pro'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="analytics-charts">
                <div class="chart-container">
                    <h3><?php _e('Andamento Raccolta Numeri', 'whatsapp-saas-pro'); ?></h3>
                    <canvas id="numbers-chart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3><?php _e('Messaggi per Piano', 'whatsapp-saas-pro'); ?></h3>
                    <canvas id="plans-chart"></canvas>
                </div>
            </div>
            
            <!-- Top Customers Table -->
            <div class="analytics-table">
                <h3><?php _e('Top Clienti per Attività', 'whatsapp-saas-pro'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Cliente', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Piano', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Numeri', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Messaggi', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Crediti', 'whatsapp-saas-pro'); ?></th>
                            <th><?php _e('Ultimo Accesso', 'whatsapp-saas-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $top_customers = self::get_top_customers($start_date, $end_date, 10);
                        foreach ($top_customers as $customer):
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($customer->business_name); ?></strong>
                                    <br><small><?php echo esc_html($customer->customer_code); ?></small>
                                </td>
                                <td>
                                    <span class="plan-badge plan-<?php echo strtolower($customer->plan_name); ?>">
                                        <?php echo esc_html($customer->plan_name); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($customer->numbers_count); ?></td>
                                <td><?php echo number_format($customer->messages_count); ?></td>
                                <td><?php echo number_format($customer->available_credits); ?></td>
                                <td>
                                    <?php 
                                    if ($customer->last_activity) {
                                        echo human_time_diff(strtotime($customer->last_activity), current_time('timestamp'));
                                        echo ' ' . __('fa', 'whatsapp-saas-pro');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize charts
            var chartData = <?php echo json_encode(self::get_chart_data($start_date, $end_date, $customer_id)); ?>;
            
            // Numbers trend chart
            var numbersCtx = document.getElementById('numbers-chart').getContext('2d');
            new Chart(numbersCtx, {
                type: 'line',
                data: {
                    labels: chartData.dates,
                    datasets: [{
                        label: '<?php _e('Numeri Raccolti', 'whatsapp-saas-pro'); ?>',
                        data: chartData.numbers,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Plans distribution chart
            var plansCtx = document.getElementById('plans-chart').getContext('2d');
            new Chart(plansCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.plan_names,
                    datasets: [{
                        data: chartData.plan_values,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get analytics statistics
     */
    private static function get_analytics_stats($start_date, $end_date, $customer_id = 0) {
        global $wpdb;
        
        // Base where clause
        $where_customer = $customer_id ? "AND customer_id = $customer_id" : "";
        
        // Active customers
        $active_customers = $wpdb->get_var("
            SELECT COUNT(DISTINCT id) 
            FROM {$wpdb->prefix}wsp_customers 
            WHERE status = 'active' 
            $where_customer
        ");
        
        // Total numbers collected in period
        $total_numbers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}wsp_whatsapp_numbers 
            WHERE DATE(created_at) BETWEEN %s AND %s 
            $where_customer
        ", $start_date, $end_date));
        
        // Total messages sent
        $total_messages = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}wsp_messages 
            WHERE DATE(sent_at) BETWEEN %s AND %s 
            $where_customer
        ", $start_date, $end_date));
        
        // Calculate conversion rate
        $conversion_rate = $total_numbers > 0 ? ($total_messages / $total_numbers) * 100 : 0;
        
        // Calculate changes vs previous period
        $period_days = (strtotime($end_date) - strtotime($start_date)) / 86400;
        $prev_start = date('Y-m-d', strtotime($start_date . " -$period_days days"));
        $prev_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
        
        // Previous period numbers
        $prev_numbers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}wsp_whatsapp_numbers 
            WHERE DATE(created_at) BETWEEN %s AND %s 
            $where_customer
        ", $prev_start, $prev_end));
        
        $numbers_change = $prev_numbers > 0 ? 
            round((($total_numbers - $prev_numbers) / $prev_numbers) * 100, 1) : 0;
        
        return array(
            'active_customers' => $active_customers,
            'total_numbers' => $total_numbers,
            'total_messages' => $total_messages,
            'conversion_rate' => $conversion_rate,
            'customers_change' => 0, // TODO: Calculate
            'numbers_change' => $numbers_change,
            'messages_change' => 0, // TODO: Calculate
            'conversion_change' => 0 // TODO: Calculate
        );
    }
    
    /**
     * Get top customers
     */
    private static function get_top_customers($start_date, $end_date, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.*,
                p.name as plan_name,
                COUNT(DISTINCT n.id) as numbers_count,
                COUNT(DISTINCT m.id) as messages_count,
                MAX(a.created_at) as last_activity
            FROM {$wpdb->prefix}wsp_customers c
            LEFT JOIN {$wpdb->prefix}wsp_customer_subscriptions cs ON c.id = cs.customer_id AND cs.status = 'active'
            LEFT JOIN {$wpdb->prefix}wsp_subscription_plans p ON cs.plan_id = p.id
            LEFT JOIN {$wpdb->prefix}wsp_whatsapp_numbers n ON c.id = n.customer_id 
                AND DATE(n.created_at) BETWEEN %s AND %s
            LEFT JOIN {$wpdb->prefix}wsp_messages m ON c.id = m.customer_id 
                AND DATE(m.sent_at) BETWEEN %s AND %s
            LEFT JOIN {$wpdb->prefix}wsp_activity_log a ON c.id = a.customer_id
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY messages_count DESC
            LIMIT %d
        ", $start_date, $end_date, $start_date, $end_date, $limit));
    }
    
    /**
     * Get chart data
     */
    private static function get_chart_data($start_date, $end_date, $customer_id = 0) {
        global $wpdb;
        
        $where_customer = $customer_id ? "AND customer_id = $customer_id" : "";
        
        // Get daily numbers
        $daily_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM {$wpdb->prefix}wsp_whatsapp_numbers
            WHERE DATE(created_at) BETWEEN %s AND %s
            $where_customer
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        // Prepare dates and values
        $dates = array();
        $numbers = array();
        
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $dates[] = date('d/m', $current);
            
            $count = 0;
            foreach ($daily_data as $data) {
                if ($data->date == $date) {
                    $count = $data->count;
                    break;
                }
            }
            $numbers[] = $count;
            
            $current = strtotime('+1 day', $current);
        }
        
        // Get plan distribution
        $plan_data = $wpdb->get_results("
            SELECT 
                p.name,
                COUNT(DISTINCT m.id) as message_count
            FROM {$wpdb->prefix}wsp_subscription_plans p
            LEFT JOIN {$wpdb->prefix}wsp_customer_subscriptions cs ON p.id = cs.plan_id AND cs.status = 'active'
            LEFT JOIN {$wpdb->prefix}wsp_messages m ON cs.customer_id = m.customer_id
            GROUP BY p.id
        ");
        
        $plan_names = array();
        $plan_values = array();
        
        foreach ($plan_data as $plan) {
            $plan_names[] = $plan->name;
            $plan_values[] = $plan->message_count;
        }
        
        return array(
            'dates' => $dates,
            'numbers' => $numbers,
            'plan_names' => $plan_names,
            'plan_values' => $plan_values
        );
    }
    
    /**
     * Handle bulk actions
     */
    private static function handle_bulk_action($action, $report_ids) {
        if (empty($report_ids)) {
            return;
        }
        
        foreach ($report_ids as $report_id) {
            switch ($action) {
                case 'send':
                    WSP_Reports::send_report($report_id);
                    break;
                    
                case 'delete':
                    WSP_Reports::delete_report($report_id);
                    break;
                    
                case 'download':
                    // TODO: Implement bulk download
                    break;
            }
        }
    }
    
    /**
     * AJAX: Get report details
     */
    public static function ajax_get_report_details() {
        check_ajax_referer('wsp_reports_nonce', 'nonce');
        
        $report_id = intval($_POST['report_id']);
        $report = WSP_Reports::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(__('Report non trovato', 'whatsapp-saas-pro'));
        }
        
        $data = json_decode($report->report_data, true);
        
        ob_start();
        ?>
        <div class="report-details">
            <div class="report-header">
                <h3><?php _e('Report', 'whatsapp-saas-pro'); ?> #<?php echo $report->id; ?></h3>
                <p><?php _e('Generato:', 'whatsapp-saas-pro'); ?> <?php echo date('d/m/Y H:i', strtotime($report->created_at)); ?></p>
                <?php if ($report->sent_at): ?>
                    <p><?php _e('Inviato:', 'whatsapp-saas-pro'); ?> <?php echo date('d/m/Y H:i', strtotime($report->sent_at)); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="report-content">
                <?php echo WSP_Reports::format_report_html($data); ?>
            </div>
        </div>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Send report
     */
    public static function ajax_send_report() {
        check_ajax_referer('wsp_reports_nonce', 'nonce');
        
        $report_id = intval($_POST['report_id']);
        
        if (WSP_Reports::send_report($report_id)) {
            wp_send_json_success(__('Report inviato con successo', 'whatsapp-saas-pro'));
        } else {
            wp_send_json_error(__('Errore nell\'invio del report', 'whatsapp-saas-pro'));
        }
    }
    
    /**
     * AJAX: Download report
     */
    public static function ajax_download_report() {
        check_ajax_referer('wsp_reports_nonce', 'nonce');
        
        $report_id = intval($_POST['report_id']);
        $report = WSP_Reports::get_report($report_id);
        
        if (!$report) {
            wp_send_json_error(__('Report non trovato', 'whatsapp-saas-pro'));
        }
        
        // Generate CSV content
        $csv_content = WSP_Reports::generate_csv($report);
        
        // Return download URL
        $upload_dir = wp_upload_dir();
        $filename = 'report-' . $report_id . '-' . date('YmdHis') . '.csv';
        $filepath = $upload_dir['basedir'] . '/wsp-reports/' . $filename;
        $fileurl = $upload_dir['baseurl'] . '/wsp-reports/' . $filename;
        
        // Create directory if not exists
        wp_mkdir_p($upload_dir['basedir'] . '/wsp-reports/');
        
        // Save file
        file_put_contents($filepath, $csv_content);
        
        wp_send_json_success(array('download_url' => $fileurl));
    }
    
    /**
     * AJAX: Delete report
     */
    public static function ajax_delete_report() {
        check_ajax_referer('wsp_reports_nonce', 'nonce');
        
        $report_id = intval($_POST['report_id']);
        
        if (WSP_Reports::delete_report($report_id)) {
            wp_send_json_success(__('Report eliminato con successo', 'whatsapp-saas-pro'));
        } else {
            wp_send_json_error(__('Errore nell\'eliminazione del report', 'whatsapp-saas-pro'));
        }
    }
    
    /**
     * AJAX: Get chart data
     */
    public static function ajax_get_chart_data() {
        check_ajax_referer('wsp_reports_nonce', 'nonce');
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        
        $data = self::get_chart_data($start_date, $end_date, $customer_id);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get message text
     */
    private static function get_message($code) {
        $messages = array(
            'report_generated' => __('Report generato con successo', 'whatsapp-saas-pro'),
            'report_sent' => __('Report inviato con successo', 'whatsapp-saas-pro'),
            'report_deleted' => __('Report eliminato con successo', 'whatsapp-saas-pro'),
            'bulk_action_completed' => __('Azione completata con successo', 'whatsapp-saas-pro')
        );
        
        return $messages[$code] ?? __('Operazione completata', 'whatsapp-saas-pro');
    }
}

// Initialize
WSP_Admin_Reports::init();