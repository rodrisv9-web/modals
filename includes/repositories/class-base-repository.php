<?php
abstract class VA_Base_Repository {
    /** @var wpdb */
    protected $wpdb;
    protected $charset_collate;

    public function __construct( \wpdb $wpdb ) {
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
}
