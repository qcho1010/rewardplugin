<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes
 */

namespace WC_Reward_Points\Core;

/**
 * The core plugin class.
 *
 * @since      1.0.0
 */
class WC_Reward_Points {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WC_Reward_Points_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('WC_REWARD_POINTS_VERSION')) {
            $this->version = WC_REWARD_POINTS_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wc-reward-points';

        $this->define_constants();
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Define constants
     */
    private function define_constants() {
        define('WC_REWARD_POINTS_PLUGIN_PATH', plugin_dir_path(dirname(dirname(__FILE__))));
        define('WC_REWARD_POINTS_PLUGIN_URL', plugin_dir_url(dirname(dirname(__FILE__))));
        define('WC_REWARD_POINTS_PLUGIN_BASENAME', plugin_basename(dirname(dirname(dirname(__FILE__)))));
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - WC_Reward_Points_Loader. Orchestrates the hooks of the plugin.
     * - WC_Reward_Points_i18n. Defines internationalization functionality.
     * - WC_Reward_Points_Admin. Defines all hooks for the admin area.
     * - WC_Reward_Points_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/core/class-wc-reward-points-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/core/class-wc-reward-points-i18n.php';

        /**
         * The class responsible for managing points.
         */
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/core/class-wc-reward-points-manager.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/admin/class-wc-reward-points-admin.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/admin/class-wc-reward-points-settings.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/admin/class-wc-reward-points-referral-admin.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/admin/class-wc-reward-points-trustpilot-admin.php';

        // Add rate limiter class for security
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/core/class-wc-reward-points-rate-limiter.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/public/class-wc-reward-points-public.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/public/class-wc-reward-points-account.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/public/class-wc-reward-points-checkout.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/public/class-wc-reward-points-referral.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/public/class-wc-reward-points-trustpilot.php';
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/public/class-wc-reward-points-shortcodes.php';

        /**
         * Integration classes
         */
        require_once WC_REWARD_POINTS_PLUGIN_PATH . 'includes/integrations/class-wc-reward-points-trustpilot-api.php';

        $this->loader = new WC_Reward_Points_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the WC_Reward_Points_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new WC_Reward_Points_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new \WC_Reward_Points\Admin\WC_Reward_Points_Admin($this->get_plugin_name(), $this->get_version());
        $settings = new \WC_Reward_Points\Admin\WC_Reward_Points_Settings();
        $referral_admin = new \WC_Reward_Points\Admin\WC_Reward_Points_Referral_Admin();
        $trustpilot_admin = new \WC_Reward_Points\Admin\WC_Reward_Points_Trustpilot_Admin();

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new \WC_Reward_Points\Public\WC_Reward_Points_Public($this->get_plugin_name(), $this->get_version());
        $account = new \WC_Reward_Points\Public\WC_Reward_Points_Account();
        $checkout = new \WC_Reward_Points\Public\WC_Reward_Points_Checkout();
        $referral = new \WC_Reward_Points\Public\WC_Reward_Points_Referral();
        $trustpilot = new \WC_Reward_Points\Public\WC_Reward_Points_Trustpilot();
        $shortcodes = new \WC_Reward_Points\Public\WC_Reward_Points_Shortcodes();

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    WC_Reward_Points_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Gets a file path relative to the plugin directory.
     *
     * @param string $path The path to be appended.
     * @return string
     */
    public static function get_plugin_path($path = '') {
        return WC_REWARD_POINTS_PLUGIN_PATH . ltrim($path, '/');
    }

    /**
     * Gets a URL relative to the plugin directory.
     *
     * @param string $path The path to be appended.
     * @return string
     */
    public static function get_plugin_url($path = '') {
        return WC_REWARD_POINTS_PLUGIN_URL . ltrim($path, '/');
    }

    /**
     * Locate a template and return the path for inclusion.
     *
     * This is the load order:
     *
     * yourtheme/wc-reward-points/$template_path
     * yourtheme/$template_path
     * $default_path/$template_path
     *
     * @param string $template_name Template name.
     * @param string $template_path Template path. (default: '').
     * @param string $default_path  Default path. (default: '').
     * @return string
     */
    public static function locate_template($template_name, $template_path = '', $default_path = '') {
        if (!$template_path) {
            $template_path = 'wc-reward-points/';
        }

        if (!$default_path) {
            $default_path = WC_REWARD_POINTS_PLUGIN_PATH . 'templates/';
        }

        // Look within passed path within the theme - this is priority.
        $template = locate_template(
            array(
                trailingslashit($template_path) . $template_name,
                $template_name,
            )
        );

        // Get default template/.
        if (!$template) {
            $template = $default_path . $template_name;
        }

        // Return what we found.
        return apply_filters('wc_reward_points_locate_template', $template, $template_name, $template_path);
    }

    /**
     * Get template part (for templates like the shop-loop).
     *
     * @param mixed  $slug Template slug.
     * @param string $name Template name (default: '').
     */
    public static function get_template_part($slug, $name = '') {
        $template = '';

        // Look in yourtheme/slug-name.php and yourtheme/wc-reward-points/slug-name.php.
        if ($name) {
            $template = self::locate_template("{$slug}-{$name}.php", '', WC_REWARD_POINTS_PLUGIN_PATH . 'templates/');
        }

        // If template file doesn't exist, look in yourtheme/slug.php and yourtheme/wc-reward-points/slug.php.
        if (!$template) {
            $template = self::locate_template("{$slug}.php", '', WC_REWARD_POINTS_PLUGIN_PATH . 'templates/');
        }

        // Allow 3rd party plugins to filter template file from their plugin.
        $template = apply_filters('wc_reward_points_get_template_part', $template, $slug, $name);

        if ($template) {
            load_template($template, false);
        }
    }

    /**
     * Get other templates passing attributes and including the file.
     *
     * @param string $template_name Template name.
     * @param array  $args          Arguments. (default: array).
     * @param string $template_path Template path. (default: '').
     * @param string $default_path  Default path. (default: '').
     */
    public static function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if (!empty($args) && is_array($args)) {
            extract($args); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        $located = self::locate_template($template_name, $template_path, $default_path);

        if (!file_exists($located)) {
            return;
        }

        // Allow 3rd party plugin filter template file from their plugin.
        $located = apply_filters('wc_reward_points_get_template', $located, $template_name, $args, $template_path, $default_path);

        do_action('wc_reward_points_before_template_part', $template_name, $template_path, $located, $args);

        include $located;

        do_action('wc_reward_points_after_template_part', $template_name, $template_path, $located, $args);
    }
} 