<?php
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/blog_posts.svg';
add_ux_builder_shortcode('AlbumForTour', array(
    'name'      => __('Album For Tour'),
    'category'  => __('BFF'),
    'priority'  => 24,
    'thumbnail' =>  $link_img,
    'wrap'   => false,
    'inline' => true,
    'options'   => array(
        'ids' => array(
            'type' => 'select',
            'heading' => 'Posts',
            'param_name' => 'ids',
            'full_width' => true,
            'config' => array(
                'multiple' => true,
                'placeholder' => 'Select..',
                'postSelect' => array(
                    'post_type' => array('album')
                ),
            )
        ),
        'title' => array(
            'type' => 'textfield',
            'heading' => 'Title',
            'full_width' => true,
        ),
        'description' => array(
            'type' => 'textarea',
            'heading' => 'Description',
            'full_width' => true,
        ),
        'class' => array(
            'type' => 'textfield',
            'heading' => 'Class',
            'full_width' => true,
        ),
    )
));