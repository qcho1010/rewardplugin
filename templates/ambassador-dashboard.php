<?php
/**
 * Frontend Brand Ambassador Dashboard Template
 *
 * @package    WC_Reward_Points
 * @version    1.0.0
 */

defined('ABSPATH') || exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    return;
}

$user_id = get_current_user_id();
$code = get_user_meta($user_id, '_wc_ambassador_code', true);

// Check if user is an ambassador
if (!$code) {
    ?>
    <div class="wc-reward-points-ambassador-not-found">
        <p><?php esc_html_e('You are not currently a brand ambassador.', 'wc-reward-points'); ?></p>
        <p>
            <a href="<?php echo esc_url(home_url('ambassador-program')); ?>" class="button">
                <?php esc_html_e('Apply Now', 'wc-reward-points'); ?>
            </a>
        </p>
    </div>
    <?php
    return;
}

// Get ambassador stats
$referral_count = get_user_meta($user_id, '_wc_ambassador_referral_count', true) ?: 0;
$total_points = get_user_meta($user_id, '_wc_ambassador_total_points', true) ?: 0;
$points_ratio = get_option('wc_reward_points_currency_ratio', 100);
$earnings = number_format($total_points / $points_ratio, 2);
$join_date = get_user_meta($user_id, '_wc_ambassador_join_date', true);

// Get recent referrals
$recent_referrals = get_posts(array(
    'post_type' => 'shop_order',
    'posts_per_page' => 5,
    'meta_key' => '_wc_ambassador_referral',
    'meta_value' => $code,
    'post_status' => array_keys(wc_get_order_statuses()),
    'orderby' => 'date',
    'order' => 'DESC'
));

// Get monthly stats
$current_month = date('Y-m');
$monthly_referrals = 0;
$monthly_points = 0;

foreach ($recent_referrals as $referral) {
    $order = wc_get_order($referral->ID);
    if (!$order) continue;
    
    if (date('Y-m', strtotime($order->get_date_created())) === $current_month) {
        $monthly_referrals++;
        $monthly_points += get_post_meta($referral->ID, '_wc_ambassador_points_earned', true);
    }
}

$monthly_earnings = number_format($monthly_points / $points_ratio, 2);

// Get referral URL
$referral_url = home_url('?ref=' . $code);
?>

<div class="wc-reward-points-ambassador-dashboard">
    <!-- Welcome Section -->
    <div class="dashboard-welcome">
        <h2><?php esc_html_e('Welcome to Your Ambassador Dashboard', 'wc-reward-points'); ?></h2>
        <p class="member-since">
            <?php 
            printf(
                esc_html__('Member since: %s', 'wc-reward-points'),
                date_i18n(get_option('date_format'), strtotime($join_date))
            ); 
            ?>
        </p>
    </div>

    <!-- Stats Overview -->
    <div class="dashboard-stats">
        <div class="stat-box total-earnings">
            <span class="stat-icon dashicons dashicons-money-alt"></span>
            <div class="stat-content">
                <h3><?php esc_html_e('Total Earnings', 'wc-reward-points'); ?></h3>
                <div class="stat-value">
                    <?php echo esc_html(get_woocommerce_currency_symbol() . $earnings); ?>
                    <small><?php echo esc_html($total_points); ?> <?php esc_html_e('points', 'wc-reward-points'); ?></small>
                </div>
            </div>
        </div>

        <div class="stat-box total-referrals">
            <span class="stat-icon dashicons dashicons-groups"></span>
            <div class="stat-content">
                <h3><?php esc_html_e('Total Referrals', 'wc-reward-points'); ?></h3>
                <div class="stat-value">
                    <?php echo esc_html($referral_count); ?>
                    <small><?php esc_html_e('customers', 'wc-reward-points'); ?></small>
                </div>
            </div>
        </div>

        <div class="stat-box monthly-earnings">
            <span class="stat-icon dashicons dashicons-chart-line"></span>
            <div class="stat-content">
                <h3><?php esc_html_e('This Month', 'wc-reward-points'); ?></h3>
                <div class="stat-value">
                    <?php echo esc_html(get_woocommerce_currency_symbol() . $monthly_earnings); ?>
                    <small><?php echo esc_html($monthly_referrals); ?> <?php esc_html_e('referrals', 'wc-reward-points'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Section -->
    <div class="dashboard-share">
        <h3><?php esc_html_e('Share Your Referral Link', 'wc-reward-points'); ?></h3>
        <div class="share-url">
            <input type="text" value="<?php echo esc_url($referral_url); ?>" readonly>
            <button class="button copy-url" data-clipboard-text="<?php echo esc_attr($referral_url); ?>">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e('Copy', 'wc-reward-points'); ?>
            </button>
        </div>
        <div class="share-buttons">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" target="_blank" class="share-button facebook">
                <span class="dashicons dashicons-facebook"></span>
                <?php esc_html_e('Share on Facebook', 'wc-reward-points'); ?>
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($referral_url); ?>&text=<?php echo urlencode(__('Check out this amazing store!', 'wc-reward-points')); ?>" target="_blank" class="share-button twitter">
                <span class="dashicons dashicons-twitter"></span>
                <?php esc_html_e('Share on Twitter', 'wc-reward-points'); ?>
            </a>
            <a href="https://api.whatsapp.com/send?text=<?php echo urlencode(__('Check out this amazing store!', 'wc-reward-points') . ' ' . $referral_url); ?>" target="_blank" class="share-button whatsapp">
                <span class="dashicons dashicons-whatsapp"></span>
                <?php esc_html_e('Share on WhatsApp', 'wc-reward-points'); ?>
            </a>
        </div>
    </div>

    <!-- Recent Referrals -->
    <div class="dashboard-recent">
        <h3><?php esc_html_e('Recent Referrals', 'wc-reward-points'); ?></h3>
        <?php if (!empty($recent_referrals)) : ?>
            <table class="referrals-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Date', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Customer', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Amount', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Points', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Status', 'wc-reward-points'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_referrals as $referral) : 
                        $order = wc_get_order($referral->ID);
                        if (!$order) continue;
                        
                        $points = get_post_meta($referral->ID, '_wc_ambassador_points_earned', true);
                    ?>
                        <tr>
                            <td>#<?php echo esc_html($order->get_order_number()); ?></td>
                            <td><?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?></td>
                            <td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
                            <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                            <td><?php echo esc_html($points); ?></td>
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
            <p class="no-referrals">
                <?php esc_html_e('No referrals yet. Share your link to start earning!', 'wc-reward-points'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
.wc-reward-points-ambassador-dashboard {
    max-width: 1200px;
    margin: 0 auto;
}

.dashboard-welcome {
    margin-bottom: 30px;
}

.member-since {
    color: #646970;
    font-style: italic;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
}

.stat-icon {
    font-size: 24px;
    width: 48px;
    height: 48px;
    background: #f0f6fc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: #2271b1;
}

.stat-content {
    flex: 1;
}

.stat-content h3 {
    margin: 0 0 5px;
    font-size: 14px;
    color: #646970;
}

.stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #1e1e1e;
    line-height: 1.2;
}

.stat-value small {
    display: block;
    font-size: 14px;
    color: #646970;
    font-weight: normal;
}

.dashboard-share {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 40px;
}

.share-url {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.share-url input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f8f9fa;
}

.copy-url {
    display: flex;
    align-items: center;
    gap: 5px;
}

.share-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.share-button {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 4px;
    text-decoration: none;
    color: #fff;
    font-size: 14px;
    transition: opacity 0.2s;
}

.share-button:hover {
    opacity: 0.9;
    color: #fff;
}

.share-button .dashicons {
    margin-right: 5px;
}

.share-button.facebook {
    background: #1877f2;
}

.share-button.twitter {
    background: #1da1f2;
}

.share-button.whatsapp {
    background: #25d366;
}

.dashboard-recent {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.referrals-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.referrals-table th,
.referrals-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #f0f0f1;
}

.referrals-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.order-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    text-transform: uppercase;
}

.status-completed,
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

.status-cancelled,
.status-failed {
    background: #eba3a3;
    color: #761919;
}

.status-refunded {
    background: #e5e5e5;
    color: #777;
}

.no-referrals {
    text-align: center;
    padding: 30px;
    color: #646970;
    font-style: italic;
}

/* Responsive styles */
@media screen and (max-width: 782px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .share-url {
        flex-direction: column;
    }
    
    .referrals-table {
        display: block;
        overflow-x: auto;
    }
    
    .referrals-table th,
    .referrals-table td {
        white-space: nowrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize clipboard.js
    new ClipboardJS('.copy-url');

    // Add copy feedback
    $('.copy-url').click(function(e) {
        e.preventDefault();
        var $button = $(this);
        var originalText = $button.html();
        
        $button.html('<span class="dashicons dashicons-yes"></span> <?php esc_html_e('Copied!', 'wc-reward-points'); ?>');
        $button.addClass('button-primary');
        
        setTimeout(function() {
            $button.html(originalText);
            $button.removeClass('button-primary');
        }, 2000);
    });
});</script> 