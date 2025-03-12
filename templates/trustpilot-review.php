<?php
/**
 * Template for Trustpilot review submission
 *
 * @package WC_Reward_Points
 */

defined('ABSPATH') || exit;

// Get current user
$user_id = get_current_user_id();
$user = get_user_by('id', $user_id);

// Get points info
$points = get_option('wc_reward_points_trustpilot_review_points', 300);
$min_rating = get_option('wc_reward_points_trustpilot_min_rating', 1);
$min_length = get_option('wc_reward_points_trustpilot_min_length', 50);

// Check for pending review
$pending_review = get_user_meta($user_id, '_wc_review_pending', true);

// Check last review date
$last_review = get_user_meta($user_id, '_wc_last_review_date', true);
$cooldown = get_option('wc_reward_points_trustpilot_cooldown', 0);

// Get completed orders count
$completed_orders = wc_get_orders(array(
    'customer' => $user_id,
    'status'   => array('completed'),
    'return'   => 'ids',
));

/**
 * Display the review form
 *
 * @param int $points Points awarded for review
 * @param int $min_rating Minimum rating required
 * @param int $min_length Minimum review length required
 */
function wc_reward_points_display_review_form($points, $min_rating, $min_length) {
    ?>
    <div class="wc-reward-points-notice wc-reward-points-notice--success">
        <p>
            <?php
            printf(
                esc_html__('You can earn %d points by leaving a verified review!', 'wc-reward-points'),
                $points
            );
            ?>
        </p>
        <p>
            <a href="<?php echo esc_url(home_url('rewards/review')); ?>" class="button">
                <?php esc_html_e('Leave a Review', 'wc-reward-points'); ?>
            </a>
        </p>
    </div>
    <?php
}
?>

<div class="wc-reward-points-trustpilot">
    <h2><?php esc_html_e('Trustpilot Review Rewards', 'wc-reward-points'); ?></h2>

    <?php if (!is_user_logged_in()) : ?>
        <div class="wc-reward-points-notice wc-reward-points-notice--error">
            <p>
                <?php
                printf(
                    esc_html__('Please %1$slog in%2$s to submit a review.', 'wc-reward-points'),
                    '<a href="' . esc_url(wc_get_page_permalink('myaccount')) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
    <?php elseif ($pending_review) : ?>
        <div class="wc-reward-points-notice wc-reward-points-notice--info">
            <p><?php esc_html_e('Your review is pending verification. Points will be awarded after the review is verified.', 'wc-reward-points'); ?></p>
            <p>
                <?php
                printf(
                    esc_html__('Review Link: %s', 'wc-reward-points'),
                    '<a href="' . esc_url($pending_review['review_link']) . '" target="_blank">' . esc_html__('Click here to leave your review', 'wc-reward-points') . '</a>'
                );
                ?>
            </p>
        </div>
    <?php elseif ($last_review && $cooldown === 0) : ?>
        <div class="wc-reward-points-notice wc-reward-points-notice--info">
            <p><?php esc_html_e('You have already earned points for a review. Only one review per customer is allowed.', 'wc-reward-points'); ?></p>
        </div>
    <?php elseif ($last_review && $cooldown > 0) : ?>
        <?php
        $next_review = strtotime($last_review) + ($cooldown * DAY_IN_SECONDS);
        if (time() < $next_review) :
        ?>
            <div class="wc-reward-points-notice wc-reward-points-notice--info">
                <p>
                    <?php
                    printf(
                        esc_html__('You can leave another review in %d days.', 'wc-reward-points'),
                        ceil(($next_review - time()) / DAY_IN_SECONDS)
                    );
                    ?>
                </p>
            </div>
        <?php else : ?>
            <?php wc_reward_points_display_review_form($points, $min_rating, $min_length); ?>
        <?php endif; ?>
    <?php elseif (empty($completed_orders)) : ?>
        <div class="wc-reward-points-notice wc-reward-points-notice--error">
            <p><?php esc_html_e('You need to have at least one completed order to leave a review.', 'wc-reward-points'); ?></p>
        </div>
    <?php else : ?>
        <?php wc_reward_points_display_review_form($points, $min_rating, $min_length); ?>
    <?php endif; ?>

    <div class="wc-reward-points-trustpilot-info">
        <h3><?php esc_html_e('Review Requirements', 'wc-reward-points'); ?></h3>
        <ul>
            <li>
                <?php
                printf(
                    esc_html__('Earn %d points for each verified review', 'wc-reward-points'),
                    $points
                );
                ?>
            </li>
            <li>
                <?php
                printf(
                    esc_html__('Minimum rating required: %d stars', 'wc-reward-points'),
                    $min_rating
                );
                ?>
            </li>
            <li>
                <?php
                printf(
                    esc_html__('Minimum review length: %d characters', 'wc-reward-points'),
                    $min_length
                );
                ?>
            </li>
            <?php if ($cooldown > 0) : ?>
                <li>
                    <?php
                    printf(
                        esc_html__('Wait %d days between reviews', 'wc-reward-points'),
                        $cooldown
                    );
                    ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<style>
.wc-reward-points-trustpilot {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.wc-reward-points-notice {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.wc-reward-points-notice--error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.wc-reward-points-notice--info {
    background-color: #cce5ff;
    border: 1px solid #b8daff;
    color: #004085;
}

.wc-reward-points-notice--success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.wc-reward-points-trustpilot-info {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-top: 30px;
}

.wc-reward-points-trustpilot-info ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.wc-reward-points-trustpilot-info li {
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.wc-reward-points-trustpilot-info li:last-child {
    border-bottom: none;
}

.wc-reward-points-trustpilot h2 {
    margin-bottom: 30px;
    text-align: center;
}

.wc-reward-points-trustpilot h3 {
    margin-bottom: 20px;
}

.wc-reward-points-trustpilot a {
    color: #0056b3;
    text-decoration: none;
}

.wc-reward-points-trustpilot a:hover {
    text-decoration: underline;
}
</style> 