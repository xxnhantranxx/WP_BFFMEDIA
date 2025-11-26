<?php

//Cho phepos viết shortcode vào cf7
add_filter( 'wpcf7_form_elements', 'mycustom_wpcf7_form_elements' );

function mycustom_wpcf7_form_elements( $form ) {
$form = do_shortcode( $form );

return $form;
}

function filter_search_by_title($where, $wp_query) {
    global $wpdb;
    if ($search_term = $wp_query->get('s')) {
        $where .= " AND {$wpdb->posts}.post_title LIKE '%" . esc_sql($search_term) . "%'";
    }
    return $where;
}
add_filter('posts_where', 'filter_search_by_title', 10, 2);

// Loại bỏ tiền tố "Bảo vệ" khỏi tiêu đề post có mật khẩu
function remove_protected_title_format($format, $post) {
    // Return format chỉ có %s để không thêm tiền tố
    return '%s';
}
add_filter('protected_title_format', 'remove_protected_title_format', 10, 2);

// Thêm form password vào post có mật khẩu
function my_custom_password_form() {
    global $post;
    $label = 'pwbox-' . ( empty( $post->ID ) ? rand() : $post->ID );
    $output = '
    <div class="boldgrid-section">
        <div class="container">
            <form action="' . esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" class="form-inline post-password-form" method="post">
                <p>' . __( 'Nội dung này được bảo vệ bằng mật khẩu. Để xem nội dung này, vui lòng nhập mật khẩu của bạn bên dưới:' ) . '</p>
                <div class="password-form-group">
                    <input name="post_password" placeholder="Nhập mật khẩu" id="' . $label . '" type="password" size="20" class="form-control" />
                    <button type="submit" name="Submit" class="button-primary">' . esc_attr_x( 'Xem nội dung', 'post password form' ) . '</button>
                </div>
            </form>
        </div>';
    return $output;
}
add_filter('the_password_form', 'my_custom_password_form', 99);