<?php
//Elements build
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/slider.svg';
add_ux_builder_shortcode( 'HomeSlider', array(
    'name'      => __('Home Slider'),
    'category'  => __('BFF'),
    'priority'  => 1,
    'thumbnail' =>  $link_img,
    'overlay'   => true,
    'options' => array(
        'settings_options' => array(
            'type'    => 'group',
            'heading' => __( 'Hướng dẫn', 'flatsome' ),
            'description' => 'Sửa trong quản trị Tuỳ Chọn > <a target="blank" style="color:#9aa506" href="'.home_url().'/wp-admin/admin.php?page=customize">Giao Diện</a>',
            'options' => array(
                'class' => array(
                    'type' => 'textfield',
                    'heading' => 'Class',
                    'full_width' => true,
                ),
            ),
        ),
    ),
) );