<?php
/**
 * Points Manager Tests
 *
 * @package WC_Reward_Points
 */

namespace WC_Reward_Points\Tests;

use WC_Reward_Points\Core\WC_Reward_Points_Manager;

/**
 * Points Manager test case
 */
class Test_Points_Manager extends WC_Reward_Points_Test_Case {

    /**
     * @var WC_Reward_Points_Manager
     */
    private $points_manager;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->points_manager = WC_Reward_Points_Manager::instance();
    }

    /**
     * Test adding points to user
     */
    public function test_add_points() {
        $user_id = $this->create_test_user();
        
        // Test basic point addition
        $result = $this->points_manager->add_points($user_id, 100, 'signup', 'Test points');
        $this->assertTrue($result);
        $this->assert_points_balance($user_id, 100);
        $this->assert_points_transaction_exists($user_id, 'signup', 100);

        // Test adding negative points
        $result = $this->points_manager->add_points($user_id, -50, 'refund', 'Refund test');
        $this->assertTrue($result);
        $this->assert_points_balance($user_id, 50);
        $this->assert_points_transaction_exists($user_id, 'refund', -50);
    }

    /**
     * Test adding points with invalid inputs
     */
    public function test_add_points_invalid_input() {
        // Test non-existent user
        $result = $this->points_manager->add_points(999999, 100, 'signup');
        $this->assertWPError($result);

        // Test invalid points value
        $user_id = $this->create_test_user();
        $result = $this->points_manager->add_points($user_id, 'invalid', 'signup');
        $this->assertWPError($result);

        // Test invalid type
        $result = $this->points_manager->add_points($user_id, 100, 'invalid_type');
        $this->assertWPError($result);
    }

    /**
     * Test deducting points
     */
    public function test_deduct_points() {
        $user_id = $this->create_test_user();
        
        // Add initial points
        $this->points_manager->add_points($user_id, 100, 'signup');
        
        // Test valid deduction
        $result = $this->points_manager->deduct_points($user_id, 50, 'redemption');
        $this->assertTrue($result);
        $this->assert_points_balance($user_id, 50);
        $this->assert_points_transaction_exists($user_id, 'redemption', -50);

        // Test insufficient points
        $result = $this->points_manager->deduct_points($user_id, 100, 'redemption');
        $this->assertWPError($result);
        $this->assert_points_balance($user_id, 50); // Balance should remain unchanged
    }

    /**
     * Test checking if user has claimed reward
     */
    public function test_has_claimed_reward() {
        $user_id = $this->create_test_user();
        
        // Test unclaimed reward
        $this->assertFalse($this->points_manager->has_claimed_reward($user_id, 'signup'));
        
        // Add a claim
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wc_rewards_claims',
            array(
                'user_id' => $user_id,
                'reward_type' => 'signup',
                'points_awarded' => 100,
                'claim_status' => 'completed'
            ),
            array('%d', '%s', '%d', '%s')
        );
        
        // Test claimed reward
        $this->assertTrue($this->points_manager->has_claimed_reward($user_id, 'signup'));
    }

    /**
     * Test logging reward claims
     */
    public function test_log_reward_claim() {
        $user_id = $this->create_test_user();
        
        // Test valid claim
        $result = $this->points_manager->log_reward_claim($user_id, 'signup', 100);
        $this->assertTrue($result);
        $this->assert_reward_claim_exists($user_id, 'signup');
        
        // Test duplicate claim
        $result = $this->points_manager->log_reward_claim($user_id, 'signup', 100);
        $this->assertTrue($result); // Should still succeed as we don't prevent duplicate logging
    }

    /**
     * Test getting points history
     */
    public function test_get_points_history() {
        $user_id = $this->create_test_user();
        
        // Add some point transactions
        $this->points_manager->add_points($user_id, 100, 'signup', 'Signup bonus');
        $this->points_manager->add_points($user_id, 50, 'referral', 'Referral bonus');
        $this->points_manager->deduct_points($user_id, 25, 'redemption', 'Test redemption');
        
        // Get history
        $history = $this->points_manager->get_points_history($user_id);
        
        // Verify history
        $this->assertCount(3, $history);
        $this->assertEquals(-25, $history[0]->points); // Most recent first
        $this->assertEquals(50, $history[1]->points);
        $this->assertEquals(100, $history[2]->points);
    }

    /**
     * Test points balance calculation
     */
    public function test_get_user_points() {
        $user_id = $this->create_test_user();
        
        // Test initial balance
        $this->assertEquals(0, $this->points_manager->get_user_points($user_id));
        
        // Add some points
        $this->points_manager->add_points($user_id, 100, 'signup');
        $this->assertEquals(100, $this->points_manager->get_user_points($user_id));
        
        // Add more points
        $this->points_manager->add_points($user_id, 50, 'referral');
        $this->assertEquals(150, $this->points_manager->get_user_points($user_id));
        
        // Deduct points
        $this->points_manager->deduct_points($user_id, 30, 'redemption');
        $this->assertEquals(120, $this->points_manager->get_user_points($user_id));
    }

    /**
     * Test concurrent point operations
     */
    public function test_concurrent_operations() {
        $user_id = $this->create_test_user();
        
        // Simulate concurrent operations
        $results = array();
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->points_manager->add_points($user_id, 100, 'signup', "Concurrent test $i");
        }
        
        // Verify all operations succeeded
        $this->assertEquals(5, count(array_filter($results)));
        $this->assert_points_balance($user_id, 500);
    }

    /**
     * Test points overflow protection
     */
    public function test_points_overflow() {
        $user_id = $this->create_test_user();
        
        // Test adding maximum points
        $result = $this->points_manager->add_points($user_id, PHP_INT_MAX, 'signup');
        $this->assertTrue($result);
        
        // Try to add more points
        $result = $this->points_manager->add_points($user_id, 100, 'signup');
        $this->assertWPError($result);
    }

    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention() {
        $user_id = $this->create_test_user();
        
        // Try SQL injection in description
        $result = $this->points_manager->add_points(
            $user_id,
            100,
            'signup',
            "'; DELETE FROM {$GLOBALS['wpdb']->prefix}wc_rewards_points_log; --"
        );
        
        $this->assertTrue($result);
        $this->assert_points_balance($user_id, 100);
        
        // Verify other records weren't affected
        $history = $this->points_manager->get_points_history($user_id);
        $this->assertCount(1, $history);
    }

    /**
     * Test transaction rollback
     */
    public function test_transaction_rollback() {
        $user_id = $this->create_test_user();
        
        // Add initial points
        $this->points_manager->add_points($user_id, 100, 'signup');
        
        // Force a transaction failure
        add_filter('pre_update_user_meta', function() {
            throw new \Exception('Forced failure');
        });
        
        // Attempt to add points (should fail)
        $result = $this->points_manager->add_points($user_id, 50, 'referral');
        
        // Remove the filter
        remove_all_filters('pre_update_user_meta');
        
        // Verify points weren't added and no transaction was logged
        $this->assertWPError($result);
        $this->assert_points_balance($user_id, 100);
        
        $history = $this->points_manager->get_points_history($user_id);
        $this->assertCount(1, $history);
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        $user_id = $this->create_test_user();
        $attempts = 0;
        
        // Try to add points rapidly
        for ($i = 0; $i < 20; $i++) {
            $result = $this->points_manager->add_points($user_id, 10, 'signup');
            if ($result === true) {
                $attempts++;
            }
        }
        
        // Verify rate limiting worked
        $this->assertLessThan(20, $attempts);
    }
} 