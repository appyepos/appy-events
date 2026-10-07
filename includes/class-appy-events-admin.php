<?php
defined('ABSPATH') || exit;

class Appy_Events_Admin {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_filter('manage_appy_event_posts_columns', [$this, 'columns']);
        add_action('manage_appy_event_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('manage_edit-appy_event_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_posts', [$this, 'sort_events']);
    }

    public function assets($hook) {
        $screen = get_current_screen();
        if (!$screen || 'appy_event' !== $screen->post_type) return;

        wp_enqueue_style(
            'appy-events-admin',
            APPY_EVENTS_URL . 'assets/css/appy-events-admin.css',
            [],
            APPY_EVENTS_VERSION
        );
    }

    public function columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                $new['appy_event_date'] = __('Event date', 'appy-events');
                $new['appy_event_location'] = __('Location', 'appy-events');
                $new['appy_event_type'] = __('Booking', 'appy-events');
                $new['appy_event_attendees'] = __('Attendees', 'appy-events');
                $new['appy_event_status'] = __('Status', 'appy-events');
            }
        }
        return $new;
    }

    public function column_content($column, $post_id) {
        if ('appy_event_date' === $column) {
            $start = get_post_meta($post_id, '_appy_event_start', true);
            echo $start ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start))) : '—';
        }

        if ('appy_event_location' === $column) {
            echo esc_html(get_post_meta($post_id, '_appy_event_location', true) ?: '—');
        }

        if ('appy_event_type' === $column) {
            $type = get_post_meta($post_id, '_appy_event_type', true) ?: 'free';
            echo '<span class="appy-event-badge">' . esc_html('paid' === $type ? __('Paid ticket', 'appy-events') : __('Free RSVP', 'appy-events')) . '</span>';
        }

        if ('appy_event_attendees' === $column) {
            $count = Appy_Events_Attendees::count($post_id);
            $capacity = absint(get_post_meta($post_id, '_appy_event_capacity', true));
            echo esc_html($capacity ? $count . ' / ' . $capacity : (string) $count);
        }

        if ('appy_event_status' === $column) {
            $start = get_post_meta($post_id, '_appy_event_start', true);
            if (!$start) {
                echo '<span class="appy-event-status is-draft">' . esc_html__('Date needed', 'appy-events') . '</span>';
                return;
            }
            $future = strtotime($start) >= current_time('timestamp');
            echo '<span class="appy-event-status ' . ($future ? 'is-upcoming' : 'is-past') . '">' .
                esc_html($future ? __('Upcoming', 'appy-events') : __('Past', 'appy-events')) . '</span>';
        }
    }

    public function sortable_columns($columns) {
        $columns['appy_event_date'] = 'appy_event_date';
        return $columns;
    }

    public function sort_events($query) {
        if (!is_admin() || !$query->is_main_query() || 'appy_event' !== $query->get('post_type')) return;
        if ('appy_event_date' === $query->get('orderby')) {
            $query->set('meta_key', '_appy_event_start');
            $query->set('orderby', 'meta_value');
        }
    }
}
