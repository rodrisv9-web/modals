<?php

interface VA_CRM_Product_Repository_Interface {
    public function get_products_by_professional( int $professional_id ): array;

    public function save_product( array $product_data );

    public function delete_product( int $product_id, int $professional_id );

    public function get_products_full( int $professional_id ): array;

    public function get_manufacturers(): array;

    public function get_active_ingredients(): array;

    public function create_or_get_manufacturer( string $manufacturer_name, array $additional_data = [] );

    public function create_or_get_active_ingredient( string $ingredient_name, array $additional_data = [] );
}
