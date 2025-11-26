<?php
/**
 * Tệp xử lý các hàm AJAX
 */

// Đăng ký script cho tính năng live search
function register_live_search_scripts() {
    wp_enqueue_script('live-search', get_stylesheet_directory_uri() . '/js/web/LiveSearch.js', array('jquery'), '1.0', true);
    
    // Truyền biến AJAX URL và nonce vào JavaScript
    wp_localize_script('live-search', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('live_search_nonce'),
        'home_url' => home_url()
    ));
}
add_action('wp_enqueue_scripts', 'register_live_search_scripts');

/**
 * Xử lý AJAX cho live search post, page, album
 */
function live_search_products_handler() {
    // Kiểm tra nonce bảo mật
    check_ajax_referer('live_search_nonce', 'nonce');
    
    // Lấy từ khóa tìm kiếm
    $query = sanitize_text_field($_POST['query']);
    
    // Kiểm tra nếu từ khóa tìm kiếm rỗng hoặc ít hơn 2 ký tự
    if (empty($query) || strlen($query) < 2) {
        wp_send_json_success(array(
            'found' => 0,
            'products' => array(),
        ));
        return;
    }
    
    // Thiết lập tham số tìm kiếm
    $args = array(
        'post_type'      => array('post', 'page', 'album'),
        'post_status'    => 'publish',
        's'              => $query,
        'posts_per_page' => 4,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    
    // Thực hiện tìm kiếm
    $search_query = new WP_Query($args);
    $total_found = $search_query->found_posts;
    $products = array();
    
    // Lấy dữ liệu kết quả
    if ($search_query->have_posts()) {
        while ($search_query->have_posts()) {
            $search_query->the_post();
            
            $post_type = get_post_type();
            $type_label = '';
            $excerpt_content = '';
            
            // Xác định nhãn và nội dung theo post type (giống như trong search.php)
            switch ($post_type) {
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
            
            // Lấy thumbnail hoặc placeholder
            $thumbnail_url = '';
            if (has_post_thumbnail()) {
                $thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
            } else {
                $thumbnail_url = get_stylesheet_directory_uri() . '/img/image-plaholder.jpg';
            }
            
            $products[] = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'thumbnail' => $thumbnail_url,
                'type_label' => $type_label,
                'excerpt' => $excerpt_content,
            );
        }
        wp_reset_postdata();
    }
    
    // Trả về kết quả dạng JSON
    wp_send_json_success(array(
        'found' => $total_found,
        'products' => $products,
    ));
}

// Đăng ký endpoint AJAX
add_action('wp_ajax_live_search_products', 'live_search_products_handler');
add_action('wp_ajax_nopriv_live_search_products', 'live_search_products_handler'); 