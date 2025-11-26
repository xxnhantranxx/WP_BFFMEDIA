<?php
//Elements build
// accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/lightbox.svg';
add_ux_builder_shortcode( 'Reviews', array(
    'name'      => __('Reviews'),
    'category'  => __('BFF'),
    'priority'  => 10,
    'thumbnail' =>  $link_img,
    'overlay'   => true,
    'options' => array(
        'settings_options' => array(
            'type'    => 'group',
	        'heading' => __( 'Hướng dẫn', 'flatsome' ),
            'description' => 'Sửa trong quản trị Tuỳ Chọn > <a target="blank" style="color:#9aa506" href="'.home_url().'/wp-admin/admin.php?page=reviews">Reviews</a>',
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