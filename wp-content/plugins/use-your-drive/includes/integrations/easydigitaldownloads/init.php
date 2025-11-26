<?php

namespace TheLion\UseyourDrive\Integrations;

use TheLion\UseyourDrive\Accounts;
use TheLion\UseyourDrive\App;
use TheLion\UseyourDrive\CacheNode;
use TheLion\UseyourDrive\Client;
use TheLion\UseyourDrive\Core;
use TheLion\UseyourDrive\Download;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Zip;

defined('ABSPATH') || exit;

class EasyDigitalDownloads
{
    public function __construct()
    {
        add_filter('edd_requested_file', [$this, 'do_download'], 10, 1);
        add_action('edd_meta_box_files_fields', [$this, 'render_file_selector'], 20, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts()
    {
        if (function_exists('get_current_screen')) {
            $current_screen = get_current_screen();
            if (isset($current_screen->post_type) && 'download' == $current_screen->post_type) {
                Core::instance()->load_scripts();
                Core::instance()->load_styles();

                // enqueue scripts/styles
                wp_enqueue_style('wpcp-googledrive-edd', plugins_url('backend.css', __FILE__), ['WPCloudPlugins.AdminUI'], USEYOURDRIVE_VERSION);
                wp_enqueue_script('wpcp-googledrive-edd', plugins_url('backend.js', __FILE__), ['UseyourDrive.AdminUI'], USEYOURDRIVE_VERSION);

                // register translations
                $translation_array = [
                    'choose_from' => sprintf(esc_html__('Add File', 'wpcloudplugins'), 'Google Drive'),
                    'download_url' => '?action=wpcp-googledrive-edd-direct-download&id=',
                    'notification_success_file_msg' => sprintf(esc_html__('%s added as downloadable file!', 'wpcloudplugins'), '{filename}'),
                    'notification_failed_file_msg' => sprintf(esc_html__('Cannot add %s!', 'wpcloudplugins'), '{filename}'),
                ];

                wp_localize_script('wpcp-googledrive-edd', 'useyourdrive_edd_translation', $translation_array);
            }
        }
    }

    public function render_file_selector($post_id = 0, $type = '')
    {
        include_once 'template_file_selector.php';
    }

    public function do_download($requested_file)
    {
        if (!strpos($requested_file, 'wpcp-googledrive-edd-direct-download')) {
            return $requested_file;
        }

        $cached_entry = $this->get_entry_for_download_by_url($requested_file);

        if (empty($cached_entry)) {
            wp_die(__('Error 104: Sorry, this file could not be downloaded.', 'easy-digital-downloads'), __('Error Downloading File', 'easy-digital-downloads'), 403);

            exit;
        }

        if ($cached_entry->get_entry()->is_dir()) {
            $_REQUEST['files'] = [$cached_entry->get_id()];
            Processor::instance()->set_requested_entry($cached_entry->get_id());
            $zip = new Zip($cached_entry->get_id());
            $zip->do_zip();

            exit;
        }

        $download = new Download($cached_entry, 'default');
        $download->start_download();

        exit;
    }

    /**
     * @param string $file_path
     *
     * @return CacheNode
     */
    public function get_entry_for_download_by_url($file_path)
    {
        $download_url = parse_url($file_path);

        if (isset($download_url['query'])) {
            parse_str($download_url['query'], $download_url_query);
        } else {
            // In some occasions the file name contains a #, causing the parameters to end up in the fragment part of the url
            parse_str($download_url['fragment'], $download_url_query);
        }

        $entry_id = $download_url_query['id'];
        $account_id = $download_url_query['account_id'];
        $account = Accounts::instance()->get_account_by_id($account_id);

        if (null === $account) {
            return false;
        }

        App::set_current_account($account);
        $cachedentry = Client::instance()->get_entry($entry_id, false);

        if (false === $cachedentry) {
            return false;
        }

        return $cachedentry;
    }
}

new EasyDigitalDownloads();
