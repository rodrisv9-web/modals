<?php

interface VA_CRM_Client_Repository_Interface {
    public function create_client( array $client_data ): int;

    public function create_guest_client( array $client_data ): int;

    public function get_clients_by_professional( int $professional_id ): array;

    public function get_client_by_id( int $client_id );

    public function get_client_by_email( string $email );

    public function link_client_to_user( int $client_id, int $user_id ): bool;

    public function search_clients_with_access_check( string $term, int $professional_id ): array;

    public function search_clients_basic( string $term ): array;

    public function get_client_by_user_id( int $user_id );
}
