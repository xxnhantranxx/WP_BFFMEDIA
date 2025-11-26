<?php
/**
 * Posts layout.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */

do_action('flatsome_before_blog');
?>

<?php get_header(); ?>
<?php echo do_shortcode('[block id="banner-goc-tu-van"]'); ?>
<section class="section MainVideosArchive">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small ar_video_r1">
            <div class="col large-12 medium-12 small-12 ar_video_r1_c1 pb-0">
                <div class="col-inner">
                    <div class="_7yob">
                        <h2 class="_7kvg">Blogs</h2>
                        <div class="_7mwl">
                            <div class="_3jlm"><?php echo do_shortcode('[fe_widget]'); ?></div>
                        </div>
                    </div>
                    <div class="_7izj"><?php echo do_shortcode('[fe_chips]'); ?></div>
                </div>
            </div>
        </div>
        <div class="row row-small ar_blogs_r2">
            <div class="col large-12 medium-12 small-12 ar_blogs_r2_c1 pb-0">
                <div class="col-inner">
                    <div class="inner-item BlogsBFF">
                        <div class="MainListBlogs">
                            <?php
                            if (have_posts()) :
                                while (have_posts()) : the_post();
                            ?>
                                <div class="item-blogs">
                                    <div class="box-image">
                                        <a href="<?php the_permalink(); ?>" class="block"><?php the_post_thumbnail('full', array('class' => 'img-fluid')); ?></a>
                                    </div>
                                    <div class="box-text">
                                        <div class="post-date">
                                            <i class="fa-light fa-calendar-days"></i>
                                            <span class="date"><?php echo get_the_date('d/m/Y'); ?></span>
                                        </div>
                                        <div class="post-title">
                                            <a href="<?php the_permalink(); ?>" class="title textLine-2"><?php the_title(); ?></a>
                                        </div>
                                        <div class="post-content">
                                            <p class="textLine-2"><?php echo get_the_excerpt(); ?></p>
                                        </div>
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
<?php echo do_shortcode('[block id="form-tin-tuc"]'); ?>
<?php get_footer(); ?>
