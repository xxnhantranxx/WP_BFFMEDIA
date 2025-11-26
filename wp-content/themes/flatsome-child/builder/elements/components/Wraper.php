<?php
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/section.svg';
add_ux_builder_shortcode( 'Wraper', array(
    'type' => 'container',
    'name'      => __('Wraper'),
    'category'  => __('BFF'),
    'priority'  => 15,
    'thumbnail' =>  $link_img,
    'overlay'   => true,
    'options' => array(
        'class' => array(
            'type' => 'textfield',
            'heading' => 'Class',
            'full_width' => true,
        ),
    ),
) );