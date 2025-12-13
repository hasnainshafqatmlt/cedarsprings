<?php
if (!defined('ABSPATH')) {
    exit;
}

class PluginLogger
{
    private static $log_file;

    public static function init()
    {
        self::$log_file = plugin_dir_path(__FILE__) . '../plugin.log';
    }

    public static function log($message)
    {
        if (!self::$log_file) {
            self::init();
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
