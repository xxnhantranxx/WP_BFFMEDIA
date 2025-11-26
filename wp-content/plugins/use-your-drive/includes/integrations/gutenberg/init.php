<?php

namespace TheLion\UseyourDrive\Integrations;

use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Modules;
use TheLion\UseyourDrive\Settings;

defined('ABSPATH') || exit;

/**
 * Gutenberg block with live preview.
 */
class Gutenberg
{
    public function __construct()
    {
        if ($this->has_gutenberg()) {
            $this->hooks();
        }
    }

    /**
     * Check if Gutenberg is enabled.
     */
    public function has_gutenberg()
    {
        return function_exists('register_block_type');
    }

    /**
     * Load Gutenberg block assets for in editor.
     */
    public function enqueue_block_editor_assets()
    {
        $asset_script_file = include plugin_dir_path(__FILE__).'build/wpcp-useyourdrive.asset.php';
        $asset_style_file = include plugin_dir_path(__FILE__).'build/editor.scss.asset.php';
        $rtl = (is_rtl() ? '-rtl' : '');

        wp_register_script(
            'wpcp-useyourdrive-block-editor-script',
            plugins_url('build/wpcp-useyourdrive.js', __FILE__),
            $asset_script_file['dependencies'],
            $asset_script_file['version']
        );

        wp_register_style(
            'wpcp-useyourdrive-block-editor-style',
            plugins_url("build/editor.scss{$rtl}.css", __FILE__),
            $asset_style_file['dependencies'],
            $asset_style_file['version']
        );

        // modules
        $modules = Modules::get_modules();

        // WP Localized globals. Use dynamic PHP stuff in JavaScript via `wpcp_useyourdrive_global` object.
        wp_localize_script(
            'wpcp-useyourdrive-block-editor-script',
            'wpcp_useyourdrive_global',
            [
                'pluginDirPath' => plugin_dir_path(__DIR__),
                'pluginDirUrl' => plugin_dir_url(__DIR__),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'i18n' => [
                    'title' => 'Google Drive',
                    'description' => sprintf(\esc_html__('Insert your %s content', 'wpcloudplugins'), 'Google Drive'),
                    'form_keywords' => [
                        'cloud',
                        'google',
                        'drive',
                        'documents',
                        'files',
                        'upload',
                        'video',
                        'audio',
                        'media',
                        'gallery',
                        'embed',
                        'links',
                        'download',
                    ],
                    'openModuleBuilder' => \esc_html__('Configure', 'wpcloudplugins'),
                    'createModule' => \esc_html__('Add New Module', 'wpcloudplugins'),
                    'updatedModule' => \esc_html__('Module is succesfully updated!', 'wpcloudplugins'),
                    'module' => \esc_html__('Module', 'wpcloudplugins'),
                    'showPreview' => \esc_html__('Show preview', 'wpcloudplugins'),
                    'hidePreview' => \esc_html__('Hide preview', 'wpcloudplugins'),
                    'moduleNotice' => \esc_html__('Do not forget to test your module on the Front-End.', 'wpcloudplugins'),
                ],
                'wpnonce' => \wp_create_nonce('useyourdrive-module-preview'),
                'editable' => Helpers::check_user_role(Settings::get('permissions_add_shortcodes')) ? 1 : 0,
                'enablePreview' => \apply_filters('useyourdrive_gutenberg_block_preview', true) ? 1 : 0,
                'modules' => $modules,
            ]
        );
    }

    /**
     *  Register Gutenberg block, enqueue styles and set i18n.
     */
    public function register_block()
    {
        register_block_type('wpcp/useyourdrive-block', [
            'attributes' => [
                'shortcode' => [
                    'type' => 'string',
                ],
                'className' => [
                    'type' => 'string',
                ],
            ],
            'editor_script' => 'wpcp-useyourdrive-block-editor-script',
            'editor_style' => 'wpcp-useyourdrive-block-editor-style',
            'render_callback' => [$this, 'get_render_html'],
        ]);
    }

    /**
     * Get form HTML to display in a WPForms Gutenberg block.
     *
     * @param array $attr attributes passed by WPForms Gutenberg block
     *
     * @return string
     */
    public function get_render_html($attr)
    {
        // Don't render the block in REST API requests, e.g. when saving a post or page.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '';
        }

        $shortcode = !empty($attr['shortcode']) ? $attr['shortcode'] : false;

        if (empty($shortcode)) {
            return '<div style="text-align:center;">⚠ '.esc_html__('This WP Cloud Plugin module is not yet configured.', 'wpcloudplugins').'</div>';
        }

        \ob_start();

        echo do_shortcode($shortcode);

        $output = \ob_get_clean();

        if (empty($output)) {
            return '';
        }

        return $output;
    }

    /**
     * Checking if is Gutenberg REST API call.
     *
     * @return bool true if is Gutenberg REST API call
     */
    public function is_gb_editor()
    {
        return \defined('REST_REQUEST') && REST_REQUEST && !empty($_REQUEST['context']) && 'edit' === $_REQUEST['context']; // phpcs:ignore
    }

    /**
     * Add WP Cloud Plugins category to blocks.
     *
     * @param mixed $categories
     * @param mixed $editor_context
     */
    public function create_block_category($categories, $editor_context)
    {
        $category_slugs = wp_list_pluck($categories, 'slug');

        // Only add the category once
        return in_array('wpcp-blocks', $category_slugs, true) ? $categories : array_merge(
            $categories,
            [
                [
                    'slug' => 'wpcp-blocks',
                    'title' => 'WP Cloud Plugins',
                    'icon' => null,
                ],
            ]
        );
    }

    /**
     * Integration hooks.
     */
    protected function hooks()
    {
        \add_action('init', [$this, 'register_block']);
        \add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        \add_filter('block_categories_all', [$this, 'create_block_category'], 10, 2);
    }
}
new Gutenberg();
