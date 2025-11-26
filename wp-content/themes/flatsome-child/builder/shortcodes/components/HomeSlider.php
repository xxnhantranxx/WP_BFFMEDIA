<?php
function HomeSlider($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="HomeSlider aos-init relative -mt-75px swiper <?php echo $class; ?>">
        <?php
        // Check rows exists.
        if (have_rows('slide_home', 'option')): ?>
            <div class="swiper-wrapper list_homeBanner">
                <?php
                // Loop through rows.
                while (have_rows('slide_home', 'option')) : the_row();
                    // Load sub field value.
                    $image_slide_home = get_sub_field('image_slide_home', 'option');
                    $heading_slide_home = get_sub_field('heading_slide_home', 'option');
                    $description_slide_banner = get_sub_field('description_slide_banner', 'option');
                    $text_button_slide_home = get_sub_field('text_button_slide_home', 'option');
                    $link_button_slide_home = get_sub_field('link_button_slide_home', 'option');
                ?>
                    <div class="swiper-slide item_homeBanner relative">
                        <div class="img_background z-1">
                            <img src="<?php echo $image_slide_home; ?>" class="w-full h-screen object-cover">
                        </div>
                        <div class="content_slide_home container absolute bottom-30 left-1p2 -translate-x-1p2 z-2">
                            <h2 class="heading_slide_home animate__animated animate__fadeInUp"><?php echo $heading_slide_home; ?></h2>
                            <?php
                            if($description_slide_banner){ ?>
                                <p class="description_slide_banner animate__animated animate__fadeInUp animate__delay-1s"><?php echo $description_slide_banner; ?></p>
                            <?php } 
                            if($link_button_slide_home){ ?>
                                <a href="<?php echo $link_button_slide_home; ?>" class="button button_slide_home animate__animated animate__bounceIn animate__delay-2s"><span><?php echo $text_button_slide_home; ?></span><i class="fa-regular fa-arrow-up-right"></i></a>
                            <?php } ?>
                        </div>
                    </div>
                <?php
                endwhile; ?>
            </div>
        <?php
        // No value.
        else :
        // Do something...
        endif; ?>
        <div class="_9byf">
            <div class="container">
                <div class="_9tfr">
                    <div class="pagination-and-navigation">
                        <div class="navigation-prev"><i class="fa-regular fa-arrow-left"></i></div>
                        <div class="pagination-home-slider"></div>
                        <div class="navigation-next"><i class="fa-regular fa-arrow-right"></i></div>
                    </div>
                    <div class="autoplay-progress">
                        <svg viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="49"></circle>
                        </svg>
                        <span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('HomeSlider', 'HomeSlider');
