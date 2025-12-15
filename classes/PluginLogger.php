<?php
if (!defined('ABSPATH')) {
    exit;
}

class PluginLogger
{
    private static $log_dir;
    private static $log_file;
    private static $current_date;

    public static function init()
    {
        // Ensure a dedicated logs directory exists (inside the plugin)
        self::$log_dir = plugin_dir_path(__FILE__) . '../logs/';
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }

        self::$current_date = date('Y-m-d');
        // Generate a dated log file under logs/, e.g. logs/plugin-2025-12-15.log
        self::$log_file = self::$log_dir . "plugin-" . self::$current_date . ".log";
    }

    public static function log($message, $context = null)
    {
        // Rotate to a new file when the date changes
        if (!self::$log_file || self::$current_date !== date('Y-m-d')) {
            self::init();
        }

        if ($context !== null) {
            // Append serialized context for richer debugging
            if (is_array($context) || is_object($context)) {
                $encoded = json_encode($context);
                $message .= ' ' . ($encoded !== false ? $encoded : var_export($context, true));
            } else {
                $message .= ' ' . $context;
            }
        }

        $time = date('Y-m-d H:i:s');
        $formatted = "[{$time}] {$message}\n";

        file_put_contents(self::$log_file, $formatted, FILE_APPEND | LOCK_EX);
    }
    public static function clear()
    {
        if (!self::$log_file) self::init();
        file_put_contents(self::$log_file, "");
    }
}
