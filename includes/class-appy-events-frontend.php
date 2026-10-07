<?php
defined('ABSPATH') || exit;

class Appy_Events_Frontend {
    public function __construct() {
        add_shortcode('appy_events_dashboard', [$this, 'dashboard']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'appy_events_dashboard')) return;

        wp_enqueue_style(
            'appy-events',
            APPY_EVENTS_URL . 'assets/css/appy-events.css',
            [],
            APPY_EVENTS_VERSION
        );
    }

    private function can_manage() {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    public function dashboard() {
        if (!is_user_logged_in()) {
            return '<div class="appy-events-notice"><strong>' . esc_html__('Please log in to manage events.', 'appy-events') . '</strong></div>';
        }

        if (!$this->can_manage()) {
            return '<div class="appy-events-notice is-error"><strong>' . esc_html__('You do not have permission to manage events.', 'appy-events') . '</strong></div>';
        }

        $events = get_posts([
            'post_type' => 'appy_event',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => -1,
            'meta_key' => '_appy_event_start',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ]);

        $upcoming = [];
        $past = [];
        $undated = [];
        $now = current_time('timestamp');

        foreach ($events as $event) {
            $start = get_post_meta($event->ID, '_appy_event_start', true);
            if (!$start) {
                $undated[] = $event;
            } elseif (strtotime($start) >= $now) {
                $upcoming[] = $event;
            } else {
                $past[] = $event;
            }
        }

        $past = array_reverse($past);

        ob_start();
        ?>
        <div class="appy-events-dashboard">
            <div class="appy-events-dashboard-head">
                <div>
                    <span class="appy-events-kicker"><?php esc_html_e('Event management', 'appy-events'); ?></span>
                    <h2><?php esc_html_e('Events', 'appy-events'); ?></h2>
                    <p><?php esc_html_e('Manage upcoming events, bookings and attendee numbers from one place.', 'appy-events'); ?></p>
                </div>
                <a class="appy-events-button" href="<?php echo esc_url(admin_url('post-new.php?post_type=appy_event')); ?>">
                    <?php esc_html_e('Add event', 'appy-events'); ?>
                </a>
            </div>

            <div class="appy-events-summary">
                <div><strong><?php echo esc_html(count($upcoming)); ?></strong><span><?php esc_html_e('Upcoming', 'appy-events'); ?></span></div>
                <div><strong><?php echo esc_html(count($past)); ?></strong><span><?php esc_html_e('Past', 'appy-events'); ?></span></div>
                <div><strong><?php echo esc_html($this->total_attendees($events)); ?></strong><span><?php esc_html_e('Total attendees', 'appy-events'); ?></span></div>
            </div>

            <?php $this->event_section(__('Upcoming events', 'appy-events'), $upcoming, __('No upcoming events yet.', 'appy-events')); ?>

            <?php if ($undated) : ?>
                <?php $this->event_section(__('Needs a date', 'appy-events'), $undated, ''); ?>
            <?php endif; ?>

            <details class="appy-events-past">
                <summary><?php printf(esc_html__('Past events (%d)', 'appy-events'), count($past)); ?></summary>
                <?php $this->event_cards($past, __('No past events yet.', 'appy-events')); ?>
            </details>
        </div>
        <?php
        return ob_get_clean();
    }

    private function total_attendees($events) {
        $total = 0;
        foreach ($events as $event) {
            $total += Appy_Events_Attendees::count($event->ID);
        }
        return $total;
    }

    private function event_section($title, $events, $empty) {
        echo '<section class="appy-events-section">';
        echo '<div class="appy-events-section-head"><h3>' . esc_html($title) . '</h3></div>';
        $this->event_cards($events, $empty);
        echo '</section>';
    }

    private function event_cards($events, $empty) {
        if (!$events) {
            echo '<div class="appy-events-empty">' . esc_html($empty) . '</div>';
            return;
        }

        echo '<div class="appy-events-list">';
        foreach ($events as $event) {
            $this->event_card($event);
        }
        echo '</div>';
    }

    private function event_card($event) {
        $start = get_post_meta($event->ID, '_appy_event_start', true);
        $location = get_post_meta($event->ID, '_appy_event_location', true);
        $capacity = absint(get_post_meta($event->ID, '_appy_event_capacity', true));
        $type = get_post_meta($event->ID, '_appy_event_type', true) ?: 'free';
        $count = Appy_Events_Attendees::count($event->ID);
        ?>
        <article class="appy-event-card">
            <div class="appy-event-date">
                <?php if ($start) : ?>
                    <strong><?php echo esc_html(wp_date('d', strtotime($start))); ?></strong>
                    <span><?php echo esc_html(wp_date('M', strtotime($start))); ?></span>
                <?php else : ?>
                    <strong>—</strong><span><?php esc_html_e('Date', 'appy-events'); ?></span>
                <?php endif; ?>
            </div>

            <div class="appy-event-main">
                <div class="appy-event-title-row">
                    <h4><?php echo esc_html(get_the_title($event)); ?></h4>
                    <span class="appy-event-type"><?php echo esc_html('paid' === $type ? __('Paid', 'appy-events') : __('Free RSVP', 'appy-events')); ?></span>
                </div>
                <div class="appy-event-meta">
                    <?php if ($start) : ?><span><?php echo esc_html(wp_date(get_option('time_format'), strtotime($start))); ?></span><?php endif; ?>
                    <?php if ($location) : ?><span><?php echo esc_html($location); ?></span><?php endif; ?>
                    <span><?php echo esc_html($capacity ? $count . ' / ' . $capacity . ' ' . __('attending', 'appy-events') : $count . ' ' . __('attending', 'appy-events')); ?></span>
                </div>
            </div>

            <div class="appy-event-actions">
                <?php if ('publish' === $event->post_status) : ?>
                    <a href="<?php echo esc_url(get_permalink($event)); ?>"><?php esc_html_e('View', 'appy-events'); ?></a>
                <?php endif; ?>
                <a class="appy-events-button is-secondary" href="<?php echo esc_url(get_edit_post_link($event->ID)); ?>"><?php esc_html_e('Edit', 'appy-events'); ?></a>
            </div>
        </article>
        <?php
    }
}
