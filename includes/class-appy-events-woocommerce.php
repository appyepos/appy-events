<?php
defined('ABSPATH') || exit;
class Appy_Events_WooCommerce {
 public function __construct(){
  add_action('woocommerce_order_status_processing',[$this,'capture']); add_action('woocommerce_order_status_completed',[$this,'capture']); add_action('woocommerce_order_status_on-hold',[$this,'capture']);
  add_action('woocommerce_order_status_cancelled',[$this,'remove_order']); add_action('woocommerce_order_status_refunded',[$this,'remove_order']);
  add_filter('woocommerce_add_to_cart_validation',[$this,'validate_capacity'],10,3); add_action('woocommerce_check_cart_items',[$this,'check_cart']);
  add_filter('woocommerce_add_cart_item_data',[$this,'cart_data'],10,2); add_filter('woocommerce_get_item_data',[$this,'display_cart_attendees'],10,2);
  add_action('woocommerce_checkout_create_order_line_item',[$this,'order_item'],10,4);
 }
 public static function available(){ return class_exists('WooCommerce'); }
 public function validate_capacity($passed,$product_id,$qty){
  $event=absint(get_post_meta($product_id,'_appy_event_id',true)); if(!$event)return $passed; $cap=absint(get_post_meta($event,'_appy_event_capacity',true)); $idx=get_post_meta($product_id,'_appy_event_ticket_index',true); $types=Appy_Events_Tickets::get($event); $tcap=isset($types[$idx])?absint($types[$idx]['capacity']??0):0; $tbooked=$tcap?Appy_Events_Tickets::booked($event,$idx):0;
  if(Appy_Events_Operations::cancelled($event)||Appy_Events_Operations::is_past($event)||($cap&&Appy_Events_Attendees::count($event)+$qty>$cap)||($tcap&&$tbooked+$qty>$tcap)){ wc_add_notice(__('There are not enough places available for this ticket.','appy-events'),'error'); return false; }
  $raw=$_POST['appy_attendees']??[]; $rows=is_array($raw)?array_values($raw):[]; if(count($rows)!==(int)$qty){ wc_add_notice(__('Please enter details for every attendee.','appy-events'),'error'); return false; }
  foreach($rows as $row){ $first=sanitize_text_field(wp_unslash($row['first_name']??'')); $last=sanitize_text_field(wp_unslash($row['last_name']??'')); $email=sanitize_email(wp_unslash($row['email']??'')); $phone=sanitize_text_field(wp_unslash($row['phone']??'')); if(!$first||!$last||!is_email($email)||!$phone){ wc_add_notice(__('First name, last name, email and phone are required for every attendee.','appy-events'),'error'); return false; } }
  return $passed;
 }
 public function check_cart(){ if(!WC()->cart)return; foreach(WC()->cart->get_cart() as $row){ $event=absint($row['appy_event_id']??0); if(!$event)continue; $cap=absint(get_post_meta($event,'_appy_event_capacity',true)); if(Appy_Events_Operations::cancelled($event)||Appy_Events_Operations::is_past($event)||($cap&&Appy_Events_Attendees::count($event)+(int)$row['quantity']>$cap)) wc_add_notice(sprintf(__('Sorry, %s no longer has enough places available.','appy-events'),get_the_title($event)),'error'); } }
 public function cart_data($data,$product_id){
  $event=absint(get_post_meta($product_id,'_appy_event_id',true)); if(!$event)return $data; $data['appy_event_id']=$event; $data['appy_ticket_index']=get_post_meta($product_id,'_appy_event_ticket_index',true);
  $data['appy_attendees']=[]; foreach((array)($_POST['appy_attendees']??[]) as $row) $data['appy_attendees'][]=['first_name'=>sanitize_text_field(wp_unslash($row['first_name']??'')),'last_name'=>sanitize_text_field(wp_unslash($row['last_name']??'')),'email'=>sanitize_email(wp_unslash($row['email']??'')),'phone'=>sanitize_text_field(wp_unslash($row['phone']??''))];
  $data['appy_attendee_key']=wp_generate_uuid4(); return $data;
 }
 public function display_cart_attendees($data,$cart){ if(empty($cart['appy_attendees']))return $data; foreach($cart['appy_attendees'] as $i=>$a) $data[]=['key'=>sprintf(__('Attendee %d','appy-events'),$i+1),'value'=>esc_html(trim($a['first_name'].' '.$a['last_name']).' · '.$a['email'].' · '.$a['phone'])]; return $data; }
 public function order_item($item,$cart_key,$values,$order){ if(empty($values['appy_event_id']))return; $item->add_meta_data('appy_event_id',absint($values['appy_event_id']),true); $item->add_meta_data('appy_ticket_index',sanitize_text_field($values['appy_ticket_index']??'0'),true); $item->add_meta_data('_appy_attendees',$values['appy_attendees']??[],true); }
 public function capture($order_id){
  if(!self::available())return; $order=wc_get_order($order_id); if(!$order)return;
  foreach($order->get_items() as $item){ $event=absint($item->get_meta('appy_event_id')); if(!$event)$event=absint(get_post_meta($item->get_product_id(),'_appy_event_id',true)); if(!$event)continue; $rows=$item->get_meta('_appy_attendees'); if(!is_array($rows))$rows=[];
   foreach($rows as $a){ $email=sanitize_email($a['email']??''); if(!$email)continue; Appy_Events_Attendees::add($event,trim(($a['first_name']??'').' '.($a['last_name']??'')),$email,'paid',$order_id,sanitize_text_field($a['phone']??''),sanitize_text_field($item->get_name())); }
  }
 }
 public function remove_order($order_id){ global $wpdb; $wpdb->delete(Appy_Events_Attendees::table(),['order_id'=>absint($order_id)],['%d']); }
 public static function sync_product($event_id,$price){ if(!self::available())return 0; $pid=absint(get_post_meta($event_id,'_appy_event_product_id',true)); $p=$pid?wc_get_product($pid):new WC_Product_Simple(); if(!$p)$p=new WC_Product_Simple(); $p->set_name(get_the_title($event_id)); $p->set_status('publish'); $p->set_catalog_visibility('hidden'); $p->set_regular_price((string)wc_format_decimal($price)); $p->set_virtual(true); $p->set_sold_individually(false); $pid=$p->save(); update_post_meta($pid,'_appy_event_id',$event_id); update_post_meta($event_id,'_appy_event_product_id',$pid); return $pid; }
}
