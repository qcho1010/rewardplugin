<?php
/**
 * Trustpilot Integration Admin
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/admin
 * @since      1.0.0
 */

namespace WC_Reward_Points\Admin;

/**
 * Trustpilot Integration Admin Class
 *
 * Handles all admin-related functionality for Trustpilot integration
 */
class WC_Reward_Points_Trustpilot_Admin {

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

        // Add test connection AJAX handler
        add_action('wp_ajax_test_trustpilot_connection', array($this, 'test_connection'));
    }

    /**
     * Add Trustpilot tab to settings
     *
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function add_settings_tab($tabs) {
        $tabs['trustpilot'] = __('Trustpilot Integration', 'wc-reward-points');
        return $tabs;
    }

    /**
     * Get settings for the Trustpilot tab
     *
     * @param array  $settings Existing settings
     * @param string $current_tab Current settings tab
     * @return array Modified settings
     */
    public function get_settings($settings, $current_tab) {
        if ($current_tab !== 'trustpilot') {
            return $settings;
        }

        $settings = array(
            array(
                'title' => __('Trustpilot Integration Settings', 'wc-reward-points'),
                'type'  => 'title',
                'id'    => 'wc_reward_points_trustpilot_options',
            ),
            
            // API Credentials
            array(
                'title'    => __('API Credentials', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_trustpilot_api',
            ),
            array(
                'title'    => __('Business Unit ID', 'wc-reward-points'),
                'desc'     => __('Your Trustpilot Business Unit ID', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_business_unit_id',
                'type'     => 'text',
                'default'  => '',
                'class'    => 'regular-text',
            ),
            array(
                'title'    => __('API Key', 'wc-reward-points'),
                'desc'     => __('Your Trustpilot API Key', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_api_key',
                'type'     => 'password',
                'default'  => '',
                'class'    => 'regular-text',
            ),
            array(
                'title'    => __('API Secret', 'wc-reward-points'),
                'desc'     => __('Your Trustpilot API Secret', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_api_secret',
                'type'     => 'password',
                'default'  => '',
                'class'    => 'regular-text',
            ),

            // Reward Settings
            array(
                'title'    => __('Reward Settings', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_trustpilot_rewards',
            ),
            array(
                'title'    => __('Points per Review', 'wc-reward-points'),
                'desc'     => __('Number of points awarded for a verified review', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_review_points',
                'type'     => 'number',
                'default'  => '300',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Minimum Rating', 'wc-reward-points'),
                'desc'     => __('Minimum star rating required to earn points (1-5)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_min_rating',
                'type'     => 'number',
                'default'  => '1',
                'min'      => '1',
                'max'      => '5',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Minimum Review Length', 'wc-reward-points'),
                'desc'     => __('Minimum number of characters required in the review text', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_min_length',
                'type'     => 'number',
                'default'  => '50',
                'css'      => 'width: 100px;',
            ),

            // Security Settings
            array(
                'title'    => __('Security Settings', 'wc-reward-points'),
                'type'     => 'title',
                'id'       => 'wc_reward_points_trustpilot_security',
            ),
            array(
                'title'    => __('Verification Period', 'wc-reward-points'),
                'desc'     => __('Number of days to wait before verifying reviews (to prevent quick deletion)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_verify_days',
                'type'     => 'number',
                'default'  => '7',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Review Cooldown', 'wc-reward-points'),
                'desc'     => __('Number of days before a customer can earn points for another review (0 for one-time only)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_cooldown',
                'type'     => 'number',
                'default'  => '0',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Review Rate Limit Period', 'wc-reward-points'),
                'desc'     => __('Time period (in hours) for review submission rate limiting', 'wc-reward-points'),
                'id'       => 'wc_reward_points_review_rate_limit',
                'type'     => 'number',
                'default'  => '24',
                'min'      => '1',
                'max'      => '168', // 1 week
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('Max Review Submissions', 'wc-reward-points'),
                'desc'     => __('Maximum number of review submissions allowed in the rate limit period', 'wc-reward-points'),
                'id'       => 'wc_reward_points_review_max_submissions',
                'type'     => 'number',
                'default'  => '3',
                'min'      => '1',
                'max'      => '10',
                'css'      => 'width: 100px;',
            ),
            array(
                'title'    => __('API Timeout', 'wc-reward-points'),
                'desc'     => __('Timeout in seconds for API requests (5-30 seconds)', 'wc-reward-points'),
                'id'       => 'wc_reward_points_trustpilot_api_timeout',
                'type'     => 'number',
                'default'  => '10',
                'min'      => '5',
                'max'      => '30',
                'css'      => 'width: 100px;',
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'wc_reward_points_trustpilot_options',
            ),
        );

        return $settings;
    }

    /**
     * Save settings
     */
    public function save_settings() {
        global $current_tab;

        if ($current_tab !== 'trustpilot') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-settings')) {
            return;
        }

        $settings = $this->get_settings(array(), 'trustpilot');
        WC_Admin_Settings::save_fields($settings);
    }

    /**
     * Test Trustpilot API connection
     */
    public function test_connection() {
        // Verify nonce
        check_ajax_referer('test_trustpilot_connection', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'wc-reward-points'));
        }

        $business_unit_id = get_option('wc_reward_points_trustpilot_business_unit_id');
        $api_key = get_option('wc_reward_points_trustpilot_api_key');
        $api_secret = get_option('wc_reward_points_trustpilot_api_secret');

        if (!$business_unit_id || !$api_key || !$api_secret) {
            wp_send_json_error(__('Please enter all API credentials.', 'wc-reward-points'));
        }

        // Log connection test without exposing credentials
        if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
            \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                'Testing Trustpilot API connection',
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::INFO,
                array(
                    'business_unit_id' => $business_unit_id,
                    // Don't log the API key or secret
                )
            );
        }

        try {
            // Initialize Trustpilot API client
            $client = new \WC_Reward_Points\Integrations\Trustpilot_API(
                $business_unit_id,
                $api_key,
                $api_secret
            );

            // Test connection by fetching business unit info
            $response = $client->get_business_unit();

            if ($response && isset($response['name'])) {
                // Log successful connection without sensitive data
                if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                        sprintf(
                            'Trustpilot API connection successful for business: %s',
                            $response['name']
                        ),
                        \WC_Reward_Points\Core\WC_Reward_Points_Debug::INFO,
                        array(
                            'business_name' => $response['name'],
                            'business_url' => $response['website'] ?? '',
                        )
                    );
                }
                
                wp_send_json_success(sprintf(
                    __('Successfully connected to Trustpilot for business: %s', 'wc-reward-points'),
                    $response['name']
                ));
            } else {
                // Log failed connection without exposing credentials
                if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                        'Could not verify Trustpilot business unit',
                        \WC_Reward_Points\Core\WC_Reward_Points_Debug::ERROR
                    );
                }
                
                wp_send_json_error(__('Could not verify business unit.', 'wc-reward-points'));
            }
        } catch (\Exception $e) {
            // Log exception without sensitive data
            if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                    sprintf('Trustpilot API connection error: %s', $e->getMessage()),
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::ERROR
                );
            }
            
            wp_send_json_error($e->getMessage());
        }
    }
} 