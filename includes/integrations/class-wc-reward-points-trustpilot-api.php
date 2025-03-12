<?php
/**
 * Trustpilot API Integration
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/integrations
 * @since      1.0.0
 */

namespace WC_Reward_Points\Integrations;

/**
 * Trustpilot API Integration Class
 *
 * Handles all Trustpilot API interactions
 */
class Trustpilot_API {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.trustpilot.com/v1';

    /**
     * Business Unit ID
     *
     * @var string
     */
    private $business_unit_id;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * API Secret
     *
     * @var string
     */
    private $api_secret;

    /**
     * Access token
     *
     * @var string
     */
    private $access_token;

    /**
     * Token expiration timestamp
     *
     * @var int
     */
    private $token_expires;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $business_unit_id Business Unit ID
     * @param string $api_key API Key
     * @param string $api_secret API Secret
     */
    public function __construct($business_unit_id, $api_key, $api_secret) {
        $this->business_unit_id = $business_unit_id;
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    /**
     * Get business unit information
     *
     * @return array|WP_Error Business unit data or error
     */
    public function get_business_unit() {
        $endpoint = "/business-units/{$this->business_unit_id}";
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Get reviews for the business unit
     *
     * @param array $params Query parameters
     * @return array|WP_Error Reviews data or error
     */
    public function get_reviews($params = array()) {
        $endpoint = "/business-units/{$this->business_unit_id}/reviews";
        return $this->make_request('GET', $endpoint, $params);
    }

    /**
     * Get a specific review by ID
     *
     * @param string $review_id Review ID
     * @return array|WP_Error Review data or error
     */
    public function get_review($review_id) {
        $endpoint = "/reviews/{$review_id}";
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Create a review invitation
     *
     * @param array $data Invitation data
     * @return array|WP_Error Response data or error
     */
    public function create_invitation($data) {
        $endpoint = "/business-units/{$this->business_unit_id}/invitation-links";
        return $this->make_request('POST', $endpoint, $data);
    }

    /**
     * Verify a review
     *
     * @param string $review_id Review ID
     * @return bool Whether the review is verified
     */
    public function verify_review($review_id) {
        try {
            $review = $this->get_review($review_id);
            
            if (is_wp_error($review)) {
                return false;
            }

            // Check if review exists and is verified
            return isset($review['verified']) && $review['verified'] === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Make an API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @return array|WP_Error Response data or error
     */
    private function make_request($method, $endpoint, $data = array()) {
        // Ensure we have a valid access token
        if (!$this->ensure_access_token()) {
            // Don't include sensitive data in error messages
            return new \WP_Error('trustpilot_auth_failed', __('Failed to authenticate with Trustpilot API', 'wc-reward-points'));
        }

        $url = $this->api_base_url . $endpoint;
        
        // Create safe copy of data for logging (without sensitive information)
        $safe_data = $data;
        if (isset($safe_data['password']) || isset($safe_data['apiKey']) || isset($safe_data['apiSecret'])) {
            $safe_data = $this->sanitize_data_for_logging($safe_data);
        }
        
        // Log the request (with sanitized data)
        if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
            \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                sprintf('Trustpilot API request: %s %s', $method, $endpoint),
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::DEBUG,
                array('method' => $method, 'endpoint' => $endpoint, 'data' => $safe_data)
            );
        }

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'apikey'       => $this->api_key
            ),
            // Add timeout setting from options, with fallback and limits
            'timeout' => min(max(absint(get_option('wc_reward_points_trustpilot_api_timeout', 10)), 5), 30)
        );

        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = wp_json_encode($data);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            // Check specifically for timeout errors
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();
            
            if ($error_code === 'http_request_failed' && strpos($error_message, 'timed out') !== false) {
                // Handle timeout specifically
                if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                        'Trustpilot API timeout error',
                        \WC_Reward_Points\Core\WC_Reward_Points_Debug::ERROR,
                        array('url' => $url, 'timeout' => $args['timeout'])
                    );
                }
                return new \WP_Error('trustpilot_timeout', __('Trustpilot API request timed out. Please try again later.', 'wc-reward-points'));
            }
            
            // Log other errors (without sensitive data)
            if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                    sprintf('Trustpilot API error: %s', $error_message),
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::ERROR
                );
            }
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $status_code = wp_remote_retrieve_response_code($response);
        
        // Log the response
        if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
            \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                sprintf('Trustpilot API response: %s %s', $status_code, $endpoint),
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::DEBUG,
                array('status' => $status_code, 'body' => $this->sanitize_data_for_logging($data))
            );
        }

        if (!$data) {
            return new \WP_Error(
                'trustpilot_invalid_response',
                __('Invalid response from Trustpilot API', 'wc-reward-points')
            );
        }

        if ($status_code >= 400) {
            return new \WP_Error(
                'trustpilot_api_error',
                isset($data['message']) ? $data['message'] : __('Unknown API error', 'wc-reward-points'),
                array('status' => $status_code)
            );
        }

        return $data;
    }

    /**
     * Sanitize data for logging to remove sensitive information
     *
     * @param array|string $data Data to sanitize
     * @return array|string Sanitized data
     */
    private function sanitize_data_for_logging($data) {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                // Recursively sanitize nested arrays
                if (is_array($value)) {
                    $sanitized[$key] = $this->sanitize_data_for_logging($value);
                    continue;
                }
                
                // Mask sensitive keys
                $sensitive_keys = array('password', 'apiKey', 'apiSecret', 'api_key', 'api_secret', 
                                       'secret', 'token', 'access_token', 'key');
                
                $is_sensitive = false;
                foreach ($sensitive_keys as $sensitive_key) {
                    if (stripos($key, $sensitive_key) !== false) {
                        $is_sensitive = true;
                        break;
                    }
                }
                
                if ($is_sensitive) {
                    $sanitized[$key] = $this->mask_sensitive_value($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            return $sanitized;
        }
        
        return $data;
    }
    
    /**
     * Mask a sensitive value for logging
     *
     * @param string $value Value to mask
     * @return string Masked value
     */
    private function mask_sensitive_value($value) {
        if (!is_string($value) || empty($value)) {
            return '';
        }
        
        $length = strlen($value);
        if ($length <= 4) {
            return '****';
        }
        
        // Show first 2 and last 2 characters, mask the rest
        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    /**
     * Ensure we have a valid access token
     *
     * @return bool Whether we have a valid token
     */
    private function ensure_access_token() {
        // Check if we have a valid token
        if ($this->access_token && $this->token_expires > time()) {
            return true;
        }

        // Log the token refresh attempt without sensitive data
        if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
            \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                'Refreshing Trustpilot API access token',
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::DEBUG
            );
        }

        // Get new access token
        $response = wp_remote_post(
            $this->api_base_url . '/oauth/oauth-business-users-for-applications/accesstoken',
            array(
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret)
                ),
                'body' => array(
                    'grant_type' => 'client_credentials'
                )
            )
        );

        if (is_wp_error($response)) {
            // Log error without exposing credentials
            if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                    sprintf('Failed to refresh Trustpilot access token: %s', $response->get_error_message()),
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::ERROR
                );
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['access_token'], $data['expires_in'])) {
            // Log error without exposing credentials
            if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                    'Invalid response when refreshing Trustpilot access token',
                    \WC_Reward_Points\Core\WC_Reward_Points_Debug::ERROR,
                    array('response_code' => wp_remote_retrieve_response_code($response))
                );
            }
            return false;
        }

        $this->access_token = $data['access_token'];
        $this->token_expires = time() + $data['expires_in'];

        // Log success without exposing token
        if (class_exists('\WC_Reward_Points\Core\WC_Reward_Points_Debug')) {
            $token_data = array(
                'expires_in' => $data['expires_in'],
                'expires_at' => date('Y-m-d H:i:s', $this->token_expires),
                'token' => $this->mask_sensitive_value($this->access_token)
            );
            
            \WC_Reward_Points\Core\WC_Reward_Points_Debug::log(
                'Successfully refreshed Trustpilot access token',
                \WC_Reward_Points\Core\WC_Reward_Points_Debug::DEBUG,
                $token_data
            );
        }

        return true;
    }

    /**
     * Create a review link for a customer
     *
     * @param string $name Customer name
     * @param string $email Customer email
     * @param string $reference Order reference
     * @return string|WP_Error Review link or error
     */
    public function create_review_link($name, $email, $reference) {
        $data = array(
            'name'      => $name,
            'email'     => $email,
            'reference' => $reference,
            'locale'    => get_locale()
        );

        $response = $this->create_invitation($data);

        if (is_wp_error($response)) {
            return $response;
        }

        return isset($response['url']) ? $response['url'] : new \WP_Error(
            'trustpilot_no_url',
            __('No review URL in response', 'wc-reward-points')
        );
    }

    /**
     * Get review statistics for the business unit
     *
     * @return array|WP_Error Statistics data or error
     */
    public function get_statistics() {
        $endpoint = "/business-units/{$this->business_unit_id}/reviews/statistics";
        return $this->make_request('GET', $endpoint);
    }

    /**
     * Check if a customer has already submitted a review.
     *
     * @param string $email Customer email
     * @param string $reference Optional order reference to check
     * @return array Result with status and details
     */
    public function has_customer_reviewed($email, $reference = '') {
        // Default result structure
        $result = array(
            'has_reviewed' => false,
            'review_count' => 0,
            'recent_review' => false,
            'details' => array(),
            'verified_by' => array()
        );
        
        // Check parameters
        if (empty($email)) {
            return $result;
        }
        
        // Build parameters for the API request
        $params = array(
            'limit' => 10, // Get more reviews to analyze patterns
            'orderBy' => 'createTime.desc' // Get newest first
        );
        
        // Add email filter
        $params['email'] = $email;
        
        // Get the customer's reviews
        $response = $this->get_reviews($params);
        
        if (is_wp_error($response) || empty($response['reviews'])) {
            return $result;
        }
        
        $reviews = $response['reviews'];
        $result['review_count'] = count($reviews);
        
        // Analyze results
        foreach ($reviews as $index => $review) {
            // For the first (most recent) review, store details
            if ($index === 0) {
                $result['recent_review'] = true;
                $result['details'] = array(
                    'id' => $review['id'] ?? '',
                    'rating' => $review['stars'] ?? 0,
                    'date' => $review['createdAt'] ?? '',
                    'title' => $review['title'] ?? '',
                    'reference' => $review['referenceId'] ?? ''
                );
            }
            
            // Build verification methods used
            if (!empty($review['verified'])) {
                $result['verified_by'][] = 'trustpilot';
            }
            
            // If we're checking for a specific order reference
            if (!empty($reference) && isset($review['referenceId']) && $review['referenceId'] === $reference) {
                $result['has_reviewed'] = true;
                $result['details']['reference_match'] = true;
            }
        }
        
        // Check fingerprinting data from multiple reviews
        if ($result['review_count'] > 1) {
            // Check for suspicious patterns in multiple reviews
            $ips = array();
            $devices = array();
            $browsers = array();
            $timeframes = array();
            
            foreach ($reviews as $review) {
                // Store metadata for pattern detection if available
                if (!empty($review['consumer']['metadata'])) {
                    $metadata = $review['consumer']['metadata'];
                    
                    if (!empty($metadata['ip'])) {
                        $ips[] = $metadata['ip'];
                    }
                    
                    if (!empty($metadata['device'])) {
                        $devices[] = $metadata['device'];
                    }
                    
                    if (!empty($metadata['userAgent'])) {
                        $browsers[] = $metadata['userAgent'];
                    }
                    
                    if (!empty($review['createdAt'])) {
                        $timeframes[] = strtotime($review['createdAt']);
                    }
                }
            }
            
            // If we have at least one review with metadata
            if (!empty($ips) || !empty($devices) || !empty($browsers)) {
                $result['verified_by'][] = 'metadata';
            }
            
            // Check for suspicious rapid review submission
            if (count($timeframes) > 1) {
                sort($timeframes);
                for ($i = 0; $i < count($timeframes) - 1; $i++) {
                    // If reviews were submitted within 24 hours of each other
                    if ($timeframes[$i + 1] - $timeframes[$i] < 86400) {
                        $result['suspicious_timing'] = true;
                        break;
                    }
                }
            }
        }
        
        // Set has_reviewed flag
        $result['has_reviewed'] = $result['review_count'] > 0;
        
        return $result;
    }

    /**
     * Verify multiple reviews in a batch
     *
     * @param array $review_ids Array of review IDs to verify
     * @return array Results for each review ID
     */
    public function batch_verify_reviews($review_ids) {
        if (empty($review_ids) || !is_array($review_ids)) {
            return array();
        }
        
        // Limit batch size to reasonable amount
        $review_ids = array_slice($review_ids, 0, 50);
        
        $results = array();
        
        // Use a batched request if possible
        if (count($review_ids) > 1) {
            // Create a batch request for the business unit's reviews
            $params = array(
                'limit' => count($review_ids),
                'ids' => implode(',', $review_ids)
            );
            
            $response = $this->get_reviews($params);
            
            if (!is_wp_error($response) && !empty($response['reviews'])) {
                // Index reviews by ID for fast lookup
                $reviews_by_id = array();
                foreach ($response['reviews'] as $review) {
                    if (isset($review['id'])) {
                        $reviews_by_id[$review['id']] = $review;
                    }
                }
                
                // Process each requested ID
                foreach ($review_ids as $review_id) {
                    if (isset($reviews_by_id[$review_id])) {
                        $review = $reviews_by_id[$review_id];
                        $results[$review_id] = array(
                            'exists' => true,
                            'verified' => !empty($review['verified']),
                            'stars' => $review['stars'] ?? 0,
                            'text' => $review['text'] ?? '',
                            'customer' => array(
                                'name' => $review['consumer']['name'] ?? '',
                                'email' => $review['consumer']['email'] ?? '',
                            )
                        );
                    } else {
                        $results[$review_id] = array(
                            'exists' => false,
                            'verified' => false,
                            'error' => 'Review not found'
                        );
                    }
                }
                
                return $results;
            }
        }
        
        // Fallback to individual requests if batch fails or only one ID
        foreach ($review_ids as $review_id) {
            $review = $this->get_review($review_id);
            
            if (is_wp_error($review)) {
                $results[$review_id] = array(
                    'exists' => false,
                    'verified' => false,
                    'error' => $review->get_error_message()
                );
            } else {
                $results[$review_id] = array(
                    'exists' => true,
                    'verified' => !empty($review['verified']),
                    'stars' => $review['stars'] ?? 0,
                    'text' => $review['text'] ?? '',
                    'customer' => array(
                        'name' => $review['consumer']['name'] ?? '',
                        'email' => $review['consumer']['email'] ?? '',
                    )
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get multiple business unit reviews in a batch
     *
     * @param array $params Parameters for filtering reviews
     * @return array|WP_Error Reviews data or error
     */
    public function batch_get_reviews_by_email($email_list) {
        if (empty($email_list) || !is_array($email_list)) {
            return array();
        }
        
        // Limit batch size
        $email_list = array_slice($email_list, 0, 20);
        
        $results = array();
        
        // Make individual requests as the API doesn't support multiple emails in one call
        foreach ($email_list as $email) {
            $params = array(
                'email' => $email,
                'limit' => 5,
                'orderBy' => 'createTime.desc'
            );
            
            $response = $this->get_reviews($params);
            
            if (!is_wp_error($response) && !empty($response['reviews'])) {
                $results[$email] = array(
                    'has_reviews' => true,
                    'reviews' => $response['reviews']
                );
            } else {
                $results[$email] = array(
                    'has_reviews' => false,
                    'reviews' => array()
                );
            }
        }
        
        return $results;
    }
} 