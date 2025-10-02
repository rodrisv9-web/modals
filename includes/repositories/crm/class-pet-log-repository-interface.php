<?php

interface VA_CRM_Pet_Log_Repository_Interface {
    public function create_pet_log( array $log_data );

    public function get_pet_logs( int $pet_id, $professional_id = null );

    public function add_pet_log_meta( int $log_id, string $meta_key, string $meta_value );

    public function add_pet_log_product( int $log_id, int $product_id, array $context_data = [] );

    public function get_pet_logs_full( int $pet_id, $professional_id = null );
}
