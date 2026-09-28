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
      <div class="gallery-main" title="Click to enlarge"><?php if($img): ?><img id="ss-main-product-image" tabindex="0" role="button" aria-label="Enlarge product image" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($p->get_name()); ?>"><?php endif; ?></div>
      <div class="thumbs"><?php foreach(array_slice($gallery,0,4) as $g): ?><button class="thumb" type="button" data-image="<?php echo esc_url($g); ?>"><img loading="lazy" src="<?php echo esc_url($g); ?>" alt=""></button><?php endforeach; ?></div>
    </section>
    <section class="buy">
      <div class="eyebrow"><?php echo esc_html(implode(' · ',$cat_names)); ?></div>
      <h1><?php echo esc_html($p->get_name()); ?></h1>
      <div class="buy-price"><?php echo esc_html(ss_price_range_html($p)); ?></div>
      <div class="stock <?php echo $p->is_in_stock()?'':'out'; ?>">● <?php echo esc_html($p->is_in_stock()?'In stock':'Out of stock'); ?></div>
      <?php if($p->is_type('variable')): ?>
        <?php woocommerce_variable_add_to_cart(); ?>
      <?php elseif($p->is_type('simple')): ?>
        <?php woocommerce_simple_add_to_cart(); ?>
      <?php else: ?>
        <div class="ss-variation-warning">Scale pricing is still being resolved from the source catalogue.</div>
      <?php endif; ?>
      <?php if($p->get_short_description()): ?><div class="bullets"><?php echo wp_kses_post(wpautop($p->get_short_description())); ?></div><?php endif; ?>
      <?php if($p->get_description()): ?><div class="accordion"><details><summary>Product Details <b>+</b></summary><div class="accordion-copy"><?php echo wp_kses_post(wpautop($p->get_description())); ?></div></details></div><?php endif; ?>
    </section>
  </div>
</div>
<section class="section related-section"><div class="wrap"><div class="section-head"><div><div class="eyebrow">You may also like</div><h2>RELATED PRODUCTS.</h2></div></div><div class="products">
<?php foreach(ss_related_products($id,3) as $rp) ss_card_real($rp); ?>
</div></div></section>
<div class="ss-lightbox" role="dialog" aria-modal="true" aria-label="Product image viewer"><button class="ss-lightbox-close" type="button" aria-label="Close image">×</button><img src="" alt=""></div>
</main>

<?php get_footer(); ?>