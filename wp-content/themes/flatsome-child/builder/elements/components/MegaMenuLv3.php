<?php
$link_img_accordion = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/row.svg';
add_ux_builder_shortcode('MegaMenuLv3', array(
    'name'      => __('Mega Menu Lv3'),
    'category'  => __('Mijuri'),
    'priority'  => 4,
    'thumbnail' =>  $link_img_accordion,
    'wrap'   => false,
    'inline' => true,
    'options'   => array(
        'is_taxonomy' => array(
            'type' => 'checkbox',
            'heading' => __('Là Danh Mục'),
            'default' => "true",
        ),
        'params_mega_menu' => array(
            'type' => 'group',
            'conditions' => 'is_taxonomy != "true"',
            'heading' => __( 'Params Mega Menu' ),
            'options' => array(
                'label_mega_menu' => array(
                    'type' => 'textfield',
                    'heading' => __( 'Nhãn' ),
                    'default' => '',
                ),
                'link_mega_menu' => array(
                    'type' => 'textfield',
                    'heading' => __( 'Link' ),
                    'default' => 'javascript:void(0)',
                ),
            ),
        ),
        'cat' => array(
            'type' => 'select',
            'conditions' => 'is_taxonomy == "true"',
            'heading' => 'Category',
            'param_name' => 'cat',
            'full_width' => true,
            'default' => 15,
            'config' => array(
                'multiple' => false,
                'placeholder' => 'Select...',
                'termSelect' => array(
                    'post_type' => 'album',
                    'taxonomies' => array('concept', 'school-level', 'district', 'tour-album')
                ),
            )
        ),
        'class' => array(
            'type' => 'textfield',
            'heading' => 'Class',
        ),
    ),
));