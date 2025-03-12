# WooCommerce Reward Points Plugin

A powerful WooCommerce plugin that implements a comprehensive reward points system through three distinct URLs. Perfect for businesses looking to increase customer engagement and loyalty through a points-based reward system.

## 🌟 Features

- **Account Rewards**: 
  - Award points when customers create new accounts
  - Existing customers can claim rewards through signup URL
  - Configurable cooldown period for repeat claims
  - Admin-controlled reward frequency
- **Social Sharing & Referral System**: 
  - Reward both referrers and new customers
  - Brand Ambassador Program:
    - Special ambassador referral URLs
    - 6% cashback in points on referred purchases (100 points = $1)
    - Lifetime commission on ambassador's referrals
    - Real-time tracking of ambassador earnings
    - Ambassador performance dashboard
- **Trustpilot Review Rewards**: Incentivize customer reviews with points
- **Admin Dashboard**: Easy-to-use interface for managing reward points
- **Security System**: Built-in protection against abuse and duplicate rewards
- **Flexible Integration**: Works with major WooCommerce points systems

## 📋 Todo List

The following items are planned for future development:

1. **Performance Optimization**
   - Audit database queries for efficiency
   - Implement caching for frequently accessed data
   - Optimize API calls to Trustpilot

2. **User Experience Enhancements**
   - Create a unified dashboard showing all rewards in one place
   - Add progress indicators toward reward milestones
   - Improve mobile responsiveness of all interfaces

3. **Additional Integrations**
   - Google Reviews integration (similar to Trustpilot)
   - Integration with email marketing platforms
   - Support for additional social media platforms

4. **Analytics & Reporting**
   - Create an admin dashboard with reward program statistics
   - Export capabilities for reward data
   - ROI calculation tools for measuring program effectiveness

5. **Documentation & Help**
   - Create in-app contextual help
   - Video tutorials for store owners
   - End-user documentation explaining the rewards system

## 🚀 Quick Start

### Installation

#### Option 1: Direct Download
1. Download the latest release from [GitHub Releases](https://github.com/qhco1010/rewardplugin/releases)
2. In WordPress Admin, go to `Plugins > Add New > Upload Plugin`
3. Upload the downloaded `wc-reward-points.zip`
4. Click "Install Now" and then "Activate"

#### Option 2: Development Setup
```bash
# Clone the repository
git clone https://github.com/qhco1010/rewardplugin.git

# Navigate to the plugin directory
cd rewardplugin

# Install dependencies
composer install
```

## Plugin Packaging and Installation

### Creating a WordPress Plugin Zip File

To create a proper zip file for installing the plugin through the WordPress admin interface:

1. **Clone the repository**:
   ```bash
   git clone https://github.com/qcho1010/rewardplugin.git
   cd rewardplugin
   ```

2. **Install production dependencies**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Remove development files**:
   ```bash
   # Remove development-only files and directories
   rm -rf .git .github .gitignore tests phpunit.xml phpunit.xml.dist
   ```

4. **Create the zip file**:
   ```bash
   # Navigate to parent directory
   cd ..
   # Create zip file with the plugin directory
   zip -r wc-reward-points.zip rewardplugin -x "rewardplugin/.*" -x "rewardplugin/bin/*" -x "rewardplugin/docs/*"
   ```

5. **Verify the zip structure**:
   The zip file should contain all files inside the 'rewardplugin' directory. When extracted, it should maintain this directory structure.

### Installing the Plugin

1. **Via WordPress Admin Panel**:
   - Log in to your WordPress admin area
   - Navigate to Plugins > Add New
   - Click the "Upload Plugin" button
   - Choose the zip file you created (wc-reward-points.zip)
   - Click "Install Now"
   - Activate the plugin after installation

2. **Via FTP/SFTP**:
   - Extract the zip file locally
   - Upload the 'rewardplugin' directory to `/wp-content/plugins/` directory
   - Activate the plugin through the WordPress admin interface

### Requirements

- WordPress 5.6 or higher
- WooCommerce 5.0 or higher
- PHP 7.2 or higher

### Troubleshooting Installation

If you encounter issues during installation:

1. **Zip File Structure**: Ensure the zip file contains a single top-level directory "rewardplugin" with all plugin files inside
2. **File Permissions**: Ensure files have appropriate permissions (typically 644 for files, 755 for directories)
3. **PHP Version**: Verify server meets the minimum PHP 7.2 requirement
4. **WooCommerce**: Confirm WooCommerce is installed and activated
5. **Error Logs**: Check WordPress error logs for any specific errors

## ⚙️ Configuration

### Basic Setup

1. Navigate to `WooCommerce > Reward Points > Settings`
2. Configure point values for each action:
   ```
   Account Creation/Signup: X points (default)
   Signup Reward Cooldown: 30 days (configurable)
   Referral: 1000 points (both referrer and referee)
   Trustpilot Review: 300 points
   ```
3. Save your settings

### Reward URLs

The plugin creates three special URLs for different reward actions:

1. **Account Creation/Signup URL**
   - Format: `https://your-site.com/rewards/signup`
   - Usage: Share this URL to reward account creation or signup
   
   **Functionality:**
   - When a customer clicks the URL:
     - New customers receive X points upon account creation
     - Existing customers can claim X points if they haven't claimed before
     - After the cooldown period (set by admin), customers can claim again
   
   **Restrictions:**
   - Claims are tracked with timestamps
   - Multiple claims allowed after cooldown period
   - Configurable cooldown period (e.g., 30 days, 60 days, etc.)
   - Anti-abuse measures prevent rapid repeat claims

2. **Referral URL**
   - Format: `https://your-site.com/rewards/refer/{user_referral_code}`
   
   **Functionality:**
   - Regular Referral:
     - Clicking the URL opens a popup with social media share buttons
     - Customer can select share method (text message, Facebook, etc.)
     - Pre-written message displayed
     - When a referral clicks the shared URL and creates an account:
       - New account receives **1000 reward points** (adjustable via admin panel)
       - Referring customer also receives **1000 reward points**
   
   - Brand Ambassador Program:
     - Customers can apply to become brand ambassadors
     - Approved ambassadors get a special ambassador URL
     - Ambassadors earn 6% of referred purchase amounts in points
     - Points conversion: 100 points = $1
     - Example: $100 purchase = 600 points ($6) for ambassador
     - Lifetime earnings from referred customers
     - Real-time tracking and reporting
     - Minimum payout thresholds configurable
     - Performance-based tier system available
   
   **Restrictions:**
   - Multiple unique referrals are allowed
   - Same person cannot be referred multiple times
   - Ambassador status requires admin approval
   - Minimum purchase amounts can be set
   - Anti-fraud measures in place

3. **Trustpilot Review URL**
   - Format: `https://your-site.com/rewards/review`
   
   **Functionality:**
   - Customer clicks URL and leaves a Trustpilot review
   - Upon verification, **300 reward points** are granted (adjustable via admin panel)
   
   **Restrictions:**
   - One-time reward per customer
   - Points only granted after review verification

### Social Sharing Setup

1. Go to `WooCommerce > Reward Points > Social Sharing`
2. Configure:
   - Share message template
   - Enabled social platforms
   - Button styling
   - Referral reward amounts

### Trustpilot Integration

1. Go to `WooCommerce > Reward Points > Trustpilot`
2. Enter your Trustpilot API credentials:
   ```
   Business Unit ID
   API Key
   Secret Key
   ```
3. Test the connection
4. Save settings

## 🛠️ Development

### Directory Structure
```
rewardplugin/
├── includes/
│   ├── admin/         # Admin-related classes
│   ├── public/        # Public-facing classes
│   └── core/          # Core plugin classes
├── assets/
│   ├── css/          # Stylesheets
│   ├── js/           # JavaScript files
│   └── images/       # Images
├── languages/        # Translation files
└── vendor/          # Composer dependencies
```

### Development Commands
```bash
# Install development dependencies
composer install --dev

# Run tests
composer test

# Check coding standards
composer phpcs

# Fix coding standards
composer phpcbf
```

### Available Hooks

```php
// Modify signup reward points
add_filter('wc_rewards_signup_points', 'custom_signup_points', 10, 2);

// Modify referral reward points
add_filter('wc_rewards_referral_points', 'custom_referral_points', 10, 3);

// Modify review reward points
add_filter('wc_rewards_review_points', 'custom_review_points', 10, 2);

// After points are awarded
add_action('wc_rewards_points_granted', 'after_points_granted', 10, 3);

// Customize share message
add_filter('wc_rewards_share_message', 'custom_share_message', 10, 2);
```

## 🔒 Security Features

- Double-dipping prevention
- IP logging and rate limiting
- Suspicious pattern detection
- CAPTCHA integration
- Secure cookie handling
- Input sanitization
- Nonce verification

## 🐛 Troubleshooting

### Common Issues

1. **Points Not Awarded**
   - Check WooCommerce activation
   - Verify points system configuration
   - Check user eligibility

2. **URLs Not Working**
   - Ensure pretty permalinks are enabled
   - Flush permalink rules
   - Check server URL rewrite configuration

3. **Trustpilot Integration Issues**
   - Verify API credentials
   - Check API access permissions
   - Review error logs

4. **Social Share Problems**
   - Check popup blocker settings
   - Verify social platform configurations
   - Test share message template

## 📝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 👥 Support

- Create an issue on [GitHub](https://github.com/qhco1010/rewardplugin/issues)
- Check our [Documentation](https://github.com/qhco1010/rewardplugin/wiki)

## 🙏 Credits

Developed by Kyu Cho for Stealth Invest - © 2025

## Testing

### Setting Up the Test Environment

1. Install WordPress test environment:
```bash
./bin/install-wp-tests.sh wordpress_test root root localhost latest
```

2. Install PHP dependencies:
```bash
composer install
```

### Running Tests

Run all tests:
```bash
composer test
```