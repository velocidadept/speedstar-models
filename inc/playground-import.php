<?php
/* Playground-only catalogue mirror. Never loaded on the production storefront. */
function ss_source_get($url){
 $allowed='https://speedstarmodels.com/wp-json/wc/store/v1/';
 if(strncmp($url,$allowed,strlen($allowed))!==0)return [];
 $r=wp_safe_remote_get($url,['timeout'=>45,'redirection'=>2,'limit_response_size'=>8*MB_IN_BYTES,'headers'=>['Accept'=>'application/json']]);
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

/* Import every image used by the Playground build into the native WordPress
 * Media Library. The source URL is kept only as provenance/fallback. */
function ss_media_source_key($url){
 $parts=wp_parse_url($url);if(!$parts||empty($parts['host'])||empty($parts['path']))return esc_url_raw($url);
 return strtolower($parts['host']).$parts['path'];
}
function ss_media_attachment_valid($id){
 $id=(int)$id;if(!$id||get_post_type($id)!=='attachment')return false;
 $file=get_attached_file($id);return $file&&is_file($file);
}
function ss_import_media_image($url,$title,$parent_id=0,$filename_base=''){
 if(!$url)return 0;
 $map=get_option('ss_media_source_map',[]);if(!is_array($map))$map=[];
 $key=ss_media_source_key($url);
 if(!empty($map[$key])&&ss_media_attachment_valid($map[$key]))return (int)$map[$key];
 if(isset($map[$key])){unset($map[$key]);update_option('ss_media_source_map',$map,false);}
 require_once ABSPATH.'wp-admin/includes/media.php';
 require_once ABSPATH.'wp-admin/includes/file.php';
 require_once ABSPATH.'wp-admin/includes/image.php';
 $path=(string)wp_parse_url($url,PHP_URL_PATH);$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
 if(!in_array($ext,['jpg','jpeg','jpe','png','gif','webp','avif'],true))$ext='jpg';
 $base=$filename_base?sanitize_title($filename_base):sanitize_title($title);
 if(!$base)$base='speedstar-image';
 $tmp=download_url($url,45);if(is_wp_error($tmp))return 0;
 $file=['name'=>$base.'.'.$ext,'tmp_name'=>$tmp];
 $id=media_handle_sideload($file,$parent_id,$title);
 if(is_wp_error($id)){@unlink($tmp);return 0;}
 update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field($title));
 update_post_meta($id,'_ss_source_url',esc_url_raw($url));
 $map[$key]=(int)$id;update_option('ss_media_source_map',$map,false);
 return (int)$id;
}
function ss_ensure_scale_taxonomy($scales){
 $attr_id=wc_attribute_taxonomy_id_by_name('scale');
 if(!$attr_id){$attr_id=wc_create_attribute(['name'=>'Scale','slug'=>'scale','type'=>'select','order_by'=>'menu_order','has_archives'=>false]);delete_transient('wc_attribute_taxonomies');}
 if(!taxonomy_exists('pa_scale')) register_taxonomy('pa_scale','product',['hierarchical'=>false,'label'=>'Scale','query_var'=>true,'rewrite'=>false,'public'=>false,'show_ui'=>false]);
 foreach($scales as $name){if(!term_exists($name,'pa_scale'))wp_insert_term($name,'pa_scale',['slug'=>sanitize_title($name)]);}
 return $attr_id;
}
function ss_import_source_catalogue(){
 if(!class_exists('WooCommerce')||!function_exists('wc_get_products')||!class_exists('WC_Product_Variable')||!class_exists('WC_Product_Variation'))return;
 $version='full-catalogue-v9-safe-bootstrap';
 if(get_option('ss_catalogue_seed_version')===$version)return;
 $base='https://speedstarmodels.com/wp-json/wc/store/v1';
 $items=ss_source_products($base); if(!$items)return;
 $variations=ss_source_variations($base);
 $vars_by_parent=[];$expected_variations=0;$created_variations=0;$variation_mismatches=[];$expected_media_sources=[];
 foreach($variations as $v){$pid=(int)($v['parent']??0);if($pid)$vars_by_parent[$pid][]=$v;}
 $old=wc_get_products(['limit'=>-1,'status'=>['publish','draft','private']]);
 foreach($old as $op){if(get_post_meta($op->get_id(),'_ss_playground_product',true))wp_delete_post($op->get_id(),true);}
 foreach($items as $x){
  if(empty($x['name'])||empty($x['slug']))continue;
  $source_id=(int)($x['id']??0);$scales=[];$product_attrs=[];$variation_attrs=[];
  foreach(($x['attributes']??[]) as $a){
   $taxonomy=$a['taxonomy']??'';$name=$a['name']??'';if($name==='')continue;
   $terms=[];foreach(($a['terms']??[]) as $t)if(isset($t['name']))$terms[]=['name'=>$t['name'],'slug'=>$t['slug']??sanitize_title($t['name'])];
   if($taxonomy==='pa_scale'){foreach($terms as $t)$scales[]=$t['name'];}
   $def=['taxonomy'=>$taxonomy,'name'=>$name,'terms'=>$terms,'variation'=>!empty($a['has_variations'])];
   $product_attrs[]=$def;if($def['variation'])$variation_attrs[]=$def;
  }
  $scales=array_values(array_unique($scales)); if($scales)ss_ensure_scale_taxonomy($scales);
  $p=$variation_attrs?new WC_Product_Variable():new WC_Product_Simple();
  $p->set_name(wp_strip_all_tags(html_entity_decode($x['name'],ENT_QUOTES|ENT_HTML5,'UTF-8')));$p->set_slug($x['slug']);$p->set_status('publish');$p->set_catalog_visibility('visible');
  if(!empty($x['sku']))$p->set_sku($x['sku']);$p->set_description($x['description']??'');$p->set_short_description($x['short_description']??'');
  $p->set_stock_status(!empty($x['is_in_stock'])?'instock':'outofstock');
  $catids=[];foreach(($x['categories']??[]) as $cat){$slug=$cat['slug']??sanitize_title($cat['name']??'');$term=get_term_by('slug',$slug,'product_cat');if(!$term&&!empty($cat['name'])){$made=wp_insert_term($cat['name'],'product_cat',['slug'=>$slug]);if(!is_wp_error($made))$term=get_term($made['term_id'],'product_cat');}if($term&&!is_wp_error($term))$catids[]=$term->term_id;}$p->set_category_ids($catids);
  if($product_attrs){$attrs=[];foreach($product_attrs as $pos=>$va){$attr=new WC_Product_Attribute();if($va['taxonomy']==='pa_scale'){$term_ids=[];foreach($va['terms'] as $t){$term=get_term_by('slug',$t['slug'],'pa_scale');if($term)$term_ids[]=$term->term_id;}$attr->set_id(wc_attribute_taxonomy_id_by_name('scale'));$attr->set_name('pa_scale');$attr->set_options($term_ids);}else{$attr->set_id(0);$attr->set_name($va['name']);$attr->set_options(array_column($va['terms'],'name'));}$attr->set_position($pos);$attr->set_visible(true);$attr->set_variation(!empty($va['variation']));$attrs[]=$attr;}$p->set_attributes($attrs);}
  else{$price=$x['prices']['price']??'0';$minor=(int)($x['prices']['currency_minor_unit']??2);$p->set_regular_price((string)ss_money_from_api($price,$minor));}
  $id=$p->save();$tagids=[];foreach(($x['tags']??[]) as $tag){$slug=$tag['slug']??sanitize_title($tag['name']??'');$term=get_term_by('slug',$slug,'product_tag');if(!$term&&!empty($tag['name'])){$made=wp_insert_term($tag['name'],'product_tag',['slug'=>$slug]);if(!is_wp_error($made))$term=get_term($made['term_id'],'product_tag');}if($term&&!is_wp_error($term))$tagids[]=$term->term_id;}if($tagids)wp_set_object_terms($id,$tagids,'product_tag');update_post_meta($id,'_ss_playground_product',1);update_post_meta($id,'_ss_source_id',$source_id);update_post_meta($id,'_ss_source_permalink',$x['permalink']??'');
  $imgs=[];$media_ids=[];$media_failures=[];
  foreach(($x['images']??[]) as $idx=>$im){
   if(empty($im['src']))continue;$src=$im['src'];$imgs[]=$src;$expected_media_sources[ss_media_source_key($src)]=$src;
   $aid=ss_import_media_image($src,$p->get_name().' image '.($idx+1),$id,'speedstar-'.$x['slug'].'-'.str_pad((string)($idx+1),2,'0',STR_PAD_LEFT));
   if($aid)$media_ids[]=$aid;else $media_failures[]=$src;
  }
  update_post_meta($id,'_ss_external_gallery',$imgs);if($imgs)update_post_meta($id,'_ss_external_image',$imgs[0]);
  if($media_ids){$p->set_image_id($media_ids[0]);$p->set_gallery_image_ids(array_slice($media_ids,1));$p->save();}
  if($media_failures)update_post_meta($id,'_ss_media_import_failures',$media_failures);else delete_post_meta($id,'_ss_media_import_failures');
  $price=$x['prices']['price']??'0';$minor=(int)($x['prices']['currency_minor_unit']??2);$range=$x['prices']['price_range']??[];$min=$range['min_amount']??$price;$max=$range['max_amount']??$price;update_post_meta($id,'_ss_price_min',ss_money_from_api($min,$minor));update_post_meta($id,'_ss_price_max',ss_money_from_api($max,$minor));update_post_meta($id,'_ss_scales',$scales);
  if($variation_attrs){
   $expected_for_product=count($x['variations']??[]);$expected_variations+=$expected_for_product;
   $source_vars=$vars_by_parent[$source_id]??[];$source_by_id=[];foreach($source_vars as $sv)$source_by_id[(int)($sv['id']??0)]=$sv;$created=0;
   foreach(($x['variations']??[]) as $pv){$sid=(int)($pv['id']??0);$sv=$source_by_id[$sid]??[];$vattrs=[];foreach(($pv['attributes']??[]) as $pa){$name=$pa['name']??'';$value=$pa['value']??'';if($name===''||$value==='')continue;$matched=null;foreach($variation_attrs as $va)if(strcasecmp($va['name'],$name)===0){$matched=$va;break;}if(!$matched)continue;$key=$matched['taxonomy']==='pa_scale'?'pa_scale':sanitize_title($matched['name']);$vattrs[$key]=$matched['taxonomy']==='pa_scale'?sanitize_title($value):$value;}if(!$vattrs)continue;$vp=new WC_Product_Variation();$vp->set_parent_id($id);$vp->set_status('publish');$vp->set_attributes($vattrs);$vminor=(int)($sv['prices']['currency_minor_unit']??$minor);$vprice=ss_money_from_api($sv['prices']['price']??$min,$vminor);$vp->set_regular_price((string)$vprice);$vp->set_price((string)$vprice);$vp->set_manage_stock(false);$vp->set_stock_status(isset($sv['is_in_stock'])&&!$sv['is_in_stock']?'outofstock':'instock');$vid=$vp->save();update_post_meta($vid,'_ss_source_variation_id',$sid);$created++;}
   $created_variations+=$created;if($created!==$expected_for_product){update_post_meta($id,'_ss_variations_unresolved',1);$variation_mismatches[]=['source_id'=>$source_id,'slug'=>$x['slug'],'expected'=>$expected_for_product,'created'=>$created];}else delete_post_meta($id,'_ss_variations_unresolved');
   WC_Product_Variable::sync($id);
  }
 }
 if(!get_page_by_path('about'))wp_insert_post(['post_title'=>'About','post_name'=>'about','post_status'=>'publish','post_type'=>'page']);
 $media_map=get_option('ss_media_source_map',[]);if(!is_array($media_map))$media_map=[];$valid_media=[];
 foreach($expected_media_sources as $key=>$src)if(!empty($media_map[$key])&&ss_media_attachment_valid($media_map[$key]))$valid_media[$key]=$media_map[$key];
 $missing_media=array_diff_key($expected_media_sources,$valid_media);
 update_option('ss_media_expected_sources',$expected_media_sources,false);update_option('ss_media_expected_count',count($expected_media_sources));update_option('ss_media_import_count',count($valid_media));update_option('ss_media_import_failures',count($missing_media));
 update_option('ss_catalogue_seed_version',$version);update_option('ss_catalogue_seed_count',count($items));update_option('ss_catalogue_variation_count',count($variations));update_option('ss_catalogue_expected_variations',$expected_variations);update_option('ss_catalogue_created_variations',$created_variations);update_option('ss_catalogue_variation_mismatches',$variation_mismatches);update_option('ss_catalogue_seeded_at',time());flush_rewrite_rules(false);
}
add_action('wp_loaded','ss_import_source_catalogue',30);


