<?php
defined('ABSPATH') || exit;
class Appy_Events_Public {
 public function __construct(){ add_shortcode('appy_events',[$this,'listing']); add_filter('the_content',[$this,'event_content'],8); add_action('wp_enqueue_scripts',[$this,'assets']); }
 public function assets(){ if(is_singular('appy_event')) wp_enqueue_style('appy-events',APPY_EVENTS_URL.'assets/css/appy-events.css',[],APPY_EVENTS_VERSION); }
 private function style(){ wp_enqueue_style('appy-events',APPY_EVENTS_URL.'assets/css/appy-events.css',[],APPY_EVENTS_VERSION); }
 private function fmt($v,$with_time=true){ return $v ? wp_date(get_option('date_format').($with_time?' '.get_option('time_format'):''),strtotime($v)) : ''; }
 public function listing($atts){
  $this->style();
  $a=shortcode_atts(['past'=>'no','limit'=>12],$atts,'appy_events'); $past='yes'===$a['past']; $now=current_time('Y-m-d\TH:i');
  $q=new WP_Query(['post_type'=>'appy_event','post_status'=>'publish','posts_per_page'=>max(1,absint($a['limit'])),'meta_key'=>'_appy_event_start','orderby'=>'meta_value','order'=>$past?'DESC':'ASC','meta_query'=>[['key'=>'_appy_event_start','value'=>$now,'compare'=>$past?'<':'>=','type'=>'CHAR']]]);
  ob_start(); echo '<div class="appy-public-events">';
  if(!$q->have_posts()) echo '<div class="appy-events-empty">'.esc_html($past?__('No past events.','appy-events'):__('No upcoming events.','appy-events')).'</div>';
  while($q->have_posts()){ $q->the_post(); $id=get_the_ID(); if(Appy_Events_Operations::cancelled($id)) continue; $start=get_post_meta($id,'_appy_event_start',true); $loc=get_post_meta($id,'_appy_event_location',true); $cap=absint(get_post_meta($id,'_appy_event_capacity',true)); $n=Appy_Events_Attendees::count($id); ?>
   <article class="appy-public-card"><?php if(has_post_thumbnail()) echo '<a class="appy-public-card-image" href="'.esc_url(get_permalink()).'">'.get_the_post_thumbnail($id,'large').'</a>'; else echo '<a class="appy-public-card-image appy-no-image" href="'.esc_url(get_permalink()).'"><span>'.esc_html__('Event','appy-events').'</span></a>'; ?>
   <div class="appy-public-card-body"><span class="appy-events-kicker"><?php echo esc_html($this->fmt($start)); ?></span><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
   <?php if($loc): ?><p><?php echo esc_html($loc); ?></p><?php endif; ?><div class="appy-public-card-foot"><span><?php echo esc_html($cap?sprintf(__('%d of %d booked','appy-events'),$n,$cap):sprintf(__('%d booked','appy-events'),$n)); ?></span><a class="appy-events-button is-secondary" href="<?php the_permalink(); ?>"><?php esc_html_e('View event','appy-events'); ?></a></div></div></article>
  <?php } wp_reset_postdata(); echo '</div>'; return ob_get_clean();
 }
 public function event_content($content){
  if(!is_singular('appy_event')||!in_the_loop()||!is_main_query()) return $content; $id=get_the_ID();
  $start=get_post_meta($id,'_appy_event_start',true); $cancelled=Appy_Events_Operations::cancelled($id); $past=Appy_Events_Operations::is_past($id); $end=get_post_meta($id,'_appy_event_end',true); $loc=get_post_meta($id,'_appy_event_location',true); $cap=absint(get_post_meta($id,'_appy_event_capacity',true)); $n=Appy_Events_Attendees::count($id);
  ob_start(); ?><div class="appy-event-public"><?php if(has_post_thumbnail()): ?><div class="appy-event-hero"><?php the_post_thumbnail('large'); ?></div><?php endif; ?>
  <?php if($cancelled): ?><div class="appy-rsvp-notice is-full appy-event-state"><strong><?php esc_html_e('This event has been cancelled.','appy-events'); ?></strong></div><?php elseif($past): ?><div class="appy-rsvp-notice appy-event-state"><strong><?php esc_html_e('This event has finished.','appy-events'); ?></strong></div><?php endif; ?><div class="appy-event-facts"><div><strong><?php esc_html_e('When','appy-events'); ?></strong><span><?php echo esc_html($this->fmt($start)); ?><?php if($end) echo ' – '.esc_html($this->fmt($end)); ?></span></div>
  <?php if($loc): ?><div><strong><?php esc_html_e('Where','appy-events'); ?></strong><span><?php echo esc_html($loc); ?></span></div><?php endif; ?>
  <div><strong><?php esc_html_e('Availability','appy-events'); ?></strong><span><?php echo esc_html($cap?sprintf(__('%d places left','appy-events'),max(0,$cap-$n)):__('Unlimited places','appy-events')); ?></span></div></div></div><?php
  $before=ob_get_clean();
  if (!$cancelled && !$past && 'paid' === get_post_meta($id,'_appy_event_type',true) && Appy_Events_WooCommerce::available()) {
   $pid=absint(get_post_meta($id,'_appy_event_product_id',true)); $price=get_post_meta($id,'_appy_event_price',true);
   if($pid && (!$cap || $n<$cap)) $content .= '<div class="appy-paid-ticket"><span class="appy-events-kicker">'.esc_html__('Paid event','appy-events').'</span><h3>'.esc_html__('Book your ticket','appy-events').'</h3><strong>'.wp_kses_post(wc_price($price)).'</strong><a class="appy-events-button" href="'.esc_url(add_query_arg('add-to-cart',$pid,wc_get_cart_url())).'">'.esc_html__('Buy ticket','appy-events').'</a></div>';
   elseif($cap && $n >= $cap) $content .= '<div class="appy-rsvp-notice is-full"><strong>'.esc_html__('This event is sold out.','appy-events').'</strong></div>';
  }
  return $before.$content;
 }
}
