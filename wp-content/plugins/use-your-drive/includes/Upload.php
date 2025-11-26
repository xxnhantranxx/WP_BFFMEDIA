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

class Upload
{
    public function __construct()
    {
        wp_using_ext_object_cache(false);
    }

    public function upload_pre_process()
    {
        do_action('useyourdrive_upload_pre_process', Processor::instance());

        foreach ($_REQUEST['files'] as $file) {
            if (!empty($file['path'])) {
                $this->create_folder_structure($file['path']);
            }
        }

        $result = apply_filters('useyourdrive_upload_pre_process_result', ['result' => 1], Processor::instance());

        echo json_encode($result);
    }

    public function do_upload_direct()
    {
        if ((!isset($_REQUEST['filename'])) || (!isset($_REQUEST['file_size'])) || (!isset($_REQUEST['mimetype']))) {
            exit;
        }

        if ('1' === Processor::instance()->get_shortcode_option('demo')) {
            echo json_encode(['result' => 0]);

            exit;
        }

        $name = Helpers::filter_filename(stripslashes(rawurldecode($_REQUEST['filename'])), false);
        $path = $_REQUEST['file_path'];
        $size = $_REQUEST['file_size'];

        // Rename, Prefix and Suffix file
        $file_extension = pathinfo(stripslashes($_REQUEST['filename']), PATHINFO_EXTENSION);
        $file_name = pathinfo(stripslashes($_REQUEST['filename']), PATHINFO_FILENAME);

        $name = trim(Placeholders::apply(
            Processor::instance()->get_shortcode_option('upload_filename'),
            Processor::instance(),
            [
                'file_name' => $file_name,
                'file_extension' => empty($file_extension) ? '' : ".{$file_extension}",
                'file_description' => !empty($_REQUEST['file_description']) ? sanitize_textarea_field(wp_unslash($_REQUEST['file_description'])) : '',
                'queue_index' => filter_var($_REQUEST['queue_index'] ?? 1, FILTER_SANITIZE_NUMBER_INT),
            ]
        ));

        $name_parts = pathinfo($name);

        if (false !== strpos($name, '/') && !empty($name_parts['dirname'])) {
            $path = Helpers::clean_folder_path($path.$name_parts['dirname']);
        }

        $name = basename($name);
        $mimetype = Helpers::get_mimetype($file_extension);

        $description = sanitize_textarea_field(wp_unslash($_REQUEST['file_description']));

        $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
        $googledrive_file->setName($name);
        $googledrive_file->setMimeType($mimetype);
        $googledrive_file->setDescription($description);

        if ('1' === Processor::instance()->get_shortcode_option('upload_keep_filedate') && isset($_REQUEST['last_modified'])) {
            $last_modified = date('c', (int) ($_REQUEST['last_modified'] / 1000)); // Javascript provides UNIX time in milliseconds, RFC 3339 required
            $googledrive_file->setModifiedTime($last_modified);
        }

        // Create Folders if needed
        $upload_folder_id = Processor::instance()->get_last_folder();
        if (!empty($path)) {
            $upload_folder_id = $this->create_folder_structure($path);
        }

        // Convert file if needed
        $convert = false;
        if ('1' === Processor::instance()->get_shortcode_option('convert')) {
            $importformats = Processor::instance()->get_import_formats();
            $convert_formats = Processor::instance()->get_shortcode_option('convert_formats');
            if (('all' === $convert_formats[0] || in_array($mimetype, $convert_formats)) && (isset($importformats[$mimetype]))) {
                $convert = $importformats[$mimetype];
            }
        }

        // Overwrite if needed
        $current_entry_id = false;
        if ('1' === Processor::instance()->get_shortcode_option('overwrite')) {
            $parent_folder = Client::instance()->get_folder($upload_folder_id);
            $current_entry = Cache::instance()->get_node_by_name($name, $parent_folder['folder']);

            if (!empty($current_entry)) {
                $current_entry_id = $current_entry->get_id();
            }
        }

        // Call the API with the media upload, defer so it doesn't immediately return.
        App::instance()->get_sdk_client()->setDefer(true);
        if (empty($current_entry_id)) {
            $googledrive_file->setParents([$upload_folder_id]);
            $request = App::instance()->get_drive()->files->create($googledrive_file, ['fields' => Client::instance()->apifilefields, 'supportsAllDrives' => true]);
        } else {
            $request = App::instance()->get_drive()->files->update($current_entry_id, $googledrive_file, ['fields' => Client::instance()->apifilefields, 'supportsAllDrives' => true]);
        }

        // Create a media file upload to represent our upload process.
        $origin = $_REQUEST['orgin'];
        $request_headers = $request->getRequestHeaders();
        $request_headers['Origin'] = $origin;
        $request->setRequestHeaders($request_headers);

        $chunkSizeBytes = 50 * 1024 * 1024;
        $media = new \UYDGoogle_Http_MediaFileUpload(
            App::instance()->get_sdk_client(),
            $request,
            $mimetype,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize($size);

        try {
            $url = $media->getResumeUri();
            echo json_encode(['result' => 1, 'url' => $url, 'convert' => $convert]);
        } catch (\Exception $ex) {
            Helpers::log_error('File not uploaded to the cloud.', 'API', ['file_name' => $name], __LINE__, $ex);
            echo json_encode(['result' => 0]);
        }

        exit;
    }

    public static function get_upload_progress($file_hash)
    {
        wp_using_ext_object_cache(false);

        return get_transient('useyourdrive_upload_'.substr($file_hash, 0, 40));
    }

    public static function set_upload_progress($file_hash, $status)
    {
        wp_using_ext_object_cache(false);

        // Update progress
        return set_transient('useyourdrive_upload_'.substr($file_hash, 0, 40), $status, HOUR_IN_SECONDS);
    }

    public function get_upload_status()
    {
        $hash = $_REQUEST['hash'];

        // Try to get the upload status of the file
        for ($_try = 1; $_try < 6; ++$_try) {
            $result = self::get_upload_progress($hash);

            if (false !== $result) {
                if ('upload-failed' === $result['status']['progress'] || 'upload-finished' === $result['status']['progress']) {
                    delete_transient('useyourdrive_upload_'.substr($hash, 0, 40));
                }

                break;
            }

            // Wait a moment, perhaps the upload still needs to start
            usleep(500000 * $_try);
        }

        if (false === $result) {
            $result = ['file' => false, 'status' => ['bytes_up_so_far' => 0, 'total_bytes_up_expected' => 0, 'percentage' => 0, 'progress' => 'upload-failed']];
        }

        echo json_encode($result);

        exit;
    }

    public function upload_convert()
    {
        if (!isset($_REQUEST['fileid']) || !isset($_REQUEST['convert'])) {
            exit;
        }
        $file_id = $_REQUEST['fileid'];
        $convert = $_REQUEST['convert'];

        $current_folder_id = Processor::instance()->get_last_folder();
        Cache::instance()->pull_for_changes($current_folder_id, true);

        $cached_node = Client::instance()->get_entry($file_id);
        if (false === $cached_node) {
            echo json_encode(['result' => 0]);

            exit;
        }

        // If needed convert document. Only possible by copying the file and removing the old one
        try {
            $entry = new \UYDGoogle_Service_Drive_DriveFile();
            $entry->setName($cached_node->get_entry()->get_basename());
            $entry->setMimeType($convert);
            $api_entry = App::instance()->get_drive()->files->copy($cached_node->get_id(), $entry, ['fields' => Client::instance()->apifilefields, 'supportsAllDrives' => true]);

            if (false !== $api_entry && null !== $api_entry) {
                $new_id = $api_entry->getId();
                // Remove file from Cache
                App::instance()->get_drive()->files->delete($cached_node->get_id(), ['supportsAllDrives' => true]);
                Cache::instance()->remove_from_cache($cached_node->get_id(), 'deleted');
            }
        } catch (\Exception $ex) {
            echo json_encode(['result' => 0]);
            Helpers::log_error('Cannot convert file to a Google Doc.', 'Upload', null, __LINE__, $ex);

            exit;
        }

        echo json_encode(['result' => 1, 'fileid' => $new_id]);

        exit;
    }

    public function upload_post_process()
    {
        if ((!isset($_REQUEST['files'])) || 0 === count($_REQUEST['files'])) {
            echo json_encode(['result' => 0]);

            exit;
        }

        // Update the cache to process all changes
        $current_folder_id = Processor::instance()->get_last_folder();
        Cache::instance()->pull_for_changes($current_folder_id, true);

        $uploaded_files = $_REQUEST['files'];
        $_uploaded_entries = [];
        $_email_entries = [];

        foreach ($uploaded_files as $file_id) {
            $cached_node = Client::instance()->get_entry($file_id, false);

            if (false === $cached_node) {
                continue;
            }

            if (false === get_transient('useyourdrive_upload_'.$file_id)) {
                // Upload Hook
                $cached_node = apply_filters('useyourdrive_upload', $cached_node, Processor::instance());
                do_action('useyourdrive_log_event', 'useyourdrive_uploaded_entry', $cached_node);

                $_email_entries[] = $cached_node;
            }

            $_uploaded_entries[] = $cached_node;
        }

        do_action('useyourdrive_upload_post_process', $_uploaded_entries, Processor::instance());

        // Send email if needed
        if (!empty($_email_entries) && ('1' === Processor::instance()->get_shortcode_option('notificationupload'))) {
            Processor::instance()->send_notification_email('upload', $_email_entries);
        }

        // Return information of the files
        $files = [];

        foreach ($_uploaded_entries as $cached_node) {
            $file = [];
            $file['name'] = $cached_node->get_entry()->get_name();
            $file['type'] = $cached_node->get_entry()->get_mimetype();
            $file['description'] = $cached_node->get_entry()->get_description();
            $file['account_id'] = App::get_current_account()->get_id();
            $file['absolute_path'] = $cached_node->get_path('root');
            $file['relative_path'] = $cached_node->get_path(Processor::instance()->get_root_folder());
            $file['fileid'] = $cached_node->get_id();
            $file['filesize'] = Helpers::bytes_to_size_1024($cached_node->get_entry()->get_size());
            $file['folder_preview_url'] = false;
            $file['folder_shared_url'] = false;
            $file['folder_absolute_path'] = false;
            $file['folder_relative_path'] = false;

            $temp_thumburl = (false === strpos($cached_node->get_entry()->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $cached_node->get_entry()->get_thumbnail_with_size('w500-h375-p-k') : $cached_node->get_entry()->get_thumbnail_small().'&account_id='.App::get_current_account()->get_id().'&listtoken='.Processor::instance()->get_listtoken();
            $file['temp_thumburl'] = ($cached_node->get_entry()->has_own_thumbnail()) ? $temp_thumburl : null;
            $file['preview_url'] = urlencode(\str_replace('?usp=drivesdk', '', $cached_node->get_entry()->get_preview_link()));
            $file['shared_url'] = false;

            if (apply_filters('useyoudrive_upload_post_process_createlink', '1' === Processor::instance()->get_shortcode_option('upload_create_shared_link'), $cached_node, Processor::instance())) {
                $file['shared_url'] = urlencode(Client::instance()->get_embed_url($cached_node, ['return_thumbnail_url' => false]));
            }

            if ($cached_node->has_parent()) {
                // Create Shared Folder url if needed
                if ('1' === Processor::instance()->get_shortcode_option('upload_create_shared_link_folder')) {
                    $file['folder_shared_url'] = urlencode(Client::instance()->get_embed_url($cached_node->get_parent()));
                }

                $file['folder_preview_url'] = urlencode($cached_node->get_parent()->get_entry()->get_preview_link());
                $file['folder_absolute_path'] = $cached_node->get_parent()->get_path('root');
                $file['folder_relative_path'] = $cached_node->get_parent()->get_path(Processor::instance()->get_root_folder());
            }

            $files[$file['fileid']] = apply_filters('useyourdrive_upload_entry_information', $file, $cached_node, Processor::instance());

            set_transient('useyourdrive_upload_'.$cached_node->get_id(), true, HOUR_IN_SECONDS);
        }

        $files = apply_filters('useyourdrive_upload_post_process_data', $files, Processor::instance());

        // Clear Cached Requests
        CacheRequest::clear_request_cache();

        echo json_encode(['result' => 1, 'files' => $files]);
    }

    public function create_folder_structure($path)
    {
        $folders = explode('/', $path);
        $current_folder_id = Processor::instance()->get_last_folder();

        foreach ($folders as $name) {
            $current_folder = Client::instance()->get_folder($current_folder_id);

            if (empty($name)) {
                continue;
            }

            $cached_entry = Cache::instance()->get_node_by_name($name, $current_folder['folder']);

            if ($cached_entry) {
                $current_folder_id = $cached_entry->get_id();

                continue;
            }
            // Update the parent folder to make sure the latest version is loaded
            Cache::instance()->pull_for_changes($current_folder_id, true, -1);
            $cached_entry = Cache::instance()->get_node_by_name($name, $current_folder['folder']);

            if ($cached_entry) {
                $current_folder_id = $cached_entry->get_id();

                continue;
            }

            try {
                $newfolder = new \UYDGoogle_Service_Drive_DriveFile();
                $newfolder->setName($name);
                $newfolder->setMimeType('application/vnd.google-apps.folder');
                $newfolder->setParents([$current_folder_id]);
                $api_entry = App::instance()->get_drive()->files->create($newfolder, ['fields' => Client::instance()->apifilefields, 'supportsAllDrives' => true]);

                // Add new file to our Cache
                $newentry = new Entry($api_entry);
                $cached_entry = Cache::instance()->add_to_cache($newentry);
                do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $cached_entry);
                Cache::instance()->update_cache();
                $current_folder_id = $cached_entry->get_id();
            } catch (\Exception $ex) {
                Helpers::log_error('Failed to add user folder.', 'Dynamic Folders', null, __LINE__);

                return new \WP_Error('broke', esc_html__('Failed to add user folder', 'wpcloudplugins'));
            }
        }

        return $current_folder_id;
    }
}