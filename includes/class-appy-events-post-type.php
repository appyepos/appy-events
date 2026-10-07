<?php
defined('ABSPATH') || exit;

class Appy_Events_Post_Type {
    public function __construct() { add_action('init', [__CLASS__, 'register']); }

    public static function register() {
        register_post_type('appy_event', [
            'labels' => [
                'name' => __('Events', 'appy-events'),
                'singular_name' => __('Event', 'appy-events'),
                'add_new_item' => __('Add New Event', 'appy-events'),
                'edit_item' => __('Edit Event', 'appy-events'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title','editor','thumbnail','excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'events'],
        ]);
    }
}
