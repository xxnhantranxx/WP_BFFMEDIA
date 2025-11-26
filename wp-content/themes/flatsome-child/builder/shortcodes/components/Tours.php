<?php

function Tours($atts, $content)
{
    extract(shortcode_atts(array(
        'ids' => '',
        'class' => '',
    ), $atts));
    ob_start();
?>
    <div class="Tours <?php echo $class; ?>">
        <div class="center-flex headding-category">
            <div class="heading-icon">
                <?php echo do_shortcode($content); ?>
            </div>
            <div class="cntt-navigation">
                <div class="cntt-button-tours-prev cntt-button"><i class="fa-light fa-arrow-left-long"></i></div>
                <div class="cntt-button-tours-next cntt-button"><i class="fa-light fa-arrow-right-long"></i></div>
            </div>
        </div>
        <div class="SlideTours swiper">
            <div class="swiper-wrapper list_tours">
                <?php
                // Chuyển đổi chuỗi IDs thành mảng số nguyên
                $ids_array = !empty($ids) ? array_map('intval', explode(',', $ids)) : array();
                
                $args = array(
                    'post_type' => 'tour',
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

                        <div class="box-tour swiper-slide">
                            <a href="<?php the_permalink(); ?>" class="block box-tour-item">
                                <div class="box-tour-img">
                                    <div class="image-box">
                                        <?php
                                        if(get_field('image_tour')){
                                            $image_tour = get_field('image_tour');
                                        } else if (get_the_post_thumbnail_url(get_the_ID(), 'full')){
                                            $image_tour = get_the_post_thumbnail_url(get_the_ID(), 'full');
                                        } else {
                                            $image_tour = get_template_directory_uri().'/assets/img/placeholder-image.jpg';
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
                endif;
                ?>
                <?php wp_reset_postdata(); ?>
            </div>
        </div>
        <div class="button-view-all">
            <div class="ButtonBFF">
                <a href="<?php echo home_url('/tours'); ?>" class="button button-bff ">
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

add_shortcode('Tours', 'Tours');
