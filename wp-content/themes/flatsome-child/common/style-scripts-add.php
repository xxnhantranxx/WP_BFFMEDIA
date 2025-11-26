<?php
// Enqueue styles and scripts for login page
function my_login_assets() {
    wp_enqueue_style('custom-login', get_stylesheet_directory_uri() . '/css/admin/style-login.css', [], '1.0.0');
    wp_enqueue_script('custom-login', get_stylesheet_directory_uri() . '/js/admin/style-login.js', [], '1.0.0', true);
}
add_action('login_enqueue_scripts', 'my_login_assets');

// Enqueue styles and scripts for admin page
function load_admin_assets() {
    wp_enqueue_style('admin-css', get_stylesheet_directory_uri() . '/css/admin/admin-style.css', [], '1.0.0');
    wp_enqueue_script('admin-js', get_stylesheet_directory_uri() . '/js/admin/admin.js', [], '1.0.0', true);
}
add_action('admin_enqueue_scripts', 'load_admin_assets');

// Function to enqueue multiple styles
function enqueue_styles($styles) {
    foreach ($styles as $handle => $path) {
        wp_enqueue_style($handle, get_stylesheet_directory_uri() . $path, [], '1.0.0');
    }
}

// Enqueue all CSS for frontend
function enqueue_frontend_styles() {
    $styles = [
        'css-screen-first' => '/css/web/header.css',
        'css-fw6' => '/assets/css/fontawesome-all.css',
        'css-select-2' => '/assets/css/select2.min.css',
        'style-swiper' => '/assets/css/swiper-bundle.min.css',
        'style-animate' => '/assets/css/animate.min.css',
        'style-system' => '/css/web/system-core.css',
        'style-footer' => '/css/web/footer.css',
        'style-customize' => '/css/web/customize.css',
        'css-responsive' => '/css/web/responsive.css',
    ];
    enqueue_styles($styles);
}
add_action('wp_enqueue_scripts', 'enqueue_frontend_styles', 101);

// Function to enqueue multiple scripts
function enqueue_scripts($scripts, $in_footer = true) {
    foreach ($scripts as $handle => $path) {
        wp_enqueue_script($handle, get_stylesheet_directory_uri() . $path, [], '1.0.0', $in_footer);
    }
}

// Enqueue all scripts to footer
function enqueue_footer_scripts() {
    $scripts = [
        'select2-js' => '/assets/js/select2.min.js',
        'swiper-js' => '/assets/js/swiper-bundle.min.js',
        'wow-js' => '/assets/js/wow.js',
        'masonry-js' => '/assets/js/masonry.pkgd.min.js',
        'swiper-customize' => '/js/web/SwiperCustomize.js',
        'my-js' => '/js/web/MyJs.js',
    ];
    enqueue_scripts($scripts);

    // Truyền biến custom_ajax_object vào JavaScript
    // wp_localize_script('handle-ajax', 'custom_ajax_object', array(
    //     'ajax_url' => admin_url('admin-ajax.php')
    // ));
}
add_action('wp_footer', 'enqueue_footer_scripts');

