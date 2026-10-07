<?php
defined('ABSPATH') || exit;

require_once APPY_EVENTS_PATH . 'includes/class-appy-events-post-type.php';
require_once APPY_EVENTS_PATH . 'includes/class-appy-events-meta.php';
require_once APPY_EVENTS_PATH . 'includes/class-appy-events-attendees.php';
require_once APPY_EVENTS_PATH . 'includes/class-appy-events-rsvp.php';
require_once APPY_EVENTS_PATH . 'includes/class-appy-events-admin.php';

final class Appy_Events {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        new Appy_Events_Post_Type();
        new Appy_Events_Meta();
        new Appy_Events_Attendees();
        new Appy_Events_RSVP();
        new Appy_Events_Admin();
    }

    public static function activate() {
        Appy_Events_Post_Type::register();
        Appy_Events_Attendees::create_table();
        flush_rewrite_rules();
    }
}
