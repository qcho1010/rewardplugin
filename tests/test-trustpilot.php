<?php
/**
 * Test cases for Trustpilot Review Handler
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/tests
 */

use WC_Reward_Points\Core\WC_Reward_Points_Trustpilot;

/**
 * Trustpilot Review Handler Test Class
 */
class Test_Trustpilot extends WC_Reward_Points_Test_Case {

    /**
     * @var WC_Reward_Points_Trustpilot
     */
    private $trustpilot;

    /**
     * @var int
     */
    private $test_user_id;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->trustpilot = new WC_Reward_Points_Trustpilot();
        $this->test_user_id = $this->create_test_user();

        // Set test API credentials
        update_option('wc_rewards_trustpilot_business_unit_id', 'test_business_unit');
        update_option('wc_rewards_trustpilot_api_key', 'test_api_key');
        update_option('wc_rewards_trustpilot_secret_key', 'test_secret_key');
    }

    /**
     * Test review endpoint registration
     */
    public function test_endpoint_registration() {
        global $wp_rest_server;
        $routes = $wp_rest_server->get_routes();
        
        $this->assertArrayHasKey('/wc-reward-points/v1', $routes);
        $this->assertArrayHasKey('/wc-reward-points/v1/review', $routes);
    }

    /**
     * Test permission check for logged out users
     */
    public function test_permission_check_logged_out() {
        wp_set_current_user(0);
        
        $result = $this->trustpilot->check_review_permission();
        $this->assertWPError($result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
    }

    /**
     * Test permission check for logged in users
     */
    public function test_permission_check_logged_in() {
        wp_set_current_user($this->test_user_id);
        
        $result = $this->trustpilot->check_review_permission();
        $this->assertTrue($result);
    }

    /**
     * Test duplicate review claim prevention
     */
    public function test_duplicate_claim_prevention() {
        wp_set_current_user($this->test_user_id);
        
        // Log a previous claim
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wc_rewards_claims',
            array(
                'user_id' => $this->test_user_id,
                'reward_type' => 'trustpilot_review',
                'points_awarded' => 300,
                'claim_status' => 'completed'
            ),
            array('%d', '%s', '%d', '%s')
        );
        
        $result = $this->trustpilot->check_review_permission();
        $this->assertWPError($result);
        $this->assertEquals('review_already_claimed', $result->get_error_code());
    }

    /**
     * Test review verification with missing API credentials
     */
    public function test_review_verification_missing_credentials() {
        delete_option('wc_rewards_trustpilot_business_unit_id');
        delete_option('wc_rewards_trustpilot_api_key');

        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        wp_set_current_user($this->test_user_id);
        $response = $this->trustpilot->handle_review_reward($request);

        $this->assertWPError($response);
        $this->assertEquals('api_credentials_missing', $response->get_error_code());
    }

    /**
     * Test successful review reward
     */
    public function test_successful_review_reward() {
        // Mock successful API response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.trustpilot.com') !== false) {
                return array(
                    'response' => array('code' => 200),
                    'body' => json_encode(array(
                        'id' => 'test_review_id',
                        'businessUnit' => array(
                            'id' => 'test_business_unit'
                        ),
                        'consumer' => array(
                            'email' => 'test@example.com'
                        )
                    ))
                );
            }
            return $preempt;
        }, 10, 3);

        // Create test user with matching email
        $user_id = wp_create_user('test_user', 'password', 'test@example.com');
        wp_set_current_user($user_id);

        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        $response = $this->trustpilot->handle_review_reward($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals(300, $data['points_awarded']);

        // Verify points were added
        global $wpdb;
        $points = $wpdb->get_var($wpdb->prepare(
            "SELECT points FROM {$wpdb->prefix}wc_rewards_points_log WHERE user_id = %d",
            $user_id
        ));
        $this->assertEquals(300, $points);
    }

    /**
     * Test review verification with API error
     */
    public function test_review_verification_api_error() {
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'API request failed');
        });

        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        wp_set_current_user($this->test_user_id);
        $response = $this->trustpilot->handle_review_reward($request);

        $this->assertWPError($response);
        $this->assertEquals('api_error', $response->get_error_code());
    }

    /**
     * Test review from different business unit
     */
    public function test_review_different_business_unit() {
        add_filter('pre_http_request', function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'businessUnit' => array(
                        'id' => 'different_business_unit'
                    )
                ))
            );
        });

        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        wp_set_current_user($this->test_user_id);
        $response = $this->trustpilot->handle_review_reward($request);

        $this->assertWPError($response);
        $this->assertEquals('invalid_review', $response->get_error_code());
    }

    /**
     * Test review from different user
     */
    public function test_review_different_user() {
        add_filter('pre_http_request', function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'businessUnit' => array(
                        'id' => 'test_business_unit'
                    ),
                    'consumer' => array(
                        'email' => 'different@example.com'
                    )
                ))
            );
        });

        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        wp_set_current_user($this->test_user_id);
        $response = $this->trustpilot->handle_review_reward($request);

        $this->assertWPError($response);
        $this->assertEquals('invalid_review', $response->get_error_code());
    }

    /**
     * Test review reward amount filter
     */
    public function test_review_reward_amount_filter() {
        add_filter('wc_rewards_review_points', function() {
            return 500;
        });

        $this->assertEquals(500, $this->trustpilot->get_review_reward_amount());
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        // Mock successful API response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.trustpilot.com') !== false) {
                return array(
                    'response' => array('code' => 200),
                    'body' => json_encode(array(
                        'id' => 'test_review_id',
                        'businessUnit' => array(
                            'id' => 'test_business_unit'
                        ),
                        'consumer' => array(
                            'email' => 'test@example.com'
                        )
                    ))
                );
            }
            return $preempt;
        });

        // Create test user
        $user_id = wp_create_user('test_user', 'password', 'test@example.com');
        wp_set_current_user($user_id);

        // Try multiple requests in quick succession
        $success_count = 0;
        for ($i = 0; $i < 10; $i++) {
            $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
            $request->set_param('review_id', "test_review_id_$i");
            
            $response = $this->trustpilot->handle_review_reward($request);
            if (!is_wp_error($response)) {
                $success_count++;
            }
        }

        // Should be rate limited
        $this->assertLessThan(10, $success_count);
    }

    /**
     * Test review verification with invalid review ID
     */
    public function test_invalid_review_id() {
        wp_set_current_user($this->test_user_id);

        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', '"><script>alert(1)</script>');

        $response = $this->trustpilot->handle_review_reward($request);
        $this->assertWPError($response);
    }

    /**
     * Test review verification with expired review
     */
    public function test_expired_review() {
        add_filter('pre_http_request', function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'id' => 'test_review_id',
                    'businessUnit' => array(
                        'id' => 'test_business_unit'
                    ),
                    'consumer' => array(
                        'email' => 'test@example.com'
                    ),
                    'createdAt' => date('c', strtotime('-31 days'))
                ))
            );
        });

        wp_set_current_user($this->test_user_id);
        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        $response = $this->trustpilot->handle_review_reward($request);
        $this->assertWPError($response);
        $this->assertEquals('review_expired', $response->get_error_code());
    }

    /**
     * Test review verification with invalid review status
     */
    public function test_invalid_review_status() {
        add_filter('pre_http_request', function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'id' => 'test_review_id',
                    'businessUnit' => array(
                        'id' => 'test_business_unit'
                    ),
                    'consumer' => array(
                        'email' => 'test@example.com'
                    ),
                    'status' => 'removed'
                ))
            );
        });

        wp_set_current_user($this->test_user_id);
        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        $response = $this->trustpilot->handle_review_reward($request);
        $this->assertWPError($response);
        $this->assertEquals('invalid_review_status', $response->get_error_code());
    }

    /**
     * Test review verification with minimum rating requirement
     */
    public function test_minimum_rating_requirement() {
        add_filter('pre_http_request', function() {
            return array(
                'response' => array('code' => 200),
                'body' => json_encode(array(
                    'id' => 'test_review_id',
                    'businessUnit' => array(
                        'id' => 'test_business_unit'
                    ),
                    'consumer' => array(
                        'email' => 'test@example.com'
                    ),
                    'stars' => 2
                ))
            );
        });

        wp_set_current_user($this->test_user_id);
        $request = new WP_REST_Request('POST', '/wc-reward-points/v1/review');
        $request->set_param('review_id', 'test_review_id');

        $response = $this->trustpilot->handle_review_reward($request);
        $this->assertWPError($response);
        $this->assertEquals('rating_too_low', $response->get_error_code());
    }

    /**
     * Clean up test environment
     */
    public function tearDown(): void {
        parent::tearDown();
        delete_option('wc_rewards_trustpilot_business_unit_id');
        delete_option('wc_rewards_trustpilot_api_key');
        delete_option('wc_rewards_trustpilot_secret_key');
        remove_all_filters('pre_http_request');
        remove_all_filters('wc_rewards_review_points');
    }
} 