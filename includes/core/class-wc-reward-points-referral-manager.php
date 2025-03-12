<?php
/**
 * The referral management functionality of the plugin.
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

use WC_Reward_Points\Core\WC_Reward_Points_Points_Manager;
use WC_Reward_Points\Core\WC_Reward_Points_Debug;

/**
 * The referral management class.
 *
 * Handles all referral-related operations including code generation,
 * referral validation, and points distribution.
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 * @author     Kyu Cho
 */
class WC_Reward_Points_Referral_Manager {

    /**
     * The points manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Points_Manager    $points_manager    The points manager instance.
     */
    private $points_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->points_manager = new WC_Reward_Points_Points_Manager();
    }

    /**
     * Generate a unique referral code for a user.
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID.
     * @return   array              Array containing status and either code or error message.
     */
    public function generate_referral_code($user_id) {
        global $wpdb;

        try {
            // Validate user
            if (!get_user_by('id', $user_id)) {
                throw new \Exception('Invalid user ID');
            }

            // Get settings
            $prefix = get_option('wc_reward_points_referral_code_prefix', 'REF');
            $length = get_option('wc_reward_points_referral_code_length', 8);
            $expiry_days = get_option('wc_reward_points_referral_expiry_days', 30);

            // Generate unique code
            do {
                $random_string = wp_generate_password($length - strlen($prefix), false);
                $code = $prefix . $random_string;
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_referral_codes WHERE code = %s",
                    $code
                ));
            } while ($exists > 0);

            // Calculate expiry date
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));

            // Insert new code
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'wc_rewards_referral_codes',
                array(
                    'user_id' => $user_id,
                    'code' => $code,
                    'created_at' => current_time('mysql'),
                    'expires_at' => $expires_at,
                    'is_active' => 1
                ),
                array('%d', '%s', '%s', '%s', '%d')
            );

            if ($inserted === false) {
                throw new \Exception('Failed to insert referral code');
            }

            WC_Reward_Points_Debug::log(
                'Referral code generated',
                'INFO',
                array(
                    'user_id' => $user_id,
                    'code' => $code,
                    'expires_at' => $expires_at
                )
            );

            return array(
                'success' => true,
                'code' => $code,
                'expires_at' => $expires_at
            );

        } catch (\Exception $e) {
            WC_Reward_Points_Debug::log(
                'Referral code generation failed',
                'ERROR',
                array(
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                )
            );

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Process a referral when a new user registers using a referral code.
     *
     * @since    1.0.0
     * @param    int       $referee_id     The new user's ID.
     * @param    string    $referral_code  The referral code used.
     * @return   array                     Array containing status and message.
     */
    public function process_referral($referee_id, $referral_code) {
        global $wpdb;

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Validate referee
            if (!get_user_by('id', $referee_id)) {
                throw new \Exception('Invalid referee ID');
            }

            // Check if referee has already been referred
            $existing_referral = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_rewards_referrals WHERE referee_id = %d",
                $referee_id
            ));

            if ($existing_referral) {
                throw new \Exception('User has already been referred');
            }

            // Get referral code details
            $code_details = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_rewards_referral_codes 
                WHERE code = %s AND is_active = 1 AND expires_at > NOW()",
                $referral_code
            ));

            if (!$code_details) {
                throw new \Exception('Invalid or expired referral code');
            }

            // Prevent self-referral
            if ($code_details->user_id === $referee_id) {
                throw new \Exception('Cannot refer yourself');
            }

            // Get point values
            $referrer_points = get_option('wc_reward_points_referral_points_referrer', 1000);
            $referee_points = get_option('wc_reward_points_referral_points_referee', 1000);

            // Create referral record
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'wc_rewards_referrals',
                array(
                    'referral_code_id' => $code_details->id,
                    'referrer_id' => $code_details->user_id,
                    'referee_id' => $referee_id,
                    'points_awarded_referrer' => $referrer_points,
                    'points_awarded_referee' => $referee_points,
                    'status' => 'completed',
                    'created_at' => current_time('mysql'),
                    'completed_at' => current_time('mysql'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT']
                ),
                array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );

            if ($inserted === false) {
                throw new \Exception('Failed to create referral record');
            }

            // Award points to referrer
            $referrer_result = $this->points_manager->add_points(
                $code_details->user_id,
                $referrer_points,
                'referral_bonus',
                sprintf('Referral bonus for referring user #%d', $referee_id)
            );

            if (!$referrer_result['success']) {
                throw new \Exception('Failed to award points to referrer: ' . $referrer_result['error']);
            }

            // Award points to referee
            $referee_result = $this->points_manager->add_points(
                $referee_id,
                $referee_points,
                'referral_bonus',
                sprintf('Welcome bonus from referral by user #%d', $code_details->user_id)
            );

            if (!$referee_result['success']) {
                throw new \Exception('Failed to award points to referee: ' . $referee_result['error']);
            }

            // Update usage count
            $wpdb->update(
                $wpdb->prefix . 'wc_rewards_referral_codes',
                array(
                    'usage_count' => $code_details->usage_count + 1,
                    'last_used' => current_time('mysql')
                ),
                array('id' => $code_details->id),
                array('%d', '%s'),
                array('%d')
            );

            // Commit transaction
            $wpdb->query('COMMIT');

            WC_Reward_Points_Debug::log(
                'Referral processed successfully',
                'INFO',
                array(
                    'referrer_id' => $code_details->user_id,
                    'referee_id' => $referee_id,
                    'code' => $referral_code,
                    'points_awarded' => array(
                        'referrer' => $referrer_points,
                        'referee' => $referee_points
                    )
                )
            );

            return array(
                'success' => true,
                'message' => 'Referral processed successfully',
                'referrer_points' => $referrer_points,
                'referee_points' => $referee_points
            );

        } catch (\Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');

            WC_Reward_Points_Debug::log(
                'Referral processing failed',
                'ERROR',
                array(
                    'referee_id' => $referee_id,
                    'code' => $referral_code,
                    'error' => $e->getMessage()
                )
            );

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get user's referral statistics.
     *
     * @since    1.0.0
     * @param    int    $user_id    The user ID.
     * @return   array              Array containing referral statistics.
     */
    public function get_user_referral_stats($user_id) {
        global $wpdb;

        try {
            // Get total successful referrals
            $total_referrals = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_referrals 
                WHERE referrer_id = %d AND status = 'completed'",
                $user_id
            ));

            // Get total points earned from referrals
            $total_points = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points_awarded_referrer) FROM {$wpdb->prefix}wc_rewards_referrals 
                WHERE referrer_id = %d AND status = 'completed'",
                $user_id
            ));

            // Get active referral codes
            $active_codes = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_rewards_referral_codes 
                WHERE user_id = %d AND is_active = 1 AND expires_at > NOW()",
                $user_id
            ));

            return array(
                'success' => true,
                'stats' => array(
                    'total_referrals' => (int)$total_referrals,
                    'total_points_earned' => (int)$total_points,
                    'active_codes' => $active_codes
                )
            );

        } catch (\Exception $e) {
            WC_Reward_Points_Debug::log(
                'Failed to get referral stats',
                'ERROR',
                array(
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                )
            );

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get referral details by code.
     *
     * @since    1.0.0
     * @param    string    $code    The referral code.
     * @return   array              Array containing referral details.
     */
    public function get_referral_code_details($code) {
        global $wpdb;

        try {
            $details = $wpdb->get_row($wpdb->prepare(
                "SELECT rc.*, u.display_name as referrer_name 
                FROM {$wpdb->prefix}wc_rewards_referral_codes rc
                LEFT JOIN {$wpdb->prefix}users u ON rc.user_id = u.ID
                WHERE rc.code = %s",
                $code
            ), ARRAY_A);

            if (!$details) {
                throw new \Exception('Referral code not found');
            }

            // Get referral history for this code
            $referrals = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*, u.display_name as referee_name 
                FROM {$wpdb->prefix}wc_rewards_referrals r
                LEFT JOIN {$wpdb->prefix}users u ON r.referee_id = u.ID
                WHERE r.referral_code_id = %d
                ORDER BY r.created_at DESC",
                $details['id']
            ), ARRAY_A);

            $details['referrals'] = $referrals;

            return array(
                'success' => true,
                'details' => $details
            );

        } catch (\Exception $e) {
            WC_Reward_Points_Debug::log(
                'Failed to get referral code details',
                'ERROR',
                array(
                    'code' => $code,
                    'error' => $e->getMessage()
                )
            );

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
} 