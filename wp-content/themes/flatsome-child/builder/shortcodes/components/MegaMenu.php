<?php
function MegaMenu($atts,$content){
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();
    ?>
    <div class="wapper-mage-menu <?php echo $class; ?>">
        <?php echo do_shortcode($content); ?>
    </div>
    <?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('MegaMenu', 'MegaMenu');