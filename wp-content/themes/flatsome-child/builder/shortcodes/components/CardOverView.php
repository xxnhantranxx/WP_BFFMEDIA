<?php
function CardOverView($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
        'img' => '',
        'title' => '',
        'link' => '',
    ), $atts));
    ob_start();
    $link = esc_url($link);
?>
    <div class="card-overview wow animate__animated animate__zoomIn" data-wow-duration="0.8s">
        <div class="card-overview-img">
            <a href="<?php echo $link; ?>" class="block _4yir">
                <img src="<?php echo wp_get_attachment_image_url($img,'full'); ?>">
            </a>
        </div>
        <div class="card-overview-title">
            <a href="<?php echo $link; ?>" class="_7hjf block">
                <h3 class="_2wqm-title"><?php echo $title; ?></h3>
                <i class="fa-regular fa-arrow-up-right"></i>
            </a>
        </div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('CardOverView', 'CardOverView');
