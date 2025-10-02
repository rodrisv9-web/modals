<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * @deprecated 1.0.0 Utiliza VA_CRM_Service en su lugar.
 */
class Veterinalia_CRM_Database {

    /** @var self|null */
    private static $instance = null;

    /** @var VA_CRM_Service */
    private $service;

    public static function get_instance() {
        _doing_it_wrong(
            __METHOD__,
            'Veterinalia_CRM_Database está en desuso. Utiliza VA_CRM_Service::get_instance().',
            '1.0.0'
        );

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->service = VA_CRM_Service::get_instance();
    }

    public function __call( $name, $arguments ) {
        _doing_it_wrong(
            __METHOD__,
            sprintf(
                'Veterinalia_CRM_Database::%1$s() está en desuso. Utiliza VA_CRM_Service::%1$s().',
                $name
            ),
            '1.0.0'
        );

        if ( method_exists( $this->service, $name ) ) {
            return call_user_func_array( [ $this->service, $name ], $arguments );
        }

        return null;
    }

    /**
     * Permite acceder al nuevo servicio.
     */
    public function service(): VA_CRM_Service {
        return $this->service;
    }
}
