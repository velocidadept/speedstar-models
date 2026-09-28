<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],'0.5.0');wp_enqueue_script('speedstar-storefront',get_template_directory_uri().'/assets/js/storefront.js',[],'0.5.0',true);if(is_product())wp_enqueue_script('wc-add-to-cart-variation');});
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

/* 0.4: paginated catalogue mirror with native WooCommerce variable products. */
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
 foreach($old as $op){if(get_post_meta($op->get_id(),'_ss_demo_product',true))wp_delete_post($op->get_id(),true);}
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
  $id=$p->save();update_post_meta($id,'_ss_demo_product',1);update_post_meta($id,'_ss_source_id',$source_id);update_post_meta($id,'_ss_source_permalink',$x['permalink']??'');
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


/* Shared product-card renderer used by archive and single-product templates. */
function ss_price_range($p){$id=$p->get_id();$min=get_post_meta($id,'_ss_price_min',true);$max=get_post_meta($id,'_ss_price_max',true);if($min===''||$min===false)$min=(float)$p->get_price();if($max===''||$max===false)$max=$min;return [(float)$min,(float)$max];}
function ss_price_range_html($p){[$min,$max]=ss_price_range($p);return $min===$max?'€'.ss_money($min):'€'.ss_money($min).' – €'.ss_money($max);}
function ss_card_real($p){if(!$p)return;$id=$p->get_id();$img=ss_img($id);$cats=wp_get_post_terms($id,'product_cat',['fields'=>'names']);$cat=is_wp_error($cats)?'':implode(' · ',$cats);$scales=ss_scales($p);$scale_data=implode('|',array_map('sanitize_title',$scales));echo '<article class="product" data-scales="'.esc_attr($scale_data).'"><a href="'.esc_url(get_permalink($id)).'"><div class="ph">'.($img?'<img class="realimg" loading="lazy" decoding="async" src="'.esc_url($img).'" alt="'.esc_attr($p->get_name()).'">':'<span class="no-image">IMAGE COMING SOON</span>').'</div><div class="pi"><h3>'.esc_html($p->get_name()).'</h3><div class="meta">'.esc_html($cat).'</div><div class="price">'.esc_html(ss_price_range_html($p)).'</div></div></a></article>';}

add_action('after_setup_theme',function(){add_theme_support('html5',['search-form','gallery','caption','style','script']);});
add_filter('document_title_separator',function(){return '·';});
add_action('wp_head',function(){if(is_front_page())echo '<meta name="theme-color" content="#0b0c0e">';},1);
