<?php
defined('ABSPATH') || exit;

class Appy_Events_Frontend {
    public function __construct() {
        add_shortcode('appy_events_dashboard', [$this, 'dashboard']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_appy_event_frontend_save', [$this, 'save_event']);
        add_action('admin_post_appy_event_attendee_add', [$this, 'add_attendee']);
        add_action('admin_post_appy_event_message_attendees', [$this, 'message_attendees']);
        add_action('admin_post_appy_event_attendee_remove', [$this, 'remove_attendee']);
        add_action('admin_post_appy_event_delete', [$this, 'delete_event']);
    }

    public function assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'appy_events_dashboard')) return;
        wp_enqueue_style('appy-events', APPY_EVENTS_URL . 'assets/css/appy-events.css', [], APPY_EVENTS_VERSION);
    }

    private function can_manage() {
        return is_user_logged_in() && current_user_can('manage_appy_events');
    }

    private function dashboard_url($args = []) {
        $url = remove_query_arg(['appy_action','event_id','appy_saved','attendee_added','attendee_removed','attendee_error','message_sent','message_error','event_deleted','event_cancelled','appy_error']);
        return add_query_arg($args, $url);
    }

    public function dashboard() {
        if (!is_user_logged_in()) return '<div class="appy-events-notice"><strong>' . esc_html__('Please log in to manage events.', 'appy-events') . '</strong></div>';
        if (!$this->can_manage()) return '<div class="appy-events-notice is-error"><strong>' . esc_html__('You do not have permission to manage events.', 'appy-events') . '</strong></div>';

        $action = isset($_GET['appy_action']) ? sanitize_key(wp_unslash($_GET['appy_action'])) : '';
        if (in_array($action, ['add', 'edit'], true)) return $this->event_form($action);
        if ('attendees' === $action) return $this->attendees_view();

        $events = get_posts([
            'post_type'=>'appy_event','post_status'=>['publish','draft','pending','future','private'],'posts_per_page'=>-1,
            'orderby'=>'date','order'=>'DESC'
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
            <?php if (isset($_GET['appy_error'])) : ?><div class="appy-events-notice is-error"><strong><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['appy_error']))); ?></strong></div><?php endif; ?>
            <?php if (isset($_GET['event_cancelled'])) : ?><div class="appy-events-success"><?php esc_html_e('Event cancelled and attendees notified.', 'appy-events'); ?></div><?php endif; ?>
            <?php if (isset($_GET['event_deleted'])) : ?><div class="appy-events-success"><?php esc_html_e('Event deleted.', 'appy-events'); ?></div><?php endif; ?>
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
        $woocommerce = Appy_Events_WooCommerce::available();
        $price = $event_id ? get_post_meta($event_id, '_appy_event_price', true) : '';
        $status = $event ? $event->post_status : 'publish';

        ob_start(); ?>
        <div class="appy-events-dashboard appy-event-editor">
            <?php if (isset($_GET['appy_error'])) : ?><div class="appy-events-notice is-error"><strong><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['appy_error']))); ?></strong></div><?php endif; ?>
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
                        <p class="appy-field"><label><?php esc_html_e('Status', 'appy-events'); ?></label><select name="event_status"><option value="publish" <?php selected($status,'publish'); ?>><?php esc_html_e('Published','appy-events'); ?></option><option value="draft" <?php selected($status,'draft'); ?>><?php esc_html_e('Draft','appy-events'); ?></option></select></p>
                    </div>
                </section>

                <section class="appy-event-form-card">
                    <h3><?php esc_html_e('Event image', 'appy-events'); ?></h3>
                    <?php if ($image) : ?><div class="appy-event-current-image"><img src="<?php echo esc_url($image); ?>" alt=""><label class="appy-remove-image"><input type="checkbox" name="remove_event_image" value="1"> <?php esc_html_e('Remove current image','appy-events'); ?></label></div><?php endif; ?>
                    <p class="appy-field"><label><?php echo esc_html($image ? __('Replace image', 'appy-events') : __('Upload image', 'appy-events')); ?></label><input type="file" name="event_image" accept="image/jpeg,image/png,image/webp"></p>
                </section>

                <section class="appy-event-form-card">
                    <h3><?php esc_html_e('Bookings', 'appy-events'); ?></h3>
                    <div class="appy-booking-options">
                        <label><input type="radio" name="event_type" value="free" <?php checked($type, 'free'); ?>><strong><?php esc_html_e('Free RSVP', 'appy-events'); ?></strong><span><?php esc_html_e('People reserve a place using the built-in booking form.', 'appy-events'); ?></span></label>
                        <label class="<?php echo $woocommerce ? '' : 'is-disabled'; ?>"><input type="radio" name="event_type" value="paid" <?php checked($type, 'paid'); ?> <?php disabled(!$woocommerce); ?>><strong><?php esc_html_e('Paid ticket', 'appy-events'); ?></strong><span><?php echo esc_html($woocommerce ? __('Sell tickets through WooCommerce.', 'appy-events') : __('Requires WooCommerce.', 'appy-events')); ?></span></label>
                    </div>
                    <?php if ($woocommerce) : ?><p class="appy-field appy-paid-price"><label><?php esc_html_e('Ticket price (£)', 'appy-events'); ?></label><input type="number" min="0" step="0.01" name="event_price" value="<?php echo esc_attr($price); ?>"></p><?php endif; ?>
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
            'post_status' => isset($_POST['event_status']) && 'draft' === $_POST['event_status'] ? 'draft' : 'publish',
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
        $price = isset($_POST['event_price']) ? max(0, (float) $_POST['event_price']) : 0;
        update_post_meta($saved_id, '_appy_event_price', $price);
        if ('paid' === $type && $price > 0) Appy_Events_WooCommerce::sync_product($saved_id, $price);

        if ($event_id && !empty($_POST['remove_event_image'])) delete_post_thumbnail($saved_id);
        if (!empty($_FILES['event_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_upload('event_image', $saved_id);
            if (!is_wp_error($attachment_id)) set_post_thumbnail($saved_id, $attachment_id);
        }

        $return = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/');
        if ('paid' === $type && $price <= 0) {
            wp_safe_redirect(add_query_arg('appy_error', rawurlencode(__('Paid events require a ticket price.','appy-events')), $this->dashboard_url(['appy_action'=>'edit','event_id'=>$saved_id]))); exit;
        }
        wp_safe_redirect(add_query_arg('appy_saved', '1', $return));
        exit;
    }


    private function attendees_view() {
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $event = $event_id ? get_post($event_id) : null;
        if (!$event || 'appy_event' !== $event->post_type || !current_user_can('edit_post', $event_id)) {
            return '<div class="appy-events-notice is-error">' . esc_html__('You cannot manage attendees for this event.', 'appy-events') . '</div>';
        }

        $attendees = Appy_Events_Attendees::get_for_event($event_id);
        $capacity = absint(get_post_meta($event_id, '_appy_event_capacity', true));
        $start = get_post_meta($event_id, '_appy_event_start', true);
        $location = get_post_meta($event_id, '_appy_event_location', true);
        $count = count($attendees);
        $remaining = $capacity ? max(0, $capacity - $count) : null;

        ob_start(); ?>
        <div class="appy-events-dashboard appy-attendees-view">
            <?php if (isset($_GET['attendee_removed'])) : ?><div class="appy-events-success"><?php esc_html_e('Attendee removed.', 'appy-events'); ?></div><?php endif; ?>
            <?php if (isset($_GET['attendee_added'])) : ?><div class="appy-events-success"><?php esc_html_e('Attendee added successfully.', 'appy-events'); ?></div><?php endif; ?>
            <?php if (isset($_GET['attendee_error'])) : ?><div class="appy-events-notice is-error"><strong><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['attendee_error']))); ?></strong></div><?php endif; ?>
            <?php if (isset($_GET['message_sent'])) : ?><div class="appy-events-success"><?php printf(esc_html__('Message sent to %d attendee(s).', 'appy-events'), absint($_GET['message_sent'])); ?></div><?php endif; ?>
            <?php if (isset($_GET['message_error'])) : ?><div class="appy-events-notice is-error"><strong><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['message_error']))); ?></strong></div><?php endif; ?>

            <div class="appy-events-dashboard-head">
                <div>
                    <span class="appy-events-kicker"><?php esc_html_e('Attendees', 'appy-events'); ?></span>
                    <h2><?php echo esc_html(get_the_title($event)); ?></h2>
                    <p>
                        <?php if ($start) echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($start))); ?>
                        <?php if ($start && $location) echo ' · '; ?>
                        <?php if ($location) echo esc_html($location); ?>
                    </p>
                </div>
                <div class="appy-head-actions"><a class="appy-events-button is-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=appy_event_export&event_id='.$event_id),'appy_export_'.$event_id)); ?>"><?php esc_html_e('Export CSV','appy-events'); ?></a><a class="appy-events-button is-secondary" href="<?php echo esc_url($this->dashboard_url()); ?>"><?php esc_html_e('Back to events', 'appy-events'); ?></a></div>
            </div>

            <div class="appy-events-summary appy-attendee-summary">
                <div><strong><?php echo esc_html($count); ?></strong><span><?php esc_html_e('Booked', 'appy-events'); ?></span></div>
                <div><strong><?php echo esc_html($capacity ?: '∞'); ?></strong><span><?php esc_html_e('Capacity', 'appy-events'); ?></span></div>
                <div><strong><?php echo esc_html(null === $remaining ? '∞' : $remaining); ?></strong><span><?php esc_html_e('Places left', 'appy-events'); ?></span></div>
            </div>

            <section class="appy-event-form-card appy-add-attendee">
                <h3><?php esc_html_e('Add attendee', 'appy-events'); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="appy_event_attendee_add">
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                    <input type="hidden" name="return_url" value="<?php echo esc_url($this->dashboard_url(['appy_action' => 'attendees', 'event_id' => $event_id])); ?>">
                    <?php wp_nonce_field('appy_event_attendee_add_' . $event_id, 'appy_attendee_nonce'); ?>
                    <div class="appy-add-attendee-grid">
                        <p class="appy-field"><label><?php esc_html_e('Name', 'appy-events'); ?> <span>*</span></label><input type="text" name="attendee_name" required></p>
                        <p class="appy-field"><label><?php esc_html_e('Email', 'appy-events'); ?> <span>*</span></label><input type="email" name="attendee_email" required></p>
                        <button class="appy-events-button" type="submit"><?php esc_html_e('Add attendee', 'appy-events'); ?></button>
                    </div>
                </form>
            </section>

            <?php if ($attendees) : ?>
            <section class="appy-event-form-card appy-message-attendees">
                <h3><?php esc_html_e('Message attendees', 'appy-events'); ?></h3>
                <p class="appy-message-intro"><?php esc_html_e('Send an update to everyone currently booked onto this event. Each attendee receives a separate email.', 'appy-events'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(sprintf(__('Send this message to all %d attendees?','appy-events'),$count)); ?>')">
                    <input type="hidden" name="action" value="appy_event_message_attendees">
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                    <input type="hidden" name="return_url" value="<?php echo esc_url($this->dashboard_url(['appy_action' => 'attendees', 'event_id' => $event_id])); ?>">
                    <?php wp_nonce_field('appy_event_message_attendees_' . $event_id, 'appy_message_nonce'); ?>
                    <div class="appy-message-fields">
                        <p class="appy-field"><label><?php esc_html_e('Subject', 'appy-events'); ?> <span>*</span></label><input type="text" name="message_subject" value="<?php echo esc_attr(sprintf(__('Update about %s', 'appy-events'), get_the_title($event))); ?>" required></p>
                        <p class="appy-field"><label><?php esc_html_e('Message', 'appy-events'); ?> <span>*</span></label><textarea name="message_body" rows="6" required></textarea></p>
                    </div>
                    <div class="appy-message-actions"><span><?php printf(esc_html__('%d recipient(s)', 'appy-events'), $count); ?></span><button class="appy-events-button" type="submit"><?php esc_html_e('Send message', 'appy-events'); ?></button></div>
                </form>
            </section>
            <?php endif; ?>

            <section class="appy-attendee-list-section">
                <div class="appy-events-section-head"><h3><?php printf(esc_html__('Attendees (%d)', 'appy-events'), $count); ?></h3></div>
                <?php if (!$attendees) : ?>
                    <div class="appy-events-empty"><?php esc_html_e('No attendees have booked yet.', 'appy-events'); ?></div>
                <?php else : ?>
                    <div class="appy-attendee-table-wrap">
                        <table class="appy-attendee-table">
                            <thead><tr><th><?php esc_html_e('Name', 'appy-events'); ?></th><th><?php esc_html_e('Email', 'appy-events'); ?></th><th><?php esc_html_e('Booking', 'appy-events'); ?></th><th><?php esc_html_e('Booked', 'appy-events'); ?></th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($attendees as $attendee) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($attendee->name); ?></strong></td>
                                    <td><a href="mailto:<?php echo esc_attr($attendee->email); ?>"><?php echo esc_html($attendee->email); ?></a></td>
                                    <td><span class="appy-event-type"><?php echo esc_html('manual' === $attendee->source ? __('Manual', 'appy-events') : ('paid' === $attendee->source ? __('Paid ticket', 'appy-events') : __('RSVP', 'appy-events'))); ?></span></td>
                                    <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($attendee->created_at))); ?></td>
                                    <td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this attendee?','appy-events')); ?>')"><input type="hidden" name="action" value="appy_event_attendee_remove"><input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>"><input type="hidden" name="attendee_id" value="<?php echo esc_attr($attendee->id); ?>"><input type="hidden" name="return_url" value="<?php echo esc_url($this->dashboard_url(['appy_action'=>'attendees','event_id'=>$event_id])); ?>"><?php wp_nonce_field('appy_remove_attendee_'.$event_id,'appy_remove_nonce'); ?><button class="appy-link-danger" type="submit"><?php esc_html_e('Remove','appy-events'); ?></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php return ob_get_clean();
    }

    public function add_attendee() {
        if (!$this->can_manage()) wp_die(esc_html__('You do not have permission to manage attendees.', 'appy-events'));

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $nonce = isset($_POST['appy_attendee_nonce']) ? sanitize_text_field(wp_unslash($_POST['appy_attendee_nonce'])) : '';
        if (!$event_id || !wp_verify_nonce($nonce, 'appy_event_attendee_add_' . $event_id) || 'appy_event' !== get_post_type($event_id) || !current_user_can('edit_post', $event_id)) {
            wp_die(esc_html__('Invalid attendee request.', 'appy-events'));
        }

        $name = isset($_POST['attendee_name']) ? sanitize_text_field(wp_unslash($_POST['attendee_name'])) : '';
        $email = isset($_POST['attendee_email']) ? sanitize_email(wp_unslash($_POST['attendee_email'])) : '';
        $return = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/');

        if (!$name || !is_email($email)) {
            wp_safe_redirect(add_query_arg('attendee_error', __('Please enter a valid name and email.', 'appy-events'), $return));
            exit;
        }

        $capacity = absint(get_post_meta($event_id, '_appy_event_capacity', true));
        if ($capacity && Appy_Events_Attendees::count($event_id) >= $capacity) {
            wp_safe_redirect(add_query_arg('attendee_error', __('This event is already full.', 'appy-events'), $return));
            exit;
        }

        if (Appy_Events_Attendees::email_exists($event_id, $email)) {
            wp_safe_redirect(add_query_arg('attendee_error', __('That email address is already booked onto this event.', 'appy-events'), $return));
            exit;
        }

        Appy_Events_Attendees::add($event_id, $name, $email, 'manual');
        wp_safe_redirect(add_query_arg('attendee_added', '1', $return));
        exit;
    }


    public function message_attendees() {
        if (!$this->can_manage()) wp_die(esc_html__('You do not have permission to message attendees.', 'appy-events'));

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $nonce = isset($_POST['appy_message_nonce']) ? sanitize_text_field(wp_unslash($_POST['appy_message_nonce'])) : '';
        if (!$event_id || !wp_verify_nonce($nonce, 'appy_event_message_attendees_' . $event_id) || 'appy_event' !== get_post_type($event_id) || !current_user_can('edit_post', $event_id)) {
            wp_die(esc_html__('Invalid message request.', 'appy-events'));
        }

        $subject = isset($_POST['message_subject']) ? sanitize_text_field(wp_unslash($_POST['message_subject'])) : '';
        $body = isset($_POST['message_body']) ? sanitize_textarea_field(wp_unslash($_POST['message_body'])) : '';
        $return = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/');

        if (!$subject || !$body) {
            wp_safe_redirect(add_query_arg('message_error', __('Please enter a subject and message.', 'appy-events'), $return));
            exit;
        }

        $attendees = Appy_Events_Attendees::get_for_event($event_id);
        if (!$attendees) {
            wp_safe_redirect(add_query_arg('message_error', __('There are no attendees to message.', 'appy-events'), $return));
            exit;
        }

        $sent = 0;
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        foreach ($attendees as $attendee) {
            if (!is_email($attendee->email)) continue;
            $message = sprintf(__("Hi %s,\n\n%s\n\n%s", 'appy-events'), $attendee->name, $body, $site_name);
            if (wp_mail($attendee->email, $subject, $message)) $sent++;
        }

        if (!$sent) {
            wp_safe_redirect(add_query_arg('message_error', __('The message could not be sent. Please check the website email configuration.', 'appy-events'), $return));
            exit;
        }

        wp_safe_redirect(add_query_arg('message_sent', $sent, $return));
        exit;
    }


    public function remove_attendee() {
        if (!$this->can_manage()) wp_die(esc_html__('Permission denied.','appy-events'));
        $event_id=absint($_POST['event_id']??0); $id=absint($_POST['attendee_id']??0); $nonce=sanitize_text_field(wp_unslash($_POST['appy_remove_nonce']??'')); $return=esc_url_raw(wp_unslash($_POST['return_url']??home_url('/')));
        if(!$event_id||!$id||!wp_verify_nonce($nonce,'appy_remove_attendee_'.$event_id)||!current_user_can('edit_post',$event_id)) wp_die(esc_html__('Invalid request.','appy-events'));
        global $wpdb; $wpdb->delete(Appy_Events_Attendees::table(),['id'=>$id,'event_id'=>$event_id],['%d','%d']);
        wp_safe_redirect(add_query_arg('attendee_removed','1',$return)); exit;
    }
    public function delete_event() {
        if(!$this->can_manage()) wp_die(esc_html__('Permission denied.','appy-events'));
        $id=absint($_POST['event_id']??0); $nonce=sanitize_text_field(wp_unslash($_POST['appy_delete_nonce']??'')); $return=esc_url_raw(wp_unslash($_POST['return_url']??home_url('/')));
        if(!$id||!wp_verify_nonce($nonce,'appy_delete_event_'.$id)||'appy_event'!==get_post_type($id)||!current_user_can('delete_post',$id)) wp_die(esc_html__('Invalid request.','appy-events'));
        global $wpdb; $wpdb->delete(Appy_Events_Attendees::table(),['event_id'=>$id],['%d']);
        $pid=absint(get_post_meta($id,'_appy_event_product_id',true)); if($pid && 'product'===get_post_type($pid)) wp_delete_post($pid,true);
        wp_delete_post($id,true);
        wp_safe_redirect(add_query_arg('event_deleted','1',$return)); exit;
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
                <a href="<?php echo esc_url($this->dashboard_url(['appy_action' => 'attendees', 'event_id' => $event->ID])); ?>"><?php esc_html_e('Attendees', 'appy-events'); ?></a>
                <a class="appy-events-button is-secondary" href="<?php echo esc_url($this->dashboard_url(['appy_action' => 'edit', 'event_id' => $event->ID])); ?>"><?php esc_html_e('Edit', 'appy-events'); ?></a>
                <?php if (!Appy_Events_Operations::cancelled($event->ID)) : ?><form class="appy-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Cancel this event and email all attendees?','appy-events')); ?>')"><input type="hidden" name="action" value="appy_event_cancel"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->ID); ?>"><input type="hidden" name="return_url" value="<?php echo esc_url($this->dashboard_url()); ?>"><?php wp_nonce_field('appy_cancel_event_'.$event->ID,'appy_cancel_nonce'); ?><button class="appy-link-danger" type="submit"><?php esc_html_e('Cancel event','appy-events'); ?></button></form><?php else : ?><span class="appy-event-type"><?php esc_html_e('Cancelled','appy-events'); ?></span><?php endif; ?>
                <form class="appy-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this event? This cannot be undone.','appy-events')); ?>')"><input type="hidden" name="action" value="appy_event_delete"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->ID); ?>"><input type="hidden" name="return_url" value="<?php echo esc_url($this->dashboard_url()); ?>"><?php wp_nonce_field('appy_delete_event_'.$event->ID,'appy_delete_nonce'); ?><button class="appy-link-danger" type="submit"><?php esc_html_e('Delete','appy-events'); ?></button></form>
            </div>
        </article>
        <?php
    }
}
