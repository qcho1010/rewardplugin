<?php
/**
 * Points redemption field at checkout
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/templates
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables that should be available: $points_balance, $conversion_rate, $max_points, $max_percentage
?>

<div class="wc-reward-points-redemption-wrapper">
    <h3><?php esc_html_e('Reward Points', 'wc-reward-points'); ?></h3>
    
    <?php if ($points_balance > 0) : ?>
        <div class="wc-reward-points-balance">
            <p>
                <?php 
                echo sprintf(
                    esc_html__('You have %s reward points available (%s).', 'wc-reward-points'),
                    '<strong>' . number_format($points_balance) . '</strong>',
                    wc_price($points_balance / $conversion_rate)
                ); 
                ?>
            </p>
            <p class="redemption-info">
                <?php 
                echo sprintf(
                    esc_html__('You can redeem up to %s points (%s) on this order.', 'wc-reward-points'),
                    '<strong>' . number_format($max_points) . '</strong>',
                    wc_price($max_points / $conversion_rate)
                ); 
                ?>
            </p>
            <p class="redemption-rate">
                <small>
                    <?php 
                    echo sprintf(
                        esc_html__('Conversion rate: %s points = %s', 'wc-reward-points'),
                        $conversion_rate,
                        wc_price(1)
                    ); 
                    ?>
                </small>
            </p>
            
            <div class="wc-reward-points-redemption-form">
                <p class="form-row">
                    <label for="wc_reward_points_redemption"><?php esc_html_e('Points to redeem:', 'wc-reward-points'); ?></label>
                    <input 
                        type="number" 
                        id="wc_reward_points_redemption" 
                        class="input-text" 
                        min="0" 
                        max="<?php echo esc_attr($max_points); ?>" 
                        step="1"
                        placeholder="<?php esc_attr_e('Enter points', 'wc-reward-points'); ?>"
                    >
                    <button 
                        type="button" 
                        class="button" 
                        id="wc_reward_points_apply"
                        data-nonce="<?php echo wp_create_nonce('wc_reward_points_redemption'); ?>"
                    >
                        <?php esc_html_e('Apply Points', 'wc-reward-points'); ?>
                    </button>
                    <button 
                        type="button" 
                        class="button" 
                        id="wc_reward_points_cancel"
                        style="display: none;"
                    >
                        <?php esc_html_e('Cancel', 'wc-reward-points'); ?>
                    </button>
                    <span class="wc-reward-points-message"></span>
                </p>
                <div class="wc-reward-points-error"></div>
                <div class="wc-reward-points-success"></div>
            </div>
            
            <div class="wc-reward-points-max-notice">
                <small>
                    <?php 
                    echo sprintf(
                        esc_html__('Maximum redemption: %s%% of order total', 'wc-reward-points'),
                        $max_percentage
                    ); 
                    ?>
                </small>
            </div>
            
        </div>
    <?php else : ?>
        <p>
            <?php esc_html_e('You don\'t have any reward points available.', 'wc-reward-points'); ?>
        </p>
    <?php endif; ?>
</div>

<style>
    .wc-reward-points-redemption-wrapper {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #f8f8f8;
    }
    .wc-reward-points-redemption-form {
        margin: 15px 0;
    }
    .wc-reward-points-redemption-form input {
        width: 100px;
        margin-right: 10px;
    }
    .wc-reward-points-error {
        color: #b81c23;
        margin-top: 10px;
        display: none;
    }
    .wc-reward-points-success {
        color: #0f834d;
        margin-top: 10px;
        display: none;
    }
    .wc-reward-points-max-notice {
        margin-top: 10px;
        color: #777;
    }
</style>

<script type="text/javascript">
    jQuery(function($) {
        // Apply points
        $('#wc_reward_points_apply').on('click', function() {
            var points = $('#wc_reward_points_redemption').val();
            var nonce = $(this).data('nonce');
            
            if (points <= 0 || points === '') {
                $('.wc-reward-points-error').text('<?php esc_html_e('Please enter a valid number of points.', 'wc-reward-points'); ?>').show();
                $('.wc-reward-points-success').hide();
                return;
            }
            
            $.ajax({
                type: 'POST',
                url: wc_checkout_params.ajax_url,
                data: {
                    action: 'apply_reward_points',
                    nonce: nonce,
                    points: points
                },
                success: function(response) {
                    if (response.success) {
                        $('.wc-reward-points-error').hide();
                        $('.wc-reward-points-success').html(response.data.message).show();
                        $('#wc_reward_points_apply').hide();
                        $('#wc_reward_points_cancel').show();
                        $('#wc_reward_points_redemption').prop('disabled', true);
                        
                        // Update checkout
                        $('body').trigger('update_checkout');
                    } else {
                        $('.wc-reward-points-error').text(response.data).show();
                        $('.wc-reward-points-success').hide();
                    }
                },
                error: function() {
                    $('.wc-reward-points-error').text('<?php esc_html_e('An error occurred. Please try again.', 'wc-reward-points'); ?>').show();
                    $('.wc-reward-points-success').hide();
                }
            });
        });
        
        // Cancel points redemption
        $('#wc_reward_points_cancel').on('click', function() {
            $.ajax({
                type: 'POST',
                url: wc_checkout_params.ajax_url,
                data: {
                    action: 'apply_reward_points',
                    nonce: $('#wc_reward_points_apply').data('nonce'),
                    points: 0
                },
                success: function() {
                    $('.wc-reward-points-success').hide();
                    $('.wc-reward-points-error').hide();
                    $('#wc_reward_points_redemption').val('').prop('disabled', false);
                    $('#wc_reward_points_cancel').hide();
                    $('#wc_reward_points_apply').show();
                    
                    // Update checkout
                    $('body').trigger('update_checkout');
                }
            });
        });
    });
</script> 