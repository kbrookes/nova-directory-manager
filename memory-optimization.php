<?php
/**
 * Memory Optimization Configuration for Nova Directory Manager
 * 
 * Add this to your wp-config.php file to optimize memory usage:
 * require_once(ABSPATH . 'memory-optimization.php');
 */

// Increase memory limit for the plugin
if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
    define( 'WP_MEMORY_LIMIT', '256M' );
}

// Increase max execution time for bulk operations
if ( ! defined( 'MAX_EXECUTION_TIME' ) ) {
    @ini_set( 'max_execution_time', 300 ); // 5 minutes
}

// Optimize PHP settings for better memory management
@ini_set( 'memory_limit', '256M' );
@ini_set( 'max_input_vars', 3000 );
@ini_set( 'post_max_size', '64M' );
@ini_set( 'upload_max_filesize', '64M' );

// Disable debug logging in production to reduce memory usage
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
    @ini_set( 'log_errors', 0 );
    @ini_set( 'display_errors', 0 );
}

// Optimize WordPress query limits
if ( ! defined( 'WP_QUERY_LIMIT' ) ) {
    define( 'WP_QUERY_LIMIT', 100 );
}

// Add custom memory monitoring function
if ( ! function_exists( 'ndm_memory_usage' ) ) {
    function ndm_memory_usage() {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $memory_usage = memory_get_usage( true );
            $memory_peak = memory_get_peak_usage( true );
            error_log( 'NDM Memory Usage: ' . size_format( $memory_usage ) . ' (Peak: ' . size_format( $memory_peak ) . ')' );
        }
    }
}

// Hook memory monitoring to key actions
add_action( 'wp_loaded', 'ndm_memory_usage' );
add_action( 'admin_init', 'ndm_memory_usage' );
