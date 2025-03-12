<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package WC_Reward_Points
 */

// First, we need to load the WordPress test environment.
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
    exit(1);
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // First, load WooCommerce if it exists
    if (file_exists(dirname(dirname(dirname(__FILE__))) . '/woocommerce/woocommerce.php')) {
        require dirname(dirname(dirname(__FILE__))) . '/woocommerce/woocommerce.php';
    }

    // Now load our plugin
    require dirname(dirname(__FILE__)) . '/wc-reward-points.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Create a sample post with a test shortcode for testing
function create_test_content() {
    // Create test page with shortcode
    $post_id = wp_insert_post(array(
        'post_title'    => 'Test Reward Points',
        'post_content'  => '[wc_reward_points_balance]',
        'post_status'   => 'publish',
        'post_type'     => 'page',
    ));
    
    // Create test order for WooCommerce (if WooCommerce is loaded)
    if (class_exists('WooCommerce')) {
        // Create a product
        $product_id = wp_insert_post(array(
            'post_title'    => 'Test Product',
            'post_type'     => 'product',
            'post_status'   => 'publish',
        ));
        
        // Set product price
        update_post_meta($product_id, '_price', '10.00');
        update_post_meta($product_id, '_regular_price', '10.00');
        
        // Create a test order
        $order = wc_create_order();
        $order->add_product(wc_get_product($product_id), 1);
        $order->calculate_totals();
        $order->update_status('completed');
    }
}
add_action('init', 'create_test_content');

// Load the base test case class
require dirname(__FILE__) . '/class-wc-reward-points-test-case.php'; 