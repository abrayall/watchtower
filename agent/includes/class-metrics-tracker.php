<?php
/**
 * Metrics Tracker Class
 * Tracks real-time site metrics like requests per minute
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_Metrics_Tracker {

    /**
     * Cache key for storing request data
     */
    private $cache_key = 'watchtower_request_metrics';

    /**
     * Time window in seconds (60 = 1 minute)
     */
    private $window_seconds = 60;

    /**
     * Initialize metrics tracking
     */
    public function __construct() {
        add_action('init', array($this, 'track_request'), 1);
    }

    /**
     * Track a request
     */
    public function track_request() {
        if (!function_exists('apcu_inc')) {
            return;
        }

        $current_second = time();
        $key = $this->cache_key . '_' . $current_second;
        apcu_add($key, 0, $this->window_seconds + 10);
        apcu_inc($key);
    }

    /**
     * Get requests per minute
     */
    public function get_requests_per_minute() {
        if (!function_exists('apcu_fetch')) {
            return null;
        }

        $current_time = time();
        $cutoff_time = $current_time - $this->window_seconds;
        $total_requests = 0;

        for ($t = $cutoff_time; $t <= $current_time; $t++) {
            $key = $this->cache_key . '_' . $t;
            $count = apcu_fetch($key, $success);
            if ($success) {
                $total_requests += $count;
            }
        }

        return $total_requests;
    }

    /**
     * Check if metrics tracking is available
     */
    public function is_available() {
        return function_exists('apcu_fetch');
    }
}
