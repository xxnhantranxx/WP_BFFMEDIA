<?php
function ButtonBFF($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
        'title' => '',
        'link' => '',
    ), $atts));
    ob_start();
    $link = esc_url($link);
?>
    <div class="ButtonBFF">
        <a href="<?php echo $link; ?>" class="button button-bff <?php echo $class; ?>">
            <span class="_7vko"><?php echo $title; ?></span>
            <i class="fa-regular fa-arrow-up-right"></i>
        </a>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('ButtonBFF', 'ButtonBFF');
