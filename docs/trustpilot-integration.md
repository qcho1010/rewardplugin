# Trustpilot Review Rewards Integration

This document provides information on how to set up and use the Trustpilot Review Rewards feature in the WooCommerce Reward Points plugin.

## Overview

The Trustpilot Review Rewards feature allows you to reward customers with points for leaving verified reviews on Trustpilot. This helps encourage customer feedback while building your brand's reputation on Trustpilot.

## Requirements

- A Trustpilot Business account
- Trustpilot API credentials (Business Unit ID, API Key, and API Secret)
- WooCommerce Reward Points plugin installed and activated

## Setup Instructions

### 1. Obtain Trustpilot API Credentials

1. Log in to your [Trustpilot Business account](https://businessapp.trustpilot.com/)
2. Navigate to Integrations > API Integration
3. Create a new API application if you don't have one
4. Copy your Business Unit ID, API Key, and API Secret

### 2. Configure the Plugin

1. Go to WooCommerce > Reward Points > Settings
2. Click on the "Trustpilot" tab
3. Enter your Trustpilot API credentials:
   - Business Unit ID
   - API Key
   - API Secret
4. Configure the reward settings:
   - Points per review (default: 300)
   - Minimum rating required (1-5 stars)
   - Minimum review length (characters)
5. Configure security settings:
   - Verification period (days to wait before verifying a review)
   - Review cooldown (days between allowed reviews, 0 for one-time only)
6. Save changes
7. Click "Test Connection" to ensure your API credentials are working correctly

### 3. Add the Review Form to Your Site

Use the provided shortcode to add the Trustpilot review submission form to any page:

```
[wc_reward_points_trustpilot_review]
```

Recommended locations:
- My Account page
- Thank you page
- Dedicated rewards page

## How it Works

1. **Customer Submits a Review Request**:
   - Customer clicks the "Leave a Review" button on your site
   - The system verifies eligibility (completed orders, cooldown period)
   - If eligible, the customer is redirected to Trustpilot with a pre-filled review form

2. **Review Verification**:
   - After the configured verification period (default: 7 days), the system checks if the review was submitted
   - The system verifies that the review meets the minimum requirements (rating, length)

3. **Points Awarding**:
   - If the review is verified, the configured points are awarded to the customer
   - The customer receives an email notification about the points earned
   - The review is marked as rewarded to prevent duplicate points

## Features

- **Automated Verification**: The system automatically verifies and awards points for valid reviews
- **Customizable Rewards**: Set the number of points awarded per review
- **Quality Control**: Set minimum requirements for rating and review length
- **Fraud Prevention**: One-time reward per customer or configurable cooldown period
- **Email Notifications**: Automatic emails when points are awarded
- **Tracking & Reporting**: Track all point transactions in the points history

## Order Email Integration

The plugin automatically adds a review invitation link to the order completion emails sent to customers. This provides an easy way for customers to leave a review after their purchase.

## Troubleshooting

### API Connection Issues

- Verify your API credentials are entered correctly
- Ensure your Trustpilot account has API access enabled
- Check if your server can make outbound HTTPS connections to Trustpilot's API

### Missing Reviews

- Check the verification period setting - reviews are only verified after this period
- Verify that customers are completing the review process on Trustpilot
- Ensure the review meets the minimum requirements (rating and length)

### Point Awarding Issues

- Check the points history to see if points have been awarded
- Verify that the review cooldown period has been respected
- Check for any error logs that might indicate issues with the verification process

## Best Practices

1. **Set Reasonable Point Values**: The default is 300 points ($3 at 100 points = $1), but adjust based on your business model
2. **Promote the Opportunity**: Make customers aware they can earn points for reviews
3. **Quality Over Quantity**: Set reasonable minimum requirements to encourage thoughtful reviews
4. **Monitor Performance**: Regularly check your Trustpilot dashboard and the points awarded
5. **Keep API Credentials Secure**: Do not share your API credentials

## Support

For additional support, please contact our support team at support@example.com or refer to the main plugin documentation. 