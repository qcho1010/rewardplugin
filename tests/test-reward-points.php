<?php
/**
 * Class WC_Reward_Points_Test
 *
 * @package WC_Reward_Points
 */

/**
 * Tests for the WooCommerce Reward Points plugin.
 */
class WC_Reward_Points_Test extends WP_UnitTestCase {

    /**
     * Test instance of the core class.
     */
    public function test_class_instance() {
        $plugin = new WC_Reward_Points\Core\WC_Reward_Points();
        $this->assertInstanceOf('WC_Reward_Points\Core\WC_Reward_Points', $plugin);
    }

    /**
     * Test points manager functionality
     */
    public function test_points_manager() {
        // Create a test user
        $user_id = $this->factory->user->create();
        
        // Get points manager instance
        $points_manager = WC_Reward_Points\Core\WC_Reward_Points_Manager::instance();
        
        // Test adding points
        $points_manager->add_points($user_id, 100, 'test', 'Test points');
        $points = $points_manager->get_points($user_id);
        $this->assertEquals(100, $points);
        
        // Test deducting points
        $points_manager->deduct_points($user_id, 50, 'test', 'Test deduction');
        $points = $points_manager->get_points($user_id);
        $this->assertEquals(50, $points);
        
        // Test points history
        $history = $points_manager->get_user_points_history($user_id);
        $this->assertEquals(2, count($history));
    }

    /**
     * Test referral code generation and validation
     */
    public function test_referral_code() {
        // Create a test user
        $user_id = $this->factory->user->create();
        
        // Create referral object
        $referral = new WC_Reward_Points\Public\WC_Reward_Points_Referral();
        
        // Generate referral code
        $code = $referral->generate_referral_code($user_id);
        
        // Test code length and format
        $this->assertEquals(8, strlen($code));
        $this->assertRegExp('/^[A-Z0-9]+$/', $code);
        
        // Test code validation
        $this->assertTrue($referral->is_valid_referral_code($code));
        $this->assertFalse($referral->is_valid_referral_code('INVALID'));
        
        // Test user lookup from code
        $this->assertEquals($user_id, $referral->get_user_id_from_referral_code($code));
    }

    /**
     * Test ambassador code generation
     */
    public function test_ambassador_code() {
        // Create a test user
        $user_id = $this->factory->user->create();
        
        // Import function directly
        require_once dirname(dirname(__FILE__)) . '/includes/admin/class-wc-reward-points-referral-admin.php';
        $admin = new WC_Reward_Points\Admin\WC_Reward_Points_Referral_Admin();
        
        // Generate ambassador code
        $code = $admin->generate_ambassador_code($user_id);
        
        // Test code length and format (ambassador codes are 6 characters)
        $this->assertEquals(6, strlen($code));
        $this->assertRegExp('/^[A-Z0-9]+$/', $code);
    }

    /**
     * Test Trustpilot review eligibility
     */
    public function test_review_eligibility() {
        // Create a test user
        $user_id = $this->factory->user->create();
        
        // Set up test conditions (no pending review, no previous review)
        delete_user_meta($user_id, '_wc_review_pending');
        delete_user_meta($user_id, '_wc_last_review_date');
        
        // Set up trustpilot handler
        $trustpilot = new WC_Reward_Points\Public\WC_Reward_Points_Trustpilot();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($trustpilot);
        $method = $reflection->getMethod('check_review_eligibility');
        $method->setAccessible(true);
        
        // Test eligible user
        $result = $method->invoke($trustpilot, $user_id);
        $this->assertTrue($result);
        
        // Test with pending review
        update_user_meta($user_id, '_wc_review_pending', array('order_id' => 1, 'timestamp' => time()));
        $result = $method->invoke($trustpilot, $user_id);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('pending_review', $result->get_error_code());
        
        // Test with previous review (one-time only)
        delete_user_meta($user_id, '_wc_review_pending');
        update_user_meta($user_id, '_wc_last_review_date', current_time('mysql'));
        update_option('wc_reward_points_trustpilot_cooldown', 0);
        $result = $method->invoke($trustpilot, $user_id);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('one_time_only', $result->get_error_code());
        
        // Test with cooldown period
        update_option('wc_reward_points_trustpilot_cooldown', 30);
        $result = $method->invoke($trustpilot, $user_id);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('review_cooldown', $result->get_error_code());
    }
    
    /**
     * Test shortcode registration and output
     */
    public function test_shortcodes() {
        // Create shortcodes instance
        $shortcodes = new WC_Reward_Points\Public\WC_Reward_Points_Shortcodes();
        
        // Test that shortcodes are registered
        $this->assertTrue(shortcode_exists('wc_reward_points_balance'));
        $this->assertTrue(shortcode_exists('wc_reward_points_history'));
        $this->assertTrue(shortcode_exists('wc_reward_points_referral'));
        $this->assertTrue(shortcode_exists('wc_reward_points_ambassador_apply'));
        $this->assertTrue(shortcode_exists('wc_reward_points_ambassador_dashboard'));
        $this->assertTrue(shortcode_exists('wc_reward_points_trustpilot_review'));
    }
    
    /**
     * Test template locator functionality
     */
    public function test_template_locator() {
        // Create a test template file
        $template_dir = get_template_directory() . '/wc-reward-points';
        wp_mkdir_p($template_dir);
        file_put_contents($template_dir . '/test-template.php', '<?php // Test template ?>');
        
        // Test template locator with theme override
        $template = WC_Reward_Points\Core\WC_Reward_Points::locate_template('test-template.php');
        $this->assertEquals($template_dir . '/test-template.php', $template);
        
        // Test template locator without theme override
        $template = WC_Reward_Points\Core\WC_Reward_Points::locate_template('non-existent-template.php');
        $this->assertEquals(WC_REWARD_POINTS_PLUGIN_PATH . 'templates/non-existent-template.php', $template);
        
        // Clean up
        unlink($template_dir . '/test-template.php');
        rmdir($template_dir);
    }
} 