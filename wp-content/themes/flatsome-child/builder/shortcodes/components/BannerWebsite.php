<?php
function BannerWebsite($atts, $content)
{
    extract(shortcode_atts(array(
        'img' => '',
        'class' => '',
    ), $atts));
    if (empty($img)) {
        return '<div class="uxb-no-content uxb-image">Upload Image...</div>';
    }
    ob_start();
?>
    <div class="BannerWebsite <?php echo $class; ?>">
        <div class="banner-view">
            <div class="image-bn">
                <img src="<?php echo wp_get_attachment_image_url($img,'full'); ?>">
            </div>
        </div>
        <div class="content-banner">
            <div class="content-inner">
                <?php echo do_shortcode($content) ?>
            </div>
        </div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('BannerWebsite', 'BannerWebsite');
