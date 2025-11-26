<?php

/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive\Modules;

use TheLion\UseyourDrive\App;
use TheLion\UseyourDrive\Core;
use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Settings;
use TheLion\UseyourDrive\User;

defined('ABSPATH') || exit;

class Upload
{
    public static $enqueued_scripts = false;

    public static function render_standalone($attributes)
    {
        echo "<div class='wpcp-module UseyourDrive upload jsdisabled' ".Module::parse_attributes($attributes).'>';
        self::render();
        echo '</div>';
    }

    public static function render()
    {
        $user_can_upload = User::can_upload();

        if (false === $user_can_upload) {
            if (
                'upload' === Processor::instance()->get_shortcode_option('mode')
            && '1' === Processor::instance()->get_shortcode_option('upload')
            && App::get_current_account()->is_drive_readonly()
            && Helpers::check_user_role(Settings::get('permissions_add_shortcodes'))) {
                echo '&#9888; <strong>'.esc_html__('This module is not available because the connected account is set to read-only mode. Please reconnect your cloud account using the correct permissions.', 'wpcloudplugins').'</strong>';
            }

            return;
        }

        self::enqueue_scripts();

        include sprintf('%s/templates/modules/upload_box.php', USEYOURDRIVE_ROOTDIR);
    }

    public static function enqueue_scripts()
    {
        if (true === self::$enqueued_scripts) {
            return;
        }

        Core::instance()->load_scripts();
        Core::instance()->load_styles();

        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('UseyourDrive.UploadBox');

        Helpers::append_dependency('UseyourDrive', 'UseyourDrive.UploadBox');

        self::$enqueued_scripts = true;
    }
}
