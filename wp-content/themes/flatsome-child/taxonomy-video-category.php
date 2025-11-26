<?php get_header(); ?>
<?php echo do_shortcode('[block id="banner-videos-page"]'); ?>
<section class="section MainVideosArchive">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="row row-small ar_video_r1">
            <div class="col large-12 medium-12 small-12 ar_video_r1_c1 pb-0">
                <div class="col-inner">
                    <div class="_7yob">
                        <h2 class="_7kvg"><?php single_term_title(); ?></h2>
                        <div class="_7mwl">
                            <div class="_3jlm"><?php echo do_shortcode('[fe_widget]'); ?></div>
                        </div>
                    </div>
                    <div class="_7izj"><?php echo do_shortcode('[fe_chips]'); ?></div>
                </div>
            </div>
        </div>
        <div class="row row-small ar_video_r2">
            <div class="col large-12 medium-12 small-12 ar_video_r2_c1 pb-0">
                <div class="col-inner">
                    <div class="inner-item VideoList">
                        <div class="list-videos">
                            <?php
                            if (have_posts()) :
                                while (have_posts()) : the_post();
                            ?>
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
<?php echo do_shortcode('[block id="quy-trinh-quay-mv"]'); ?>
<?php echo do_shortcode('[block id="block-about"]'); ?>
<?php get_footer(); ?>