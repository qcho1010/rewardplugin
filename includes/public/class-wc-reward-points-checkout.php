<?php
/**
 * Points Checkout Integration
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 */

namespace WC_Reward_Points\Public;

use WC_Reward_Points\Core\WC_Reward_Points_Debug;
use WC_Reward_Points\Core\WC_Reward_Points_Manager;

/**
 * Handles points redemption at checkout
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 * @author     Kyu Cho
 */
class WC_Reward_Points_Checkout {

    /**
     * The points manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Manager    $points_manager    The points manager instance.
     */
    private $points_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->points_manager = new WC_Reward_Points_Manager();
        
        // Add the points redemption field to the checkout
        add_action('woocommerce_before_checkout_form', array($this, 'add_points_redemption_field'), 10);
        
        // Handle points redemption AJAX call
        add_action('wp_ajax_apply_reward_points', array($this, 'apply_reward_points'));
        add_action('wp_ajax_nopriv_apply_reward_points', array($this, 'apply_reward_points'));
        
        // Add points discount to the cart
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_points_discount'));
        
        // Save redeemed points to the order
        add_action('woocommerce_checkout_order_processed', array($this, 'save_redeemed_points'), 10, 3);
        
        // Display redeemed points in order emails and order details
        add_action('woocommerce_email_after_order_table', array($this, 'display_points_in_email'), 10, 4);
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_points_in_order_details'), 10);
    }

    /**
     * Add points redemption field to the checkout page.
     *
     * @since    1.0.0
     */
    public function add_points_redemption_field() {
        // Only show for logged in users
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $points_balance = $this->points_manager->get_user_points($user_id);
        $conversion_rate = get_option('wc_reward_points_conversion_rate', 100); // Default: 100 points = $1
        
        // Get the maximum points redemption setting
        $max_redemption_percentage = get_option('wc_reward_points_max_redemption_percentage', 50); // Default 50%
        $max_points_per_order = get_option('wc_reward_points_max_points_per_order', 0); // 0 means no limit except percentage
        
        // Get cart total
        $cart_total = WC()->cart->get_subtotal();
        
        // Calculate maximum points that can be redeemed based on percentage
        $max_points_by_percentage = ($cart_total * $max_redemption_percentage / 100) * $conversion_rate;
        
        // Use the lower of the two limits (if max_points_per_order is set)
        $max_points = ($max_points_per_order > 0) ? min($max_points_per_order, $max_points_by_percentage) : $max_points_by_percentage;
        
        // Cap at the user's balance
        $max_points = min($max_points, $points_balance);
        
        wc_get_template(
            'checkout/reward-points-redemption.php',
            array(
                'points_balance'    => $points_balance,
                'conversion_rate'   => $conversion_rate,
                'max_points'        => floor($max_points),
                'max_percentage'    => $max_redemption_percentage,
            ),
            '',
            WC_REWARD_POINTS_TEMPLATE_PATH
        );
    }

    /**
     * Handle the AJAX request to apply reward points.
     *
     * @since    1.0.0
     */
    public function apply_reward_points() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_reward_points_redemption')) {
            wp_send_json_error(__('Security check failed.', 'wc-reward-points'));
        }
        
        // Check user login
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in to redeem points', 'wc-reward-points'));
        }
        
        $user_id = get_current_user_id();
        $points_balance = $this->points_manager->get_user_points($user_id);
        $conversion_rate = get_option('wc_reward_points_conversion_rate', 100);
        
        // Get points to apply
        $applied_points = isset($_POST['points']) ? intval($_POST['points']) : 0;
        
        // Validate input
        if ($applied_points <= 0) {
            wp_send_json_error(__('Please enter a valid number of points.', 'wc-reward-points'));
        }
        
        // Check if user has enough points
        if ($applied_points > $points_balance) {
            wp_send_json_error(__('You do not have enough points.', 'wc-reward-points'));
        }
        
        // Get the maximum points redemption setting
        $max_redemption_percentage = get_option('wc_reward_points_max_redemption_percentage', 50);
        $max_points_per_order = get_option('wc_reward_points_max_points_per_order', 0);
        
        // Check against maximum points per order limit
        if ($max_points_per_order > 0 && $applied_points > $max_points_per_order) {
            wp_send_json_error(
                sprintf(
                    __('You can only redeem up to %s points for this order', 'wc-reward-points'),
                    number_format($max_points_per_order)
                )
            );
        }
        
        // Calculate cart total
        $cart_total = WC()->cart->get_subtotal();
        
        // Calculate maximum points that can be redeemed based on percentage
        $max_points_by_percentage = ($cart_total * $max_redemption_percentage / 100) * $conversion_rate;
        
        // Check against percentage limit
        if ($applied_points > $max_points_by_percentage) {
            wp_send_json_error(
                sprintf(
                    __('You can only redeem up to %s%% of the order total (%s points)', 'wc-reward-points'),
                    $max_redemption_percentage,
                    number_format(floor($max_points_by_percentage))
                )
            );
        }
        
        // Store in session
        WC()->session->set('wc_reward_points_applied', $applied_points);
        
        // Calculate discount
        $discount_amount = $applied_points / $conversion_rate;
        
        wp_send_json_success(array(
            'points' => $applied_points,
            'discount' => wc_price($discount_amount),
            'message' => sprintf(
                __('%s points applied for a discount of %s', 'wc-reward-points'),
                number_format($applied_points),
                wc_price($discount_amount)
            )
        ));
    }

    /**
     * Add the points discount to the cart.
     *
     * @since    1.0.0
     * @param    WC_Cart    $cart    The cart object.
     */
    public function add_points_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Check if points are being applied
        $applied_points = WC()->session->get('wc_reward_points_applied', 0);
        
        if ($applied_points > 0) {
            $conversion_rate = get_option('wc_reward_points_conversion_rate', 100);
            $discount_amount = $applied_points / $conversion_rate;
            
            // Double-check the applied points against current limits
            $user_id = get_current_user_id();
            $points_balance = $this->points_manager->get_user_points($user_id);
            
            // Get the maximum points redemption setting
            $max_redemption_percentage = get_option('wc_reward_points_max_redemption_percentage', 50);
            $max_points_per_order = get_option('wc_reward_points_max_points_per_order', 0);
            
            // Calculate cart total
            $cart_total = $cart->get_subtotal();
            
            // Calculate maximum points that can be redeemed based on percentage
            $max_points_by_percentage = ($cart_total * $max_redemption_percentage / 100) * $conversion_rate;
            
            // Use the lower of the two limits (if max_points_per_order is set)
            $max_points = ($max_points_per_order > 0) ? min($max_points_per_order, $max_points_by_percentage) : $max_points_by_percentage;
            
            // Cap at the user's balance
            $max_points = min($max_points, $points_balance);
            
            // If applied points exceed the maximum, adjust them
            if ($applied_points > $max_points) {
                $applied_points = floor($max_points);
                $discount_amount = $applied_points / $conversion_rate;
                WC()->session->set('wc_reward_points_applied', $applied_points);
            }
            
            if ($discount_amount > 0) {
                $cart->add_fee(
                    sprintf(__('Reward Points Discount (%s points)', 'wc-reward-points'), number_format($applied_points)),
                    -$discount_amount,
                    true,
                    'standard'
                );
            }
        }
    }

    /**
     * Save redeemed points to the order.
     *
     * @since    1.0.0
     * @param    int       $order_id    The order ID.
     * @param    array     $posted_data The posted data.
     * @param    WC_Order  $order       The order object.
     */
    public function save_redeemed_points($order_id, $posted_data, $order) {
        $applied_points = WC()->session->get('wc_reward_points_applied', 0);
        
        if ($applied_points > 0) {
            // Update order meta
            update_post_meta($order_id, '_wc_reward_points_redeemed', $applied_points);
            
            // Deduct points from user
            $user_id = $order->get_user_id();
            $this->points_manager->deduct_points(
                $user_id,
                $applied_points,
                'redemption',
                sprintf(__('Points redeemed for order #%s', 'wc-reward-points'), $order_id)
            );
            
            // Add order note
            $conversion_rate = get_option('wc_reward_points_conversion_rate', 100);
            $discount_amount = $applied_points / $conversion_rate;
            
            $order->add_order_note(
                sprintf(
                    __('%s reward points redeemed for a discount of %s', 'wc-reward-points'),
                    number_format($applied_points),
                    wc_price($discount_amount)
                )
            );
            
            // Clear session
            WC()->session->set('wc_reward_points_applied', 0);
        }
    }

    /**
     * Display redeemed points in order emails.
     *
     * @since    1.0.0
     * @param    WC_Order  $order           The order object.
     * @param    bool      $sent_to_admin   Whether the email is being sent to admin.
     * @param    bool      $plain_text      Whether the email is plain text.
     * @param    WC_Email  $email           The email object.
     */
    public function display_points_in_email($order, $sent_to_admin, $plain_text, $email) {
        $redeemed_points = get_post_meta($order->get_id(), '_wc_reward_points_redeemed', true);
        
        if ($redeemed_points) {
            $conversion_rate = get_option('wc_reward_points_conversion_rate', 100);
            $discount_amount = $redeemed_points / $conversion_rate;
            
            if ($plain_text) {
                echo "\n\n";
                echo sprintf(
                    __('Reward Points: %s points redeemed for a discount of %s', 'wc-reward-points'),
                    number_format($redeemed_points),
                    wc_price($discount_amount)
                );
                echo "\n\n";
            } else {
                echo '<h2>' . __('Reward Points', 'wc-reward-points') . '</h2>';
                echo '<p>' . sprintf(
                    __('%s points redeemed for a discount of %s', 'wc-reward-points'),
                    number_format($redeemed_points),
                    wc_price($discount_amount)
                ) . '</p>';
            }
        }
    }

    /**
     * Display redeemed points in order details.
     *
     * @since    1.0.0
     * @param    WC_Order  $order    The order object.
     */
    public function display_points_in_order_details($order) {
        $redeemed_points = get_post_meta($order->get_id(), '_wc_reward_points_redeemed', true);
        
        if ($redeemed_points) {
            $conversion_rate = get_option('wc_reward_points_conversion_rate', 100);
            $discount_amount = $redeemed_points / $conversion_rate;
            
            echo '<div class="wc-reward-points-redemption">';
            echo '<h2>' . __('Reward Points', 'wc-reward-points') . '</h2>';
            echo '<p>' . sprintf(
                __('%s points redeemed for a discount of %s', 'wc-reward-points'),
                number_format($redeemed_points),
                wc_price($discount_amount)
            ) . '</p>';
            echo '</div>';
        }
    }
}

new WC_Reward_Points_Checkout(); 