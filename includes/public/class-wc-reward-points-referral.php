<?php
/**
 * Referral System Handler
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 * @since      1.0.0
 */

namespace WC_Reward_Points\Public;

use WC_Reward_Points\Core\WC_Reward_Points_Manager;

/**
 * Referral System Handler Class
 *
 * Handles all referral-related functionality including code generation,
 * URL handling, and points distribution
 */
class WC_Reward_Points_Referral {

    /**
     * Points Manager instance
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Manager
     */
    private $points_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->points_manager = WC_Reward_Points_Manager::instance();

        // Init and endpoints
        add_action('init', array($this, 'init'), 10);
        add_action('init', array($this, 'add_endpoints'), 11);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Handle referrals
        add_action('template_redirect', array($this, 'handle_referral_endpoint'));
        add_action('template_redirect', array($this, 'handle_short_referral_url'));

        // Process orders and referrals
        add_action('woocommerce_checkout_order_processed', array($this, 'process_referral_order'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'process_referral_order'));
        add_action('woocommerce_thankyou', array($this, 'process_referral_order'));

        // User registration
        add_action('user_register', array($this, 'generate_referral_code'));
        add_action('user_register', array($this, 'store_referral_for_new_user'), 20);

        // Ambassador features
        add_action('admin_post_wc_ambassador_apply', array($this, 'handle_ambassador_application'));
        add_action('admin_post_nopriv_wc_ambassador_apply', array($this, 'handle_ambassador_application'));
        add_action('woocommerce_account_ambassador_endpoint', array($this, 'render_ambassador_dashboard'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_ambassador_menu_item'));
        add_filter('query_vars', array($this, 'add_ambassador_query_var'));
        add_filter('woocommerce_get_query_vars', array($this, 'add_ambassador_query_var'));

        // Shortcodes
        add_shortcode('wc_referral_widget', array($this, 'render_referral_widget'));
        
        // Schedule cron events for ambassador performance handling
        add_action('wc_reward_points_monthly_performance_check', array($this, 'process_ambassador_performance_bonuses'));
        add_action('wc_reward_points_monthly_inactivity_check', array($this, 'process_ambassador_inactivity_penalties'));
        
        // Make sure cron events are scheduled
        if (!wp_next_scheduled('wc_reward_points_monthly_performance_check')) {
            // Schedule for the first day of each month at 1 AM
            wp_schedule_event(strtotime('first day of next month 1:00am'), 'monthly', 'wc_reward_points_monthly_performance_check');
        }
        
        if (!wp_next_scheduled('wc_reward_points_monthly_inactivity_check')) {
            // Schedule for the first day of each month at 2 AM
            wp_schedule_event(strtotime('first day of next month 2:00am'), 'monthly', 'wc_reward_points_monthly_inactivity_check');
        }
    }

    /**
     * Register the referral endpoint
     *
     * @since    1.0.0
     */
    public function add_endpoints() {
        add_rewrite_endpoint('rewards/refer', EP_ROOT);
        
        if (get_option('wc_reward_points_flush_rewrite') !== false) {
            flush_rewrite_rules();
            delete_option('wc_reward_points_flush_rewrite');
        }
    }

    /**
     * Generate unique referral code for user
     *
     * @param int $user_id User ID
     * @return string Generated referral code
     */
    public function generate_referral_code($user_id) {
        $code = wp_generate_password(8, false);
        update_user_meta($user_id, '_wc_referral_code', $code);
        
        // Save the generation date for expiration tracking
        update_user_meta($user_id, '_wc_referral_code_generated', current_time('timestamp'));
        
        return $code;
    }

    /**
     * Get user's referral code
     *
     * @param int $user_id User ID
     * @return string Referral code or empty if expired
     */
    public function get_referral_code($user_id) {
        $code = get_user_meta($user_id, '_wc_referral_code', true);
        $generated_time = get_user_meta($user_id, '_wc_referral_code_generated', true);
        
        // If no code exists, generate a new one
        if (!$code) {
            $code = $this->generate_referral_code($user_id);
            return $code;
        }
        
        // Check for expiration
        $expiration_days = absint(get_option('wc_reward_points_referral_expiration', 0));
        if ($expiration_days > 0 && $generated_time) {
            $expiration_timestamp = $generated_time + ($expiration_days * DAY_IN_SECONDS);
            
            // If code is expired
            if (current_time('timestamp') > $expiration_timestamp) {
                // Check if we should auto-reset
                if ('yes' === get_option('wc_reward_points_reset_expired_codes', 'no')) {
                    // Generate a new code
                    $code = $this->generate_referral_code($user_id);
                } else {
                    // Mark as expired but don't regenerate
                    update_user_meta($user_id, '_wc_referral_code_expired', 1);
                    return '';
                }
            }
        }
        
        return $code;
    }
    
    /**
     * Check if a referral code is valid and not expired
     *
     * @param string $code Referral code to check
     * @return bool Whether the code is valid
     */
    public function is_valid_referral_code($code) {
        if (empty($code)) {
            return false;
        }
        
        // Find user by code
        $args = array(
            'meta_key'    => '_wc_referral_code',
            'meta_value'  => $code,
            'number'      => 1,
            'fields'      => 'ID',
        );
        $users = get_users($args);
        
        if (empty($users)) {
            return false;
        }
        
        $user_id = $users[0];
        
        // Check if code is marked as expired
        $is_expired = get_user_meta($user_id, '_wc_referral_code_expired', true);
        if ($is_expired) {
            return false;
        }
        
        // Check expiration based on generation date
        $expiration_days = absint(get_option('wc_reward_points_referral_expiration', 0));
        if ($expiration_days > 0) {
            $generated_time = get_user_meta($user_id, '_wc_referral_code_generated', true);
            
            if ($generated_time) {
                $expiration_timestamp = $generated_time + ($expiration_days * DAY_IN_SECONDS);
                
                // Check if code is expired
                if (current_time('timestamp') > $expiration_timestamp) {
                    // Mark as expired
                    update_user_meta($user_id, '_wc_referral_code_expired', 1);
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Get user's referral URL
     *
     * @param int $user_id User ID
     * @return string Referral URL or empty if code is expired
     */
    public function get_referral_url($user_id) {
        $code = $this->get_referral_code($user_id);
        if (!$code) {
            return '';
        }
        
        // Check if URL shortening is enabled
        if ('yes' === get_option('wc_reward_points_short_urls', 'yes')) {
            return $this->get_short_referral_url($code);
        } else {
            return home_url('/rewards/refer/' . $code);
        }
    }
    
    /**
     * Generate and get a short referral URL
     *
     * @param string $code Referral code
     * @return string Short referral URL
     */
    private function get_short_referral_url($code) {
        global $wpdb;
        
        // Check if we already have a short URL for this code
        $short_code = get_option('wc_referral_short_' . $code, '');
        if ($short_code) {
            return home_url('/r/' . $short_code);
        }
        
        // Generate a new short code (4 characters)
        $short_code = $this->generate_short_code();
        
        // Store the mapping
        update_option('wc_referral_short_' . $code, $short_code);
        update_option('wc_referral_map_' . $short_code, $code);
        
        return home_url('/r/' . $short_code);
    }
    
    /**
     * Generate a unique short code
     *
     * @return string Short code
     */
    private function generate_short_code() {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = 4;
        
        do {
            $short_code = '';
            for ($i = 0; $i < $length; $i++) {
                $short_code .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Check if this code already exists
            $existing = get_option('wc_referral_map_' . $short_code, '');
            
        } while ($existing);
        
        return $short_code;
    }
    
    /**
     * Handle short referral URL redirect
     */
    public function handle_short_referral_url() {
        global $wp_query;
        
        // Check if we're on a short referral URL
        if (!isset($wp_query->query_vars['r'])) {
            return;
        }
        
        // Get the short code from URL
        $short_code = get_query_var('r');
        if (!$short_code) {
            wp_redirect(home_url());
            exit;
        }
        
        // Look up the original referral code
        $referral_code = get_option('wc_referral_map_' . $short_code, '');
        if (!$referral_code) {
            wp_redirect(home_url());
            exit;
        }
        
        // Redirect to the full referral URL
        wp_redirect(home_url('/rewards/refer/' . $referral_code));
        exit;
    }

    /**
     * Handle referral endpoint
     */
    public function handle_referral_endpoint() {
        global $wp_query, $wpdb;

        // Check if we're on the referral endpoint
        if (!isset($wp_query->query_vars['rewards/refer'])) {
            return;
        }

        // Get referral code from URL
        $referral_code = get_query_var('rewards/refer');
        if (!$referral_code) {
            wp_redirect(home_url());
            exit;
        }
        
        // Check if the referral code is valid and not expired
        if (!$this->is_valid_referral_code($referral_code)) {
            wc_add_notice(__('This referral code has expired or is invalid.', 'wc-reward-points'), 'error');
            wp_redirect(wc_get_page_permalink('shop'));
            exit;
        }

        // Store referral code in session
        WC()->session->set('referral_code', $referral_code);

        // If user is logged in, check if they're trying to use their own code
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $own_code = $this->get_referral_code($user_id);
            
            if ($own_code === $referral_code) {
                wc_add_notice(__('You cannot use your own referral link.', 'wc-reward-points'), 'error');
                wp_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }

            // Store email association for cross-device tracking
            $user_info = get_userdata($user_id);
            if ($user_info && $user_info->user_email) {
                $this->store_email_referral_association($user_info->user_email, $referral_code);
            }
        }

        // Redirect to registration page for new users
        if (!is_user_logged_in()) {
            // Store referral code in a persistent cookie (30 days)
            setcookie('wc_referral_code', $referral_code, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
            
            wp_redirect(add_query_arg('referral', 'yes', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Show success message for logged-in users
        $points = get_option('wc_reward_points_referee_points', 1000);
        $message = get_option('wc_reward_points_referral_success', 
            __('Thanks for using a referral link! {points} points will be added to your account after your first order.', 'wc-reward-points')
        );
        $message = str_replace('{points}', $points, $message);
        wc_add_notice($message, 'success');
        
        wp_redirect(wc_get_page_permalink('shop'));
        exit;
    }

    /**
     * Store email to referral code association for cross-device tracking
     *
     * @param string $email User email
     * @param string $referral_code Referral code
     * @return bool Success status
     */
    private function store_email_referral_association($email, $referral_code) {
        global $wpdb;
        
        // Get expiration date
        $expiry_days = get_option('wc_reward_points_referral_expiry_days', 30);
        $expires_at = $expiry_days > 0 
            ? date('Y-m-d H:i:s', strtotime("+{$expiry_days} days")) 
            : null;
        
        // Check if email already has a referral association
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wc_rewards_referral_emails
            WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            // Update existing record
            return $wpdb->update(
                "{$wpdb->prefix}wc_rewards_referral_emails",
                array(
                    'referral_code' => $referral_code,
                    'created_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                ),
                array('email' => $email),
                array('%s', '%s', '%s'),
                array('%s')
            );
        } else {
            // Insert new record
            return $wpdb->insert(
                "{$wpdb->prefix}wc_rewards_referral_emails",
                array(
                    'email' => $email,
                    'referral_code' => $referral_code,
                    'created_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                ),
                array('%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Get referral code by email for cross-device tracking
     *
     * @param string $email User email
     * @return string|bool Referral code or false if not found
     */
    private function get_referral_code_by_email($email) {
        global $wpdb;
        
        $code = $wpdb->get_var($wpdb->prepare(
            "SELECT referral_code FROM {$wpdb->prefix}wc_rewards_referral_emails
            WHERE email = %s AND (expires_at IS NULL OR expires_at > %s)",
            $email,
            current_time('mysql')
        ));
        
        return $code ? $code : false;
    }

    /**
     * Process referral when order is completed
     *
     * @param int $order_id Order ID
     */
    public function process_referral_order($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        
        // Get referral code from various sources
        $referral_code = WC()->session->get('referral_code');
        
        // If not in session, check other sources
        if (!$referral_code) {
            if ($user_id) {
                // For registered users, check email association
                $user_data = get_userdata($user_id);
                if ($user_data && !empty($user_data->user_email)) {
                    $referral_code = $this->get_referral_code_by_email($user_data->user_email);
                }
            } else {
                // For guest users, check billing email
                $billing_email = $order->get_billing_email();
                if ($billing_email) {
                    $referral_code = $this->get_referral_code_by_email($billing_email);
                }
            }
            
            // If still not found, check for cookie (backwards compatibility)
            if (!$referral_code && isset($_COOKIE['wc_referral_code'])) {
                $referral_code = sanitize_text_field($_COOKIE['wc_referral_code']);
            }
        }
        
        // For guest checkouts, store the referral code with the order for later processing
        if (!$user_id && $referral_code) {
            // First check if the code is valid and not expired
            if (!$this->is_valid_referral_code($referral_code)) {
                // If the code is invalid or expired, log it and don't store
                $order->add_order_note(sprintf(
                    __('Attempted to use expired or invalid referral code: %s', 'wc-reward-points'),
                    $referral_code
                ));
                
                // Clear the invalid code
                WC()->session->set('referral_code', null);
                if (isset($_COOKIE['wc_referral_code'])) {
                    setcookie('wc_referral_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
                }
                
                // Skip to ambassador processing
                $referral_code = '';
            } else {
                update_post_meta($order_id, '_wc_guest_referral_code', $referral_code);
                
                // Store association with email for future retrieval
                $billing_email = $order->get_billing_email();
                if ($billing_email) {
                    $this->store_email_referral_association($billing_email, $referral_code);
                }
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Guest checkout with referral code: %s. Rewards will be applied when account is created.', 'wc-reward-points'),
                    $referral_code
                ));
                
                // Clear referral code from session
                WC()->session->set('referral_code', null);
            }
        }
        
        // Process regular referral for registered users with first order
        if ($user_id && $this->is_first_order($user_id) && $referral_code) {
            // First check if the code is valid and not expired
            if (!$this->is_valid_referral_code($referral_code)) {
                // If the code is invalid or expired, log it and don't process
                $order->add_order_note(sprintf(
                    __('Attempted to use expired or invalid referral code: %s', 'wc-reward-points'),
                    $referral_code
                ));
                
                // Clear the invalid code
                WC()->session->set('referral_code', null);
                if (isset($_COOKIE['wc_referral_code'])) {
                    setcookie('wc_referral_code', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
                }
            } else {
                // Find referrer
                $args = array(
                    'meta_key'    => '_wc_referral_code',
                    'meta_value'  => $referral_code,
                    'number'      => 1,
                    'fields'      => 'ID',
                );
                $referrer = get_users($args);

                if (!empty($referrer)) {
                    $referrer_id = $referrer[0];
                    
                    // Check monthly limit
                    if ($this->check_monthly_limit($referrer_id)) {
                        // Check minimum order amount
                        $min_amount = get_option('wc_reward_points_referral_min_order', 0);
                        if (!($min_amount > 0 && $order->get_total() < $min_amount)) {
                            // Award points to referrer
                            $referrer_points = get_option('wc_reward_points_referrer_points', 1000);
                            $this->points_manager->add_points(
                                $referrer_id,
                                $referrer_points,
                                'referral',
                                sprintf(__('Referral reward for order #%s', 'wc-reward-points'), $order_id)
                            );

                            // Award points to referee
                            $referee_points = get_option('wc_reward_points_referee_points', 1000);
                            $this->points_manager->add_points(
                                $user_id,
                                $referee_points,
                                'referral',
                                __('Reward for using referral link', 'wc-reward-points')
                            );

                            // Clear referral code from session
                            WC()->session->set('referral_code', null);
                            
                            // Mark as processed
                            update_post_meta($order_id, '_wc_reward_points_processed', true);
                        }
                    }
                }
            }
        }
        
        // Handle ambassador commissions
        // Check if order has ambassador code
        $ambassador_code = get_post_meta($order_id, '_wc_ambassador_code', true);
        
        // If no code stored in order meta, check for code in session or cookie
        if (!$ambassador_code) {
            $ambassador_code = WC()->session->get('ambassador_code');
            
            // If not in session, check cookie
            if (!$ambassador_code && isset($_COOKIE['wc_ambassador_code'])) {
                $ambassador_code = sanitize_text_field($_COOKIE['wc_ambassador_code']);
            }
            
            // Store the code in order meta if found
            if ($ambassador_code) {
                update_post_meta($order_id, '_wc_ambassador_code', $ambassador_code);
            }
        }
        
        // For guest checkouts with ambassador code, add note and store for later
        if (!$user_id && $ambassador_code) {
            $order->add_order_note(sprintf(
                __('Guest checkout with ambassador code: %s. Commission will be processed when account is created.', 'wc-reward-points'),
                $ambassador_code
            ));
            
            // Clear ambassador code from session
            WC()->session->set('ambassador_code', null);
        }
        // For registered users, process ambassador commission immediately
        else if ($user_id && $ambassador_code) {
            // Find ambassador by code
            $args = array(
                'meta_key'    => '_wc_ambassador_code',
                'meta_value'  => $ambassador_code,
                'number'      => 1,
                'fields'      => 'ID',
            );
            $ambassador = get_users($args);
            
            if (!empty($ambassador)) {
                $ambassador_id = $ambassador[0];
                
                // Process commission with tax-aware calculation
                $this->process_ambassador_commission($order_id, $ambassador_id);
                
                // Clear ambassador code from session
                WC()->session->set('ambassador_code', null);
            }
        }
    }

    /**
     * Check if this is user's first order
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function is_first_order($user_id) {
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status'  => array('completed'),
            'limit'   => 1,
        ));

        return count($orders) === 1;
    }

    /**
     * Check monthly referral limit
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function check_monthly_limit($user_id) {
        $limit = get_option('wc_reward_points_referral_limit', 0);
        if ($limit === 0) {
            return true;
        }

        $month_start = strtotime('first day of this month midnight');
        $month_end = strtotime('last day of this month 23:59:59');

        $args = array(
            'user_id' => $user_id,
            'type'    => 'referral',
            'date'    => array($month_start, $month_end),
        );

        $count = $this->points_manager->get_points_log_count($args);
        return $count < $limit;
    }

    /**
     * Render referral widget shortcode
     *
     * @return string Widget HTML
     */
    public function render_referral_widget() {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        $referral_url = $this->get_referral_url($user_id);
        
        // Get enabled social networks
        $networks = array();
        if (get_option('wc_reward_points_enable_facebook', 'yes') === 'yes') {
            $networks['facebook'] = __('Facebook', 'wc-reward-points');
        }
        if (get_option('wc_reward_points_enable_twitter', 'yes') === 'yes') {
            $networks['twitter'] = __('Twitter', 'wc-reward-points');
        }
        if (get_option('wc_reward_points_enable_whatsapp', 'yes') === 'yes') {
            $networks['whatsapp'] = __('WhatsApp', 'wc-reward-points');
        }

        // Get share message
        $points = get_option('wc_reward_points_referee_points', 1000);
        $message = get_option('wc_reward_points_share_message');
        $message = str_replace(
            array('{store_name}', '{points}'),
            array(get_bloginfo('name'), $points),
            $message
        );

        // Load template
        ob_start();
        include WC_REWARD_POINTS_PLUGIN_DIR . 'templates/referral-widget.php';
        return ob_get_clean();
    }

    /**
     * Initialize functionality
     */
    public function init() {
        // Add short URL endpoint if enabled
        if ('yes' === get_option('wc_reward_points_short_urls', 'yes')) {
            add_rewrite_rule('^r/([^/]+)/?$', 'index.php?r=$matches[1]', 'top');
            add_rewrite_tag('%r%', '([^/]+)');
            
            // Handle short URL redirects
            add_action('template_redirect', array($this, 'handle_short_referral_url'));
        }
        
        // Check for referral code in URL parameter
        if (isset($_GET['ref'])) {
            $code = sanitize_text_field($_GET['ref']);
            $this->store_referral_code($code);
            
            // Also store in cookie for cross-device tracking
            setcookie('wc_referral_code', $code, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
            
            // If user is logged in, store email association
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user_data = get_userdata($user_id);
                if ($user_data && !empty($user_data->user_email)) {
                    $this->store_email_referral_association($user_data->user_email, $code);
                }
            }
        }
    }

    /**
     * Enqueue required scripts.
     */
    public function enqueue_scripts() {
        wp_enqueue_script('clipboard');
        wp_enqueue_style('dashicons');
    }

    /**
     * Store referral code in session.
     *
     * @param string $code Referral code.
     */
    private function store_referral_code($code) {
        if (!WC()->session) {
            return;
        }

        // Validate code
        $referrer_id = $this->get_user_by_code($code);
        if (!$referrer_id) {
            return;
        }

        // Don't allow self-referral
        if (is_user_logged_in() && get_current_user_id() === $referrer_id) {
            return;
        }

        // Store code in session
        WC()->session->set('referral_code', $code);
    }

    /**
     * Get user ID by referral code.
     *
     * @param string $code Referral code.
     * @return int|false User ID or false if not found.
     */
    private function get_user_by_code($code) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = '_wc_ambassador_code' 
            AND meta_value = %s",
            $code
        ));

        return $user_id ? absint($user_id) : false;
    }

    /**
     * Handle ambassador application submission.
     */
    public function handle_ambassador_application() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wc_ambassador_application')) {
            wp_die(__('Invalid request.', 'wc-reward-points'));
        }

        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to apply.', 'wc-reward-points'));
        }

        $user_id = get_current_user_id();

        // Check if already an ambassador
        if (get_user_meta($user_id, '_wc_ambassador_code', true)) {
            wp_die(__('You are already a brand ambassador.', 'wc-reward-points'));
        }

        // Check if has pending application
        if (get_user_meta($user_id, '_wc_ambassador_application_date', true)) {
            wp_die(__('You already have a pending application.', 'wc-reward-points'));
        }

        // Check for application rate limiting using the new rate limiter
        $cooldown_days = absint(get_option('wc_reward_points_application_cooldown', 30));
        $limit = 1; // Only 1 application allowed in the timeframe
        $timeframe = $cooldown_days * DAY_IN_SECONDS;
        
        // Use the rate limiter class for all rate limiting
        $rate_check = \WC_Reward_Points\Core\WC_Reward_Points_Rate_Limiter::check_rate_limit(
            'ambassador_application',
            'ambassador_apply',
            null, // Use default identity
            $limit,
            $timeframe
        );
        
        if ($rate_check['limited']) {
            $days_remaining = ceil(($rate_check['reset'] - time()) / DAY_IN_SECONDS);
            wp_die(sprintf(
                __('You have recently submitted an application. Please wait %d days before applying again.', 'wc-reward-points'),
                $days_remaining
            ));
        }

        // Validate required fields
        if (empty($_POST['ambassador_why']) || empty($_POST['ambassador_reach'])) {
            wp_die(__('Please fill in all required fields.', 'wc-reward-points'));
        }

        // Store application data
        update_user_meta($user_id, '_wc_ambassador_application_date', current_time('mysql'));
        update_user_meta($user_id, '_wc_ambassador_application_why', sanitize_textarea_field($_POST['ambassador_why']));
        update_user_meta($user_id, '_wc_ambassador_application_reach', sanitize_textarea_field($_POST['ambassador_reach']));
        update_user_meta($user_id, '_wc_ambassador_application_social', sanitize_text_field($_POST['ambassador_social']));
        
        // Record the application attempt in the rate limiter
        \WC_Reward_Points\Core\WC_Reward_Points_Rate_Limiter::record_attempt(
            'ambassador_application',
            'ambassador_apply',
            null, // Use default identity
            $limit,
            $timeframe
        );

        // Notify admin
        $this->send_admin_notification($user_id);

        // Redirect back with success message
        wp_safe_redirect(add_query_arg('application_submitted', '1', wp_get_referer()));
        exit;
    }

    /**
     * Send admin notification about new ambassador application.
     *
     * @param int $user_id User ID.
     */
    private function send_admin_notification($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $subject = sprintf(
            __('New Brand Ambassador Application from %s', 'wc-reward-points'),
            $user->display_name
        );

        $message = sprintf(
            __('A new brand ambassador application has been submitted by %1$s (%2$s).', 'wc-reward-points'),
            $user->display_name,
            $user->user_email
        );

        $admin_email = get_option('admin_email');
        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Add ambassador endpoint to My Account.
     */
    public function add_ambassador_endpoint() {
        add_rewrite_endpoint('ambassador', EP_ROOT | EP_PAGES);
    }

    /**
     * Add ambassador query var.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_ambassador_query_var($vars) {
        $vars[] = 'ambassador';
        return $vars;
    }

    /**
     * Add ambassador menu item to My Account menu.
     *
     * @param array $items Menu items.
     * @return array
     */
    public function add_ambassador_menu_item($items) {
        // Only show for ambassadors
        if (is_user_logged_in() && get_user_meta(get_current_user_id(), '_wc_ambassador_code', true)) {
            $items['ambassador'] = __('Ambassador Dashboard', 'wc-reward-points');
        }
        return $items;
    }

    /**
     * Render ambassador dashboard.
     */
    public function render_ambassador_dashboard() {
        wc_get_template(
            'ambassador-dashboard.php',
            array(),
            '',
            plugin_dir_path(WC_REWARD_POINTS_FILE) . 'templates/'
        );
    }

    /**
     * Generate unique ambassador code.
     *
     * @param int $user_id User ID.
     * @return string
     */
    public static function generate_ambassador_code($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return '';
        }

        // Generate base code from username
        $base = sanitize_title($user->user_login);
        $code = $base;
        $suffix = 1;

        // Ensure uniqueness
        while (self::code_exists($code)) {
            $code = $base . $suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * Check if ambassador code exists.
     *
     * @param string $code Ambassador code.
     * @return bool
     */
    private static function code_exists($code) {
        global $wpdb;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
            WHERE meta_key = '_wc_ambassador_code' 
            AND meta_value = %s",
            $code
        ));

        return $exists > 0;
    }

    /**
     * Store referral association for new user.
     *
     * @param int $user_id New user ID
     */
    public function store_referral_for_new_user($user_id) {
        // Get referral code from cookie
        $cookie_referral = isset($_COOKIE['wc_referral_code']) ? sanitize_text_field($_COOKIE['wc_referral_code']) : '';
        
        // Get user email
        $user_data = get_userdata($user_id);
        if (!$user_data || empty($user_data->user_email)) {
            return;
        }
        
        // If we have a cookie referral, store the association
        if ($cookie_referral) {
            $this->store_email_referral_association($user_data->user_email, $cookie_referral);
        }
        
        // Check for pending guest checkout orders that should be rewarded
        $this->check_pending_guest_checkouts($user_id, $user_data->user_email);
    }
    
    /**
     * Check for pending guest checkout orders that should be rewarded
     *
     * @param int $user_id The new user ID
     * @param string $email The user's email address
     */
    private function check_pending_guest_checkouts($user_id, $email) {
        global $wpdb;
        
        // Get completed orders with this email that don't have user ID (guest checkouts)
        $orders = wc_get_orders(array(
            'status' => array('completed', 'processing'),
            'email' => $email,
            'limit' => -1,
            'return' => 'ids',
            'date_created' => '>' . (time() - (30 * DAY_IN_SECONDS)) // Only look at last 30 days
        ));
        
        if (empty($orders)) {
            return;
        }
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            
            // Skip if not a guest order or already processed
            if ($order->get_user_id() != 0 || get_post_meta($order_id, '_wc_reward_points_processed', true)) {
                continue;
            }
            
            // Check for referral code associated with this order
            $referral_code = get_post_meta($order_id, '_wc_guest_referral_code', true);
            
            if (!$referral_code) {
                // Check if there's a referral code associated with this email
                $referral_code = $this->get_referral_code_by_email($email);
            }
            
            if ($referral_code) {
                // Find referrer
                $args = array(
                    'meta_key'    => '_wc_referral_code',
                    'meta_value'  => $referral_code,
                    'number'      => 1,
                    'fields'      => 'ID',
                );
                $referrer = get_users($args);

                if (!empty($referrer)) {
                    $referrer_id = $referrer[0];
                    
                    // Check monthly limit
                    if ($this->check_monthly_limit($referrer_id)) {
                        // Check minimum order amount
                        $min_amount = get_option('wc_reward_points_referral_min_order', 0);
                        if (!($min_amount > 0 && $order->get_total() < $min_amount)) {
                            // Award points to referrer
                            $referrer_points = get_option('wc_reward_points_referrer_points', 1000);
                            $this->points_manager->add_points(
                                $referrer_id,
                                $referrer_points,
                                'referral',
                                sprintf(__('Referral reward for guest order #%s (account created later)', 'wc-reward-points'), $order_id)
                            );

                            // Award points to new user
                            $referee_points = get_option('wc_reward_points_referee_points', 1000);
                            $this->points_manager->add_points(
                                $user_id,
                                $referee_points,
                                'referral',
                                __('Reward for using referral link on previous guest order', 'wc-reward-points')
                            );
                            
                            // Update the order user ID to the new account
                            update_post_meta($order_id, '_customer_user', $user_id);
                            
                            // Add order note
                            $order->add_order_note(sprintf(
                                __('Guest order linked to new account %s. Referral rewards applied.', 'wc-reward-points'),
                                $email
                            ));
                        }
                    }
                }
            }
            
            // Also check for ambassador commissions
            $ambassador_code = get_post_meta($order_id, '_wc_ambassador_code', true);
            
            if ($ambassador_code) {
                // Find ambassador by code
                $args = array(
                    'meta_key'    => '_wc_ambassador_code',
                    'meta_value'  => $ambassador_code,
                    'number'      => 1,
                    'fields'      => 'ID',
                );
                $ambassador = get_users($args);
                
                if (!empty($ambassador)) {
                    $ambassador_id = $ambassador[0];
                    
                    // Process commission
                    $this->process_ambassador_commission($order_id, $ambassador_id);
                    
                    // Add order note
                    $order->add_order_note(sprintf(
                        __('Ambassador commission processed for guest order after account creation.', 'wc-reward-points')
                    ));
                }
            }
            
            // Mark order as processed
            update_post_meta($order_id, '_wc_reward_points_processed', true);
        }
    }

    /**
     * Calculate ambassador commission for an order
     * 
     * @param WC_Order $order The order to calculate commission for
     * @return float The calculated commission amount
     */
    public function calculate_commission($order) {
        // Get the commission rate (percent)
        $commission_rate = floatval(get_option('wc_reward_points_ambassador_commission_rate', 6)) / 100;
        
        // Get tax handling setting
        $tax_handling = get_option('wc_reward_points_ambassador_tax_handling', 'include');
        
        // Calculate commission based on tax handling setting
        switch ($tax_handling) {
            case 'exclude':
                // Use total excluding tax
                $amount = $order->get_total() - $order->get_total_tax();
                break;
            case 'subtotal':
                // Use subtotal only (no tax, no shipping)
                $amount = $order->get_subtotal();
                break;
            case 'include':
            default:
                // Use total including tax (default)
                $amount = $order->get_total();
                break;
        }
        
        // Apply filters to allow for custom commission calculations
        $commission = apply_filters('wc_reward_points_ambassador_commission', $amount * $commission_rate, $order, $commission_rate);
        
        return round($commission, 2);
    }

    /**
     * Process ambassador commission for an order
     * 
     * @param int $order_id The order ID
     * @param int $ambassador_id The ambassador user ID
     * @return bool True if processed successfully, false otherwise
     */
    public function process_ambassador_commission($order_id, $ambassador_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $customer_id = $order->get_user_id();
        
        // Prevent self-referral
        if ($customer_id && $customer_id == $ambassador_id) {
            return false;
        }
        
        // Check if this order has already been processed for this ambassador
        $processed = get_post_meta($order_id, '_wc_ambassador_commission_processed_' . $ambassador_id, true);
        if ($processed) {
            return false;
        }
        
        // Calculate commission
        $commission = $this->calculate_commission($order);
        
        // Convert commission to points
        $points_conversion_rate = floatval(get_option('wc_reward_points_currency_rate', 100)); // Default: 100 points = $1
        $points = round($commission * $points_conversion_rate);
        
        // Award points to ambassador
        $this->points_manager->add_points(
            $ambassador_id,
            $points,
            'ambassador_commission',
            sprintf(__('Commission for referred order #%s', 'wc-reward-points'), $order->get_order_number())
        );
        
        // Log commission in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_reward_points_ambassador_earnings';
        
        $wpdb->insert(
            $table_name,
            array(
                'ambassador_id' => $ambassador_id,
                'order_id' => $order_id,
                'order_total' => $order->get_total(),
                'commission_amount' => $commission,
                'points_awarded' => $points,
                'date_created' => current_time('mysql'),
            ),
            array('%d', '%d', '%f', '%f', '%d', '%s')
        );
        
        // Mark as processed
        update_post_meta($order_id, '_wc_ambassador_commission_processed_' . $ambassador_id, 1);
        
        // Add note to order
        $order->add_order_note(
            sprintf(
                __('Ambassador commission processed: %s points awarded to ambassador (ID: %d)', 'wc-reward-points'),
                $points,
                $ambassador_id
            )
        );
        
        // Update ambassador statistics
        $total_earnings = get_user_meta($ambassador_id, '_wc_ambassador_total_earnings', true) ?: 0;
        $total_referrals = get_user_meta($ambassador_id, '_wc_ambassador_total_referrals', true) ?: 0;
        
        update_user_meta($ambassador_id, '_wc_ambassador_total_earnings', $total_earnings + $commission);
        update_user_meta($ambassador_id, '_wc_ambassador_total_referrals', $total_referrals + 1);
        
        do_action('wc_reward_points_ambassador_commission_processed', $ambassador_id, $order_id, $commission, $points);
        
        return true;
    }

    /**
     * Check and apply ambassador performance bonuses.
     * This should be called by a scheduled event at the end of each month.
     */
    public function process_ambassador_performance_bonuses() {
        global $wpdb;
        
        // Get ambassador bonus settings
        $bonus_threshold = absint(get_option('wc_reward_points_ambassador_bonus_threshold', 1000));
        $bonus_amount = absint(get_option('wc_reward_points_ambassador_bonus_amount', 1000));
        
        // If bonus is disabled, return
        if ($bonus_threshold <= 0 || $bonus_amount <= 0) {
            return;
        }
        
        // Calculate first day of previous month and last day of previous month
        $last_month_start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $last_month_end = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        
        // Get all ambassadors
        $ambassador_users = get_users(array(
            'meta_key' => '_wc_ambassador_code',
            'meta_compare' => 'EXISTS',
        ));
        
        // Process each ambassador
        foreach ($ambassador_users as $user) {
            $user_id = $user->ID;
            
            // Calculate ambassador's total sales for last month
            $referrals_table = $wpdb->prefix . 'wc_reward_points_referrals';
            $monthly_sales = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(order_total) 
                FROM $referrals_table 
                WHERE referrer_id = %d 
                AND date_created BETWEEN %s AND %s 
                AND status = 'complete'",
                $user_id,
                $last_month_start,
                $last_month_end
            ));
            
            // Convert to numeric value
            $monthly_sales = floatval($monthly_sales);
            
            // If ambassador meets or exceeds the threshold, award bonus points
            if ($monthly_sales >= $bonus_threshold) {
                // Add points to ambassador
                $points_added = $this->add_points_to_user($user_id, $bonus_amount);
                
                if ($points_added) {
                    // Log the bonus
                    $log_entry = sprintf(
                        __('Performance bonus awarded: %d points for generating $%s in sales during %s', 'wc-reward-points'),
                        $bonus_amount,
                        number_format($monthly_sales, 2),
                        date_i18n('F Y', strtotime($last_month_start))
                    );
                    
                    $this->log_points_transaction($user_id, $bonus_amount, 'bonus', $log_entry);
                    
                    // Notify the ambassador
                    $this->send_ambassador_bonus_notification($user_id, $bonus_amount, $monthly_sales);
                }
            }
        }
    }
    
    /**
     * Check and apply ambassador inactivity penalties.
     * This should be called by a scheduled event at the start of each month.
     */
    public function process_ambassador_inactivity_penalties() {
        global $wpdb;
        
        // Get ambassador penalty settings
        $penalty_threshold = absint(get_option('wc_reward_points_ambassador_penalty_threshold', 3));
        $penalty_percent = absint(get_option('wc_reward_points_ambassador_penalty_percent', 10));
        
        // If penalty is disabled, return
        if ($penalty_threshold <= 0 || $penalty_percent <= 0) {
            return;
        }
        
        // Calculate date threshold for inactivity (x months ago)
        $inactivity_date = date('Y-m-d H:i:s', strtotime("-{$penalty_threshold} months"));
        
        // Get all ambassadors
        $ambassador_users = get_users(array(
            'meta_key' => '_wc_ambassador_code',
            'meta_compare' => 'EXISTS',
        ));
        
        // Get referrals table name
        $referrals_table = $wpdb->prefix . 'wc_reward_points_referrals';
        
        // Process each ambassador
        foreach ($ambassador_users as $user) {
            $user_id = $user->ID;
            
            // Check when ambassador last had a successful referral
            $last_activity = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(date_created) 
                FROM $referrals_table 
                WHERE referrer_id = %d 
                AND status = 'complete'",
                $user_id
            ));
            
            // If no activity found or last activity was before threshold
            if (!$last_activity || strtotime($last_activity) < strtotime($inactivity_date)) {
                // Get current points balance
                $current_points = get_user_meta($user_id, '_wc_reward_points_balance', true);
                $current_points = absint($current_points);
                
                if ($current_points > 0) {
                    // Calculate penalty points
                    $penalty_points = floor($current_points * ($penalty_percent / 100));
                    
                    if ($penalty_points > 0) {
                        // Deduct points from ambassador
                        $new_balance = max(0, $current_points - $penalty_points);
                        update_user_meta($user_id, '_wc_reward_points_balance', $new_balance);
                        
                        // Log the penalty
                        $log_entry = sprintf(
                            __('Inactivity penalty applied: %d points deducted (%d%% of balance) for %d months of inactivity', 'wc-reward-points'),
                            $penalty_points,
                            $penalty_percent,
                            $penalty_threshold
                        );
                        
                        $this->log_points_transaction($user_id, -$penalty_points, 'penalty', $log_entry);
                        
                        // Notify the ambassador
                        $this->send_ambassador_penalty_notification($user_id, $penalty_points, $penalty_percent);
                    }
                }
            }
        }
    }
    
    /**
     * Add points to a user account.
     *
     * @param int $user_id User ID.
     * @param int $points Points to add.
     * @return bool Whether points were added.
     */
    private function add_points_to_user($user_id, $points) {
        $current_points = get_user_meta($user_id, '_wc_reward_points_balance', true);
        $current_points = absint($current_points);
        $new_balance = $current_points + absint($points);
        
        return update_user_meta($user_id, '_wc_reward_points_balance', $new_balance);
    }
    
    /**
     * Log a points transaction.
     *
     * @param int $user_id User ID.
     * @param int $points Points amount (negative for deductions).
     * @param string $type Transaction type.
     * @param string $note Transaction note.
     */
    private function log_points_transaction($user_id, $points, $type, $note) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_reward_points_transactions';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'points' => $points,
                'type' => $type,
                'note' => $note,
                'date_created' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Send notification about performance bonus.
     *
     * @param int $user_id User ID.
     * @param int $bonus_amount Bonus points awarded.
     * @param float $monthly_sales Monthly sales amount.
     */
    private function send_ambassador_bonus_notification($user_id, $bonus_amount, $monthly_sales) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = __('Congratulations! You\'ve earned a performance bonus', 'wc-reward-points');
        
        $message = sprintf(
            __('Hi %s,

Great job on your ambassador performance last month! You\'ve earned a bonus of %d points for generating $%s in sales.

This bonus has been added to your rewards account and can be used for future purchases.

Thank you for being an outstanding brand ambassador!

Regards,
%s', 'wc-reward-points'),
            $user->display_name,
            $bonus_amount,
            number_format($monthly_sales, 2),
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send notification about inactivity penalty.
     *
     * @param int $user_id User ID.
     * @param int $penalty_points Points deducted.
     * @param int $penalty_percent Penalty percentage.
     */
    private function send_ambassador_penalty_notification($user_id, $penalty_points, $penalty_percent) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = __('Notice: Inactivity penalty applied to your rewards account', 'wc-reward-points');
        
        $message = sprintf(
            __('Hi %s,

We\'ve noticed that there hasn\'t been any activity on your ambassador account for some time.

According to our ambassador program terms, an inactivity penalty of %d%% has been applied to your rewards account. %d points have been deducted from your balance.

To avoid future penalties and maintain your ambassador status, please continue to refer customers to our store.

If you have any questions, please contact our customer support team.

Regards,
%s', 'wc-reward-points'),
            $user->display_name,
            $penalty_percent,
            $penalty_points,
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
} 