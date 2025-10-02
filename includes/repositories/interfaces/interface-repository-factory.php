<?php
interface VA_Repository_Factory_Interface {
    public function get( string $repository_key );
    public function bind( string $repository_key, $repository_instance ): void;
}
