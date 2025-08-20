#!/usr/bin/env python3
"""
WhatsApp SaaS Pro - Setup Verification Script
=============================================

This script verifies the WhatsApp SaaS Pro plugin setup and structure.
It checks for required files, validates PHP syntax, and ensures all
components are properly configured.

Usage: python3 verify-setup.py [--fix] [--verbose]

Author: WaPower Team
Version: 4.2.0
"""

import os
import sys
import json
import re
import zipfile
import hashlib
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Tuple, Optional

# ANSI color codes for terminal output
class Colors:
    RED = '\033[91m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    MAGENTA = '\033[95m'
    CYAN = '\033[96m'
    WHITE = '\033[97m'
    RESET = '\033[0m'
    BOLD = '\033[1m'

class PluginVerifier:
    """Main verification class for WhatsApp SaaS Pro plugin"""
    
    def __init__(self, plugin_dir: str = None, fix_mode: bool = False, verbose: bool = False):
        self.plugin_dir = plugin_dir or self._find_plugin_dir()
        self.fix_mode = fix_mode
        self.verbose = verbose
        self.issues = []
        self.warnings = []
        self.successes = []
        
        # Define required structure
        self.required_files = {
            'whatsapp-saas-pro.php': 'Main plugin file',
            'includes/class-wsp-database-saas.php': 'Database management',
            'includes/class-wsp-customers.php': 'Customer management',
            'includes/class-wsp-reports.php': 'Report generation',
            'includes/class-wsp-integrations.php': 'External integrations',
            'includes/class-wsp-activity-log.php': 'Activity logging',
            'includes/class-wsp-loader.php': 'Compatibility loader',
            'admin/class-wsp-admin-customers.php': 'Admin customers UI',
            'admin/class-wsp-admin-reports.php': 'Admin reports UI',
            'admin/class-wsp-admin-test-saas.php': 'Test suite'
        }
        
        self.required_directories = [
            'admin', 'includes', 'assets', 'templates', 
            'install', 'n8n-workflows'
        ]
        
        self.required_tables = [
            'wsp_customers', 'wsp_subscription_plans',
            'wsp_customer_subscriptions', 'wsp_whatsapp_numbers',
            'wsp_campaigns', 'wsp_messages', 'wsp_credits_transactions',
            'wsp_reports', 'wsp_activity_log', 'wsp_webhook_events',
            'wsp_integrations'
        ]
        
        self.required_constants = {
            'WSP_VERSION': '4.0.0',
            'WSP_MAIL2WA_DEFAULT_API': 'https://api.Mail2Wa.it',
            'WSP_MAIL2WA_DEFAULT_KEY': '1f06d5c8bd0cd19f7c99b660b504bb25',
            'WSP_QR_SERVICE_URL': 'https://qr.wapower.it',
            'WSP_REGISTRATION_FORM_URL': 'https://upgradeservizi.eu/external_add_user/'
        }
    
    def _find_plugin_dir(self) -> str:
        """Find the plugin directory"""
        base_dir = os.path.dirname(os.path.abspath(__file__))
        
        # Check for clean version first
        clean_dir = os.path.join(base_dir, 'whatsapp-saas-pro-clean')
        if os.path.exists(clean_dir):
            return clean_dir
        
        # Check for regular version
        regular_dir = os.path.join(base_dir, 'whatsapp-saas-pro')
        if os.path.exists(regular_dir):
            return regular_dir
        
        raise FileNotFoundError("Plugin directory not found")
    
    def print_header(self, text: str):
        """Print section header"""
        print(f"\n{Colors.BOLD}{Colors.CYAN}=== {text} ==={Colors.RESET}\n")
    
    def print_success(self, text: str):
        """Print success message"""
        print(f"{Colors.GREEN}✓{Colors.RESET} {text}")
        self.successes.append(text)
    
    def print_error(self, text: str):
        """Print error message"""
        print(f"{Colors.RED}✗{Colors.RESET} {text}")
        self.issues.append(text)
    
    def print_warning(self, text: str):
        """Print warning message"""
        print(f"{Colors.YELLOW}⚠{Colors.RESET} {text}")
        self.warnings.append(text)
    
    def print_info(self, text: str):
        """Print info message"""
        print(f"{Colors.BLUE}ℹ{Colors.RESET} {text}")
    
    def print_verbose(self, text: str):
        """Print verbose output if enabled"""
        if self.verbose:
            print(f"  {Colors.WHITE}→ {text}{Colors.RESET}")
    
    def verify_file_structure(self):
        """Verify all required files exist"""
        self.print_header("File Structure Verification")
        
        # Check directories
        for directory in self.required_directories:
            dir_path = os.path.join(self.plugin_dir, directory)
            if os.path.isdir(dir_path):
                file_count = len(list(Path(dir_path).iterdir()))
                self.print_success(f"Directory '{directory}' exists ({file_count} items)")
            else:
                self.print_error(f"Directory '{directory}' not found")
                if self.fix_mode:
                    os.makedirs(dir_path, exist_ok=True)
                    self.print_info(f"Created directory: {directory}")
        
        # Check files
        for file_path, description in self.required_files.items():
            full_path = os.path.join(self.plugin_dir, file_path)
            if os.path.exists(full_path):
                size = os.path.getsize(full_path)
                self.print_success(f"Found: {file_path} ({description}) - {self.format_size(size)}")
                self.print_verbose(f"Path: {full_path}")
            else:
                self.print_error(f"Missing: {file_path} ({description})")
                if self.fix_mode:
                    self.create_stub_file(full_path, description)
    
    def verify_php_syntax(self):
        """Check PHP files for basic syntax issues"""
        self.print_header("PHP Syntax Validation")
        
        php_files = list(Path(self.plugin_dir).rglob("*.php"))
        
        for php_file in php_files:
            relative_path = os.path.relpath(php_file, self.plugin_dir)
            
            try:
                with open(php_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                    
                    # Basic PHP syntax checks
                    issues = []
                    
                    # Check for opening PHP tag
                    if not content.startswith('<?php'):
                        issues.append("Missing <?php opening tag")
                    
                    # Check for balanced braces
                    open_braces = content.count('{')
                    close_braces = content.count('}')
                    if open_braces != close_braces:
                        issues.append(f"Unbalanced braces: {open_braces} open, {close_braces} close")
                    
                    # Check for balanced parentheses
                    open_parens = content.count('(')
                    close_parens = content.count(')')
                    if open_parens != close_parens:
                        issues.append(f"Unbalanced parentheses: {open_parens} open, {close_parens} close")
                    
                    # Check for common syntax errors
                    if re.search(r';;', content):
                        issues.append("Double semicolon found")
                    
                    if issues:
                        self.print_warning(f"{relative_path}: {', '.join(issues)}")
                    else:
                        self.print_verbose(f"✓ {relative_path} - No obvious syntax issues")
                        
            except Exception as e:
                self.print_error(f"Could not read {relative_path}: {str(e)}")
        
        self.print_success(f"Checked {len(php_files)} PHP files")
    
    def verify_class_definitions(self):
        """Verify all required classes are defined"""
        self.print_header("Class Definition Verification")
        
        required_classes = {
            'WhatsAppSaasPluginPro': 'Main plugin class',
            'WSP_Database_SaaS': 'Database management',
            'WSP_Customers': 'Customer management',
            'WSP_Reports': 'Report generation',
            'WSP_Integrations': 'External integrations',
            'WSP_Activity_Log': 'Activity logging'
        }
        
        php_files = list(Path(self.plugin_dir).rglob("*.php"))
        found_classes = {}
        
        for php_file in php_files:
            try:
                with open(php_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                    
                    # Search for class definitions
                    class_pattern = r'class\s+(\w+)(?:\s+extends\s+\w+)?(?:\s+implements\s+[\w,\s]+)?\s*{'
                    matches = re.findall(class_pattern, content)
                    
                    for class_name in matches:
                        if class_name in required_classes:
                            relative_path = os.path.relpath(php_file, self.plugin_dir)
                            found_classes[class_name] = relative_path
                            
            except Exception as e:
                self.print_verbose(f"Could not read {php_file}: {str(e)}")
        
        # Report findings
        for class_name, description in required_classes.items():
            if class_name in found_classes:
                self.print_success(f"Class {class_name} found in {found_classes[class_name]} ({description})")
            else:
                self.print_warning(f"Class {class_name} not found ({description})")
    
    def verify_constants(self):
        """Verify required constants are defined"""
        self.print_header("Configuration Constants Verification")
        
        main_file = os.path.join(self.plugin_dir, 'whatsapp-saas-pro.php')
        
        if not os.path.exists(main_file):
            self.print_error("Main plugin file not found")
            return
        
        try:
            with open(main_file, 'r', encoding='utf-8') as f:
                content = f.read()
                
                for constant, expected_value in self.required_constants.items():
                    pattern = rf"define\s*\(\s*['\"]{ constant}['\"]"
                    if re.search(pattern, content):
                        self.print_success(f"Constant {constant} is defined")
                        self.print_verbose(f"Expected value: {expected_value}")
                    else:
                        self.print_error(f"Constant {constant} not defined")
                        
        except Exception as e:
            self.print_error(f"Could not read main file: {str(e)}")
    
    def verify_database_schema(self):
        """Verify database schema file exists and contains required tables"""
        self.print_header("Database Schema Verification")
        
        schema_file = os.path.join(self.plugin_dir, 'install', 'database-schema.sql')
        
        if os.path.exists(schema_file):
            self.print_success(f"Database schema file found")
            
            try:
                with open(schema_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                    
                    for table in self.required_tables:
                        if table in content:
                            self.print_verbose(f"✓ Table definition found: {table}")
                        else:
                            self.print_warning(f"Table {table} not found in schema")
                            
            except Exception as e:
                self.print_error(f"Could not read schema file: {str(e)}")
        else:
            self.print_warning("Database schema file not found")
            if self.fix_mode:
                self.create_database_schema()
    
    def verify_plugin_conflicts(self):
        """Check for potential plugin conflicts"""
        self.print_header("Plugin Conflict Check")
        
        # Check for multiple plugin headers
        php_files = list(Path(self.plugin_dir).glob("*.php"))
        plugin_headers = []
        
        for php_file in php_files:
            try:
                with open(php_file, 'r', encoding='utf-8') as f:
                    content = f.read()
                    if re.search(r'^\s*\*\s*Plugin Name:', content, re.MULTILINE):
                        plugin_headers.append(os.path.basename(php_file))
            except:
                pass
        
        if len(plugin_headers) == 0:
            self.print_error("No plugin header found")
        elif len(plugin_headers) == 1:
            self.print_success(f"Single plugin header in {plugin_headers[0]} (no conflicts)")
        else:
            self.print_error(f"Multiple plugin headers found: {', '.join(plugin_headers)}")
        
        # Check for old version files
        old_files = [
            'whatsapp-saas-plugin.php',
            'whatsapp-saas-plugin-v3.php',
            'whatsapp-saas-plugin-v4.php'
        ]
        
        for old_file in old_files:
            if os.path.exists(os.path.join(self.plugin_dir, old_file)):
                self.print_warning(f"Old version file found: {old_file}")
                if self.fix_mode:
                    os.remove(os.path.join(self.plugin_dir, old_file))
                    self.print_info(f"Removed old file: {old_file}")
    
    def verify_zip_package(self):
        """Verify ZIP package is correctly structured"""
        self.print_header("ZIP Package Verification")
        
        # Look for ZIP files
        zip_files = list(Path(os.path.dirname(self.plugin_dir)).glob("*.zip"))
        
        for zip_file in zip_files:
            if 'whatsapp-saas-pro' in zip_file.name:
                self.print_info(f"Found ZIP: {zip_file.name}")
                
                try:
                    with zipfile.ZipFile(zip_file, 'r') as zf:
                        file_list = zf.namelist()
                        
                        # Check structure
                        has_main_file = any('whatsapp-saas-pro.php' in f for f in file_list)
                        has_includes = any('includes/' in f for f in file_list)
                        has_admin = any('admin/' in f for f in file_list)
                        
                        if has_main_file and has_includes and has_admin:
                            self.print_success(f"{zip_file.name} has correct structure")
                        else:
                            self.print_warning(f"{zip_file.name} may have incomplete structure")
                        
                        self.print_verbose(f"ZIP contains {len(file_list)} files")
                        
                except Exception as e:
                    self.print_error(f"Could not read ZIP: {str(e)}")
    
    def create_stub_file(self, file_path: str, description: str):
        """Create a stub PHP file"""
        os.makedirs(os.path.dirname(file_path), exist_ok=True)
        
        # Extract class name from file path
        basename = os.path.basename(file_path).replace('.php', '')
        parts = basename.split('-')
        
        if parts[0] == 'class':
            parts = parts[1:]
            class_name = 'WSP_' + '_'.join(p.capitalize() for p in parts)
        else:
            class_name = None
        
        content = "<?php\n"
        content += f"/**\n * Stub file for {description}\n"
        content += f" * Generated: {datetime.now().isoformat()}\n */\n\n"
        content += "if (!defined('ABSPATH')) {\n    exit;\n}\n\n"
        
        if class_name:
            content += f"class {class_name} {{\n"
            content += "    // Stub implementation\n"
            content += "}\n"
        
        with open(file_path, 'w') as f:
            f.write(content)
        
        self.print_info(f"Created stub file: {os.path.basename(file_path)}")
    
    def create_database_schema(self):
        """Create database schema file"""
        schema_dir = os.path.join(self.plugin_dir, 'install')
        os.makedirs(schema_dir, exist_ok=True)
        
        schema = "-- WhatsApp SaaS Pro Database Schema\n"
        schema += f"-- Generated: {datetime.now().isoformat()}\n\n"
        
        for table in self.required_tables:
            schema += f"-- Table: {table}\n"
            schema += f"CREATE TABLE IF NOT EXISTS `{{prefix}}{table}` (\n"
            schema += "  `id` bigint(20) NOT NULL AUTO_INCREMENT,\n"
            
            # Add table-specific fields
            if table == 'wsp_customers':
                schema += "  `customer_code` varchar(50) NOT NULL,\n"
                schema += "  `business_name` varchar(255) NOT NULL,\n"
                schema += "  `email` varchar(255) NOT NULL,\n"
                schema += "  `api_key` varchar(64) DEFAULT NULL,\n"
            elif table == 'wsp_subscription_plans':
                schema += "  `plan_name` varchar(100) NOT NULL,\n"
                schema += "  `monthly_price` decimal(10,2) NOT NULL,\n"
                schema += "  `credits_included` int(11) NOT NULL,\n"
            
            schema += "  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,\n"
            schema += "  PRIMARY KEY (`id`)\n"
            schema += ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n"
        
        schema_file = os.path.join(schema_dir, 'database-schema.sql')
        with open(schema_file, 'w') as f:
            f.write(schema)
        
        self.print_info("Created database schema file")
    
    def format_size(self, size: int) -> str:
        """Format file size in human-readable format"""
        for unit in ['B', 'KB', 'MB', 'GB']:
            if size < 1024.0:
                return f"{size:.1f} {unit}"
            size /= 1024.0
        return f"{size:.1f} TB"
    
    def generate_summary(self):
        """Generate and display summary report"""
        self.print_header("Verification Summary")
        
        total_issues = len(self.issues)
        total_warnings = len(self.warnings)
        total_successes = len(self.successes)
        
        if total_issues == 0 and total_warnings == 0:
            print(f"{Colors.BOLD}{Colors.GREEN}✅ All checks passed successfully!{Colors.RESET}")
        elif total_issues == 0:
            print(f"{Colors.BOLD}{Colors.YELLOW}⚠️  Some warnings detected but no critical issues{Colors.RESET}")
        else:
            print(f"{Colors.BOLD}{Colors.RED}❌ Critical issues detected{Colors.RESET}")
        
        print(f"\nResults:")
        print(f"  • Successes: {Colors.GREEN}{total_successes}{Colors.RESET}")
        print(f"  • Warnings: {Colors.YELLOW}{total_warnings}{Colors.RESET}")
        print(f"  • Issues: {Colors.RED}{total_issues}{Colors.RESET}")
        
        if total_issues > 0:
            print(f"\n{Colors.RED}Critical Issues:{Colors.RESET}")
            for issue in self.issues[:5]:  # Show first 5 issues
                print(f"  • {issue}")
            if len(self.issues) > 5:
                print(f"  ... and {len(self.issues) - 5} more")
        
        if total_warnings > 0:
            print(f"\n{Colors.YELLOW}Warnings:{Colors.RESET}")
            for warning in self.warnings[:5]:  # Show first 5 warnings
                print(f"  • {warning}")
            if len(self.warnings) > 5:
                print(f"  ... and {len(self.warnings) - 5} more")
        
        # Save report
        self.save_report()
    
    def save_report(self):
        """Save verification report to file"""
        report_dir = os.path.join(os.path.dirname(self.plugin_dir), 'verification-reports')
        os.makedirs(report_dir, exist_ok=True)
        
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        report_file = os.path.join(report_dir, f'verification_{timestamp}.json')
        
        report = {
            'timestamp': datetime.now().isoformat(),
            'plugin_dir': self.plugin_dir,
            'summary': {
                'successes': len(self.successes),
                'warnings': len(self.warnings),
                'issues': len(self.issues)
            },
            'details': {
                'successes': self.successes,
                'warnings': self.warnings,
                'issues': self.issues
            }
        }
        
        with open(report_file, 'w') as f:
            json.dump(report, f, indent=2)
        
        self.print_info(f"Report saved to: {report_file}")
    
    def run(self):
        """Run all verification checks"""
        print(f"{Colors.BOLD}{Colors.CYAN}WhatsApp SaaS Pro - Setup Verification{Colors.RESET}")
        print(f"Plugin Directory: {self.plugin_dir}")
        print(f"Fix Mode: {'Enabled' if self.fix_mode else 'Disabled'}")
        print(f"Verbose: {'Yes' if self.verbose else 'No'}")
        
        # Run all checks
        self.verify_file_structure()
        self.verify_php_syntax()
        self.verify_class_definitions()
        self.verify_constants()
        self.verify_database_schema()
        self.verify_plugin_conflicts()
        self.verify_zip_package()
        
        # Generate summary
        self.generate_summary()
        
        return len(self.issues) == 0


def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(
        description='Verify WhatsApp SaaS Pro plugin setup'
    )
    parser.add_argument(
        '--fix',
        action='store_true',
        help='Attempt to fix issues automatically'
    )
    parser.add_argument(
        '--verbose',
        action='store_true',
        help='Show detailed output'
    )
    parser.add_argument(
        '--plugin-dir',
        help='Path to plugin directory',
        default=None
    )
    
    args = parser.parse_args()
    
    try:
        verifier = PluginVerifier(
            plugin_dir=args.plugin_dir,
            fix_mode=args.fix,
            verbose=args.verbose
        )
        
        success = verifier.run()
        sys.exit(0 if success else 1)
        
    except Exception as e:
        print(f"{Colors.RED}Error: {str(e)}{Colors.RESET}")
        sys.exit(1)


if __name__ == '__main__':
    main()