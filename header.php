<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<div class="topbar">RESIN PARTS FOR SCALE MODEL BUILDERS · DESIGNED IN PORTUGAL</div>
<header class="site-head"><div class="wrap head">
<a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Speedstar Models home">SPEED<b>STAR</b></a>
<nav class="desktop-nav" aria-label="Primary navigation">
<a href="<?php echo esc_url(ss_category_url('bodykits')); ?>">BODYKITS</a><a href="<?php echo esc_url(ss_category_url('wheels')); ?>">WHEELS</a><a href="<?php echo esc_url(ss_category_url('engines')); ?>">ENGINES</a><a href="<?php echo esc_url(ss_category_url('t-shirts')); ?>">MERCH</a><a href="<?php echo esc_url(ss_workshop_url()); ?>">WORKSHOP</a><a href="<?php echo esc_url(home_url('/about/')); ?>">ABOUT</a>
</nav>
<div class="tools">
<button class="tool ss-search-toggle" type="button" aria-label="Search products" aria-expanded="false">⌕</button>
<a class="tool cart-tool" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="Cart">◫<?php if(WC()->cart && WC()->cart->get_cart_contents_count()): ?><span class="cart-count"><?php echo esc_html(WC()->cart->get_cart_contents_count()); ?></span><?php endif; ?></a>
<button class="tool menu-toggle" type="button" aria-label="Open menu" aria-expanded="false">☰</button>
</div></div>
<div class="search-panel"><div class="wrap"><form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"><input type="hidden" name="post_type" value="product"><label class="screen-reader-text" for="ss-search">Search products</label><input id="ss-search" name="s" type="search" placeholder="Search S13, Enkei, Cosworth…" autocomplete="off"><button type="submit">SEARCH</button></form></div></div>
<div class="mobile-menu"><div class="wrap"><a href="<?php echo esc_url(ss_category_url('bodykits')); ?>">BODYKITS</a><a href="<?php echo esc_url(ss_category_url('wheels')); ?>">WHEELS</a><a href="<?php echo esc_url(ss_category_url('engines')); ?>">ENGINES</a><a href="<?php echo esc_url(ss_category_url('t-shirts')); ?>">MERCH</a><a href="<?php echo esc_url(ss_workshop_url()); ?>">WORKSHOP</a><a href="<?php echo esc_url(home_url('/about/')); ?>">ABOUT</a></div></div>
</header>