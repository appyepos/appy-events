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
  if(!is_singular('appy_event')||!in_the_loop()||!is_main_query()) return $content;
  $id=get_the_ID(); $start=get_post_meta($id,'_appy_event_start',true); $end=get_post_meta($id,'_appy_event_end',true); $loc=get_post_meta($id,'_appy_event_location',true);
  $cap=absint(get_post_meta($id,'_appy_event_capacity',true)); $n=Appy_Events_Attendees::count($id); $cancelled=Appy_Events_Operations::cancelled($id); $past=Appy_Events_Operations::is_past($id); $type=get_post_meta($id,'_appy_event_type',true)?:'free';
  ob_start(); ?>
  <div class="appy-single-event">
   <div class="appy-single-main">
    <?php if(has_post_thumbnail()): ?><div class="appy-event-hero"><?php the_post_thumbnail('large'); ?></div><?php endif; ?>
    <div class="appy-event-facts">
     <div><strong><?php esc_html_e('When','appy-events'); ?></strong><span><?php echo esc_html($this->fmt($start)); ?><?php if($end) echo ' – '.esc_html($this->fmt($end)); ?></span></div>
     <?php if($loc): ?><div><strong><?php esc_html_e('Where','appy-events'); ?></strong><span><?php echo esc_html($loc); ?></span></div><?php endif; ?>
     <div><strong><?php esc_html_e('Availability','appy-events'); ?></strong><span><?php echo esc_html($cap?sprintf(__('%d places left','appy-events'),max(0,$cap-$n)):__('Unlimited places','appy-events')); ?></span></div>
    </div>
    <div class="appy-event-description"><?php echo $content; ?></div>
   </div>
   <aside class="appy-single-booking">
    <?php if($cancelled): ?><div class="appy-rsvp-notice is-full"><strong><?php esc_html_e('This event has been cancelled.','appy-events'); ?></strong></div>
    <?php elseif($past): ?><div class="appy-rsvp-notice"><strong><?php esc_html_e('This event has finished.','appy-events'); ?></strong></div>
    <?php elseif('paid'===$type && Appy_Events_WooCommerce::available()): $types=Appy_Events_Tickets::get($id); ?>
      <div class="appy-paid-ticket"><span class="appy-events-kicker"><?php esc_html_e('Tickets','appy-events'); ?></span><h3><?php esc_html_e('Book your tickets','appy-events'); ?></h3>
      <?php foreach($types as $i=>$ticket): $pid=absint($ticket['product_id']??0); if(!$pid) continue; $tcap=absint($ticket['capacity']??0); $booked=$tcap?Appy_Events_Tickets::booked($id,$i):0; $left=$tcap?max(0,$tcap-$booked):99; ?>
       <form class="appy-ticket-row appy-attendee-ticket-form" method="post" action="<?php echo esc_url(wc_get_cart_url()); ?>"><input type="hidden" name="add-to-cart" value="<?php echo esc_attr($pid); ?>">
        <div><strong><?php echo esc_html($ticket['name']); ?></strong><span><?php echo wp_kses_post(wc_price($ticket['price'])); ?><?php if($tcap): ?> · <?php echo esc_html(sprintf(__('%d left','appy-events'),$left)); ?><?php endif; ?></span></div>
        <?php if($left>0): ?><div class="appy-attendee-capture"><p class="appy-attendee-help"><?php esc_html_e('Enter the details for each person attending.','appy-events'); ?></p><div class="appy-attendee-fields"><div class="appy-attendee-person"><strong><?php esc_html_e('Attendee 1','appy-events'); ?></strong><div class="appy-attendee-person-grid"><label><?php esc_html_e('First name','appy-events'); ?><input required name="appy_attendees[0][first_name]"></label><label><?php esc_html_e('Last name','appy-events'); ?><input required name="appy_attendees[0][last_name]"></label><label><?php esc_html_e('Email','appy-events'); ?><input required type="email" name="appy_attendees[0][email]"></label><label><?php esc_html_e('Phone','appy-events'); ?><input required type="tel" name="appy_attendees[0][phone]"></label></div></div></div></div><div class="appy-ticket-buy"><label><?php esc_html_e('Qty','appy-events'); ?><input type="number" name="quantity" min="1" max="<?php echo esc_attr(min(10,$left)); ?>" value="1"></label><button class="appy-events-button" type="submit"><?php esc_html_e('Continue','appy-events'); ?></button></div><?php else: ?><span class="appy-sold-out"><?php esc_html_e('Sold out','appy-events'); ?></span><?php endif; ?>
       </form>
      <?php endforeach; ?></div>
    <?php else: echo do_shortcode('[appy_event_rsvp id="'.$id.'"]'); endif; ?>
   </aside>
  </div>
  <script>
document.querySelectorAll('.appy-attendee-ticket-form').forEach(function(form){
 const qty=form.querySelector('input[name="quantity"]'),wrap=form.querySelector('.appy-attendee-fields'); if(!qty||!wrap)return;
 function draw(){let n=parseInt(qty.value||1,10),vals=[];wrap.querySelectorAll('.appy-attendee-person').forEach(function(p){vals.push(Array.from(p.querySelectorAll('input')).map(function(i){return i.value}))});wrap.innerHTML='';
 for(let i=0;i<n;i++){let v=vals[i]||['','','',''];let d=document.createElement('div');d.className='appy-attendee-person';d.innerHTML='<strong>Attendee '+(i+1)+'</strong><div class="appy-attendee-person-grid"><label>First name<input required name="appy_attendees['+i+'][first_name]"></label><label>Last name<input required name="appy_attendees['+i+'][last_name]"></label><label>Email<input required type="email" name="appy_attendees['+i+'][email]"></label><label>Phone<input required type="tel" name="appy_attendees['+i+'][phone]"></label></div>';let ins=d.querySelectorAll('input');ins.forEach(function(x,j){x.value=v[j]||''});wrap.appendChild(d)}}
 qty.addEventListener('input',draw);draw();
});
</script>
<?php return ob_get_clean();
 }
}
