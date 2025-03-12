<?php
/**
 * Admin Brand Ambassador Details Template
 *
 * @package    WC_Reward_Points
 * @version    1.0.0
 */

defined('ABSPATH') || exit;

$user_id = isset($_GET['user']) ? absint($_GET['user']) : 0;
$user = get_user_by('id', $user_id);

if (!$user || !get_user_meta($user_id, '_wc_ambassador_code', true)) {
    wp_die(__('Invalid ambassador.', 'wc-reward-points'));
}

$code = get_user_meta($user_id, '_wc_ambassador_code', true);
$referral_count = get_user_meta($user_id, '_wc_ambassador_referral_count', true) ?: 0;
$total_points = get_user_meta($user_id, '_wc_ambassador_total_points', true) ?: 0;
$points_ratio = get_option('wc_reward_points_currency_ratio', 100);
$earnings = number_format($total_points / $points_ratio, 2);
$join_date = get_user_meta($user_id, '_wc_ambassador_join_date', true);

// Get referral history
$referrals = get_posts(array(
    'post_type' => 'shop_order',
    'posts_per_page' => -1,
    'meta_key' => '_wc_ambassador_referral',
    'meta_value' => $code,
    'post_status' => array_keys(wc_get_order_statuses())
));
?>

<div class="wrap">
    <h1>
        <?php echo esc_html(sprintf(__('Ambassador: %s', 'wc-reward-points'), $user->display_name)); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-reward-points&tab=ambassador')); ?>" class="page-title-action">
            <?php esc_html_e('Back to List', 'wc-reward-points'); ?>
        </a>
    </h1>

    <!-- Ambassador Overview -->
    <div class="ambassador-section">
        <div class="ambassador-stats">
            <div class="stat-box">
                <h3><?php esc_html_e('Total Referrals', 'wc-reward-points'); ?></h3>
                <span class="stat-value"><?php echo esc_html($referral_count); ?></span>
            </div>
            <div class="stat-box">
                <h3><?php esc_html_e('Total Points', 'wc-reward-points'); ?></h3>
                <span class="stat-value"><?php echo esc_html($total_points); ?></span>
            </div>
            <div class="stat-box">
                <h3><?php esc_html_e('Total Earnings', 'wc-reward-points'); ?></h3>
                <span class="stat-value"><?php echo esc_html(get_woocommerce_currency_symbol() . $earnings); ?></span>
            </div>
            <div class="stat-box">
                <h3><?php esc_html_e('Ambassador Since', 'wc-reward-points'); ?></h3>
                <span class="stat-value"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($join_date))); ?></span>
            </div>
        </div>

        <div class="ambassador-info">
            <h3><?php esc_html_e('Ambassador Information', 'wc-reward-points'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Name', 'wc-reward-points'); ?></th>
                    <td><?php echo esc_html($user->display_name); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Email', 'wc-reward-points'); ?></th>
                    <td><?php echo esc_html($user->user_email); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Referral Code', 'wc-reward-points'); ?></th>
                    <td>
                        <code><?php echo esc_html($code); ?></code>
                        <button class="button button-small copy-code" data-clipboard-text="<?php echo esc_attr($code); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Referral URL', 'wc-reward-points'); ?></th>
                    <td>
                        <?php $referral_url = home_url('?ref=' . $code); ?>
                        <code><?php echo esc_url($referral_url); ?></code>
                        <button class="button button-small copy-code" data-clipboard-text="<?php echo esc_attr($referral_url); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Referral History -->
    <div class="ambassador-section">
        <h2><?php esc_html_e('Referral History', 'wc-reward-points'); ?></h2>
        <?php if (!empty($referrals)) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Customer', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Order Total', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Points Earned', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Date', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Status', 'wc-reward-points'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $referral) : 
                        $order = wc_get_order($referral->ID);
                        if (!$order) continue;
                        
                        $points = get_post_meta($referral->ID, '_wc_ambassador_points_earned', true);
                    ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url($order->get_edit_order_url()); ?>" target="_blank">
                                    #<?php echo esc_html($order->get_order_number()); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo esc_html($order->get_formatted_billing_full_name()); ?>
                                <br>
                                <small><?php echo esc_html($order->get_billing_email()); ?></small>
                            </td>
                            <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                            <td><?php echo esc_html($points); ?></td>
                            <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?></td>
                            <td>
                                <span class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No referrals found.', 'wc-reward-points'); ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.ambassador-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
}

.ambassador-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    text-align: center;
}

.stat-box h3 {
    margin: 0 0 10px;
    color: #23282d;
    font-size: 14px;
}

.stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #1e1e1e;
}

.ambassador-info {
    margin-top: 30px;
}

.ambassador-info h3 {
    margin-top: 0;
}

.copy-code {
    margin-left: 5px !important;
    padding: 0 4px !important;
    min-height: 22px !important;
}

.copy-code .dashicons {
    width: 16px;
    height: 16px;
    font-size: 16px;
    vertical-align: text-bottom;
}

.order-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    text-transform: uppercase;
}

.status-completed {
    background: #c6e1c6;
    color: #5b841b;
}

.status-processing {
    background: #c6e1c6;
    color: #5b841b;
}

.status-on-hold {
    background: #f8dda7;
    color: #94660c;
}

.status-pending {
    background: #e5e5e5;
    color: #777;
}

.status-cancelled {
    background: #eba3a3;
    color: #761919;
}

.status-refunded {
    background: #e5e5e5;
    color: #777;
}

.status-failed {
    background: #eba3a3;
    color: #761919;
}

/* Responsive styles */
@media screen and (max-width: 782px) {
    .ambassador-stats {
        grid-template-columns: 1fr;
    }
    
    .widefat td {
        padding: 8px 10px;
    }
    
    .copy-code {
        margin-top: 5px !important;
        display: inline-block;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize clipboard.js
    new ClipboardJS('.copy-code');

    // Add copy feedback
    $('.copy-code').click(function(e) {
        e.preventDefault();
        var $button = $(this);
        $button.addClass('button-primary');
        setTimeout(function() {
            $button.removeClass('button-primary');
        }, 1000);
    });
});</script> 