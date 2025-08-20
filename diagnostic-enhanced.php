<?php
/**
 * WhatsApp SaaS Pro - Enhanced Diagnostic Script
 * 
 * This script performs comprehensive checks on all components of the 
 * WhatsApp SaaS Pro multi-tenant system
 * 
 * Usage: php diagnostic-enhanced.php [--fix] [--verbose]
 * 
 * @version 4.2.0
 * @author WaPower Team
 */

// Check if running in CLI
$is_cli = php_sapi_name() === 'cli';

// Parse command line arguments
$options = getopt('', ['fix', 'verbose', 'help']);
$fix_mode = isset($options['fix']);
$verbose = isset($options['verbose']);

if (isset($options['help'])) {
    echo "WhatsApp SaaS Pro - Enhanced Diagnostic Script\n";
    echo "==============================================\n\n";
    echo "Usage: php diagnostic-enhanced.php [options]\n\n";
    echo "Options:\n";
    echo "  --fix      Attempt to fix found issues automatically\n";
    echo "  --verbose  Show detailed output for each check\n";
    echo "  --help     Show this help message\n";
    exit(0);
}

// Color codes for CLI output
class Colors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[0;33m";
    const BLUE = "\033[0;34m";
    const MAGENTA = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const WHITE = "\033[0;37m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

// Helper functions
function print_header($text) {
    global $is_cli;
    if ($is_cli) {
        echo "\n" . Colors::BOLD . Colors::CYAN . "=== $text ===" . Colors::RESET . "\n\n";
    } else {
        echo "<h2>$text</h2>";
    }
}

function print_success($text) {
    global $is_cli;
    if ($is_cli) {
        echo Colors::GREEN . "✓ " . Colors::RESET . $text . "\n";
    } else {
        echo "<div style='color: green;'>✓ $text</div>";
    }
}

function print_error($text) {
    global $is_cli;
    if ($is_cli) {
        echo Colors::RED . "✗ " . Colors::RESET . $text . "\n";
    } else {
        echo "<div style='color: red;'>✗ $text</div>";
    }
}

function print_warning($text) {
    global $is_cli;
    if ($is_cli) {
        echo Colors::YELLOW . "⚠ " . Colors::RESET . $text . "\n";
    } else {
        echo "<div style='color: orange;'>⚠ $text</div>";
    }
}

function print_info($text) {
    global $is_cli;
    if ($is_cli) {
        echo Colors::BLUE . "ℹ " . Colors::RESET . $text . "\n";
    } else {
        echo "<div style='color: blue;'>ℹ $text</div>";
    }
}

function print_verbose($text) {
    global $verbose, $is_cli;
    if ($verbose) {
        if ($is_cli) {
            echo Colors::WHITE . "  → " . $text . Colors::RESET . "\n";
        } else {
            echo "<div style='color: gray; margin-left: 20px;'>→ $text</div>";
        }
    }
}

// Main diagnostic class
class WSP_Diagnostic {
    
    private $plugin_dir;
    private $issues = [];
    private $warnings = [];
    private $successes = [];
    private $fix_mode;
    private $verbose;
    
    // Expected files and directories
    private $required_files = [
        'whatsapp-saas-pro.php' => 'Main plugin file',
        'includes/class-wsp-database-saas.php' => 'Database management class',
        'includes/class-wsp-customers.php' => 'Customer management class',
        'includes/class-wsp-reports.php' => 'Report generation class',
        'includes/class-wsp-integrations.php' => 'External integrations class',
        'includes/class-wsp-activity-log.php' => 'Activity logging class',
        'includes/class-wsp-loader.php' => 'Compatibility loader',
        'admin/class-wsp-admin-customers.php' => 'Admin customers interface',
        'admin/class-wsp-admin-reports.php' => 'Admin reports interface',
        'admin/class-wsp-admin-test-saas.php' => 'Test suite'
    ];
    
    // Expected database tables
    private $required_tables = [
        'wsp_customers' => 'Customer records',
        'wsp_subscription_plans' => 'Subscription plans',
        'wsp_customer_subscriptions' => 'Active subscriptions',
        'wsp_whatsapp_numbers' => 'Collected WhatsApp numbers',
        'wsp_campaigns' => 'Marketing campaigns',
        'wsp_messages' => 'Messages sent',
        'wsp_credits_transactions' => 'Credit transactions',
        'wsp_reports' => 'Generated reports',
        'wsp_activity_log' => 'System activity logs',
        'wsp_webhook_events' => 'Webhook events',
        'wsp_integrations' => 'External integrations'
    ];
    
    // Expected configuration
    private $required_config = [
        'wsp_mail2wa_api_url' => 'Mail2Wa API URL',
        'wsp_mail2wa_api_key' => 'Mail2Wa API Key',
        'wsp_qr_service_url' => 'QR Code Service URL',
        'wsp_registration_form_url' => 'Registration Form URL',
        'wsp_n8n_webhook_url' => 'N8N Webhook URL (optional)'
    ];
    
    public function __construct($plugin_dir, $fix_mode = false, $verbose = false) {
        $this->plugin_dir = $plugin_dir;
        $this->fix_mode = $fix_mode;
        $this->verbose = $verbose;
    }
    
    /**
     * Run all diagnostic checks
     */
    public function run_full_diagnostic() {
        print_header("WhatsApp SaaS Pro - Enhanced Diagnostic");
        echo "Plugin Directory: " . $this->plugin_dir . "\n";
        echo "Fix Mode: " . ($this->fix_mode ? 'Enabled' : 'Disabled') . "\n";
        echo "Verbose: " . ($this->verbose ? 'Yes' : 'No') . "\n";
        
        // 1. Check file structure
        print_header("1. File Structure Check");
        $this->check_file_structure();
        
        // 2. Check PHP syntax
        print_header("2. PHP Syntax Check");
        $this->check_php_syntax();
        
        // 3. Check class definitions
        print_header("3. Class Definitions Check");
        $this->check_class_definitions();
        
        // 4. Check database structure (if possible)
        print_header("4. Database Structure Check");
        $this->check_database_structure();
        
        // 5. Check configuration
        print_header("5. Configuration Check");
        $this->check_configuration();
        
        // 6. Check dependencies
        print_header("6. Dependencies Check");
        $this->check_dependencies();
        
        // 7. Check permissions
        print_header("7. File Permissions Check");
        $this->check_permissions();
        
        // 8. Check for conflicts
        print_header("8. Plugin Conflicts Check");
        $this->check_conflicts();
        
        // 9. Generate summary report
        print_header("Diagnostic Summary");
        $this->generate_summary();
        
        return [
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'successes' => $this->successes
        ];
    }
    
    /**
     * Check file structure
     */
    private function check_file_structure() {
        foreach ($this->required_files as $file => $description) {
            $full_path = $this->plugin_dir . '/' . $file;
            
            if (file_exists($full_path)) {
                $size = filesize($full_path);
                print_success("Found: $file ($description) - " . $this->format_bytes($size));
                print_verbose("Path: $full_path");
                $this->successes[] = "File $file exists";
                
                // Check if file is readable
                if (!is_readable($full_path)) {
                    print_warning("File $file is not readable");
                    $this->warnings[] = "File $file is not readable";
                    
                    if ($this->fix_mode) {
                        chmod($full_path, 0644);
                        print_info("Fixed: Set permissions to 0644 for $file");
                    }
                }
            } else {
                print_error("Missing: $file ($description)");
                $this->issues[] = "Missing file: $file";
                
                if ($this->fix_mode) {
                    $this->attempt_fix_missing_file($file);
                }
            }
        }
        
        // Check for extra directories
        $directories = ['admin', 'includes', 'assets', 'templates', 'install', 'n8n-workflows'];
        foreach ($directories as $dir) {
            $full_path = $this->plugin_dir . '/' . $dir;
            if (is_dir($full_path)) {
                $count = count(glob($full_path . '/*'));
                print_success("Directory $dir exists with $count items");
            } else {
                print_warning("Directory $dir not found");
                
                if ($this->fix_mode) {
                    mkdir($full_path, 0755, true);
                    print_info("Created directory: $dir");
                }
            }
        }
    }
    
    /**
     * Check PHP syntax of all files
     */
    private function check_php_syntax() {
        $php_files = $this->get_all_php_files($this->plugin_dir);
        $errors_found = false;
        
        foreach ($php_files as $file) {
            $relative_path = str_replace($this->plugin_dir . '/', '', $file);
            $output = [];
            $return_var = 0;
            
            exec("php -l '$file' 2>&1", $output, $return_var);
            
            if ($return_var !== 0) {
                print_error("Syntax error in $relative_path");
                print_verbose(implode("\n", $output));
                $this->issues[] = "PHP syntax error in $relative_path";
                $errors_found = true;
            } else {
                print_verbose("✓ $relative_path - No syntax errors");
            }
        }
        
        if (!$errors_found) {
            print_success("All PHP files have valid syntax");
            $this->successes[] = "All PHP files have valid syntax";
        }
    }
    
    /**
     * Check if all required classes are defined
     */
    private function check_class_definitions() {
        $classes_to_check = [
            'WhatsAppSaasPluginPro' => 'Main plugin class',
            'WSP_Database_SaaS' => 'Database management',
            'WSP_Customers' => 'Customer management',
            'WSP_Reports' => 'Report generation',
            'WSP_Integrations' => 'External integrations',
            'WSP_Activity_Log' => 'Activity logging',
            'WSP_Admin_Customers' => 'Admin customer interface',
            'WSP_Admin_Reports' => 'Admin reports interface',
            'WSP_Admin_Test_SaaS' => 'Test suite'
        ];
        
        // Simulate loading classes
        foreach ($classes_to_check as $class => $description) {
            $found = false;
            
            // Search for class definition in files
            $search_pattern = "/class\s+$class\s*{/";
            $php_files = $this->get_all_php_files($this->plugin_dir);
            
            foreach ($php_files as $file) {
                $content = file_get_contents($file);
                if (preg_match($search_pattern, $content)) {
                    $relative_path = str_replace($this->plugin_dir . '/', '', $file);
                    print_success("Class $class found in $relative_path ($description)");
                    $this->successes[] = "Class $class is defined";
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                print_warning("Class $class not found ($description)");
                $this->warnings[] = "Class $class not found";
            }
        }
    }
    
    /**
     * Check database structure
     */
    private function check_database_structure() {
        print_info("Database checks require WordPress environment");
        print_verbose("To run database checks, use the WordPress admin diagnostic tool");
        
        // Check if we can read the database schema file
        $schema_file = $this->plugin_dir . '/install/database-schema.sql';
        if (file_exists($schema_file)) {
            print_success("Database schema file found");
            $content = file_get_contents($schema_file);
            
            foreach ($this->required_tables as $table => $description) {
                if (strpos($content, $table) !== false) {
                    print_verbose("✓ Table definition found: $table ($description)");
                } else {
                    print_warning("Table definition not found in schema: $table");
                }
            }
        } else {
            print_warning("Database schema file not found");
            
            if ($this->fix_mode) {
                $this->create_database_schema_file();
            }
        }
    }
    
    /**
     * Check configuration
     */
    private function check_configuration() {
        // Check if constants are defined in main file
        $main_file = $this->plugin_dir . '/whatsapp-saas-pro.php';
        
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file);
            
            $constants = [
                'WSP_VERSION' => '4.0.0',
                'WSP_MAIL2WA_DEFAULT_API' => 'https://api.Mail2Wa.it',
                'WSP_MAIL2WA_DEFAULT_KEY' => '1f06d5c8bd0cd19f7c99b660b504bb25',
                'WSP_QR_SERVICE_URL' => 'https://qr.wapower.it',
                'WSP_REGISTRATION_FORM_URL' => 'https://upgradeservizi.eu/external_add_user/'
            ];
            
            foreach ($constants as $constant => $expected_value) {
                if (preg_match("/define\s*\(\s*'$constant'/", $content)) {
                    print_success("Constant $constant is defined");
                    print_verbose("Expected value: $expected_value");
                } else {
                    print_error("Constant $constant not defined");
                    $this->issues[] = "Missing constant: $constant";
                }
            }
        }
    }
    
    /**
     * Check dependencies
     */
    private function check_dependencies() {
        // Check PHP version
        $php_version = phpversion();
        if (version_compare($php_version, '7.4', '>=')) {
            print_success("PHP version $php_version meets requirements (>= 7.4)");
        } else {
            print_error("PHP version $php_version does not meet requirements (requires >= 7.4)");
            $this->issues[] = "PHP version too old";
        }
        
        // Check required PHP extensions
        $required_extensions = ['curl', 'json', 'mysqli', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                print_success("PHP extension '$ext' is loaded");
            } else {
                print_error("PHP extension '$ext' is not loaded");
                $this->issues[] = "Missing PHP extension: $ext";
            }
        }
        
        // Check if we can write to temp directory
        $temp_dir = sys_get_temp_dir();
        if (is_writable($temp_dir)) {
            print_success("Temp directory is writable: $temp_dir");
        } else {
            print_warning("Temp directory is not writable: $temp_dir");
            $this->warnings[] = "Temp directory not writable";
        }
    }
    
    /**
     * Check file permissions
     */
    private function check_permissions() {
        $dirs_to_check = [
            'assets/logs' => 0755,
            'assets/cache' => 0755,
            'assets/uploads' => 0755
        ];
        
        foreach ($dirs_to_check as $dir => $expected_perms) {
            $full_path = $this->plugin_dir . '/' . $dir;
            
            if (!is_dir($full_path)) {
                print_verbose("Directory $dir does not exist (may be normal)");
                
                if ($this->fix_mode) {
                    mkdir($full_path, $expected_perms, true);
                    print_info("Created directory: $dir with permissions " . decoct($expected_perms));
                }
            } else {
                $current_perms = fileperms($full_path) & 0777;
                if ($current_perms === $expected_perms) {
                    print_success("Directory $dir has correct permissions (" . decoct($expected_perms) . ")");
                } else {
                    print_warning("Directory $dir has permissions " . decoct($current_perms) . " (expected " . decoct($expected_perms) . ")");
                    
                    if ($this->fix_mode) {
                        chmod($full_path, $expected_perms);
                        print_info("Fixed permissions for $dir");
                    }
                }
            }
        }
    }
    
    /**
     * Check for potential conflicts
     */
    private function check_conflicts() {
        // Check for duplicate plugin files
        $plugin_files = glob($this->plugin_dir . '/*.php');
        $plugin_headers_found = 0;
        
        foreach ($plugin_files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/^\s*\*\s*Plugin Name:/m', $content)) {
                $plugin_headers_found++;
                $filename = basename($file);
                print_verbose("Plugin header found in: $filename");
                
                if ($plugin_headers_found > 1) {
                    print_error("Multiple plugin headers found - this will cause conflicts!");
                    $this->issues[] = "Multiple plugin headers in $filename";
                }
            }
        }
        
        if ($plugin_headers_found === 1) {
            print_success("Single plugin header found (no conflicts)");
        } elseif ($plugin_headers_found === 0) {
            print_error("No plugin header found!");
            $this->issues[] = "No plugin header found";
        }
        
        // Check for old version files
        $old_files = [
            'whatsapp-saas-plugin.php',
            'whatsapp-saas-plugin-v3.php',
            'whatsapp-saas-plugin-v4.php'
        ];
        
        foreach ($old_files as $old_file) {
            if (file_exists($this->plugin_dir . '/' . $old_file)) {
                print_warning("Old version file found: $old_file - should be removed");
                $this->warnings[] = "Old file present: $old_file";
                
                if ($this->fix_mode) {
                    unlink($this->plugin_dir . '/' . $old_file);
                    print_info("Removed old file: $old_file");
                }
            }
        }
    }
    
    /**
     * Generate summary report
     */
    private function generate_summary() {
        $total_issues = count($this->issues);
        $total_warnings = count($this->warnings);
        $total_successes = count($this->successes);
        
        echo "\n";
        
        if ($total_issues === 0 && $total_warnings === 0) {
            echo Colors::BOLD . Colors::GREEN . "✅ All checks passed successfully!" . Colors::RESET . "\n";
        } elseif ($total_issues === 0) {
            echo Colors::BOLD . Colors::YELLOW . "⚠️  Some warnings detected but no critical issues" . Colors::RESET . "\n";
        } else {
            echo Colors::BOLD . Colors::RED . "❌ Critical issues detected" . Colors::RESET . "\n";
        }
        
        echo "\n";
        echo "Results:\n";
        echo "  • Successes: " . Colors::GREEN . $total_successes . Colors::RESET . "\n";
        echo "  • Warnings: " . Colors::YELLOW . $total_warnings . Colors::RESET . "\n";
        echo "  • Issues: " . Colors::RED . $total_issues . Colors::RESET . "\n";
        
        if ($total_issues > 0) {
            echo "\n" . Colors::RED . "Critical Issues to Fix:" . Colors::RESET . "\n";
            foreach ($this->issues as $issue) {
                echo "  • $issue\n";
            }
        }
        
        if ($total_warnings > 0) {
            echo "\n" . Colors::YELLOW . "Warnings to Review:" . Colors::RESET . "\n";
            foreach ($this->warnings as $warning) {
                echo "  • $warning\n";
            }
        }
        
        if ($this->fix_mode) {
            echo "\n" . Colors::CYAN . "Fix mode was enabled - attempted automatic fixes where possible" . Colors::RESET . "\n";
        } else {
            echo "\n" . Colors::BLUE . "Tip: Run with --fix flag to attempt automatic fixes" . Colors::RESET . "\n";
        }
        
        // Save report to file
        $this->save_report_to_file();
    }
    
    /**
     * Helper: Get all PHP files recursively
     */
    private function get_all_php_files($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Helper: Format bytes to human readable
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Helper: Attempt to fix missing file
     */
    private function attempt_fix_missing_file($file) {
        // Create stub file with basic structure
        $full_path = $this->plugin_dir . '/' . $file;
        $dir = dirname($full_path);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $class_name = $this->get_class_name_from_file($file);
        
        $stub_content = "<?php\n";
        $stub_content .= "/**\n";
        $stub_content .= " * Stub file created by diagnostic tool\n";
        $stub_content .= " * Original file: $file\n";
        $stub_content .= " */\n\n";
        $stub_content .= "if (!defined('ABSPATH')) {\n";
        $stub_content .= "    exit;\n";
        $stub_content .= "}\n\n";
        
        if ($class_name) {
            $stub_content .= "class $class_name {\n";
            $stub_content .= "    // Stub implementation\n";
            $stub_content .= "}\n";
        }
        
        file_put_contents($full_path, $stub_content);
        print_info("Created stub file: $file");
    }
    
    /**
     * Helper: Get class name from file path
     */
    private function get_class_name_from_file($file) {
        $basename = basename($file, '.php');
        $parts = explode('-', $basename);
        
        if ($parts[0] === 'class') {
            array_shift($parts);
            $class_parts = array_map('ucfirst', $parts);
            return implode('_', $class_parts);
        }
        
        return null;
    }
    
    /**
     * Helper: Create database schema file
     */
    private function create_database_schema_file() {
        $schema_dir = $this->plugin_dir . '/install';
        if (!is_dir($schema_dir)) {
            mkdir($schema_dir, 0755, true);
        }
        
        $schema_content = $this->generate_database_schema();
        file_put_contents($schema_dir . '/database-schema.sql', $schema_content);
        print_info("Created database schema file");
    }
    
    /**
     * Helper: Generate database schema
     */
    private function generate_database_schema() {
        $schema = "-- WhatsApp SaaS Pro Database Schema\n";
        $schema .= "-- Generated by diagnostic tool\n\n";
        
        foreach ($this->required_tables as $table => $description) {
            $schema .= "-- Table: $table ($description)\n";
            $schema .= "CREATE TABLE IF NOT EXISTS `{prefix}$table` (\n";
            
            // Add basic structure based on table name
            switch ($table) {
                case 'wsp_customers':
                    $schema .= "  `id` bigint(20) NOT NULL AUTO_INCREMENT,\n";
                    $schema .= "  `customer_code` varchar(50) NOT NULL,\n";
                    $schema .= "  `business_name` varchar(255) NOT NULL,\n";
                    $schema .= "  `email` varchar(255) NOT NULL,\n";
                    $schema .= "  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,\n";
                    $schema .= "  PRIMARY KEY (`id`),\n";
                    $schema .= "  UNIQUE KEY `customer_code` (`customer_code`)\n";
                    break;
                    
                default:
                    $schema .= "  `id` bigint(20) NOT NULL AUTO_INCREMENT,\n";
                    $schema .= "  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,\n";
                    $schema .= "  PRIMARY KEY (`id`)\n";
            }
            
            $schema .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        }
        
        return $schema;
    }
    
    /**
     * Save report to file
     */
    private function save_report_to_file() {
        $report_dir = $this->plugin_dir . '/diagnostic-reports';
        if (!is_dir($report_dir)) {
            mkdir($report_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $report_file = $report_dir . '/diagnostic_' . $timestamp . '.txt';
        
        $report = "WhatsApp SaaS Pro - Diagnostic Report\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $report .= "=====================================\n\n";
        
        $report .= "SUMMARY\n";
        $report .= "-------\n";
        $report .= "Successes: " . count($this->successes) . "\n";
        $report .= "Warnings: " . count($this->warnings) . "\n";
        $report .= "Issues: " . count($this->issues) . "\n\n";
        
        if (count($this->issues) > 0) {
            $report .= "CRITICAL ISSUES\n";
            $report .= "---------------\n";
            foreach ($this->issues as $issue) {
                $report .= "• $issue\n";
            }
            $report .= "\n";
        }
        
        if (count($this->warnings) > 0) {
            $report .= "WARNINGS\n";
            $report .= "--------\n";
            foreach ($this->warnings as $warning) {
                $report .= "• $warning\n";
            }
            $report .= "\n";
        }
        
        if (count($this->successes) > 0) {
            $report .= "SUCCESSFUL CHECKS\n";
            $report .= "-----------------\n";
            foreach ($this->successes as $success) {
                $report .= "• $success\n";
            }
        }
        
        file_put_contents($report_file, $report);
        print_info("Report saved to: $report_file");
    }
}

// Main execution
if (!$is_cli) {
    echo "<pre>";
}

// Determine plugin directory
$plugin_dir = dirname(__FILE__) . '/whatsapp-saas-pro-clean';
if (!is_dir($plugin_dir)) {
    $plugin_dir = dirname(__FILE__) . '/whatsapp-saas-pro';
}

if (!is_dir($plugin_dir)) {
    print_error("Plugin directory not found!");
    print_info("Expected: $plugin_dir");
    exit(1);
}

// Run diagnostic
$diagnostic = new WSP_Diagnostic($plugin_dir, $fix_mode, $verbose);
$results = $diagnostic->run_full_diagnostic();

if (!$is_cli) {
    echo "</pre>";
}

// Exit with appropriate code
$exit_code = count($results['issues']) > 0 ? 1 : 0;
exit($exit_code);