# WhatsApp SaaS Pro - WordPress Plugin
## 📱 Overview
WhatsApp SaaS Pro is a comprehensive WordPress plugin that integrates Mail2Wa services for WhatsApp messaging. It provides a complete admin interface for managing WhatsApp numbers, sending messages, running campaigns, and tracking analytics.
## ✨ Features
### Core Functionality
- **WhatsApp Number Management**: Store and manage WhatsApp numbers with full CRUD operations
- **Message Sending**: Send individual or bulk WhatsApp messages through Mail2Wa API
- **Campaign Management**: Create and track QR code campaigns for number collection
- **Credits System**: Built-in credit management for message sending
- **Activity Logging**: Complete audit trail of all operations
- **CSV Export/Import**: Export data for analysis and backup
### Admin Pages
1. **Dashboard**: Overview statistics and quick actions
2. **Numbers**: Manage WhatsApp contacts
3. **Messages**: Send and track messages
4. **Credits**: Manage credit balance and transactions
5. **Reports**: Analytics and performance metrics
6. **Logs**: Activity and error logging
7. **Campaigns**: QR code campaign management
8. **Test**: API testing and debugging tools
## 🚀 Installation
### Method 1: Upload via WordPress Admin
1. Download the plugin ZIP file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"
### Method 2: Manual Installation
1. Extract the plugin ZIP file
2. Upload the `whatsapp-saas-pro-fixed` folder to `/wp-content/plugins/`
3. Go to WordPress Admin > Plugins
4. Find "WhatsApp SaaS Pro" and click "Activate"
### Method 3: Direct Copy
```bash
# Navigate to WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/
# Copy the plugin folder
cp -r /path/to/whatsapp-saas-pro-fixed ./
# Set proper permissions
chmod -R 755 whatsapp-saas-pro-fixed
```
## 🔧 Configuration
### Initial Setup
Upon activation, the plugin will:
1. Create necessary database tables
2. Insert sample data for testing
3. Set default configuration values
4. Add 100 free credits to your account
### Mail2Wa API Configuration
The plugin comes pre-configured with default Mail2Wa settings:
- **API Key**: `1f06d5c8bd0cd19f7c99b660b504bb25`
- **Base URL**: `https://mail2wa.gatway.cloud`
- **Auth Method**: Query parameter
To modify these settings, go to the plugin settings page.
## 📝 Database Tables
The plugin creates the following tables:
- `wp_wsp_whatsapp_numbers` - Stores WhatsApp contact information
- `wp_wsp_messages` - Message history and delivery status
- `wp_wsp_campaigns` - Campaign definitions and statistics
- `wp_wsp_credits_transactions` - Credit usage tracking
- `wp_wsp_activity_log` - System activity logging
## 🧪 Testing
### API Test
1. Go to WhatsApp SaaS > Test in WordPress admin
2. Click "Test API Connection" to verify Mail2Wa connectivity
3. Use "Test Extraction" to verify data processing
### Database Fix Script
If you encounter database issues, run the included fix script:
```bash
cd /path/to/wordpress/wp-content/plugins/whatsapp-saas-pro-fixed
php fix-database.php
```
## 📊 Sample Data
The plugin includes sample data for testing:
- 5 sample WhatsApp numbers
- 3 test messages
- 2 QR campaigns
- Transaction logs
To remove sample data, deactivate and reactivate the plugin with a clean installation.
## 🔌 REST API Endpoints
The plugin provides REST API endpoints for external integration:
### Webhook Endpoint
```
POST /wp-json/wsp/v1/webhook
Headers: X-API-Key: your-api-key
```
### Send Message
```
POST /wp-json/wsp/v1/send
Headers: X-API-Key: your-api-key
Body: {
  "phone": "+1234567890",
  "message": "Your message here"
}
```
### Get Statistics
```
GET /wp-json/wsp/v1/stats
Headers: X-API-Key: your-api-key
```
## 🎨 Shortcodes
### Display QR Campaign
```
[wsp_qr_campaign campaign_id="campaign_101" size="250" color="#000000"]
```
### Test Form
```
[wsp_test_form]
```
## 🛠️ Troubleshooting
### Common Issues
#### 1. "Unknown column 'campaign_id'" Error
**Soluzione Rapida:**
1. Vai a **WhatsApp SaaS > Impostazioni**
2. Clicca sul pulsante **"🔧 Fix Completo Database"**
3. Ricarica la pagina
**Soluzione Alternativa via Script:**
```bash
cd /path/to/wordpress/wp-content/plugins/whatsapp-saas-pro-fixed
php emergency-fix.php
```
#### 2. API Connection Failed
- Verify your Mail2Wa API key is valid
- Check if your server can make outbound HTTPS requests
- Review firewall settings
#### 3. Numbers Not Displaying
- Clear browser cache
- Check database permissions
- Run the fix-database.php script
#### 4. Credits Not Updating
- Verify wp_options table has write permissions
- Check for caching plugins interfering
## 📦 File Structure
```
whatsapp-saas-pro-fixed/
├── admin/
│   └── class-wsp-admin.php         # Admin interface
├── includes/
│   ├── class-wsp-api.php           # REST API handlers
│   ├── class-wsp-campaigns.php     # Campaign management
│   ├── class-wsp-credits.php       # Credits system
│   ├── class-wsp-database.php      # Database operations
│   ├── class-wsp-gmail.php         # Gmail integration
│   ├── class-wsp-mail2wa.php       # Mail2Wa API client
│   ├── class-wsp-messages.php      # Message handling
│   ├── class-wsp-migration.php     # Database migrations
│   ├── class-wsp-sample-data.php   # Sample data insertion
│   ├── class-wsp-settings.php      # Plugin settings
│   └── class-wsp-test.php          # Testing utilities
├── assets/
│   ├── css/                        # Stylesheets
│   └── js/                          # JavaScript files
├── languages/                       # Translation files
├── whatsapp-saas-plugin.php        # Main plugin file
├── fix-database.php                 # Database repair utility
└── README.md                        # This file
```
## 🔒 Security
The plugin implements several security measures:
- Nonce verification for all AJAX requests
- Capability checks for admin operations
- Data sanitization and validation
- SQL injection prevention using WordPress $wpdb
- XSS protection through proper escaping
## 📄 License
This plugin is provided as-is for use with WordPress installations. Please review the license terms before deployment.
## 🤝 Support
For issues, questions, or feature requests:
1. Check the Troubleshooting section
2. Review the test page for API connectivity
3. Run the database fix script if needed
4. Check WordPress debug logs for detailed error information
## 🔄 Updates
### Version 3.0.0 (Current)
- Complete admin interface implementation
- Mail2Wa API integration
- Sample data system
- Database structure fixes
- CSV export functionality
- Enhanced error handling
## 🚦 Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher
- Active Mail2Wa account (for production use)
- SSL certificate (recommended for API calls)
## ⚡ Quick Start
1. Install and activate the plugin
2. Go to WhatsApp SaaS > Dashboard
3. Test API connection in the Test page
4. Add WhatsApp numbers manually or via import
5. Send your first message from the Messages page
6. Monitor activity in Reports and Logs
---
**Note**: This plugin uses the Mail2Wa service for WhatsApp messaging. Ensure you comply with WhatsApp's terms of service and messaging policies.
--------------------------------------------------------------------------------