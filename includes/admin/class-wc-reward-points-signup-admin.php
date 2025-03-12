<?php
/**
 * Signup Rewards Admin Settings
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/admin
 */

namespace WC_Reward_Points\Admin;

use WC_Reward_Points\Core\WC_Reward_Points_Signup;
use WC_Reward_Points\Core\WC_Reward_Points_Debug;

/**
 * Signup Rewards Admin Settings Class
 */
class WC_Reward_Points_Signup_Admin {

    /**
     * Signup handler instance
     *
     * @var WC_Reward_Points_Signup
     */
    private $signup;

    /**
     * Debug logger instance
     *
     * @var WC_Reward_Points_Debug
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->signup = new WC_Reward_Points_Signup();
        $this->logger = new WC_Reward_Points_Debug();

        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_notices', array($this, 'show_settings_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_wc-reward-points-signup' !== $hook) {
            return;
        }

        // Clipboard.js for copying URL
        wp_enqueue_script(
            'clipboard',
            'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js',
            array(),
            '2.0.11',
            true
        );

        // QR Code generator
        wp_enqueue_script(
            'qrcode',
            'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
            array(),
            '1.0.0',
            true
        );

        // Chart.js for statistics
        wp_enqueue_script(
            'chart-js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js',
            array(),
            '3.9.1',
            true
        );

        // Custom admin styles
        wp_enqueue_style(
            'wc-reward-points-admin',
            plugins_url('assets/css/admin.css', WC_REWARD_POINTS_PLUGIN_FILE),
            array(),
            WC_REWARD_POINTS_VERSION
        );

        // Custom admin scripts
        wp_enqueue_script(
            'wc-reward-points-admin',
            plugins_url('assets/js/admin.js', WC_REWARD_POINTS_PLUGIN_FILE),
            array('jquery', 'clipboard', 'qrcode', 'chart-js'),
            WC_REWARD_POINTS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('wc-reward-points-admin', 'wcRewardPoints', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_reward_points_admin'),
            'i18n' => array(
                'copied' => __('URL copied to clipboard!', 'wc-reward-points'),
                'exportFileName' => __('signup-claims', 'wc-reward-points'),
                'noResults' => __('No results found.', 'wc-reward-points')
            )
        ));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wc_rewards_signup_settings',
            'wc_rewards_signup_settings',
            array($this, 'validate_settings')
        );
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Reward Points - Signup', 'wc-reward-points'),
            __('Reward Points - Signup', 'wc-reward-points'),
            'manage_woocommerce',
            'wc-reward-points-signup',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Validate settings
     *
     * @param array $input Settings input
     * @return array Validated settings
     */
    public function validate_settings($input) {
        $result = $this->signup->validate_settings($input);

        if (is_wp_error($result)) {
            foreach ($result->get_error_messages() as $message) {
                add_settings_error(
                    'wc_rewards_signup_settings',
                    'validation_error',
                    $message
                );
            }
            return get_option('wc_rewards_signup_settings', array());
        }

        $this->logger->log(
            'Signup reward settings updated',
            WC_Reward_Points_Debug::INFO,
            array('settings' => array_keys($input))
        );

        return $result;
    }

    /**
     * Show settings notices
     */
    public function show_settings_notices() {
        settings_errors('wc_rewards_signup_settings');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = $this->signup->get_settings();
        $current_values = get_option('wc_rewards_signup_settings', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Signup/Account Rewards Settings', 'wc-reward-points'); ?></h1>

            <!-- Statistics Cards -->
            <div class="reward-points-stats-grid">
                <?php $this->render_statistics(); ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('wc_rewards_signup_settings'); ?>
                
                <!-- Settings Card -->
                <div class="card settings-card">
                    <h2><?php echo esc_html__('Reward Settings', 'wc-reward-points'); ?></h2>
                    <table class="form-table">
                        <?php foreach ($settings as $key => $field): ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($field['title']); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php
                                    $value = isset($current_values[$key]) ? $current_values[$key] : $field['default'];
                                    switch ($field['type']):
                                        case 'number':
                                            ?>
                                            <div class="reward-points-input-group">
                                                <input
                                                    type="number"
                                                    id="<?php echo esc_attr($key); ?>"
                                                    name="wc_rewards_signup_settings[<?php echo esc_attr($key); ?>]"
                                                    value="<?php echo esc_attr($value); ?>"
                                                    class="regular-text"
                                                    <?php
                                                    if (isset($field['min'])) echo ' min="' . esc_attr($field['min']) . '"';
                                                    if (isset($field['max'])) echo ' max="' . esc_attr($field['max']) . '"';
                                                    echo !empty($field['required']) ? ' required' : '';
                                                    ?>
                                                >
                                                <?php if ($key === 'signup_points'): ?>
                                                    <span class="reward-points-addon">points</span>
                                                <?php elseif ($key === 'signup_cooldown'): ?>
                                                    <span class="reward-points-addon">days</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php
                                            break;
                                    endswitch;
                                    
                                    if (!empty($field['description'])):
                                        ?>
                                        <p class="description">
                                            <?php echo esc_html($field['description']); ?>
                                        </p>
                                        <?php
                                    endif;
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <?php submit_button(); ?>
                </div>
            </form>

            <!-- URL Card -->
            <div class="card url-card">
                <h2>
                    <?php echo esc_html__('Signup URL', 'wc-reward-points'); ?>
                    <button type="button" class="page-title-action copy-url" data-clipboard-target="#signup-url">
                        <?php echo esc_html__('Copy URL', 'wc-reward-points'); ?>
                    </button>
                </h2>
                <p>
                    <?php echo esc_html__('Share this URL to allow customers to claim signup/account rewards:', 'wc-reward-points'); ?>
                </p>
                <div class="reward-points-url-group">
                    <input
                        type="text"
                        readonly
                        id="signup-url"
                        class="large-text code"
                        value="<?php echo esc_url(site_url('rewards/signup')); ?>"
                        onclick="this.select();"
                    >
                    <div class="qr-code" id="signup-qr"></div>
                </div>
                <p class="description">
                    <?php echo esc_html__('Customers can claim rewards through this URL once every cooldown period.', 'wc-reward-points'); ?>
                </p>
            </div>

            <!-- Claims Card -->
            <div class="card claims-card">
                <div class="claims-header">
                    <h2><?php echo esc_html__('Recent Claims', 'wc-reward-points'); ?></h2>
                    <div class="claims-actions">
                        <input type="text" id="claims-search" placeholder="<?php echo esc_attr__('Search claims...', 'wc-reward-points'); ?>" class="regular-text">
                        <select id="claims-filter">
                            <option value=""><?php echo esc_html__('All Claims', 'wc-reward-points'); ?></option>
                            <option value="today"><?php echo esc_html__('Today', 'wc-reward-points'); ?></option>
                            <option value="week"><?php echo esc_html__('This Week', 'wc-reward-points'); ?></option>
                            <option value="month"><?php echo esc_html__('This Month', 'wc-reward-points'); ?></option>
                        </select>
                        <button type="button" class="button export-csv">
                            <?php echo esc_html__('Export CSV', 'wc-reward-points'); ?>
                        </button>
                    </div>
                </div>
                <?php $this->render_recent_claims(); ?>
            </div>
        </div>

        <style>
        .reward-points-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 10px;
            color: #23282d;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2271b1;
        }
        .stat-trend {
            font-size: 14px;
            color: #50575e;
        }
        .trend-up { color: #46b450; }
        .trend-down { color: #dc3232; }
        .reward-points-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .reward-points-addon {
            color: #50575e;
        }
        .reward-points-url-group {
            display: flex;
            gap: 20px;
            align-items: center;
            margin: 15px 0;
        }
        .qr-code {
            width: 100px;
            height: 100px;
            background: #f0f0f1;
        }
        .claims-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .claims-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .settings-card, .url-card, .claims-card {
            margin-top: 20px;
            padding: 20px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Initialize clipboard.js
            new ClipboardJS('.copy-url').on('success', function() {
                alert('<?php echo esc_js(__('URL copied to clipboard!', 'wc-reward-points')); ?>');
            });

            // Generate QR code
            new QRCode(document.getElementById('signup-qr'), {
                text: '<?php echo esc_url(site_url('rewards/signup')); ?>',
                width: 100,
                height: 100
            });

            // Claims search functionality
            $('#claims-search').on('input', function() {
                var searchText = $(this).val().toLowerCase();
                $('.claims-table tbody tr').each(function() {
                    var rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchText));
                });
            });

            // Claims filter functionality
            $('#claims-filter').on('change', function() {
                var filter = $(this).val();
                var today = new Date();
                $('.claims-table tbody tr').each(function() {
                    var claimDate = new Date($(this).find('td:nth-child(3)').text());
                    var show = true;

                    switch(filter) {
                        case 'today':
                            show = claimDate.toDateString() === today.toDateString();
                            break;
                        case 'week':
                            var weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                            show = claimDate >= weekAgo;
                            break;
                        case 'month':
                            show = claimDate.getMonth() === today.getMonth() &&
                                  claimDate.getFullYear() === today.getFullYear();
                            break;
                    }

                    $(this).toggle(show);
                });
            });

            // Export CSV functionality
            $('.export-csv').on('click', function() {
                var csv = ['User,Points,Claim Time,Next Eligible\n'];
                $('.claims-table tbody tr:visible').each(function() {
                    var row = [];
                    $(this).find('td').each(function() {
                        row.push('"' + $(this).text().trim().replace(/"/g, '""') + '"');
                    });
                    csv.push(row.join(',') + '\n');
                });

                var blob = new Blob([csv.join('')], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.setAttribute('download', 'signup-claims.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
        </script>
        <?php
    }

    /**
     * Render statistics
     */
    private function render_statistics() {
        global $wpdb;

        // Total claims today
        $today_claims = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_claims 
            WHERE reward_type = %s AND DATE(claim_time) = CURDATE()",
            'signup'
        ));

        // Total claims this month
        $month_claims = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_claims 
            WHERE reward_type = %s AND MONTH(claim_time) = MONTH(CURDATE()) 
            AND YEAR(claim_time) = YEAR(CURDATE())",
            'signup'
        ));

        // Total points awarded
        $total_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_awarded) FROM {$wpdb->prefix}wc_rewards_claims 
            WHERE reward_type = %s",
            'signup'
        ));

        // Month-over-month growth
        $last_month_claims = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_rewards_claims 
            WHERE reward_type = %s AND MONTH(claim_time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
            AND YEAR(claim_time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))",
            'signup'
        ));

        $growth = $last_month_claims > 0 ? 
            (($month_claims - $last_month_claims) / $last_month_claims * 100) : 0;

        $stats = array(
            array(
                'title' => __('Claims Today', 'wc-reward-points'),
                'value' => $today_claims,
                'trend' => null
            ),
            array(
                'title' => __('Claims This Month', 'wc-reward-points'),
                'value' => $month_claims,
                'trend' => $growth
            ),
            array(
                'title' => __('Total Points Awarded', 'wc-reward-points'),
                'value' => number_format($total_points),
                'trend' => null
            )
        );

        foreach ($stats as $stat):
            ?>
            <div class="stat-card">
                <h3><?php echo esc_html($stat['title']); ?></h3>
                <div class="stat-value"><?php echo esc_html($stat['value']); ?></div>
                <?php if ($stat['trend'] !== null): ?>
                    <div class="stat-trend <?php echo $stat['trend'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                        <?php 
                        echo $stat['trend'] >= 0 ? '↑' : '↓';
                        echo ' ' . abs(round($stat['trend'])) . '%';
                        echo ' ' . __('vs last month', 'wc-reward-points');
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        endforeach;
    }

    /**
     * Render recent claims table
     */
    private function render_recent_claims() {
        global $wpdb;

        $claims = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, u.user_email, u.display_name
                FROM {$wpdb->prefix}wc_rewards_claims c
                JOIN {$wpdb->users} u ON c.user_id = u.ID
                WHERE c.reward_type = %s
                ORDER BY c.claim_time DESC
                LIMIT 10",
                'signup'
            )
        );

        if (empty($claims)) {
            echo '<p>' . esc_html__('No claims yet.', 'wc-reward-points') . '</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('User', 'wc-reward-points'); ?></th>
                    <th><?php echo esc_html__('Points', 'wc-reward-points'); ?></th>
                    <th><?php echo esc_html__('Claim Time', 'wc-reward-points'); ?></th>
                    <th><?php echo esc_html__('Next Eligible', 'wc-reward-points'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($claims as $claim): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($claim->display_name); ?>
                            <br>
                            <small><?php echo esc_html($claim->user_email); ?></small>
                        </td>
                        <td><?php echo esc_html($claim->points_awarded); ?></td>
                        <td><?php echo esc_html($claim->claim_time); ?></td>
                        <td>
                            <?php
                            $next_claim = strtotime(
                                "+" . $this->signup->get_cooldown_period() . " days",
                                strtotime($claim->claim_time)
                            );
                            echo esc_html(date('Y-m-d H:i:s', $next_claim));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
} 