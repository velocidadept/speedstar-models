<?php
defined('ABSPATH') || exit;
get_header();
global $post;
$p=wc_get_product($post->ID);
if(!$p){get_footer();return;}
$id=$p->get_id(); $gallery=ss_gallery($id); $img=$gallery[0]??ss_img($id); $scales=ss_scales($p);
[$min,$max]=ss_price_range($p);
$cat_names=wp_get_post_terms($id,'product_cat',['fields'=>'names']);
?>
<main class="ss-single">
<div class="wrap">
  <div class="crumb ss-product-crumb">Home / <?php echo esc_html(implode(' / ',$cat_names)); ?> / <?php echo esc_html($p->get_name()); ?></div>
  <div class="product-layout">
    <section class="ss-gallery">
      <div class="gallery-main"><?php if($img): ?><img id="ss-main-product-image" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($p->get_name()); ?>"><?php endif; ?></div>
      <div class="thumbs"><?php foreach(array_slice($gallery,0,4) as $g): ?><button class="thumb" type="button" data-image="<?php echo esc_url($g); ?>"><img loading="lazy" src="<?php echo esc_url($g); ?>" alt=""></button><?php endforeach; ?></div>
    </section>
    <section class="buy">
      <div class="eyebrow"><?php echo esc_html(implode(' · ',$cat_names)); ?> · Resin kit</div>
      <h1><?php echo esc_html($p->get_name()); ?></h1>
      <div class="buy-price"><?php echo esc_html(ss_price_range_html($p)); ?></div>
      <div class="stock <?php echo $p->is_in_stock()?'':'out'; ?>">● <?php echo esc_html($p->is_in_stock()?'In stock':'Out of stock'); ?></div>
      <?php if($scales): ?><div class="scale-title">Select scale</div><div class="scales"><?php foreach($scales as $s): ?><button class="scale" type="button"><?php echo esc_html($s); ?></button><?php endforeach; ?></div><?php endif; ?>
      <div class="addrow"><input class="qty" type="number" min="1" value="1"><button class="add" type="button">ADD TO CART</button></div>
      <div class="bullets"><?php echo wp_kses_post(wpautop($p->get_short_description())); ?></div>
      <div class="accordion"><div>Product Details <b>+</b></div><div>What's Included <b>+</b></div><div>Assembly & Painting <b>+</b></div><div>Shipping & Returns <b>+</b></div></div>
    </section>
  </div>
</div>
<section class="section light-section"><div class="wrap"><div class="section-head"><div><div class="eyebrow">You may also like</div><h2>RELATED PRODUCTS.</h2></div></div><div class="products">
<?php foreach(wc_get_products(['limit'=>3,'exclude'=>[$id],'status'=>'publish']) as $rp) ss_card_real($rp); ?>
</div></div></section>
</main>
<script>
document.querySelectorAll('.ss-gallery .thumb[data-image]').forEach(function(btn){btn.addEventListener('click',function(){var main=document.getElementById('ss-main-product-image');if(main){main.src=this.dataset.image;document.querySelectorAll('.ss-gallery .thumb').forEach(function(x){x.classList.remove('active')});this.classList.add('active');}});});
</script>
<?php get_footer(); ?>