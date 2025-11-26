<?php

namespace TheLion\UseyourDrive\Integrations;

use TheLion\UseyourDrive\Core;

defined('ABSPATH') || exit;

class FL_WPCP_UseyourDrive_Module extends \FLBuilderModule
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'Google Drive',
            'description' => sprintf(\esc_html__('Insert your %s content', 'wpcloudplugins'), 'Google Drive'),
            'category' => 'WP Cloud Plugins',
            'dir' => USEYOURDRIVE_ROOTDIR.'/includes/integrations/beaverbuilder/modules/wpcp_useyourdrive_module/',
            'url' => USEYOURDRIVE_ROOTPATH.'/includes/integrations/beaverbuilder/modules/wpcp_useyourdrive_module/',
            'icon' => USEYOURDRIVE_ROOTDIR.'/css/images/google_drive_logo.svg',
        ]);
    }

    public function get_icon($icon = '')
    {
        return file_get_contents($icon);
    }

    public function enqueue_scripts()
    {
        Core::instance()->load_scripts();
        Core::instance()->load_styles();

        wp_enqueue_script('WPCloudPlugins.Libraries');
        wp_enqueue_script('UseyourDrive.ShortcodeBuilder');
        wp_enqueue_style('UseyourDrive');
    }
}

// Register the module and its form settings.
\FLBuilder::register_module('\TheLion\UseyourDrive\Integrations\FL_WPCP_UseyourDrive_Module', [
    'general' => [ // Tab
        'title' => esc_html__('General'), // Tab title
        'sections' => [ // Tab Sections
            'general' => [ // Section
                'title' => esc_html__('Module', 'wpcloudplugins'), // Section Title
                'fields' => [ // Section Fields
                    'raw_shortcode' => [
                        'type' => 'wpcp_useyourdrive',
                        'label' => esc_html__('Module Configuration', 'wpcloudplugins'),
                        'default' => '',
                    ],
                ],
            ],
        ],
    ],
]);
