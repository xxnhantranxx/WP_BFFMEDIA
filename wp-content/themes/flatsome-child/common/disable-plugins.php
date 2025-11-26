<?php 
// Chặn auto update plugin chỉ định
add_filter('auto_update_plugin', 'disable_auto_update_specific_plugins', 10, 2);

function disable_auto_update_specific_plugins($update, $item)
{
    // Danh sách các plugin cần chặn auto update
    $plugins_to_disable = [
        'all-in-one-wp-migration/all-in-one-wp-migration.php',
        'wpc-additional-variation-images/wpc-additional-variation-images.php',
        'contact-form-7/wp-contact-form-7.php'
    ];

    if (in_array($item->plugin, $plugins_to_disable, true)) {
        return false; // Chặn auto update cho các plugin trong danh sách
    }

    return $update; // Giữ nguyên hành động auto update cho các plugin khác
}


//Tắt thông báo cho plugin
add_filter('site_transient_update_plugins', 'disable_update_notifications_for_multiple_plugins');

function disable_update_notifications_for_multiple_plugins($transient)
{
    // Danh sách các plugin cần tắt thông báo cập nhật (plugin-folder/plugin-file.php)
    $plugins_to_disable = array(
        'all-in-one-wp-migration/all-in-one-wp-migration.php',
        'all-in-one-wp-migration-unlimited-extension/all-in-one-wp-migration-unlimited-extension.php',
        'advanced-custom-fields-pro/acf.php',
        'wordpress-seo-premium/wp-seo-premium.php',
        'filter-everything-pro/filter-everything.php',
        'wp-rocket/wp-rocket.php',
        'yith-woocommerce-ajax-product-filter-premium/init.php',
        'woo-variation-swatches-pro/woo-variation-swatches-pro.php',
        'contact-form-7/wp-contact-form-7.php',
        'woocommerce-orders-tracking/woocommerce-orders-tracking.php',
        'woocommerce-photo-reviews/woocommerce-photo-reviews.php',
    );

    foreach ($plugins_to_disable as $plugin) {
        if (isset($transient->response[$plugin])) {
            unset($transient->response[$plugin]);
        }
    }

    return $transient;
}
