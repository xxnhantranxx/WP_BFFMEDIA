<?php

function BlogsBFF($atts)
{
    extract(shortcode_atts(array(
        'cat' => '',
        'offset' => 0,
        'count' => 8,
        'link' => '',
    ), $atts));
    ob_start();

    $terms = get_terms(array(
        'taxonomy' => 'category',
        'hide_empty' => false, // để hiển thị cả những term không có bài viết nào
    ));
    $cat_ids = array();
    foreach ($terms as $term) {
        $cat_ids[] = $term->term_id;
    }
    if ($cat == '') {
        $cat =  $cat_ids;
    }
?>
    <div class="BlogsBFF">
        <div class="MainListBlogs">
            <?php
            $args = array(
                'post_type' => 'post',
                'tax_query' => array(                     //(array) - Lấy bài viết dựa theo taxonomy
                    'relation' => 'AND',                      //(string) - Mối quan hệ giữa các tham số bên trong, AND hoặc OR
                    array(
                        'taxonomy' => 'category',
                        'field' => 'id',
                        'terms' => $cat,
                    )
                ),
                'posts_per_page' => $count,
                'offset' => $offset,
                'order' => 'DESC',
                'orderby' => 'date',
            );
            $the_query = new WP_Query($args);

            // The Loop
            if ($the_query->have_posts()) :
                while ($the_query->have_posts()) : $the_query->the_post(); ?>

                    <div class="item-blogs">
                        <div class="box-image">
                            <a href="<?php the_permalink(); ?>" class="block">
                                <?php the_post_thumbnail('full', array('class' => 'img-fluid')); ?>
                            </a>
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
            endif;

            // Reset Post Data
            wp_reset_postdata();
            ?>
        </div>
        <div class="_8hkb">
            <a href="<?php echo $link; ?>" class="button-flex button myButton">
                <span>Xem tất cả</span>
                <i class="fa-regular fa-arrow-up-right"></i>
            </a>
        </div>
    </div>
<?php

    $contentShortcode = ob_get_contents();
    ob_end_clean();
    return $contentShortcode;
}

add_shortcode('BlogsBFF', 'BlogsBFF');
