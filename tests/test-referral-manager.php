<?php
/**
 * Class Test_Referral_Manager
 *
 * @package WC_Reward_Points
 */

use WC_Reward_Points\Core\WC_Reward_Points_Referral_Manager;

/**
 * Referral manager test case.
 */
class Test_Referral_Manager extends WC_Reward_Points_Test_Case {

    /**
     * The referral manager instance.
     *
     * @var WC_Reward_Points_Referral_Manager
     */
    private $referral_manager;

    /**
     * Test users.
     *
     * @var array
     */
    private $test_users = array();

    /**
     * Set up test environment.
     */
    public function set_up() {
        parent::set_up();
        
        $this->referral_manager = new WC_Reward_Points_Referral_Manager();

        // Create test users
        $this->test_users['referrer'] = $this->factory->user->create(array(
            'role' => 'customer',
            'user_login' => 'test_referrer',
            'user_email' => 'referrer@test.com'
        ));

        $this->test_users['referee'] = $this->factory->user->create(array(
            'role' => 'customer',
            'user_login' => 'test_referee',
            'user_email' => 'referee@test.com'
        ));
    }

    /**
     * Clean up test environment.
     */
    public function tear_down() {
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }

        parent::tear_down();
    }

    /**
     * Test referral code generation.
     */
    public function test_generate_referral_code() {
        // Test successful code generation
        $result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['code']);
        $this->assertNotEmpty($result['expires_at']);

        // Test code format
        $prefix = get_option('wc_reward_points_referral_code_prefix', 'REF');
        $this->assertStringStartsWith($prefix, $result['code']);

        // Test invalid user ID
        $invalid_result = $this->referral_manager->generate_referral_code(999999);
        $this->assertFalse($invalid_result['success']);
        $this->assertEquals('Invalid user ID', $invalid_result['error']);

        // Test code uniqueness
        $codes = array();
        for ($i = 0; $i < 10; $i++) {
            $result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
            $this->assertNotContains($result['code'], $codes);
            $codes[] = $result['code'];
        }
    }

    /**
     * Test referral processing.
     */
    public function test_process_referral() {
        // Generate a referral code
        $code_result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        $this->assertTrue($code_result['success']);

        // Test successful referral
        $result = $this->referral_manager->process_referral($this->test_users['referee'], $code_result['code']);
        $this->assertTrue($result['success']);
        $this->assertEquals(1000, $result['referrer_points']);
        $this->assertEquals(1000, $result['referee_points']);

        // Verify points were awarded
        global $wpdb;
        $referrer_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}wc_rewards_points_log WHERE user_id = %d",
            $this->test_users['referrer']
        ));
        $this->assertEquals(1000, $referrer_points);

        $referee_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}wc_rewards_points_log WHERE user_id = %d",
            $this->test_users['referee']
        ));
        $this->assertEquals(1000, $referee_points);

        // Test duplicate referral prevention
        $duplicate_result = $this->referral_manager->process_referral($this->test_users['referee'], $code_result['code']);
        $this->assertFalse($duplicate_result['success']);
        $this->assertEquals('User has already been referred', $duplicate_result['error']);

        // Test invalid referral code
        $invalid_result = $this->referral_manager->process_referral($this->test_users['referee'], 'INVALID_CODE');
        $this->assertFalse($invalid_result['success']);
        $this->assertEquals('Invalid or expired referral code', $invalid_result['error']);

        // Test self-referral prevention
        $self_code_result = $this->referral_manager->generate_referral_code($this->test_users['referee']);
        $self_result = $this->referral_manager->process_referral($this->test_users['referee'], $self_code_result['code']);
        $this->assertFalse($self_result['success']);
        $this->assertEquals('Cannot refer yourself', $self_result['error']);
    }

    /**
     * Test referral statistics.
     */
    public function test_get_user_referral_stats() {
        // Generate referral code and process a referral
        $code_result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        $this->referral_manager->process_referral($this->test_users['referee'], $code_result['code']);

        // Test stats for referrer
        $stats_result = $this->referral_manager->get_user_referral_stats($this->test_users['referrer']);
        $this->assertTrue($stats_result['success']);
        $this->assertEquals(1, $stats_result['stats']['total_referrals']);
        $this->assertEquals(1000, $stats_result['stats']['total_points_earned']);
        $this->assertNotEmpty($stats_result['stats']['active_codes']);

        // Test stats for user with no referrals
        $new_user_id = $this->factory->user->create();
        $empty_stats = $this->referral_manager->get_user_referral_stats($new_user_id);
        $this->assertTrue($empty_stats['success']);
        $this->assertEquals(0, $empty_stats['stats']['total_referrals']);
        $this->assertEquals(0, $empty_stats['stats']['total_points_earned']);
        $this->assertEmpty($empty_stats['stats']['active_codes']);
        wp_delete_user($new_user_id);
    }

    /**
     * Test referral code details retrieval.
     */
    public function test_get_referral_code_details() {
        // Generate a code and process a referral
        $code_result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        $this->referral_manager->process_referral($this->test_users['referee'], $code_result['code']);

        // Test code details retrieval
        $details_result = $this->referral_manager->get_referral_code_details($code_result['code']);
        $this->assertTrue($details_result['success']);
        $this->assertEquals($this->test_users['referrer'], $details_result['details']['user_id']);
        $this->assertEquals($code_result['code'], $details_result['details']['code']);
        $this->assertNotEmpty($details_result['details']['referrals']);
        $this->assertEquals(1, count($details_result['details']['referrals']));

        // Test invalid code
        $invalid_result = $this->referral_manager->get_referral_code_details('INVALID_CODE');
        $this->assertFalse($invalid_result['success']);
        $this->assertEquals('Referral code not found', $invalid_result['error']);
    }

    /**
     * Test concurrent referral processing.
     */
    public function test_concurrent_referrals() {
        // Generate a referral code
        $code_result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        
        // Create multiple referees
        $referees = array();
        for ($i = 0; $i < 5; $i++) {
            $referees[] = $this->factory->user->create(array(
                'role' => 'customer',
                'user_login' => "test_referee_{$i}",
                'user_email' => "referee_{$i}@test.com"
            ));
        }

        // Process referrals concurrently
        $results = array();
        foreach ($referees as $referee_id) {
            $results[] = $this->referral_manager->process_referral($referee_id, $code_result['code']);
        }

        // Verify all referrals were successful
        $success_count = 0;
        foreach ($results as $result) {
            if ($result['success']) {
                $success_count++;
            }
        }
        $this->assertEquals(5, $success_count);

        // Verify total points awarded
        global $wpdb;
        $total_referrer_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$wpdb->prefix}wc_rewards_points_log WHERE user_id = %d",
            $this->test_users['referrer']
        ));
        $this->assertEquals(5000, $total_referrer_points);

        // Clean up
        foreach ($referees as $user_id) {
            wp_delete_user($user_id);
        }
    }

    /**
     * Test referral code expiration.
     */
    public function test_referral_code_expiration() {
        global $wpdb;

        // Generate a referral code
        $code_result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        
        // Manually expire the code
        $wpdb->update(
            $wpdb->prefix . 'wc_rewards_referral_codes',
            array('expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))),
            array('code' => $code_result['code']),
            array('%s'),
            array('%s')
        );

        // Try to use expired code
        $result = $this->referral_manager->process_referral($this->test_users['referee'], $code_result['code']);
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired referral code', $result['error']);
    }

    /**
     * Test referral code deactivation.
     */
    public function test_referral_code_deactivation() {
        global $wpdb;

        // Generate a referral code
        $code_result = $this->referral_manager->generate_referral_code($this->test_users['referrer']);
        
        // Deactivate the code
        $wpdb->update(
            $wpdb->prefix . 'wc_rewards_referral_codes',
            array('is_active' => 0),
            array('code' => $code_result['code']),
            array('%d'),
            array('%s')
        );

        // Try to use deactivated code
        $result = $this->referral_manager->process_referral($this->test_users['referee'], $code_result['code']);
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid or expired referral code', $result['error']);
    }
} 