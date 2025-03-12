<?php
/**
 * Rate Limiter Class
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 * @since      1.0.0
 */

namespace WC_Reward_Points\Core;

/**
 * Rate Limiter Class
 *
 * Provides methods for rate limiting all reward actions
 */
class WC_Reward_Points_Rate_Limiter {
    
    /**
     * Rate limit buckets
     * 
     * @var array
     */
    private static $buckets = array(
        'reward_action' => 'wc_reward_points_rate_limits',
        'api_request' => 'wc_reward_points_api_rate_limits',
        'review_submission' => 'wc_reward_points_review_rate_limits',
        'referral_claim' => 'wc_reward_points_referral_rate_limits',
        'ambassador_apply' => 'wc_reward_points_ambassador_rate_limits',
        'point_redemption' => 'wc_reward_points_redemption_rate_limits',
    );
    
    /**
     * Check if an action is rate limited
     *
     * @param string $action    Action identifier
     * @param string $bucket    Bucket name (defaults to reward_action)
     * @param mixed  $identity  User identifier (ID, email, IP)
     * @param int    $limit     Maximum attempts
     * @param int    $timeframe Timeframe in seconds
     * @return array Result with limited status and details
     */
    public static function check_rate_limit($action, $bucket = 'reward_action', $identity = null, $limit = 5, $timeframe = 3600) {
        // Default result
        $result = array(
            'limited' => false,
            'count' => 0,
            'remaining' => $limit,
            'reset' => time() + $timeframe,
        );
        
        // Validate bucket
        if (!isset(self::$buckets[$bucket])) {
            return $result;
        }
        
        // Get identity if not provided
        if (is_null($identity)) {
            $identity = self::get_identity();
        }
        
        // Skip rate limiting for admin users
        if (current_user_can('manage_options')) {
            return $result;
        }
        
        // Get the rate limits transient
        $bucket_name = self::$buckets[$bucket];
        $rate_limits = get_transient($bucket_name);
        
        if (false === $rate_limits) {
            $rate_limits = array();
        }
        
        // Create key for this action and identity
        $key = md5($action . '_' . $identity);
        
        // Initialize if not exists
        if (!isset($rate_limits[$key])) {
            $rate_limits[$key] = array(
                'count' => 0,
                'timestamps' => array(),
                'reset' => time() + $timeframe,
            );
        }
        
        // Clean up old timestamps
        $cutoff = time() - $timeframe;
        $rate_limits[$key]['timestamps'] = array_filter($rate_limits[$key]['timestamps'], function($timestamp) use ($cutoff) {
            return $timestamp >= $cutoff;
        });
        
        // Recalculate count
        $rate_limits[$key]['count'] = count($rate_limits[$key]['timestamps']);
        
        // Check if rate limited
        if ($rate_limits[$key]['count'] >= $limit) {
            $result['limited'] = true;
            $result['count'] = $rate_limits[$key]['count'];
            $result['remaining'] = 0;
            
            // Calculate reset time
            if (!empty($rate_limits[$key]['timestamps'])) {
                sort($rate_limits[$key]['timestamps']);
                $oldest = $rate_limits[$key]['timestamps'][0];
                $result['reset'] = $oldest + $timeframe;
            }
            
            // Save updated rate limits
            set_transient($bucket_name, $rate_limits, $timeframe * 2);
            
            return $result;
        }
        
        // Not rate limited, update the result
        $result['count'] = $rate_limits[$key]['count'];
        $result['remaining'] = $limit - $result['count'];
        
        // Calculate reset time
        if (!empty($rate_limits[$key]['timestamps'])) {
            sort($rate_limits[$key]['timestamps']);
            $oldest = $rate_limits[$key]['timestamps'][0];
            $result['reset'] = $oldest + $timeframe;
        }
        
        return $result;
    }
    
    /**
     * Record an action attempt
     *
     * @param string $action    Action identifier
     * @param string $bucket    Bucket name (defaults to reward_action)
     * @param mixed  $identity  User identifier (ID, email, IP)
     * @param int    $timeframe Timeframe in seconds
     * @return bool|array False if rate limited, otherwise the updated limits
     */
    public static function record_attempt($action, $bucket = 'reward_action', $identity = null, $limit = 5, $timeframe = 3600) {
        // Validate bucket
        if (!isset(self::$buckets[$bucket])) {
            return false;
        }
        
        // Get identity if not provided
        if (is_null($identity)) {
            $identity = self::get_identity();
        }
        
        // Skip rate limiting for admin users
        if (current_user_can('manage_options')) {
            return array(
                'limited' => false,
                'count' => 0,
                'remaining' => $limit,
                'reset' => time() + $timeframe,
            );
        }
        
        // Get the rate limits transient
        $bucket_name = self::$buckets[$bucket];
        $rate_limits = get_transient($bucket_name);
        
        if (false === $rate_limits) {
            $rate_limits = array();
        }
        
        // Create key for this action and identity
        $key = md5($action . '_' . $identity);
        
        // Initialize if not exists
        if (!isset($rate_limits[$key])) {
            $rate_limits[$key] = array(
                'count' => 0,
                'timestamps' => array(),
                'reset' => time() + $timeframe,
            );
        }
        
        // Clean up old timestamps
        $cutoff = time() - $timeframe;
        $rate_limits[$key]['timestamps'] = array_filter($rate_limits[$key]['timestamps'], function($timestamp) use ($cutoff) {
            return $timestamp >= $cutoff;
        });
        
        // Recalculate count
        $rate_limits[$key]['count'] = count($rate_limits[$key]['timestamps']);
        
        // Check if rate limited
        if ($rate_limits[$key]['count'] >= $limit) {
            // Calculate reset time
            if (!empty($rate_limits[$key]['timestamps'])) {
                sort($rate_limits[$key]['timestamps']);
                $oldest = $rate_limits[$key]['timestamps'][0];
                $rate_limits[$key]['reset'] = $oldest + $timeframe;
            }
            
            // Save updated rate limits
            set_transient($bucket_name, $rate_limits, $timeframe * 2);
            
            return array(
                'limited' => true,
                'count' => $rate_limits[$key]['count'],
                'remaining' => 0,
                'reset' => $rate_limits[$key]['reset'],
            );
        }
        
        // Add new timestamp
        $rate_limits[$key]['timestamps'][] = time();
        
        // Update count
        $rate_limits[$key]['count'] = count($rate_limits[$key]['timestamps']);
        
        // Save updated rate limits
        set_transient($bucket_name, $rate_limits, $timeframe * 2);
        
        // Not rate limited, return updated info
        return array(
            'limited' => false,
            'count' => $rate_limits[$key]['count'],
            'remaining' => $limit - $rate_limits[$key]['count'],
            'reset' => $rate_limits[$key]['reset'],
        );
    }
    
    /**
     * Reset rate limits for a specific action
     *
     * @param string $action    Action identifier
     * @param string $bucket    Bucket name (defaults to reward_action)
     * @param mixed  $identity  User identifier (ID, email, IP)
     * @return bool Success
     */
    public static function reset_rate_limit($action, $bucket = 'reward_action', $identity = null) {
        // Validate bucket
        if (!isset(self::$buckets[$bucket])) {
            return false;
        }
        
        // Get identity if not provided
        if (is_null($identity)) {
            $identity = self::get_identity();
        }
        
        // Get the rate limits transient
        $bucket_name = self::$buckets[$bucket];
        $rate_limits = get_transient($bucket_name);
        
        if (false === $rate_limits) {
            return true; // Nothing to reset
        }
        
        // Create key for this action and identity
        $key = md5($action . '_' . $identity);
        
        // Remove the rate limit entry
        if (isset($rate_limits[$key])) {
            unset($rate_limits[$key]);
            set_transient($bucket_name, $rate_limits, 3600 * 24);
        }
        
        return true;
    }
    
    /**
     * Get the identity for rate limiting
     * 
     * Uses user ID if logged in, otherwise IP
     *
     * @return string Identity
     */
    private static function get_identity() {
        if (is_user_logged_in()) {
            return 'u_' . get_current_user_id();
        } else {
            return 'ip_' . self::get_ip_address();
        }
    }
    
    /**
     * Get IP address
     *
     * @return string IP address
     */
    private static function get_ip_address() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
} 