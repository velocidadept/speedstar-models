<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],'0.6.0');wp_enqueue_script('speedstar-storefront',get_template_directory_uri().'/assets/js/storefront.js',[],'0.6.0',true);if(is_product())wp_enqueue_script('wc-add-to-cart-variation');});
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
