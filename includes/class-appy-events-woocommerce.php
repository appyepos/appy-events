<?php
defined('ABSPATH') || exit;
class Appy_Events_WooCommerce {
 public function __construct(){ add_action('woocommerce_order_status_processing',[$this,'capture']); add_action('woocommerce_order_status_completed',[$this,'capture']); add_action('woocommerce_order_status_on-hold',[$this,'capture']); add_filter('woocommerce_add_cart_item_data',[$this,'cart_data'],10,2); }
 public static function available(){ return class_exists('WooCommerce'); }
 public function cart_data($data,$product_id){ $event=absint(get_post_meta($product_id,'_appy_event_id',true)); if($event) $data['appy_event_id']=$event; return $data; }
 public function capture($order_id){ if(!self::available()) return; $order=wc_get_order($order_id); if(!$order) return; foreach($order->get_items() as $item){ $event=absint($item->get_meta('appy_event_id')); if(!$event) $event=absint(get_post_meta($item->get_product_id(),'_appy_event_id',true)); if(!$event) continue; $email=$order->get_billing_email(); if($email&&!Appy_Events_Attendees::email_exists($event,$email)) Appy_Events_Attendees::add($event,trim($order->get_billing_first_name().' '.$order->get_billing_last_name()),$email,'paid',$order_id); } }
 public static function sync_product($event_id,$price){
  if(!self::available()) return 0; $pid=absint(get_post_meta($event_id,'_appy_event_product_id',true)); $p=$pid?wc_get_product($pid):new WC_Product_Simple();
  if(!$p) $p=new WC_Product_Simple(); $p->set_name(get_the_title($event_id)); $p->set_status('publish'); $p->set_catalog_visibility('hidden'); $p->set_regular_price((string)wc_format_decimal($price)); $p->set_virtual(true); $pid=$p->save(); update_post_meta($pid,'_appy_event_id',$event_id); update_post_meta($event_id,'_appy_event_product_id',$pid); return $pid;
 }
}
