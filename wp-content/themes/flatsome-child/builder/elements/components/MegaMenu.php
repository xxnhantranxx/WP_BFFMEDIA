<?php
$link_img_accordion = home_url().'/wp-content/themes/flatsome-child/img/admin/icon-builder/row.svg';
add_ux_builder_shortcode('MegaMenu', array(
    'type' => 'container',
    'allow' => array( 'MegaMenuLv2', 'row'),
    'name'      => __('Mega Menu'),
    'category'  => __('Mijuri'),
    'priority'  => 1,
    'thumbnail' =>  $link_img_accordion,
    'wrap'   => false,
    'inline' => true,
    'options'   => array(
        'class' => array(
            'type' => 'textfield',
            'heading' => 'Class',
        ),
    ),
));