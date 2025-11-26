<?php get_header(); ?>
<section class="section SinglePost">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small single-post-r1">
            <div class="col large-9 medium-9 small-12 single-post-r1_c1 pb-0">
                <div class="col-inner SinglePostContent">
                    <div class="single-post-title">
                        <h1><?php the_title(); ?></h1>
                    </div>
                    <div class="_5ehx">
                        <div class="single-post-meta">
                            <div class="single-post-date">
                                <span class="_9mwk">Ngày đăng:</span>
                                <span class="_2ylh"><?php echo get_the_date('d/m/Y'); ?></span>
                                <span class="_0vgy">Lúc <?php echo get_the_time('H:i'); ?></span>
                            </div>
                            <div class="count-view">
                                <span class="_4afh"><?php echo do_shortcode('[post-views]'); ?></span>
                                <span class="_0pkb">lượt xem</span>
                            </div>
                        </div>
                        <div class="SharePost">
                            <div class="_2lfc"><span class="_8yeh">Chia sẻ</span><i class="fa-light fa-share-nodes"></i></div>
                            <div class="_2dzo">
                                <?php echo do_shortcode('[share]'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="single-post-content">
                        <?php the_content(); ?>
                    </div>
                </div>
            </div>
            <div class="col large-3 medium-3 small-12 single-post-r1_c2 pb-0">
                <div class="col-inner SinglePostSidebar">
                    <div class="single-post-sidebar-title">
                        <h2>Bài viết liên quan</h2>
                    </div>
                    <div class="single-post-sidebar-content">
                        <?php
                        // Lấy tất cả các danh mục của bài viết hiện tại
                        $categories = get_the_terms(get_the_ID(), 'category');
                        $category_ids = array();
                        if ($categories && !is_wp_error($categories)) {
                            foreach ($categories as $category) {
                                $category_ids[] = $category->term_id;
                            }
                        }

                        $args = array(
                            'post_type' => 'post',
                            'posts_per_page' => 5,
                            'orderby' => 'date',
                            'order' => 'DESC',
                            'status' => 'publish',
                            'category__in' => $category_ids,
                            'post__not_in' => array(get_the_ID()),
                        );
                        $query = new WP_Query($args);
                        if ($query->have_posts()) :
                            while ($query->have_posts()) : $query->the_post(); ?>
                                <div class="single-post-sidebar-item">
                                    <div class="image-post">
                                        <a href="<?php the_permalink(); ?>" class="block"><?php the_post_thumbnail('full', array('class' => 'img-fluid')); ?></a>
                                    </div>
                                    <div class="text-post">
                                        <h3 class="_6mmh"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                                        <div class="post-date">
                                            <span class="_2ylh"><?php echo get_the_date('d/m/Y'); ?></span>
                                        </div>
                                    </div>
                                </div>
                        <?php endwhile;
                        endif;
                        wp_reset_postdata();
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php echo do_shortcode('[block id="form-tin-tuc"]'); ?>
<?php get_footer(); ?>