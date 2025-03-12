<?php
/**
 * Trustpilot Review Handler
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 * @since      1.0.0
 */

namespace WC_Reward_Points\Public;

use WC_Reward_Points\Core\WC_Reward_Points_Manager;
use WC_Reward_Points\Integrations\Trustpilot_API;

/**
 * Trustpilot Review Handler Class
 *
 * Handles all Trustpilot review-related functionality
 */
class WC_Reward_Points_Trustpilot {

    /**
     * Points Manager instance
     *
     * @var WC_Reward_Points_Manager
     */
    private $points_manager;

    /**
     * Trustpilot API instance
     *
     * @var Trustpilot_API
     */
    private $api;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->points_manager = WC_Reward_Points_Manager::instance();
        
        // Initialize API client
        $this->init_api();

        // Register endpoints
        add_action('init', array($this, 'add_endpoints'));
        
        // Handle review endpoint
        add_action('template_redirect', array($this, 'handle_review_endpoint'));
        
        // Add review link to order emails
        add_action('woocommerce_email_after_order_table', array($this, 'add_review_link_to_email'), 10, 4);
        
        // Schedule review verification
        add_action('wc_reward_points_verify_review', array($this, 'verify_review'), 10, 2);
        
        // Batch review verification
        add_action('wc_reward_points_batch_verify_reviews', array($this, 'batch_verify_pending_reviews'));
    }

    /**
     * Initialize Trustpilot API client
     */
    private function init_api() {
        $business_unit_id = get_option('wc_reward_points_trustpilot_business_unit_id');
        $api_key = get_option('wc_reward_points_trustpilot_api_key');
        $api_secret = get_option('wc_reward_points_trustpilot_api_secret');

        if ($business_unit_id && $api_key && $api_secret) {
            $this->api = new Trustpilot_API($business_unit_id, $api_key, $api_secret);
        }
    }

    /**
     * Register the review endpoint
     */
    public function add_endpoints() {
        add_rewrite_endpoint('rewards/review', EP_ROOT);
        
        if (get_option('wc_reward_points_flush_rewrite') !== false) {
            flush_rewrite_rules();
            delete_option('wc_reward_points_flush_rewrite');
        }
    }

    /**
     * Handle review endpoint
     */
    public function handle_review_endpoint() {
        global $wp_query, $wpdb;

        // Check if we're on the review endpoint
        if (!isset($wp_query->query_vars['rewards/review'])) {
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_redirect(add_query_arg('redirect_to', urlencode($_SERVER['REQUEST_URI']), wc_get_page_permalink('myaccount')));
            exit;
        }

        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);

        // Get order ID from query string
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        
        // Implement rate limiting for review submissions
        $rate_limit_period = absint(get_option('wc_reward_points_review_rate_limit', 24)); // Hours
        $rate_limit_check = $this->check_submission_rate_limit($user_id, $user->user_email, $rate_limit_period);
        
        if ($rate_limit_check['limited']) {
            wc_add_notice(
                sprintf(
                    __('You have submitted too many reviews recently. Please wait %s before submitting another review.', 'wc-reward-points'),
                    human_time_diff(time(), $rate_limit_check['next_allowed'])
                ), 
                'error'
            );
            wp_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        // Check review eligibility
        $eligibility = $this->check_review_eligibility($user_id);
        
        if (!$eligibility['eligible']) {
            wc_add_notice($eligibility['message'], 'error');
            wp_redirect(wc_get_account_endpoint_url('orders'));
            exit;
        }

        // Load template
        if (isset($_GET['order_id']) && $order_id > 0) {
            // Get review link for this order
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wc_add_notice(__('Invalid order.', 'wc-reward-points'), 'error');
                wp_redirect(wc_get_account_endpoint_url('orders'));
                exit;
            }

            // Check if the order belongs to this user
            if ($order->get_customer_id() != $user_id) {
                wc_add_notice(__('You do not have permission to review this order.', 'wc-reward-points'), 'error');
                wp_redirect(wc_get_account_endpoint_url('orders'));
                exit;
            }
            
            // Check if this order already has a review
            $review_check = $this->check_order_reviewed($order_id, $user_id, $user->user_email);
            if ($review_check['reviewed']) {
                wc_add_notice(
                    sprintf(
                        __('This order has already been reviewed on %s. You can only submit one review per order.', 'wc-reward-points'),
                        date_i18n(get_option('date_format'), strtotime($review_check['date']))
                    ), 
                    'error'
                );
                wp_redirect(wc_get_account_endpoint_url('orders'));
                exit;
            }

            // Generate a review link
            $review_link = $this->generate_review_link($order);
            
            // Track this review submission attempt
            $submission_id = $this->track_review_submission_attempt($user_id, $user->user_email, $order_id);
            
            // Add order ID and submission ID to the link for tracking
            $review_link .= '&wc_submission_id=' . $submission_id;
            
            // Redirect to Trustpilot
            wp_redirect($review_link);
            exit;
        } else {
            // Display a list of orders eligible for review
            wc_get_template(
                'trustpilot-review.php',
                array(
                    'orders' => wc_get_orders(array(
                        'customer' => $user_id,
                        'status' => array('completed'),
                        'limit' => 10,
                    )),
                ),
                '',
                WC_REWARD_POINTS_TEMPLATE_PATH
            );
            exit;
        }
    }
    
    /**
     * Track review submission attempt
     *
     * @param int    $user_id   User ID
     * @param string $email     User email
     * @param int    $order_id  Order ID
     * @return int   Submission ID
     */
    private function track_review_submission_attempt($user_id, $email, $order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_reward_points_review_submissions';
        
        // Get IP address and user agent
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Order reference
        $order_reference = $order_id ? 'order_' . $order_id : '';
        
        // Insert tracking record
        $wpdb->insert(
            $table_name,
            array(
                'user_id'             => $user_id,
                'email'               => $email,
                'ip_address'          => $ip_address,
                'user_agent'          => $user_agent,
                'order_reference'     => $order_reference,
                'status'              => 'pending',
                'date_created'        => current_time('mysql'),
                'verification_attempts' => 0
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        // Record the attempt in the rate limiter
        $identity = $user_id ? 'u_' . $user_id : 'email_' . md5($email);
        $hours = absint(get_option('wc_reward_points_review_rate_limit', 24));
        $max_submissions = absint(get_option('wc_reward_points_review_max_submissions', 3));
        $timeframe = $hours * HOUR_IN_SECONDS;
        
        \WC_Reward_Points\Core\WC_Reward_Points_Rate_Limiter::record_attempt(
            'review_submission',
            'review_submission',
            $identity,
            $max_submissions,
            $timeframe
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Check if order has already been reviewed
     *
     * @param int    $order_id Order ID
     * @param int    $user_id  User ID
     * @param string $email    User email
     * @return array Result with reviewed status and date
     */
    private function check_order_reviewed($order_id, $user_id, $email) {
        global $wpdb;
        
        $result = array(
            'reviewed' => false,
            'date' => null
        );
        
        // Check in local database first (faster)
        $table_name = $wpdb->prefix . 'wc_reward_points_review_submissions';
        $order_reference = 'order_' . $order_id;
        
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE (user_id = %d OR email = %s)
            AND order_reference = %s
            AND status = 'completed'
            ORDER BY date_created DESC
            LIMIT 1",
            $user_id, $email, $order_reference
        ));
        
        if ($submission) {
            $result['reviewed'] = true;
            $result['date'] = $submission->date_created;
            $result['source'] = 'database';
            return $result;
        }
        
        // If not found locally, check with Trustpilot API
        if ($this->api) {
            // We'll use the enhanced API method to check for reviews
            $api_check = $this->api->has_customer_reviewed($email, $order_reference);
            
            // If user has reviewed and we have a matching reference
            if ($api_check['has_reviewed'] && !empty($api_check['details']['reference_match'])) {
                $result['reviewed'] = true;
                $result['date'] = !empty($api_check['details']['date']) ? $api_check['details']['date'] : current_time('mysql');
                $result['source'] = 'api';
                
                // Store this information locally for future checks
                $this->track_review_submission_attempt($user_id, $email, $order_id);
                
                // Update status to completed
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'completed',
                        'review_id' => $api_check['details']['id'],
                        'date_verified' => current_time('mysql')
                    ),
                    array(
                        'user_id' => $user_id,
                        'order_reference' => $order_reference
                    ),
                    array('%s', '%s', '%s'),
                    array('%d', '%s')
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Check submission rate limiting
     *
     * @param int    $user_id User ID
     * @param string $email   User email
     * @param int    $hours   Rate limit period in hours
     * @return array Rate limit status
     */
    private function check_submission_rate_limit($user_id, $email, $hours = 24) {
        // Get settings
        $max_submissions = absint(get_option('wc_reward_points_review_max_submissions', 3));
        $timeframe = $hours * HOUR_IN_SECONDS;
        
        // Use our rate limiter class for consistent rate limiting
        $identity = $user_id ? 'u_' . $user_id : 'email_' . md5($email);
        
        return \WC_Reward_Points\Core\WC_Reward_Points_Rate_Limiter::check_rate_limit(
            'review_submission',
            'review_submission',
            $identity,
            $max_submissions,
            $timeframe
        );
    }

    /**
     * Check if user is eligible to leave a review
     *
     * @param int $user_id User ID
     * @return true|WP_Error True if eligible, error if not
     */
    private function check_review_eligibility($user_id) {
        // Check if user has a pending review
        $pending_review = get_user_meta($user_id, '_wc_review_pending', true);
        if ($pending_review) {
            return new \WP_Error(
                'pending_review',
                __('You already have a pending review. Please wait for it to be verified.', 'wc-reward-points')
            );
        }

        // Check cooldown period
        $last_review = get_user_meta($user_id, '_wc_last_review_date', true);
        if ($last_review) {
            $cooldown = get_option('wc_reward_points_trustpilot_cooldown', 0);
            if ($cooldown > 0) {
                $next_review = strtotime($last_review) + ($cooldown * DAY_IN_SECONDS);
                if (time() < $next_review) {
                    return new \WP_Error(
                        'review_cooldown',
                        sprintf(
                            __('You can leave another review in %d days.', 'wc-reward-points'),
                            ceil(($next_review - time()) / DAY_IN_SECONDS)
                        )
                    );
                }
            } else {
                return new \WP_Error(
                    'one_time_only',
                    __('You have already earned points for a review.', 'wc-reward-points')
                );
            }
        }

        return true;
    }

    /**
     * Generate review link for order
     *
     * @param WC_Order $order Order object
     * @return string|WP_Error Review link or error
     */
    private function generate_review_link($order) {
        if (!$this->api) {
            return new \WP_Error(
                'api_not_configured',
                __('Trustpilot API is not properly configured.', 'wc-reward-points')
            );
        }

        return $this->api->create_review_link(
            $order->get_formatted_billing_full_name(),
            $order->get_billing_email(),
            $order->get_order_number()
        );
    }

    /**
     * Add review link to order completion email
     *
     * @param WC_Order $order Order object
     * @param bool     $sent_to_admin Whether email is sent to admin
     * @param bool     $plain_text Whether email is plain text
     * @param WC_Email $email Email object
     */
    public function add_review_link_to_email($order, $sent_to_admin, $plain_text, $email) {
        // Only add to customer emails for completed orders
        if ($sent_to_admin || $order->get_status() !== 'completed' || $email->id !== 'customer_completed_order') {
            return;
        }

        $points = get_option('wc_reward_points_trustpilot_review_points', 300);
        
        if ($plain_text) {
            echo "\n\n" . sprintf(
                __('Leave a review and earn %d reward points: %s', 'wc-reward-points'),
                $points,
                home_url('rewards/review')
            );
        } else {
            echo '<p>' . sprintf(
                __('Leave a review and earn %d reward points: <a href="%s">Click here</a>', 'wc-reward-points'),
                $points,
                esc_url(home_url('rewards/review'))
            ) . '</p>';
        }
    }

    /**
     * Verify review and award points
     *
     * @param int $user_id User ID
     * @param int $order_id Order ID
     */
    public function verify_review($user_id, $order_id) {
        $pending_review = get_user_meta($user_id, '_wc_review_pending', true);
        if (!$pending_review || $pending_review['order_id'] !== $order_id) {
            return;
        }

        if (!$this->api) {
            return;
        }

        // Get reviews for the order
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $email = $order->get_billing_email();
        $reviews = $this->api->get_reviews(array(
            'email'     => $email,
            'reference' => $order->get_order_number()
        ));

        if (is_wp_error($reviews) || empty($reviews['reviews'])) {
            delete_user_meta($user_id, '_wc_review_pending');
            return;
        }

        $review = $reviews['reviews'][0];

        // Verify review meets requirements
        $min_rating = get_option('wc_reward_points_trustpilot_min_rating', 1);
        $min_length = get_option('wc_reward_points_trustpilot_min_length', 50);

        if ($review['stars'] < $min_rating || strlen($review['text']) < $min_length) {
            delete_user_meta($user_id, '_wc_review_pending');
            return;
        }

        // Award points
        $points = get_option('wc_reward_points_trustpilot_review_points', 300);
        $this->points_manager->add_points(
            $user_id,
            $points,
            'review',
            sprintf(__('Points for Trustpilot review of order #%s', 'wc-reward-points'), $order->get_order_number())
        );

        // Update user meta
        delete_user_meta($user_id, '_wc_review_pending');
        update_user_meta($user_id, '_wc_last_review_date', current_time('mysql'));

        // Send notification
        $this->send_points_notification($user_id, $points);
    }

    /**
     * Send points awarded notification
     *
     * @param int $user_id User ID
     * @param int $points Points awarded
     */
    private function send_points_notification($user_id, $points) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $subject = sprintf(
            __('You earned %d points for your review!', 'wc-reward-points'),
            $points
        );

        $message = sprintf(
            __('Hi %s,\n\nThank you for your review! We have awarded you %d points.\n\nBest regards,\n%s', 'wc-reward-points'),
            $user->display_name,
            $points,
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Process batch verification of pending reviews
     * This should be scheduled to run daily
     */
    public function batch_verify_pending_reviews() {
        global $wpdb;
        
        // Check if API is available
        if (!$this->api) {
            return;
        }
        
        // Get the verification period
        $verify_days = absint(get_option('wc_reward_points_trustpilot_verify_days', 7));
        
        // Determine the cutoff date for verification
        $verify_cutoff = date('Y-m-d H:i:s', strtotime("-{$verify_days} days"));
        
        // Get pending reviews ready for verification
        $table_name = $wpdb->prefix . 'wc_reward_points_review_submissions';
        $pending_reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE status = 'pending' 
            AND date_created <= %s
            AND verification_attempts < 3
            ORDER BY date_created ASC
            LIMIT 50",
            $verify_cutoff
        ));
        
        if (empty($pending_reviews)) {
            return;
        }
        
        // Group reviews by email for efficient processing
        $reviews_by_email = array();
        $all_emails = array();
        
        foreach ($pending_reviews as $submission) {
            $email = $submission->email;
            $all_emails[] = $email;
            
            if (!isset($reviews_by_email[$email])) {
                $reviews_by_email[$email] = array();
            }
            
            $reviews_by_email[$email][] = $submission;
        }
        
        // Get all reviews for these emails in bulk
        $batch_results = $this->api->batch_get_reviews_by_email(array_unique($all_emails));
        
        // Process the results
        foreach ($reviews_by_email as $email => $submissions) {
            // Skip if no results for this email
            if (!isset($batch_results[$email]) || empty($batch_results[$email]['reviews'])) {
                // Update attempt count
                foreach ($submissions as $submission) {
                    $wpdb->update(
                        $table_name,
                        array(
                            'verification_attempts' => $submission->verification_attempts + 1,
                            'status' => ($submission->verification_attempts >= 2) ? 'failed' : 'pending'
                        ),
                        array('id' => $submission->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                }
                continue;
            }
            
            // Get the reviews
            $reviews = $batch_results[$email]['reviews'];
            
            // Process each submission
            foreach ($submissions as $submission) {
                $order_reference = $submission->order_reference;
                $user_id = $submission->user_id;
                $found_review = false;
                
                // Look for matching review
                foreach ($reviews as $review) {
                    // Check if this review matches our criteria
                    $reference_match = !empty($review['referenceId']) && $review['referenceId'] === $order_reference;
                    $rating_match = isset($review['stars']) && $review['stars'] >= get_option('wc_reward_points_trustpilot_min_rating', 1);
                    $length_match = isset($review['text']) && strlen($review['text']) >= get_option('wc_reward_points_trustpilot_min_length', 50);
                    
                    if ($reference_match && $rating_match && $length_match) {
                        $found_review = true;
                        
                        // Award points if this is a valid review
                        $points = absint(get_option('wc_reward_points_trustpilot_review_points', 300));
                        $cooldown = absint(get_option('wc_reward_points_trustpilot_cooldown', 0));
                        
                        // Check if within cooldown period if it's set
                        $can_award = true;
                        if ($cooldown > 0 && $user_id) {
                            $last_award = $wpdb->get_var($wpdb->prepare(
                                "SELECT MAX(t.date_created) 
                                FROM {$wpdb->prefix}wc_reward_points_transactions t 
                                WHERE t.user_id = %d AND t.type = 'trustpilot_review'",
                                $user_id
                            ));
                            
                            if ($last_award) {
                                $cooldown_date = date('Y-m-d H:i:s', strtotime("-{$cooldown} days"));
                                if (strtotime($last_award) > strtotime($cooldown_date)) {
                                    $can_award = false;
                                }
                            }
                        }
                        
                        // Award points if eligible
                        if ($can_award && $user_id && $points > 0) {
                            // Add points to user
                            $current_points = get_user_meta($user_id, '_wc_reward_points_balance', true);
                            $current_points = absint($current_points);
                            $new_balance = $current_points + $points;
                            
                            update_user_meta($user_id, '_wc_reward_points_balance', $new_balance);
                            
                            // Record transaction
                            $wpdb->insert(
                                $wpdb->prefix . 'wc_reward_points_transactions',
                                array(
                                    'user_id' => $user_id,
                                    'points' => $points,
                                    'type' => 'trustpilot_review',
                                    'note' => sprintf(__('Points for Trustpilot review: %s', 'wc-reward-points'), $review['id']),
                                    'date_created' => current_time('mysql')
                                ),
                                array('%d', '%d', '%s', '%s', '%s')
                            );
                            
                            // Update submission record
                            $wpdb->update(
                                $table_name,
                                array(
                                    'status' => 'completed',
                                    'review_id' => $review['id'],
                                    'points_awarded' => $points,
                                    'date_verified' => current_time('mysql')
                                ),
                                array('id' => $submission->id),
                                array('%s', '%s', '%d', '%s'),
                                array('%d')
                            );
                            
                            // Send notification
                            $this->send_points_notification($user_id, $points);
                        } else {
                            // Mark as verified but no points awarded
                            $wpdb->update(
                                $table_name,
                                array(
                                    'status' => 'verified',
                                    'review_id' => $review['id'],
                                    'date_verified' => current_time('mysql')
                                ),
                                array('id' => $submission->id),
                                array('%s', '%s', '%s'),
                                array('%d')
                            );
                        }
                        
                        break;
                    }
                }
                
                // If no matching review found, increment attempt counter
                if (!$found_review) {
                    $wpdb->update(
                        $table_name,
                        array(
                            'verification_attempts' => $submission->verification_attempts + 1,
                            'status' => ($submission->verification_attempts >= 2) ? 'failed' : 'pending'
                        ),
                        array('id' => $submission->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                }
            }
        }
    }
} 