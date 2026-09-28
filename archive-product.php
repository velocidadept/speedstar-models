<?php
defined('ABSPATH')||exit;get_header();
$title=is_shop()?'SHOP':single_term_title('',false);
$current=isset($_GET['ss_scale'])?sanitize_title(wp_unslash($_GET['ss_scale'])):'';
$show_scales=!(is_product_category('t-shirts'));
$scales=['124'=>'1:24','118'=>'1:18'];
if(is_product_category('engines'))$scales+=['112'=>'1:12','110'=>'1:10','18'=>'1:8'];
?>
<main><section class="pagehero"><div class="wrap"><div class="crumb">Home / <?php echo esc_html($title); ?></div><h1><?php echo esc_html(strtoupper($title)); ?>.</h1><?php if(is_product_category()&&term_description()): ?><div class="ss-lead"><?php echo wp_kses_post(term_description()); ?></div><?php endif; ?></div></section>
<section class="section"><div class="wrap">
<?php if($show_scales): ?><nav class="filters" aria-label="Filter by scale"><a class="chip <?php echo $current?'':'on'; ?>" href="<?php echo esc_url(remove_query_arg('ss_scale')); ?>">All</a><?php foreach($scales as $slug=>$label): ?><a class="chip <?php echo $current===$slug?'on':''; ?>" href="<?php echo esc_url(add_query_arg('ss_scale',$slug)); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></nav><?php endif; ?>
<div class="products"><?php if(have_posts()):while(have_posts()):the_post();ss_card_real(wc_get_product(get_the_ID()));endwhile;else:?><div class="empty-state"><h2>NO PARTS HERE YET.</h2><p>Try another scale or category.</p></div><?php endif;?></div>
<?php the_posts_pagination(['mid_size'=>1,'prev_text'=>'← PREVIOUS','next_text'=>'NEXT →']); ?>
</div></section></main><?php get_footer(); ?>