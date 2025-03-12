<?php
/**
 * Debug Logger for WC Reward Points
 *
 * @package    WC_Reward_Points
 * @subpackage WC_Reward_Points/includes/core
 */

namespace WC_Reward_Points\Core;

/**
 * Debug Logger class
 */
class WC_Reward_Points_Debug {

    /**
     * Log levels
     */
    const ERROR   = 'error';
    const WARNING = 'warning';
    const INFO    = 'info';
    const DEBUG   = 'debug';

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private static $debug_mode = false;

    /**
     * Sensitive data patterns that should be masked in logs
     *
     * @var array
     */
    private static $sensitive_patterns = array(
        // API keys and tokens
        '/(?:api_key|api[-_]?secret|secret[-_]?key|access[-_]?token|auth[-_]?token|password|pass)(?:\s*[:=]\s*|\s*[\'"]?\s*:\s*[\'"]?)([^\'"\s&]*)(?:[\'"]|\s|&|$)/i',
        // Trustpilot specific patterns
        '/(?:trustpilot[-_]?api[-_]?key|trustpilot[-_]?api[-_]?secret|trustpilot[-_]?secret[-_]?key)(?:\s*[:=]\s*|\s*[\'"]?\s*:\s*[\'"]?)([^\'"\s&]*)(?:[\'"]|\s|&|$)/i',
        // Bearer tokens
        '/Bearer\s+([A-Za-z0-9\-\._~\+\/]+=*)/i',
        // Basic auth
        '/Basic\s+([A-Za-z0-9+\/]+=*)/i',
        // Common sensitive params in URLs
        '/([?&](api[-_]?key|secret|token|password)=)([^&\s]+)/',
    );

    /**
     * Initialize debug mode
     */
    public static function init() {
        self::$debug_mode = (defined('WP_DEBUG') && WP_DEBUG) || 
                           (defined('WC_REWARD_POINTS_DEBUG') && WC_REWARD_POINTS_DEBUG);
    }

    /**
     * Sanitize context array to remove or mask sensitive data
     *
     * @param array $context Context data
     * @return array Sanitized context
     */
    private static function sanitize_context($context) {
        if (!is_array($context)) {
            return $context;
        }

        $sanitized = array();
        $sensitive_keys = array(
            'api_key', 'apikey', 'api-key', 'secret', 'api_secret', 'password', 
            'pass', 'token', 'access_token', 'auth_token', 'key', 'secret_key',
            'credential', 'pw', 'passwd'
        );

        foreach ($context as $key => $value) {
            // Handle nested arrays recursively
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_context($value);
                continue;
            }
            
            // Check if this is a sensitive key that should be masked
            $is_sensitive = false;
            foreach ($sensitive_keys as $sensitive_key) {
                if (stripos($key, $sensitive_key) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive && is_string($value)) {
                $sanitized[$key] = self::mask_value($value);
            } else {
                // For non-sensitive data, still sanitize the value if it's a string
                // as it might contain sensitive data in its content
                $sanitized[$key] = is_string($value) ? self::sanitize_sensitive_data($value) : $value;
            }
        }

        return $sanitized;
    }

    /**
     * Mask a sensitive value
     *
     * @param string $value Value to mask
     * @return string Masked value
     */
    private static function mask_value($value) {
        if (empty($value)) {
            return '';
        }
        
        $length = strlen($value);
        
        // For very short values, just use fixed asterisks
        if ($length <= 8) {
            return '******';
        }
        
        // For longer values, preserve first and last 2 chars
        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    /**
     * Sanitize sensitive data in a string
     *
     * @param string $data Data to sanitize
     * @return string Sanitized data
     */
    private static function sanitize_sensitive_data($data) {
        if (!is_string($data)) {
            return $data;
        }

        foreach (self::$sensitive_patterns as $pattern) {
            $data = preg_replace_callback($pattern, function($matches) {
                // Different patterns have the sensitive data in different capture groups
                $sensitiveValue = isset($matches[3]) ? $matches[3] : (isset($matches[1]) ? $matches[1] : '');
                $replacement = self::mask_value($sensitiveValue);
                
                if (isset($matches[3])) {
                    return $matches[1] . $replacement;
                } else if (isset($matches[1])) {
                    $fullMatch = $matches[0];
                    return str_replace($sensitiveValue, $replacement, $fullMatch);
                }
                
                return $matches[0];
            }, $data);
        }

        return $data;
    }

    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level   Log level
     * @param array  $context Additional context
     */
    public static function log($message, $level = self::INFO, $context = array()) {
        if (!self::$debug_mode && $level === self::DEBUG) {
            return;
        }

        // Sanitize the message and context to mask sensitive data
        $message = self::sanitize_sensitive_data($message);
        $context = self::sanitize_context($context);

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context
        );

        // Add request information in debug mode
        if (self::$debug_mode) {
            $log_entry['request'] = array(
                'url'      => self::sanitize_sensitive_data($_SERVER['REQUEST_URI'] ?? ''),
                'method'   => $_SERVER['REQUEST_METHOD'] ?? '',
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_id'  => get_current_user_id(),
            );
        }

        // Format log entry
        $formatted_entry = self::format_log_entry($log_entry);

        // Write to debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($formatted_entry);
        }

        // Store in database for admin viewing
        self::store_log_entry($log_entry);
    }

    /**
     * Format log entry for file output
     *
     * @param array $entry Log entry
     * @return string
     */
    private static function format_log_entry($entry) {
        $formatted = sprintf(
            "[%s] %s: %s",
            $entry['timestamp'],
            strtoupper($entry['level']),
            $entry['message']
        );

        if (!empty($entry['context'])) {
            $formatted .= "\nContext: " . json_encode($entry['context'], JSON_PRETTY_PRINT);
        }

        if (!empty($entry['request'])) {
            $formatted .= "\nRequest: " . json_encode($entry['request'], JSON_PRETTY_PRINT);
        }

        return $formatted;
    }

    /**
     * Store log entry in database
     *
     * @param array $entry Log entry
     */
    private static function store_log_entry($entry) {
        global $wpdb;

        // Create table if it doesn't exist
        self::maybe_create_log_table();

        $wpdb->insert(
            $wpdb->prefix . 'wc_rewards_debug_log',
            array(
                'timestamp' => $entry['timestamp'],
                'level'     => $entry['level'],
                'message'   => $entry['message'],
                'context'   => json_encode($entry['context']),
                'request'   => isset($entry['request']) ? json_encode($entry['request']) : null
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Create debug log table if it doesn't exist
     */
    private static function maybe_create_log_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_rewards_debug_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                level varchar(20) NOT NULL,
                message text NOT NULL,
                context text,
                request text,
                PRIMARY KEY  (id),
                KEY level (level),
                KEY timestamp (timestamp)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Get log entries
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'limit'  => 100,
            'offset' => 0,
            'level'  => null,
            'search' => null,
        );

        $args = wp_parse_args($args, $defaults);

        $query = "SELECT * FROM {$wpdb->prefix}wc_rewards_debug_log WHERE 1=1";
        $params = array();

        if ($args['level']) {
            $query .= " AND level = %s";
            $params[] = $args['level'];
        }

        if ($args['search']) {
            $query .= " AND (message LIKE %s OR context LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $query .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Clear log entries
     *
     * @param string $level Optional level to clear
     */
    public static function clear_logs($level = null) {
        global $wpdb;

        $query = "DELETE FROM {$wpdb->prefix}wc_rewards_debug_log";
        
        if ($level) {
            $query .= $wpdb->prepare(" WHERE level = %s", $level);
        }

        $wpdb->query($query);
    }
} 