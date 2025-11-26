<?php
function BoxTrust($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
        'number' => '',
        'title' => '',
    ), $atts));
    ob_start();
?>
    <div class="BoxTrust <?php echo $class; ?>">
        <div class="number"><?php echo $number; ?></div>
        <div class="title"><?php echo $title; ?></div>
        <div class="content-trust"><?php echo do_shortcode($content); ?></div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('BoxTrust', 'BoxTrust');
