<?php

interface VA_CRM_Pet_Repository_Interface {
    public function create_pet( array $pet_data );

    public function create_pet_with_share_code( array $pet_data );

    public function get_pets_by_client( int $client_id );

    public function get_pets_by_client_with_access( int $client_id, int $professional_id );

    public function get_pet_by_share_code( string $share_code );

    public function get_pet_by_id( int $pet_id );

    public function update_pet( int $pet_id, array $data );

    public function get_pet_by_name_and_client( string $pet_name, int $client_id );

    public function grant_pet_access( int $pet_id, int $professional_id, string $access_level = 'read' );

    public function check_pet_access( int $professional_id, int $pet_id );
}
