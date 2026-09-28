<?php
/**
 * Plugin Name: Speedstar Core
 * Description: Speedstar Models operational tools: secure CTT tracking and non-fiscal order/packing-slip PDFs.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

final class Speedstar_Core {
 const TRACKING_META='_ss_ctt_tracking';
 const PDF_META='_ss_packing_slip_file';
 public static function init(){
  add_action('before_woocommerce_init',[__CLASS__,'declare_hpos']);
  add_action('add_meta_boxes',[__CLASS__,'add_meta_box']);
  add_action('woocommerce_process_shop_order_meta',[__CLASS__,'save_tracking'],20,2);
  add_action('wp_ajax_ss_generate_packing_slip',[__CLASS__,'generate_pdf_action']);
  add_action('wp_ajax_ss_download_packing_slip',[__CLASS__,'download_pdf_action']);
  add_action('woocommerce_order_details_after_order_table',[__CLASS__,'customer_tracking']);
  add_filter('woocommerce_my_account_my_orders_columns',[__CLASS__,'account_column']);
  add_action('woocommerce_my_account_my_orders_column_ss-tracking',[__CLASS__,'account_tracking_cell']);
  add_filter('manage_edit-shop_order_columns',[__CLASS__,'legacy_order_column']);
  add_action('manage_shop_order_posts_custom_column',[__CLASS__,'legacy_order_cell'],10,2);
  add_filter('manage_woocommerce_page_wc-orders_columns',[__CLASS__,'hpos_order_column']);
  add_action('manage_woocommerce_page_wc-orders_custom_column',[__CLASS__,'hpos_order_cell'],10,2);
  add_action('woocommerce_email_after_order_table',[__CLASS__,'email_tracking'],20,4);
 }
 public static function declare_hpos(){
  if(class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);
 }
 private static function order_from_post($value){
  if($value instanceof WC_Order)return $value;
  $id=is_object($value)&&isset($value->ID)?absint($value->ID):absint($value);
  return $id?wc_get_order($id):false;
 }
 public static function add_meta_box(){
  $screens=['shop_order'];if(function_exists('wc_get_page_screen_id'))$screens[]=wc_get_page_screen_id('shop-order');
  foreach(array_unique($screens) as $screen)add_meta_box('ss-order-ops','Speedstar Operations',[__CLASS__,'meta_box'],$screen,'side','high');
 }
 public static function meta_box($value){
  $o=self::order_from_post($value);if(!$o)return;$id=$o->get_id();$t=$o->get_meta(self::TRACKING_META);$pdf=$o->get_meta(self::PDF_META);
  wp_nonce_field('ss_order_ops_'.$id,'ss_order_ops_nonce');
  echo '<p><label for="ss_ctt_tracking"><strong>CTT Tracking Number</strong></label></p><input style="width:100%" type="text" id="ss_ctt_tracking" name="ss_ctt_tracking" value="'.esc_attr($t).'" placeholder="e.g. RD123456789PT">';
  if($t)echo '<p><a class="button" target="_blank" rel="noopener noreferrer" href="'.esc_url(self::tracking_url($t)).'">Track shipment ↗</a></p>';
  $gen=wp_nonce_url(admin_url('admin-ajax.php?action=ss_generate_packing_slip&order_id='.$id),'ss_generate_pdf_'.$id);
  echo '<hr><p><strong>Order / Packing Slip</strong><br><small>Non-fiscal document. Not a tax invoice.</small></p><p><a class="button button-primary" href="'.esc_url($gen).'">'.($pdf?'Regenerate PDF':'Generate PDF').'</a></p>';
  if($pdf){$dl=wp_nonce_url(admin_url('admin-ajax.php?action=ss_download_packing_slip&order_id='.$id),'ss_download_pdf_'.$id);echo '<p><a class="button" href="'.esc_url($dl).'">View / Print PDF ↗</a></p>';}
 }
 public static function save_tracking($order_id,$unused=null){
  if(empty($_POST['ss_order_ops_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ss_order_ops_nonce'])),'ss_order_ops_'.$order_id))return;
  if(!current_user_can('edit_shop_order',$order_id))return;$o=wc_get_order($order_id);if(!$o)return;
  $t=isset($_POST['ss_ctt_tracking'])?strtoupper(preg_replace('/[^A-Za-z0-9]/','',sanitize_text_field(wp_unslash($_POST['ss_ctt_tracking'])))):'';
  $o->update_meta_data(self::TRACKING_META,$t);$o->save();
 }
 public static function tracking_url($t){return 'https://ctt.pt/t/'.rawurlencode(trim($t));}
 public static function customer_tracking($order_id){$o=wc_get_order($order_id);if(!$o)return;$t=$o->get_meta(self::TRACKING_META);if(!$t)return;echo '<section class="woocommerce-order-details ss-order-tracking"><h2>Shipment tracking</h2><p><strong>CTT:</strong> '.esc_html($t).' &nbsp; <a class="button" target="_blank" rel="noopener noreferrer" href="'.esc_url(self::tracking_url($t)).'">Track shipment</a></p></section>';}
 public static function account_column($cols){$out=[];foreach($cols as $k=>$v){$out[$k]=$v;if($k==='order-status')$out['ss-tracking']='Tracking';}return $out;}
 public static function account_tracking_cell($o){$t=$o->get_meta(self::TRACKING_META);echo $t?'<a target="_blank" rel="noopener noreferrer" href="'.esc_url(self::tracking_url($t)).'">'.esc_html($t).'</a>':'—';}
 public static function legacy_order_column($cols){$cols['ss_tracking']='Tracking';return $cols;}
 public static function legacy_order_cell($col,$id){if($col==='ss_tracking')self::admin_tracking_cell(wc_get_order($id));}
 public static function hpos_order_column($cols){$cols['ss_tracking']='Tracking';return $cols;}
 public static function hpos_order_cell($col,$o){if($col==='ss_tracking')self::admin_tracking_cell(self::order_from_post($o));}
 private static function admin_tracking_cell($o){if(!$o){echo '—';return;}$t=$o->get_meta(self::TRACKING_META);echo $t?'<a target="_blank" rel="noopener noreferrer" href="'.esc_url(self::tracking_url($t)).'">'.esc_html($t).'</a>':'—';}
 public static function email_tracking($o,$admin,$plain,$email){if($admin||!$o)return;$t=$o->get_meta(self::TRACKING_META);if(!$t)return;if($plain)echo "\nCTT tracking: ".$t."\n".self::tracking_url($t)."\n";else echo '<p><strong>CTT tracking:</strong> <a href="'.esc_url(self::tracking_url($t)).'">'.esc_html($t).'</a></p>';}
 private static function storage_dir(){
  $u=wp_upload_dir();if(!empty($u['error']))return new WP_Error('upload_dir',$u['error']);
  $dir=trailingslashit($u['basedir']).'speedstar-private';
  if(!wp_mkdir_p($dir))return new WP_Error('mkdir','Unable to create document directory.');
  if(!file_exists($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n");
  if(!file_exists($dir.'/index.php'))@file_put_contents($dir.'/index.php',"<?php exit;\n");
  return $dir;
 }
 private static function pdf_path($o){$stored=$o->get_meta(self::PDF_META);if(!$stored)return false;$real=realpath($stored);$dir=self::storage_dir();if(is_wp_error($dir)||!$real)return false;$base=realpath($dir);return $base&&str_starts_with($real,$base.DIRECTORY_SEPARATOR)?$real:false;}
 public static function generate_pdf_action(){
  $id=absint($_GET['order_id']??0);check_admin_referer('ss_generate_pdf_'.$id);if(!$id||!current_user_can('edit_shop_order',$id))wp_die('Not allowed.',403);
  $o=wc_get_order($id);if(!$o)wp_die('Order not found.',404);$dir=self::storage_dir();if(is_wp_error($dir))wp_die(esc_html($dir->get_error_message()),500);
  $file=$dir.'/packing-slip-'.$id.'-'.wp_generate_password(20,false,false).'.pdf';$bytes=file_put_contents($file,self::build_pdf($o),LOCK_EX);
  if($bytes===false)wp_die('Could not write PDF.',500);$old=self::pdf_path($o);if($old&&$old!==$file)@unlink($old);
  $o->update_meta_data(self::PDF_META,$file);$o->save();wp_safe_redirect(get_edit_post_link($id,'raw')?:admin_url('admin.php?page=wc-orders&action=edit&id='.$id));exit;
 }
 public static function download_pdf_action(){
  $id=absint($_GET['order_id']??0);check_admin_referer('ss_download_pdf_'.$id);if(!$id||!current_user_can('edit_shop_order',$id))wp_die('Not allowed.',403);
  $o=wc_get_order($id);$file=$o?self::pdf_path($o):false;if(!$file||!is_readable($file))wp_die('PDF not found.',404);
  nocache_headers();header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="speedstar-order-'.absint($id).'-packing-slip.pdf"');header('Content-Length: '.filesize($file));readfile($file);exit;
 }
 private static function clean($s){$s=wp_strip_all_tags((string)$s);$s=html_entity_decode($s,ENT_QUOTES|ENT_HTML5,'UTF-8');return function_exists('iconv')?(iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s)?:$s):$s;}
 private static function item_variants($item){
  $parts=[];foreach($item->get_formatted_meta_data('') as $m){$key=wc_attribute_label($m->key,$item->get_product());$val=wp_strip_all_tags($m->display_value);if($key&&$val)$parts[]=$key.': '.$val;}return $parts;
 }
 private static function lines_for_order($o){
  $L=['SPEEDSTAR MODELS','ORDER / PACKING SLIP','This document is not a tax invoice.','',sprintf('Order #%s   Date: %s',$o->get_order_number(),wc_format_datetime($o->get_date_created(),'Y-m-d')),'','SHIP TO:'];
  foreach([$o->get_shipping_first_name().' '.$o->get_shipping_last_name(),$o->get_shipping_company(),$o->get_shipping_address_1(),$o->get_shipping_address_2(),trim($o->get_shipping_postcode().' '.$o->get_shipping_city()),$o->get_shipping_state(),$o->get_shipping_country()] as $x)if(trim($x)!=='')$L[]=trim($x);
  $L[]='';$L[]='ITEMS:';
  foreach($o->get_items() as $item){$L[]=$item->get_quantity().' x '.$item->get_name();foreach(self::item_variants($item) as $v)$L[]='    '.$v;}
  $L[]='';foreach($o->get_shipping_methods() as $ship)$L[]='Shipping: '.$ship->get_name();
  $L[]='Total: '.wp_strip_all_tags($o->get_formatted_order_total());$t=$o->get_meta(self::TRACKING_META);if($t)$L[]='CTT tracking: '.$t;return $L;
 }
 private static function pdf_escape($s){return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);}
 private static function build_pdf($o){
  $lines=self::lines_for_order($o);$chunks=array_chunk($lines,45);$objs=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];$pageIds=[];$contentIds=[];$next=4;
  foreach($chunks as $_){$pageIds[]=$next++;$contentIds[]=$next++;}$kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));$objs[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';
  foreach($chunks as $i=>$chunk){$stream="BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";foreach($chunk as $line)$stream.='('.self::pdf_escape(self::clean($line)).") Tj\nT*\n";$stream.="ET";$objs[$pageIds[$i]]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentIds[$i].' 0 R >>';$objs[$contentIds[$i]]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";}
  ksort($objs);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offset=[0=>0];foreach($objs as $id=>$body){$offset[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}$xref=strlen($pdf);$max=max(array_keys($objs));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ',$offset[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
 }
}
add_action('plugins_loaded',['Speedstar_Core','init']);
