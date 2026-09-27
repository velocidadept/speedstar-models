<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],'0.3.0');});
add_filter('woocommerce_enqueue_styles','__return_empty_array');

function ss_img($id){
  $url=get_post_meta($id,'_ss_external_image',true);
  if($url) return $url;
  $thumb=get_the_post_thumbnail_url($id,'large');
  return $thumb ?: '';
}
function ss_scales($product){
  $values=$product->get_attribute('pa_scale');
  return $values ? array_map('trim',explode(',',$values)) : [];
}

/* Playground seed catalogue. Runs only when this demo database is empty. */
add_action('wp_loaded',function(){
 if(!class_exists('WooCommerce') || get_option('ss_demo_seed_03')) return;
 if(wc_get_products(['limit'=>1,'status'=>'publish'])){update_option('ss_demo_seed_03',1);return;}
 $cats=['Bodykits','Wheels','Engines','Accessories'];
 foreach($cats as $c){if(!term_exists($c,'product_cat')) wp_insert_term($c,'product_cat',['slug'=>sanitize_title($c)]);}
 $scale_tax='pa_scale';
 if(!taxonomy_exists($scale_tax)){
   $aid=wc_create_attribute(['name'=>'Scale','slug'=>'scale','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);
   delete_transient('wc_attribute_taxonomies');
   register_taxonomy($scale_tax,['product'],['hierarchical'=>false,'show_ui'=>false,'query_var'=>true,'rewrite'=>false]);
 }
 foreach(['1:24','1:18','1:12','1:10','1:8'] as $s){if(!term_exists($s,$scale_tax)) wp_insert_term($s,$scale_tax,['slug'=>str_replace(':','',$s)]);}
 $items=[
 ['Nissan Silvia S13 Spirit Rei Miyabi Widebody Kit','bodykits',['1:24','1:18'],30,45,'https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/11/il_794xN.6924719895_12ly.jpg?fit=794%2C528&ssl=1','Complete resin widebody conversion for the Nissan Silvia S13 Tamiya model kit.'],
 ['Enkei GTC02 19” with Sport Tires','wheels',['1:24','1:18'],15,20,'https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/12/enkei_gtc02.jpg?resize=900%2C900&ssl=1','Resin wheel and sport-tire set with detailed split-spoke design.'],
 ['Ford Cosworth V8 DFV F1 Engine Resin','engines',['1:24','1:18','1:12','1:10','1:8'],25,75,'https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/11/il_794xN.6164545748_6blb.jpg?fit=794%2C818&ssl=1','High-resolution resin engine kit with separate parts for painting, exhaust manifolds, injection system, clutch and intake trumpets.'],
 ['Mazda RX-7 Time Attack Bodykit for Tamiya','bodykits',['1:24','1:18'],30,45,'https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/11/il_794xN.6876814250_kkcg.jpg?fit=794%2C794&ssl=1','Complete Time Attack resin bodykit with aero components, wheels and tyres for the Mazda RX-7.']
 ];
 foreach($items as $it){
   $p=new WC_Product_Variable(); $p->set_name($it[0]); $p->set_status('publish'); $p->set_catalog_visibility('visible');
   $p->set_description($it[6]); $p->set_short_description($it[6]); $p->set_category_ids([get_term_by('slug',$it[1],'product_cat')->term_id]);
   $p->set_regular_price((string)$it[3]);
   $id=$p->save(); update_post_meta($id,'_ss_external_image',$it[5]); update_post_meta($id,'_ss_price_min',$it[3]); update_post_meta($id,'_ss_price_max',$it[4]);
   wp_set_object_terms($id,$it[2],$scale_tax);
   $attr=new WC_Product_Attribute(); $attr->set_id(wc_attribute_taxonomy_id_by_name('scale')); $attr->set_name($scale_tax); $attr->set_options(wp_get_object_terms($id,$scale_tax,['fields'=>'ids'])); $attr->set_visible(true); $attr->set_variation(true); $p->set_attributes([$attr]); $p->save();
   foreach($it[2] as $i=>$sc){$v=new WC_Product_Variation();$v->set_parent_id($id);$v->set_attributes(['pa_scale'=>str_replace(':','',$sc)]);$price=count($it[2])>1?round($it[3]+(($it[4]-$it[3])*$i/(count($it[2])-1)),2):$it[3];$v->set_regular_price((string)$price);$v->set_stock_status('instock');$v->save();}
 }
 update_option('ss_demo_seed_03',1); flush_rewrite_rules();
},20);
