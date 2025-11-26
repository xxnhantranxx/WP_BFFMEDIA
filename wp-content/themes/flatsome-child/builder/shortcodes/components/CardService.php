<?php
function CardService($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
        'img' => '',
        'title' => '',
        'description' => '',
        'link' => '',
    ), $atts));
    ob_start();
    $link = esc_url($link);
?>
    <div class="card-service">
        <div class="card-service-img">
            <a href="<?php echo $link; ?>" class="block _4yir">
                <img src="<?php echo wp_get_attachment_image_url($img,'full'); ?>">
            </a>
        </div>
        <div class="card-service-title">
            <a href="<?php echo $link; ?>" class="_7hjf block">
                <div class="_6cfb">
                    <h3 class="_2wqm-title"><?php echo $title; ?></h3>
                    <p class="_21ux"><?php echo $description; ?></p>
                </div>
                <i class="fa-regular fa-arrow-up-right"></i>
            </a>
        </div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('CardService', 'CardService');
