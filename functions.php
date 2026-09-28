<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],'0.6.1');wp_enqueue_script('speedstar-storefront',get_template_directory_uri().'/assets/js/storefront.js',[],'0.6.1',true);if(is_product())wp_enqueue_script('wc-add-to-cart-variation');});
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

if(get_option('ss_playground_seed')==='1') require_once get_template_directory().'/inc/playground-import.php';

/* Shared product-card renderer used by archive and single-product templates. */
function ss_price_range($p){$id=$p->get_id();$min=get_post_meta($id,'_ss_price_min',true);$max=get_post_meta($id,'_ss_price_max',true);if($min===''||$min===false)$min=(float)$p->get_price();if($max===''||$max===false)$max=$min;return [(float)$min,(float)$max];}
function ss_price_range_html($p){[$min,$max]=ss_price_range($p);return $min===$max?'€'.ss_money($min):'€'.ss_money($min).' – €'.ss_money($max);}
function ss_card_real($p){if(!$p)return;$id=$p->get_id();$img=ss_img($id);$cats=wp_get_post_terms($id,'product_cat',['fields'=>'names']);$cat=is_wp_error($cats)?'':implode(' · ',$cats);$scales=ss_scales($p);$scale_data=implode('|',array_map('sanitize_title',$scales));echo '<article class="product" data-scales="'.esc_attr($scale_data).'"><a href="'.esc_url(get_permalink($id)).'"><div class="ph">'.($img?'<img class="realimg" loading="lazy" decoding="async" src="'.esc_url($img).'" alt="'.esc_attr($p->get_name()).'">':'<span class="no-image">IMAGE COMING SOON</span>').'</div><div class="pi"><h3>'.esc_html($p->get_name()).'</h3><div class="meta">'.esc_html($cat).'</div><div class="price">'.esc_html(ss_price_range_html($p)).'</div></div></a></article>';}

add_action('after_setup_theme',function(){add_theme_support('html5',['search-form','gallery','caption','style','script']);});
add_filter('document_title_separator',function(){return '·';});
add_action('wp_head',function(){if(is_front_page())echo '<meta name="theme-color" content="#0b0c0e">';},1);

/* 0.6 storefront helpers: real Woo archives and server-side scale filtering. */
function ss_shop_url(){return function_exists('wc_get_page_permalink')?wc_get_page_permalink('shop'):home_url('/shop/');}
function ss_category_url($slug){$term=get_term_by('slug',$slug,'product_cat');return $term&&!is_wp_error($term)?get_term_link($term):home_url('/product-category/'.$slug.'/');}
add_action('pre_get_posts',function($q){if(is_admin()||!$q->is_main_query()||!(is_shop()||is_product_category()))return;$scale=isset($_GET['ss_scale'])?sanitize_title(wp_unslash($_GET['ss_scale'])):'';if(!$scale)return;$tax=(array)$q->get('tax_query');$tax[]=['taxonomy'=>'pa_scale','field'=>'slug','terms'=>[$scale]];$q->set('tax_query',$tax);});

/* 0.6.1 Workshop editorial layer */
add_action('init',function(){
 register_post_type('workshop',[
  'labels'=>['name'=>'Workshop','singular_name'=>'Workshop Article','add_new_item'=>'Add Workshop Article','edit_item'=>'Edit Workshop Article'],
  'public'=>true,'has_archive'=>'workshop','rewrite'=>['slug'=>'workshop','with_front'=>false],
  'show_in_rest'=>true,'menu_icon'=>'dashicons-hammer','supports'=>['title','editor','excerpt','thumbnail','author','revisions'],
 ]);
 register_taxonomy('workshop_type','workshop',['labels'=>['name'=>'Article Types'],'public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>['slug'=>'workshop/type']]);
 register_taxonomy('workshop_brand','workshop',['labels'=>['name'=>'Brands'],'public'=>true,'show_in_rest'=>true,'hierarchical'=>false,'rewrite'=>['slug'=>'workshop/brand']]);
 register_taxonomy('workshop_topic','workshop',['labels'=>['name'=>'Topics'],'public'=>true,'show_in_rest'=>true,'hierarchical'=>false,'rewrite'=>['slug'=>'workshop/topic']]);
});
function ss_workshop_url(){return get_post_type_archive_link('workshop')?:home_url('/workshop/');}
function ss_workshop_meta($id,$key,$fallback=''){ $v=get_post_meta($id,$key,true);return $v!==''?$v:$fallback; }
function ss_workshop_card($post_id){
 $types=wp_get_post_terms($post_id,'workshop_type',['fields'=>'names']);$type=!is_wp_error($types)&&$types?$types[0]:'Workshop';
 $img=get_the_post_thumbnail_url($post_id,'large');
 echo '<article class="workshop-card"><a href="'.esc_url(get_permalink($post_id)).'"><div class="workshop-media">'.($img?'<img loading="lazy" decoding="async" src="'.esc_url($img).'" alt="'.esc_attr(get_the_title($post_id)).'">':'<span>'.esc_html(strtoupper($type)).'</span>').'</div><div class="workshop-card-copy"><div class="eyebrow">'.esc_html($type).'</div><h3>'.esc_html(get_the_title($post_id)).'</h3><p>'.esc_html(get_the_excerpt($post_id)).'</p></div></a></article>';
}
function ss_seed_workshop(){
 if(get_option('ss_workshop_seed_version')==='v2')return;
 $types=['tutorials'=>'Tutorials','kit-reviews'=>'Kit Reviews','techniques'=>'Techniques','new-kits'=>'New Kits'];foreach($types as $slug=>$name)if(!term_exists($slug,'workshop_type'))wp_insert_term($name,'workshop_type',['slug'=>$slug]);
 $articles=[
 ['How to Remove Old Decals Without Damaging the Paint','remove-old-decals','tutorials','Decals','Beginner','20–40 min','A careful starting guide to lifting unwanted decals while protecting the finish underneath.','Start with the least aggressive method. Test an inconspicuous area first, soften the decal gradually, and avoid scraping with hard tools. Painted and clear-coated surfaces react differently, so patience matters more than force.'],
 ['How to Prepare Resin Parts Before Primer','prepare-resin-before-primer','tutorials','Resin','Beginner','30–60 min','Cleaning, inspecting and preparing resin parts so primer has the best possible surface to grip.','Inspect the part for supports, flash and print residue. Wash appropriately, allow it to dry fully, refine attachment points and test-fit before primer. Use suitable respiratory and dust protection whenever sanding resin.'],
 ['How to Fit a Resin Widebody Kit','fit-resin-widebody-kit','techniques','Bodykits','Intermediate','2–4 hours','A practical workflow for test fitting, trimming and aligning a resin widebody conversion before paint.','Dry-fit repeatedly before committing to glue. Establish wheel position and ride height early, mark cuts conservatively, work symmetrically and only move to filler and primer once panel alignment is stable.'],
 ['Tamiya Nissan Silvia K’s S13 1:24: Builder’s Guide','tamiya-silvia-s13-builders-guide','kit-reviews','Tamiya · Nissan','Intermediate','','A builder-focused look at the classic 1:24 S13 as a base for stock and modified projects.','The S13 is especially relevant to the Speedstar catalogue because it is a natural base for aftermarket bodywork and wheel changes. This guide focuses on planning the build, stance, wheel fitment and the areas to test-fit before modification rather than pretending to be a hands-on review of a fresh box.'],
 ['Aoshima Fast & Furious JZA80 Supra 1:24: First Look','aoshima-jza80-supra-first-look','new-kits','Aoshima · Toyota','Beginner','','A first look at Aoshima’s all-new-mould 1:24 movie Supra, released in August 2026.','Aoshima describes this as a completely new mould and a snap-fit kit requiring no glue or painting. The official specification lists the aero-top body, BOMEX aero kit, GT wing, aluminium-style wheels, detailed interior, steerable front wheels, rubber tyres and both stickers and water-slide decals. This is a First Look based on manufacturer information, not a hands-on fit review.'],
 ['Tamiya BMW 320i Racing 1:24: First Look','tamiya-bmw-320i-racing-first-look','new-kits','Tamiya · BMW','Intermediate','','Tamiya’s 1:24 BMW 320i Racing brings the 1977 competition car back with engine and cockpit detail.','Tamiya lists item 24379 as a limited-edition 1:24 kit. The official specification highlights the overfenders, spoiler and wing, inline-four DOHC engine, roll-cage interior and Cartograf decals. This article is deliberately presented as a First Look until a physical build can support judgements about fit and mould quality.']
 ];
 foreach($articles as $a){if(get_page_by_path($a[1],OBJECT,'workshop'))continue;$id=wp_insert_post(['post_type'=>'workshop','post_status'=>'publish','post_title'=>$a[0],'post_name'=>$a[1],'post_excerpt'=>$a[6],'post_content'=>'<p>'.$a[7].'</p><h2>Workshop notes</h2><p>This article is part of the Speedstar Workshop and will be expanded with step-by-step photography, measurements and build observations where appropriate.</p>']);if($id&&!is_wp_error($id)){wp_set_object_terms($id,$a[2],'workshop_type');wp_set_object_terms($id,array_map('trim',explode('·',$a[3])),'workshop_brand');update_post_meta($id,'_ss_difficulty',$a[4]);update_post_meta($id,'_ss_build_time',$a[5]);}}
 update_option('ss_workshop_seed_version','v2');flush_rewrite_rules(false);
}
if(get_option('ss_playground_seed')==='1')add_action('wp_loaded','ss_seed_workshop',30);
