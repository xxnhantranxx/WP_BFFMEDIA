<?php

function Album($atts)
{
    extract(shortcode_atts(array(
        'ids' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="Album <?php echo $class; ?>">
        <?php
        // Chuyển đổi chuỗi IDs thành mảng số nguyên
        $ids_array = !empty($ids) ? array_map('intval', explode(',', $ids)) : array();
        
        $args = array(
            'post_type' => 'album',
            'posts_per_page' => 6,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Chỉ thêm post__in nếu có IDs
        if (!empty($ids_array)) {
            $args['post__in'] = $ids_array;
        }
        $the_query = new WP_Query($args);

        // The Loop
        if ($the_query->have_posts()) :
            $i = 0;
            while ($the_query->have_posts()) : $the_query->the_post(); $i++; ?>
                <div class="_5wxl wow animate__animated animate__zoomIn" data-wow-duration="0.5s" data-wow-delay="<?php echo ($i - 1) % 3 * 0.2; ?>s">
                    <div class="_0bxy">
                        <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                            <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'full'); ?>" alt="<?php the_title(); ?>" class="img-fluid">
                        </a>
                        <div class="rating">
                            <span class="avg_rating">
                            <?php
                            // Lấy điểm đánh giá trung bình từ plugin kk Star Ratings
                            $post_id = get_the_ID();
                            $avg_rating = get_post_meta($post_id, '_kksr_avg_default', true);
                            if ($avg_rating && is_numeric($avg_rating)) {
                                $rating = (float)$avg_rating;
                            } else {
                                $rating = 5.0; // Mặc định 5.0 nếu chưa có rating
                            }
                            echo esc_html(number_format($rating, 1));
                            ?>
                            </span>
                            <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/Star.png" alt="Star" class="icon-star">
                        </div>
                    </div>
                    <div class="_9zfi">
                        <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                            <h4 class="textLine-1"><?php the_title(); ?></h4>
                            <i class="fa-regular fa-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
        <?php
            endwhile;
        endif;

        // Reset Post Data
        wp_reset_postdata();
        ?>
    </div>
<?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('Album', 'Album');
