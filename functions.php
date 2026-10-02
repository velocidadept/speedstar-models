<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');add_theme_support('html5',['search-form','gallery','caption','style','script']);});
add_action('wp_enqueue_scripts',function(){
 $v=wp_get_theme()->get('Version');
 wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],$v);
 wp_enqueue_script('speedstar-storefront',get_template_directory_uri().'/assets/js/storefront.js',[],$v,true);
 if(is_product())wp_enqueue_script('wc-add-to-cart-variation');
});
add_filter('woocommerce_enqueue_styles','__return_empty_array');

function ss_img($id){
 $local=get_the_post_thumbnail_url($id,'large');if($local)return $local;
 $urls=get_post_meta($id,'_ss_external_gallery',true);
 if(is_array($urls)&&!empty($urls[0])) return $urls[0];
 $url=get_post_meta($id,'_ss_external_image',true);
 return $url?:'';
}
function ss_gallery($id){
 if(function_exists('wc_get_product')){
  $product=wc_get_product($id);
  if($product){
   $ids=array_filter(array_merge([$product->get_image_id()],$product->get_gallery_image_ids()));
   $local=[];foreach($ids as $aid){$url=wp_get_attachment_image_url($aid,'large');if($url)$local[]=$url;}
   if($local)return $local;
  }
 }
 $x=get_post_meta($id,'_ss_external_gallery',true);return is_array($x)?$x:array_filter([ss_img($id)]);
}
function ss_product_image_by_slug($slug){
 $post=get_page_by_path($slug,OBJECT,'product');return $post?ss_img($post->ID):'';
}

/* Playground catalogue uses remote source images. Feed those images into both the
 * Cart/Checkout Blocks Store API and the classic cart without downloading 100+
 * source files into each ephemeral Playground instance. */
function ss_cart_source_image_url($cart_item){
 $parent_id=(int)($cart_item['product_id']??0);
 if(!$parent_id && !empty($cart_item['data']) && $cart_item['data'] instanceof WC_Product){
  $parent_id=(int)$cart_item['data']->get_parent_id();
  if(!$parent_id)$parent_id=(int)$cart_item['data']->get_id();
 }
 return $parent_id?ss_img($parent_id):'';
}
add_filter('woocommerce_store_api_cart_item_images',function($images,$cart_item,$cart_item_key){
 $url=ss_cart_source_image_url($cart_item);if(!$url)return $images;
 $product_id=(int)($cart_item['product_id']??0);
 $name=$product_id?get_the_title($product_id):'Product';
 return [(object)['id'=>$product_id?:1,'src'=>$url,'thumbnail'=>$url,'srcset'=>'','sizes'=>'','name'=>$name,'alt'=>$name]];
},10,3);
add_filter('woocommerce_cart_item_thumbnail',function($thumbnail,$cart_item,$cart_item_key){
 $url=ss_cart_source_image_url($cart_item);if(!$url)return $thumbnail;
 $product_id=(int)($cart_item['product_id']??0);$name=$product_id?get_the_title($product_id):'Product';
 return '<img src="'.esc_url($url).'" alt="'.esc_attr($name).'" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" loading="lazy" decoding="async">';
},10,3);
function ss_scales($product){
 $x=get_post_meta($product->get_id(),'_ss_scales',true);
 if(is_array($x)) return $x;
 $values=$product->get_attribute('pa_scale');
 return $values?array_map('trim',explode(',',$values)):[];
}
function ss_money($n){return function_exists('wc_price')?wp_strip_all_tags(wc_price((float)$n,['decimals'=>2])):number_format((float)$n,2,',','').' €';}

if(get_option('ss_playground_seed')==='1') require_once get_template_directory().'/inc/playground-import.php';

/* Keep the Playground build self-contained: every image referenced by products
 * or Workshop is mirrored into Media Library, retried if a remote host blips,
 * and reattached to the content that uses it. */
function ss_reconcile_media_library(){
 if(get_option('ss_playground_seed')!=='1'||!function_exists('ss_media_source_key')||!function_exists('ss_import_media_image'))return;
 $expected=get_option('ss_media_expected_sources',[]);$map=get_option('ss_media_source_map',[]);
 if(!is_array($expected))$expected=[];if(!is_array($map))$map=[];
 $valid_map=[];foreach($map as $key=>$id)if(ss_media_attachment_valid($id))$valid_map[$key]=(int)$id;
 if(count($valid_map)!==count($map)){update_option('ss_media_source_map',$valid_map,false);$map=$valid_map;}
 $missing=array_diff_key($expected,$map);$attempted=0;
 foreach($missing as $key=>$url){if($attempted>=8)break;$attempted++;ss_import_media_image($url,'Speedstar Models image',0,'speedstar-media-'.substr(md5($key),0,12));}
 $map=get_option('ss_media_source_map',[]);if(!is_array($map))$map=[];
 foreach(wc_get_products(['limit'=>-1,'status'=>'publish']) as $product){
  $sources=get_post_meta($product->get_id(),'_ss_external_gallery',true);if(!is_array($sources)||!$sources)continue;
  $ids=[];foreach($sources as $src){$key=ss_media_source_key($src);if(!empty($map[$key])&&get_post((int)$map[$key]))$ids[]=(int)$map[$key];}
  if($ids){$product->set_image_id($ids[0]);$product->set_gallery_image_ids(array_slice($ids,1));$product->save();}
 }
 $workshops=get_posts(['post_type'=>'workshop','post_status'=>'publish','numberposts'=>-1]);
 foreach($workshops as $article){$src=get_post_meta($article->ID,'_ss_remote_image',true);if(!$src)continue;$key=ss_media_source_key($src);if(!empty($map[$key])&&get_post((int)$map[$key]))set_post_thumbnail($article->ID,(int)$map[$key]);}
 $valid=[];foreach($expected as $key=>$url)if(!empty($map[$key])&&ss_media_attachment_valid($map[$key]))$valid[$key]=(int)$map[$key];
 update_option('ss_media_expected_count',count($expected));update_option('ss_media_import_count',count($valid));update_option('ss_media_import_failures',count($expected)-count($valid));
}
if(get_option('ss_playground_seed')==='1')add_action('wp_loaded','ss_reconcile_media_library',80);
add_action('admin_notices',function(){
 if(get_option('ss_playground_seed')!=='1')return;$expected=(int)get_option('ss_media_expected_count',0);$done=(int)get_option('ss_media_import_count',0);
 if(!$expected)return;$ok=$done===$expected;
 echo '<div class="notice '.($ok?'notice-success':'notice-warning').'"><p><strong>Speedstar Media Library:</strong> '.esc_html($done).' / '.esc_html($expected).' build images imported'.($ok?' ✓':'. Remaining images will retry automatically.').'</p></div>';
});

/* Shared product-card renderer used by archive and single-product templates. */
function ss_price_range($p){$id=$p->get_id();$min=get_post_meta($id,'_ss_price_min',true);$max=get_post_meta($id,'_ss_price_max',true);if($min===''||$min===false)$min=(float)$p->get_price();if($max===''||$max===false)$max=$min;return [(float)$min,(float)$max];}
function ss_price_range_html($p){[$min,$max]=ss_price_range($p);return $min===$max?ss_money($min):ss_money($min).' – '.ss_money($max);}
function ss_card_real($p){if(!$p)return;$id=$p->get_id();$img=ss_img($id);$cats=wp_get_post_terms($id,'product_cat',['fields'=>'names']);$cat=is_wp_error($cats)?'':implode(' · ',$cats);$scales=ss_scales($p);$scale_data=implode('|',array_map('sanitize_title',$scales));echo '<article class="product" data-scales="'.esc_attr($scale_data).'"><a href="'.esc_url(get_permalink($id)).'"><div class="ph">'.($img?'<img class="realimg" loading="lazy" decoding="async" src="'.esc_url($img).'" alt="'.esc_attr($p->get_name()).'">':'<span class="no-image">IMAGE COMING SOON</span>').'</div><div class="pi"><h3>'.esc_html($p->get_name()).'</h3><div class="meta">'.esc_html($cat).'</div><div class="price">'.esc_html(ss_price_range_html($p)).'</div></div></a></article>';}


add_filter('document_title_separator',function(){return '·';});
add_action('wp_head',function(){if(is_front_page())echo '<meta name="theme-color" content="#0b0c0e">';},1);

/* 0.6 storefront helpers: real Woo archives and server-side scale filtering. */
function ss_shop_url(){if(function_exists('wc_get_page_permalink')){$url=wc_get_page_permalink('shop');if($url)return $url;}return home_url('/shop/');}
function ss_category_url($slug){$term=get_term_by('slug',$slug,'product_cat');return $term&&!is_wp_error($term)?get_term_link($term):home_url('/product-category/'.$slug.'/');}
add_action('pre_get_posts',function($q){
 if(is_admin()||!$q->is_main_query()||!(is_shop()||is_product_category()))return;
 $scale=isset($_GET['ss_scale'])?sanitize_title(wp_unslash($_GET['ss_scale'])):'';
 if(!$scale||!term_exists($scale,'pa_scale'))return;
 $tax=(array)$q->get('tax_query');$tax[]=['taxonomy'=>'pa_scale','field'=>'slug','terms'=>[$scale]];$q->set('tax_query',$tax);
});

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
function ss_workshop_image($id,$size='large'){$img=get_the_post_thumbnail_url($id,$size);return $img?:get_post_meta($id,'_ss_remote_image',true);}
function ss_workshop_card($post_id){
 $types=wp_get_post_terms($post_id,'workshop_type',['fields'=>'names']);$type=!is_wp_error($types)&&$types?$types[0]:'Workshop';
 $img=ss_workshop_image($post_id);
 echo '<article class="workshop-card"><a href="'.esc_url(get_permalink($post_id)).'"><div class="workshop-media">'.($img?'<img loading="lazy" decoding="async" src="'.esc_url($img).'" alt="'.esc_attr(get_the_title($post_id)).'">':'<span>'.esc_html(strtoupper($type)).'</span>').'</div><div class="workshop-card-copy"><div class="eyebrow">'.esc_html($type).'</div><h3>'.esc_html(get_the_title($post_id)).'</h3><p>'.esc_html(get_the_excerpt($post_id)).'</p></div></a></article>';
}
function ss_seed_workshop(){
 if(get_option('ss_workshop_seed_version')==='v4-native-media')return;
 $types=['tutorials'=>'Tutorials','kit-reviews'=>'Kit Reviews','techniques'=>'Techniques','new-kits'=>'New Kits'];foreach($types as $slug=>$name)if(!term_exists($slug,'workshop_type'))wp_insert_term($name,'workshop_type',['slug'=>$slug]);
 $articles=[
 ['How to Remove Old Decals Without Damaging the Paint','remove-old-decals','tutorials','Decals','Beginner','20–40 min','Removing an old decal is a rescue job: soften the film, protect the clear coat and stop before the cure becomes worse than the problem.','https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/11/il_794xN.6924719895_12ly.jpg?fit=1200%2C800&ssl=1',<<<'HTML'
<p>Old decals fail in several ways. They can silver, crack, yellow, lift at the edges or become so brittle that touching them turns the carrier film into confetti. The safest removal method depends less on the decal itself than on what is underneath it: bare plastic, colour coat, clear coat, or another decal.</p>
<h2>Before you touch the decal</h2><p>Work out what you are trying to preserve. If the body has a sound clear coat, you have a useful protective layer. If the decal sits directly on paint, solvents and aggressive rubbing become much riskier. Photograph the markings first if you may need to reproduce their position later.</p>
<p>Start with warm water, cotton buds, a soft brush, wooden toothpicks and low-tack masking tape. Keep stronger products off the bench until the gentle methods have failed. Never begin by scraping with a metal blade.</p>
<h2>Method 1: warm water and patience</h2><p>Lay a small piece of warm, damp tissue over the decal for several minutes. The aim is not to flood the model but to encourage moisture under an already compromised edge. Test with a damp cotton bud. If an edge moves, work from that edge inwards instead of attacking the centre.</p>
<h2>Method 2: controlled lifting</h2><p>For a decal already lifting, low-tack tape can sometimes remove loose film. De-tack the tape on your hand first, touch only the decal and pull back on itself at a shallow angle. Stop immediately if paint or clear coat begins to move with it.</p>
<h2>Stubborn residue</h2><p>After the film is gone, adhesive may remain. Warm water with a tiny amount of mild detergent is the first choice. Work locally with a cotton bud and dry the area often so you can see whether you are removing residue or beginning to change the finish.</p>
<h2>What not to do</h2><p>Do not assume decal-setting solution is a decal remover. Do not soak an assembled body in solvent. Avoid fingernails, knife tips and abrasive compounds until the decal film is completely gone. On an unknown paint system, test every chemical on a hidden area first.</p>
<h2>After removal</h2><p>Wash the area gently, let it dry and inspect it under raking light. A visible outline may be a ridge in clear coat rather than adhesive. If the model is being refinished, that ridge can be levelled during the later sanding and polishing stages. If the finish is being preserved, restraint usually produces a better result than chasing the final ghost of the old marking.</p>
HTML
],
 ['How to Prepare Resin Parts Before Primer','prepare-resin-before-primer','tutorials','Resin','Beginner','30–60 min','Good primer starts before the primer. Clean, inspect, test-fit and key resin parts so paint is bonding to the part rather than to residue or sanding dust.','https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/11/il_794xN.6164545748_6blb.jpg?fit=1000%2C1000&ssl=1',<<<'HTML'
<p>Resin aftermarket parts reward preparation. Fine print detail can look spectacular under paint, but support marks, dust, uncured residue or a glossy surface can ruin adhesion. Treat preparation as part of the build, not as the boring five minutes before the airbrush.</p>
<h2>1. Inspect before sanding</h2><p>Use strong side lighting and look for support attachment points, tiny steps, print lines, trapped residue and thin edges. Compare left and right parts before removing material. On a wheel set or bodykit, symmetry is more important than making one piece perfect in isolation.</p>
<h2>2. Clean the parts</h2><p>Follow the resin maker's cleaning guidance. For finished aftermarket parts, a gentle wash with lukewarm water and mild detergent removes handling contamination and sanding dust. Rinse well and let everything dry completely. Do not prime a part that is still damp in recesses.</p>
<h2>3. Remove supports and refine attachment points</h2><p>Cut supports progressively rather than twisting them off. Leave a fraction of material and sand the final nub flush. This reduces the chance of tearing a crater from a visible surface. Protect delicate fins, spokes and trumpets while handling nearby supports.</p>
<h2>4. Sand safely</h2><p>Resin dust should not be inhaled. Wet sanding helps keep dust down; appropriate respiratory protection and good ventilation are sensible whenever dry dust can be produced. Use fine abrasives and let the abrasive do the work. Deep scratches that look harmless in grey resin become surprisingly loud under primer.</p>
<h2>5. Test-fit now, not after paint</h2><p>Check locating faces, wheel centres, engine mounts and bodykit interfaces before primer. Correcting a tight fit after colour and clear coat is an excellent way to damage both parts. Drill pinning holes and establish mounting points at this stage.</p>
<h2>6. Final clean and primer</h2><p>After the last sanding pass, clean the part again and handle it with clean gloves or by a hidden mounting point. Apply compatible primer in light coats. The first coat is also an inspection coat: if it reveals a support scar or print line, fix it now rather than burying it under more paint.</p>
HTML
],
 ['How to Fit a Resin Widebody Kit','fit-resin-widebody-kit','techniques','Bodykits','Intermediate','2–4 hours','A widebody conversion is an alignment problem before it is a glue problem. Establish stance, wheel position and symmetry before making irreversible cuts.','https://i0.wp.com/speedstarmodels.com/wp-content/uploads/2025/11/il_794xN.6924719895_12ly.jpg?fit=1200%2C800&ssl=1',<<<'HTML'
<p>The temptation with a resin widebody is to start cutting the donor shell immediately. Resist it. A convincing conversion is built around relationships: tyre to arch, flare to body line, front to rear track and left side to right side.</p>
<h2>Inventory the conversion</h2><p>Lay out every resin component and identify what replaces original plastic and what overlays it. Check whether bumpers, skirts, wings and wheels are part of the conversion. Photograph the complete set before work starts. It becomes a useful reference once the bench fills with trimmed pieces.</p>
<h2>Build a temporary rolling reference</h2><p>Mock up the chassis, suspension and wheels. Decide ride height and approximate track width before positioning arches. A flare can be perfectly aligned to the body and still look wrong if the wheel sits 2 mm too far inboard.</p>
<h2>Dry-fit with tape</h2><p>Use small pieces of low-tack tape to hold the resin in position. Work from strong reference points such as door shuts, sill lines and bumper corners. Compare both sides from front, rear and plan view. Measure when possible rather than trusting perspective.</p>
<h2>Mark cuts conservatively</h2><p>If the original arches must be opened, mark the minimum cut first. Remove material in stages. It is easy to enlarge an opening and much harder to put a wheel arch back. Keep structural plastic until you know it interferes with the new part.</p>
<h2>Correct the resin, then commit</h2><p>Minor warping can often be corrected carefully before installation, but use a method appropriate to the resin and manufacturer. Sand mating surfaces flat, create positive locating points where useful and make sure the doors, bonnet and chassis can still be assembled.</p>
<h2>Glue in sequence</h2><p>Do not glue every panel in one heroic session. Fix the components that establish alignment first, recheck symmetry, then continue. Use an adhesive compatible with resin and styrene. Avoid flooding visible seams.</p>
<h2>Blend only where the real conversion demands it</h2><p>Some real widebody kits have visible panel edges and fasteners; others are blended into the body. Study the intended full-size kit before filling every seam. Once the structure is stable, use filler sparingly, sand the transition, apply an inspection coat of primer and repeat until the silhouette reads cleanly from every angle.</p>
HTML
],
 ['Tamiya Nissan Silvia K’s S13 1:24: Builder’s Guide','tamiya-silvia-s13-builders-guide','kit-reviews','Tamiya · Nissan','Intermediate','','The venerable Tamiya S13 remains a useful 1:24 foundation for stock builds and modified projects, including the kind of resin conversions Speedstar produces.','https://d7z22c0gz59ng.cloudfront.net/japan_contents/img/usr/item/2/24078/24078_1.jpg',<<<'HTML'
<p><strong>Kit:</strong> Tamiya 24078 · <strong>Scale:</strong> 1:24 · <strong>Subject:</strong> Nissan Silvia K's (S13).</p>
<p>This is a builder's guide rather than a fresh-box fit review. Tamiya lists the finished model at 185 mm long and 82 mm wide. The kit represents the two-door S13 and includes a separately detailed interior, chassis underside with exhaust and drivetrain detail, rear multi-link suspension components, clear headlamp parts, treaded synthetic-rubber tyres and the option to build left- or right-hand drive.</p>
<h2>Why the S13 still matters</h2><p>The S13 is unusually useful as a tuning canvas. Its simple surfacing gives aftermarket wheels and bodywork enormous visual influence, while the underlying Tamiya kit provides a known 1:24 reference around which a conversion can be planned.</p>
<h2>Plan the stance before bodywork</h2><p>For a stock build, the supplied suspension and tyres define the look. For a modified build, mock up your intended wheels before changing the arches. Wheel diameter, tyre shoulder, offset and ride height should be considered together. A widebody conversion designed around a low stance can look oddly perched if the chassis is left untouched.</p>
<h2>Interior and chassis</h2><p>Tamiya's official specification calls out the monoform-style seats, two centre-console choices and rear shelf. The underside also includes separate upper-arm components around the rear multi-link layout. Decide early how much of this detail will remain visible, because a display build can justify more careful painting underneath than a permanently fixed street diorama.</p>
<h2>Body preparation</h2><p>Preserve the crisp S13 window openings, lamps and door shut lines when sanding. If fitting aftermarket overfenders, use those factory lines as alignment references. Do not remove the original arches until the resin parts and wheel position have been proven together.</p>
<h2>Speedstar connection</h2><p>The S13 is directly relevant to our workshop because the Nissan Silvia S13 Spirit Rei Miyabi widebody conversion turns this kind of donor kit into a much more aggressive project. The useful lesson is broader than one product: treat the plastic kit, resin conversion and wheel package as one system rather than three separate purchases.</p>
HTML
],
 ['Aoshima Fast & Furious JZA80 Supra 1:24: First Look','aoshima-jza80-supra-first-look','new-kits','Aoshima · Toyota','Beginner','','Aoshima has approached the famous orange JZA80 as a new-mould 1:24 Snap Car. Here is what the manufacturer specification tells us, and what still needs a real build to verify.','https://www.aoshima-bk.co.jp/wp/wp-content/uploads/2026/03/4905083067710_pkg.jpg',<<<'HTML'
<p><strong>Manufacturer:</strong> Aoshima · <strong>Reference:</strong> 06771 · <strong>Scale:</strong> 1:24 · <strong>Release:</strong> August 2026.</p>
<p>This is a First Look based on Aoshima's published specification, not a hands-on review. That distinction matters: photographs and feature lists can tell us what is engineered into the kit, but not whether every joint disappears neatly or how forgiving the assembly is.</p>
<h2>What is new?</h2><p>Aoshima describes the Fast & Furious JZA80 Supra as a completely new mould in its 1:24 Snap Car line. The company states that the kit uses snap-fit assembly and is designed so that glue and painting are not required to produce the depicted car.</p>
<h2>The movie-specific hardware</h2><p>The official feature list includes the aero-top body, BOMEX aerodynamic components and GT wing that define the film car's silhouette. Aluminium-style wheels, rubber tyres and a detailed interior are included, while the front wheels are steerable.</p>
<h2>Decoration</h2><p>Aoshima supplies both stickers and water-slide decals. That is particularly interesting for two audiences: a beginner can pursue a straightforward snap build, while an experienced builder can paint the body and use water-slide markings for a more traditional finish.</p>
<h2>What we want to inspect in a physical build</h2><p>The key questions are seam placement, the fit of the aero parts, transparency and thickness of the glazing, how convincingly the unpainted plastic carries the orange finish, and whether the snap engineering leaves visible joints around the body. None of those should be scored from catalogue photography alone.</p>
<h2>Who is it for?</h2><p>On paper, it occupies an interesting middle ground: an accessible kit of a culturally enormous tuner car, but with enough subject-specific equipment to tempt experienced builders into paint, detailing and modification. A full Workshop review should follow only after handling and assembling the actual kit.</p>
HTML
],
 ['Tamiya BMW 320i Racing 1:24: First Look','tamiya-bmw-320i-racing-first-look','new-kits','Tamiya · BMW','Intermediate','','A newly announced 1:24 racing BMW with an unusually useful ingredient for detail builders: an engine bay that Tamiya explicitly calls out in the specification.','https://d7z22c0gz59ng.cloudfront.net/cms/img/usr/item/2/24379/info/24379_1.jpg',<<<'HTML'
<p><strong>Manufacturer:</strong> Tamiya · <strong>Item:</strong> 24379 · <strong>Scale:</strong> 1:24 · <strong>Status:</strong> Limited Edition.</p>
<p>Tamiya's new BMW 320i Racing represents the car that competed in the 1977 World Championship for Makes. The official dimensions are 203 mm long, 85 mm wide and 55 mm high. This is a First Look from manufacturer information, so fit and mould-quality judgements are deliberately reserved for a physical build.</p>
<h2>The shape</h2><p>The racing 320i is defined by enormous overfenders, a front spoiler and rear wing. Those features give a 1:24 kit plenty of visual presence even before livery is applied, and they also make panel alignment and wheel position important areas to inspect when the kit reaches the bench.</p>
<h2>Engine detail</h2><p>Tamiya specifically highlights the longitudinal inline-four DOHC engine and describes the engine bay as realistically reproduced. That immediately makes the kit interesting to builders who enjoy plumbing, wiring and subtle aftermarket additions rather than treating the bonnet as permanently closed.</p>
<h2>Cockpit and markings</h2><p>The interior includes the competition roll cage. Tamiya also confirms stripes, numbers and sponsor markings, with Cartograf decals called out on the product page. For a race-car build, decal behaviour over compound curves and around the widened bodywork will be one of the important practical points to test.</p>
<h2>Release timing</h2><p>Tamiya's Japanese product information lists shipment for November 2026. Until production kits are in builders' hands, catalogue images should be treated as a specification preview rather than evidence of assembly quality.</p>
<h2>Workshop verdict, for now</h2><p>No score and no imaginary fit report. What we can say is that the combination of wide 1970s touring-car bodywork, a represented engine bay and full racing decoration gives this release unusually strong potential for a detailed 1:24 project. When a physical kit is available, the Workshop can turn this First Look into a proper bench review.</p>
HTML
]
 ];
 foreach($articles as $a){$existing=get_page_by_path($a[1],OBJECT,'workshop');$id=$existing?$existing->ID:0;$post=['post_type'=>'workshop','post_status'=>'publish','post_title'=>$a[0],'post_name'=>$a[1],'post_excerpt'=>$a[6],'post_content'=>$a[8]];if($id){$post['ID']=$id;$id=wp_update_post($post);}else{$id=wp_insert_post($post);}if($id&&!is_wp_error($id)){wp_set_object_terms($id,$a[2],'workshop_type');wp_set_object_terms($id,array_map('trim',explode('·',$a[3])),'workshop_brand');update_post_meta($id,'_ss_difficulty',$a[4]);update_post_meta($id,'_ss_build_time',$a[5]);update_post_meta($id,'_ss_remote_image',$a[7]);if(function_exists('ss_import_media_image')&&!get_post_thumbnail_id($id)){$aid=ss_import_media_image($a[7],$a[0],$id,'speedstar-workshop-'.$a[1]);if($aid)set_post_thumbnail($id,$aid);}}}
 if(function_exists('ss_media_source_key')){
  $expected=get_option('ss_media_expected_sources',[]);if(!is_array($expected))$expected=[];
  foreach($articles as $a)$expected[ss_media_source_key($a[7])]=$a[7];
  update_option('ss_media_expected_sources',$expected,false);
 }
 update_option('ss_workshop_seed_version','v4-native-media');flush_rewrite_rules(false);
}
if(get_option('ss_playground_seed')==='1')add_action('wp_loaded','ss_seed_workshop',30);

/* Product-first related products: use WooCommerce relevance instead of arbitrary catalogue items. */
function ss_related_products($product_id,$limit=3){
 $ids=function_exists('wc_get_related_products')?wc_get_related_products($product_id,$limit):[];
 if(!$ids)$ids=wc_get_products(['limit'=>$limit,'exclude'=>[$product_id],'status'=>'publish','return'=>'ids','orderby'=>'date','order'=>'DESC']);
 return array_values(array_filter(array_map('wc_get_product',$ids)));
}
