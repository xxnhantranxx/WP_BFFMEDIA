<?php
//Elements build
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/text_box.svg';
add_ux_builder_shortcode( 'BoxTrust', array(
    'type' => 'container',
    'name'      => __('Box Trust'),
    'category'  => __('BFF'),
    'priority'  => 14,
    'thumbnail' =>  $link_img,
    'overlay'   => true,
    'options' => array(
        'number' => array(
            'type' => 'textfield',
            'full_width' => true,
            'heading' => __('Number'),
            'default' => ''
        ),
        'title' => array(
            'type' => 'textarea',
            'full_width' => true,
            'heading' => __('Title'),
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