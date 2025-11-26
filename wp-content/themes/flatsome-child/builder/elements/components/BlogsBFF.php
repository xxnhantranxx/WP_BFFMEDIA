<?php
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/blog_posts.svg';
add_ux_builder_shortcode('BlogsBFF', array(
    'name'      => __('Tin tức BFF'),
    'category'  => __('BFF'),
    'priority'  => 6,
    'thumbnail' =>  $link_img,
    'wrap'   => false,
    'inline' => true,
    'options'   => array(
        'cat' => array(
            'type' => 'select',
            'heading' => 'Danh mục',
            'param_name' => 'cat',
            'full_width' => true,
            'default' => '',
            'config' => array(
                'multiple' => false,
                'placeholder' => 'Chọn...',
                'termSelect' => array(
                    'post_type' => 'post',
                    'taxonomies' => 'category'
                ),
            )
        ),
        'offset' => array(
            'type' => 'slider',
            'heading' => 'Bỏ qua',
            'default' => 0,
            'unit'    => 'count',
            'max' => 20,
            'min' => 0,
        ),
        'count' => array(
            'type' => 'slider',
            'heading' => 'Tổng',
            'default' => 8,
            'unit'    => 'count',
            'max' => 20,
            'min' => 0,
        ),
        'link' => array(
            'type' => 'textfield',
            'heading' => __( 'Dường dẫn' ),
            'default' => '',
        ),
    )
));