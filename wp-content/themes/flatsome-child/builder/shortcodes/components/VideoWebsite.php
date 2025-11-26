<?php
function VideoWebsite($atts, $content)
{
    extract(shortcode_atts(array(
        'link' => '',
        'title' => '',
        'img' => '',
        'img_mobile' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="VideoWebsite <?php echo $class; ?>">
        <div class="banner-video">
            <img src="<?php echo wp_get_attachment_image_url($img,'full'); ?>" class="_1arc hide-for-small">
            <img src="<?php echo wp_get_attachment_image_url($img_mobile,'full'); ?>" class="_1arc show-for-small">
            <div class="video-button-wrapper">
                <a href="<?php echo $link; ?>" class="_1blk button icon circle is-outline is-xlarge">
                    <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/video_2.png" alt="play-icon">
                </a>
            </div>
        </div>
        <div class="_9iih">
            <h2 class="textLine-2"><?php echo wp_kses_post($title); ?></h2>
        </div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('VideoWebsite', 'VideoWebsite');
