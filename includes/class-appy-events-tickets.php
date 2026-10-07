<?php
defined('ABSPATH') || exit;
class Appy_Events_Tickets {
 public static function get($event_id){
  $rows=get_post_meta($event_id,'_appy_event_ticket_types',true);
  if(is_array($rows)&&$rows) return array_values($rows);
  $price=(float)get_post_meta($event_id,'_appy_event_price',true);
  $pid=absint(get_post_meta($event_id,'_appy_event_product_id',true));
  return $price>0 ? [['name'=>__('General Admission','appy-events'),'price'=>$price,'capacity'=>0,'product_id'=>$pid]] : [];
 }
 public static function sanitize($names,$prices,$caps){
  $out=[]; foreach((array)$names as $i=>$name){ $name=sanitize_text_field(wp_unslash($name)); $price=max(0,(float)($prices[$i]??0)); $cap=absint($caps[$i]??0); if($name!==''&&$price>=0) $out[]=['name'=>$name,'price'=>$price,'capacity'=>$cap,'product_id'=>0]; } return $out;
 }
 public static function sync($event_id,$types){
  if(!Appy_Events_WooCommerce::available()) return $types;
  $old=self::get($event_id);
  foreach($types as $i=>&$type){
   $pid=absint($old[$i]['product_id']??0); $p=$pid?wc_get_product($pid):new WC_Product_Simple(); if(!$p)$p=new WC_Product_Simple();
   $p->set_name(get_the_title($event_id).' – '.$type['name']); $p->set_status('publish'); $p->set_catalog_visibility('hidden'); $p->set_regular_price((string)wc_format_decimal($type['price'])); $p->set_virtual(true); $p->set_sold_individually(false); $p->set_manage_stock(false); $pid=$p->save();
   update_post_meta($pid,'_appy_event_id',$event_id); update_post_meta($pid,'_appy_event_ticket_index',$i); $type['product_id']=$pid;
  } unset($type);
  for($i=count($types);$i<count($old);$i++){ $pid=absint($old[$i]['product_id']??0); if($pid&&'product'===get_post_type($pid)) wp_delete_post($pid,true); }
  update_post_meta($event_id,'_appy_event_ticket_types',$types);
  if(isset($types[0])){ update_post_meta($event_id,'_appy_event_price',$types[0]['price']); update_post_meta($event_id,'_appy_event_product_id',$types[0]['product_id']); }
  return $types;
 }
 public static function booked($event_id,$index){
  if(!function_exists('wc_get_orders')) return 0; $total=0;
  $orders=wc_get_orders(['limit'=>-1,'status'=>['wc-processing','wc-completed','wc-on-hold']]);
  foreach($orders as $order) foreach($order->get_items() as $item) if(absint($item->get_meta('appy_event_id'))===$event_id && (string)$item->get_meta('appy_ticket_index')===(string)$index) $total+=(int)$item->get_quantity();
  return $total;
 }
}
