<?php
/**
 * The URL handling functionality of the plugin.
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

use WC_Reward_Points\Core\WC_Reward_Points_Points_Manager;
use WC_Reward_Points\Core\WC_Reward_Points_Referral_Manager;
use WC_Reward_Points\Core\WC_Reward_Points_Debug;

/**
 * The URL handler class.
 *
 * Manages all reward URLs and their functionality including signup rewards,
 * referral processing, and review rewards.
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 * @author     Kyu Cho
 */
class WC_Reward_Points_URL_Handler {

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

        // Register URL endpoints
        add_action('init', array($this, 'register_endpoints'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_reward_endpoints'));

        // Register AJAX handlers
        add_action('wp_ajax_process_signup_reward', array($this, 'process_signup_reward'));
        add_action('wp_ajax_process_review_reward', array($this, 'process_review_reward'));
    }

    /**
     * Register URL endpoints.
     *
     * @since    1.0.0
     */
    public function register_endpoints() {
        add_rewrite_rule(
            'rewards/signup/?$',
            'index.php?wc_reward_action=signup',
            'top'
        );

        add_rewrite_rule(
            'rewards/refer/([^/]+)/?$',
            'index.php?wc_reward_action=refer&referral_code=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            'rewards/review/?$',
            'index.php?wc_reward_action=review',
            'top'
        );

        // Flush rewrite rules if needed
        if (get_option('wc_reward_points_flush_rules', false)) {
            flush_rewrite_rules();
            delete_option('wc_reward_points_flush_rules');
        }
    }

    /**
     * Add custom query variables.
     *
     * @since    1.0.0
     * @param    array    $vars    The array of query variables.
     * @return   array             The modified array of query variables.
     */
    public function add_query_vars($vars) {
        $vars[] = 'wc_reward_action';
        $vars[] = 'referral_code';
        return $vars;
    }

    /**
     * Handle reward endpoints.
     *
     * @since    1.0.0
     */
    public function handle_reward_endpoints() {
        $action = get_query_var('wc_reward_action');

        if (!$action) {
            return;
        }

        switch ($action) {
            case 'signup':
                $this->handle_signup_page();
                break;

            case 'refer':
                $code = get_query_var('referral_code');
                $this->handle_referral_page($code);
                break;

            case 'review':
                $this->handle_review_page();
                break;
        }

        exit;
    }

    /**
     * Handle signup reward page.
     *
     * @since    1.0.0
     */
    private function handle_signup_page() {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('rewards/signup')));
            exit;
        }

        // Get current user
        $user_id = get_current_user_id();

        // Check cooldown period
        $last_claim = get_user_meta($user_id, 'wc_reward_points_last_signup_claim', true);
        $cooldown = get_option('wc_reward_points_signup_cooldown', 30) * DAY_IN_SECONDS;

        if ($last_claim && (time() - strtotime($last_claim) < $cooldown)) {
            $next_claim = date('F j, Y', strtotime($last_claim) + $cooldown);
            wp_die(
                sprintf(
                    __('You can claim your next signup reward on %s.', 'wc-reward-points'),
                    $next_claim
                ),
                __('Reward Not Available', 'wc-reward-points')
            );
        }

        // Load template
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/signup-reward.php';
    }

    /**
     * Handle referral page.
     *
     * @since    1.0.0
     * @param    string    $code    The referral code.
     */
    private function handle_referral_page($code) {
        if (!$code) {
            wp_die(
                __('Invalid referral code.', 'wc-reward-points'),
                __('Error', 'wc-reward-points')
            );
        }

        // Store referral code in session
        WC()->session->set('wc_reward_points_referral_code', $code);

        // If user is logged in, process referral
        if (is_user_logged_in()) {
            $result = $this->referral_manager->process_referral(
                get_current_user_id(),
                $code
            );

            if ($result['success']) {
                wp_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }
        }

        // Load template
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/referral.php';
    }

    /**
     * Handle review reward page.
     *
     * @since    1.0.0
     */
    private function handle_review_page() {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('rewards/review')));
            exit;
        }

        // Check if user has already claimed review reward
        $user_id = get_current_user_id();
        if (get_user_meta($user_id, 'wc_reward_points_review_claimed', true)) {
            wp_die(
                __('You have already claimed your review reward.', 'wc-reward-points'),
                __('Reward Already Claimed', 'wc-reward-points')
            );
        }

        // Load template
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/review-reward.php';
    }

    /**
     * Process signup reward AJAX request.
     *
     * @since    1.0.0
     */
    public function process_signup_reward() {
        check_ajax_referer('wc_reward_points_signup', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to claim rewards.', 'wc-reward-points'));
        }

        $user_id = get_current_user_id();
        $points = get_option('wc_reward_points_signup_points', 1000);

        // Add points
        $result = $this->points_manager->add_points(
            $user_id,
            $points,
            'signup_reward',
            __('Signup reward points', 'wc-reward-points')
        );

        if ($result['success']) {
            // Update last claim timestamp
            update_user_meta($user_id, 'wc_reward_points_last_signup_claim', current_time('mysql'));

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Congratulations! You have been awarded %d points.', 'wc-reward-points'),
                    $points
                ),
                'points' => $points
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Process review reward AJAX request.
     *
     * @since    1.0.0
     */
    public function process_review_reward() {
        check_ajax_referer('wc_reward_points_review', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to claim rewards.', 'wc-reward-points'));
        }

        $user_id = get_current_user_id();
        $review_url = sanitize_text_field($_POST['review_url']);

        // Verify Trustpilot review
        $verified = $this->verify_trustpilot_review($review_url, $user_id);
        if (!$verified['success']) {
            wp_send_json_error($verified['error']);
        }

        // Add points
        $points = get_option('wc_reward_points_review_points', 300);
        $result = $this->points_manager->add_points(
            $user_id,
            $points,
            'review_reward',
            __('Trustpilot review reward', 'wc-reward-points')
        );

        if ($result['success']) {
            // Mark review as claimed
            update_user_meta($user_id, 'wc_reward_points_review_claimed', true);
            update_user_meta($user_id, 'wc_reward_points_review_url', $review_url);

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Thank you for your review! You have been awarded %d points.', 'wc-reward-points'),
                    $points
                ),
                'points' => $points
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Verify Trustpilot review.
     *
     * @since    1.0.0
     * @param    string    $review_url    The review URL.
     * @param    int       $user_id       The user ID.
     * @return   array                    Array containing success status and message.
     */
    private function verify_trustpilot_review($review_url, $user_id) {
        // TODO: Implement Trustpilot API verification
        // This is a placeholder that will be replaced with actual Trustpilot API integration
        return array(
            'success' => true,
            'message' => 'Review verified'
        );
    }
} 