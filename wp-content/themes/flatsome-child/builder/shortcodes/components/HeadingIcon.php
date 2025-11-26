<?php
function HeadingIcon($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
        'subtitle' => '',
        'title' => '',
        'description' => '',
    ), $atts));
    ob_start();
?>
    <div class="HeadingIcon <?php echo $class; ?>">
        <h4 class="subtitle wow animate__animated animate__fadeInUp" data-wow-duration="0.8s" data-wow-delay="0.1s">
            <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/icon_white_bffmedia.svg" class="_4yix">
            <span><?php echo $subtitle; ?></span>
        </h4>
        <h2 class="title wow animate__animated animate__fadeInUp" data-wow-duration="0.8s" data-wow-delay="0.3s"><?php echo $title; ?></h2>
        <?php if($description): ?>
            <p class="description wow animate__animated animate__fadeInUp" data-wow-duration="0.8s" data-wow-delay="0.5s"><?php echo $description; ?></p>
        <?php endif; ?>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('HeadingIcon', 'HeadingIcon');
