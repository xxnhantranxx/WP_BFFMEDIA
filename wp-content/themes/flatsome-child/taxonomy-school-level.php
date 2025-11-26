<?php get_header(); ?>
<?php echo do_shortcode('[block id="banner-album"]'); ?>
<section class="section HotTrendAlbum">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <div class="poring-bins">
                        <div class="_3bus swiper HotTrendAlbumSlider">
                            <div class="swiper-wrapper">
                                <?php
                                $args = array(
                                    'post_type' => 'album',
                                    'posts_per_page' => -1,
                                    'orderby' => 'date',
                                    'order' => 'DESC',
                                    'status' => 'publish',
                                    'meta_query' => array(
                                        array(
                                            'key' => 'hot_trend',
                                            'value' => '1',
                                            'compare' => '='
                                        )
                                    ),
                                );
                                $query = new WP_Query($args);
                                if ($query->have_posts()) :
                                    while ($query->have_posts()) : $query->the_post(); ?>
                                        <div class="box-album swiper-slide">
                                            <div class="BoxLeft">
                                                <div class="_5pkq"><span>Hot trend</span></div>
                                                <div class="_6qdm"><?php the_title(); ?></div>
                                                <div class="_0rty">
                                                    <ul class="_2qoy">
                                                        <?php if (get_field('class_room')): ?>
                                                            <li class="_0tmz"><span>Lớp: </span><?php echo get_field('class_room'); ?></li>
                                                        <?php endif; ?>
                                                        <?php if (get_field('location_album')): ?>
                                                            <li class="_0tmz"><span>Địa điểm: </span><?php echo get_field('location_album'); ?></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                    <?php if (get_field('description_album')): ?>
                                                        <p class="_2vpq"><?php echo get_field('description_album'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ButtonBookingGroup">
                                                    <a href="<?php the_permalink(); ?>" class="button Details button-bff"><span>Xem chi tiết</span></a>
                                                    <a href="#booking_album" class="button Booking" data-concept="<?php the_title(); ?>"><span>Đặt concept này</span></a>
                                                </div>
                                            </div>
                                            <div class="BoxRight">
                                                <div class="image-box">
                                                    <?php echo get_the_post_thumbnail(get_the_ID(), 'full'); ?>
                                                </div>
                                            </div>
                                        </div>
                                <?php endwhile;
                                endif;
                                wp_reset_postdata();
                                ?>
                            </div>
                        </div>
                        <div class="cntt-navigation">
                            <div class="cntt-button-album-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
                            <div class="cntt-button-album-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="section MainAlbum">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small ma-r1">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <div class="text heading_s8">
                        <h2><?php single_term_title(); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="row row-small ma-r2">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <div class="list-album">
                        <div class="filters">
                            <div class="_3jlm"><?php echo do_shortcode('[fe_widget]'); ?></div>
                            <div class="_7izj"><?php echo do_shortcode('[fe_chips]'); ?></div>
                        </div>
                        <div class="inner-item Album">
                            <?php
                            // Lấy query vars từ main query hiện tại
                            global $wp_query;
                            $args = $wp_query->query_vars;
                            
                            // Hàm filter để sắp xếp ưu tiên post public trước
                            if (!function_exists('archive_album_custom_orderby')) {
                                function archive_album_custom_orderby($orderby) {
                                    global $wpdb;
                                    return "CASE WHEN {$wpdb->posts}.post_password = '' THEN 0 ELSE 1 END ASC, {$wpdb->posts}.post_date DESC";
                                }
                            }
                            
                            // Thêm filter trước khi query
                            add_filter('posts_orderby', 'archive_album_custom_orderby', 10, 1);
                            
                            // Tạo custom query với args hiện tại
                            $custom_query = new WP_Query($args);
                            
                            // Remove filter sau khi query xong
                            remove_filter('posts_orderby', 'archive_album_custom_orderby', 10);
                            
                            if ($custom_query->have_posts()) :
                                while ($custom_query->have_posts()) : $custom_query->the_post();
                            ?>
                                    <div class="_5wxl<?php echo !empty($post->post_password) && post_password_required() ? ' private' : ''; ?>">
                                        <div class="_0bxy">
                                            <a href="<?php the_permalink(); ?>" title="<?php the_title(); ?>">
                                                <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'full'); ?>" alt="<?php the_title(); ?>" class="img-fluid">
                                                <?php if (post_password_required() && !empty($post->post_password)){ ?>
                                                    <div class="status-private-box">
                                                        <i class="fa-regular fa-lock"></i>
                                                        <span class="status-private">Được bảo vệ</span>
                                                    </div>
                                                <?php }else if(!post_password_required() && !empty($post->post_password)){ ?>
                                                    <div class="status-private-box">
                                                        <i class="fa-regular fa-unlock"></i>
                                                        <span class="status-private">Đã mở khoá</span>
                                                    </div>
                                                <?php } ?>
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
                                wp_reset_postdata();
                            endif;
                            ?>
                        </div>
                        <div class="pagination-cntt">
                            <?php flatsome_posts_pagination(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php echo do_shortcode('[block id="cam-nhan-khach-hang"]'); ?>
<span class="scroll-to" data-label="Scroll to: #booking_album" data-bullet="false" data-link="#booking_album" data-title="Booking Album" data-offset-type="custom" data-offset="75"><a name="booking_album"></a></span>
<?php echo do_shortcode('[block id="form-concept"]'); ?>
<?php echo do_shortcode('[block id="album-cung-xem"]'); ?>
<?php echo do_shortcode('[block id="video-hau-truong"]'); ?>
<?php echo do_shortcode('[block id="block-about"]'); ?>
<?php get_footer(); ?>