<?php
// name slug image: accordion, block, blog_posts, button, categories, countdown, divider, forms, grid, icon_box, image_box, lightbox, map, message_box, page_title, pages, payment-icons, play, product, row, search, share, section, slider, tabs, text, text_box, title, ux_banner, ux_gallery, ux_html, ux_image
$link_img = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/blog_posts.svg';
add_ux_builder_shortcode('Faqs', array(
    'type' => 'container',
    'name'      => __('Faqs'),
    'category'  => __('BFF'),
    'priority'  => 23,
    'thumbnail' =>  $link_img,
    'wrap'   => false,
    'inline' => true,
    'options'   => array(
        'img' => array(
            'type' => 'image',
            'heading' => __('Image'),
            'default' => ''
        ),
        'group_faq' => array(
            'type' => 'select',
            'heading' => 'Nhóm Câu',
            'default' => 'style1',
            'options' => array(
                'style1' => 'Chi phí',
                'style2' => 'Kỷ yếu',
                'style3' => 'Quay video',
                'style4' => 'Tour',
                'style5' => 'Hậu kỳ & In ấn',
            ),
            'config' => array(
                'placeholder' => __( 'Select', 'flatsome' ),
            )
        ),
        'settings_options' => array(
            'type'    => 'group',
            'heading' => __( 'Hướng dẫn', 'flatsome' ),
            'description' => 'Sửa trong quản trị Tuỳ Chọn > <a target="blank" style="color:#9aa506" href="'.home_url().'/wp-admin/admin.php?page=chi-phi-thanh-toan">Faqs</a>',
            'options' => array(
                'class' => array(
                    'type' => 'textfield',
                    'heading' => 'Class',
                    'full_width' => true,
                ),
            ),
        ),
    )
));