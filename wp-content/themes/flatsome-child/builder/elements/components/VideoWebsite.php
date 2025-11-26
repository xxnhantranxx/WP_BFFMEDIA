<?php
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/slider.svg';
add_ux_builder_shortcode( 'VideoWebsite', array(
    'name'      => __('Video Website'),
    'category'  => __('BFF'),
    'priority'  => 9,
    'thumbnail' =>  $link_img,
    'overlay'   => true,
    'options' => array(
        'video_options' => array(
            'type' => 'group',
            'heading' => __( 'Video' ),
            'options' => array(
                'link' => array(
                    'type' => 'textfield',
                    'heading' => 'Đường dẫn',
                    'full_width' => true,
                ),
                'title' => array(
                    'type' => 'textarea',
                    'heading' => 'Tiêu đề',
                    'full_width' => true,
                ),
                'img' => array(
                    'type' => 'image',
                    'heading' => __('Image'),
                    'default' => ''
                ),
                'img_mobile' => array(
                    'type' => 'image',
                    'heading' => __('Ảnh Mobile'),
                    'default' => ''
                ),
            ),
        ),
        'class' => array(
            'type' => 'textfield',
            'heading' => 'Class',
            'full_width' => true,
        ),
    ),
) );