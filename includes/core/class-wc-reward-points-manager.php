<?php
/**
 * Points Management System
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

/**
 * Points Management System
 *
 * Handles all point-related operations including awarding, deducting,
 * and tracking points for users.
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 * @author     Kyu Cho
 */
class WC_Reward_Points_Manager {

    /**
     * The single instance of the class.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WC_Reward_Points_Manager    $instance    The single instance of the class.
     */
    protected static $instance = null;

    /**
     * Main Points Manager Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @since    1.0.0
     * @return   WC_Reward_Points_Manager    Main instance.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add points to a user's account
     *
     * @since    1.0.0
     * @param    int    $user_id       The user ID
     * @param    int    $points        Number of points to add
     * @param    string $type          Type of points (signup, referral, review)
     * @param    string $description   Description of the points transaction
     * @param    int    $reference_id  Optional reference ID (e.g., referral ID)
     * @return   bool|WP_Error         True on success, WP_Error on failure
     */
    public function add_points($user_id, $points, $type, $description = '', $reference_id = null) {
        global $wpdb;

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Validate inputs
            if (!$this->validate_points_input($user_id, $points, $type)) {
                throw new \Exception('Invalid input parameters');
            }

            // Log the points transaction
            $result = $wpdb->insert(
                $wpdb->prefix . 'wc_rewards_points_log',
                array(
                    'user_id' => $user_id,
                    'points' => $points,
                    'type' => $type,
                    'description' => $description,
                    'reference_id' => $reference_id,
                    'reference_type' => $type
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );

            if ($result === false) {
                throw new \Exception('Failed to log points transaction');
            }

            // Update user meta with new points total
            $current_points = $this->get_user_points($user_id);
            $new_points = $current_points + $points;
            update_user_meta($user_id, '_wc_reward_points_balance', $new_points);

            // Commit transaction
            $wpdb->query('COMMIT');

            // Fire action for points added
            do_action('wc_reward_points_added', $user_id, $points, $type, $new_points);

            return true;

        } catch (\Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            return new \WP_Error('points_add_failed', $e->getMessage());
        }
    }

    /**
     * Deduct points from a user's account
     *
     * @since    1.0.0
     * @param    int    $user_id       The user ID
     * @param    int    $points        Number of points to deduct
     * @param    string $type          Type of deduction
     * @param    string $description   Description of the deduction
     * @return   bool|WP_Error         True on success, WP_Error on failure
     */
    public function deduct_points($user_id, $points, $type, $description = '') {
        $current_points = $this->get_user_points($user_id);

        // Check if user has enough points
        if ($current_points < $points) {
            return new \WP_Error(
                'insufficient_points',
                sprintf(
                    __('Insufficient points. Required: %d, Available: %d', 'wc-reward-points'),
                    $points,
                    $current_points
                )
            );
        }

        // Deduct points (add negative points)
        return $this->add_points($user_id, -$points, $type, $description);
    }

    /**
     * Get user's current point balance
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID
     * @return   int                Current point balance
     */
    public function get_user_points($user_id) {
        $points = get_user_meta($user_id, '_wc_reward_points_balance', true);
        return empty($points) ? 0 : (int) $points;
    }

    /**
     * Check if user has already claimed a specific reward
     *
     * @since    1.0.0
     * @param    int    $user_id      The user ID
     * @param    string $reward_type  Type of reward to check
     * @return   bool                 True if already claimed
     */
    public function has_claimed_reward($user_id, $reward_type) {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_claims
            WHERE user_id = %d AND reward_type = %s AND claim_status = 'completed'",
            $user_id,
            $reward_type
        ));

        return $result > 0;
    }

    /**
     * Log a reward claim
     *
     * @since    1.0.0
     * @param    int    $user_id       The user ID
     * @param    string $reward_type   Type of reward claimed
     * @param    int    $points        Points awarded
     * @return   bool|WP_Error         True on success, WP_Error on failure
     */
    public function log_reward_claim($user_id, $reward_type, $points) {
        global $wpdb;

        // Get user's IP and user agent
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $result = $wpdb->insert(
            $wpdb->prefix . 'wc_rewards_claims',
            array(
                'user_id' => $user_id,
                'reward_type' => $reward_type,
                'points_awarded' => $points,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            return new \WP_Error(
                'claim_log_failed',
                __('Failed to log reward claim', 'wc-reward-points')
            );
        }

        return true;
    }

    /**
     * Validate points input parameters
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID
     * @param    int    $points     Number of points
     * @param    string $type       Type of points transaction
     * @return   bool               True if valid
     */
    private function validate_points_input($user_id, $points, $type) {
        // Check user exists
        if (!get_user_by('id', $user_id)) {
            return false;
        }

        // Check points is numeric
        if (!is_numeric($points)) {
            return false;
        }

        // Check type is valid
        $valid_types = array('signup', 'referral', 'review', 'redemption', 'manual', 'refund');
        if (!in_array($type, $valid_types)) {
            return false;
        }

        return true;
    }

    /**
     * Get points transaction history for a user
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID
     * @param    int    $limit      Number of records to return
     * @param    int    $offset     Offset for pagination
     * @return   array              Array of transaction records
     */
    public function get_points_history($user_id, $limit = 10, $offset = 0) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wc_rewards_points_log
            WHERE user_id = %d
            ORDER BY date_added DESC
            LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));

        return $results;
    }
} 