<?php
/**
 * Template for the referral page.
 *
 * @package WC_Reward_Points
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

$referral_code = WC()->session->get('wc_reward_points_referral_code');
$points = get_option('wc_reward_points_referral_points_referee', 1000);
?>

<div class="wc-reward-points-referral-container">
    <div class="wc-reward-points-referral-card">
        <h1><?php _e('Welcome to Our Store!', 'wc-reward-points'); ?></h1>
        
        <div class="wc-reward-points-referral-content">
            <div class="referral-info">
                <h2><?php _e('You\'ve Been Referred!', 'wc-reward-points'); ?></h2>
                <p class="reward-description">
                    <?php printf(
                        __('Create an account now and receive %d reward points instantly!', 'wc-reward-points'),
                        $points
                    ); ?>
                </p>
                
                <div class="reward-amount">
                    <span class="points"><?php echo esc_html($points); ?></span>
                    <span class="label"><?php _e('Welcome Points', 'wc-reward-points'); ?></span>
                </div>
            </div>

            <?php if (!is_user_logged_in()): ?>
                <div class="registration-form">
                    <h3><?php _e('Create Your Account', 'wc-reward-points'); ?></h3>
                    
                    <form method="post" class="woocommerce-form woocommerce-form-register register">
                        <?php do_action('woocommerce_register_form_start'); ?>

                        <p class="woocommerce-form-row">
                            <label for="reg_email"><?php _e('Email address', 'woocommerce'); ?> <span class="required">*</span></label>
                            <input type="email" class="woocommerce-Input" name="email" id="reg_email" autocomplete="email" required />
                        </p>

                        <p class="woocommerce-form-row">
                            <label for="reg_password"><?php _e('Password', 'woocommerce'); ?> <span class="required">*</span></label>
                            <input type="password" class="woocommerce-Input" name="password" id="reg_password" autocomplete="new-password" required />
                        </p>

                        <?php do_action('woocommerce_register_form'); ?>

                        <input type="hidden" name="referral_code" value="<?php echo esc_attr($referral_code); ?>" />

                        <p class="woocommerce-form-row">
                            <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
                            <button type="submit" class="woocommerce-Button wc-reward-points-button" name="register">
                                <?php _e('Register & Claim Points', 'wc-reward-points'); ?>
                            </button>
                        </p>

                        <?php do_action('woocommerce_register_form_end'); ?>
                    </form>

                    <p class="login-link">
                        <?php _e('Already have an account?', 'wc-reward-points'); ?>
                        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
                            <?php _e('Log in', 'wc-reward-points'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="referral-terms">
                <p>
                    <?php _e('By creating an account, you agree to our Terms of Service and Privacy Policy.', 'wc-reward-points'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php
get_footer('shop');
?> 