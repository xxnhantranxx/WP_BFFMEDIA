<?php
// Shortcode build
function QuyTrinh($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="QuyTrinh swiper <?php echo $class; ?>">
        <?php
        // Check rows exists.
        if (have_rows('quy_trinh', 'option')): ?>
            <div class="list_QuyTrinh swiper-wrapper">
                <?php
                // Loop through rows.
                while (have_rows('quy_trinh', 'option')) : the_row();
                    // Load sub field value.
                    $thu_tu = get_sub_field('thu_tu', 'option');
                    $ten_quy_trinh = get_sub_field('ten_quy_trinh', 'option');
                    $mo_ta_quy_trinh = get_sub_field('mo_ta_quy_trinh', 'option'); ?>
                    <div class="item_QuyTrinh swiper-slide">
                        <div class="_0ngs"> 
                            <div class="_6cyu">
                                <div class="_7rar">
                                    <div class="_7vni"></div>
                                </div>
                            </div>
                            <div class="_8oao">
                                <div class="_3jtx">
                                    <span class="_5ipg"><?php echo $thu_tu; ?></span>
                                </div>
                                <h3 class="_1wwt"><?php echo $ten_quy_trinh; ?></h3>
                                <div class="_7soq"><?php echo $mo_ta_quy_trinh; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="_2tka">
                <div class="cntt-button-prev cntt-button-slide"><i class="fa-light fa-arrow-left-long"></i></div>
                <div class="cntt-button-next cntt-button-slide"><i class="fa-light fa-arrow-right-long"></i></div>
            </div>
    </div>
<?php
        // No value.
        else :
        // Do something...
        endif; ?>

<?php echo do_shortcode($content);
    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('QuyTrinh', 'QuyTrinh');
