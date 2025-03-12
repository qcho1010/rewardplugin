<?php
/**
 * Test cases for Debug Logger
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/tests
 */

use WC_Reward_Points\Core\WC_Reward_Points_Debug;

/**
 * Debug Logger Test Class
 */
class Test_Debug extends WC_Reward_Points_Test_Case {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        WC_Reward_Points_Debug::init();
    }

    /**
     * Test log creation and retrieval
     */
    public function test_log_creation() {
        // Create test log entries
        WC_Reward_Points_Debug::log('Test error message', WC_Reward_Points_Debug::ERROR);
        WC_Reward_Points_Debug::log('Test warning message', WC_Reward_Points_Debug::WARNING);
        WC_Reward_Points_Debug::log('Test info message', WC_Reward_Points_Debug::INFO);

        // Retrieve logs
        $logs = WC_Reward_Points_Debug::get_logs();

        // Verify log count
        $this->assertEquals(3, count($logs));

        // Verify log levels
        $this->assertEquals('error', $logs[0]->level);
        $this->assertEquals('warning', $logs[1]->level);
        $this->assertEquals('info', $logs[2]->level);

        // Verify messages
        $this->assertEquals('Test error message', $logs[0]->message);
        $this->assertEquals('Test warning message', $logs[1]->message);
        $this->assertEquals('Test info message', $logs[2]->message);
    }

    /**
     * Test log filtering by level
     */
    public function test_log_filtering() {
        // Create test log entries
        WC_Reward_Points_Debug::log('Error 1', WC_Reward_Points_Debug::ERROR);
        WC_Reward_Points_Debug::log('Error 2', WC_Reward_Points_Debug::ERROR);
        WC_Reward_Points_Debug::log('Warning 1', WC_Reward_Points_Debug::WARNING);

        // Get only error logs
        $error_logs = WC_Reward_Points_Debug::get_logs(['level' => WC_Reward_Points_Debug::ERROR]);
        $this->assertEquals(2, count($error_logs));

        // Get only warning logs
        $warning_logs = WC_Reward_Points_Debug::get_logs(['level' => WC_Reward_Points_Debug::WARNING]);
        $this->assertEquals(1, count($warning_logs));
    }

    /**
     * Test log search functionality
     */
    public function test_log_search() {
        // Create test log entries
        WC_Reward_Points_Debug::log('User points added', WC_Reward_Points_Debug::INFO);
        WC_Reward_Points_Debug::log('Points redeemed', WC_Reward_Points_Debug::INFO);
        WC_Reward_Points_Debug::log('User registered', WC_Reward_Points_Debug::INFO);

        // Search for 'points' logs
        $points_logs = WC_Reward_Points_Debug::get_logs(['search' => 'points']);
        $this->assertEquals(2, count($points_logs));

        // Search for 'user' logs
        $user_logs = WC_Reward_Points_Debug::get_logs(['search' => 'user']);
        $this->assertEquals(2, count($user_logs));
    }

    /**
     * Test log clearing functionality
     */
    public function test_log_clearing() {
        // Create test log entries
        WC_Reward_Points_Debug::log('Error message', WC_Reward_Points_Debug::ERROR);
        WC_Reward_Points_Debug::log('Warning message', WC_Reward_Points_Debug::WARNING);
        WC_Reward_Points_Debug::log('Info message', WC_Reward_Points_Debug::INFO);

        // Clear only error logs
        WC_Reward_Points_Debug::clear_logs(WC_Reward_Points_Debug::ERROR);
        $logs = WC_Reward_Points_Debug::get_logs();
        $this->assertEquals(2, count($logs));

        // Clear all remaining logs
        WC_Reward_Points_Debug::clear_logs();
        $logs = WC_Reward_Points_Debug::get_logs();
        $this->assertEquals(0, count($logs));
    }

    /**
     * Test debug mode functionality
     */
    public function test_debug_mode() {
        // Set WP_DEBUG to false
        if (defined('WP_DEBUG')) {
            runkit_constant_redefine('WP_DEBUG', false);
        } else {
            define('WP_DEBUG', false);
        }

        // Debug messages should not be logged when debug mode is off
        WC_Reward_Points_Debug::log('Debug message', WC_Reward_Points_Debug::DEBUG);
        $logs = WC_Reward_Points_Debug::get_logs();
        $this->assertEquals(0, count($logs));

        // Set WP_DEBUG to true
        runkit_constant_redefine('WP_DEBUG', true);
        WC_Reward_Points_Debug::init();

        // Debug messages should be logged when debug mode is on
        WC_Reward_Points_Debug::log('Debug message', WC_Reward_Points_Debug::DEBUG);
        $logs = WC_Reward_Points_Debug::get_logs();
        $this->assertEquals(1, count($logs));
    }

    /**
     * Test context data logging
     */
    public function test_context_logging() {
        $context = [
            'user_id' => 1,
            'points' => 100,
            'action' => 'add'
        ];

        WC_Reward_Points_Debug::log('Points operation', WC_Reward_Points_Debug::INFO, $context);
        $logs = WC_Reward_Points_Debug::get_logs();

        $this->assertEquals(1, count($logs));
        $stored_context = json_decode($logs[0]->context, true);
        $this->assertEquals($context, $stored_context);
    }

    /**
     * Test pagination of log entries
     */
    public function test_log_pagination() {
        // Create 15 test log entries
        for ($i = 1; $i <= 15; $i++) {
            WC_Reward_Points_Debug::log("Log entry $i", WC_Reward_Points_Debug::INFO);
        }

        // Test first page (limit 10)
        $first_page = WC_Reward_Points_Debug::get_logs(['limit' => 10, 'offset' => 0]);
        $this->assertEquals(10, count($first_page));

        // Test second page
        $second_page = WC_Reward_Points_Debug::get_logs(['limit' => 10, 'offset' => 10]);
        $this->assertEquals(5, count($second_page));
    }
} 