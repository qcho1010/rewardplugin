<?php
/**
 * Fired during plugin activation
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 * @author     Kyu Cho
 */
class WC_Reward_Points_Activator {

    /**
     * Database version - used for updates
     */
    const DB_VERSION = '1.0.0';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Constructor
    }

    /**
     * Activate the plugin.
     *
     * Creates necessary database tables and sets up initial options.
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;

        // Set default options
        self::set_default_options();

        // Create database tables
        self::create_tables();

        // Store database version
        update_option('wc_reward_points_db_version', self::DB_VERSION);
    }

    /**
     * Set default plugin options.
     *
     * @since    1.0.0
     */
    private static function set_default_options() {
        // Default point values
        $default_options = array(
            'signup_points' => 100,           // Default points for signup
            'referral_points_referrer' => 1000, // Default points for referrer
            'referral_points_referee' => 1000,  // Default points for referee
            'review_points' => 300,           // Default points for review
            'min_points_redeem' => 1000,      // Minimum points needed for redemption
            'points_to_currency_ratio' => 100, // How many points equal 1 unit of currency
            'conversion_rate' => 100,         // Points to currency conversion rate
            'max_redemption_percentage' => 50, // Maximum percentage of order total that can be paid with points
            'max_points_per_order' => 0,      // Maximum points per order (0 = no limit except percentage)
            'share_message' => 'Hey, join [Store Name] using my referral code [CODE] and get [POINTS] points!',
            'enabled_social_platforms' => array('facebook', 'twitter', 'whatsapp', 'email'),
            'referral_code_length' => 8,      // Length of referral codes
            'referral_code_prefix' => 'REF',  // Prefix for referral codes
            'referral_expiry_days' => 30,     // Days until referral codes expire
        );

        foreach ($default_options as $key => $value) {
            if (get_option('wc_reward_points_' . $key) === false) {
                update_option('wc_reward_points_' . $key, $value);
            }
        }
    }

    /**
     * Create necessary database tables.
     *
     * @since    1.0.0
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // SQL for creating tables
        $sql = array();

        // Table for tracking referral email associations (for cross-device tracking)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_rewards_referral_emails (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            referral_code varchar(50) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY referral_code (referral_code),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Table for tracking reward claims
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_rewards_claims (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            reward_type varchar(50) NOT NULL,
            points_awarded int(11) NOT NULL,
            date_claimed datetime DEFAULT CURRENT_TIMESTAMP,
            claim_status varchar(20) DEFAULT 'completed',
            ip_address varchar(100),
            user_agent text,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY reward_type (reward_type),
            KEY date_claimed (date_claimed)
        ) $charset_collate;";

        // Table for referral codes
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_rewards_referral_codes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            code varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            last_used datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_code (code),
            KEY user_id (user_id),
            KEY is_active (is_active),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Table for referrals
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_rewards_referrals (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            referral_code_id bigint(20) NOT NULL,
            referrer_id bigint(20) NOT NULL,
            referee_id bigint(20) NOT NULL,
            points_awarded_referrer int(11) DEFAULT 0,
            points_awarded_referee int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            ip_address varchar(100),
            user_agent text,
            PRIMARY KEY  (id),
            KEY referral_code_id (referral_code_id),
            KEY referrer_id (referrer_id),
            KEY referee_id (referee_id),
            KEY status (status),
            KEY created_at (created_at),
            UNIQUE KEY unique_referee (referee_id),
            CONSTRAINT fk_referral_code 
                FOREIGN KEY (referral_code_id) 
                REFERENCES {$wpdb->prefix}wc_rewards_referral_codes(id)
                ON DELETE CASCADE
        ) $charset_collate;";

        // Table for tracking reviews
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_rewards_reviews (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            review_platform varchar(50) NOT NULL,
            review_id varchar(100),
            points_awarded int(11) DEFAULT 0,
            date_reviewed datetime DEFAULT CURRENT_TIMESTAMP,
            verification_status varchar(20) DEFAULT 'pending',
            verification_date datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY review_platform (review_platform),
            UNIQUE KEY unique_review (user_id,review_platform,review_id)
        ) $charset_collate;";

        // Table for points log
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wc_rewards_points_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL,
            type varchar(50) NOT NULL,
            description text,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            reference_id bigint(20),
            reference_type varchar(50),
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY date_added (date_added)
        ) $charset_collate;";

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query) {
            dbDelta($query);
        }

        // Add table creation error checking
        if (!empty($wpdb->last_error)) {
            error_log('WC Reward Points Table Creation Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Check if database needs updating.
     *
     * @since    1.0.0
     * @return   boolean    True if update is needed
     */
    public static function needs_db_update() {
        $current_version = get_option('wc_reward_points_db_version', '0');
        return version_compare($current_version, self::DB_VERSION, '<');
    }
} 