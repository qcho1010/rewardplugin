<?php
/**
 * Signup Reward Handler
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 */

namespace WC_Reward_Points\Public;

use WC_Reward_Points\Core\WC_Reward_Points_Manager;

/**
 * Signup Reward Handler
 *
 * Handles the signup reward URL endpoint and processing
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 * @author     Kyu Cho
 */
class WC_Reward_Points_Signup {

    /**
     * Points Manager instance
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Manager    $points_manager    Points management instance
     */
    private $points_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->points_manager = WC_Reward_Points_Manager::instance();

        // Register the endpoint
        add_action('init', array($this, 'add_endpoints'));
        
        // Handle the endpoint
        add_action('template_redirect', array($this, 'handle_signup_endpoint'));
        
        // Handle user registration
        add_action('user_register', array($this, 'handle_signup_reward'), 10, 1);
    }

    /**
     * Register the signup endpoint
     *
     * @since    1.0.0
     */
    public function add_endpoints() {
        add_rewrite_endpoint('rewards/signup', EP_ROOT);
        
        // Check if we need to flush rewrite rules
        if (get_option('wc_reward_points_flush_rewrite') !== false) {
            flush_rewrite_rules();
            delete_option('wc_reward_points_flush_rewrite');
        }
    }

    /**
     * Handle the signup endpoint
     *
     * @since    1.0.0
     */
    public function handle_signup_endpoint() {
        global $wp_query;

        // Check if we're on the signup endpoint
        if (!isset($wp_query->query_vars['rewards/signup'])) {
            return;
        }

        // If user is logged in, check if they can claim the reward
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            // Check if user has already claimed the signup reward
            if ($this->points_manager->has_claimed_reward($user_id, 'signup')) {
                wc_add_notice(__('You have already claimed the signup reward.', 'wc-reward-points'), 'error');
                wp_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }

            // Award points
            $this->award_signup_points($user_id);
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        // If user is not logged in, show registration form
        if (!is_user_logged_in()) {
            // Set session variable to indicate signup came from reward URL
            WC()->session->set('reward_signup', 'yes');
            
            // Redirect to registration page
            wp_redirect(add_query_arg('reward_signup', 'yes', wc_get_page_permalink('myaccount')));
            exit;
        }
    }

    /**
     * Handle signup reward when user registers
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID
     */
    public function handle_signup_reward($user_id) {
        // Check if registration came from reward URL
        if (WC()->session && WC()->session->get('reward_signup') === 'yes') {
            $this->award_signup_points($user_id);
            WC()->session->set('reward_signup', null);
        }
    }

    /**
     * Award signup points to user
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID
     * @return   bool|WP_Error      True on success, WP_Error on failure
     */
    private function award_signup_points($user_id) {
        try {
            // Get points amount from settings
            $points = get_option('wc_reward_points_signup_points', 100);

            // Security check - verify user hasn't claimed before
            if ($this->points_manager->has_claimed_reward($user_id, 'signup')) {
                throw new \Exception(__('Signup reward already claimed', 'wc-reward-points'));
            }

            // Log the claim first
            $claim_result = $this->points_manager->log_reward_claim($user_id, 'signup', $points);
            if (is_wp_error($claim_result)) {
                throw new \Exception($claim_result->get_error_message());
            }

            // Award the points
            $result = $this->points_manager->add_points(
                $user_id,
                $points,
                'signup',
                __('Signup reward points', 'wc-reward-points')
            );

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            // Add success notice
            wc_add_notice(
                sprintf(
                    __('Congratulations! You have been awarded %d points for signing up!', 'wc-reward-points'),
                    $points
                ),
                'success'
            );

            return true;

        } catch (\Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return new \WP_Error('signup_reward_failed', $e->getMessage());
        }
    }

    /**
     * Check if current page is signup reward page
     *
     * @since    1.0.0
     * @return   bool    True if current page is signup reward page
     */
    public static function is_signup_reward_page() {
        global $wp_query;
        return isset($wp_query->query_vars['rewards/signup']);
    }
} 