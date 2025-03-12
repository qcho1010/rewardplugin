<?php
/**
 * Template for the signup reward page.
 *
 * @package WC_Reward_Points
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

$points = get_option('wc_reward_points_signup_points', 1000);
$cooldown = get_option('wc_reward_points_signup_cooldown', 30);
?>

<div class="wc-reward-points-signup-container">
    <div class="wc-reward-points-signup-card">
        <h1><?php _e('Claim Your Reward', 'wc-reward-points'); ?></h1>
        
        <div class="wc-reward-points-signup-content">
            <p class="reward-description">
                <?php printf(
                    __('You can claim %d reward points right now!', 'wc-reward-points'),
                    $points
                ); ?>
            </p>
            
            <div class="reward-info">
                <div class="reward-amount">
                    <span class="points"><?php echo esc_html($points); ?></span>
                    <span class="label"><?php _e('Points', 'wc-reward-points'); ?></span>
                </div>
                
                <div class="reward-cooldown">
                    <span class="days"><?php echo esc_html($cooldown); ?></span>
                    <span class="label"><?php _e('Day Cooldown', 'wc-reward-points'); ?></span>
                </div>
            </div>

            <button id="claim-reward" class="wc-reward-points-button">
                <?php _e('Claim Now', 'wc-reward-points'); ?>
            </button>

            <div class="reward-terms">
                <p>
                    <?php printf(
                        __('After claiming, you can claim again in %d days.', 'wc-reward-points'),
                        $cooldown
                    ); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#claim-reward').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).addClass('loading');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'process_signup_reward',
                nonce: '<?php echo wp_create_nonce('wc_reward_points_signup'); ?>'
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

        $('.wc-reward-points-signup-content').prepend($message);

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