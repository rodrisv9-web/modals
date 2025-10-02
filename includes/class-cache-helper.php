<?php
/**
 * Cache Helper para Veterinalia Appointment Plugin
 * 
 * Proporciona una interfaz unificada para manejo de cache usando transients de WordPress
 * Incluye invalidación inteligente y logging para debugging
 * 
 * @package Veterinalia_Appointment
 * @since 1.0.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class VA_Cache_Helper {
    
    // Tiempos de expiración por defecto
    const DEFAULT_EXPIRATION = HOUR_IN_SECONDS;
    const SHORT_EXPIRATION = 15 * MINUTE_IN_SECONDS;
    const LONG_EXPIRATION = 6 * HOUR_IN_SECONDS;
    
    // Prefijos para diferentes tipos de cache
    const PREFIX_SERVICES = 'va_services_';
    const PREFIX_PET_ACCESS = 'va_pet_access_';
    const PREFIX_CONFIG = 'va_config_';
    const PREFIX_CATEGORIES = 'va_categories_';
    const PREFIX_LISTINGS = 'va_listings_ids_';
    
    /**
     * Obtiene datos del cache o los genera usando el callback
     * 
     * @param string $key Clave única del cache
     * @param callable $callback Función que genera los datos si no están en cache
     * @param int $expiration Tiempo de expiración en segundos
     * @return mixed Los datos del cache o generados por el callback
     */
    public static function get_or_set($key, $callback, $expiration = self::DEFAULT_EXPIRATION) {
        // Sanitizar la clave
        $cache_key = self::sanitize_key($key);
        
        // Intentar obtener del cache
        $data = get_transient($cache_key);
        
        if (false !== $data) {
            // Cache hit - logging opcional para debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[VA Cache] HIT: {$cache_key}");
            }
            return $data;
        }
        
        // Cache miss - generar datos
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[VA Cache] MISS: {$cache_key}");
        }
        
        if (!is_callable($callback)) {
            error_log("[VA Cache] ERROR: Callback no es válido para {$cache_key}");
            return false;
        }
        
        try {
            $data = call_user_func($callback);
            
            // Solo cachear si tenemos datos válidos
            if (false !== $data && null !== $data) {
                set_transient($cache_key, $data, $expiration);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[VA Cache] SET: {$cache_key} (expires in {$expiration}s)");
                }
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("[VA Cache] ERROR generating data for {$cache_key}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invalida cache por prefijo/grupo
     * 
     * @param string $prefix Prefijo del grupo a invalidar
     * @param mixed $identifier Identificador específico (opcional)
     */
    public static function invalidate_group($prefix, $identifier = null) {
        global $wpdb;
        
        if ($identifier !== null) {
            // Invalidar cache específico
            $cache_key = self::sanitize_key($prefix . $identifier);
            delete_transient($cache_key);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
                    error_log("[VA Cache] INVALIDATE SPECIFIC: {$cache_key}");
                }
            }
        } else {
            // Invalidar todo el grupo
            $pattern = '_transient_' . esc_sql($prefix) . '%';
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
            
            // También limpiar timeout transients
            $timeout_pattern = '_transient_timeout_' . esc_sql($prefix) . '%';
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $timeout_pattern
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
                    error_log("[VA Cache] INVALIDATE GROUP: {$prefix} ({$deleted} items)");
                }
            }
        }
    }
    
    /**
     * Invalida cache específico por clave completa
     * 
     * @param string $key Clave del cache a invalidar
     */
    public static function invalidate($key) {
        $cache_key = self::sanitize_key($key);
        $deleted = delete_transient($cache_key);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
                error_log("[VA Cache] INVALIDATE: {$cache_key} " . ($deleted ? 'SUCCESS' : 'NOT_FOUND'));
            }
        }
        
        return $deleted;
    }
    
    /**
     * Limpia todo el cache del plugin
     */
    public static function flush_all() {
        $prefixes = [
            self::PREFIX_SERVICES,
            self::PREFIX_PET_ACCESS,
            self::PREFIX_CONFIG,
            self::PREFIX_CATEGORIES,
            self::PREFIX_LISTINGS,
        ];
        
        foreach ($prefixes as $prefix) {
            self::invalidate_group($prefix);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[VA Cache] FLUSH ALL completed");
        }
    }
    
    /**
     * Obtiene estadísticas del cache (para debugging)
     * 
     * @return array Estadísticas del cache
     */
    public static function get_stats() {
        global $wpdb;
        
        $stats = [];
        $prefixes = [
            'services'   => self::PREFIX_SERVICES,
            'pet_access' => self::PREFIX_PET_ACCESS,
            'config'     => self::PREFIX_CONFIG,
            'categories' => self::PREFIX_CATEGORIES,
            'listings'   => self::PREFIX_LISTINGS,
        ];
        
        foreach ($prefixes as $name => $prefix) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_' . esc_sql($prefix) . '%'
                )
            );
            $stats[$name] = intval($count);
        }
        
        return $stats;
    }
    
    /**
     * Sanitiza la clave del cache para asegurar compatibilidad
     * 
     * @param string $key Clave original
     * @return string Clave sanitizada
     */
    private static function sanitize_key($key) {
        // Remover caracteres problemáticos y limitar longitud
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        
        // WordPress tiene límite de 172 caracteres para option_name
        if (strlen($sanitized) > 140) {
            $sanitized = substr($sanitized, 0, 120) . '_' . md5($key);
        }
        
        return $sanitized;
    }
    
    /**
     * Genera clave de cache para servicios de un profesional
     * 
     * @param int $professional_id ID del profesional
     * @param bool $active_only Solo servicios activos
     * @return string Clave de cache
     */
    public static function services_key($professional_id, $active_only = true) {
        return self::PREFIX_SERVICES . $professional_id . ($active_only ? '_active' : '_all');
    }
    
    /**
     * Genera clave de cache para categorías de un profesional
     * 
     * @param int $professional_id ID del profesional
     * @return string Clave de cache
     */
    public static function categories_key($professional_id) {
        return self::PREFIX_CATEGORIES . $professional_id;
    }
    
    /**
     * Genera clave de cache para acceso a mascotas
     * 
     * @param int $professional_id ID del profesional
     * @param int $client_id ID del cliente
     * @return string Clave de cache
     */
    public static function pet_access_key($professional_id, $client_id) {
        return self::PREFIX_PET_ACCESS . $professional_id . '_' . $client_id;
    }
}
