<?php
/**
 * WooCommerce Reward Points Plugin
 *
 * @package           WC_Reward_Points
 * @author            Kyu Cho
 * @copyright         2025 Stealth Invest
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Reward Points
 * Plugin URI:        https://example.com/wc-reward-points
 * Description:       A comprehensive reward points system for WooCommerce with account rewards, referrals, ambassador program, and Trustpilot review rewards.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Text Domain:       wc-reward-points
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 5.0
 * WC tested up to: 7.0
 */

defined('ABSPATH') || exit;

// Define WC_REWARD_POINTS_PLUGIN_FILE.
if (!defined('WC_REWARD_POINTS_PLUGIN_FILE')) {
    define('WC_REWARD_POINTS_PLUGIN_FILE', __FILE__);
}

// Define WC_REWARD_POINTS_VERSION.
if (!defined('WC_REWARD_POINTS_VERSION')) {
    define('WC_REWARD_POINTS_VERSION', '1.0.0');
}

// Composer autoloader
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

/**
 * Check if WooCommerce is active
 */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'wc_reward_points_woocommerce_notice');
    return;
}

/**
 * WooCommerce not activated notice
 */
function wc_reward_points_woocommerce_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('WooCommerce Reward Points requires WooCommerce to be installed and activated.', 'wc-reward-points'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wc_reward_points_init() {
    // Include the main WC_Reward_Points class.
    if (!class_exists('WC_Reward_Points\\Core\\WC_Reward_Points')) {
        include_once dirname(__FILE__) . '/includes/core/class-wc-reward-points.php';
    }

    // Create an instance of the plugin
    $plugin = new WC_Reward_Points\Core\WC_Reward_Points();
    $plugin->run();
}
add_action('plugins_loaded', 'wc_reward_points_init');

/**
 * Activation hook
 */
function wc_reward_points_activate() {
    global $wpdb;
    
    // Create database tables if needed
    $charset_collate = $wpdb->get_charset_collate();
    
    // Referrals table
    $table_name = $wpdb->prefix . 'wc_reward_points_referrals';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_id bigint(20) unsigned NOT NULL,
            referee_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_total decimal(10,2) NOT NULL DEFAULT '0.00',
            points_awarded bigint(20) unsigned NOT NULL DEFAULT '0',
            date_created datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            PRIMARY KEY  (id),
            KEY referrer_id (referrer_id),
            KEY referee_id (referee_id),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Transactions table
    $table_name = $wpdb->prefix . 'wc_reward_points_transactions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            points int(11) NOT NULL,
            type varchar(50) NOT NULL,
            note text NOT NULL,
            date_created datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Review submissions tracking table
    $table_name = $wpdb->prefix . 'wc_reward_points_review_submissions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NULL,
            email varchar(100) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            review_id varchar(100) NULL,
            order_reference varchar(100) NULL,
            points_awarded int(11) NOT NULL DEFAULT '0',
            status varchar(20) NOT NULL DEFAULT 'pending',
            date_created datetime NOT NULL,
            date_verified datetime NULL,
            verification_attempts int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY email (email),
            KEY review_id (review_id),
            KEY ip_address (ip_address),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Set default options if not already set
    if (!get_option('wc_reward_points_version')) {
        add_option('wc_reward_points_version', WC_REWARD_POINTS_VERSION);
        add_option('wc_reward_points_referrer_points', 1000);
        add_option('wc_reward_points_referee_points', 1000);
        add_option('wc_reward_points_currency_ratio', 100);
    }

    // Create required pages if they don't exist
    // (Code to create pages)

    // Schedule daily cleanup task
    if (!wp_next_scheduled('wc_reward_points_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'wc_reward_points_daily_cleanup');
    }
    
    // Schedule batch review verification
    if (!wp_next_scheduled('wc_reward_points_batch_verify_reviews')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wc_reward_points_batch_verify_reviews');
    }
    
    // Set a flag to flush rewrite rules
    update_option('wc_reward_points_flush_rewrite', 'yes');
}
register_activation_hook(__FILE__, 'wc_reward_points_activate');

/**
 * Deactivation hook
 */
function wc_reward_points_deactivate() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('wc_reward_points_daily_cleanup');
    wp_clear_scheduled_hook('wc_reward_points_verify_review');
    wp_clear_scheduled_hook('wc_reward_points_monthly_performance_check');
    wp_clear_scheduled_hook('wc_reward_points_monthly_inactivity_check');
    wp_clear_scheduled_hook('wc_reward_points_batch_verify_reviews');
}
register_deactivation_hook(__FILE__, 'wc_reward_points_deactivate'); 