<?php

function VideoList($atts)
{
    extract(shortcode_atts(array(
        'ids' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="VideoList <?php echo $class; ?>">
        <div class="list-videos">
            <?php
            // Chuyển đổi chuỗi IDs thành mảng số nguyên
            $ids_array = !empty($ids) ? array_map('intval', explode(',', $ids)) : array();

            $args = array(
                'post_type' => 'videos',
                'posts_per_page' => 20,
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
                while ($the_query->have_posts()) : $the_query->the_post(); ?>
                    <div class="VideoWebsite">
                        <div class="banner-video">
                            <?php 
                                $embed = get_field('url_youtube'); // https://www.youtube.com/embed/zvirUg7DfqA?feature=oembed 
                                // Lấy id từ url youtube và gán vào biến $embed_code
                                $embed_code = '';
                                if (!empty($embed)) {
                                    // Hỗ trợ cả link dạng https://www.youtube.com/embed/xxxxxx?feature=... hoặc dạng share bình thường
                                    // Chuẩn hóa đường dẫn
                                    $pattern = '%(?:\/embed\/|v=|\.be\/)([A-Za-z0-9_-]{11})%';
                                    if (preg_match($pattern, $embed, $matches)) {
                                        $embed_code = $matches[1];
                                    } else {
                                        // fallback: nếu chỉ có mã id
                                        if (strlen($embed) === 11) {
                                            $embed_code = $embed;
                                        }
                                    }
                                }
                                // Lấy thumbnail từ YouTube theo embed_code
                                $thumbnail_url = '';
                                if (!empty($embed_code)) {
                                    $thumbnail_url = 'https://img.youtube.com/vi/' . $embed_code . '/maxresdefault.jpg';
                                }
                            ?>
                            <img src="<?php echo $thumbnail_url; ?>" class="_1arc">
                            <div class="video-button-wrapper">
                                <a href="<?php echo 'https://www.youtube.com/watch?v=' . $embed_code; ?>" class="_1blk button icon circle is-outline is-xlarge">
                                    <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/video_2.png" alt="play-icon">
                                </a>
                            </div>
                        </div>
                        <div class="_9iih">
                            <h2 class="textLine-2"><?php the_title(); ?></h2>
                        </div>
                    </div>
            <?php
                endwhile;
            endif;
            ?>
            <?php wp_reset_postdata(); ?>
        </div>
        <div class="button-view-all">
            <div class="ButtonBFF">
                <a href="<?php echo home_url('/videos'); ?>" class="button button-bff ">
                    <span class="_7vko uppercase">Xem tất cả</span>
                    <i class="fa-regular fa-arrow-up-right"></i>
                </a>
            </div>
        </div>
    </div>
<?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('VideoList', 'VideoList');
