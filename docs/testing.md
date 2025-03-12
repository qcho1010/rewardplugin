# WooCommerce Reward Points Plugin - Testing Guide

This document provides information on how to set up and run tests for the WooCommerce Reward Points plugin.

## Overview

We use PHPUnit to test the functionality of our WooCommerce Reward Points plugin. The tests cover core functionality, including points management, referral codes, ambassador codes, review eligibility, shortcodes, and template loading.

## Requirements

- PHP 7.2 or higher
- PHPUnit 8.0 or higher
- WordPress testing environment
- WooCommerce plugin (for integration tests)

## Setting Up the Test Environment

### 1. Install WordPress Test Environment

You can use the WP-CLI to set up the WordPress test environment:

```bash
# Install the WordPress test suite
bin/install-wp-tests.sh wordpress_test root password localhost latest
```

Replace `root` and `password` with your MySQL credentials.

Alternatively, you can manually set up the WordPress test environment by following these steps:

1. Create a test database (e.g., `wordpress_test`)
2. Download WordPress
3. Set up the WordPress test library
4. Configure the environment

### 2. Running Tests

Once you have set up the test environment, you can run the tests using the included script:

```bash
# Make sure the script is executable
chmod +x bin/run-tests.sh

# Run the tests
bin/run-tests.sh
```

Alternatively, you can run PHPUnit directly:

```bash
# Navigate to the plugin directory
cd rewardplugin

# Run PHPUnit
phpunit
```

## Test Structure

The tests are organized as follows:

- `tests/bootstrap.php`: Sets up the WordPress testing environment and loads the plugin
- `tests/test-reward-points.php`: Contains test cases for core functionality
- `phpunit.xml`: Configuration file for PHPUnit

## Coverage Report

To generate a code coverage report, you can run:

```bash
phpunit --coverage-html coverage
```

This will generate an HTML coverage report in the `coverage` directory.

## Writing Additional Tests

When adding new features or fixing bugs, please add appropriate tests. Here's a simple template for adding a new test:

```php
/**
 * Test [feature name]
 */
public function test_feature_name() {
    // Setup
    $variable = 'value';
    
    // Execute
    $result = function_to_test($variable);
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

## Continuous Integration

We use GitHub Actions for continuous integration. The workflow runs our PHPUnit tests on each push and pull request.

The CI workflow:
1. Sets up PHP and WordPress
2. Installs dependencies
3. Runs the tests
4. Reports any failures

## Troubleshooting

### Common Issues

1. **Tests aren't running**
   - Check that PHPUnit is installed
   - Verify the WordPress test environment is set up correctly
   - Ensure database credentials are correct

2. **WooCommerce-dependent tests failing**
   - Make sure WooCommerce is installed in the test environment
   - Check that WooCommerce is being loaded before our plugin

3. **Permission issues**
   - Make sure the run-tests.sh script is executable
   - Check file permissions on the test directory 