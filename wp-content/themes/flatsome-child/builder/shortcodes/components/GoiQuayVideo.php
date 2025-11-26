<?php

function GoiQuayVideo($atts)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();

?>
    <div class="GoiQuayVideo <?php echo $class; ?>">
        <?php
        // Check rows exists.
        if (have_rows('VideosPricing', 'option')): ?>
            <div class="ListPackage">
                <?php
                // Loop through rows.
                while (have_rows('VideosPricing', 'option')) : the_row();
                    // Load sub field value.
                    $NameVideosPricing = get_sub_field('NameVideosPricing', 'option');
                    $DescriptionVideosPricing = get_sub_field('DescriptionVideosPricing', 'option');
                    $PriceVideosPricing = get_sub_field('PriceVideosPricing', 'option');
                    $PricePerPersionVideos = get_sub_field('PricePerPersionVideos', 'option');
                    $HightlightVideosPricing = get_sub_field('HightlightVideosPricing', 'option');
                    $ImageDetailYearBook = get_sub_field('ImageDetailYearBook', 'option');
                ?>
                    <div class="PackageItem <?php if ($HightlightVideosPricing): ?>highlight<?php endif; ?>">
                        <div class="NameYearBook"><?php echo $NameVideosPricing; ?></div>
                        <div class="DescriptionYearBook"><?php echo $DescriptionVideosPricing; ?></div>
                        <div class="PriceYearBook">
                            <?php echo $PriceVideosPricing; ?>
                            <?php
                            if ($PricePerPersionVideos): ?><span>/người</span>
                        <?php endif; ?>
                        </div>
                        <div class="HangMuc">
                            <?php
                            if (have_rows('HangMucVideosPricing', 'option')): ?>
                                <ul>
                                    <?php
                                    while (have_rows('HangMucVideosPricing', 'option')) : the_row();
                                        $DauMuc = get_sub_field('DauMucVideosPricing', 'option');
                                    ?>
                                        <li><?php echo $DauMuc; ?></li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="_5ncd">
                            <a class="button Booking button-dangky" href="#booking_package" data-package-video="<?php echo 'Video: '.$NameVideosPricing; ?>"><span>Chọn gói này</span></a>
                            <a class="button-link lightbox image-lightbox lightbox-gallery zoom-image block" href="<?php echo $ImageDetailYearBook; ?>" data-lightbox="lightbox-gallery"><span>Xem chi tiết</span></a>
                        </div>
                    </div>
            <?php
                endwhile;
            endif;
            ?>
    </div>
        <?php

        $contentShortcode = ob_get_contents();
        ob_end_clean();
        return $contentShortcode;
    }

    add_shortcode('GoiQuayVideo', 'GoiQuayVideo');
