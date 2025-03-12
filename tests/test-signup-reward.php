<?php
/**
 * Signup Reward Tests
 *
 * @package WC_Reward_Points
 */

namespace WC_Reward_Points\Tests;

use WC_Reward_Points\Public\WC_Reward_Points_Signup;
use WC_Reward_Points\Core\WC_Reward_Points_Manager;

/**
 * Signup Reward test case
 */
class Test_Signup_Reward extends WC_Reward_Points_Test_Case {

    /**
     * @var WC_Reward_Points_Signup
     */
    private $signup_handler;

    /**
     * @var WC_Reward_Points_Manager
     */
    private $points_manager;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->signup_handler = new WC_Reward_Points_Signup();
        $this->points_manager = WC_Reward_Points_Manager::instance();
        
        // Set up default reward points
        update_option('wc_reward_points_signup_points', 100);
    }

    /**
     * Test endpoint registration
     */
    public function test_endpoint_registration() {
        global $wp_rewrite;
        
        // Trigger endpoint registration
        $this->signup_handler->add_endpoints();
        
        // Check if endpoint exists
        $this->assertContains('rewards/signup', $wp_rewrite->endpoints);
    }

    /**
     * Test signup reward for new user
     */
    public function test_signup_reward_new_user() {
        // Create a new user
        $user_id = $this->create_test_user();
        
        // Simulate session variable
        WC()->session = new \WC_Session_Handler();
        WC()->session->init();
        WC()->session->set('reward_signup', 'yes');
        
        // Trigger signup reward
        $this->signup_handler->handle_signup_reward($user_id);
        
        // Verify points were awarded
        $this->assert_points_balance($user_id, 100);
        $this->assert_reward_claim_exists($user_id, 'signup');
        $this->assert_points_transaction_exists($user_id, 'signup', 100);
        
        // Verify session was cleared
        $this->assertNull(WC()->session->get('reward_signup'));
    }

    /**
     * Test preventing duplicate signup rewards
     */
    public function test_prevent_duplicate_signup_reward() {
        // Create a user who has already claimed the reward
        $user_id = $this->create_test_user();
        $this->points_manager->log_reward_claim($user_id, 'signup', 100);
        
        // Set up test environment
        $_SERVER['REQUEST_URI'] = '/rewards/signup';
        $GLOBALS['wp_query'] = new \WP_Query();
        $GLOBALS['wp_query']->query_vars['rewards/signup'] = '';
        
        // Start output buffering to catch redirects
        ob_start();
        
        // Set current user
        wp_set_current_user($user_id);
        
        // Try to claim reward again
        $this->signup_handler->handle_signup_endpoint();
        
        // Clean up output buffer
        ob_end_clean();
        
        // Verify no additional points were awarded
        $this->assert_points_balance($user_id, 0);
    }

    /**
     * Test signup endpoint for logged out users
     */
    public function test_signup_endpoint_logged_out() {
        // Set up test environment
        $_SERVER['REQUEST_URI'] = '/rewards/signup';
        $GLOBALS['wp_query'] = new \WP_Query();
        $GLOBALS['wp_query']->query_vars['rewards/signup'] = '';
        
        // Ensure user is logged out
        wp_set_current_user(0);
        
        // Start output buffering to catch redirects
        ob_start();
        
        // Access signup endpoint
        $this->signup_handler->handle_signup_endpoint();
        
        // Clean up output buffer
        ob_end_clean();
        
        // Verify session was set
        $this->assertEquals('yes', WC()->session->get('reward_signup'));
    }

    /**
     * Test signup endpoint for logged in users
     */
    public function test_signup_endpoint_logged_in() {
        // Create and login as test user
        $user_id = $this->create_test_user();
        wp_set_current_user($user_id);
        
        // Set up test environment
        $_SERVER['REQUEST_URI'] = '/rewards/signup';
        $GLOBALS['wp_query'] = new \WP_Query();
        $GLOBALS['wp_query']->query_vars['rewards/signup'] = '';
        
        // Start output buffering to catch redirects
        ob_start();
        
        // Access signup endpoint
        $this->signup_handler->handle_signup_endpoint();
        
        // Clean up output buffer
        ob_end_clean();
        
        // Verify points were awarded
        $this->assert_points_balance($user_id, 100);
        $this->assert_reward_claim_exists($user_id, 'signup');
    }

    /**
     * Test error handling for invalid users
     */
    public function test_error_handling() {
        // Try to award points to non-existent user
        $result = $this->signup_handler->handle_signup_reward(999999);
        
        // Verify error was returned
        $this->assertWPError($result);
    }

    /**
     * Test signup page detection
     */
    public function test_is_signup_reward_page() {
        // Test regular page
        $GLOBALS['wp_query'] = new \WP_Query();
        $this->assertFalse(WC_Reward_Points_Signup::is_signup_reward_page());
        
        // Test signup page
        $GLOBALS['wp_query']->query_vars['rewards/signup'] = '';
        $this->assertTrue(WC_Reward_Points_Signup::is_signup_reward_page());
    }
} 