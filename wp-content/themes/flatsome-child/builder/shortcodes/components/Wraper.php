<?php
function Wraper($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="Wraper <?php echo $class; ?>">
        <?php echo do_shortcode($content); ?>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('Wraper', 'Wraper');
