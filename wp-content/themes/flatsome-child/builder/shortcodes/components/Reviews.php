<?php
// Shortcode build
function Reviews($atts, $content)
{
    extract(shortcode_atts(array(
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="Reviews-home <?php echo $class; ?>">
        <div class="Reviews swiper">
            <?php
            // Check rows exists.
            if (have_rows('reviews-home', 'option')): ?>
                <div class="list_TeamMember swiper-wrapper">
                    <?php
                    // Loop through rows.
                    $i = 1;
                    while (have_rows('reviews-home', 'option')) : the_row();
                        // Load sub field value.
                        $name_review = get_sub_field('name_review', 'option');
                        $avatar_review = get_sub_field('avatar_review', 'option');
                        $content_review = get_sub_field('content_review', 'option');
                    ?>
                        <div class="box-review swiper-slide">
                            <div class="icon-box-img">
                                <img src="<?php echo $avatar_review; ?>" class="_7deo">
                            </div>
                            <div class="icon-box-text">
                                <div class="text_icon_box_ab5">
                                    <h3 class="_4xnf textLine-1"><?php echo $name_review; ?></h3>
                                    <p class="_5efc textLine-8"><?php echo $content_review; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php $i++;
                    endwhile; ?>
                </div>
                <div class="pagination_team"></div>
        </div>
        <div class="_2tka">
            <div class="cntt-button-reviews-prev cntt-button-slide">
            <i class="fa-regular fa-arrow-left"></i>
            </div>
            <div class="cntt-button-reviews-next cntt-button-slide">
                <i class="fa-regular fa-arrow-right"></i>
            </div>
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

add_shortcode('Reviews', 'Reviews');
