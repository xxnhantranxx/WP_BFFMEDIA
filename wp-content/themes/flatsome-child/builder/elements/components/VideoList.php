<?php
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/blog_posts.svg';
add_ux_builder_shortcode('VideoList', array(
    'name'      => __('Video List'),
    'category'  => __('BFF'),
    'priority'  => 13,
    'thumbnail' =>  $link_img,
    'wrap'   => false,
    'inline' => true,
    'options'   => array(
        'ids' => array(
            'type' => 'select',
            'heading' => 'Video List',
            'param_name' => 'ids',
            'full_width' => true,
            'config' => array(
                'multiple' => true,
                'placeholder' => 'Select..',
                'postSelect' => array(
                    'post_type' => array('videos')
                ),
            )
        ),
        'class' => array(
            'type' => 'textfield',
            'heading' => 'Class',
            'full_width' => true,
        ),
    )
));