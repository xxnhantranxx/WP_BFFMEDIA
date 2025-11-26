<?php
/**
 * The blog template file.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */
get_header();

$s = get_search_query();
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$posts_per_page = get_option('posts_per_page', 10);
$original_query = null;
$query_all = null;

// Kiểm tra nếu từ khóa tìm kiếm rỗng hoặc ít hơn 3 ký tự
if (empty($s) || strlen($s) < 3) {
    $total_results = 0;
} else {
    // Truy vấn tổng hợp tất cả các post types
    $args_all = array(
        'post_type'      => array('product', 'post', 'page', 'album'),
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => 'title',
        'order'          => 'ASC',
        's'              => $s,
        'post_status'    => 'publish'
    );
    $query_all = new WP_Query($args_all);
    
    // Lưu query gốc và thay thế để flatsome_posts_pagination có thể sử dụng
    global $wp_query;
    $original_query = $wp_query;
    $wp_query = $query_all;
    
    // Tổng số kết quả
    $total_results = $query_all->found_posts;
}

?>
<section class="_8aoy section">
    <div class="section-bg fill"></div>
    <div class="section-content relative">
        <div class="_3prq row">
            <div class="col large-12 medium-12 small-12 RemovePaddingBottom">
                <div class="col-inner">
                    <div class="_7mzt">
                        <h2>Kết quả tìm kiếm cho: "<?php echo esc_html($s); ?>"</h2>
                    </div>
                    <div class="search-count">Tìm thấy <span><?php echo $total_results; ?></span> kết quả</div>
                    <div class="_7yzl list_search_result">
                        <?php if ($query_all && $query_all->have_posts()) : ?>
                            <ul class="search-results">
                                <?php while ($query_all->have_posts()) : $query_all->the_post(); 
                                    $post_type = get_post_type();
                                    $type_label = '';
                                    $excerpt_content = '';
                                    
                                    // Xác định nhãn và nội dung theo post type
                                    switch ($post_type) {
                                        case 'product':
                                            $type_label = 'Sản phẩm';
                                            $excerpt_content = get_the_excerpt();
                                            break;
                                        case 'post':
                                            $type_label = 'Tin tức';
                                            $excerpt_content = get_the_excerpt();
                                            break;
                                        case 'page':
                                            $type_label = 'Trang';
                                            $excerpt_content = get_the_excerpt();
                                            break;
                                        case 'album':
                                            $type_label = 'Album';
                                            $excerpt_content = get_field('description_album');
                                            break;
                                    }
                                ?>
                                    <li class="item <?php echo esc_attr($post_type); ?>">
                                        <a class="_4nnm" href="<?php the_permalink(); ?>">
                                            <?php
                                            if (has_post_thumbnail()) {
                                                echo get_the_post_thumbnail();
                                            } else {
                                                echo '<img src="' . get_stylesheet_directory_uri() . '/img/image-plaholder.jpg" alt="Placeholder Image">';
                                            }
                                            ?>
                                        </a>

                                        <div class="_9lfn">
                                            <a href="<?php the_permalink(); ?>" class="_1hgo textLine-2"><?php the_title(); ?></a>
                                            <div class="_1srv type textLine-1"><?php echo esc_html($type_label); ?> - <?php the_permalink(); ?></div>
                                            <div class="_5khu textLine-3"><?php echo $excerpt_content; ?></div>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                            
                            <div class="pagination-cntt">
                                <?php flatsome_posts_pagination(); ?>
                            </div>
                        <?php else : ?>
                            <p>Không tìm thấy kết quả nào.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
// Khôi phục query gốc nếu đã thay thế
if ($original_query !== null) {
    global $wp_query;
    $wp_query = $original_query;
}
wp_reset_postdata();
get_footer();
?>