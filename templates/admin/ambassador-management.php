<?php
/**
 * Admin Brand Ambassador Management Template
 *
 * @package    WC_Reward_Points
 * @version    1.0.0
 */

defined('ABSPATH') || exit;

// Display success messages
if (isset($_GET['approved'])) {
    ?>
    <div class="notice notice-success">
        <p><?php esc_html_e('Ambassador application approved successfully.', 'wc-reward-points'); ?></p>
    </div>
    <?php
}
if (isset($_GET['rejected'])) {
    ?>
    <div class="notice notice-success">
        <p><?php esc_html_e('Ambassador application rejected successfully.', 'wc-reward-points'); ?></p>
    </div>
    <?php
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Brand Ambassadors', 'wc-reward-points'); ?></h1>

    <!-- Pending Applications -->
    <div class="ambassador-section">
        <h2><?php esc_html_e('Pending Applications', 'wc-reward-points'); ?></h2>
        <?php if (!empty($applications)) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Email', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Application Date', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Actions', 'wc-reward-points'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $user) : 
                        $application_date = get_user_meta($user->ID, '_wc_ambassador_application_date', true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($application_date))); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_ambassador">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                    <?php wp_nonce_field('approve_ambassador'); ?>
                                    <button type="submit" class="button button-primary">
                                        <?php esc_html_e('Approve', 'wc-reward-points'); ?>
                                    </button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline; margin-left: 5px;">
                                    <input type="hidden" name="action" value="reject_ambassador">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                    <?php wp_nonce_field('reject_ambassador'); ?>
                                    <button type="submit" class="button">
                                        <?php esc_html_e('Reject', 'wc-reward-points'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No pending applications.', 'wc-reward-points'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Active Ambassadors -->
    <div class="ambassador-section" style="margin-top: 30px;">
        <h2><?php esc_html_e('Active Ambassadors', 'wc-reward-points'); ?></h2>
        <?php if (!empty($ambassadors)) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ambassador', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Code', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Total Referrals', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Total Earnings', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Status', 'wc-reward-points'); ?></th>
                        <th><?php esc_html_e('Actions', 'wc-reward-points'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ambassadors as $user) : 
                        $code = get_user_meta($user->ID, '_wc_ambassador_code', true);
                        $referral_count = get_user_meta($user->ID, '_wc_ambassador_referral_count', true) ?: 0;
                        $total_points = get_user_meta($user->ID, '_wc_ambassador_total_points', true) ?: 0;
                        $points_ratio = get_option('wc_reward_points_currency_ratio', 100);
                        $earnings = number_format($total_points / $points_ratio, 2);
                    ?>
                        <tr>
                            <td>
                                <?php echo esc_html($user->display_name); ?>
                                <br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td>
                                <code><?php echo esc_html($code); ?></code>
                                <button class="button button-small copy-code" data-clipboard-text="<?php echo esc_attr($code); ?>">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                            </td>
                            <td><?php echo esc_html($referral_count); ?></td>
                            <td>
                                <?php echo esc_html(get_woocommerce_currency_symbol() . $earnings); ?>
                                <br>
                                <small><?php echo esc_html($total_points); ?> <?php esc_html_e('points', 'wc-reward-points'); ?></small>
                            </td>
                            <td>
                                <span class="status-active"><?php esc_html_e('Active', 'wc-reward-points'); ?></span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-reward-points&tab=ambassador&user=' . $user->ID)); ?>" class="button">
                                    <?php esc_html_e('View Details', 'wc-reward-points'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No active ambassadors.', 'wc-reward-points'); ?></p>
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

.ambassador-section h2 {
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

.status-active {
    background: #00a32a;
    color: #fff;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    text-transform: uppercase;
}

/* Responsive table */
@media screen and (max-width: 782px) {
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