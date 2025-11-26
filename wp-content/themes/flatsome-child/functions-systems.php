<?php

// Xóa widget không cần thiết trên bảng điều khiển
function nt_remove_default_admin_widgets() {
    $widgets = [
        'dashboard_primary', 'dashboard_secondary', 'dashboard_quick_press',
        'dashboard_recent_drafts', 'dashboard_recent_comments', 'dashboard_right_now',
        'dashboard_activity', 'dashboard_incoming_links', 'e-dashboard-overview',
        'dashboard_site_health', 'dashboard_php_nag', 'themeisle',
        'yith_dashboard_blog_news', 'yith_dashboard_products_news'
    ];
    foreach ($widgets as $widget) {
        remove_meta_box($widget, 'dashboard', 'normal');
        remove_meta_box($widget, 'dashboard', 'side');
    }
}
add_action('wp_dashboard_setup', 'nt_remove_default_admin_widgets');
remove_action('welcome_panel', 'wp_welcome_panel');

// Xóa logo và submenu trong admin bar
function remove_admin_bar_items() {
    global $wp_admin_bar;
    $items = ['wp-logo', 'about', 'wporg', 'documentation', 'support-forums', 'feedback', 'comments'];
    foreach ($items as $item) {
        $wp_admin_bar->remove_menu($item);
    }
}
add_action('wp_before_admin_bar_render', 'remove_admin_bar_items');

// Ẩn thông báo cập nhật nếu tùy chọn được bật
if (get_option('turn_off_update') == 1) {
    function disable_update_notifications() {
        global $wp_version;
        return (object) ['last_checked' => time(), 'version_checked' => $wp_version];
    }
    add_filter('pre_site_transient_update_core', 'disable_update_notifications');
    add_filter('pre_site_transient_update_plugins', 'disable_update_notifications');
    add_filter('pre_site_transient_update_themes', 'disable_update_notifications');
}

// Xóa một số submenu trong admin menu
function remove_unwanted_submenus() {
    global $submenu, $menu;
    unset($submenu['index.php'][10]); // Cập nhật
    unset($submenu['themes.php'][5]); // Giao diện
    unset($submenu['themes.php'][15]); // Cài đặt giao diện
    unset($submenu['themes.php'][6]); // Tuỳ chỉnh
    unset($submenu['themes.php'][20]);
    $menu[26][0] = 'Blocks';
}
add_action('admin_menu', 'remove_unwanted_submenus');

// Xóa menu của Flatsome Panel
function remove_flatsome_panel_menus() {
    remove_menu_page('edit.php?post_type=acf');
    remove_menu_page('flatsome-panel');
}
add_action('admin_init', 'remove_flatsome_panel_menus');

// Ẩn menu WooCommerce không mong muốn
function hide_woocommerce_menus() {
    // Chỉnh sửa nếu cần
}
add_action('admin_menu', 'hide_woocommerce_menus', 71);

// Vô hiệu hóa trình chỉnh sửa Gutenberg
add_filter('use_block_editor_for_post', '__return_false');

// Chuyển đổi kích thước font chữ của TinyMCE
function modify_tinymce_font_sizes($initArray) {
    $initArray['fontsize_formats'] = "9px 10px 12px 13px 14px 16px 17px 18px 19px 20px 21px 24px 28px 32px 36px";
    return $initArray;
}
add_filter('tiny_mce_before_init', 'modify_tinymce_font_sizes', 99);

// Tuỳ chỉnh admin bar
function customize_admin_bar() {
    global $wp_admin_bar;
    $icon_style = 'font: normal 20px/1 \"dashicons\"; -webkit-font-smoothing: antialiased; padding-right: 4px; margin-top:3px;';
    $menus = [
        'flatsome_panel' => 'dashicons Tuỳ Chọn',
        'theme_options' => 'dashicons-admin Tuỳ Biến',
        'options_advanced' => 'dashicons-admin Nâng Cao'
    ];
    foreach ($menus as $id => $title) {
        $wp_admin_bar->add_menu(['id' => $id, 'title' => '<span class="dashicons ' . explode(' ', $title)[0] . '" style="' . $icon_style . 'margin-top:6px;"></span> ' . explode(' ', $title, 2)[1]]);
    }
    $nodes_to_remove = ['flatsome_panel_license', 'flatsome_panel_support', 'flatsome_panel_changelog', 'flatsome_panel_setup_wizard', 'flatsome-activate'];
    foreach ($nodes_to_remove as $node) {
        $wp_admin_bar->remove_node($node);
    }
}
add_action('admin_bar_menu', 'customize_admin_bar', 40);

// Bật hỗ trợ SVG upload
function enable_svg_uploads($file_types) {
    $file_types['svg'] = 'image/svg+xml';
    return $file_types;
}
add_action('upload_mimes', 'enable_svg_uploads');

// Vô hiệu hóa bình luận trên bài viết
function disable_comments() {
    // Tắt hỗ trợ bình luận cho tất cả các loại bài đăng
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if(post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }

    // Đóng bình luận trên toàn bộ trang web
    update_option('default_comment_status', 'closed');
    
    // Ẩn menu Comments trong admin
    add_action('admin_menu', function () {
        remove_menu_page('edit-comments.php');
    });
    
    // Ẩn widget Recent Comments
    add_action('widgets_init', function () {
        unregister_widget('WP_Widget_Recent_Comments');
    });
    
    // Ẩn Comments từ admin bar
    add_action('wp_before_admin_bar_render', function() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
    });
}
add_action('init', 'disable_comments');


