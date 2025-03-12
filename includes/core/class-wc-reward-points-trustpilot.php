<?php
/**
 * Trustpilot Review Handler
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

use WP_Error;

/**
 * Handles Trustpilot review verification and reward points
 */
class WC_Reward_Points_Trustpilot {

    /**
     * Points manager instance
     *
     * @var WC_Reward_Points_Manager
     */
    private $points_manager;

    /**
     * Debug logger instance
     *
     * @var WC_Reward_Points_Debug
     */
    private $logger;

    /**
     * Trustpilot API credentials
     *
     * @var array
     */
    private $api_credentials;

    /**
     * Constructor
     */
    public function __construct() {
        $this->points_manager = WC_Reward_Points_Manager::instance();
        $this->logger = new WC_Reward_Points_Debug();
        $this->api_credentials = $this->get_api_credentials();

        // Register review endpoint
        add_action('rest_api_init', array($this, 'register_review_endpoint'));
    }

    /**
     * Get Trustpilot API credentials
     *
     * @return array
     */
    private function get_api_credentials() {
        return array(
            'business_unit_id' => get_option('wc_rewards_trustpilot_business_unit_id', ''),
            'api_key' => get_option('wc_rewards_trustpilot_api_key', ''),
            'secret_key' => get_option('wc_rewards_trustpilot_secret_key', '')
        );
    }

    /**
     * Register review reward endpoint
     */
    public function register_review_endpoint() {
        register_rest_route('wc-reward-points/v1', '/review', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_review_reward'),
            'permission_callback' => array($this, 'check_review_permission'),
            'args' => array(
                'review_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }

    /**
     * Check if user has permission to claim review reward
     *
     * @return bool|WP_Error
     */
    public function check_review_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in to claim review rewards.', 'wc-reward-points'),
                array('status' => 401)
            );
        }

        // Check if user has already claimed review reward
        $user_id = get_current_user_id();
        if ($this->points_manager->has_claimed_reward($user_id, 'trustpilot_review')) {
            return new WP_Error(
                'review_already_claimed',
                __('You have already claimed a review reward.', 'wc-reward-points'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Handle review reward request
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function handle_review_reward($request) {
        $user_id = get_current_user_id();
        $review_id = $request->get_param('review_id');

        $this->logger->log(
            'Processing review reward request',
            WC_Reward_Points_Debug::INFO,
            array(
                'user_id' => $user_id,
                'review_id' => $review_id
            )
        );

        // Verify review
        $review = $this->verify_review($review_id);
        if (is_wp_error($review)) {
            return $review;
        }

        // Check if review is from the current user
        if (!$this->is_review_from_user($review, $user_id)) {
            return new WP_Error(
                'invalid_review',
                __('The review was not created by your account.', 'wc-reward-points'),
                array('status' => 403)
            );
        }

        // Award points
        $points = apply_filters('wc_rewards_review_points', 300);
        $result = $this->points_manager->add_points(
            $user_id,
            $points,
            'trustpilot_review',
            sprintf(__('Trustpilot review reward (Review ID: %s)', 'wc-reward-points'), $review_id)
        );

        if (is_wp_error($result)) {
            $this->logger->log(
                'Failed to award review points',
                WC_Reward_Points_Debug::ERROR,
                array(
                    'user_id' => $user_id,
                    'review_id' => $review_id,
                    'error' => $result->get_error_message()
                )
            );
            return $result;
        }

        // Log the claim
        $this->points_manager->log_reward_claim($user_id, 'trustpilot_review', $points);

        $this->logger->log(
            'Review points awarded successfully',
            WC_Reward_Points_Debug::INFO,
            array(
                'user_id' => $user_id,
                'review_id' => $review_id,
                'points' => $points
            )
        );

        return rest_ensure_response(array(
            'success' => true,
            'points_awarded' => $points,
            'message' => sprintf(
                __('Thank you for your review! %d points have been added to your account.', 'wc-reward-points'),
                $points
            )
        ));
    }

    /**
     * Verify review with Trustpilot API
     *
     * @param string $review_id Review ID
     * @return array|WP_Error Review data or error
     */
    private function verify_review($review_id) {
        // Rate limiting
        $rate_limit_key = 'wc_rewards_trustpilot_rate_limit_' . get_current_user_id();
        $attempts = (int)get_transient($rate_limit_key);
        if ($attempts >= 5) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many review verification attempts. Please try again later.', 'wc-reward-points'),
                array('status' => 429)
            );
        }
        set_transient($rate_limit_key, $attempts + 1, HOUR_IN_SECONDS);

        // Validate review ID format
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $review_id)) {
            return new WP_Error(
                'invalid_review_id',
                __('Invalid review ID format.', 'wc-reward-points'),
                array('status' => 400)
            );
        }

        // Check API credentials
        if (empty($this->api_credentials['business_unit_id']) || 
            empty($this->api_credentials['api_key'])) {
            return new WP_Error(
                'api_credentials_missing',
                __('Trustpilot API credentials are not configured.', 'wc-reward-points'),
                array('status' => 500)
            );
        }

        // Make API request
        $api_url = sprintf(
            'https://api.trustpilot.com/v1/reviews/%s',
            urlencode($review_id)
        );

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'apikey' => $this->api_credentials['api_key']
            )
        ));

        if (is_wp_error($response)) {
            $this->logger->log(
                'Trustpilot API request failed',
                WC_Reward_Points_Debug::ERROR,
                array(
                    'review_id' => $review_id,
                    'error' => $response->get_error_message()
                )
            );
            return new WP_Error(
                'api_error',
                __('Failed to verify review with Trustpilot.', 'wc-reward-points'),
                array('status' => 500)
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200 || empty($body)) {
            $this->logger->log(
                'Invalid Trustpilot API response',
                WC_Reward_Points_Debug::ERROR,
                array(
                    'review_id' => $review_id,
                    'status_code' => $status_code,
                    'response' => $body
                )
            );
            return new WP_Error(
                'api_error',
                __('Invalid response from Trustpilot.', 'wc-reward-points'),
                array('status' => 500)
            );
        }

        // Verify review belongs to our business unit
        if ($body['businessUnit']['id'] !== $this->api_credentials['business_unit_id']) {
            return new WP_Error(
                'invalid_review',
                __('This review is not for our business.', 'wc-reward-points'),
                array('status' => 400)
            );
        }

        // Check review status
        if (isset($body['status']) && $body['status'] !== 'published') {
            return new WP_Error(
                'invalid_review_status',
                __('This review is not currently published.', 'wc-reward-points'),
                array('status' => 400)
            );
        }

        // Check review age
        $review_date = isset($body['createdAt']) ? strtotime($body['createdAt']) : 0;
        if ($review_date < strtotime('-30 days')) {
            return new WP_Error(
                'review_expired',
                __('This review is too old to claim rewards for.', 'wc-reward-points'),
                array('status' => 400)
            );
        }

        // Check minimum rating
        $min_rating = apply_filters('wc_rewards_minimum_review_rating', 3);
        if (isset($body['stars']) && $body['stars'] < $min_rating) {
            return new WP_Error(
                'rating_too_low',
                sprintf(
                    __('Reviews must have at least %d stars to qualify for rewards.', 'wc-reward-points'),
                    $min_rating
                ),
                array('status' => 400)
            );
        }

        return $body;
    }

    /**
     * Check if review is from the current user
     *
     * @param array $review Review data
     * @param int   $user_id User ID
     * @return bool
     */
    private function is_review_from_user($review, $user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Compare email addresses
        return isset($review['consumer']['email']) && 
               $review['consumer']['email'] === $user->user_email;
    }

    /**
     * Get review reward amount
     *
     * @return int
     */
    public function get_review_reward_amount() {
        return apply_filters('wc_rewards_review_points', 300);
    }

    /**
     * Get plugin settings
     *
     * @return array
     */
    public function get_settings() {
        return array(
            'business_unit_id' => array(
                'title' => __('Business Unit ID', 'wc-reward-points'),
                'type' => 'text',
                'description' => __('Your Trustpilot Business Unit ID', 'wc-reward-points'),
                'default' => '',
                'required' => true
            ),
            'api_key' => array(
                'title' => __('API Key', 'wc-reward-points'),
                'type' => 'password',
                'description' => __('Your Trustpilot API Key', 'wc-reward-points'),
                'default' => '',
                'required' => true
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'wc-reward-points'),
                'type' => 'password',
                'description' => __('Your Trustpilot Secret Key', 'wc-reward-points'),
                'default' => '',
                'required' => true
            ),
            'points_amount' => array(
                'title' => __('Points Amount', 'wc-reward-points'),
                'type' => 'number',
                'description' => __('Number of points to award for reviews', 'wc-reward-points'),
                'default' => 300,
                'required' => true
            ),
            'minimum_rating' => array(
                'title' => __('Minimum Rating', 'wc-reward-points'),
                'type' => 'number',
                'description' => __('Minimum star rating required for rewards (1-5)', 'wc-reward-points'),
                'default' => 3,
                'min' => 1,
                'max' => 5,
                'required' => true
            ),
            'review_expiry_days' => array(
                'title' => __('Review Expiry Days', 'wc-reward-points'),
                'type' => 'number',
                'description' => __('Number of days after which reviews become ineligible for rewards', 'wc-reward-points'),
                'default' => 30,
                'min' => 1,
                'required' => true
            )
        );
    }

    /**
     * Validate settings
     *
     * @param array $settings Settings to validate
     * @return array|WP_Error Validated settings or error
     */
    public function validate_settings($settings) {
        $errors = new WP_Error();
        $required_fields = array_filter($this->get_settings(), function($field) {
            return !empty($field['required']);
        });

        foreach ($required_fields as $key => $field) {
            if (empty($settings[$key])) {
                $errors->add(
                    'missing_field',
                    sprintf(__('%s is required.', 'wc-reward-points'), $field['title'])
                );
            }
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        // Validate numeric fields
        if (!is_numeric($settings['points_amount']) || $settings['points_amount'] < 0) {
            $errors->add('invalid_points', __('Points amount must be a positive number.', 'wc-reward-points'));
        }

        if (!is_numeric($settings['minimum_rating']) || 
            $settings['minimum_rating'] < 1 || 
            $settings['minimum_rating'] > 5) {
            $errors->add('invalid_rating', __('Minimum rating must be between 1 and 5.', 'wc-reward-points'));
        }

        if (!is_numeric($settings['review_expiry_days']) || $settings['review_expiry_days'] < 1) {
            $errors->add('invalid_expiry', __('Review expiry days must be a positive number.', 'wc-reward-points'));
        }

        return $errors->has_errors() ? $errors : $settings;
    }
} 