<?php
/**
 * Plugin Name: Appy Events
 * Description: Reusable event management, RSVP and ticketing for WordPress.
 * Version: 1.5.0
 * Author: Appy
 * Text Domain: appy-events
 */
defined('ABSPATH') || exit;

define('APPY_EVENTS_VERSION', '1.5.0');
define('APPY_EVENTS_FILE', __FILE__);
define('APPY_EVENTS_PATH', plugin_dir_path(__FILE__));
define('APPY_EVENTS_URL', plugin_dir_url(__FILE__));

require_once APPY_EVENTS_PATH . 'includes/class-appy-events.php';
register_activation_hook(__FILE__, ['Appy_Events', 'activate']);

/*
 * Elementor's editor makes several special preview/AJAX requests. Appy Events
 * does not need to bootstrap its event rendering during those requests, and
 * standing down here avoids content/query hooks interfering with the editor.
 */
$appy_events_elementor_request =
    isset($_GET['elementor-preview']) ||
    (isset($_REQUEST['action']) && 0 === strpos(sanitize_key(wp_unslash($_REQUEST['action'])), 'elementor'));

if (!$appy_events_elementor_request) {
    Appy_Events::instance();
}
