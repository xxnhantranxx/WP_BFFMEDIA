<?php
//Elements build
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/block.svg';
add_ux_builder_shortcode( 'BlockServiceText', array(
    'type' => 'container',
    'name'      => __('Block Service Text'),
    'category'  => __('BFF'),
    'priority'  => 5,
    'thumbnail' =>  $link_img,
    'overlay'   => true,
    'options' => array(
        'subtitle' => array(
            'type' => 'textfield',
            'full_width' => true,
            'heading' => __('Subtitle'),
            'default' => ''
        ),
        'title' => array(
            'type' => 'textfield',
            'full_width' => true,
            'heading' => __('Title'),
            'default' => ''
        ),
        'description' => array(
            'type' => 'textarea',
            'full_width' => true,
            'heading' => __('Description'),
            'default' => ''
        ),
        'class' => array(
            'type' => 'textfield',
            'full_width' => true,
            'heading' => __('Class'),
            'default' => ''
        ),
    ),
) );