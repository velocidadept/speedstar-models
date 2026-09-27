<?php
add_action('after_setup_theme',function(){add_theme_support('title-tag');add_theme_support('woocommerce');add_theme_support('post-thumbnails');});
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('speedstar-models',get_stylesheet_uri(),[],'0.1.0');});
add_filter('woocommerce_enqueue_styles','__return_empty_array');
