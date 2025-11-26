<?php
// Add custom Theme Functions here
require_once('builder/elements/builder.php');
require_once('builder/shortcodes/shortcodes.php');
require_once('functions-systems.php');
require_once('common/customize-admin-page.php');
require_once('common/smtp-config.php');
require_once('common/lightbox-form.php');
require_once('common/options.php');
require_once('common/shortcodes-customize.php');
require_once('common/post-type.php');
require_once('common/styles-global.php');
require_once('common/general.php');
require_once('common/style-scripts-add.php');
require_once('common/disable-plugins.php');
require_once('inc/ajax-functions.php');

// Đăng ký và enqueue script cho Idea Bank
function enqueue_idea_bank_scripts() {
    if (is_post_type_archive('idea-bank')) {
        wp_enqueue_script('idea-bank-filter', get_stylesheet_directory_uri() . '/assets/js/idea-bank-filter.js', array('jquery'), '1.0.0', true);
        wp_localize_script('idea-bank-filter', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'current_language' => pll_current_language()
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_idea_bank_scripts');