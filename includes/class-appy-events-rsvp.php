<?php
defined('ABSPATH') || exit;

class Appy_Events_RSVP {
    public function __construct() {
        add_shortcode('appy_event_rsvp', [$this, 'shortcode']);
        add_action('admin_post_nopriv_appy_event_rsvp', [$this, 'submit']);
        add_action('admin_post_appy_event_rsvp', [$this, 'submit']);
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts, 'appy_event_rsvp');
        $event_id = absint($atts['id']);
        if (!$event_id || 'appy_event' !== get_post_type($event_id)) return '';
        if ('paid' === get_post_meta($event_id, '_appy_event_type', true)) return '';

        $capacity = absint(get_post_meta($event_id, '_appy_event_capacity', true));
        if ($capacity && Appy_Events_Attendees::count($event_id) >= $capacity) return '<p class="appy-events-full">This event is full.</p>';

        ob_start(); ?>
        <form class="appy-events-rsvp" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="appy_event_rsvp">
            <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
            <?php wp_nonce_field('appy_event_rsvp_' . $event_id, 'appy_event_rsvp_nonce'); ?>
            <p><label>Name<br><input type="text" name="name" required></label></p>
            <p><label>Email<br><input type="email" name="email" required></label></p>
            <p><button type="submit">Book my place</button></p>
        </form>
        <?php return ob_get_clean();
    }

    public function submit() {
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $nonce = isset($_POST['appy_event_rsvp_nonce']) ? sanitize_text_field(wp_unslash($_POST['appy_event_rsvp_nonce'])) : '';
        if (!$event_id || !wp_verify_nonce($nonce, 'appy_event_rsvp_' . $event_id) || 'appy_event' !== get_post_type($event_id)) wp_die('Invalid RSVP request.');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (!$name || !is_email($email)) wp_die('Please enter a valid name and email.');

        $capacity = absint(get_post_meta($event_id, '_appy_event_capacity', true));
        if ($capacity && Appy_Events_Attendees::count($event_id) >= $capacity) wp_die('Sorry, this event is now full.');

        global $wpdb;
        $wpdb->insert(Appy_Events_Attendees::table(), [
            'event_id' => $event_id, 'name' => $name, 'email' => $email,
            'source' => 'rsvp', 'created_at' => current_time('mysql'),
        ], ['%d','%s','%s','%s','%s']);

        $redirect = add_query_arg('appy_rsvp', 'success', get_permalink($event_id));
        wp_safe_redirect($redirect);
        exit;
    }
}
