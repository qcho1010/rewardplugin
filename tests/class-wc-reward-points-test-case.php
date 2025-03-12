<?php
/**
 * Base test case for WC Reward Points tests
 *
 * @package WC_Reward_Points
 */

namespace WC_Reward_Points\Tests;

/**
 * Base test case class
 */
class WC_Reward_Points_Test_Case extends \WP_UnitTestCase {
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Ensure WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is required for these tests.');
        }

        // Create test tables
        require_once dirname(dirname(__FILE__)) . '/includes/core/class-wc-reward-points-activator.php';
        \WC_Reward_Points\Core\WC_Reward_Points_Activator::activate();
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void {
        parent::tearDown();
        
        global $wpdb;
        
        // Clean up test tables
        $tables = array(
            'wc_rewards_claims',
            'wc_rewards_referrals',
            'wc_rewards_reviews',
            'wc_rewards_points_log'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
    }

    /**
     * Create a test user
     *
     * @param array $args User arguments
     * @return int User ID
     */
    protected function create_test_user($args = array()) {
        $default_args = array(
            'role' => 'customer',
            'user_login' => 'testuser' . rand(1000, 9999),
            'user_pass' => 'password',
            'user_email' => 'testuser' . rand(1000, 9999) . '@example.com'
        );
        
        $args = wp_parse_args($args, $default_args);
        $user_id = wp_insert_user($args);
        
        return $user_id;
    }

    /**
     * Assert points balance for a user
     *
     * @param int $user_id User ID
     * @param int $expected_points Expected points balance
     */
    protected function assert_points_balance($user_id, $expected_points) {
        $points_manager = \WC_Reward_Points\Core\WC_Reward_Points_Manager::instance();
        $actual_points = $points_manager->get_user_points($user_id);
        $this->assertEquals($expected_points, $actual_points);
    }

    /**
     * Assert reward claim exists
     *
     * @param int    $user_id User ID
     * @param string $reward_type Reward type
     * @param string $status Claim status
     */
    protected function assert_reward_claim_exists($user_id, $reward_type, $status = 'completed') {
        global $wpdb;
        
        $claim = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wc_rewards_claims
            WHERE user_id = %d AND reward_type = %s AND claim_status = %s",
            $user_id,
            $reward_type,
            $status
        ));
        
        $this->assertNotNull($claim);
    }

    /**
     * Assert points transaction exists
     *
     * @param int    $user_id User ID
     * @param string $type Transaction type
     * @param int    $points Points amount
     */
    protected function assert_points_transaction_exists($user_id, $type, $points) {
        global $wpdb;
        
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wc_rewards_points_log
            WHERE user_id = %d AND type = %s AND points = %d",
            $user_id,
            $type,
            $points
        ));
        
        $this->assertNotNull($transaction);
    }
} 