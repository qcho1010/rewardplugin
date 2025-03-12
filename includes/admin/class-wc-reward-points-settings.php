<?php
/**
 * Points Settings Admin
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/admin
 */

namespace WC_Reward_Points\Admin;

/**
 * Points Settings Admin
 *
 * Handles all settings related to points earning and redemption
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/admin
 * @author     Kyu Cho
 */
class WC_Reward_Points_Settings {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Add settings tab
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_reward_points', array($this, 'settings_tab'));
        add_action('woocommerce_update_options_reward_points', array($this, 'update_settings'));
        
        // Add security tab if it doesn't exist
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_security_tab'), 70);
        add_action('woocommerce_settings_tabs_reward_security', array($this, 'security_settings_tab'));
        add_action('woocommerce_update_options_reward_security', array($this, 'update_settings'));
    }

    /**
     * Add settings tab.
     *
     * @since    1.0.0
     * @param    array    $tabs    Settings tabs.
     * @return   array             Modified settings tabs.
     */
    public function add_settings_tab($tabs) {
        $tabs['reward_points'] = __('Reward Points', 'wc-reward-points');
        return $tabs;
    }

    /**
     * Add a security tab
     *
     * @param array $tabs Settings tabs
     * @return array Settings tabs
     */
    public function add_security_tab($tabs) {
        $tabs['reward_security'] = __('Reward Security', 'wc-reward-points');
        return $tabs;
    }

    /**
     * Settings tab content
     */
    public function settings_tab() {
        woocommerce_admin_fields($this->get_settings());
    }
    
    /**
     * Security settings tab content
     */
    public function security_settings_tab() {
        woocommerce_admin_fields($this->get_security_settings());
    }

    /**
     * Update settings
     */
    public function update_settings() {
        woocommerce_update_options($this->get_settings());
        woocommerce_update_options($this->get_security_settings());
    }

    /**
     * Get settings.
     *
     * @since    1.0.0
     * @return   array    Settings fields.
     */
    public function get_settings() {
        $settings = array(
            array(
                'title' => __('Reward Points Settings', 'wc-reward-points'),
                'type'  => 'title',
                'desc'  => __('Configure the reward points system.', 'wc-reward-points'),
                'id'    => 'wc_reward_points_options',
            ),
            array(
                'title'    => __('Points Currency Ratio', 'wc-reward-points'),
                'desc'     => __('Number of points equal to 1 currency unit (e.g., 100 points = $1)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_currency_ratio',
                'type'     => 'number',
                'default'  => '100',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Points Expiration', 'wc-reward-points'),
                'desc'     => __('Number of months before points expire (0 for never)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_expiration',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Maximum Points Per Order', 'wc-reward-points'),
                'desc'     => __('Maximum points a customer can earn per order (0 for unlimited)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_max_per_order',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Message on Checkout', 'wc-reward-points'),
                'desc'     => __('Message displayed on checkout showing available points', 'wc-reward-points'),
                'id'       => 'wc_reward_points_checkout_message',
                'type'     => 'textarea',
                'default'  => __('You have {points} reward points available ({amount}).', 'wc-reward-points'),
                'css'      => 'width: 400px; height: 75px;',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_reward_points_options',
            ),
        );
        
        return apply_filters('wc_reward_points_settings', $settings);
    }
    
    /**
     * Get security settings
     *
     * @return array Settings
     */
    public function get_security_settings() {
        $settings = array(
            array(
                'title' => __('Reward Points Security Settings', 'wc-reward-points'),
                'type'  => 'title',
                'desc'  => __('Configure security settings for the reward points system.', 'wc-reward-points'),
                'id'    => 'wc_reward_points_security_options',
            ),
            
            // General rate limiting settings
            array(
                'title' => __('Rate Limiting', 'wc-reward-points'),
                'type'  => 'title',
                'desc'  => __('Configure general rate limiting for reward actions.', 'wc-reward-points'),
                'id'    => 'wc_reward_points_rate_limiting',
            ),
            array(
                'title'    => __('Enable Rate Limiting', 'wc-reward-points'),
                'desc'     => __('Enable rate limiting for reward actions to prevent abuse', 'wc-reward-points'),
                'id'       => 'wc_reward_points_enable_rate_limiting',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'title'    => __('Point Redemption Limit', 'wc-reward-points'),
                'desc'     => __('Maximum number of point redemptions per day', 'wc-reward-points'),
                'id'       => 'wc_reward_points_redemption_limit',
                'type'     => 'number',
                'default'  => '5',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Referral Claim Limit', 'wc-reward-points'),
                'desc'     => __('Maximum number of referral code claims per day', 'wc-reward-points'),
                'id'       => 'wc_reward_points_referral_claim_limit',
                'type'     => 'number',
                'default'  => '3',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('API Request Limit', 'wc-reward-points'),
                'desc'     => __('Maximum number of API requests per hour', 'wc-reward-points'),
                'id'       => 'wc_reward_points_api_limit',
                'type'     => 'number',
                'default'  => '100',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Rate Limit Log Days', 'wc-reward-points'),
                'desc'     => __('Number of days to keep rate limit logs', 'wc-reward-points'),
                'id'       => 'wc_reward_points_rate_limit_log_days',
                'type'     => 'number',
                'default'  => '30',
                'css'      => 'width: 100px;',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_reward_points_rate_limiting',
            ),
            
            // Fraud prevention settings
            array(
                'title' => __('Fraud Prevention', 'wc-reward-points'),
                'type'  => 'title',
                'desc'  => __('Configure fraud prevention settings.', 'wc-reward-points'),
                'id'    => 'wc_reward_points_fraud_prevention',
            ),
            array(
                'title'    => __('Enable Fraud Detection', 'wc-reward-points'),
                'desc'     => __('Enable fraud detection for reward actions', 'wc-reward-points'),
                'id'       => 'wc_reward_points_enable_fraud_detection',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'title'    => __('IP Tracking', 'wc-reward-points'),
                'desc'     => __('Track IP addresses for reward actions to detect abuse', 'wc-reward-points'),
                'id'       => 'wc_reward_points_ip_tracking',
                'type'     => 'checkbox',
                'default'  => 'yes',
            ),
            array(
                'title'    => __('Security Log Days', 'wc-reward-points'),
                'desc'     => __('Number of days to keep security logs', 'wc-reward-points'),
                'id'       => 'wc_reward_points_security_log_days',
                'type'     => 'number',
                'default'  => '90',
                'css'      => 'width: 100px;',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'wc_reward_points_fraud_prevention',
            ),
            
            array(
                'type' => 'sectionend',
                'id'   => 'wc_reward_points_security_options',
            ),
        );

        return apply_filters('wc_reward_points_security_settings', $settings);
    }
}

new WC_Reward_Points_Settings(); 