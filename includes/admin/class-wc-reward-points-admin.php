<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/admin
 */

namespace WC_Reward_Points\Admin;

use WC_Reward_Points\Core\WC_Reward_Points_Points_Manager;
use WC_Reward_Points\Core\WC_Reward_Points_Referral_Manager;
use WC_Reward_Points\Core\WC_Reward_Points_Debug;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area.
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/admin
 * @author     Kyu Cho
 */
class WC_Reward_Points_Admin {

    /**
     * The points manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Points_Manager    $points_manager    The points manager instance.
     */
    private $points_manager;

    /**
     * The referral manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Referral_Manager    $referral_manager    The referral manager instance.
     */
    private $referral_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->points_manager = new WC_Reward_Points_Points_Manager();
        $this->referral_manager = new WC_Reward_Points_Referral_Manager();

        // Add menu items
        add_action('admin_menu', array($this, 'add_menu_items'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_get_reward_stats', array($this, 'get_reward_stats'));
        add_action('wp_ajax_get_referral_list', array($this, 'get_referral_list'));
        add_action('wp_ajax_get_points_history', array($this, 'get_points_history'));
    }

    /**
     * Register the admin menu items.
     *
     * @since    1.0.0
     */
    public function add_menu_items() {
        add_submenu_page(
            'woocommerce',
            __('Reward Points', 'wc-reward-points'),
            __('Reward Points', 'wc-reward-points'),
            'manage_woocommerce',
            'wc-reward-points',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'woocommerce',
            __('Reward Settings', 'wc-reward-points'),
            __('Reward Settings', 'wc-reward-points'),
            'manage_woocommerce',
            'wc-reward-points-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // General Settings
        register_setting('wc_reward_points_general', 'wc_reward_points_signup_points');
        register_setting('wc_reward_points_general', 'wc_reward_points_signup_cooldown');
        register_setting('wc_reward_points_general', 'wc_reward_points_referral_points_referrer');
        register_setting('wc_reward_points_general', 'wc_reward_points_referral_points_referee');
        register_setting('wc_reward_points_general', 'wc_reward_points_review_points');

        // Social Sharing Settings
        register_setting('wc_reward_points_social', 'wc_reward_points_share_message');
        register_setting('wc_reward_points_social', 'wc_reward_points_enabled_social_platforms');

        // Trustpilot Settings
        register_setting('wc_reward_points_trustpilot', 'wc_reward_points_trustpilot_business_id');
        register_setting('wc_reward_points_trustpilot', 'wc_reward_points_trustpilot_api_key');
        register_setting('wc_reward_points_trustpilot', 'wc_reward_points_trustpilot_secret_key');

        // Security Settings
        register_setting('wc_reward_points_security', 'wc_reward_points_rate_limit');
        register_setting('wc_reward_points_security', 'wc_reward_points_captcha_enabled');
        register_setting('wc_reward_points_security', 'wc_reward_points_ip_tracking');
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since    1.0.0
     * @param    string    $hook    The current admin page.
     */
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('woocommerce_page_wc-reward-points', 'woocommerce_page_wc-reward-points-settings'))) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'wc-reward-points-admin',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );

        // Scripts
        wp_enqueue_script(
            'wc-reward-points-admin',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            '1.0.0',
            true
        );

        // Localize script
        wp_localize_script('wc-reward-points-admin', 'wcRewardPointsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_reward_points_admin'),
            'i18n' => array(
                'noResults' => __('No results found.', 'wc-reward-points'),
                'error' => __('An error occurred. Please try again.', 'wc-reward-points'),
                'success' => __('Changes saved successfully.', 'wc-reward-points'),
                'confirm' => __('Are you sure?', 'wc-reward-points')
            )
        ));
    }

    /**
     * Render the dashboard page.
     *
     * @since    1.0.0
     */
    public function render_dashboard_page() {
        include plugin_dir_path(dirname(__FILE__)) . 'admin/partials/dashboard.php';
    }

    /**
     * Render the settings page.
     *
     * @since    1.0.0
     */
    public function render_settings_page() {
        include plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings.php';
    }

    /**
     * Get reward statistics via AJAX.
     *
     * @since    1.0.0
     */
    public function get_reward_stats() {
        check_ajax_referer('wc_reward_points_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-reward-points'));
        }

        global $wpdb;

        // Get total points awarded
        $total_points = $wpdb->get_var(
            "SELECT SUM(points) FROM {$wpdb->prefix}wc_rewards_points_log"
        );

        // Get total referrals
        $total_referrals = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_referrals WHERE status = 'completed'"
        );

        // Get total reviews
        $total_reviews = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta WHERE meta_key = 'wc_reward_points_review_claimed'"
        );

        // Get recent activity
        $recent_activity = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wc_rewards_points_log 
            ORDER BY created_at DESC 
            LIMIT 10"
        );

        wp_send_json_success(array(
            'total_points' => (int)$total_points,
            'total_referrals' => (int)$total_referrals,
            'total_reviews' => (int)$total_reviews,
            'recent_activity' => $recent_activity
        ));
    }

    /**
     * Get referral list via AJAX.
     *
     * @since    1.0.0
     */
    public function get_referral_list() {
        check_ajax_referer('wc_reward_points_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-reward-points'));
        }

        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        global $wpdb;

        // Build query
        $sql = "SELECT r.*, 
                       u1.display_name as referrer_name,
                       u2.display_name as referee_name
                FROM {$wpdb->prefix}wc_rewards_referrals r
                LEFT JOIN {$wpdb->users} u1 ON r.referrer_id = u1.ID
                LEFT JOIN {$wpdb->users} u2 ON r.referee_id = u2.ID";

        if ($search) {
            $sql .= $wpdb->prepare(
                " WHERE u1.display_name LIKE %s OR u2.display_name LIKE %s",
                "%{$search}%",
                "%{$search}%"
            );
        }

        $sql .= " ORDER BY r.created_at DESC";
        $sql .= " LIMIT %d OFFSET %d";

        $referrals = $wpdb->get_results($wpdb->prepare(
            $sql,
            $per_page,
            ($page - 1) * $per_page
        ));

        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_referrals");

        wp_send_json_success(array(
            'referrals' => $referrals,
            'total' => (int)$total,
            'pages' => ceil($total / $per_page)
        ));
    }

    /**
     * Get points history via AJAX.
     *
     * @since    1.0.0
     */
    public function get_points_history() {
        check_ajax_referer('wc_reward_points_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-reward-points'));
        }

        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $per_page = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        global $wpdb;

        // Build query
        $sql = "SELECT l.*, u.display_name as user_name
                FROM {$wpdb->prefix}wc_rewards_points_log l
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID";

        $where = array();
        $params = array();

        if ($user_id) {
            $where[] = "l.user_id = %d";
            $params[] = $user_id;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY l.created_at DESC";
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = ($page - 1) * $per_page;

        $history = $wpdb->get_results($wpdb->prepare(
            $sql,
            $params
        ));

        // Get total count
        $total_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_points_log l";
        if (!empty($where)) {
            $total_sql .= " WHERE " . implode(" AND ", $where);
        }
        $total = $wpdb->get_var($wpdb->prepare($total_sql, array_slice($params, 0, -2)));

        wp_send_json_success(array(
            'history' => $history,
            'total' => (int)$total,
            'pages' => ceil($total / $per_page)
        ));
    }
} 