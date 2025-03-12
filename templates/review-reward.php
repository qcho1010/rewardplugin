<?php
/**
 * Template for the review reward page.
 *
 * @package WC_Reward_Points
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

$points = get_option('wc_reward_points_review_points', 300);
$business_id = get_option('wc_reward_points_trustpilot_business_id');
?>

<div class="wc-reward-points-review-container">
    <div class="wc-reward-points-review-card">
        <h1><?php _e('Review & Earn Rewards', 'wc-reward-points'); ?></h1>
        
        <div class="wc-reward-points-review-content">
            <div class="review-info">
                <h2><?php _e('Share Your Experience', 'wc-reward-points'); ?></h2>
                <p class="reward-description">
                    <?php printf(
                        __('Leave us a review on Trustpilot and earn %d reward points!', 'wc-reward-points'),
                        $points
                    ); ?>
                </p>
                
                <div class="reward-amount">
                    <span class="points"><?php echo esc_html($points); ?></span>
                    <span class="label"><?php _e('Review Points', 'wc-reward-points'); ?></span>
                </div>
            </div>

            <div class="review-steps">
                <h3><?php _e('How It Works', 'wc-reward-points'); ?></h3>
                
                <ol>
                    <li>
                        <?php _e('Click the button below to visit our Trustpilot page', 'wc-reward-points'); ?>
                    </li>
                    <li>
                        <?php _e('Write and submit your honest review', 'wc-reward-points'); ?>
                    </li>
                    <li>
                        <?php _e('Copy your review URL and paste it below', 'wc-reward-points'); ?>
                    </li>
                    <li>
                        <?php _e('Click verify to claim your points', 'wc-reward-points'); ?>
                    </li>
                </ol>

                <a href="https://www.trustpilot.com/review/<?php echo esc_attr($business_id); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer" 
                   class="wc-reward-points-button review-button">
                    <?php _e('Write a Review', 'wc-reward-points'); ?>
                </a>
            </div>

            <div class="review-verification">
                <h3><?php _e('Verify Your Review', 'wc-reward-points'); ?></h3>
                
                <div class="review-form">
                    <label for="review-url"><?php _e('Your Review URL', 'wc-reward-points'); ?></label>
                    <input type="url" 
                           id="review-url" 
                           placeholder="https://www.trustpilot.com/reviews/..." 
                           required />
                    
                    <button id="verify-review" class="wc-reward-points-button">
                        <?php _e('Verify & Claim Points', 'wc-reward-points'); ?>
                    </button>
                </div>
            </div>

            <div class="review-terms">
                <p>
                    <?php _e('Review rewards can only be claimed once per customer. Your review must be genuine and follow Trustpilot\'s guidelines.', 'wc-reward-points'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#verify-review').on('click', function() {
        var $button = $(this);
        var reviewUrl = $('#review-url').val();

        if (!reviewUrl) {
            showMessage('<?php _e('Please enter your review URL.', 'wc-reward-points'); ?>', 'error');
            return;
        }

        $button.prop('disabled', true).addClass('loading');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'process_review_reward',
                nonce: '<?php echo wp_create_nonce('wc_reward_points_review'); ?>',
                review_url: reviewUrl
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = '<?php echo wc_get_page_permalink('myaccount'); ?>';
                    }, 2000);
                } else {
                    showMessage(response.data, 'error');
                    $button.prop('disabled', false).removeClass('loading');
                }
            },
            error: function() {
                showMessage('<?php _e('An error occurred. Please try again.', 'wc-reward-points'); ?>', 'error');
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });

    function showMessage(message, type) {
        var $message = $('<div>')
            .addClass('wc-reward-points-message ' + type)
            .text(message);

        $('.wc-reward-points-review-content').prepend($message);

        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
});
</script>

<?php
get_footer('shop');
?> 