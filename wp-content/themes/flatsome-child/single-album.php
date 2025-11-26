<?php get_header(); ?>
<section class="section section-27">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small s27_r1">
            <div class="col large-6 medium-6 small-12 s27_r1_c1 pb-0">
                <div class="col-inner">
                    <div class="_6bsf">
                        <div class="_0wch rating">
                            <div class="_1mgt">
                                <img src="<?php echo get_stylesheet_directory_uri(); ?>/img/icons/star_white.png" alt="Star" class="icon-star">
                                <span class="avg_rating">
                                    <span class="avg_rating_value">
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
                                    <span class="max_rating_value">/5</span>
                                </span>
                            </div>
                            <div class="_1raj">
                                <span class="rating_count">
                                    <?php
                                    // Lấy số lượng votes từ plugin KK Star Ratings
                                    $rating_count = get_post_meta($post_id, '_kksr_casts', true);
                                    // Nếu không có, thử lấy từ count_default
                                    if (!$rating_count || $rating_count === '') {
                                        $rating_count = get_post_meta($post_id, '_kksr_count_default', true);
                                    }
                                    echo esc_html($rating_count ? $rating_count : '0');
                                    ?>
                                </span> lượt bình chọn
                            </div>
                        </div>
                        <div class="_3urf">
                            <div class="_1ppj"><h1 class="_8rgd"><?php the_title(); ?></h1></div>
                            <div class="_2pbi">
                                <?php if (get_field('class_room')): ?>
                                    <div class="_3qou">
                                        <div class="_6tpp">Lớp</div>
                                        <div class="_7oqo"><?php echo get_field('class_room'); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (get_field('location_album')): ?>
                                    <div class="_3qou">
                                        <div class="_6tpp">Địa điểm</div>
                                        <div class="_7oqo"><?php echo get_field('location_album'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (get_field('description_album')): ?>
                                <div class="_9ztx">
                                    <?php echo get_field('description_album'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="_8izp">
                            <div class="_3ldw">
                                <div class="_1ywd">
                                    <div class="_2vyk">
                                        <span>Thích concept này</span>
                                        <i class="fa-regular fa-thumbs-up"></i>
                                    </div>
                                    <div class="_6zjw"><?php echo kk_star_ratings();  ?></div>
                                </div>
                                <div class="_0nng">
                                    <span class="view_count"><?php echo do_shortcode('[post-views]'); ?></span><span class="_5ayb">người đã xem concept này</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col large-6 medium-6 small-12 s27_r1_c2 pb-0">
                <div class="_7qbw<?php echo (!empty($post->post_password) && post_password_required()) ? ' locked' : ''; ?> col-inner">
                    <div class="_5hie"><img src="<?php echo get_field('image_card'); ?>" alt="<?php the_title(); ?>"></div>
                    <?php if (!empty($post->post_password)): ?>
                        <?php if (post_password_required()): ?>
                            <div class="status-private-box">
                                <i class="fa-regular fa-lock"></i>
                                <span class="status-private">Được bảo vệ</span>
                            </div>
                        <?php else: ?>
                            <div class="status-private-box">
                                <i class="fa-regular fa-unlock"></i>
                                <span class="status-private">Đã mở khoá</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
if (post_password_required()) { ?>
    <section class="section section-password">
        <div class="section-bg fill"></div>
        <div class="section-content relative">
            <div class="row row-small">
                <div class="col large-12 medium-12 small-12 pb-0">
                    <div class="col-inner">
                        <?php echo get_the_password_form(); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php } else { ?>
<?php the_content(); ?>
<section class="section Galleryalbum">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small row-gallery">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <?php
                    if (get_field('dung_driver')) { ?>
                        <div class="_7bkq">
                            <?php echo get_field('album_google_driver'); ?>
                        </div>
                    <?php } else { ?>
                        <div class="gallery-inner">
                            <?php
                            $images = get_field('gallery_album');
                            if ($images): ?>
                                <div class="grid">
                                    <?php foreach ($images as $image): ?>
                                        <div class="grid-item">
                                            <a href="<?php echo $image; ?>" class="image-lightbox lightbox-gallery zoom-image block">
                                                <img src="<?php echo $image; ?>" alt="<?php the_title(); ?>">
                                                <figcaption class="dgwt-jg-caption">
                                                    <span class="dgwt-jg-caption__icon">
                                                        <svg width="29" height="29" xmlns="http://www.w3.org/2000/svg">
                                                            <g>
                                                                <line stroke-linecap="null" stroke-linejoin="null" y2="29.302036" x2="14.46875" y1="-0.034375" x1="14.46875" fill-opacity="null" stroke-opacity="null" stroke-width="1.5" stroke="#FFFFFF" fill="none"></line>
                                                                <line stroke-linecap="null" stroke-linejoin="null" y2="14.590625" x2="29.290947" y1="14.590625" x1="-0.09375" fill-opacity="null" stroke-opacity="null" stroke-width="1.5" stroke="#FFFFFF" fill="none"></line>
                                                            </g>
                                                        </svg>
                                                    </span>
                                                </figcaption>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php } ?>
<?php echo do_shortcode('[block id="cam-nhan-khach-hang"]'); ?>
<?php echo do_shortcode('[block id="form-concept"]'); ?>
<?php echo do_shortcode('[block id="album-cung-xem"]'); ?>
<?php echo do_shortcode('[block id="tin-tuc"]'); ?>
<?php get_footer(); ?>