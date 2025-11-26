<?php

function ServiceOther($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();

?>
    <div class="ServiceOther">
        <div class="center-flex headding-category">
            <?php echo do_shortcode($content); ?>
            <div class="cntt-navigation">
                <div class="cntt-button-service-other-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
                <div class="cntt-button-service-other-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
            </div>
        </div>
        <div class="SlideServiceOther swiper">
            <?php
            // Check rows exists.
            if (have_rows('service_other', 'option')): ?>
                <div class="swiper-wrapper list_service_other">
                    <?php
                    // Loop through rows.
                    while (have_rows('service_other', 'option')) : the_row();
                        // Load sub field value.
                        $heading_service_other = get_sub_field('heading_service_other', 'option');
                        $image_service_other = get_sub_field('image_service_other', 'option');
                        $link_service_other = get_sub_field('link_service_other', 'option');
                    ?>
                        <div class="card-item swiper-slide">
                            <div class="card-service-other-img">
                                <a href="<?php echo $link_service_other; ?>" class="block _4yir">
                                    <img src="<?php echo $image_service_other; ?>">
                                </a>
                            </div>
                            <div class="card-service-other-title">
                                <a href="<?php echo $link_service_other; ?>" class="_7hjf block">
                                    <h3 class="_2wqm-title textLine-1"><?php echo $heading_service_other; ?></h3>
                                    <i class="fa-regular fa-arrow-up-right"></i>
                                </a>
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
        </div>
    </div>
<?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('ServiceOther', 'ServiceOther');
