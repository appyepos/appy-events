<?php
defined('ABSPATH') || exit;

class Appy_Events_Meta {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'boxes']);
        add_action('save_post_appy_event', [$this, 'save']);
    }

    public function boxes() {
        add_meta_box('appy_event_details', __('Event Details', 'appy-events'), [$this, 'render'], 'appy_event', 'normal', 'high');
    }

    public function render($post) {
        wp_nonce_field('appy_event_save', 'appy_event_nonce');
        $fields = [
            '_appy_event_start' => ['Start date & time', 'datetime-local'],
            '_appy_event_end' => ['End date & time', 'datetime-local'],
            '_appy_event_location' => ['Location', 'text'],
            '_appy_event_capacity' => ['Capacity (leave blank for unlimited)', 'number'],
        ];
        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, $key, true);
            printf('<p><label><strong>%s</strong><br><input type="%s" name="%s" value="%s" style="width:100%%;max-width:520px"></label></p>',
                esc_html($field[0]), esc_attr($field[1]), esc_attr($key), esc_attr($value));
        }
        $type = get_post_meta($post->ID, '_appy_event_type', true) ?: 'free';
        echo '<p><label><strong>Attendance</strong><br><select name="_appy_event_type">';
        echo '<option value="free"'.selected($type,'free',false).'>Free RSVP</option>';
        echo '<option value="paid"'.selected($type,'paid',false).'>Paid ticket</option>';
        echo '</select></label></p>';
    }

    public function save($post_id) {
        if (!isset($_POST['appy_event_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['appy_event_nonce'])), 'appy_event_save')) return;
        if (!current_user_can('edit_post', $post_id) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

        foreach (['_appy_event_start','_appy_event_end','_appy_event_location','_appy_event_capacity'] as $key) {
            $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            update_post_meta($post_id, $key, $value);
        }
        $type = isset($_POST['_appy_event_type']) && 'paid' === $_POST['_appy_event_type'] ? 'paid' : 'free';
        update_post_meta($post_id, '_appy_event_type', $type);
    }
}
