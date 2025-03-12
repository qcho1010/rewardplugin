<?php
/**
 * Shortcodes Handler
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/public
 * @since      1.0.0
 */

namespace WC_Reward_Points\Public;

/**
 * Shortcodes Handler Class
 *
 * Handles all shortcodes for the plugin
 */
class WC_Reward_Points_Shortcodes {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Initialize shortcodes
        $this->init_shortcodes();
    }

    /**
     * Initialize shortcodes
     */
    private function init_shortcodes() {
        add_shortcode('wc_reward_points_balance', array($this, 'render_points_balance'));
        add_shortcode('wc_reward_points_history', array($this, 'render_points_history'));
        add_shortcode('wc_reward_points_referral', array($this, 'render_referral_program'));
        add_shortcode('wc_reward_points_ambassador_apply', array($this, 'render_ambassador_application'));
        add_shortcode('wc_reward_points_ambassador_dashboard', array($this, 'render_ambassador_dashboard'));
        add_shortcode('wc_reward_points_trustpilot_review', array($this, 'render_trustpilot_review'));
    }

    /**
     * Render points balance
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_points_balance($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return sprintf(
                '<p>%s</p>',
                esc_html__('Please log in to view your points balance.', 'wc-reward-points')
            );
        }

        $user_id = get_current_user_id();
        $points_manager = \WC_Reward_Points\Core\WC_Reward_Points_Manager::instance();
        $points = $points_manager->get_points($user_id);

        // Format points
        $formatted_points = number_format($points);

        // Get conversion rate
        $conversion_rate = get_option('wc_reward_points_conversion_rate', 100);
        $value = wc_price($points / $conversion_rate);

        ob_start();
        ?>
        <div class="wc-reward-points-balance">
            <p class="wc-reward-points-balance__total">
                <?php
                printf(
                    esc_html__('Your current balance: %s points', 'wc-reward-points'),
                    '<strong>' . esc_html($formatted_points) . '</strong>'
                );
                ?>
            </p>
            <p class="wc-reward-points-balance__value">
                <?php
                printf(
                    esc_html__('Value: %s', 'wc-reward-points'),
                    $value
                );
                ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render points history
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_points_history($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return sprintf(
                '<p>%s</p>',
                esc_html__('Please log in to view your points history.', 'wc-reward-points')
            );
        }

        $user_id = get_current_user_id();
        $points_manager = \WC_Reward_Points\Core\WC_Reward_Points_Manager::instance();
        $history = $points_manager->get_user_points_history($user_id);

        ob_start();
        ?>
        <div class="wc-reward-points-history">
            <h3><?php esc_html_e('Points History', 'wc-reward-points'); ?></h3>
            <?php if (empty($history)) : ?>
                <p><?php esc_html_e('No points transactions found.', 'wc-reward-points'); ?></p>
            <?php else : ?>
                <table class="wc-reward-points-history__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'wc-reward-points'); ?></th>
                            <th><?php esc_html_e('Description', 'wc-reward-points'); ?></th>
                            <th><?php esc_html_e('Points', 'wc-reward-points'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($entry->date_created))); ?>
                                </td>
                                <td><?php echo esc_html($entry->description); ?></td>
                                <td>
                                    <?php
                                    echo esc_html(($entry->points > 0 ? '+' : '') . number_format($entry->points));
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render referral program
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_referral_program($atts) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return sprintf(
                '<p>%s</p>',
                esc_html__('Please log in to access the referral program.', 'wc-reward-points')
            );
        }

        $user_id = get_current_user_id();
        $referral = new \WC_Reward_Points\Public\WC_Reward_Points_Referral();
        
        ob_start();
        $referral->render_referral_program();
        return ob_get_clean();
    }

    /**
     * Render ambassador application
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_ambassador_application($atts) {
        ob_start();
        include_once WC_REWARD_POINTS_PLUGIN_PATH . 'templates/ambassador-application.php';
        return ob_get_clean();
    }

    /**
     * Render ambassador dashboard
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_ambassador_dashboard($atts) {
        ob_start();
        include_once WC_REWARD_POINTS_PLUGIN_PATH . 'templates/ambassador-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Render Trustpilot review
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_trustpilot_review($atts) {
        ob_start();
        include_once WC_REWARD_POINTS_PLUGIN_PATH . 'templates/trustpilot-review.php';
        return ob_get_clean();
    }
} 