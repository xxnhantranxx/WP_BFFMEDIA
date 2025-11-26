<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

defined('ABSPATH') || exit;

// Exit if no permission to embed files
if (!Helpers::check_user_role(Settings::get('permissions_add_embedded'))) {
    exit;
}

// Add own styles and script and remove default ones
Core::instance()->load_scripts();
Core::instance()->load_styles();

function remove_all_scripts()
{
    global $wp_scripts;
    $wp_scripts->queue = [];

    wp_enqueue_script('jquery-effects-fade');
    wp_enqueue_script('UseyourDrive');
    wp_enqueue_script('UseyourDrive.DocumentEmbedder');
}

function remove_all_styles()
{
    global $wp_styles;
    $wp_styles->queue = [];
    wp_enqueue_style('UseyourDrive');
    wp_enqueue_style('WPCloudPlugins.AdminUI');
}

add_action('wp_print_scripts', __NAMESPACE__.'\remove_all_scripts', 1000);
add_action('wp_print_styles', __NAMESPACE__.'\remove_all_styles', 1000);

$callback = Helpers::select_callback($_REQUEST['callback'] ?? '');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo get_bloginfo('language'); ?>" class="wpcp-h-full wpcp-bg-gray-100">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?php esc_html_e('Embed Files', 'wpcloudplugins'); ?></title>
    <?php wp_print_styles(); ?>
</head>

<body class="wpcp-h-full wpcp-m-0">
    <div id="wpcp" class="wpcp-app hidden" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
        <div id="wpcp-form" data-callback="<?php echo esc_attr($callback); ?>">
            <nav class="bg-brand-color-900 shadow-sm sticky top-0 z-50">
                <div class="mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <div class="shrink-0 flex items-center">
                                <a href="https://www.wpcloudplugins.com"><img class="block h-8 w-auto" src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/wpcloudplugins-logo-light.png"></a>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="shrink-0 relative wpcp-dropdown-menu">
                                <div>
                                    <button type="button" class="wpcp-dropdown-menu-button wpcp-button-secondary" aria-haspopup="true">
                                        <!-- Heroicon name: solid/plus-sm -->
                                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        <span><?php esc_html_e('Embed Files', 'wpcloudplugins'); ?></span>
                                    </button>
                                </div>
                                <div class="wpcp-dropdown-menu-content hidden origin-top-right absolute right-0 mt-2 w-96 rounded-md shadow-lg py-1 bg-white ring-1 ring-black/5 focus:outline-hidden z-10" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                                    <a href="#" class="px-4 py-2 text-sm text-gray-700 link-preview flex items-center" data-type="preview" role="menuitem" tabindex="-1" id="user-menu-item-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span class="ml-2 flex-auto"><?php esc_html_e('Read-only', 'wpcloudplugins'); ?></span>
                                    </a>
                                    <a href="#" class="px-4 py-2 text-sm text-gray-700 embed-minimal flex items-center" data-type="editable" role="menuitem" tabindex="-1" id="user-menu-item-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span class="ml-2 flex-auto"><?php esc_html_e('Editable', 'wpcloudplugins'); ?> <small>(Google & Office Docs)</small></span>
                                    </a>
                                    <a href="#" class="px-4 py-2 text-sm text-gray-700 embed-full flex items-center" data-type="editable-full" role="menuitem" tabindex="-1" id="user-menu-item-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span class="ml-2 flex-auto"><?php esc_html_e('Editable with toolbar', 'wpcloudplugins'); ?> <small>(Google & Office Docs)</small></span>
                                    </a>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

            </nav>

            <div class="">
                <main>
                    <?php
                    if (false === Accounts::instance()->has_accounts()) {
                        include_once 'no_account_linked.php';
                    } else {
                        ?>
                    <div class="mx-auto">
                        <div class="">
                            <?php

                        // Add File Browser
                        $atts = [
                            'singleaccount' => '0',
                            'dir' => 'drive',
                            'mode' => 'files',
                            'show_tree' => '1',
                            'showfiles' => '1',
                            'upload' => '0',
                            'delete' => '0',
                            'rename' => '0',
                            'addfolder' => '0',
                            'viewrole' => 'all',
                            'candownloadzip' => '0',
                            'search' => '1',
                            'searchcontents' => '1',
                            'showsharelink' => '0',
                            'previewinline' => '0',
                            'popup' => 'embedded',
                            'includeext' => '*',
                            '_random' => 'embed',
                        ];

                        $user_folder_backend = apply_filters('useyourdrive_use_user_folder_backend', Settings::get('userfolder_backend'));

                        if ('No' !== $user_folder_backend) {
                            $atts['userfolders'] = $user_folder_backend;

                            $private_root_folder = Settings::get('userfolder_backend_auto_root');
                            if ('auto' === $user_folder_backend && !empty($private_root_folder) && isset($private_root_folder['id'])) {
                                if (!isset($private_root_folder['account']) || empty($private_root_folder['account'])) {
                                    $main_account = Accounts::instance()->get_primary_account();
                                    $atts['account'] = $main_account->get_id();
                                } else {
                                    $atts['account'] = $private_root_folder['account'];
                                }

                                $atts['dir'] = $private_root_folder['id'];

                                if (!isset($private_root_folder['view_roles']) || empty($private_root_folder['view_roles'])) {
                                    $private_root_folder['view_roles'] = ['none'];
                                }
                                $atts['viewuserfoldersrole'] = implode('|', $private_root_folder['view_roles']);
                            }
                        }

                        echo Shortcodes::do_shortcode($atts);
                        ?>
                        </div>
                    </div>
                    <?php
                    }
?>
                </main>
                <footer>
                    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="border-t border-gray-200 py-4 text-sm text-gray-500 text-center sm:text-left">
                            <span class="block sm:inline">
                                <?php AdminLayout::render_notice(esc_html__('Please note that the embedded files do have the public sharing permission [anyone with link can view]. Google Docs can also be embedded in "Edit" mode. The sharing permissions are set to [anyone with link can edit].', 'wpcloudplugins'), 'info'); ?>
                            </span>
                        </div>
                    </div>
                </footer>
            </div>

        </div>
    </div>

    <?php wp_print_scripts(); ?>
</body>

</html>