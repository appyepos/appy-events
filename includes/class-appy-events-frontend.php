<?php
defined('ABSPATH') || exit;

class Appy_Events_Frontend {
    public function __construct() {
        add_shortcode('appy_events_dashboard', [$this, 'dashboard']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_appy_event_frontend_save', [$this, 'save_event']);
    }

    public function assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'appy_events_dashboard')) return;
        wp_enqueue_style('appy-events', APPY_EVENTS_URL . 'assets/css/appy-events.css', [], APPY_EVENTS_VERSION);
    }

    private function can_manage() {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    private function dashboard_url($args = []) {
        $url = remove_query_arg(['appy_action', 'event_id', 'appy_saved']);
        return add_query_arg($args, $url);
    }

    public function dashboard() {
        if (!is_user_logged_in()) return '<div class="appy-events-notice"><strong>' . esc_html__('Please log in to manage events.', 'appy-events') . '</strong></div>';
        if (!$this->can_manage()) return '<div class="appy-events-notice is-error"><strong>' . esc_html__('You do not have permission to manage events.', 'appy-events') . '</strong></div>';

        $action = isset($_GET['appy_action']) ? sanitize_key(wp_unslash($_GET['appy_action'])) : '';
        if (in_array($action, ['add', 'edit'], true)) return $this->event_form($action);

        $events = get_posts([
            'post_type' => 'appy_event',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => -1,
            'meta_key' => '_appy_event_start',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ]);

        $upcoming = []; $past = []; $undated = [];
        $now = current_time('timestamp');
        foreach ($events as $event) {
            $start = get_post_meta($event->ID, '_appy_event_start', true);
            if (!$start) $undated[] = $event;
            elseif (strtotime($start) >= $now) $upcoming[] = $event;
            else $past[] = $event;
        }
        $past = array_reverse($past);

        ob_start(); ?>
        <div class="appy-events-dashboard">
            <?php if (isset($_GET['appy_saved'])) : ?>
                <div class="appy-events-success"><?php esc_html_e('Event saved successfully.', 'appy-events'); ?></div>
            <?php endif; ?>
            <div class="appy-events-dashboard-head">
                <div>
                    <span class="appy-events-kicker"><?php esc_html_e('Event management', 'appy-events'); ?></span>
                    <h2><?php esc_html_e('Events', 'appy-events'); ?></h2>
                    <p><?php esc_html_e('Manage upcoming events, bookings and attendee numbers from one place.', 'appy-events'); ?></p>
                </div>
                <a class="appy-events-button" href="<?php echo esc_url($this->dashboard_url(['appy_action' => 'add'])); ?>"><?php esc_html_e('Add event', 'appy-events'); ?></a>
            </div>
            <div class="appy-events-summary">
                <div><strong><?php echo esc_html(count($upcoming)); ?></strong><span><?php esc_html_e('Upcoming', 'appy-events'); ?></span></div>
                <div><strong><?php echo esc_html(count($past)); ?></strong><span><?php esc_html_e('Past', 'appy-events'); ?></span></div>
                <div><strong><?php echo esc_html($this->total_attendees($events)); ?></strong><span><?php esc_html_e('Total attendees', 'appy-events'); ?></span></div>
            </div>
            <?php $this->event_section(__('Upcoming events', 'appy-events'), $upcoming, __('No upcoming events yet.', 'appy-events')); ?>
            <?php if ($undated) $this->event_section(__('Needs a date', 'appy-events'), $undated, ''); ?>
            <details class="appy-events-past">
                <summary><?php printf(esc_html__('Past events (%d)', 'appy-events'), count($past)); ?></summary>
                <?php $this->event_cards($past, __('No past events yet.', 'appy-events')); ?>
            </details>
        </div>
        <?php return ob_get_clean();
    }

    private function event_form($action) {
        $event_id = 'edit' === $action && isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $event = $event_id ? get_post($event_id) : null;
        if ('edit' === $action && (!$event || 'appy_event' !== $event->post_type || !current_user_can('edit_post', $event_id))) {
            return '<div class="appy-events-notice is-error">' . esc_html__('You cannot edit this event.', 'appy-events') . '</div>';
        }

        $title = $event ? $event->post_title : '';
        $description = $event ? $event->post_content : '';
        $start = $event_id ? get_post_meta($event_id, '_appy_event_start', true) : '';
        $end = $event_id ? get_post_meta($event_id, '_appy_event_end', true) : '';
        $location = $event_id ? get_post_meta($event_id, '_appy_event_location', true) : '';
        $capacity = $event_id ? get_post_meta($event_id, '_appy_event_capacity', true) : '';
        $type = $event_id ? (get_post_meta($event_id, '_appy_event_type', true) ?: 'free') : 'free';
        $image = $event_id ? get_the_post_thumbnail_url($event_id, 'medium') : '';
        $woocommerce = class_exists('WooCommerce');

        ob_start(); ?>
        <div class="appy-events-dashboard appy-event-editor">
            <div class="appy-events-dashboard-head">
                <div>
                    <span class="appy-events-kicker"><?php esc_html_e('Event management', 'appy-events'); ?></span>
                    <h2><?php echo esc_html($event ? __('Edit event', 'appy-events') : __('Add event', 'appy-events')); ?></h2>
                    <p><?php esc_html_e('Add the event details below. You can come back and edit them at any time.', 'appy-events'); ?></p>
                </div>
                <a class="appy-events-button is-secondary" href="<?php echo esc_url($this->dashboard_url()); ?>"><?php esc_html_e('Cancel', 'appy-events'); ?></a>
            </div>

            <form class="appy-event-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="appy_event_frontend_save">
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                <input type="hidden" name="return_url" value="<?php echo esc_url($this->dashboard_url()); ?>">
                <?php wp_nonce_field('appy_event_frontend_save_' . $event_id, 'appy_event_frontend_nonce'); ?>

                <section class="appy-event-form-card">
                    <h3><?php esc_html_e('Event details', 'appy-events'); ?></h3>
                    <div class="appy-event-form-grid">
                        <p class="appy-field is-full"><label><?php esc_html_e('Event name', 'appy-events'); ?> <span>*</span></label><input type="text" name="event_title" value="<?php echo esc_attr($title); ?>" required></p>
                        <p class="appy-field is-full"><label><?php esc_html_e('Description', 'appy-events'); ?></label><textarea name="event_description" rows="7"><?php echo esc_textarea($description); ?></textarea></p>
                        <p class="appy-field"><label><?php esc_html_e('Start date & time', 'appy-events'); ?> <span>*</span></label><input type="datetime-local" name="event_start" value="<?php echo esc_attr($start); ?>" required></p>
                        <p class="appy-field"><label><?php esc_html_e('End date & time', 'appy-events'); ?></label><input type="datetime-local" name="event_end" value="<?php echo esc_attr($end); ?>"></p>
                        <p class="appy-field"><label><?php esc_html_e('Location', 'appy-events'); ?></label><input type="text" name="event_location" value="<?php echo esc_attr($location); ?>" placeholder="<?php esc_attr_e('e.g. Bournemouth Pier', 'appy-events'); ?>"></p>
                        <p class="appy-field"><label><?php esc_html_e('Capacity', 'appy-events'); ?></label><input type="number" min="0" step="1" name="event_capacity" value="<?php echo esc_attr($capacity); ?>" placeholder="<?php esc_attr_e('Unlimited', 'appy-events'); ?>"><small><?php esc_html_e('Leave blank or 0 for unlimited.', 'appy-events'); ?></small></p>
                    </div>
                </section>

                <section class="appy-event-form-card">
                    <h3><?php esc_html_e('Event image', 'appy-events'); ?></h3>
                    <?php if ($image) : ?><div class="appy-event-current-image"><img src="<?php echo esc_url($image); ?>" alt=""></div><?php endif; ?>
                    <p class="appy-field"><label><?php echo esc_html($image ? __('Replace image', 'appy-events') : __('Upload image', 'appy-events')); ?></label><input type="file" name="event_image" accept="image/jpeg,image/png,image/webp"></p>
                </section>

                <section class="appy-event-form-card">
                    <h3><?php esc_html_e('Bookings', 'appy-events'); ?></h3>
                    <div class="appy-booking-options">
                        <label><input type="radio" name="event_type" value="free" <?php checked($type, 'free'); ?>><strong><?php esc_html_e('Free RSVP', 'appy-events'); ?></strong><span><?php esc_html_e('People reserve a place using the built-in booking form.', 'appy-events'); ?></span></label>
                        <label class="<?php echo $woocommerce ? '' : 'is-disabled'; ?>"><input type="radio" name="event_type" value="paid" <?php checked($type, 'paid'); ?> <?php disabled(!$woocommerce); ?>><strong><?php esc_html_e('Paid ticket', 'appy-events'); ?></strong><span><?php echo esc_html($woocommerce ? __('Sell tickets through WooCommerce.', 'appy-events') : __('Requires WooCommerce.', 'appy-events')); ?></span></label>
                    </div>
                </section>

                <div class="appy-event-form-actions">
                    <a href="<?php echo esc_url($this->dashboard_url()); ?>"><?php esc_html_e('Cancel', 'appy-events'); ?></a>
                    <button class="appy-events-button" type="submit"><?php echo esc_html($event ? __('Save changes', 'appy-events') : __('Create event', 'appy-events')); ?></button>
                </div>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function save_event() {
        if (!$this->can_manage()) wp_die(esc_html__('You do not have permission to manage events.', 'appy-events'));

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $nonce = isset($_POST['appy_event_frontend_nonce']) ? sanitize_text_field(wp_unslash($_POST['appy_event_frontend_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'appy_event_frontend_save_' . $event_id)) wp_die(esc_html__('Invalid event request.', 'appy-events'));
        if ($event_id && ('appy_event' !== get_post_type($event_id) || !current_user_can('edit_post', $event_id))) wp_die(esc_html__('You cannot edit this event.', 'appy-events'));

        $title = isset($_POST['event_title']) ? sanitize_text_field(wp_unslash($_POST['event_title'])) : '';
        $start = isset($_POST['event_start']) ? sanitize_text_field(wp_unslash($_POST['event_start'])) : '';
        if (!$title || !$start) wp_die(esc_html__('Event name and start date are required.', 'appy-events'));

        $post_data = [
            'post_type' => 'appy_event',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => isset($_POST['event_description']) ? wp_kses_post(wp_unslash($_POST['event_description'])) : '',
        ];
        if ($event_id) $post_data['ID'] = $event_id;

        $saved_id = $event_id ? wp_update_post($post_data, true) : wp_insert_post($post_data, true);
        if (is_wp_error($saved_id)) wp_die(esc_html($saved_id->get_error_message()));

        update_post_meta($saved_id, '_appy_event_start', $start);
        update_post_meta($saved_id, '_appy_event_end', isset($_POST['event_end']) ? sanitize_text_field(wp_unslash($_POST['event_end'])) : '');
        update_post_meta($saved_id, '_appy_event_location', isset($_POST['event_location']) ? sanitize_text_field(wp_unslash($_POST['event_location'])) : '');
        $capacity = isset($_POST['event_capacity']) ? absint($_POST['event_capacity']) : 0;
        update_post_meta($saved_id, '_appy_event_capacity', $capacity ?: '');
        $type = isset($_POST['event_type']) && 'paid' === $_POST['event_type'] && class_exists('WooCommerce') ? 'paid' : 'free';
        update_post_meta($saved_id, '_appy_event_type', $type);

        if (!empty($_FILES['event_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_upload('event_image', $saved_id);
            if (!is_wp_error($attachment_id)) set_post_thumbnail($saved_id, $attachment_id);
        }

        $return = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/');
        wp_safe_redirect(add_query_arg('appy_saved', '1', $return));
        exit;
    }

    private function total_attendees($events) {
        $total = 0;
        foreach ($events as $event) $total += Appy_Events_Attendees::count($event->ID);
        return $total;
    }

    private function event_section($title, $events, $empty) {
        echo '<section class="appy-events-section"><div class="appy-events-section-head"><h3>' . esc_html($title) . '</h3></div>';
        $this->event_cards($events, $empty);
        echo '</section>';
    }

    private function event_cards($events, $empty) {
        if (!$events) { echo '<div class="appy-events-empty">' . esc_html($empty) . '</div>'; return; }
        echo '<div class="appy-events-list">';
        foreach ($events as $event) $this->event_card($event);
        echo '</div>';
    }

    private function event_card($event) {
        $start = get_post_meta($event->ID, '_appy_event_start', true);
        $location = get_post_meta($event->ID, '_appy_event_location', true);
        $capacity = absint(get_post_meta($event->ID, '_appy_event_capacity', true));
        $type = get_post_meta($event->ID, '_appy_event_type', true) ?: 'free';
        $count = Appy_Events_Attendees::count($event->ID); ?>
        <article class="appy-event-card">
            <div class="appy-event-date">
                <?php if ($start) : ?><strong><?php echo esc_html(wp_date('d', strtotime($start))); ?></strong><span><?php echo esc_html(wp_date('M', strtotime($start))); ?></span>
                <?php else : ?><strong>—</strong><span><?php esc_html_e('Date', 'appy-events'); ?></span><?php endif; ?>
            </div>
            <div class="appy-event-main">
                <div class="appy-event-title-row"><h4><?php echo esc_html(get_the_title($event)); ?></h4><span class="appy-event-type"><?php echo esc_html('paid' === $type ? __('Paid', 'appy-events') : __('Free RSVP', 'appy-events')); ?></span></div>
                <div class="appy-event-meta">
                    <?php if ($start) : ?><span><?php echo esc_html(wp_date(get_option('time_format'), strtotime($start))); ?></span><?php endif; ?>
                    <?php if ($location) : ?><span><?php echo esc_html($location); ?></span><?php endif; ?>
                    <span><?php echo esc_html($capacity ? $count . ' / ' . $capacity . ' ' . __('attending', 'appy-events') : $count . ' ' . __('attending', 'appy-events')); ?></span>
                </div>
            </div>
            <div class="appy-event-actions">
                <?php if ('publish' === $event->post_status) : ?><a href="<?php echo esc_url(get_permalink($event)); ?>"><?php esc_html_e('View', 'appy-events'); ?></a><?php endif; ?>
                <a class="appy-events-button is-secondary" href="<?php echo esc_url($this->dashboard_url(['appy_action' => 'edit', 'event_id' => $event->ID])); ?>"><?php esc_html_e('Edit', 'appy-events'); ?></a>
            </div>
        </article>
        <?php
    }
}
