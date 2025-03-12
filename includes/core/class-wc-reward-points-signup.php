<?php
/**
 * Signup Reward Handler
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

use WP_Error;

/**
 * Handles signup rewards and points
 */
class WC_Reward_Points_Signup {

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
     * Constructor
     */
    public function __construct() {
        $this->points_manager = WC_Reward_Points_Manager::instance();
        $this->logger = new WC_Reward_Points_Debug();

        // Register signup endpoint
        add_action('rest_api_init', array($this, 'register_signup_endpoint'));
    }

    /**
     * Register signup reward endpoint
     */
    public function register_signup_endpoint() {
        register_rest_route('wc-reward-points/v1', '/signup', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_signup_reward'),
            'permission_callback' => array($this, 'check_signup_permission')
        ));
    }

    /**
     * Check if user has permission to claim signup reward
     *
     * @return bool|WP_Error
     */
    public function check_signup_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in to claim signup rewards.', 'wc-reward-points'),
                array('status' => 401)
            );
        }

        $user_id = get_current_user_id();
        $last_claim = $this->get_last_claim_time($user_id);
        $cooldown = $this->get_cooldown_period();

        // If user has claimed before, check cooldown
        if ($last_claim) {
            $next_claim_time = strtotime("+{$cooldown} days", $last_claim);
            if (time() < $next_claim_time) {
                return new WP_Error(
                    'cooldown_active',
                    sprintf(
                        __('You can claim this reward again after %s.', 'wc-reward-points'),
                        date('Y-m-d H:i:s', $next_claim_time)
                    ),
                    array('status' => 403)
                );
            }
        }

        return true;
    }

    /**
     * Handle signup reward request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_signup_reward() {
        $user_id = get_current_user_id();

        $this->logger->log(
            'Processing signup reward request',
            WC_Reward_Points_Debug::INFO,
            array('user_id' => $user_id)
        );

        // Award points
        $points = $this->get_signup_reward_amount();
        $result = $this->points_manager->add_points(
            $user_id,
            $points,
            'signup',
            __('Signup/Account reward', 'wc-reward-points')
        );

        if (is_wp_error($result)) {
            $this->logger->log(
                'Failed to award signup points',
                WC_Reward_Points_Debug::ERROR,
                array(
                    'user_id' => $user_id,
                    'error' => $result->get_error_message()
                )
            );
            return $result;
        }

        // Log the claim with timestamp
        $this->log_claim_time($user_id);

        $this->logger->log(
            'Signup points awarded successfully',
            WC_Reward_Points_Debug::INFO,
            array(
                'user_id' => $user_id,
                'points' => $points
            )
        );

        return rest_ensure_response(array(
            'success' => true,
            'points_awarded' => $points,
            'next_claim_date' => date('Y-m-d H:i:s', strtotime("+" . $this->get_cooldown_period() . " days")),
            'message' => sprintf(
                __('%d points have been added to your account.', 'wc-reward-points'),
                $points
            )
        ));
    }

    /**
     * Get signup reward amount
     *
     * @return int
     */
    public function get_signup_reward_amount() {
        return (int)get_option('wc_rewards_signup_points', 100);
    }

    /**
     * Get cooldown period in days
     *
     * @return int
     */
    public function get_cooldown_period() {
        return (int)get_option('wc_rewards_signup_cooldown', 30);
    }

    /**
     * Get last claim time for user
     *
     * @param int $user_id User ID
     * @return int|false Timestamp of last claim or false if never claimed
     */
    private function get_last_claim_time($user_id) {
        global $wpdb;
        
        $timestamp = $wpdb->get_var($wpdb->prepare(
            "SELECT claim_time 
            FROM {$wpdb->prefix}wc_rewards_claims 
            WHERE user_id = %d 
            AND reward_type = 'signup'
            ORDER BY claim_time DESC 
            LIMIT 1",
            $user_id
        ));

        return $timestamp ? strtotime($timestamp) : false;
    }

    /**
     * Log claim time for user
     *
     * @param int $user_id User ID
     */
    private function log_claim_time($user_id) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'wc_rewards_claims',
            array(
                'user_id' => $user_id,
                'reward_type' => 'signup',
                'points_awarded' => $this->get_signup_reward_amount(),
                'claim_time' => current_time('mysql'),
                'claim_status' => 'completed'
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Get plugin settings
     *
     * @return array
     */
    public function get_settings() {
        return array(
            'signup_points' => array(
                'title' => __('Signup Points', 'wc-reward-points'),
                'type' => 'number',
                'description' => __('Number of points to award for signup/account rewards', 'wc-reward-points'),
                'default' => 100,
                'required' => true
            ),
            'signup_cooldown' => array(
                'title' => __('Reward Cooldown Period', 'wc-reward-points'),
                'type' => 'number',
                'description' => __('Number of days before a user can claim the reward again', 'wc-reward-points'),
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

        if (!isset($settings['signup_points']) || !is_numeric($settings['signup_points']) || $settings['signup_points'] < 0) {
            $errors->add('invalid_points', __('Signup points must be a positive number.', 'wc-reward-points'));
        }

        if (!isset($settings['signup_cooldown']) || !is_numeric($settings['signup_cooldown']) || $settings['signup_cooldown'] < 1) {
            $errors->add('invalid_cooldown', __('Cooldown period must be at least 1 day.', 'wc-reward-points'));
        }

        return $errors->has_errors() ? $errors : $settings;
    }
} 