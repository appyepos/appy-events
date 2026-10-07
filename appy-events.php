<?php
/**
 * Plugin Name: Appy Events
 * Description: Reusable event management, RSVP and ticketing for WordPress.
 * Version: 1.3.0
 * Author: Appy
 * Text Domain: appy-events
 */
defined('ABSPATH') || exit;

define('APPY_EVENTS_VERSION', '1.3.0');
define('APPY_EVENTS_FILE', __FILE__);
define('APPY_EVENTS_PATH', plugin_dir_path(__FILE__));
define('APPY_EVENTS_URL', plugin_dir_url(__FILE__));

require_once APPY_EVENTS_PATH . 'includes/class-appy-events.php';
register_activation_hook(__FILE__, ['Appy_Events', 'activate']);
Appy_Events::instance();
