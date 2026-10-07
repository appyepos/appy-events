<?php
defined('ABSPATH') || exit;
class Appy_Events_Capabilities {
 public static function caps(){ return ['edit_appy_event','read_appy_event','delete_appy_event','edit_appy_events','edit_others_appy_events','publish_appy_events','read_private_appy_events','delete_appy_events','delete_private_appy_events','delete_published_appy_events','delete_others_appy_events','edit_private_appy_events','edit_published_appy_events']; }
 public static function install(){
  foreach(['administrator','editor'] as $name){ $role=get_role($name); if(!$role) continue; foreach(self::caps() as $cap) $role->add_cap($cap); $role->add_cap('manage_appy_events'); }
  if(!get_role('event_manager')) add_role('event_manager',__('Event Manager','appy-events'),array_fill_keys(array_merge(['read','upload_files','manage_appy_events'],self::caps()),true));
 }
}
