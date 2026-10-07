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
            phone varchar(60) NOT NULL DEFAULT '',
            ticket_type varchar(190) NOT NULL DEFAULT '',
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

    public static function get_for_event($event_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE event_id = %d ORDER BY created_at DESC, id DESC',
            $event_id
        ));
    }

    public static function email_exists($event_id, $email) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE event_id = %d AND email = %s LIMIT 1',
            $event_id,
            $email
        ));
    }

    public static function add($event_id, $name, $email, $source = 'manual', $order_id = null, $phone = '', $ticket_type = '') {
        global $wpdb;
        return $wpdb->insert(self::table(), [
            'event_id' => absint($event_id),
            'name' => sanitize_text_field($name),
            'email' => sanitize_email($email),
            'phone' => sanitize_text_field($phone),
            'ticket_type' => sanitize_text_field($ticket_type),
            'source' => sanitize_key($source),
            'order_id' => $order_id ? absint($order_id) : null,
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']);
    }
}
