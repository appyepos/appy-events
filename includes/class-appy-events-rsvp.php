<?php
defined('ABSPATH') || exit;

class Appy_Events_RSVP {
    public function __construct() {
        add_shortcode('appy_event_rsvp', [$this, 'shortcode']);
        add_action('admin_post_nopriv_appy_event_rsvp', [$this, 'submit']);
        add_action('admin_post_appy_event_rsvp', [$this, 'submit']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('the_content', [$this, 'append_to_event_content']);
    }

    public function assets() {
        if (!is_singular()) return;
        global $post;
        if (!is_singular('appy_event') && (!$post || !has_shortcode($post->post_content,'appy_event_rsvp'))) return;
        wp_enqueue_style('appy-events', APPY_EVENTS_URL . 'assets/css/appy-events.css', [], APPY_EVENTS_VERSION);
    }

    public function append_to_event_content($content) {
        if (!is_singular('appy_event') || !in_the_loop() || !is_main_query()) return $content;
        if (has_shortcode($content, 'appy_event_rsvp')) return $content;
        if ('paid' === get_post_meta(get_the_ID(), '_appy_event_type', true)) return $content;

        return $content . $this->shortcode(['id' => get_the_ID()]);
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts, 'appy_event_rsvp');
        $event_id = absint($atts['id']);
        if (!$event_id || 'appy_event' !== get_post_type($event_id)) return '';
        if ('paid' === get_post_meta($event_id, '_appy_event_type', true)) return '';
        if (Appy_Events_Operations::cancelled($event_id)) return '<div class="appy-rsvp-notice is-full"><strong>'.esc_html__('This event has been cancelled.','appy-events').'</strong></div>';
        if (Appy_Events_Operations::is_past($event_id)) return '<div class="appy-rsvp-notice"><strong>'.esc_html__('Bookings for this event are closed.','appy-events').'</strong></div>';

        $capacity = absint(get_post_meta($event_id, '_appy_event_capacity', true));
        $count = Appy_Events_Attendees::count($event_id);
        $remaining = $capacity ? max(0, $capacity - $count) : null;

        if (isset($_GET['appy_rsvp']) && 'cancelled' === sanitize_key(wp_unslash($_GET['appy_rsvp']))) return '<div class="appy-rsvp-success"><strong>'.esc_html__('Your booking has been cancelled.','appy-events').'</strong></div>';
        if (isset($_GET['appy_rsvp']) && 'closed' === sanitize_key(wp_unslash($_GET['appy_rsvp']))) return '<div class="appy-rsvp-notice"><strong>'.esc_html__('Bookings for this event are closed.','appy-events').'</strong></div>';

        if (isset($_GET['appy_rsvp']) && 'success' === sanitize_key(wp_unslash($_GET['appy_rsvp']))) {
            $extra = Appy_Events_Settings::get('confirmation_email',1) ? '<span>'.esc_html__('We have sent a confirmation to your email address.','appy-events').'</span>' : '';
            return '<div class="appy-rsvp-success"><strong>' . esc_html__('Your place is booked.', 'appy-events') . '</strong>'.$extra.'</div>'; 
        }

        if (isset($_GET['appy_rsvp']) && 'invalid' === sanitize_key(wp_unslash($_GET['appy_rsvp']))) return '<div class="appy-rsvp-notice is-error"><strong>'.esc_html__('Please enter a valid name and email address.','appy-events').'</strong></div>';

        if (isset($_GET['appy_rsvp']) && 'duplicate' === sanitize_key(wp_unslash($_GET['appy_rsvp']))) {
            return '<div class="appy-rsvp-notice is-error"><strong>' . esc_html__('This email address is already booked onto this event.', 'appy-events') . '</strong></div>';
        }

        if (isset($_GET['appy_rsvp']) && 'full' === sanitize_key(wp_unslash($_GET['appy_rsvp']))) {
            return '<div class="appy-rsvp-notice is-full"><strong>' . esc_html__('This event is full.', 'appy-events') . '</strong><span>' . esc_html__('There are currently no places available.', 'appy-events') . '</span></div>';
        }

        if ($capacity && $count >= $capacity) {
            return '<div class="appy-rsvp-notice is-full"><strong>' . esc_html__('This event is full.', 'appy-events') . '</strong><span>' . esc_html__('There are currently no places available.', 'appy-events') . '</span></div>';
        }

        ob_start(); ?>
        <div class="appy-rsvp-box">
            <div class="appy-rsvp-heading">
                <div>
                    <span class="appy-events-kicker"><?php esc_html_e('Free event', 'appy-events'); ?></span>
                    <h3><?php esc_html_e('Book your place', 'appy-events'); ?></h3>
                    <p><?php esc_html_e('Enter your details below to reserve your place.', 'appy-events'); ?></p>
                </div>
                <?php if (null !== $remaining) : ?>
                    <div class="appy-rsvp-places"><strong><?php echo esc_html($remaining); ?></strong><span><?php esc_html_e('places left', 'appy-events'); ?></span></div>
                <?php endif; ?>
            </div>
            <form class="appy-events-rsvp" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="appy_event_rsvp">
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                <?php wp_nonce_field('appy_event_rsvp_' . $event_id, 'appy_event_rsvp_nonce'); ?>
                <div class="appy-rsvp-fields">
                    <p><label for="appy-rsvp-name"><?php esc_html_e('Name', 'appy-events'); ?></label><input id="appy-rsvp-name" type="text" name="name" autocomplete="name" required></p>
                    <p><label for="appy-rsvp-email"><?php esc_html_e('Email', 'appy-events'); ?></label><input id="appy-rsvp-email" type="email" name="email" autocomplete="email" required></p>
                </div>
                <button class="appy-rsvp-button" type="submit"><?php esc_html_e('Book my place', 'appy-events'); ?></button>
                <p class="appy-rsvp-small"><?php esc_html_e('We will only use your email for information about this event.', 'appy-events'); ?></p>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function submit() {
        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $nonce = isset($_POST['appy_event_rsvp_nonce']) ? sanitize_text_field(wp_unslash($_POST['appy_event_rsvp_nonce'])) : '';
        if (!$event_id || !wp_verify_nonce($nonce, 'appy_event_rsvp_' . $event_id) || 'appy_event' !== get_post_type($event_id)) {
            wp_die(esc_html__('Invalid RSVP request.', 'appy-events'));
        }
        if (Appy_Events_Operations::cancelled($event_id) || Appy_Events_Operations::is_past($event_id)) { wp_safe_redirect(add_query_arg('appy_rsvp','closed',get_permalink($event_id))); exit; }
        if ('paid' === get_post_meta($event_id, '_appy_event_type', true)) {
            wp_die(esc_html__('This event does not accept free RSVP bookings.', 'appy-events'));
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (!$name || !is_email($email)) { wp_safe_redirect(add_query_arg('appy_rsvp','invalid',get_permalink($event_id))); exit; }

        $redirect = get_permalink($event_id);

        if (Appy_Events_Attendees::email_exists($event_id, $email)) {
            wp_safe_redirect(add_query_arg('appy_rsvp', 'duplicate', $redirect));
            exit;
        }

        $capacity = absint(get_post_meta($event_id, '_appy_event_capacity', true));
        if ($capacity && Appy_Events_Attendees::count($event_id) >= $capacity) {
            wp_safe_redirect(add_query_arg('appy_rsvp', 'full', $redirect));
            exit;
        }

        $inserted = Appy_Events_Attendees::add($event_id, $name, $email, 'rsvp');
        if (!$inserted) wp_die(esc_html__('We could not save your booking. Please try again.', 'appy-events'));

        if (Appy_Events_Settings::get('confirmation_email', 1)) $this->send_confirmation($event_id, $name, $email);
        if (Appy_Events_Settings::get('notify_organiser', 1)) {
            $to = Appy_Events_Settings::get('organiser_email', get_option('admin_email'));
            if (is_email($to)) wp_mail($to, sprintf(__('New booking: %s', 'appy-events'), get_the_title($event_id)), sprintf(__("%s (%s) has booked onto %s.", 'appy-events'), $name, $email, get_the_title($event_id)));
        }

        wp_safe_redirect(add_query_arg('appy_rsvp', 'success', $redirect));
        exit;
    }

    private function send_confirmation($event_id, $name, $email) {
        $title = get_the_title($event_id);
        $start = get_post_meta($event_id, '_appy_event_start', true);
        $location = get_post_meta($event_id, '_appy_event_location', true);

        $subject = sprintf(__('Your booking for %s', 'appy-events'), $title);
        $message = sprintf(__("Hi %s,\n\nYour place is confirmed for %s.", 'appy-events'), $name, $title);

        if ($start) {
            $message .= "\n" . sprintf(__('Date: %s', 'appy-events'), wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start)));
        }
        if ($location) {
            $message .= "\n" . sprintf(__('Location: %s', 'appy-events'), $location);
        }

        $cancel=add_query_arg(['action'=>'appy_event_cancel_booking','event_id'=>$event_id,'email'=>$email,'token'=>Appy_Events_Operations::token($event_id,$email)],admin_url('admin-post.php'));
        $message .= "\n\n" . __('We look forward to seeing you there.', 'appy-events');
        $message .= "\n\n" . sprintf(__('If you can no longer attend, cancel your booking here: %s','appy-events'),$cancel);
        $message .= "\n\n" . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        wp_mail($email, $subject, $message);
    }
}
