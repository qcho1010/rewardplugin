<?php
/**
 * Class Test_Referral_Social_Share
 *
 * @package WC_Reward_Points
 */

use WC_Reward_Points\Core\WC_Reward_Points_Referral_Social_Share;

/**
 * Social share test case.
 */
class Test_Referral_Social_Share extends WC_Reward_Points_Test_Case {

    /**
     * The social share instance.
     *
     * @var WC_Reward_Points_Referral_Social_Share
     */
    private $social_share;

    /**
     * Test user ID.
     *
     * @var int
     */
    private $test_user_id;

    /**
     * Set up test environment.
     */
    public function set_up() {
        parent::set_up();
        
        $this->social_share = new WC_Reward_Points_Referral_Social_Share();

        // Create test user
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'customer',
            'user_login' => 'test_user',
            'user_email' => 'test@example.com'
        ));

        // Set up default options
        update_option('wc_reward_points_share_message', 'Hey, join [Store Name] using my referral code [CODE] and get [POINTS] points!');
        update_option('wc_reward_points_enabled_social_platforms', array('facebook', 'twitter', 'whatsapp', 'email'));
        update_option('wc_reward_points_referral_points_referee', 1000);
        update_option('blogname', 'Test Store');
    }

    /**
     * Clean up test environment.
     */
    public function tear_down() {
        wp_delete_user($this->test_user_id);
        parent::tear_down();
    }

    /**
     * Test share data generation.
     */
    public function test_get_share_data() {
        // Test successful share data generation
        $result = $this->social_share->get_share_data($this->test_user_id);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('code', $result['data']);
        $this->assertArrayHasKey('expires_at', $result['data']);
        $this->assertArrayHasKey('share_message', $result['data']);
        $this->assertArrayHasKey('share_url', $result['data']);
        $this->assertArrayHasKey('share_urls', $result['data']);
        $this->assertArrayHasKey('enabled_platforms', $result['data']);

        // Verify share message placeholders are replaced
        $expected_message = 'Hey, join Test Store using my referral code ' . $result['data']['code'] . ' and get 1000 points!';
        $this->assertEquals($expected_message, $result['data']['share_message']);

        // Verify share URLs
        $this->assertArrayHasKey('facebook', $result['data']['share_urls']);
        $this->assertArrayHasKey('twitter', $result['data']['share_urls']);
        $this->assertArrayHasKey('whatsapp', $result['data']['share_urls']);
        $this->assertArrayHasKey('email', $result['data']['share_urls']);

        // Test invalid user ID
        $invalid_result = $this->social_share->get_share_data(999999);
        $this->assertFalse($invalid_result['success']);
        $this->assertEquals('Invalid user ID', $invalid_result['error']);
    }

    /**
     * Test AJAX share data retrieval.
     */
    public function test_ajax_get_share_data() {
        // Set up AJAX request
        $_REQUEST['_ajax_nonce'] = wp_create_nonce('wc_reward_points_share');
        $_REQUEST['action'] = 'get_share_data';

        // Test unauthenticated request
        try {
            $this->social_share->ajax_get_share_data();
        } catch (\WPAjaxDieContinueException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals('User not logged in', $response['data']);
        }

        // Test authenticated request
        wp_set_current_user($this->test_user_id);
        try {
            $this->social_share->ajax_get_share_data();
        } catch (\WPAjaxDieContinueException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertTrue($response['success']);
            $this->assertArrayHasKey('code', $response['data']);
            $this->assertArrayHasKey('share_urls', $response['data']);
        }

        // Test invalid nonce
        $_REQUEST['_ajax_nonce'] = 'invalid_nonce';
        try {
            $this->social_share->ajax_get_share_data();
        } catch (\WPAjaxDieContinueException $e) {
            $response = json_decode($this->_last_response, true);
            $this->assertFalse($response['success']);
            $this->assertEquals('Invalid nonce', $response['data']);
        }
    }

    /**
     * Test frontend asset registration.
     */
    public function test_register_frontend_assets() {
        // Test script and style registration
        $this->social_share->register_frontend_assets();

        $this->assertTrue(wp_style_is('wc-reward-points-social-share', 'registered'));
        $this->assertTrue(wp_script_is('wc-reward-points-social-share', 'registered'));

        // Test script localization
        global $wp_scripts;
        $this->assertArrayHasKey('wcRewardPointsShare', $wp_scripts->get_data('wc-reward-points-social-share', 'data'));
    }

    /**
     * Test frontend asset enqueuing.
     */
    public function test_enqueue_frontend_assets() {
        // Test enqueuing
        $this->social_share->enqueue_frontend_assets();

        $this->assertTrue(wp_style_is('wc-reward-points-social-share', 'enqueued'));
        $this->assertTrue(wp_script_is('wc-reward-points-social-share', 'enqueued'));
    }

    /**
     * Test share popup HTML generation.
     */
    public function test_get_share_popup_html() {
        // Get share data
        $share_data = $this->social_share->get_share_data($this->test_user_id)['data'];

        // Generate popup HTML
        $html = $this->social_share->get_share_popup_html($share_data);

        // Test HTML structure
        $this->assertStringContainsString('wc-reward-points-share-popup', $html);
        $this->assertStringContainsString('Share & Earn Rewards', $html);
        $this->assertStringContainsString($share_data['code'], $html);
        $this->assertStringContainsString($share_data['share_url'], $html);

        // Test social buttons
        foreach ($share_data['enabled_platforms'] as $platform) {
            $this->assertStringContainsString("wc-reward-points-share-button {$platform}", $html);
            $this->assertStringContainsString($share_data['share_urls'][$platform], $html);
        }

        // Test expiry date
        $expiry_date = new DateTime($share_data['expires_at']);
        $expiry_date = $expiry_date->format('F j, Y');
        $this->assertStringContainsString($expiry_date, $html);

        // Test XSS prevention
        $malicious_data = $share_data;
        $malicious_data['code'] = '<script>alert("XSS")</script>';
        $malicious_html = $this->social_share->get_share_popup_html($malicious_data);
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $malicious_html);
        $this->assertStringContainsString('&lt;script&gt;', $malicious_html);
    }

    /**
     * Test custom share message.
     */
    public function test_custom_share_message() {
        // Set custom share message
        $custom_message = 'Custom message with [Store Name] and [CODE] and [POINTS]!';
        update_option('wc_reward_points_share_message', $custom_message);

        // Get share data
        $result = $this->social_share->get_share_data($this->test_user_id);
        $this->assertTrue($result['success']);

        // Verify custom message
        $expected_message = str_replace(
            array('[Store Name]', '[CODE]', '[POINTS]'),
            array('Test Store', $result['data']['code'], '1000'),
            $custom_message
        );
        $this->assertEquals($expected_message, $result['data']['share_message']);
    }

    /**
     * Test custom social platforms.
     */
    public function test_custom_social_platforms() {
        // Set custom platforms
        $custom_platforms = array('facebook', 'twitter');
        update_option('wc_reward_points_enabled_social_platforms', $custom_platforms);

        // Get share data
        $result = $this->social_share->get_share_data($this->test_user_id);
        $this->assertTrue($result['success']);

        // Verify only specified platforms are included
        $this->assertEquals($custom_platforms, $result['data']['enabled_platforms']);
        $this->assertArrayHasKey('facebook', $result['data']['share_urls']);
        $this->assertArrayHasKey('twitter', $result['data']['share_urls']);
        $this->assertArrayNotHasKey('whatsapp', $result['data']['share_urls']);
        $this->assertArrayNotHasKey('email', $result['data']['share_urls']);
    }
} 