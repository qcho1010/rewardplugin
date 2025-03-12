<?php
/**
 * Frontend Brand Ambassador Application Template
 *
 * @package    WC_Reward_Points
 * @version    1.0.0
 */

defined('ABSPATH') || exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    ?>
    <div class="wc-reward-points-ambassador-login">
        <p><?php esc_html_e('Please log in to apply for our Brand Ambassador Program.', 'wc-reward-points'); ?></p>
        <?php wp_login_form(array('redirect' => get_permalink())); ?>
    </div>
    <?php
    return;
}

$user_id = get_current_user_id();

// Check if user is already an ambassador
if (get_user_meta($user_id, '_wc_ambassador_code', true)) {
    ?>
    <div class="wc-reward-points-ambassador-status">
        <h2><?php esc_html_e('You are already a Brand Ambassador!', 'wc-reward-points'); ?></h2>
        <p><?php esc_html_e('Thank you for being part of our Brand Ambassador Program. You can manage your referrals and earnings in your account dashboard.', 'wc-reward-points'); ?></p>
        <p>
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('ambassador')); ?>" class="button">
                <?php esc_html_e('View Ambassador Dashboard', 'wc-reward-points'); ?>
            </a>
        </p>
    </div>
    <?php
    return;
}

// Check if user has a pending application
if (get_user_meta($user_id, '_wc_ambassador_application_date', true)) {
    ?>
    <div class="wc-reward-points-ambassador-pending">
        <h2><?php esc_html_e('Application Under Review', 'wc-reward-points'); ?></h2>
        <p><?php esc_html_e('Your application for our Brand Ambassador Program is currently under review. We will notify you once a decision has been made.', 'wc-reward-points'); ?></p>
        <p><?php esc_html_e('Application Date:', 'wc-reward-points'); ?> 
            <strong>
                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime(get_user_meta($user_id, '_wc_ambassador_application_date', true)))); ?>
            </strong>
        </p>
    </div>
    <?php
    return;
}

// Display success message if application was submitted
if (isset($_GET['application_submitted'])) {
    ?>
    <div class="wc-reward-points-message success">
        <p><?php esc_html_e('Thank you for applying to our Brand Ambassador Program! We will review your application and get back to you soon.', 'wc-reward-points'); ?></p>
    </div>
    <?php
    return;
}

// Get minimum requirements
$min_orders = get_option('wc_reward_points_ambassador_min_orders', 3);
$min_spent = get_option('wc_reward_points_ambassador_min_spent', 100);

// Check if user meets minimum requirements
$customer_orders = wc_get_orders(array(
    'customer_id' => $user_id,
    'status' => array('wc-completed'),
    'limit' => -1,
));

$total_orders = count($customer_orders);
$total_spent = 0;

foreach ($customer_orders as $order) {
    $total_spent += $order->get_total();
}

$meets_requirements = $total_orders >= $min_orders && $total_spent >= $min_spent;

if (!$meets_requirements) {
    ?>
    <div class="wc-reward-points-ambassador-requirements">
        <h2><?php esc_html_e('Brand Ambassador Program Requirements', 'wc-reward-points'); ?></h2>
        <p><?php esc_html_e('To become a brand ambassador, you need to meet the following requirements:', 'wc-reward-points'); ?></p>
        
        <div class="requirements-list">
            <div class="requirement <?php echo $total_orders >= $min_orders ? 'met' : 'not-met'; ?>">
                <span class="dashicons <?php echo $total_orders >= $min_orders ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                <div class="requirement-details">
                    <h4><?php esc_html_e('Minimum Orders', 'wc-reward-points'); ?></h4>
                    <p>
                        <?php 
                        printf(
                            esc_html__('You need at least %1$d completed orders. You currently have %2$d orders.', 'wc-reward-points'),
                            $min_orders,
                            $total_orders
                        ); 
                        ?>
                    </p>
                </div>
            </div>

            <div class="requirement <?php echo $total_spent >= $min_spent ? 'met' : 'not-met'; ?>">
                <span class="dashicons <?php echo $total_spent >= $min_spent ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                <div class="requirement-details">
                    <h4><?php esc_html_e('Minimum Spent', 'wc-reward-points'); ?></h4>
                    <p>
                        <?php 
                        printf(
                            esc_html__('You need to spend at least %1$s. You have spent %2$s so far.', 'wc-reward-points'),
                            wc_price($min_spent),
                            wc_price($total_spent)
                        ); 
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <p class="requirements-note">
            <?php esc_html_e('Please continue shopping to meet these requirements and become eligible for our Brand Ambassador Program.', 'wc-reward-points'); ?>
        </p>

        <p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button">
                <?php esc_html_e('Continue Shopping', 'wc-reward-points'); ?>
            </a>
        </p>
    </div>
    <?php
    return;
}
?>

<div class="wc-reward-points-ambassador-application">
    <h2><?php esc_html_e('Brand Ambassador Program Application', 'wc-reward-points'); ?></h2>
    
    <div class="program-benefits">
        <h3><?php esc_html_e('Program Benefits', 'wc-reward-points'); ?></h3>
        <ul>
            <li>
                <span class="dashicons dashicons-money-alt"></span>
                <?php esc_html_e('Earn 6% back in points for every referred purchase', 'wc-reward-points'); ?>
            </li>
            <li>
                <span class="dashicons dashicons-share"></span>
                <?php esc_html_e('Get a unique referral link to share with your network', 'wc-reward-points'); ?>
            </li>
            <li>
                <span class="dashicons dashicons-chart-line"></span>
                <?php esc_html_e('Track your referrals and earnings in real-time', 'wc-reward-points'); ?>
            </li>
            <li>
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e('Join our exclusive ambassador community', 'wc-reward-points'); ?>
            </li>
        </ul>
    </div>

    <form method="post" class="ambassador-application-form">
        <?php wp_nonce_field('wc_ambassador_application'); ?>
        <input type="hidden" name="action" value="wc_ambassador_apply">

        <p class="form-row">
            <label for="ambassador_why"><?php esc_html_e('Why do you want to become a brand ambassador?', 'wc-reward-points'); ?> <span class="required">*</span></label>
            <textarea id="ambassador_why" name="ambassador_why" rows="4" required></textarea>
        </p>

        <p class="form-row">
            <label for="ambassador_reach"><?php esc_html_e('How do you plan to promote our products?', 'wc-reward-points'); ?> <span class="required">*</span></label>
            <textarea id="ambassador_reach" name="ambassador_reach" rows="4" required></textarea>
        </p>

        <p class="form-row">
            <label for="ambassador_social"><?php esc_html_e('Social Media Profiles (Optional)', 'wc-reward-points'); ?></label>
            <input type="text" id="ambassador_social" name="ambassador_social" placeholder="<?php esc_attr_e('Instagram, Facebook, Twitter, etc.', 'wc-reward-points'); ?>">
        </p>

        <p class="form-row terms">
            <label class="checkbox">
                <input type="checkbox" name="ambassador_terms" required>
                <?php 
                printf(
                    esc_html__('I agree to the %1$sAmbassador Program Terms & Conditions%2$s', 'wc-reward-points'),
                    '<a href="#" target="_blank">',
                    '</a>'
                ); 
                ?>
                <span class="required">*</span>
            </label>
        </p>

        <p class="form-row submit">
            <button type="submit" class="button alt">
                <?php esc_html_e('Submit Application', 'wc-reward-points'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.wc-reward-points-ambassador-application {
    max-width: 800px;
    margin: 0 auto;
}

.program-benefits {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 30px;
}

.program-benefits ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.program-benefits li {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.program-benefits .dashicons {
    margin-right: 10px;
    color: #2271b1;
}

.ambassador-application-form .form-row {
    margin-bottom: 20px;
}

.ambassador-application-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.ambassador-application-form textarea,
.ambassador-application-form input[type="text"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ambassador-application-form .terms {
    margin-top: 30px;
}

.ambassador-application-form .terms label {
    font-weight: normal;
}

.ambassador-application-form .submit {
    margin-top: 30px;
}

.requirements-list {
    margin: 30px 0;
}

.requirement {
    display: flex;
    align-items: flex-start;
    margin-bottom: 20px;
    padding: 15px;
    border-radius: 4px;
    background: #f8f9fa;
}

.requirement .dashicons {
    margin-right: 15px;
    margin-top: 3px;
}

.requirement.met .dashicons {
    color: #00a32a;
}

.requirement.not-met .dashicons {
    color: #d63638;
}

.requirement-details h4 {
    margin: 0 0 5px;
}

.requirement-details p {
    margin: 0;
    color: #646970;
}

.requirements-note {
    font-style: italic;
    color: #646970;
}

/* Responsive styles */
@media screen and (max-width: 600px) {
    .requirement {
        flex-direction: column;
    }
    
    .requirement .dashicons {
        margin-bottom: 10px;
    }
}
</style> 