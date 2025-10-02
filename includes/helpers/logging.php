<?php
/**
 * Centralized logging helper for Veterinalia Appointment.
 *
 * Enable debug logs by defining VA_LOG_ENABLED or WordPress WP_DEBUG.
 * Example: define( 'VA_LOG_ENABLED', true );
 *
 * @package Veterinalia_Appointment\Helpers
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'va_log' ) ) {
    /**
     * Write plugin logs in a controlled way.
     *
     * Logs are emitted only when WP_DEBUG or VA_LOG_ENABLED are truthy.
     * Messages are prefixed with the selected level for easier filtering.
     *
     * @param mixed  $message Message to log. Arrays/objects are JSON encoded.
     * @param string $level   Log level (debug|info|error).
     * @return void
     */
    function va_log( $message, $level = 'debug' ) {
        $is_enabled = ( defined( 'VA_LOG_ENABLED' ) && VA_LOG_ENABLED ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
        if ( ! $is_enabled ) {
            return;
        }

        $level = strtolower( (string) $level );
        $allowed_levels = [ 'debug', 'info', 'error' ];
        if ( ! in_array( $level, $allowed_levels, true ) ) {
            $level = 'info';
        }

        if ( is_array( $message ) || is_object( $message ) ) {
            $message = wp_json_encode( $message );
        }

        error_log( sprintf( '[VA %s] %s', strtoupper( $level ), $message ) );
    }
}
