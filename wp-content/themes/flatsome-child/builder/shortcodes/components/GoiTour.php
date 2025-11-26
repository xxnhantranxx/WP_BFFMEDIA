<?php

function GoiTour($atts, $content)
{
    extract(shortcode_atts(array(
        'ids' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="GoiTour <?php echo $class; ?>">
        <div class="center-flex headding-category">
            <div class="heading-icon">
                <?php echo do_shortcode($content); ?>
            </div>
            <div class="cntt-navigation">
                <div class="cntt-button-thue-tours-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
                <div class="cntt-button-thue-tours-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
            </div>
        </div>
        <div class="SlideGoiTour swiper">
            <div class="swiper-wrapper list_GoiTour">
                <?php
                // Check rows exists.
                if (have_rows('GoiTour', 'option')): ?>
                    <?php
                    // Loop through rows.
                    while (have_rows('GoiTour', 'option')) : the_row();
                        // Load sub field value.
                        $AnhGoiTour = get_sub_field('AnhGoiTour', 'option');
                        $TenGoiTour = get_sub_field('TenGoiTour', 'option');
                        $LinkTour = get_sub_field('LinkTour', 'option');
                    ?>
                        <div class="swiper-slide GoiTourItem">
                            <div class="AnhGoiTour"><img src="<?php echo $AnhGoiTour; ?>"></div>
                            <div class="_6xtl">
                                <h3 class="_4nhj"><?php echo $TenGoiTour; ?></h3>
                                <div class="_2ljx">
                                <?php
                                    if (have_rows('HangMucGoiTour', 'option')): 
                                            while (have_rows('HangMucGoiTour', 'option')) : the_row();
                                                $StudenGoiTour = get_sub_field('StudenGoiTour', 'option');
                                                $PriceGoiTour = get_sub_field('PriceGoiTour', 'option');
                                            ?>
                                                <div class="_6wxm">
                                                    <div class="_2oek"><?php echo $StudenGoiTour; ?></div>
                                                    <div class="_9fia"><?php echo $PriceGoiTour; ?></div>
                                                </div>
                                            <?php endwhile;
                                    endif; ?>
                                </div>
                            </div>
                            <div class="_5ncd">
                                <a class="button button-dangky" href="#" data-name="<?php echo $TenGoiTour; ?>"><span>Tư vấn tour này </span></a>
                                <a class="button-link" href="<?php echo $LinkTour; ?>"><span>Xem chi tiết</span></a>
                            </div>
                        </div>
                <?php
                    endwhile;
                endif;
                ?>
            </div>
        </div>
    <?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('GoiTour', 'GoiTour');
