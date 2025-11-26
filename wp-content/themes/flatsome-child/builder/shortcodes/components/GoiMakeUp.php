<?php

function GoiMakeUp($atts)
{
    extract(shortcode_atts(array(
        'ids' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="GoiMakeUp <?php echo $class; ?>">
        <div class="center-flex headding-category">
            <div class="cntt-navigation">
                <div class="cntt-button-make-up-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
                <div class="cntt-button-make-up-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
            </div>
        </div>
        <div class="SlideMakeUp swiper">
            <div class="swiper-wrapper list_GoiMakeUp">
                <?php
                // Check rows exists.
                if (have_rows('MakeUp', 'option')): ?>
                    <?php
                    // Loop through rows.
                    while (have_rows('MakeUp', 'option')) : the_row();
                        // Load sub field value.
                        $NameMakeUp = get_sub_field('NameMakeUp', 'option');
                        $DescriptionMakeUp = get_sub_field('DescriptionMakeUp', 'option');
                        $PriceMakeUp = get_sub_field('PriceMakeUp', 'option');
                        $ContentMakeUp = get_sub_field('ContentMakeUp', 'option');
                    ?>
                        <div class="swiper-slide GoiMakeUpItem">
                            <div class="_8qpl">
                                <div class="NameMakeUp"><?php echo $NameMakeUp; ?></div>
                                <div class="DescriptionMakeUp"><?php echo $DescriptionMakeUp; ?></div>
                                <div class="PriceMakeUp">
                                    <span class="_9wdc"><?php echo $PriceMakeUp; ?></span>
                                    <span class="_0qqu">/Buổi chụp</span>
                                </div>
                                <div class="ContentMakeUp"><?php echo $ContentMakeUp; ?></div>
                            </div>
                            <div class="_5ncd">
                                <a class="button Booking button-dangky" href="#booking_package" data-package-make-up="<?php echo $NameMakeUp; ?>"><span>Đặt Makeup cho lớp tôi</span></a>
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

add_shortcode('GoiMakeUp', 'GoiMakeUp');
