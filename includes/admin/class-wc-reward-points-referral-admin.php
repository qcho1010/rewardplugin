<?php
/**
 * Referral Rewards Admin
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/admin
 * @since      1.0.0
 */

namespace WC_Reward_Points\Admin;

/**
 * Referral Rewards Admin Class
 *
 * Handles all admin-related functionality for referral rewards
 */
class WC_Reward_Points_Referral_Admin {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Add settings tab
        add_filter('wc_reward_points_settings_tabs', array($this, 'add_settings_tab'));
        
        // Add settings
        add_filter('wc_reward_points_get_settings', array($this, 'get_settings'), 10, 2);
        
        // Save settings
        add_action('wc_reward_points_save_settings', array($this, 'save_settings'));

        // Add ambassador management page
        add_action('admin_menu', array($this, 'add_ambassador_menu'));

        // Handle ambassador approval/rejection
        add_action('admin_post_approve_ambassador', array($this, 'handle_ambassador_approval'));
        add_action('admin_post_reject_ambassador', array($this, 'handle_ambassador_rejection'));
    }

    /**
     * Add Referral tab to settings
     *
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function add_settings_tab($tabs) {
        $tabs['referral'] = __('Referral Rewards', 'wc-reward-points');
        return $tabs;
    }

    /**
     * Add ambassador management menu
     */
    public function add_ambassador_menu() {
        add_submenu_page(
            'woocommerce',
            __('Brand Ambassadors', 'wc-reward-points'),
            __('Brand Ambassadors', 'wc-reward-points'),
            'manage_woocommerce',
            'wc-brand-ambassadors',
            array($this, 'render_ambassador_page')
        );
    }

    /**
     * Get settings for the referral tab
     *
     * @param array  $settings Existing settings
     * @param string $current_tab Current settings tab
     * @return array Modified settings
     */
    public function get_settings($settings, $current_tab) {
        if ($current_tab !== 'referral') {
            return $settings;
        }

        $settings = array(
            array(
                'title' => __('Referral Reward Settings', 'wc-reward-points'),
                'type'  => 'title',
                'id'    => 'wc_reward_points_referral_options',
            ),
            
            // Regular Referral Points Settings
            array(
                'title'    => __('Regular Referral Settings', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_regular_referral',
            ),
            array(
                'title'    => __('Referrer Points', 'wc-reward-points'),
                'desc'     => __('Points awarded to the customer who refers someone', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referrer_points',
                'type'     => 'number',
                'default'  => '1000',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Referee Points', 'wc-reward-points'),
                'desc'     => __('Points awarded to the new customer who was referred', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referee_points',
                'type'     => 'number',
                'default'  => '1000',
                'css'      => 'width: 100px;',
            ),

            // Brand Ambassador Settings
            array(
                'title'    => __('Brand Ambassador Settings', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_ambassador',
            ),
            array(
                'title'    => __('Enable Brand Ambassadors', 'wc-reward-points'),
                'desc'     => __('Allow customers to apply for brand ambassador status', 'wc-reward-points'),
                'id'       => 'wc_reward_points_enable_ambassadors',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'title'    => __('Commission Rate', 'wc-reward-points'),
                'desc'     => __('Percentage of purchase amount converted to points (e.g., 6 for 6%)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_commission',
                'type'     => 'number',
                'default'  => '6',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Tax Handling', 'wc-reward-points'),
                'desc'     => __('How to handle taxes when calculating ambassador commissions', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_tax_handling',
                'type'     => 'select',
                'options'  => array(
                    'include'  => __('Include tax in commission calculation', 'wc-reward-points'),
                    'exclude'  => __('Exclude tax from commission calculation', 'wc-reward-points'),
                    'subtotal' => __('Use subtotal only (no tax, shipping, or fees)', 'wc-reward-points'),
                ),
                'default'  => 'include',
                'css'      => 'width: 400px;',
            ),
            array(
                'title'    => __('Points to Currency Ratio', 'wc-reward-points'),
                'desc'     => __('Number of points equal to 1 currency unit (e.g., 100 points = $1)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_currency_ratio',
                'type'     => 'number',
                'default'  => '100',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Minimum Purchase Amount', 'wc-reward-points'),
                'desc'     => __('Minimum order amount to qualify for ambassador commission', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_min_purchase',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Minimum Payout Threshold', 'wc-reward-points'),
                'desc'     => __('Minimum points required before ambassador can redeem (0 for no minimum)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_min_payout',
                'type'     => 'number',
                'default'  => '5000',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Performance Bonus Threshold', 'wc-reward-points'),
                'desc'     => __('Monthly sales amount that triggers a performance bonus ($)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_bonus_threshold',
                'type'     => 'number',
                'default'  => '1000',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Performance Bonus Amount', 'wc-reward-points'),
                'desc'     => __('Points awarded as a bonus for high performing ambassadors', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_bonus_amount',
                'type'     => 'number',
                'default'  => '1000',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Inactivity Penalty Threshold', 'wc-reward-points'),
                'desc'     => __('Number of months of inactivity before applying a penalty (0 to disable)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_penalty_threshold',
                'type'     => 'number',
                'default'  => '3',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Inactivity Penalty', 'wc-reward-points'),
                'desc'     => __('Percentage of points deducted for inactivity (e.g., 10 for 10%)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_penalty_percent',
                'type'     => 'number',
                'default'  => '10',
                'css'      => 'width: 100px;',
            ),

            // Social Sharing Settings
            array(
                'title'    => __('Social Sharing', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_social_options',
            ),
            array(
                'title'    => __('Enable Facebook', 'wc-reward-points'),
                'desc'     => __('Allow sharing on Facebook', 'wc-reward-points'),
                'id'       => 'wc_reward_points_enable_facebook',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'title'    => __('Enable Twitter', 'wc-reward-points'),
                'desc'     => __('Allow sharing on Twitter', 'wc-reward-points'),
                'id'       => 'wc_reward_points_enable_twitter',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'title'    => __('Enable WhatsApp', 'wc-reward-points'),
                'desc'     => __('Allow sharing on WhatsApp', 'wc-reward-points'),
                'id'       => 'wc_reward_points_enable_whatsapp',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),

            // Message Templates
            array(
                'title'    => __('Message Templates', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_message_options',
            ),
            array(
                'title'    => __('Regular Share Message', 'wc-reward-points'),
                'desc'     => __('Message template for regular referral sharing. Use {store_name} for store name, {points} for points amount', 'wc-reward-points'),
                'id'       => 'wc_reward_points_share_message',
                'type'     => 'textarea',
                'default'  => __('Hey! Shop at {store_name} using my referral link and get {points} reward points!', 'wc-reward-points'),
                'css'      => 'width: 400px; height: 100px;',
            ),
            array(
                'title'    => __('Ambassador Share Message', 'wc-reward-points'),
                'desc'     => __('Message template for ambassador sharing. Use {store_name} for store name, {discount} for equivalent discount amount', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ambassador_message',
                'type'     => 'textarea',
                'default'  => __('Shop at {store_name} using my ambassador link and earn reward points worth ${discount} on your purchase!', 'wc-reward-points'),
                'css'      => 'width: 400px; height: 100px;',
            ),
            array(
                'title'    => __('Success Message', 'wc-reward-points'),
                'desc'     => __('Message shown when referral is successful', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referral_success',
                'type'     => 'textarea',
                'default'  => __('Thanks for using a referral link! {points} points have been added to your account.', 'wc-reward-points'),
                'css'      => 'width: 400px; height: 100px;',
            ),

            // Security Settings
            array(
                'title'    => __('Security Settings', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_referral_security',
            ),
            array(
                'title'    => __('Minimum Order Amount', 'wc-reward-points'),
                'desc'     => __('Minimum order amount required for referee to earn points (0 for no minimum)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referral_min_order',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Referral Limit', 'wc-reward-points'),
                'desc'     => __('Maximum number of successful referrals per month (0 for unlimited)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referral_limit',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Referral Code Expiration', 'wc-reward-points'),
                'desc'     => __('Days before referral codes expire (0 for never)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referral_expiration',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Reset Expired Codes', 'wc-reward-points'),
                'desc'     => __('When referral codes expire, generate new ones automatically', 'wc-reward-points'),
                'id'       => 'wc_reward_points_reset_expired_codes',
                'type'     => 'checkbox',
                'default'  => 'no',
            ),
            array(
                'title'    => __('Application Rate Limiting', 'wc-reward-points'),
                'desc'     => __('Days between ambassador applications (0 for no limit)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_application_cooldown',
                'type'     => 'number',
                'default'  => '30',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Shorten Referral URLs', 'wc-reward-points'),
                'desc'     => __('Generate shorter, more shareable referral URLs', 'wc-reward-points'),
                'id'       => 'wc_reward_points_short_urls',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'wc_reward_points_referral_options',
            ),
        );

        return $settings;
    }

    /**
     * Save settings
     */
    public function save_settings() {
        global $current_tab;

        if ($current_tab !== 'referral') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-settings')) {
            return;
        }

        $old_short_urls = get_option('wc_reward_points_short_urls', 'yes');
        
        // Save settings
        $settings = $this->get_settings(array(), 'referral');
        WC_Admin_Settings::save_fields($settings);
        
        // Check if short URLs setting has changed
        $new_short_urls = get_option('wc_reward_points_short_urls', 'yes');
        if ($old_short_urls !== $new_short_urls) {
            // Flush rewrite rules
            flush_rewrite_rules();
        }
    }

    /**
     * Render ambassador management page
     */
    public function render_ambassador_page() {
        // Get ambassador applications
        $applications = $this->get_ambassador_applications();
        
        // Get active ambassadors
        $ambassadors = $this->get_active_ambassadors();

        include WC_REWARD_POINTS_PLUGIN_DIR . 'templates/admin/ambassador-management.php';
    }

    /**
     * Get ambassador applications
     *
     * @return array Array of user objects with application data
     */
    private function get_ambassador_applications() {
        $args = array(
            'meta_key'    => '_wc_ambassador_application',
            'meta_value'  => 'pending',
            'number'      => -1,
            'fields'      => 'all',
        );
        return get_users($args);
    }

    /**
     * Get active ambassadors
     *
     * @return array Array of user objects with ambassador data
     */
    private function get_active_ambassadors() {
        $args = array(
            'meta_key'    => '_wc_ambassador_status',
            'meta_value'  => 'active',
            'number'      => -1,
            'fields'      => 'all',
        );
        return get_users($args);
    }

    /**
     * Handle ambassador approval
     */
    public function handle_ambassador_approval() {
        // Verify nonce and permissions
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'approve_ambassador') || !current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized action', 'wc-reward-points'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_die(__('Invalid user', 'wc-reward-points'));
        }

        // Update user meta
        delete_user_meta($user_id, '_wc_ambassador_application');
        update_user_meta($user_id, '_wc_ambassador_status', 'active');
        update_user_meta($user_id, '_wc_ambassador_approved_date', current_time('mysql'));

        // Generate unique ambassador code
        $code = $this->generate_ambassador_code($user_id);
        update_user_meta($user_id, '_wc_ambassador_code', $code);

        // Redirect back with success message
        wp_redirect(add_query_arg('approved', '1', wp_get_referer()));
        exit;
    }

    /**
     * Handle ambassador rejection
     */
    public function handle_ambassador_rejection() {
        // Verify nonce and permissions
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'reject_ambassador') || !current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized action', 'wc-reward-points'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_die(__('Invalid user', 'wc-reward-points'));
        }

        // Update user meta
        delete_user_meta($user_id, '_wc_ambassador_application');
        update_user_meta($user_id, '_wc_ambassador_status', 'rejected');
        update_user_meta($user_id, '_wc_ambassador_rejected_date', current_time('mysql'));

        // Redirect back with success message
        wp_redirect(add_query_arg('rejected', '1', wp_get_referer()));
        exit;
    }

    /**
     * Generate unique ambassador code
     *
     * @param int $user_id User ID
     * @return string Unique ambassador code
     */
    private function generate_ambassador_code($user_id) {
        $prefix = 'AMB';
        $user = get_user_by('id', $user_id);
        $username = $user ? sanitize_title($user->user_login) : '';
        $unique = substr(uniqid(), -4);
        return strtoupper($prefix . '_' . $username . '_' . $unique);
    }
} 