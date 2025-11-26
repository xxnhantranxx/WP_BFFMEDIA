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

if (!function_exists('useyourdrive_api_php_client_autoload')) {
    require_once USEYOURDRIVE_ROOTDIR.'/vendors/Google-sdk/src/Google/autoload.php';
}

class Client
{
    public $apifilefields = 'capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey';
    public $apilistfilesfields = 'files(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),nextPageToken';
    public $apilistchangesfields = 'changes(file(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),removed, changeType, fileId),newStartPageToken,nextPageToken';

    /**
     * The single instance of the class.
     *
     * @var Client
     */
    protected static $_instance;

    public function __construct()
    {
        $this->apifilefields = apply_filters('useyourdrive_set_apifilefields', $this->apifilefields);
        $this->apilistfilesfields = apply_filters('useyourdrive_set_apilistfilesfields', $this->apilistfilesfields);
        $this->apilistchangesfields = apply_filters('useyourdrive_set_apilistchangesfields', $this->apilistchangesfields);
    }

    /**
     * Client Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return Client - Client instance
     *
     * @static
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function get_multiple_entries($entries)
    {
        if (empty($entries)) {
            return [];
        }

        if (1 === count($entries)) {
            $api_entry = App::instance()->get_drive()->files->get(reset($entries), ['supportsAllDrives' => true, 'fields' => $this->apifilefields]);

            return [$api_entry];
        }

        App::instance()->get_sdk_client()->setUseBatch(true);
        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

        foreach ($entries as $id) {
            $batch->add(App::instance()->get_drive()->files->get($id, ['fields' => $this->apifilefields, 'supportsAllDrives' => true]), $id);
        }

        try {
            if (defined('GOOGLE_API_BATCH')) {
                usleep(wp_rand(10000, 500000));
            } else {
                define('GOOGLE_API_BATCH', true);
            }
            $batch_result = $batch->execute();
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            throw $ex;
            // return false; CAN CAUSE CORRUPT CACHE
        }
        App::instance()->get_sdk_client()->setUseBatch(false);

        return $batch_result;
    }

    public function get_entries_in_subfolders(CacheNode $cachedfolder, $checkauthorized = true)
    {
        $result = $this->get_files_recursive($cachedfolder);
        $entries_in_searchedfolder = [];

        foreach ($result['files'] as $file) {
            $cached_node = $this->get_entry($file['ID'], $checkauthorized);

            if (empty($cached_node)) {
                continue;
            }

            $entries_in_searchedfolder[$cached_node->get_id()] = $cached_node;
        }

        return $entries_in_searchedfolder;
    }

    // Get entry
    public function get_entry($id = false, $checkauthorized = true)
    {
        if (false === $id) {
            $id = Processor::instance()->get_requested_entry();
        }

        try {
            $cached_node = API::get_entry($id);
        } catch (\Exception $ex) {
            return false;
        }
        if (true === $checkauthorized && 'root' !== $id && !Processor::instance()->_is_entry_authorized($cached_node)) {
            return false;
        }

        if ($cached_node->is_shortcut()) {
            return $this->get_entry($cached_node->get_original_node_id(), $checkauthorized);
        }

        return $cached_node;
    }

    // Get folders and files
    public function get_folder($folderid = false, $checkauthorized = true)
    {
        if (false === $folderid) {
            $folderid = Processor::instance()->get_requested_entry();
        }

        try {
            $cached_node = API::get_folder($folderid);
        } catch (\Exception $ex) {
            return false;
        }

        if (empty($cached_node)) {
            Helpers::log_error('Folder is not found', 'Client', ['entry_id' => $folderid], __LINE__);

            return false;
        }

        // Check if folder is in the shortcode-set rootfolder
        if (true === $checkauthorized && !Processor::instance()->_is_entry_authorized($cached_node)) {
            return false;
        }

        if ($cached_node->is_shortcut()) {
            return $this->get_folder($cached_node->get_original_node_id(), $checkauthorized);
        }

        return ['folder' => $cached_node, 'contents' => $cached_node->get_children()];
    }

    public function get_shortcuts_for_entry(CacheNode $entry_node)
    {
        $shortcuts_found = [];
        $api_entries_found = [];

        if ($entry_node->is_virtual_folder()) {
            // Virtual folders can't have shortcuts
            return $shortcuts_found;
        }

        if (null !== $entry_node->get_original_node_for()) {
            return $entry_node->get_original_node_for();
        }

        // Find all items containing query
        $params = [
            'q' => "shortcutDetails.targetId='{$entry_node->get_id()}' and trashed = false",
            'fields' => $this->apilistfilesfields,
            'pageSize' => 500,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ];

        do {
            try {
                $search_response = App::instance()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

                return $shortcuts_found;
            }

            // Process the response
            $more_files = $search_response->getFiles();
            $api_entries_found = array_merge($api_entries_found, $more_files);

            $nextpagetoken = $search_response->getNextPageToken();
            $params['pageToken'] = $nextpagetoken;
        } while (null !== $nextpagetoken);

        $entries_found = [];
        $new_parent_folders = [];

        foreach ($api_entries_found as $api_entry) {
            $entry = new Entry($api_entry);
            $entries_found[] = $entry;

            if ($entry->has_parent()) {
                $parent_id = $entry->get_parent_id();
                if (false === Cache::instance()->get_node_by_id($parent_id, false)) {
                    $new_parent_folders[$parent_id] = $parent_id;
                }
            }
        }

        // Load all new parents at once
        $new_parents_folders_api = $this->get_multiple_entries($new_parent_folders);
        foreach ($new_parents_folders_api as $parent) {
            if (!$parent instanceof EntryAbstract) {
                $parent = new Entry($parent);
            }

            Cache::instance()->add_to_cache($parent);
        }

        foreach ($entries_found as $entry) {
            // Check if files are in cache
            $cached_node = Cache::instance()->is_cached($entry->get_id(), 'id', true);

            // If not found, add to cache
            if (false === $cached_node) {
                $cached_node = Cache::instance()->add_to_cache($entry);
            } else {
                // Update Thumbnails
                $cached_node_node = $cached_node->get_entry();
                $cached_node_node->set_thumbnail_icon($entry->get_thumbnail_icon());
                $cached_node_node->set_thumbnail_small($entry->get_thumbnail_small());
                $cached_node_node->set_thumbnail_small_cropped($entry->get_thumbnail_small_cropped());
                $cached_node_node->set_thumbnail_large($entry->get_thumbnail_large());
                $cached_node_node->set_thumbnail_original($entry->get_thumbnail_original());

                Cache::instance()->set_updated();
            }

            $shortcuts_found[] = $cached_node;
        }

        $entry_node->add_original_node_for(array_keys($shortcuts_found));
        // Update the cache already here so that the Search Output is cached
        Cache::instance()->update_cache();

        return $shortcuts_found;
    }

    public function delete_entries($entries_to_delete = [])
    {
        foreach ($entries_to_delete as $key => $entry_id) {
            $target_cached_entry = $this->get_entry($entry_id);

            if (false === $target_cached_entry) {
                unset($entries_to_delete[$key]);

                continue;
            }

            $target_entry = $target_cached_entry->get_entry();

            if ($target_entry->is_file() && false === User::can_delete_files()) {
                Helpers::log_error('Failed to delete entry as user is not allowed to remove files', 'Client', ['entry_id' => $target_entry->get_id], __LINE__);

                unset($entries_to_delete[$key]);
            }

            if ($target_entry->is_dir() && false === User::can_delete_folders()) {
                Helpers::log_error('Failed to delete entry as user is not allowed to remove folders', 'Client', ['entry_id' => $target_entry->get_id], __LINE__);

                unset($entries_to_delete[$key]);
            }

            if ('1' === Processor::instance()->get_shortcode_option('demo')) {
                unset($entries_to_delete[$key]);
            }
        }

        $deleted_entries = API::delete($entries_to_delete, Processor::instance()->get_shortcode_option('deletetotrash'));

        // Send email if needed
        if ('1' === Processor::instance()->get_shortcode_option('notificationdeletion')) {
            Processor::instance()->send_notification_email('deletion_multiple', $deleted_entries);
        }

        return $deleted_entries;
    }

    // Rename entry from Google Drive

    public function rename_entry($new_filename = null)
    {
        if ('1' === Processor::instance()->get_shortcode_option('demo')) {
            return new \WP_Error('broke', esc_html__('Failed to rename file.', 'wpcloudplugins'));
        }

        if (null === $new_filename) {
            return new \WP_Error('broke', esc_html__('No new name set', 'wpcloudplugins'));
        }

        // Get entry meta data
        $cached_node = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached_node) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
            if (false === $cached_node) {
                return new \WP_Error('broke', esc_html__('Failed to rename file.', 'wpcloudplugins'));
            }
        }

        // Check if user is allowed to delete from this dir
        if (!$cached_node->is_in_folder(Processor::instance()->get_last_folder())) {
            return new \WP_Error('broke', esc_html__('You are not authorized to rename files in this directory', 'wpcloudplugins'));
        }

        $entry = $cached_node->get_entry();

        // Check user permission
        if (!$entry->get_permission('canrename')) {
            return new \WP_Error('broke', esc_html__('You are not authorized to rename this file or folder', 'wpcloudplugins'));
        }

        // Check if entry is allowed
        if (!Processor::instance()->_is_entry_authorized($cached_node)) {
            return new \WP_Error('broke', esc_html__('You are not authorized to rename this file or folder', 'wpcloudplugins'));
        }

        if ($entry->is_dir() && (false === User::can_rename_folders())) {
            return new \WP_Error('broke', esc_html__('You are not authorized to rename folder', 'wpcloudplugins'));
        }

        if ($entry->is_file() && (false === User::can_rename_files())) {
            return new \WP_Error('broke', esc_html__('You are not authorized to rename this file', 'wpcloudplugins'));
        }

        $extension = $entry->get_extension();
        $name = (!empty($extension)) ? $new_filename.'.'.$extension : $new_filename;
        $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
        $updateentry->setName($name);

        try {
            $renamed_entry = API::patch($entry->get_id(), $updateentry);

            do_action('useyourdrive_log_event', 'useyourdrive_renamed_entry', $renamed_entry, ['old_name' => $entry->get_name()]);
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            return new \WP_Error('broke', esc_html__('Failed to rename file.', 'wpcloudplugins'));
        }

        return $renamed_entry;
    }

    // Move & Copy entry

    public function move_entries($entries, $target, $copy = false)
    {
        $entries_to_move = [];

        $cached_target = $this->get_entry($target);
        $cached_current_folder = $this->get_entry(Processor::instance()->get_last_folder());

        if (false === $cached_target) {
            Helpers::log_error('Failed to move as target folder is not found.', 'Client', ['target' => $target], __LINE__);

            return $entries_to_move;
        }

        foreach ($entries as $key => $entry_id) {
            $entries_to_move[$entry_id] = false; // Set after Request is finished

            $cached_node = $this->get_entry($entry_id);

            if (false === $cached_node) {
                unset($entries[$key]);

                continue;
            }

            $entry = $cached_node->get_entry();

            if (!$copy && $entry->is_dir() && (false === User::can_move_folders())) {
                Helpers::log_error('Failed to move as user is not allowed to move folders.', 'Client', ['target' => $cached_node->get_id()], __LINE__);

                unset($entries[$key]);

                continue;
            }

            if ($copy && $entry->is_dir() && (false === User::can_copy_folders())) {
                Helpers::log_error('Failed to move as user is not allowed to copy folders.', 'Client', ['target' => $cached_node->get_id()], __LINE__);
                unset($entries[$key]);

                continue;
            }

            if (!$copy && $entry->is_file() && (false === User::can_move_files())) {
                Helpers::log_error('Failed to move as user is not allowed to move files.', 'Client', ['target' => $cached_node->get_id()], __LINE__);
                unset($entries[$key]);

                continue;
            }

            if ($copy && $entry->is_file() && (false === User::can_copy_files())) {
                Helpers::log_error('Failed to move as user is not allowed to copy files.', 'Client', ['target' => $cached_node->get_id()], __LINE__);
                unset($entries[$key]);

                continue;
            }

            if ('1' === Processor::instance()->get_shortcode_option('demo')) {
                unset($entries[$key]);

                continue;
            }

            // Check if user is allowed to delete from this dir
            if (!$cached_node->is_in_folder($cached_current_folder->get_id())) {
                Helpers::log_error('Failed to move as user is not allowed to move items in this directoy.', 'Client', ['target' => $cached_node->get_id()], __LINE__);
                unset($entries[$key]);

                continue;
            }

            // Check user permission
            if (!$copy && !$entry->get_permission('canmove')) {
                Helpers::log_error('Failed to move as the sharing permissions on it prevent this.', 'Client', ['target' => $cached_node->get_id()], __LINE__);
                unset($entries[$key]);
            }
        }

        // Execute the Batch Call
        try {
            $entries_to_move = API::move($entries, $cached_target->get_id(), $copy);
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            return $entries_to_move;
        }

        // Send email if needed
        if ($copy && '1' === Processor::instance()->get_shortcode_option('notificationcopy')) {
            Processor::instance()->send_notification_email('copy_multiple', $entries_to_move);
        } elseif ('1' === Processor::instance()->get_shortcode_option('notificationmove')) {
            Processor::instance()->send_notification_email('move_multiple', $entries_to_move);
        }

        return $entries_to_move;
    }

    public function create_shortcuts($entries, $target)
    {
        $shortcuts_to_create = [];

        $cached_target = $this->get_entry($target);
        $cached_current_folder = $this->get_entry(Processor::instance()->get_last_folder());

        if (false === $cached_target) {
            Helpers::log_error('Failed to create shortcut as target folder is not found.', 'Client', ['entry_id' => $target], __LINE__);

            return $shortcuts_to_create;
        }

        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

        foreach ($entries as $entry_id) {
            App::instance()->get_sdk_client()->setUseBatch(false);
            $cached_node = $this->get_entry($entry_id);
            App::instance()->get_sdk_client()->setUseBatch(true);

            if (false === $cached_node) {
                continue;
            }

            $entry = $cached_node->get_entry();

            if ($entry->is_dir() && (false === User::can_create_shortcuts_folder())) {
                Helpers::log_error('Failed to create shortcut as user is not allowed to create shortcuts for folders.', 'Client', ['entry_id' => $cached_node->get_id()], __LINE__);
                $shortcuts_to_create[$cached_node->get_id()] = false;

                continue;
            }

            if ($entry->is_file() && (false === User::can_create_shortcuts_files())) {
                Helpers::log_error('Failed to create shortcut as user is not allowed to create shortcuts for files.', 'Client', ['entry_id' => $cached_node->get_id()], __LINE__);
                $shortcuts_to_create[$cached_node->get_id()] = false;

                continue;
            }

            if ('1' === Processor::instance()->get_shortcode_option('demo')) {
                $shortcuts_to_create[$cached_node->get_id()] = false;

                continue;
            }

            // Check if user is allowed to access the requested entry
            if (!$cached_node->is_in_folder($cached_current_folder->get_id())) {
                Helpers::log_error('Failed to create shortcut as user is not allowed to create shortcuts in this directory.', 'Client', ['entry_id' => $cached_node->get_id()], __LINE__);
                $shortcuts_to_create[$cached_node->get_id()] = false;

                continue;
            }

            $shortcuts_to_create[$cached_node->get_id()] = false; // Set after Batch Request $cached_node;

            // Create an the entry for Patch
            $shortcut = new \UYDGoogle_Service_Drive_DriveFile();
            $shortcut->setName($entry->get_name());
            $shortcut->setMimetype('application/vnd.google-apps.shortcut');
            $shortcut_details = new \UYDGoogle_Service_Drive_DriveFileShortcutDetails();
            $shortcut_details->setTargetId($entry->get_id());
            $shortcut_details->setTargetMimeType($entry->get_mimetype());
            $shortcut->setShortcutDetails($shortcut_details);
            $shortcut->setParents([$cached_target->get_id()]);

            $call = App::instance()->get_drive()->files->create($shortcut, ['fields' => $this->apifilefields, 'supportsAllDrives' => true]);

            $batch->add($call);
        }

        // Execute the Batch Call
        try {
            if (defined('GOOGLE_API_BATCH')) {
                usleep(50000);
            } else {
                define('GOOGLE_API_BATCH', true);
            }
            $batch_result = $batch->execute();

            App::instance()->get_sdk_client()->setUseBatch(false);

            foreach ($batch_result as $api_entry) {
                $new_cached_entry = new Entry($api_entry);

                $cached_updated_entry = Cache::instance()->add_to_cache($new_cached_entry);
                $shortcuts_to_create[$cached_updated_entry->get_id()] = $cached_updated_entry;

                do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $cached_updated_entry);
            }
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            return $shortcuts_to_create;
        }

        // Clear Cached Requests
        CacheRequest::clear_local_cache_for_shortcode(App::get_current_account()->get_id(), Processor::instance()->get_listtoken());

        return $shortcuts_to_create;
    }

    // Edit descriptions entry from Google Drive

    public function update_description($new_description = null)
    {
        if (null === $new_description) {
            return new \WP_Error('broke', esc_html__('No new description set', 'wpcloudplugins'));
        }

        // Get entry meta data
        $cached_node = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached_node) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
            if (false === $cached_node) {
                return new \WP_Error('broke', esc_html__('Failed to edit file.', 'wpcloudplugins'));
            }
        }

        // Check if user is allowed to delete from this dir
        if (!$cached_node->is_in_folder(Processor::instance()->get_last_folder())) {
            return new \WP_Error('broke', esc_html__('You are not authorized to edit files in this directory', 'wpcloudplugins'));
        }

        $entry = $cached_node->get_entry();

        // Check user permission
        if (!$entry->get_permission('canrename')) {
            return new \WP_Error('broke', esc_html__('You are not authorized to edit this file or folder', 'wpcloudplugins'));
        }

        // Check if entry is allowed
        if (!Processor::instance()->_is_entry_authorized($cached_node)) {
            return new \WP_Error('broke', esc_html__('You are not authorized to edit this file or folder', 'wpcloudplugins'));
        }

        // Create an the entry for Patch
        $updated_entry = new \UYDGoogle_Service_Drive_DriveFile();
        $updated_entry->setDescription($new_description);

        try {
            $edited_entry = API::patch($entry->get_id(), $updated_entry);

            do_action('useyourdrive_log_event', 'useyourdrive_updated_description', $edited_entry, ['description' => $new_description]);
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            return new \WP_Error('broke', esc_html__('Failed to edit file.', 'wpcloudplugins'));
        }

        return $edited_entry->get_entry()->get_description();
    }

    // Add entry to Google Drive
    public function add_entry($new_name, $mimetype)
    {
        if ('1' === Processor::instance()->get_shortcode_option('demo')) {
            return new \WP_Error('broke', esc_html__('Failed to add file.', 'wpcloudplugins'));
        }

        if (null === $new_name) {
            return new \WP_Error('broke', esc_html__('No new name set', 'wpcloudplugins'));
        }

        // Get entry meta data of current folder
        $cached_node = Cache::instance()->is_cached(Processor::instance()->get_last_folder());

        if (false === $cached_node) {
            $cached_node = $this->get_entry(Processor::instance()->get_last_folder());
            if (false === $cached_node) {
                return new \WP_Error('broke', esc_html__('Failed to add file.', 'wpcloudplugins'));
            }
        }

        if (!Processor::instance()->_is_entry_authorized($cached_node)) {
            return new \WP_Error('broke', esc_html__('You are not authorized to add something in this directory.', 'wpcloudplugins'));
        }

        $currentfolder = $cached_node->get_entry();

        // Check user permission
        if (!$currentfolder->get_permission('canadd')) {
            return new \WP_Error('broke', esc_html__('You are not authorized to add a file.', 'wpcloudplugins'));
        }

        try {
            $new_cached_entry = API::create_entry($new_name, $currentfolder->get_id(), $mimetype);
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            return new \WP_Error('broke', esc_html__('Failed to add file.', 'wpcloudplugins'));
        }

        return $new_cached_entry;
    }

    /**
     * Create thumbnails for Google docs which need a accesstoken.
     */
    public function build_thumbnail()
    {
        $cached = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
        } else {
            $cached_node = $cached;
        }

        if (false === $cached_node) {
            exit;
        }

        // Check if entry is allowed
        if (!Processor::instance()->_is_entry_authorized($cached_node)) {
            exit;
        }

        $thumbnail_original = $cached_node->get_entry()->get_thumbnail_original();
        if (empty($thumbnail_original)) {
            header('Location: '.$cached_node->get_entry()->get_default_thumbnail_icon());

            exit;
        }

        // Set the thumbnail attributes & file
        switch ($_REQUEST['s']) {
            case 'icon':
                $thumbnail_attributes = '=h16-c-nu';

                break;

            case 'small':
                $thumbnail_attributes = '=w400-h300-p-k';

                break;

            case 'cropped':
                $thumbnail_attributes = 'w500-h375-c-nu';

                break;

            case 'large':
            default:
                $thumbnail_attributes = '=s0';

                break;
        }

        // Check if file already exists
        $thumbnail_file = $cached_node->get_id().$thumbnail_attributes.'.png';
        if (file_exists(USEYOURDRIVE_CACHEDIR.'/thumbnails/'.$thumbnail_file) && (filemtime(USEYOURDRIVE_CACHEDIR.'/thumbnails/'.$thumbnail_file) === strtotime($cached_node->get_entry()->get_last_edited()))) {
            $url = USEYOURDRIVE_CACHEURL.'/thumbnails/'.$thumbnail_file;

            // Update the cached node
            switch ($_REQUEST['s']) {
                case 'icon':
                    $cached_node->get_entry()->set_thumbnail_icon($url);

                    // no break
                case 'small':
                    $cached_node->get_entry()->set_thumbnail_small($url);

                    break;

                case 'cropped':
                    $cached_node->get_entry()->set_thumbnail_small_cropped($url);

                    break;

                case 'large':
                default:
                    $cached_node->get_entry()->set_thumbnail_large($url);
                    $thumbnail_attributes = '=s0';

                    break;
            }
            Cache::instance()->set_updated(true);
            Cache::instance()->update_cache();

            header('Location: '.$url);

            exit;
        }

        // Build the thumbnail URL where we fetch the thumbnail

        $downloadlink = $cached_node->get_entry()->get_thumbnail_original();
        $downloadlink = str_replace('=s220', $thumbnail_attributes, $downloadlink);

        // Do the request
        try {
            App::instance()->get_sdk_client()->getAccessToken();
            $request = new \UYDGoogle_Http_Request($downloadlink, 'GET');
            App::instance()->get_sdk_client()->getIo()->setOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
            $httpRequest = App::instance()->get_sdk_client()->getAuth()->authenticatedRequest($request);

            if (!file_exists(USEYOURDRIVE_CACHEDIR.'/thumbnails')) {
                @mkdir(USEYOURDRIVE_CACHEDIR.'/thumbnails', 0755);
            }

            if (!is_writable(USEYOURDRIVE_CACHEDIR.'/thumbnails')) {
                @chmod(USEYOURDRIVE_CACHEDIR.'/thumbnails', 0755);
            }

            // Save the thumbnail locally
            @file_put_contents(USEYOURDRIVE_CACHEDIR.'/thumbnails/'.$thumbnail_file, $httpRequest->getResponseBody()); // New SDK: $response->getBody()
            touch(USEYOURDRIVE_CACHEDIR.'/thumbnails/'.$thumbnail_file, strtotime($cached_node->get_entry()->get_last_edited()));
            $url = USEYOURDRIVE_CACHEURL.'/thumbnails/'.$thumbnail_file;

            // Update the cached node
            switch ($_REQUEST['s']) {
                case 'icon':
                    $cached_node->get_entry()->set_thumbnail_icon($url);

                    // no break
                case 'small':
                    $cached_node->get_entry()->set_thumbnail_small($url);

                    break;

                case 'cropped':
                    $cached_node->get_entry()->set_thumbnail_small_cropped($url);

                    break;

                case 'large':
                default:
                    $cached_node->get_entry()->set_thumbnail_large($url);
                    $thumbnail_attributes = '=s0';

                    break;
            }
            Cache::instance()->set_updated(true);
            header('Location: '.$url);
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);
        }

        exit;
    }

    public function get_folder_thumbnails()
    {
        $thumbnails = [];
        $maximages = 3;
        $target_height = Processor::instance()->get_shortcode_option('targetheight');
        $target_width = round($target_height * (4 / 3));

        $folder = $this->get_folder();

        if (false === $folder) {
            return $thumbnails;
        }

        $all_subfolders = $folder['folder']->get_all_sub_folders();
        $folders_id = [];

        foreach ($all_subfolders as $subfolder) {
            // Use the orginial entry if the folder is a shortcut
            if ($subfolder->is_shortcut()) {
                $shortcut_node = $this->get_folder($subfolder->get_original_node_id(), false);
                $subfolder = $shortcut_node['folder'];
            }

            $subfolder_entry = $subfolder->get_entry();
            $folder_thumbnails = $subfolder_entry->get_folder_thumbnails();

            // 1: First if the cache still has valid thumbnails available
            if (isset($folder_thumbnails['expires']) && $folder_thumbnails['expires'] > time()) {
                $iimages = 1;
                $thumbnails_html = '';

                foreach ($folder_thumbnails['thumbs'] as $folder_thumbnail) {
                    $thumb_url = $subfolder_entry->get_thumbnail_with_size('h'.round($target_height * 2).'-w'.round($target_width * 2).'-nu', $folder_thumbnail);
                    $thumbnails_html .= "<div class='folder-thumb thumb{$iimages}' style='width:".$target_width.'px;height:'.$target_height.'px;background-image: url('.$thumb_url.")'></div>";
                    ++$iimages;
                }
                $thumbnails[$subfolder->get_id()] = $thumbnails_html;
            } else {
                $cached_node = Cache::instance()->is_cached($subfolder->get_id(), 'id', false);
                // 2: Check if we can use the content of the folder itself
                if (false !== $cached_node && !$cached_node->is_expired()) {
                    $iimages = 1;
                    $thumbnails_html = '';

                    $children = $subfolder->get_children();
                    foreach ($children as $cached_child) {
                        $entry = $cached_child->get_entry();
                        if ($iimages > $maximages) {
                            break;
                        }

                        if (!$entry->has_own_thumbnail() || !$entry->is_file()) {
                            continue;
                        }

                        $thumbnail = $entry->get_thumbnail_with_size('h'.round($target_height * 2).'-w'.round($target_width * 2).'-nu');
                        $thumbnails_html .= "<div class='folder-thumb thumb{$iimages}' style='width:".$target_width.'px;height:'.$target_height.'px;background-image: url('.$thumbnail.")'></div>";
                        ++$iimages;
                    }

                    $thumbnails[$subfolder->get_id()] = $thumbnails_html;
                } else {
                    // 3: If we don't have thumbnails available, get them
                    $folders_id[] = $subfolder->get_id();
                }
            }
        }

        if (empty($folders_id)) {
            CacheRequest::clear_local_cache_for_shortcode(App::get_current_account()->get_id(), Processor::instance()->get_listtoken());

            return $thumbnails;
        }

        $requests = array_chunk($folders_id, 99, true);
        $batch_results = [];

        // Find all items containing query
        $params = [
            'fields' => 'files(id,thumbnailLink,parents),nextPageToken',
            'pageSize' => $maximages,
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
        ];

        foreach ($requests as $request_folder_ids) {
            App::instance()->get_sdk_client()->setUseBatch(true);
            $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

            foreach ($request_folder_ids as $request_folder_id) {
                $params['q'] = "'{$request_folder_id}' in parents and (mimeType = 'image/gif' or mimeType = 'image/png' or mimeType = 'image/jpeg' or mimeType = 'x-ms-bmp' or mimeType = 'image/webp') and trashed = false";
                $batch->add(App::instance()->get_drive()->files->listFiles($params), $request_folder_id);
            }

            if (defined('GOOGLE_API_BATCH')) {
                usleep(50000);
            } else {
                define('GOOGLE_API_BATCH', true);
            }

            try {
                $batch_result = $batch->execute();
            } catch (\Exception $ex) {
                Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

                throw $ex;
            }
            App::instance()->get_sdk_client()->setUseBatch(false);

            $batch_results = array_merge($batch_results, $batch_result);
        }

        foreach ($batch_results as $batchkey => $result) {
            $folderid = str_replace('response-', '', $batchkey);
            $subfolder = $all_subfolders[$folderid];

            $images = $result->getFiles();

            if (!is_array($images)) {
                continue;
            }

            $iimages = 1;
            $thumbnails_html = '';
            $folder_thumbs = [];

            foreach ($images as $image) {
                $entry = new Entry($image);
                $folder_thumbs[] = $entry->get_thumbnail_small();
                $thumbnail = $entry->get_thumbnail_with_size('h'.round($target_height * 2).'-w'.round($target_width * 2).'-nu');
                $thumbnails_html .= "<div class='folder-thumb thumb{$iimages}' style='display:none; width:".$target_width.'px;height:'.$target_height.'px;background-image: url('.$thumbnail.")'></div>";
                ++$iimages;
            }

            $subfolder->get_entry()->set_folder_thumbnails(['expires' => time() + 1800, 'thumbs' => $folder_thumbs]);
            $thumbnails[$folderid] = $thumbnails_html;
        }

        Cache::instance()->set_updated();

        CacheRequest::clear_local_cache_for_shortcode(App::get_current_account()->get_id(), Processor::instance()->get_listtoken());

        return $thumbnails;
    }

    public function preview_entry()
    {
        // Check if file is cached and still valid
        $cached = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
        } else {
            $cached_node = $cached;
        }

        if (false === $cached_node) {
            exit;
        }

        // Use the orginial entry if the file/folder is a shortcut
        if ($cached_node->is_shortcut()) {
            $original_node = $cached_node->get_original_node();

            if (!empty($original_node)) {
                $cached_node = $original_node;
            }
        }

        $entry = $cached_node->get_entry();

        if (false === $entry->get_can_preview_by_cloud()) {
            exit('-1');
        }

        // get the last-modified-date of this very file
        $lastModified = strtotime($entry->get_last_edited());
        // get a unique hash of this file (etag)
        $etagFile = md5($lastModified);
        // get the HTTP_IF_MODIFIED_SINCE header if set
        $ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false);
        // get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
        $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

        if (!empty($entry->get_last_edited())) {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModified).' GMT');
            header("Etag: {$etagFile}");
        }

        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 60 * 5).' GMT');
        header('Cache-Control: must-revalidate');

        // check if page has changed. If not, send 304 and exit
        if (false !== $lastModified && !empty($entry->get_last_edited()) && false !== $cached && (@strtotime($ifModifiedSince) == $lastModified || $etagHeader == $etagFile)) {
            // Send email if needed
            if ('1' === Processor::instance()->get_shortcode_option('notificationdownload') && !isset($_REQUEST['raw'])) {
                Processor::instance()->send_notification_email('download', [$cached_node]);
            }

            do_action('useyourdrive_preview', $cached_node);

            do_action('useyourdrive_log_event', 'useyourdrive_previewed_entry', $cached_node);

            header('HTTP/1.1 304 Not Modified');

            exit;
        }

        // Check if entry is allowed
        if (!Processor::instance()->_is_entry_authorized($cached_node)) {
            exit;
        }

        // Download original images rather than showing a thumbnail.
        if (isset($_REQUEST['raw'])) {
            $entry = $cached_node->get_entry();

            $extension = $entry->get_extension();
            $allowedextensions = ['gif', 'jpg', 'jpeg', 'png', 'bmp', 'cr2', 'crw', 'raw', 'tif', 'tiff', 'webp', 'heic'];

            if (empty($extension) || !in_array($extension, $allowedextensions)) {
                exit;
            }

            $download = new Download($cached_node, 'default');
            $download->set_download_method('proxy');
            $download->start_download();

            exit;
        }

        $previewurl = $this->get_embed_url($cached_node);

        if (false === $previewurl) {
            Helpers::log_error('Cannot generate preview/embed link', 'Client', ['entry_id' => $cached_node->get_id()], __LINE__);

            exit;
        }

        if ('0' === Processor::instance()->get_shortcode_option('previewinline') && User::can_download()) {
            $previewurl = str_replace('preview?rm=demo', 'view?rm=demo', $previewurl);
            $previewurl = str_replace('preview?rm=minimal', 'view?', $previewurl);
        }

        header('Location: '.$previewurl);

        do_action('useyourdrive_preview', $cached_node);
        do_action('useyourdrive_log_event', 'useyourdrive_previewed_entry', $cached_node);

        exit;
    }

    public function edit_entry()
    {
        // Check if file is cached and still valid
        $cached = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
        } else {
            $cached_node = $cached;
        }

        if (false === $cached_node) {
            exit;
        }

        $entry = $cached_node->get_entry();

        if ($entry->is_dir() || false === $entry->get_can_edit_by_cloud()) {
            exit('-1');
        }

        // get the last-modified-date of this very file
        $lastModified = strtotime($entry->get_last_edited());
        // get a unique hash of this file (etag)
        $etagFile = md5($lastModified);
        // get the HTTP_IF_MODIFIED_SINCE header if set
        $ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false);
        // get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
        $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

        if (!empty($entry->get_last_edited())) {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModified).' GMT');
            header("Etag: {$etagFile}");
        }

        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 60 * 5).' GMT');
        header('Cache-Control: must-revalidate');

        // check if page has changed. If not, send 304 and exit
        if (false !== $lastModified && !empty($entry->get_last_edited()) && false !== $cached && (@strtotime($ifModifiedSince) == $lastModified || $etagHeader == $etagFile)) {
            do_action('useyourdrive_edit', $cached_node);
            do_action('useyourdrive_log_event', 'useyourdrive_edited_entry', $cached_node);

            header('HTTP/1.1 304 Not Modified');

            exit;
        }

        $edit_link = API::create_edit_url($cached_node->get_id());

        if (empty($edit_link)) {
            Helpers::log_error('Cannot create a editable link.', 'Client', ['entry_id' => $cached_node->get_id()], __LINE__);

            exit;
        }

        $edit_type = Processor::instance()->get_shortcode_option('edit_type');
        if ('full' === $edit_type) {
            $edit_link .= '&rm=embedded&embedded=true';
        } else {
            $edit_link .= '&rm=minimal&embedded=true';
        }

        do_action('useyourdrive_edit', $cached_node);
        do_action('useyourdrive_log_event', 'useyourdrive_edited_entry', $cached_node);

        header('Location: '.$edit_link);

        exit;
    }

    // Download file

    public function download_entry()
    {
        // Check if file is cached and still valid
        $cached = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
        } else {
            $cached_node = $cached;
        }

        if (false === $cached_node) {
            exit;
        }

        $entry = $cached_node->get_entry();

        $event_type = (isset($_REQUEST['action']) && 'useyourdrive-stream' === $_REQUEST['action']) ? 'useyourdrive_streamed_entry' : 'useyourdrive_downloaded_entry';

        // get the last-modified-date of this very file
        $lastModified = strtotime($entry->get_last_edited());
        // get a unique hash of this file (etag)
        $etagFile = md5($lastModified);
        // get the HTTP_IF_MODIFIED_SINCE header if set
        $ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false);
        // get the HTTP_IF_NONE_MATCH header if set (etag: unique file hash)
        $etagHeader = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);

        if (!empty($entry->get_last_edited())) {
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastModified).' GMT');
            header("Etag: {$etagFile}");
        }

        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 60 * 5).' GMT');
        header('Cache-Control: must-revalidate');

        // check if page has changed. If not, send 304 and exit
        if (false !== $lastModified && !empty($entry->get_last_edited()) && false !== $cached && (@strtotime($ifModifiedSince) == $lastModified || $etagHeader == $etagFile)) {
            // Send email if needed
            if ('1' === Processor::instance()->get_shortcode_option('notificationdownload') && 'useyourdrive_downloaded_entry' === $event_type) {
                Processor::instance()->send_notification_email('download', [$cached_node]);
            }

            do_action('useyourdrive_download', $cached_node);

            do_action('useyourdrive_log_event', $event_type, $cached_node);

            header('HTTP/1.1 304 Not Modified');

            exit;
        }

        // Check if entry is allowed
        if (!Processor::instance()->_is_entry_authorized($cached_node)) {
            exit;
        }

        $download = new Download($cached_node);

        $download->start_download();

        exit;
    }

    public function stream_entry()
    {
        // Check if file is cached and still valid
        $cached = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

        if (false === $cached) {
            $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
        } else {
            $cached_node = $cached;
        }

        if (false === $cached_node) {
            exit;
        }

        $entry = $cached_node->get_entry();

        $extension = $entry->get_extension();
        $allowedextensions = ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'oga', 'wav', 'webm', 'flac', 'vtt', 'srt'];

        if (empty($extension) || !in_array($extension, $allowedextensions)) {
            exit;
        }

        if (in_array($extension, ['vtt', 'srt'])) {
            // Download Captions directly
            $download = new Download($cached_node, 'default', true);
        } else {
            $download = new Download($cached_node);
        }

        // Google currently doesn't support direct streams anymore. Enabled this again when changed.
        // if ($entry->get_size() > 26214400) {
        $download->set_download_method('proxy');
        // }

        $download->start_download();

        exit;
    }

    public function get_embed_url(CacheNode $cached_node, $extra = ['return_thumbnail_url' => true])
    {
        // Check the permissions and set it if possible
        if (!API::has_permission($cached_node->get_id())) {
            API::set_permission($cached_node->get_id(), 'reader', 'embed');
        }

        $entry = $cached_node->get_entry();
        $mimetype = $entry->get_mimetype();

        $arguments = 'preview?rm=demo';

        switch ($mimetype) {
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/vnd.google-apps.document':
                $arguments = 'preview?rm=minimal'; // rm=minimal&overridemobile=true'; Causing errors on iPads
                $preview = 'https://docs.google.com/document/d/'.$cached_node->get_id().'/'.$arguments;

                break;

            case 'application/vnd.ms-excel':
            case 'application/vnd.ms-excel.sheet.macroenabled.12':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.google-apps.spreadsheet':
                $preview = 'https://docs.google.com/spreadsheets/d/'.$cached_node->get_id().'/'.$arguments;

                break;

            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
            case 'application/vnd.google-apps.presentation':
                $preview = 'https://docs.google.com/presentation/d/'.$cached_node->get_id().'/'.$arguments;

                break;

            case 'application/vnd.google-apps.folder':
                $preview = 'https://drive.google.com/open?id='.$cached_node->get_id();

                break;

            case 'application/vnd.google-apps.drawing':
                $preview = 'https://docs.google.com/drawings/d/'.$cached_node->get_id().'?';

                break;

            case 'application/vnd.google-apps.form':
                $preview = 'https://docs.google.com/forms/d/'.$cached_node->get_id().'/viewform?';

                break;

            default:
                $preview = 'https://drive.google.com/file/d/'.$cached_node->get_id().'/preview?rm=minimal';

                break;
        }

        // Add Resources key to give permission to access the item
        if ($entry->has_resourcekey()) {
            $preview .= "&resourcekey={$entry->get_resourcekey()}";
        }

        // For images, just return the actual file
        if (in_array($cached_node->get_entry()->get_extension(), ['jpg', 'jpeg', 'gif', 'png', 'webp', 'heic']) && (!empty($extra['return_thumbnail_url']))) {
            $preview = $cached_node->get_entry()->get_thumbnail_large();
        }

        return apply_filters('useyourdrive_set_embed_url', $preview, $cached_node);
    }

    public function create_link(?CacheNode $cached_node = null, $editable = false)
    {
        $error = false;

        if (null === $cached_node) {
            // Check if file is cached and still valid
            $cached = Cache::instance()->is_cached(Processor::instance()->get_requested_entry());

            // Get the file if not cached
            if (false === $cached) {
                $cached_node = $this->get_entry(Processor::instance()->get_requested_entry());
            } else {
                $cached_node = $cached;
            }
        }

        $viewlink = false;
        $embedlink = false;

        if (null !== $cached_node && false !== $cached_node) {
            $entry = $cached_node->get_entry();
            $embedurl = $this->get_embed_url($cached_node);

            // Build Direct link
            $viewurl = str_replace('edit?usp=drivesdk', 'view?', $embedurl);
            $viewurl = str_replace('preview?rm=minimal', 'view?', $viewurl);
            $viewurl = str_replace('preview', 'view', $viewurl);

            // Convert to Edit link if possible
            if ($editable && ($editurl = API::create_edit_url($cached_node->get_id())) !== false) {
                $embedurl = $editurl;
            }

            $type = 'iframe';
            // For images, just return the actual file
            if (in_array($cached_node->get_entry()->get_extension(), ['jpg', 'jpeg', 'gif', 'png', 'webp', 'heic'])) {
                $type = 'image';
                $viewurl = 'https://docs.google.com/file/d/'.$cached_node->get_entry()->get_id().'/view';
                $embedurl = USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-embed-image&account_id={$cached_node->get_account_id()}&id=".$cached_node->get_id();
            }

            if (!empty($embedurl)) {
                $embedlink = API::shorten_url($embedurl, null, ['name' => $entry->get_name()]);
                $viewlink = API::shorten_url($viewurl, null, ['name' => $entry->get_name()]);
            } else {
                $error = esc_html__("Can't create link", 'wpcloudplugins');
            }
        }

        $resultdata = [
            'id' => $entry->get_id(),
            'name' => $entry->get_name(),
            'link' => $viewlink,
            'embeddedlink' => $embedlink,
            'type' => $type,
            'size' => Helpers::bytes_to_size_1024($entry->get_size()),
            'error' => $error,
            'resourcekey' => false,
        ];

        if ($entry->has_resourcekey()) {
            $resultdata['resourcekey'] = $entry->get_resourcekey();
        }

        do_action('useyourdrive_created_link', $cached_node);

        do_action('useyourdrive_log_event', 'useyourdrive_created_link_to_entry', $cached_node, ['url' => $viewlink]);

        return $resultdata;
    }

    public function create_links($editable = false)
    {
        $links = ['links' => []];

        foreach ($_REQUEST['entries'] as $id) {
            $entry_id = sanitize_text_field($id);
            $cached = Cache::instance()->is_cached($entry_id);

            // Get the file if not cached or doesn't have permissions yet
            if (false === $cached) {
                $cached_node = $this->get_entry($entry_id);
            } else {
                $cached_node = $cached;
            }

            $links['links'][] = $this->create_link($cached_node, $editable);
        }

        return $links;
    }

    /**
     * Restore sharing permissions for the latest shared items.
     */
    public static function restore_sharing_permissions()
    {
        if ('Yes' !== Settings::get('cloud_security_restore_permissions', 'No')) {
            return;
        }

        $entries = Events::instance()->get_latest_shared_items();
        $accounts = [];

        foreach ($entries as $entry) {
            $accounts[$entry['account_id']][] = $entry['entry_id'];
        }

        $api_params = [
            'supportsAllDrives' => true,
        ];

        // Sort the accounts by ID to prevent switching accounts too often
        ksort($accounts);

        // Loop through the accounts and remove the permissions
        foreach ($accounts as $account_id => $account_entries) {
            API::set_account_by_id($account_id);

            App::instance()->get_sdk_client()->setUseBatch(true);
            $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

            foreach ($account_entries as $entry_id) {
                $batch->add(App::instance()->get_drive()->permissions->delete($entry_id, 'anyoneWithLink', $api_params), $entry_id);
            }

            try {
                $batch->execute();
            } catch (\Exception $ex) {
                Helpers::log_error('Failed to restore sharing permissions.', 'Client', ['items' => $accounts], __LINE__, $ex);

                return;
            }

            App::instance()->get_sdk_client()->setUseBatch(false);
        }
    }

    public function _get_files_recursive(CacheNode $cached_node, $currentpath = '', &$dirlisting = ['folders' => [], 'files' => [], 'bytes' => 0, 'bytes_total' => 0])
    {
        return $this->get_files_recursive($cached_node, $currentpath, $dirlisting);
    }

    public function get_files_recursive(CacheNode $cached_node, $currentpath = '', &$dirlisting = ['folders' => [], 'files' => [], 'bytes' => 0, 'bytes_total' => 0])
    {
        // Get entry meta data
        if (empty($cached_node) || null === $cached_node->has_entry()) {
            return $dirlisting;
        }

        // Check if entry is allowed
        if (Processor::instance()->is_filtering_entries() && !Processor::instance()->_is_entry_authorized($cached_node)) {
            return $dirlisting;
        }

        // Use the orginial entry if the file/folder is a shortcut
        if ($cached_node->is_shortcut()) {
            $original_node = $cached_node->get_original_node();

            if (!empty($original_node)) {
                $cached_node = $original_node;
            }
        }

        if ($cached_node->is_dir()) {
            $folder_path = $currentpath.$cached_node->get_name().'/';

            $dirlisting['folders'][] = $folder_path;
            $cached_folder = $this->get_folder($cached_node->get_id());

            if (!empty($cached_folder) && !empty($cached_folder['folder'])) {
                foreach ($cached_folder['folder']->get_children() as $cached_child) {
                    $dirlisting = $this->get_files_recursive($cached_child, $folder_path, $dirlisting);
                }
            }
        } else {
            $entry = $cached_node->get_entry();

            $entry_path = $currentpath.$cached_node->get_name();
            if (null === $entry->get_direct_download_link()) {
                $formats = $entry->get_save_as();
                $format = reset($formats);
                $downloadlink = 'https://www.googleapis.com/drive/v3/files/'.$entry->get_id().'/export?mimeType='.urlencode($format['mimetype']).'&alt=media';
                $entry_path .= '.'.$format['extension'];
            } else {
                $downloadlink = 'https://www.googleapis.com/drive/v3/files/'.$entry->get_id().'?alt=media';
            }

            $dirlisting['files'][] = ['ID' => $entry->get_id(), 'path' => $entry_path, 'url' => $downloadlink, 'bytes' => $entry->get_size()];
            $dirlisting['bytes_total'] += $entry->get_size();
        }

        return $dirlisting;
    }

    /**
     * Get the latest change token.
     *
     * @param string $drive_id drive ID to get the change token for
     *
     * @return UYDGoogle_Service_Drive_StartPageToken
     */
    public function get_changes_starttoken($drive_id)
    {
        $params = [
            'supportsAllDrives' => true,
            'driveId' => ('mydrive' === $drive_id) ? null : $drive_id,
        ];

        try {
            $result = App::instance()->get_drive()->changes->getStartPageToken($params);

            return $result->getStartPageToken();
        } catch (\Exception $ex) {
            Helpers::log_error('Client is receiving API error.', 'API', null, __LINE__, $ex);

            return false;
        }
    }

    /**
     * Get the changes on the cloud account since a certain moment in time.
     *
     * @param string $drive_id     drive ID to get the changes from
     * @param string $change_token Change cursor
     * @param array  $params
     *
     * @return array Returns an array ['new_change_token' => '', 'changes' => []]
     */
    public function get_changes($drive_id, $change_token = false, $params = [])
    {
        // Load the root folder when needed
        API::get_root_folder();

        do_action('useyourdrive_api_before_get_changes', $change_token, $params);

        $list_of_update_entries = [];

        if (empty($change_token)) {
            return ['new_change_token' => $this->get_changes_starttoken($drive_id), 'changes' => []];
        }

        $default_params = [
            'fields' => $this->apilistchangesfields,
            'pageSize' => 999,
            'restrictToMyDrive' => false,
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
            'spaces' => App::get_current_account()->has_app_folder_access() ? 'appDataFolder' : 'drive',
            'driveId' => ('mydrive' === $drive_id) ? null : $drive_id,
        ];

        $params = array_merge($default_params, $params);

        $changes = [];

        try {
            $result = App::instance()->get_drive()->changes->listChanges($change_token, $params);
            $change_token = $result->getNextPageToken();

            if (null != $result->getNewStartPageToken()) {
                // Last page, save this token for the next polling interval
                $new_change_token = $result->getNewStartPageToken();
            }

            $changes = array_merge($changes, $result->getChanges());
        } catch (\Exception $ex) {
            Cache::instance()->reset_cache();
            Helpers::log_error('Failed to receive changes', 'Client', null, __LINE__, $ex);

            return ['new_change_token' => false, 'changes' => []];
        }

        $list_of_update_entries = [];
        foreach ($changes as $change) {
            if ('drive' === $change->getChangeType()) {
                // Changes to the Shared Drives aren't processed
                continue;
            }

            if (true === $change->getRemoved()) {
                // File is removed
                $list_of_update_entries[$change->getFileId()] = 'deleted';
            } elseif ($change->getFile()->getTrashed()) {
                // File is trashed
                $list_of_update_entries[$change->getFileId()] = 'deleted';
            } else {
                // File is updated
                $list_of_update_entries[$change->getFileId()] = new Entry($change->getFile());
            }
        }

        do_action('useyourdrive_api_after_get_changes', $list_of_update_entries);

        return ['new_change_token' => $new_change_token, 'changes' => $list_of_update_entries];
    }
}
