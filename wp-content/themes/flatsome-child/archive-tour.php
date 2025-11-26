<?php get_header(); ?>
<?php echo do_shortcode('[block id="banner-tour"]'); ?>
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
                                    'post_type' => 'tour',
                                    'posts_per_page' => -1,
                                    'orderby' => 'date',
                                    'order' => 'DESC',
                                    'status' => 'publish',
                                    'meta_query' => array(
                                        array(
                                            'key' => 'hot_trend_tour',
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
                                                <div class="_5pkq"><span>Tour nổi bật</span></div>
                                                <div class="_6qdm"><?php the_title(); ?></div>
                                                <div class="_0rty">
                                                    <div class="_8hzx">
                                                        <span class="_9gkk"><?php echo get_field('price_tour'); ?></span>
                                                        <span class="_9cbq">/</span>
                                                        <span class="_5hhw"><?php echo get_field('quantity_tour'); ?></span>
                                                    </div>
                                                    <ul class="_2qoy">
                                                        <?php if (get_field('date_tour')): ?>
                                                            <li class="_0tmz"><span>Thời gian: </span><?php echo get_field('date_tour'); ?></li>
                                                        <?php endif; ?>
                                                        <?php if (get_field('location_tour')): ?>
                                                            <li class="_0tmz"><span>Địa điểm: </span><?php echo get_field('location_tour'); ?></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                    <?php if (get_field('description_short_tour')): ?>
                                                        <p class="_2vpq"><?php echo get_field('description_short_tour'); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ButtonBookingGroup">
                                                    <a href="#lightbox-album" class="button Booking" data-concept="<?php the_ID(); ?>"><span>Đặt tour kỷ yếu này</span></a>
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
<?php echo do_shortcode('[block id="loi-ich-tour"]'); ?>
<section class="section MainAlbum">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small ma-r1">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <div class="text heading_s8">
                        <h2>Danh sách tour</h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="row row-small ma-r2">
            <div class="col large-12 medium-12 small-12 pb-0">
                <div class="col-inner">
                    <div class="list-tour">
                        <div class="inner-item Tour">
                            <?php
                            if (have_posts()) :
                                while (have_posts()) : the_post();
                            ?>
                                    <div class="box-tour">
                                        <a href="<?php the_permalink(); ?>" class="block box-tour-item">
                                            <div class="box-tour-img">
                                                <div class="image-box">
                                                    <?php
                                                    if (get_field('image_tour')) {
                                                        $image_tour = get_field('image_tour');
                                                    } else if (get_the_post_thumbnail_url(get_the_ID(), 'full')) {
                                                        $image_tour = get_the_post_thumbnail_url(get_the_ID(), 'full');
                                                    } else {
                                                        $image_tour = get_template_directory_uri() . '/assets/img/placeholder-image.jpg';
                                                    }
                                                    ?>
                                                    <img src="<?php echo esc_url($image_tour); ?>" alt="<?php the_title(); ?>">
                                                    <div class="box-tour-img-overlay"></div>
                                                </div>
                                                <div class="category-name">
                                                    <span class="category-name-text"><?php echo get_the_terms(get_the_ID(), 'tour-category')[0]->name; ?></span>
                                                </div>
                                            </div>
                                            <div class="box-tour-text">
                                                <h3 class="_2wqm-title textLine-2"><?php the_title(); ?></h3>
                                                <div class="meta">
                                                    <div class="_8zqa"><?php echo get_field('price_tour'); ?></div>
                                                    <span class="separator">/</span>
                                                    <div class="_1foo"><?php echo get_field('quantity_tour'); ?></div>
                                                </div>
                                            </div>
                                        </a>
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
<?php echo do_shortcode('[block id="mau"]'); ?>
<?php echo do_shortcode('[block id="cam-nhan-khach-hang"]'); ?>
<?php echo do_shortcode('[block id="faqs-tour"]'); ?>
<?php echo do_shortcode('[block id="form-tour"]'); ?>
<?php get_footer(); ?>