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

        $start = get_post_meta($post->ID, '_appy_event_start', true);
        $end = get_post_meta($post->ID, '_appy_event_end', true);
        $location = get_post_meta($post->ID, '_appy_event_location', true);
        $capacity = get_post_meta($post->ID, '_appy_event_capacity', true);
        $type = get_post_meta($post->ID, '_appy_event_type', true) ?: 'free';
        $woocommerce = class_exists('WooCommerce');
        ?>
        <div class="appy-event-fields">
            <div class="appy-event-grid">
                <p class="appy-event-field">
                    <label for="appy-event-start"><?php esc_html_e('Start date & time', 'appy-events'); ?></label>
                    <input id="appy-event-start" type="datetime-local" name="_appy_event_start" value="<?php echo esc_attr($start); ?>">
                </p>
                <p class="appy-event-field">
                    <label for="appy-event-end"><?php esc_html_e('End date & time', 'appy-events'); ?></label>
                    <input id="appy-event-end" type="datetime-local" name="_appy_event_end" value="<?php echo esc_attr($end); ?>">
                </p>
                <p class="appy-event-field">
                    <label for="appy-event-location"><?php esc_html_e('Location', 'appy-events'); ?></label>
                    <input id="appy-event-location" type="text" name="_appy_event_location" value="<?php echo esc_attr($location); ?>" placeholder="<?php esc_attr_e('e.g. Bournemouth Pier', 'appy-events'); ?>">
                </p>
                <p class="appy-event-field">
                    <label for="appy-event-capacity"><?php esc_html_e('Capacity', 'appy-events'); ?></label>
                    <input id="appy-event-capacity" type="number" min="0" step="1" name="_appy_event_capacity" value="<?php echo esc_attr($capacity); ?>" placeholder="<?php esc_attr_e('Unlimited', 'appy-events'); ?>">
                    <span class="appy-event-help"><?php esc_html_e('Leave blank or enter 0 for unlimited places.', 'appy-events'); ?></span>
                </p>
            </div>

            <div class="appy-event-attendance">
                <span class="appy-event-section-title"><?php esc_html_e('How will people book?', 'appy-events'); ?></span>
                <div class="appy-event-type-options">
                    <label class="appy-event-type-card">
                        <input type="radio" name="_appy_event_type" value="free" <?php checked($type, 'free'); ?>>
                        <strong><?php esc_html_e('Free RSVP', 'appy-events'); ?></strong>
                        <span><?php esc_html_e('Visitors reserve a place using the built-in Appy Events form.', 'appy-events'); ?></span>
                    </label>
                    <label class="appy-event-type-card">
                        <input type="radio" name="_appy_event_type" value="paid" <?php checked($type, 'paid'); ?> <?php disabled(!$woocommerce); ?>>
                        <strong><?php esc_html_e('Paid ticket', 'appy-events'); ?></strong>
                        <span>
                            <?php
                            echo esc_html($woocommerce
                                ? __('Sell tickets through WooCommerce. Ticket settings will be added in the ticketing module.', 'appy-events')
                                : __('Requires WooCommerce. Install and activate WooCommerce to enable paid tickets.', 'appy-events'));
                            ?>
                        </span>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    public function save($post_id) {
        if (!isset($_POST['appy_event_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['appy_event_nonce'])), 'appy_event_save')) return;
        if (!current_user_can('edit_post', $post_id) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

        foreach (['_appy_event_start', '_appy_event_end', '_appy_event_location'] as $key) {
            $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            update_post_meta($post_id, $key, $value);
        }

        $capacity = isset($_POST['_appy_event_capacity']) ? absint($_POST['_appy_event_capacity']) : 0;
        update_post_meta($post_id, '_appy_event_capacity', $capacity ?: '');

        $type = isset($_POST['_appy_event_type']) && 'paid' === $_POST['_appy_event_type'] && class_exists('WooCommerce') ? 'paid' : 'free';
        update_post_meta($post_id, '_appy_event_type', $type);
    }
}
