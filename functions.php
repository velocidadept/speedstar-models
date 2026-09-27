<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],'0.3.2');});
add_filter('woocommerce_enqueue_styles','__return_empty_array');

function ss_img($id){
 $urls=get_post_meta($id,'_ss_external_gallery',true);
 if(is_array($urls)&&!empty($urls[0])) return $urls[0];
 $url=get_post_meta($id,'_ss_external_image',true);
 if($url) return $url;
 return get_the_post_thumbnail_url($id,'large') ?: '';
}
function ss_gallery($id){$x=get_post_meta($id,'_ss_external_gallery',true);return is_array($x)?$x:array_filter([ss_img($id)]);}
function ss_scales($product){
 $x=get_post_meta($product->get_id(),'_ss_scales',true);
 if(is_array($x)) return $x;
 $values=$product->get_attribute('pa_scale');
 return $values?array_map('trim',explode(',',$values)):[];
}
function ss_money($n){return number_format((float)$n,2,',','');}

/* 0.3.2: mirror the frozen source catalogue into the Playground WooCommerce DB.
   Product text, slugs, categories, image URLs, scale choices and displayed price
   ranges come directly from the source Store API. Images remain remote in dev. */
function ss_import_source_catalogue(){
 if(!class_exists('WooCommerce')) return;
 $version='full-catalogue-v1';
 if(get_option('ss_catalogue_seed_version')===$version) return;

 $base='https://speedstarmodels.com/wp-json/wc/store/v1';
 $response=wp_remote_get($base.'/products?per_page=100&page=1',['timeout'=>45,'headers'=>['Accept'=>'application/json']]);
 if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200) return;
 $items=json_decode(wp_remote_retrieve_body($response),true);
 if(!is_array($items)||!count($items)) return;

 /* Remove only our earlier Playground demo products, never source/production data. */
 $old=wc_get_products(['limit'=>-1,'status'=>['publish','draft','private']]);
 foreach($old as $op){if(get_post_meta($op->get_id(),'_ss_demo_product',true)) wp_delete_post($op->get_id(),true);}

 foreach($items as $x){
   if(empty($x['name'])||empty($x['slug'])) continue;
   $existing=get_page_by_path($x['slug'],OBJECT,'product');
   $p=$existing?wc_get_product($existing->ID):new WC_Product_Simple();
   if(!$p) $p=new WC_Product_Simple();
   $p->set_name(wp_strip_all_tags(html_entity_decode($x['name'],ENT_QUOTES|ENT_HTML5,'UTF-8')));
   $p->set_slug($x['slug']); $p->set_status('publish'); $p->set_catalog_visibility('visible');
   $p->set_sku(!empty($x['sku'])?$x['sku']:'');
   $p->set_description($x['description']??''); $p->set_short_description($x['short_description']??'');
   $price=$x['prices']['price']??'0'; $minor=(int)($x['prices']['currency_minor_unit']??2);
   $p->set_regular_price((string)(((float)$price)/pow(10,$minor)));
   $p->set_stock_status(!empty($x['is_in_stock'])?'instock':'outofstock');
   $catids=[];
   foreach(($x['categories']??[]) as $c){
     $slug=$c['slug']??sanitize_title($c['name']??'');
     $term=get_term_by('slug',$slug,'product_cat');
     if(!$term&&!empty($c['name'])){$made=wp_insert_term($c['name'],'product_cat',['slug'=>$slug]);if(!is_wp_error($made))$term=get_term($made['term_id'],'product_cat');}
     if($term&&!is_wp_error($term))$catids[]=$term->term_id;
   }
   $p->set_category_ids($catids); $id=$p->save();
   update_post_meta($id,'_ss_demo_product',1);
   update_post_meta($id,'_ss_source_id',(int)($x['id']??0));
   update_post_meta($id,'_ss_source_permalink',$x['permalink']??'');
   $imgs=[]; foreach(($x['images']??[]) as $im) if(!empty($im['src'])) $imgs[]=$im['src'];
   update_post_meta($id,'_ss_external_gallery',$imgs); if($imgs) update_post_meta($id,'_ss_external_image',$imgs[0]);
   $range=$x['prices']['price_range']??null;
   $min=$range['min_amount']??$price; $max=$range['max_amount']??$price;
   update_post_meta($id,'_ss_price_min',((float)$min)/pow(10,$minor));
   update_post_meta($id,'_ss_price_max',((float)$max)/pow(10,$minor));
   $scales=[];
   foreach(($x['attributes']??[]) as $a) if(($a['taxonomy']??'')==='pa_scale') foreach(($a['terms']??[]) as $t) if(!empty($t['name'])) $scales[]=$t['name'];
   update_post_meta($id,'_ss_scales',array_values(array_unique($scales)));
 }
 update_option('ss_catalogue_seed_version',$version);
 update_option('ss_catalogue_seed_count',count($items));
 flush_rewrite_rules(false);
}
add_action('wp_loaded','ss_import_source_catalogue',30);
