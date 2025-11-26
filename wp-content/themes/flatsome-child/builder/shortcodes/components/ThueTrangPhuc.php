<?php

function ThueTrangPhuc($atts, $content)
{
    extract(shortcode_atts(array(
        'ids' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="ThueTrangPhuc <?php echo $class; ?>">
        <div class="cntt-navigation">
            <div class="cntt-button-thue-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
            <div class="cntt-button-thue-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
        </div>
        <div class="SlideThueTrangPhuc swiper">
            <div class="swiper-wrapper list_ThueTrangPhuc">
                <?php
                // Check rows exists.
                if (have_rows('ThueTrangPhuc', 'option')): ?>
                    <?php
                    // Loop through rows.
                    while (have_rows('ThueTrangPhuc', 'option')) : the_row();
                        // Load sub field value.
                        $AnhTrangPhuc = get_sub_field('AnhTrangPhuc', 'option');
                        $TenGoiThueTrangPhuc = get_sub_field('TenGoiThueTrangPhuc', 'option');
                        $DesciptionTrangPhuc = get_sub_field('DesciptionTrangPhuc', 'option');
                        $GiaGoiTrangPhuc = get_sub_field('GiaGoiTrangPhuc', 'option');
                        $PricePerDay = get_sub_field('PricePerDay', 'option');
                        $DetailsTrangPhuc = get_sub_field('DetailsTrangPhuc', 'option');
                    ?>
                        <div class="swiper-slide PackageItem">
                            <div class="AnhTrangPhuc"><img src="<?php echo $AnhTrangPhuc; ?>"></div>
                            <div class="_1hrl">
                                <div class="_1noh"><h3 class="_0icl"><?php echo $TenGoiThueTrangPhuc; ?></h3></div>
                                <div class="_4rap"><?php echo $DesciptionTrangPhuc; ?></div>
                                <div class="_1obr">
                                    <span class="_1lbn"><?php echo $GiaGoiTrangPhuc; ?></span>
                                    <?php if ($PricePerDay): ?>
                                        <span>/ngày</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="_5ncd">
                                <a class="button button-dangky" href="#" data-name="<?php echo $TenGoiThueTrangPhuc; ?>"><span>Chọn gói này</span></a>
                                <a class="button-link lightbox image-lightbox lightbox-gallery zoom-image block" href="<?php echo $DetailsTrangPhuc; ?>" data-lightbox="lightbox-gallery"><span>Xem chi tiết</span></a>
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

add_shortcode('ThueTrangPhuc', 'ThueTrangPhuc');
