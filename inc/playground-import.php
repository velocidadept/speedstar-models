<?php
/* Playground-only catalogue mirror. Never loaded on the production storefront. */
function ss_source_get($url){
 $r=wp_remote_get($url,['timeout'=>45,'headers'=>['Accept'=>'application/json']]);
 if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return [];
 $x=json_decode(wp_remote_retrieve_body($r),true);
 return is_array($x)?$x:[];
}
function ss_source_products($base){
 $all=[];$page=1;
 do{$batch=ss_source_get($base.'/products?per_page=100&page='.$page);if(!$batch)break;$all=array_merge($all,$batch);$page++;}while(count($batch)===100&&$page<=20);
 return $all;
}
function ss_source_variations($base){
 $all=[];$page=1;
 do{$batch=ss_source_get($base.'/products?type=variation&per_page=100&page='.$page);if(!$batch)break;$all=array_merge($all,$batch);$page++;}while(count($batch)===100&&$page<=30);
 return $all;
}
function ss_money_from_api($amount,$minor){return ((float)$amount)/pow(10,(int)$minor);}
function ss_ensure_scale_taxonomy($scales){
 global $wpdb;
 $attr_id=wc_attribute_taxonomy_id_by_name('scale');
 if(!$attr_id){$attr_id=wc_create_attribute(['name'=>'Scale','slug'=>'scale','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);delete_transient('wc_attribute_taxonomies');}
 if(!taxonomy_exists('pa_scale')) register_taxonomy('pa_scale','product',['hierarchical'=>false,'label'=>'Scale','query_var'=>true,'rewrite'=>false,'public'=>false,'show_ui'=>false]);
 foreach($scales as $name){if(!term_exists($name,'pa_scale'))wp_insert_term($name,'pa_scale',['slug'=>sanitize_title($name)]);}
 return $attr_id;
}
function ss_import_source_catalogue(){
 if(!class_exists('WooCommerce'))return;
 $version='full-catalogue-v2-variable';
 if(get_option('ss_catalogue_seed_version')===$version)return;
 $base='https://speedstarmodels.com/wp-json/wc/store/v1';
 $items=ss_source_products($base); if(!$items)return;
 $variations=ss_source_variations($base);
 $vars_by_parent=[];
 foreach($variations as $v){$pid=(int)($v['parent']??0);if($pid)$vars_by_parent[$pid][]=$v;}
 $old=wc_get_products(['limit'=>-1,'status'=>['publish','draft','private']]);
 foreach($old as $op){if(get_post_meta($op->get_id(),'_ss_playground_product',true))wp_delete_post($op->get_id(),true);}
 foreach($items as $x){
  if(empty($x['name'])||empty($x['slug']))continue;
  $source_id=(int)($x['id']??0);$scales=[];
  foreach(($x['attributes']??[]) as $a)if(($a['taxonomy']??'')==='pa_scale')foreach(($a['terms']??[]) as $t)if(!empty($t['name']))$scales[]=$t['name'];
  $scales=array_values(array_unique($scales)); if($scales)ss_ensure_scale_taxonomy($scales);
  $p=$scales?new WC_Product_Variable():new WC_Product_Simple();
  $p->set_name(wp_strip_all_tags(html_entity_decode($x['name'],ENT_QUOTES|ENT_HTML5,'UTF-8')));$p->set_slug($x['slug']);$p->set_status('publish');$p->set_catalog_visibility('visible');
  if(!empty($x['sku']))$p->set_sku($x['sku']);$p->set_description($x['description']??'');$p->set_short_description($x['short_description']??'');
  $p->set_stock_status(!empty($x['is_in_stock'])?'instock':'outofstock');
  $catids=[];foreach(($x['categories']??[]) as $cat){$slug=$cat['slug']??sanitize_title($cat['name']??'');$term=get_term_by('slug',$slug,'product_cat');if(!$term&&!empty($cat['name'])){$made=wp_insert_term($cat['name'],'product_cat',['slug'=>$slug]);if(!is_wp_error($made))$term=get_term($made['term_id'],'product_cat');}if($term&&!is_wp_error($term))$catids[]=$term->term_id;}$p->set_category_ids($catids);
  if($scales){$term_ids=[];foreach($scales as $scale){$term=get_term_by('name',$scale,'pa_scale');if($term)$term_ids[]=$term->term_id;} $attr=new WC_Product_Attribute();$attr->set_id(wc_attribute_taxonomy_id_by_name('scale'));$attr->set_name('pa_scale');$attr->set_options($term_ids);$attr->set_position(0);$attr->set_visible(true);$attr->set_variation(true);$p->set_attributes([$attr]);}
  else{$price=$x['prices']['price']??'0';$minor=(int)($x['prices']['currency_minor_unit']??2);$p->set_regular_price((string)ss_money_from_api($price,$minor));}
  $id=$p->save();update_post_meta($id,'_ss_playground_product',1);update_post_meta($id,'_ss_source_id',$source_id);update_post_meta($id,'_ss_source_permalink',$x['permalink']??'');
  $imgs=[];foreach(($x['images']??[]) as $im)if(!empty($im['src']))$imgs[]=$im['src'];update_post_meta($id,'_ss_external_gallery',$imgs);if($imgs)update_post_meta($id,'_ss_external_image',$imgs[0]);
  $price=$x['prices']['price']??'0';$minor=(int)($x['prices']['currency_minor_unit']??2);$range=$x['prices']['price_range']??[];$min=$range['min_amount']??$price;$max=$range['max_amount']??$price;update_post_meta($id,'_ss_price_min',ss_money_from_api($min,$minor));update_post_meta($id,'_ss_price_max',ss_money_from_api($max,$minor));update_post_meta($id,'_ss_scales',$scales);
  if($scales){
   $source_vars=$vars_by_parent[$source_id]??[];$created=0;
   foreach($source_vars as $sv){$scale='';foreach(($sv['attributes']??[]) as $a)if(($a['taxonomy']??'')==='pa_scale')foreach(($a['terms']??[]) as $t)if(!empty($t['name'])){$scale=$t['name'];break 2;}if(!$scale)continue;$vp=new WC_Product_Variation();$vp->set_parent_id($id);$vp->set_attributes(['pa_scale'=>sanitize_title($scale)]);$vminor=(int)($sv['prices']['currency_minor_unit']??$minor);$vprice=ss_money_from_api($sv['prices']['price']??$min,$vminor);$vp->set_regular_price((string)$vprice);$vp->set_stock_status(!empty($sv['is_in_stock'])?'instock':'outofstock');$vid=$vp->save();update_post_meta($vid,'_ss_source_variation_id',(int)($sv['id']??0));$created++;}
   if(!$created)update_post_meta($id,'_ss_variations_unresolved',1);else delete_post_meta($id,'_ss_variations_unresolved');
   WC_Product_Variable::sync($id);
  }
 }
 update_option('ss_catalogue_seed_version',$version);update_option('ss_catalogue_seed_count',count($items));update_option('ss_catalogue_variation_count',count($variations));flush_rewrite_rules(false);
}
add_action('wp_loaded','ss_import_source_catalogue',30);


