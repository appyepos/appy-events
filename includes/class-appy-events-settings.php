<?php
defined('ABSPATH') || exit;
class Appy_Events_Settings {
 public function __construct(){ add_action('admin_menu',[$this,'menu']); add_action('admin_init',[$this,'register']); add_filter('wp_mail_from_name',[$this,'mail_name']); add_filter('wp_mail_from',[$this,'mail_from']); }
 public function menu(){ add_submenu_page('edit.php?post_type=appy_event',__('Event Settings','appy-events'),__('Settings','appy-events'),'manage_options','appy-events-settings',[$this,'page']); }
 public function register(){ register_setting('appy_events','appy_events_settings',['sanitize_callback'=>[$this,'clean']]); }
 public function clean($v){ return ['organiser_email'=>sanitize_email($v['organiser_email']??''),'sender_name'=>sanitize_text_field($v['sender_name']??''),'sender_email'=>sanitize_email($v['sender_email']??''),'notify_organiser'=>empty($v['notify_organiser'])?0:1,'confirmation_email'=>empty($v['confirmation_email'])?0:1]; }
 public static function get($k,$default=''){ $o=get_option('appy_events_settings',[]); return array_key_exists($k,$o)?$o[$k]:$default; }
 public function mail_name($name){ $v=self::get('sender_name',''); return $v?:$name; }
 public function mail_from($email){ $v=self::get('sender_email',''); return is_email($v)?$v:$email; }
 public function page(){ $o=get_option('appy_events_settings',[]); ?><div class="wrap"><h1><?php esc_html_e('Appy Events Settings','appy-events'); ?></h1><p><?php esc_html_e('Configure booking notifications and the sender details used by Appy Events emails.','appy-events'); ?></p><form method="post" action="options.php"><?php settings_fields('appy_events'); ?>
 <table class="form-table"><tr><th><?php esc_html_e('Organiser email','appy-events'); ?></th><td><input class="regular-text" type="email" name="appy_events_settings[organiser_email]" value="<?php echo esc_attr($o['organiser_email']??get_option('admin_email')); ?>"><p class="description"><?php esc_html_e('New booking notifications are sent here.','appy-events'); ?></p></td></tr>
 <tr><th><?php esc_html_e('Sender name','appy-events'); ?></th><td><input class="regular-text" type="text" name="appy_events_settings[sender_name]" value="<?php echo esc_attr($o['sender_name']??get_bloginfo('name')); ?>"></td></tr>
 <tr><th><?php esc_html_e('Sender email','appy-events'); ?></th><td><input class="regular-text" type="email" name="appy_events_settings[sender_email]" value="<?php echo esc_attr($o['sender_email']??''); ?>"><p class="description"><?php esc_html_e('Leave blank to use the website mail configuration. Use an address authorised by your mail provider.','appy-events'); ?></p></td></tr>
 <tr><th><?php esc_html_e('Emails','appy-events'); ?></th><td><label><input type="checkbox" name="appy_events_settings[confirmation_email]" value="1" <?php checked($o['confirmation_email']??1,1); ?>> <?php esc_html_e('Send attendee confirmations','appy-events'); ?></label><br><label><input type="checkbox" name="appy_events_settings[notify_organiser]" value="1" <?php checked($o['notify_organiser']??1,1); ?>> <?php esc_html_e('Notify organiser of new bookings','appy-events'); ?></label></td></tr></table><?php submit_button(); ?></form></div><?php }
}
