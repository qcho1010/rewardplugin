<?php
/**
 * The social sharing functionality of the plugin.
 *
 * @link       https://github.com/qhco1010/rewardplugin
 * @since      1.0.0
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

/**
 * The social sharing class.
 *
 * Handles all social sharing functionality including share message generation,
 * social platform integration, and URL generation.
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 * @author     Kyu Cho
 */
class WC_Reward_Points_Referral_Social_Share {

    /**
     * The referral manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WC_Reward_Points_Referral_Manager    $referral_manager    The referral manager instance.
     */
    private $referral_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->referral_manager = new WC_Reward_Points_Referral_Manager();

        // Register AJAX handlers
        add_action('wp_ajax_get_share_data', array($this, 'ajax_get_share_data'));
        add_action('wp_ajax_nopriv_get_share_data', array($this, 'ajax_get_share_data'));
    }

    /**
     * Get share data for a user.
     *
     * @since    1.0.0
     * @param    int       $user_id    The user ID.
     * @return   array                 Array containing share data.
     */
    public function get_share_data($user_id) {
        try {
            // Get or generate referral code
            $code_result = $this->referral_manager->generate_referral_code($user_id);
            if (!$code_result['success']) {
                throw new \Exception($code_result['error']);
            }

            // Get share settings
            $share_message = get_option('wc_reward_points_share_message', 'Hey, join [Store Name] using my referral code [CODE] and get [POINTS] points!');
            $enabled_platforms = get_option('wc_reward_points_enabled_social_platforms', array('facebook', 'twitter', 'whatsapp', 'email'));
            $referee_points = get_option('wc_reward_points_referral_points_referee', 1000);
            $store_name = get_bloginfo('name');

            // Replace placeholders in share message
            $share_message = str_replace(
                array('[Store Name]', '[CODE]', '[POINTS]'),
                array($store_name, $code_result['code'], $referee_points),
                $share_message
            );

            // Generate share URLs
            $share_url = add_query_arg('ref', $code_result['code'], home_url());
            $encoded_message = urlencode($share_message);
            $encoded_url = urlencode($share_url);

            $share_urls = array();
            if (in_array('facebook', $enabled_platforms)) {
                $share_urls['facebook'] = "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}&quote={$encoded_message}";
            }
            if (in_array('twitter', $enabled_platforms)) {
                $share_urls['twitter'] = "https://twitter.com/intent/tweet?text={$encoded_message}&url={$encoded_url}";
            }
            if (in_array('whatsapp', $enabled_platforms)) {
                $share_urls['whatsapp'] = "https://api.whatsapp.com/send?text={$encoded_message}%20{$encoded_url}";
            }
            if (in_array('email', $enabled_platforms)) {
                $share_urls['email'] = "mailto:?subject=" . urlencode($store_name . " - Special Offer") . "&body={$encoded_message}%20{$encoded_url}";
            }

            return array(
                'success' => true,
                'data' => array(
                    'code' => $code_result['code'],
                    'expires_at' => $code_result['expires_at'],
                    'share_message' => $share_message,
                    'share_url' => $share_url,
                    'share_urls' => $share_urls,
                    'enabled_platforms' => $enabled_platforms
                )
            );

        } catch (\Exception $e) {
            WC_Reward_Points_Debug::log(
                'Failed to get share data',
                'ERROR',
                array(
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                )
            );

            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * AJAX handler for getting share data.
     *
     * @since    1.0.0
     */
    public function ajax_get_share_data() {
        // Verify nonce
        if (!check_ajax_referer('wc_reward_points_share', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        // Get share data
        $result = $this->get_share_data($user_id);
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Register frontend scripts and styles.
     *
     * @since    1.0.0
     */
    public function register_frontend_assets() {
        // Register styles
        wp_register_style(
            'wc-reward-points-social-share',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/social-share.css',
            array(),
            '1.0.0'
        );

        // Register scripts
        wp_register_script(
            'wc-reward-points-social-share',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/social-share.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script
        wp_localize_script('wc-reward-points-social-share', 'wcRewardPointsShare', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_reward_points_share'),
            'i18n' => array(
                'copySuccess' => __('Referral link copied!', 'wc-reward-points'),
                'copyError' => __('Failed to copy referral link', 'wc-reward-points'),
                'shareError' => __('Failed to open share dialog', 'wc-reward-points')
            )
        ));
    }

    /**
     * Enqueue frontend scripts and styles.
     *
     * @since    1.0.0
     */
    public function enqueue_frontend_assets() {
        if (!wp_script_is('wc-reward-points-social-share', 'registered')) {
            $this->register_frontend_assets();
        }

        wp_enqueue_style('wc-reward-points-social-share');
        wp_enqueue_script('wc-reward-points-social-share');
    }

    /**
     * Get the share popup HTML.
     *
     * @since    1.0.0
     * @param    array    $share_data    The share data.
     * @return   string                  The popup HTML.
     */
    public function get_share_popup_html($share_data) {
        ob_start();
        ?>
        <div class="wc-reward-points-share-popup">
            <div class="wc-reward-points-share-popup-content">
                <span class="wc-reward-points-share-popup-close">&times;</span>
                
                <h2><?php _e('Share & Earn Rewards', 'wc-reward-points'); ?></h2>
                
                <div class="wc-reward-points-share-message">
                    <?php echo esc_html($share_data['share_message']); ?>
                </div>

                <div class="wc-reward-points-share-code">
                    <label><?php _e('Your Referral Code:', 'wc-reward-points'); ?></label>
                    <div class="wc-reward-points-share-code-container">
                        <input type="text" readonly value="<?php echo esc_attr($share_data['code']); ?>">
                        <button class="wc-reward-points-copy-code">
                            <?php _e('Copy', 'wc-reward-points'); ?>
                        </button>
                    </div>
                </div>

                <div class="wc-reward-points-share-url">
                    <label><?php _e('Share Link:', 'wc-reward-points'); ?></label>
                    <div class="wc-reward-points-share-url-container">
                        <input type="text" readonly value="<?php echo esc_url($share_data['share_url']); ?>">
                        <button class="wc-reward-points-copy-url">
                            <?php _e('Copy', 'wc-reward-points'); ?>
                        </button>
                    </div>
                </div>

                <div class="wc-reward-points-share-buttons">
                    <?php foreach ($share_data['enabled_platforms'] as $platform): ?>
                        <a href="<?php echo esc_url($share_data['share_urls'][$platform]); ?>" 
                           class="wc-reward-points-share-button <?php echo esc_attr($platform); ?>"
                           target="_blank"
                           rel="noopener noreferrer">
                            <span class="wc-reward-points-share-icon <?php echo esc_attr($platform); ?>"></span>
                            <?php echo esc_html(ucfirst($platform)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="wc-reward-points-share-expiry">
                    <?php
                    $expiry_date = new \DateTime($share_data['expires_at']);
                    $expiry_date = $expiry_date->format('F j, Y');
                    printf(
                        __('This referral code expires on %s', 'wc-reward-points'),
                        esc_html($expiry_date)
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
} 