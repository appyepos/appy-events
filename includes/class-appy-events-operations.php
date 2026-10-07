<?php
defined('ABSPATH') || exit;
class Appy_Events_Operations {
 public function __construct(){
  add_action('admin_post_appy_event_cancel',[$this,'cancel_event']);
  add_action('admin_post_appy_event_export',[$this,'export_csv']);
  add_action('admin_post_nopriv_appy_event_cancel_booking',[$this,'cancel_booking']);
  add_action('admin_post_appy_event_cancel_booking',[$this,'cancel_booking']);
 }
 public static function token($id,$email){ return hash_hmac('sha256',absint($id).'|'.strtolower($email),wp_salt('auth')); }
 public static function is_past($id){ $end=get_post_meta($id,'_appy_event_end',true); $start=get_post_meta($id,'_appy_event_start',true); $v=$end?:$start; return $v && strtotime($v)<current_time('timestamp'); }
 public static function cancelled($id){ return 'cancelled'===get_post_meta($id,'_appy_event_status',true); }
 public function cancel_event(){
  $id=absint($_POST['event_id']??0); $nonce=sanitize_text_field(wp_unslash($_POST['appy_cancel_nonce']??'')); $return=esc_url_raw(wp_unslash($_POST['return_url']??home_url('/')));
  if(!$id||!wp_verify_nonce($nonce,'appy_cancel_event_'.$id)||!current_user_can('edit_post',$id)) wp_die(esc_html__('Invalid cancellation request.','appy-events'));
  update_post_meta($id,'_appy_event_status','cancelled'); $attendees=Appy_Events_Attendees::get_for_event($id); $subject=sprintf(__('Event cancelled: %s','appy-events'),get_the_title($id));
  foreach($attendees as $a) if(is_email($a->email)) wp_mail($a->email,$subject,sprintf(__("Hi %s,\n\nUnfortunately %s has been cancelled.\n\n%s",'appy-events'),$a->name,get_the_title($id),wp_specialchars_decode(get_bloginfo('name'),ENT_QUOTES)));
  wp_safe_redirect(add_query_arg('event_cancelled','1',$return)); exit;
 }
 public function export_csv(){
  $id=absint($_GET['event_id']??0); $nonce=sanitize_text_field(wp_unslash($_GET['_wpnonce']??''));
  if(!$id||!wp_verify_nonce($nonce,'appy_export_'.$id)||!current_user_can('edit_post',$id)) wp_die(esc_html__('Invalid export request.','appy-events'));
  nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="event-'.$id.'-attendees.csv"');
  $out=fopen('php://output','w'); fputcsv($out,['Name','Email','Booking source','Order ID','Booked']);
  foreach(Appy_Events_Attendees::get_for_event($id) as $a) fputcsv($out,[$a->name,$a->email,$a->source,$a->order_id,$a->created_at]); fclose($out); exit;
 }
 public function cancel_booking(){
  $id=absint($_GET['event_id']??0); $email=sanitize_email(wp_unslash($_GET['email']??'')); $token=sanitize_text_field(wp_unslash($_GET['token']??''));
  if(!$id||!is_email($email)||!hash_equals(self::token($id,$email),$token)) wp_die(esc_html__('Invalid cancellation link.','appy-events'));
  global $wpdb; $wpdb->delete(Appy_Events_Attendees::table(),['event_id'=>$id,'email'=>$email],['%d','%s']);
  wp_safe_redirect(add_query_arg('appy_rsvp','cancelled',get_permalink($id))); exit;
 }
}
