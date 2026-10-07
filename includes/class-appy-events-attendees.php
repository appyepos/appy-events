<?php
defined('ABSPATH') || exit;

class Appy_Events_Attendees {
    public function __construct() {}

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'appy_event_attendees';
    }

    public static function create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            name varchar(190) NOT NULL,
            email varchar(190) NOT NULL,
            source varchar(30) NOT NULL DEFAULT 'rsvp',
            order_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY email (email)
        ) {$charset};");
    }

    public static function count($event_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE event_id = %d', $event_id));
    }
}
