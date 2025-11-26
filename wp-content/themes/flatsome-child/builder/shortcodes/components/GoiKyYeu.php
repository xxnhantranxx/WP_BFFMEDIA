<?php

function GoiKyYeu($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();

?>
    <div class="GoiKyYeu <?php echo $class; ?>">
        <?php
        // Check rows exists.
        if (have_rows('YearBook', 'option')): ?>
            <div class="ListPackage">
                <?php
                // Loop through rows.
                while (have_rows('YearBook', 'option')) : the_row();
                    // Load sub field value.
                    $ClassYearBook = get_sub_field('ClassYearBook', 'option');
                    $NameYearBook = get_sub_field('NameYearBook', 'option');
                    $DescriptionYearBook = get_sub_field('DescriptionYearBook', 'option');
                    $PriceYearBook = get_sub_field('PriceYearBook', 'option');
                    $PricePerPersion = get_sub_field('PricePerPersion', 'option');
                    $HightlightYearBook = get_sub_field('HightlightYearBook', 'option');
                    $ImageDetailYearBook = get_sub_field('ImageDetailYearBook', 'option');
                ?>
                    <div class="PackageItem <?php if ($HightlightYearBook): ?>highlight<?php endif; ?>">
                        <div class="ClassYearBook"><?php echo $ClassYearBook; ?></div>
                        <div class="NameYearBook"><?php echo $NameYearBook; ?></div>
                        <div class="DescriptionYearBook"><?php echo $DescriptionYearBook; ?></div>
                        <div class="PriceYearBook">
                            <?php echo $PriceYearBook; ?>
                            <?php
                            if ($PricePerPersion): ?><span>/người</span>
                        <?php endif; ?>
                        </div>
                        <div class="HangMuc">
                            <?php
                            if (have_rows('hạng_mục', 'option')): ?>
                                <ul>
                                    <?php
                                    while (have_rows('hạng_mục', 'option')) : the_row();
                                        $DauMuc = get_sub_field('DauMuc', 'option');
                                    ?>
                                        <li><?php echo $DauMuc; ?></li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="_5ncd">
                            <a class="button button-dangky" href="#" data-name="<?php echo $NameYearBook; ?>"><span>Chọn gói này</span></a>
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

    add_shortcode('GoiKyYeu', 'GoiKyYeu');
