<?php
/**
 * Configuration Cache para Veterinalia Appointment Plugin
 * 
 * Cachea opciones de WordPress frecuentemente accedidas para mejorar rendimiento
 * 
 * @package Veterinalia_Appointment
 * @since 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class VA_Config {
    
    /**
     * Cache interno para la sesión actual
     * @var array
     */
    private static $cache = [];
    
    /**
     * Opciones que deben ser cacheadas por más tiempo
     * @var array
     */
    private static $persistent_options = [
        'date_format',
        'time_format',
        'timezone_string',
        'va_appointment_guest_booking_enabled',
        'va_schedule_migration_done',
        'va_templates_db_version',
        'va_sample_patients_populated',
    ];
    
    /**
     * Obtiene una opción con cache inteligente
     * 
     * @param string $option Nombre de la opción
     * @param mixed $default Valor por defecto
     * @param bool $force_refresh Forzar actualización del cache
     * @return mixed Valor de la opción
     */
    public static function get($option, $default = false, $force_refresh = false) {
        // Cache de sesión para opciones ya consultadas
        if (!$force_refresh && isset(self::$cache[$option])) {
            return self::$cache[$option];
        }
        
        // Para opciones persistentes, usar transients
        if (in_array($option, self::$persistent_options)) {
            $cache_key = VA_Cache_Helper::PREFIX_CONFIG . $option;
            
            if (!$force_refresh) {
                $value = get_transient($cache_key);
                if (false !== $value) {
                    self::$cache[$option] = $value;
                    return $value;
                }
            }
            
            // Obtener valor real y cachear
            $value = get_option($option, $default);
            self::$cache[$option] = $value;
            
            // Cachear por más tiempo las opciones que raramente cambian
            $expiration = self::get_cache_expiration($option);
            set_transient($cache_key, $value, $expiration);
            
            return $value;
        }
        
        // Para opciones no persistentes, solo cache de sesión
        $value = get_option($option, $default);
        self::$cache[$option] = $value;
        
        return $value;
    }
    
    /**
     * Actualiza una opción e invalida su cache
     * 
     * @param string $option Nombre de la opción
     * @param mixed $value Nuevo valor
     * @return bool True si se actualizó correctamente
     */
    public static function update($option, $value) {
        $updated = update_option($option, $value);
        
        if ($updated) {
            // Limpiar cache de sesión
            unset(self::$cache[$option]);
            
            // Limpiar cache persistente si aplica
            if (in_array($option, self::$persistent_options)) {
                $cache_key = VA_Cache_Helper::PREFIX_CONFIG . $option;
                delete_transient($cache_key);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[VA Config] Updated and invalidated cache for: {$option}");
            }
        }
        
        return $updated;
    }
    
    /**
     * Obtiene múltiples opciones de una vez
     * 
     * @param array $options Array de nombres de opciones
     * @param array $defaults Array asociativo de valores por defecto
     * @return array Array asociativo con los valores
     */
    public static function get_multiple($options, $defaults = []) {
        $values = [];
        
        foreach ($options as $option) {
            $default = isset($defaults[$option]) ? $defaults[$option] : false;
            $values[$option] = self::get($option, $default);
        }
        
        return $values;
    }
    
    /**
     * Formatea una fecha usando las opciones de WordPress cacheadas
     * 
     * @param string|int $date Fecha a formatear
     * @param bool $include_time Incluir hora
     * @return string Fecha formateada
     */
    public static function format_date($date, $include_time = false) {
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        
        if (!$timestamp) {
            return '';
        }
        
        $date_format = self::get('date_format');
        
        if ($include_time) {
            $time_format = self::get('time_format');
            $format = $date_format . ' ' . $time_format;
        } else {
            $format = $date_format;
        }
        
        return date_i18n($format, $timestamp);
    }
    
    /**
     * Limpia todo el cache de configuración
     */
    public static function flush_cache() {
        // Limpiar cache de sesión
        self::$cache = [];
        
        // Limpiar cache persistente
        VA_Cache_Helper::invalidate_group(VA_Cache_Helper::PREFIX_CONFIG);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
                error_log("[VA Config] All configuration cache flushed");
            }
        }
    }
    
    /**
     * Obtiene estadísticas del cache de configuración
     * 
     * @return array Estadísticas
     */
    public static function get_cache_stats() {
        return [
            'session_cache_count' => count(self::$cache),
            'session_cached_options' => array_keys(self::$cache),
            'persistent_options_count' => count(self::$persistent_options),
            'persistent_options' => self::$persistent_options,
        ];
    }
    
    /**
     * Determina el tiempo de expiración según el tipo de opción
     * 
     * @param string $option Nombre de la opción
     * @return int Tiempo de expiración en segundos
     */
    private static function get_cache_expiration($option) {
        // Opciones que raramente cambian - cache largo
        $long_cache_options = [
            'date_format',
            'time_format',
            'timezone_string',
        ];
        
        // Opciones del plugin que pueden cambiar más frecuentemente
        $medium_cache_options = [
            'va_appointment_guest_booking_enabled',
        ];
        
        // Opciones de migración/setup que cambian muy raramente
        $very_long_cache_options = [
            'va_schedule_migration_done',
            'va_templates_db_version',
            'va_sample_patients_populated',
        ];
        
        if (in_array($option, $very_long_cache_options)) {
            return DAY_IN_SECONDS; // 24 horas
        } elseif (in_array($option, $long_cache_options)) {
            return VA_Cache_Helper::LONG_EXPIRATION; // 6 horas
        } elseif (in_array($option, $medium_cache_options)) {
            return VA_Cache_Helper::DEFAULT_EXPIRATION; // 1 hora
        }
        
        return VA_Cache_Helper::DEFAULT_EXPIRATION; // Por defecto 1 hora
    }
}
