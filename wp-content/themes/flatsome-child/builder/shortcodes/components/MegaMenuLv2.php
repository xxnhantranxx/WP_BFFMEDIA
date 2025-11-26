<?php
function MegaMenuLv2($atts,$content){
    extract(shortcode_atts(array(
        'label' => '',
        'class' => '',
    ), $atts));
    ob_start();
    ?>
    <div class="_1rgv MegaMenuLv2">
        <div class="_6krl">
            <span class="_1000 textLine-1"><?php echo $label; ?></span>
        </div>
        <div class="_4loi">
            <?php echo do_shortcode($content); ?>
        </div>
    </div>
    <?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('MegaMenuLv2', 'MegaMenuLv2');