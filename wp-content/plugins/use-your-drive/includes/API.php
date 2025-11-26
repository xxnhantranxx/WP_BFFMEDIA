<?php

/*
 * API Class.
 *
 * Use the API to execute calls directly for the set cloud account.
 * You can use the API using WPCP_GDRIVE_API::get_entry(...)
 *
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

defined('ABSPATH') || exit; // Exit if accessed directly.

if (!function_exists('useyourdrive_api_php_client_autoload')) {
    require_once USEYOURDRIVE_ROOTDIR.'/vendors/Google-sdk/src/Google/autoload.php';
}

class API
{
    public static $apifilefields = 'capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey';
    public static $apilistfilesfields = 'files(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),nextPageToken';

    /**
     * Set which cloud account should be used.
     *
     * @return Account|false - Account
     */
    public static function set_account_by_id(string $account_id)
    {
        $account = Accounts::instance()->get_account_by_id($account_id);
        if (null === $account) {
            Helpers::log_error('Cannot use the requested account as it is not linked with the plugin', 'Account', ['account_id' => $account_id], __LINE__);

            return false;
        }

        return App::set_current_account($account);
    }

    /**
     * @param string $id ID of the entry that should be loaded
     *
     * @return API_Exception|CacheNode
     */
    public static function get_entry($id)
    {
        // Load the root folder when needed
        self::get_root_folder();

        // Get entry from cache
        $cached_node = Cache::instance()->is_cached($id);

        if (!empty($cached_node)) {
            return $cached_node;
        }

        do_action('useyourdrive_api_before_get_entry', $id);

        try {
            $api_entry = App::instance()->get_drive()->files->get($id, ['supportsAllDrives' => true, 'fields' => self::$apifilefields]);
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            throw new API_Exception(esc_html__('Failed to load file.', 'wpcloudplugins'));
        }

        $entry = new Entry($api_entry);

        if (false === $entry->is_dir()) {
            $cached_node = Cache::instance()->add_to_cache($entry);
        } else {
            $cached_node = self::get_folder($id);
        }

        do_action('useyourdrive_api_after_get_entry', $cached_node);

        return $cached_node;
    }

    /**
     * Get folder information. Metadata of direct child files are loaded as well.
     *
     * @param string $id ID of the folder that should be loaded
     *
     * @return API_Exception|CacheNode
     */
    public static function get_folder($id)
    {
        // Load the root folder when needed
        self::get_root_folder();

        if ('shared-drives' === $id) {
            $cached_node = self::get_shared_drives();
        } elseif ('shared-with-me' === $id) {
            $cached_node = self::get_shared_with_me();
        } elseif ('computers' === $id) {
            $cached_node = self::get_computers();
        } elseif ('own_data_folder' === $id) {
            $cached_node = self::get_own_data_folder();
        } elseif ('app_data_folder' === $id) {
            $cached_node = self::get_app_data_folder();
        } else {
            $cached_node = Cache::instance()->is_cached($id, 'id', false);
        }

        if (!empty($cached_node)) {
            return $cached_node;
        }

        do_action('useyourdrive_api_before_get_folder', $id);

        $list_params = ['q' => "'".$id."' in parents and trashed = false", 'fields' => self::$apilistfilesfields, 'pageSize' => 999, 'supportsAllDrives' => true, 'includeItemsFromAllDrives' => true];

        if (App::get_current_account()->has_app_folder_access()) {
            $list_params['spaces'] = 'appDataFolder';
        }

        App::instance()->get_sdk_client()->setUseBatch(true);
        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

        $batch->add(App::instance()->get_drive()->files->get($id, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]), 'folder');
        $batch->add(App::instance()->get_drive()->files->listFiles($list_params), 'foldercontents');

        try {
            if (defined('GOOGLE_API_BATCH')) {
                usleep(50000);
            } else {
                define('GOOGLE_API_BATCH', true);
            }
            $results = $batch->execute();
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        App::instance()->get_sdk_client()->setUseBatch(false);
        $folder = $results['response-folder'];

        if ($folder instanceof \Exception) {
            Helpers::log_error('', 'API', null, __LINE__, $folder);

            return false;
        }

        if (empty($results['response-folder'])) {
            Helpers::log_error('Unexpectedly received empty response requesting folder.', 'API', ['entry_id' => $id], __LINE__);

            return false;
        }

        if ($results['response-foldercontents'] instanceof \Exception) {
            Helpers::log_error('Unexpectedly received empty response requesting folder.', 'API', null, __LINE__, $results['response-foldercontents']);

            return false;
        }

        if (empty($results['response-foldercontents'])) {
            Helpers::log_error('Unexpectedly received empty response requesting contents for folder', 'API', ['entry_id' => $id], __LINE__);

            return false;
        }

        $files_in_folder = $results['response-foldercontents']->getFiles();
        $nextpagetoken = (null !== $results['response-foldercontents']->getNextPageToken()) ? $results['response-foldercontents']->getNextPageToken() : false;
        $max_files_per_folder = apply_filters('useyourdrive_api_max_entries_per_folder', 2 * 999, $id, $folder);

        // Get all files in folder
        while ($nextpagetoken && (count($files_in_folder) < $max_files_per_folder)) {
            try {
                $list_params['pageToken'] = $nextpagetoken;
                $more_files = App::instance()->get_drive()->files->listFiles($list_params);
                $files_in_folder = array_merge($files_in_folder, $more_files->getFiles());
                $nextpagetoken = (null !== $more_files->getNextPageToken()) ? $more_files->getNextPageToken() : false;
            } catch (\Exception $ex) {
                Helpers::log_error('', 'API', null, __LINE__, $ex);

                return false;
            }
        }

        // Convert the items to Framework Entry
        $virtual_folder = false;
        if ($folder->getId() === $folder->getDriveId()) {
            // Folder is a Shared Drive
            $virtual_folder = 'shared-drive';
            $folder->setParents(['shared-drives']);
        } elseif ($cached_my_drive = Cache::instance()->get_node_by_id($folder->getId())) {
            if ($cached_my_drive->has_entry() && in_array($cached_my_drive->get_entry()->get_virtual_folder(), ['mydrive', 'own_data_folder'])) {
                // Folder is a virtual folder
                $virtual_folder = $cached_my_drive->get_entry()->get_virtual_folder();
                $folder->setParents(['drive']);
            }
        }

        $folder_entry = new Entry($folder, $virtual_folder);

        // BUG FIX normal API returning different name for Shared Drive Name
        if ($cached_team_drive = Cache::instance()->get_node_by_id($folder_entry->get_id())) {
            if ($cached_team_drive->has_entry() && 'shared-drive' === $cached_team_drive->get_entry()->get_virtual_folder()) {
                $folder_entry->set_name($cached_team_drive->get_name());
            }
        }
        // END BUG FIX

        $folder_items = [];
        foreach ($files_in_folder as $entry) {
            $folder_items[] = new Entry($entry);
        }

        $cached_node = Cache::instance()->add_to_cache($folder_entry);
        $cached_node->set_loaded_children(true);

        // Add all files in folder to cache
        foreach ($folder_items as $item) {
            Cache::instance()->add_to_cache($item);
        }

        Cache::instance()->update_cache();

        do_action('useyourdrive_api_after_get_folder', $cached_node);

        return $cached_node;
    }

    /**
     * Get root folder information. Metadata of direct child files are loaded as well.
     *
     * @return API_Exception|CacheNode
     */
    public static function get_root_folder()
    {
        $root_node = Cache::instance()->get_root_node();

        if (false !== $root_node && null !== $root_node->get_entry()) {
            return $root_node;
        }

        // Top Google Drive Folder
        $root_api = new \UYDGoogle_Service_Drive_DriveFile();
        $root_api->setId('drive');
        $root_api->setDriveId('drive');
        $root_api->setName('Google (Virtual Folder)');
        $root_api->setMimeType('application/vnd.google-apps.folder');
        $root_entry = new Entry($root_api, 'drive');
        $cached_root_node = Cache::instance()->add_to_cache($root_entry);
        $cached_root_node->set_root();
        $cached_root_node->set_loaded_children(true);
        $cached_root_node->set_virtual_folder('drive');

        $cached_root_node->set_updated();
        Cache::instance()->set_root_node_id('drive');

        if (App::get_current_account()->has_drive_access()) {
            self::get_computers();
            self::get_shared_drives();
            self::get_shared_with_me('init');
            self::get_my_drive();
        }

        if (App::get_current_account()->has_own_app_folder_access()) {
            self::get_own_data_folder();
        }

        if (App::get_current_account()->has_app_folder_access()) {
            self::get_app_data_folder();
        }

        Cache::instance()->set_updated();
        Cache::instance()->update_cache();

        return Cache::instance()->get_root_node();
    }

    /**
     * Get the main folder for the account, that is the My Drive folder, own application folder or app folder.
     *
     * @return CacheNode
     */
    public static function get_main_folder()
    {
        if (App::get_current_account()->has_own_app_folder_access()) {
            return self::get_own_data_folder();
        }

        if (App::get_current_account()->has_app_folder_access()) {
            return self::get_app_data_folder();
        }

        return self::get_my_drive();
    }

    /**
     * Get own App Folder information. Metadata of direct child files are loaded as well.
     *
     * @return API_Exception|CacheNode
     */
    public static function get_own_data_folder()
    {
        $cached_root = self::get_root_folder();

        foreach ($cached_root->get_children() as $cached_child) {
            if ('own_data_folder' === $cached_child->get_virtual_folder()) {
                return self::get_folder($cached_child->get_id(), false);
            }
        }

        // Try to Find APP folder first
        $params = [
            'q' => "appProperties has { key='WPCloudPluginApp' and value='true' } and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields' => Client::instance()->apilistfilesfields,
            'pageSize' => 1,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
            'corpora' => 'allDrives',
        ];

        try {
            $search_response = App::instance()->get_drive()->files->listFiles($params);
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        // Process the response
        $result = $search_response->getFiles();

        // If App Folder is found, use that. Otherwise, create a new folder
        if (!empty($result)) {
            $own_app_folder_api = reset($result);
        } else {
            // Create new Google File
            $own_app_drivefile = new \UYDGoogle_Service_Drive_DriveFile();
            $own_app_drivefile->setName('WP Cloud Plugins - Website Folder (Use-your-Drive)');
            $own_app_drivefile->setMimeType('application/vnd.google-apps.folder');
            $own_app_drivefile->setDescription("Folder accessible by the WP Cloud Plugins - Use-your-Drive | Google Drive plugin for WordPress. \n\nIMPORTANT: Only content added via the plugin will be accessible by the plugin. If you want the plugin to have full access to your Drive, relink your Google Account with the required permissions. \n\nYou can move this folder to another location or rename it. \n\nCreated via the WordPress site: ".get_home_url());
            $own_app_drivefile->setAppProperties(['WPCloudPluginApp' => true]);
            $own_app_drivefile->setFolderColorRgb('#f83a22');

            try {
                $own_app_folder_api = App::instance()->get_drive()->files->create($own_app_drivefile, ['supportsAllDrives' => true]);
            } catch (\Exception $ex) {
                Helpers::log_error('', 'API', null, __LINE__, $ex);

                return false;
            }
        }
        $own_app_folder_api->setParents(['drive']);
        $own_app_folder_entry = new Entry($own_app_folder_api, 'own_data_folder');
        $cached_own_app_folder_node = Cache::instance()->add_to_cache($own_app_folder_entry);
        $cached_own_app_folder_node->set_virtual_folder('own_data_folder');
        $cached_own_app_folder_node->set_updated();
        Cache::instance()->set_updated();

        return self::get_folder($cached_own_app_folder_node->get_id());
    }

    /**
     * Get App Data folder information. Metadata of direct child files are loaded as well.
     *
     * @return API_Exception|CacheNode
     */
    public static function get_app_data_folder()
    {
        $cached_root = self::get_root_folder();

        foreach ($cached_root->get_children() as $cached_child) {
            if ('app_data_folder' === $cached_child->get_virtual_folder()) {
                return self::get_folder($cached_child->get_id(), false);
            }
        }

        try {
            $app_data_folder_api = App::instance()->get_drive()->files->get('appDataFolder', ['fields' => self::$apifilefields]);
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        $app_data_folder_api->setParents(['drive']);
        $data_folder_entry = new Entry($app_data_folder_api, 'app_data_folder');
        $cached_app_data_folder_node = Cache::instance()->add_to_cache($data_folder_entry);
        $cached_app_data_folder_node->set_virtual_folder('app_data_folder');
        $cached_app_data_folder_node->set_updated();
        Cache::instance()->set_updated();

        return self::get_folder($cached_app_data_folder_node->get_id());
    }

    /**
     * Get My Drive folder information. Metadata of direct child files are loaded as well.
     *
     * @return API_Exception|CacheNode
     */
    public static function get_my_drive()
    {
        $cached_root = self::get_root_folder();

        foreach ($cached_root->get_children() as $cached_child) {
            if ('mydrive' === $cached_child->get_virtual_folder()) {
                return self::get_folder($cached_child->get_id(), false);
            }
        }

        try {
            $mydrive_api = App::instance()->get_drive()->files->get('root', ['fields' => self::$apifilefields]);
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        $mydrive_api->setDriveId('mydrive');
        $mydrive_api->setParents(['drive']);
        $mydrive_entry = new Entry($mydrive_api, 'mydrive');
        $cached_mydrive_node = Cache::instance()->add_to_cache($mydrive_entry);
        $cached_mydrive_node->set_virtual_folder('mydrive');
        $cached_mydrive_node->set_updated();
        Cache::instance()->set_updated();

        return self::get_folder($cached_mydrive_node->get_id());
    }

    /**
     * Get Team/Shared Drives information. Metadata of direct child files are loaded as well.
     *
     * @return API_Exception|CacheNode
     */
    public static function get_shared_drives()
    {
        $cached_shared_drives_node = Cache::instance()->is_cached('shared-drives', 'id', false);

        if (false !== $cached_shared_drives_node) {
            return $cached_shared_drives_node;
        }

        // Shared Drives (Google Workspaces)
        $shared_drives_api = new \UYDGoogle_Service_Drive_DriveFile();
        $shared_drives_api->setId('shared-drives');
        $shared_drives_api->setName(esc_html__('Shared Drives', 'wpcloudplugins'));
        $shared_drives_api->setMimeType('application/vnd.google-apps.folder');
        $shared_drives_api->setParents(['drive']);
        $shared_drives_entry = new Entry($shared_drives_api, 'shared-drives');
        $cached_shared_drives_node = Cache::instance()->add_to_cache($shared_drives_entry);
        $cached_shared_drives_node->set_virtual_folder('shared-drives');
        $cached_shared_drives_node->set_updated();

        $shared_drives = [];
        $params = [
            'fields' => 'kind,nextPageToken,drives(kind,id,name,capabilities,backgroundImageFile,backgroundImageLink)',
            'pageSize' => 50,
        ];

        $nextpagetoken = null;
        // Get all files in folder
        while ($nextpagetoken || null === $nextpagetoken) {
            try {
                if (null !== $nextpagetoken) {
                    $params['pageToken'] = $nextpagetoken;
                }

                $more_drives = App::instance()->get_drive()->drives->listDrives($params);
                $shared_drives = array_merge($shared_drives, $more_drives->getDrives());
                $nextpagetoken = (null !== $more_drives->getNextPageToken()) ? $more_drives->getNextPageToken() : false;
            } catch (\Exception $ex) {
                Helpers::log_error('', 'API', null, __LINE__, $ex);

                $cached_shared_drives_node->set_loaded_children(true);
                $cached_shared_drives_node->set_updated();

                return $cached_shared_drives_node;
            }
        }

        foreach ($shared_drives as $drive) {
            $drive_item = new EntryDrive($drive, 'shared-drive');
            $drive_item->set_parent_id('shared-drives');
            $cached_drive_node = Cache::instance()->add_to_cache($drive_item);
            $cached_drive_node->set_virtual_folder('shared-drive');
        }

        Cache::instance()->set_updated();

        return $cached_shared_drives_node;
    }

    /**
     * Get Computers folder information. Metadata of direct child files are loaded as well.
     *
     * Computers are not yet fully supported by the API
     *
     * @return API_Exception|CacheNode
     */
    public static function get_computers()
    {
        $cached_computers_node = Cache::instance()->get_node_by_id('computers');

        if (false !== $cached_computers_node) {
            return $cached_computers_node;
        }

        $computers_api = new \UYDGoogle_Service_Drive_DriveFile();
        $computers_api->setId('computers');
        $computers_api->setName(esc_html__('Computers', 'wpcloudplugins').' ('.esc_html__('Limited Support!', 'wpcloudplugins').')');
        $computers_api->setMimeType('application/vnd.google-apps.folder');
        $computers_api->setParents(['drive']);
        $computers_entry = new Entry($computers_api, 'computers');
        $cached_computers_node = Cache::instance()->add_to_cache($computers_entry);
        $cached_computers_node->set_virtual_folder('computers');
        $cached_computers_node->set_loaded_children(true); // Can't yet read data in Computers via API
        $cached_computers_node->set_updated();
        Cache::instance()->set_updated();

        return $cached_computers_node;
    }

    /**
     * Get Shared with Me files/folders information. Metadata of direct child files are loaded as well.
     *
     * @param bool $init Only load the folder node itself. Can prevent performance issues with very large Shared with Me folders
     *
     * @return API_Exception|CacheNode
     */
    public static function get_shared_with_me($init = false)
    {
        $cached_shared_with_me_node = Cache::instance()->is_cached('shared-with-me', 'id', false);

        if (false !== $cached_shared_with_me_node) {
            return $cached_shared_with_me_node;
        }
        $shared_api = new \UYDGoogle_Service_Drive_DriveFile();
        $shared_api->setId('shared-with-me');
        $shared_api->setName(esc_html__('Shared with me', 'wpcloudplugins'));
        $shared_api->setMimeType('application/vnd.google-apps.folder');
        $shared_api->setParents(['drive']);
        $shared_entry = new Entry($shared_api, 'shared-with-me');
        $cached_shared_with_me_node = Cache::instance()->add_to_cache($shared_entry);
        $cached_shared_with_me_node->set_virtual_folder('shared-with-me');

        if ($init) {
            // Only load the folder node itself on init
            // To prevent performance issues with very large Shared with Me folders
            return ['folder' => $cached_shared_with_me_node, 'contents' => $cached_shared_with_me_node->get_children()];
        }

        $params = ['q' => 'sharedWithMe = true and trashed = false', 'fields' => self::$apilistfilesfields, 'pageSize' => 999, 'supportsAllDrives' => true, 'includeItemsFromAllDrives' => true];

        $shared_entries = [];
        $nextpagetoken = null;

        while ($nextpagetoken || null === $nextpagetoken) {
            try {
                if (null !== $nextpagetoken) {
                    $params['pageToken'] = $nextpagetoken;
                }

                $more_shared_entries = App::instance()->get_drive()->files->listFiles($params);
                $shared_entries = array_merge($shared_entries, $more_shared_entries->getFiles());
                $nextpagetoken = (null !== $more_shared_entries->getNextPageToken()) ? $more_shared_entries->getNextPageToken() : false;
            } catch (\Exception $ex) {
                Helpers::log_error('', 'API', null, __LINE__, $ex);

                $cached_shared_with_me_node->set_loaded_children(true);
                $cached_shared_with_me_node->set_updated();

                return $cached_shared_with_me_node;
            }
        }

        foreach ($shared_entries as $api_entry) {
            if (empty($api_entry->getParents())) {
                // Add root parent for shared files which are located in the root 'Shared with me' virtual folder
                $api_entry->setParents('shared-with-me');
            }
            // Shared files are by definition not owned by the linked account
            $api_entry->setOwnedByMe(false);

            $entry = new Entry($api_entry);
            Cache::instance()->add_to_cache($entry);
        }

        $cached_shared_with_me_node->set_loaded_children(true);
        $cached_shared_with_me_node->set_updated();

        Cache::instance()->update_cache();

        return $cached_shared_with_me_node;
    }

    /**
     * Get (and create) sub folder by path.
     *
     * @param string $parent_folder_id
     * @param string $subfolder_path
     * @param bool   $create_if_not_exist
     *
     * @return bool|CacheNode
     */
    public static function get_sub_folder_by_path($parent_folder_id, $subfolder_path, $create_if_not_exist = false)
    {
        $cached_parent_folder = self::get_folder($parent_folder_id);

        if (empty($cached_parent_folder)) {
            return false;
        }

        if (empty($subfolder_path)) {
            return $cached_parent_folder;
        }

        $subfolders = array_filter(explode('/', $subfolder_path));
        $current_folder = array_shift($subfolders);

        // Try to load the subfolder at once
        $cached_sub_folder = self::search_for_name_in_folder($current_folder, $parent_folder_id);

        if (false === $cached_sub_folder && false === apply_filters('useyourdrive_api_create_subfolder_if_not_exist', $create_if_not_exist, $parent_folder_id, $subfolder_path)) {
            return false;
        }

        // If the subfolder can't be found, create the sub folder
        if (!$cached_sub_folder) {
            $_new_entry = new \UYDGoogle_Service_Drive_DriveFile();
            $_new_entry->setName($current_folder);
            $_new_entry->setMimeType('application/vnd.google-apps.folder');
            $_new_entry->setParents([$parent_folder_id]);

            try {
                $api_entry = App::instance()->get_drive()->files->create($_new_entry, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]);

                if (null !== $api_entry) {
                    // Add new file to our Cache
                    $newentry = new Entry($api_entry);
                    $cached_sub_folder = Cache::instance()->add_to_cache($newentry);

                    do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $cached_sub_folder);
                }
            } catch (\Exception $ex) {
                Helpers::log_error('', 'API', null, __LINE__, $ex);

                return false;
            }
        }

        return self::get_sub_folder_by_path($cached_sub_folder->get_id(), implode('/', $subfolders), $create_if_not_exist);
    }

    /**
     * Find entry with specific name in specific parent folder.
     *
     * @param string $entry_name
     * @param string $parent_folder_id
     * @param string $mimetype         (default = 'application/vnd.google-apps.folder')
     *
     * @return bool|CacheNode
     */
    public static function search_for_name_in_folder($entry_name, $parent_folder_id, $mimetype = 'application/vnd.google-apps.folder')
    {
        // First check if the entry with name can be found in cache
        $cached_node = Cache::instance()->get_node_by_name($entry_name, $parent_folder_id);

        // If not present in cache, search Drive for the name in the parent folder
        $cached_parent_folder = self::get_folder($parent_folder_id);

        if (empty($cached_parent_folder)) {
            return false; // if parent folder is not found
        }

        // Check if the child is already present in cache
        $cached_node = Cache::instance()->get_node_by_name($entry_name, $parent_folder_id);
        if (!empty($cached_node)) {
            return $cached_node;
        }

        // Otherwise query the API
        $drive_id = $cached_parent_folder->get_drive_id();

        $params = [
            'fields' => Client::instance()->apilistfilesfields,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => (!in_array($drive_id, ['mydrive', null])),
            'corpora' => (in_array($drive_id, ['mydrive', null])) ? 'user' : 'drive',
            'orderBy' => 'createdTime',
        ];

        if (!in_array($drive_id, ['mydrive', null])) {
            $params['driveId'] = $drive_id;
        }

        // Find entry with $entry_name in parent folder
        $params['q'] = "name = '{$entry_name}' and '{$parent_folder_id}' in parents and trashed = false".(!empty($mimetype) ? " and mimeType = '{$mimetype}'" : '');

        try {
            $search_response = App::instance()->get_drive()->files->listFiles($params);
            $api_results = $search_response->getFiles();

            foreach ($api_results as $api_entries) {
                $api_entry = new Entry($api_entries);

                $cached_node = Cache::instance()->add_to_cache($api_entry);
                $cached_node->set_loaded_children(false);

                // Double check to make sure that the entry indeed has the exact same name as request in case the API search does strange things
                if ($api_entry->get_name() === $entry_name) {
                    return $cached_node;
                }
            }
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        // The API search hasn't found entry with requested name in parent folder
        return false;
    }

    /**
     * Create a folder in the Cloud Account.
     *
     * @param string $new_name  the name for the newly created entry
     * @param string $parent_id ID of the folder where the new entry should be created
     *
     * @return API_Exception|CacheNode
     */
    public static function create_folder($new_name, $parent_id)
    {
        return self::create_entry($new_name, $parent_id, 'application/vnd.google-apps.folder');
    }

    /**
     * Create an entry in the Cloud Account.
     *
     * @param string $new_name  the name for the newly created entry
     * @param string $parent_id ID of the folder where the new entry should be created
     * @param string $mimetype  Mimetype of the new file. Use  'application/vnd.google-apps.folder' for an folder.
     * @param array  $params
     *
     * @return API_Exception|CacheNode
     */
    public static function create_entry($new_name, $parent_id, $mimetype = 'application/vnd.google-apps.folder', $params = [])
    {
        $parent_id = apply_filters('useyourdrive_api_create_entry_set_parent_id', $parent_id);
        $mimetype = apply_filters('useyourdrive_api_create_entry_set_mimetype', $mimetype);
        $params = apply_filters('useyourdrive_api_create_entry_set_params', $params);

        $_new_entry = new \UYDGoogle_Service_Drive_DriveFile();
        $_new_entry->setName($new_name);
        $_new_entry->setMimeType($mimetype);
        $_new_entry->setParents([$parent_id]);

        do_action('useyourdrive_api_before_create_entry', $new_name, $parent_id, $params);

        try {
            $api_entry = App::instance()->get_drive()->files->create($_new_entry, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]);

            if (null === $api_entry) {
                throw new \Exception(esc_html__('Failed to create file.', 'wpcloudplugins'));
            }

            $newentry = new Entry($api_entry);
            $node = Cache::instance()->add_to_cache($newentry);

            do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $node);
        } catch (\Exception $ex) {
            Cache::instance()->reset_cache();
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            throw new API_Exception(esc_html__('Failed to create file.', 'wpcloudplugins'));
        }

        Cache::instance()->pull_for_changes(true);

        do_action('useyourdrive_api_after_create_entry', $node);

        return $node;
    }

    /**
     * Copy multiple files to a new location.
     *
     * @param array  $entry_ids ID of the files that should be moved / copied
     * @param string $target_id ID of the folder where the files should be moved/copied to
     * @param array  $params
     *
     * @return API_Exception|CacheNode
     */
    public static function copy($entry_ids, $target_id, $params = [])
    {
        $entry_ids = apply_filters('useyourdrive_api_move_set_entry_ids', $entry_ids);
        $target_id = apply_filters('useyourdrive_api_move_set_target_id', $target_id);
        $params = apply_filters('useyourdrive_api_copy_set_params', $params);

        do_action('useyourdrive_api_before_copy', $entry_ids, $target_id, $params);

        $copied_entries = self::move($entry_ids, $target_id, true, $params);

        do_action('useyourdrive_api_after_copy', $copied_entries);

        return $copied_entries;
    }

    /**
     * Move an entry to a new location.
     *
     * @param array  $entry_ids ID of the files that should be moved / copied
     * @param string $target_id ID of the folder where the files should be moved/copied to
     * @param bool   $copy      Move or copy the entries. Default: copy = false
     * @param array  $params
     *
     * @return API_Exception|CacheNode
     */
    public static function move($entry_ids, $target_id, $copy = false, $params = [])
    {
        $entry_ids = apply_filters('useyourdrive_api_move_set_entry_ids', $entry_ids);
        $target_id = apply_filters('useyourdrive_api_move_set_target_id', $target_id);
        $copy = apply_filters('useyourdrive_api_move_set_copy', $copy);
        $params = apply_filters('useyourdrive_api_move_set_params', $params);

        do_action('useyourdrive_api_before_move', $entry_ids, $target_id, $copy, $params);

        $entries_to_move = [];
        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

        foreach ($entry_ids as $entry_id) {
            App::instance()->get_sdk_client()->setUseBatch(false);
            $cached_entry = self::get_entry($entry_id);
            App::instance()->get_sdk_client()->setUseBatch(true);

            $entries_to_move[$cached_entry->get_id()] = false; // Set after Batch Request $cached_entry;

            if ($copy) {
                if (false === $cached_entry->is_dir()) {
                    $patch_entry = new \UYDGoogle_Service_Drive_DriveFile();

                    $params = [
                        'fields' => self::$apifilefields,
                        'supportsAllDrives' => true,
                    ];

                    if ($target_id !== $cached_entry->get_parent()->get_id()) {
                        $patch_entry->setParents([$target_id]);
                    }

                    $batch->add(App::instance()->get_drive()->files->copy($entry_id, $patch_entry, $params), $entry_id);
                }

                if ($cached_entry->is_dir()) {
                    $new_entry = new \UYDGoogle_Service_Drive_DriveFile();
                    $new_entry->setName($cached_entry->get_name());
                    $new_entry->setMimeType('application/vnd.google-apps.folder');
                    if ($target_id !== $cached_entry->get_parent()->get_id()) {
                        $new_entry->setParents([$target_id]);
                    }

                    try {
                        App::instance()->get_sdk_client()->setUseBatch(false);

                        $api_entry = App::instance()->get_drive()->files->create($new_entry, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]);

                        if (null !== $api_entry) {
                            // Add new file to our Cache
                            $new_folder_node = Cache::instance()->add_to_cache(new Entry($api_entry));
                            self::copy_folder_recursive($cached_entry->get_id(), $new_folder_node->get_id());
                        }
                        App::instance()->get_sdk_client()->setUseBatch(true);
                    } catch (\Exception $ex) {
                        App::instance()->get_sdk_client()->setUseBatch(true);

                        Helpers::log_error('Failed to copy folder', 'API', ['entry_id' => $cached_entry->get_id()], __LINE__, $ex);
                        $entries_to_move[$cached_entry->get_id()] = false;

                        continue;
                    }

                    $entries_to_move[$cached_entry->get_id()] = $new_folder_node;
                }
            }

            if (false === $copy) {
                // Create an the entry for Patch
                $patch_entry = new \UYDGoogle_Service_Drive_DriveFile();

                // Add the new Parent to the Entry
                $params = [
                    'fields' => self::$apifilefields,
                    'supportsAllDrives' => true,
                    'addParents' => $target_id,
                    'removeParents' => $cached_entry->get_parent()->get_id(),
                ];

                $batch->add(App::instance()->get_drive()->files->update($entry_id, $patch_entry, $params), $entry_id);
            }
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
                $updated_entry = new Entry($api_entry);

                if (!$copy) {
                    Cache::instance()->remove_from_cache($updated_entry->get_id(), 'moved');
                }

                $cached_updated_entry = Cache::instance()->add_to_cache($updated_entry);
                $entries_to_move[$cached_updated_entry->get_id()] = $cached_updated_entry;

                do_action('useyourdrive_log_event', 'useyourdrive_moved_entry', $cached_updated_entry);
            }
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return $entries_to_move;
        }

        Cache::instance()->update_cache();

        // Clear Cached Requests
        CacheRequest::clear_local_cache_for_shortcode(App::get_current_account()->get_id(), Processor::instance()->get_listtoken());

        do_action('useyourdrive_api_after_move', $entries_to_move);

        return $entries_to_move;
    }

    /**
     * Move the contents of an folder into another folder.
     *
     * @param string $folder_id ID of the folder containing the files that should be moved
     * @param string $target_id ID of the folder where the files should be moved to
     *
     * @return API_Exception|bool
     */
    public static function move_folder_content($folder_id, $target_id)
    {
        $folder_id = apply_filters('useyourdrive_api_move_folder_content_set_folder_id', $folder_id);
        $target_id = apply_filters('useyourdrive_api_move_folder_content_set_target_id', $target_id);

        do_action('useyourdrive_api_before_move_folder_content', $folder_id, $target_id);

        $cached_folder = self::get_folder($folder_id);

        if (false === $cached_folder) {
            return false;
        }

        $cached_target = self::get_folder($target_id);

        if (false === $cached_target) {
            return false;
        }

        $entries_to_move = $cached_folder->get_children();

        $moved_entries = self::move(array_keys($entries_to_move), $target_id);

        do_action('useyourdrive_api_after_move_folder_content', $moved_entries);

        return $moved_entries;
    }

    /**
     * Copy the contents of an folder into another folder.
     *
     * @param string $folder_id ID of the folder containing the files that should be copied
     * @param string $target_id ID of the folder where the files should be copied to
     *
     * @return API_Exception|bool
     */
    public static function copy_folder_recursive($folder_id, $target_id)
    {
        $cached_folder_node = self::get_folder($folder_id);

        if (empty($cached_folder_node) || empty($target_id)) {
            return false;
        }

        if (false === $cached_folder_node->has_children()) {
            return false;
        }

        $cached_folder_children = $cached_folder_node->get_children();

        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());
        $batch_requests = 0;
        App::instance()->get_sdk_client()->setUseBatch(true);

        // Create folders & Copy files via Batch
        foreach ($cached_folder_children as $cached_child) {
            // SKIP if folder already exists
            $entry_exists = Cache::instance()->get_node_by_name($cached_child->get_name(), $target_id);
            if (false !== $entry_exists) {
                continue;
            }

            if ($cached_child->is_dir()) {
                // Create child folder in user folder
                $newchildfolder = new \UYDGoogle_Service_Drive_DriveFile();

                // Apply placeholders to folder name
                $folder_name = Placeholders::apply($cached_child->get_name(), Processor::instance());

                $newchildfolder->setName($folder_name);
                $newchildfolder->setMimeType('application/vnd.google-apps.folder');
                $newchildfolder->setParents([$target_id]);

                $batch->add(App::instance()->get_drive()->files->create($newchildfolder, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]), $cached_child->get_id());
            } else {
                // Copy file to new folder
                $newfile = new \UYDGoogle_Service_Drive_DriveFile();
                $newfile->setName($cached_child->get_name());
                $newfile->setParents([$target_id]);

                $batch->add(App::instance()->get_drive()->files->copy($cached_child->get_id(), $newfile, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]), $cached_child->get_id());
            }
            ++$batch_requests;
        }

        // Execute the Batch Call
        try {
            usleep(20000 * $batch_requests);
            Helpers::set_time_limit(30);

            $batch_result = $batch->execute();
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        App::instance()->get_sdk_client()->setUseBatch(false);

        // Process the result

        foreach ($batch_result as $key => $api_child_entry) {
            $new_child_entry = new Entry($api_child_entry);
            Cache::instance()->add_to_cache($new_child_entry);

            $original_id = str_replace('response-', '', $key);
            $folder_child_entry = $cached_folder_children[$original_id];
            $new_id = $api_child_entry->getId();

            if ($folder_child_entry->get_entry()->is_dir()) {
                // Copy contents of child folder to new create child user folder
                $cached_child_folder = self::get_folder($original_id);

                if (false === $cached_child_folder) {
                    Helpers::log_error('Wait 5 seconds before trying to load the folder again.', 'API', ['entry_id' => $original_id], __LINE__);
                    usleep(5000000);
                    $cached_child_folder = self::get_folder($original_id);
                }

                if (false === $cached_child_folder) {
                    Helpers::log_error('Template folder cannot be loaded. Skipping folder.', 'API', ['entry_id' => $original_id], __LINE__);

                    continue;
                }

                self::copy_folder_recursive($cached_child_folder->get_id(), $new_id);
            }
        }

        return true;
    }

    /**
     * Delete files by their IDs.
     *
     * @param array $entries array of IDs that need to be deleted
     * @param bool  $trash   Trash the content or delete it permanently. Default: trash = true.
     * @param array $params
     *
     * @return API_Exception|CacheNode
     */
    public static function delete($entries = [], $trash = true, $params = [])
    {
        do_action('useyourdrive_api_before_delete', $entries, $trash, $params);

        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

        $deleted_entries = [];

        foreach ($entries as $entry_id) {
            App::instance()->get_sdk_client()->setUseBatch(false);
            $deleted_entries[$entry_id] = self::get_entry($entry_id);
            App::instance()->get_sdk_client()->setUseBatch(true);

            if ($trash) {
                $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
                $updateentry->setTrashed(true);
                $batch->add(App::instance()->get_drive()->files->update($entry_id, $updateentry, ['supportsAllDrives' => true]), $entry_id);
            } else {
                $batch->add(App::instance()->get_drive()->files->delete($entry_id, ['supportsAllDrives' => true]), $entry_id);
            }
        }

        // Execute the Batch Call
        try {
            if (defined('GOOGLE_API_BATCH')) {
                usleep(50000);
            } else {
                define('GOOGLE_API_BATCH', true);
            }
            $batch_result = $batch->execute();
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return $deleted_entries;
        }

        App::instance()->get_sdk_client()->setUseBatch(false);

        // Process Batch Response
        foreach ($batch_result as $key => $api_entry) {
            $original_id = str_replace('response-', '', $key);
            do_action('useyourdrive_log_event', 'useyourdrive_deleted_entry', $deleted_entries[$original_id], ['to_trash' => $trash]);
            Cache::instance()->remove_from_cache($original_id, 'deleted');
        }

        // Remove items from cache
        Cache::instance()->pull_for_changes(true);

        // Clear Cached Requests
        CacheRequest::clear_request_cache();

        do_action('useyourdrive_api_after_delete', $deleted_entries, $params);

        return $deleted_entries;
    }

    /**
     * Get the account information.
     *
     * @return UYDGoogle_Service_Oauth2_Userinfoplus
     */
    public static function get_account_info()
    {
        $cache_key = 'useyourdrive_account_'.App::get_current_account()->get_id();
        if (empty($account_info = get_transient($cache_key, false))) {
            $account_info = App::instance()->get_user()->userinfo->get();
            \set_transient($cache_key, $account_info, HOUR_IN_SECONDS);
        }

        return $account_info;
    }

    /**
     * Get the information about the available space.
     *
     * @return UYDGoogle_Service_Drive_About
     */
    public static function get_space_info()
    {
        $cache_key = 'useyourdrive_account_'.App::get_current_account()->get_id().'_space';
        if (empty($space_info = get_transient($cache_key, false))) {
            $space_info = App::instance()->get_drive()->about->get(['fields' => 'importFormats,kind,storageQuota,user']);
            \set_transient($cache_key, $space_info, HOUR_IN_SECONDS);
        }

        return $space_info;
    }

    /**
     * Upload a file to the cloud using a simple file object.
     *
     * @param string      $upload_folder_id ID of the upload folder
     * @param null|string $description      Add a description to the file
     * @param bool        $overwrite        should we overwrite an existing file with the same name? If false, the file will be renamed
     * @param stdClass    $file             Object containing the file details. Same as file object in $_FILES.
     *                                      <code>
     *                                      $file = object {
     *                                      'name' : 'filename.ext',
     *                                      'type' : 'image/jpeg',
     *                                      'tmp_name' : '...\php8D2C.tmp',
     *                                      'size' : 1274994
     *                                      }
     *                                      </code>
     */
    public static function upload_file($file, $upload_folder_id, $description = null, $overwrite = false)
    {
        $upload_folder_id = apply_filters('useyourdrive_api_upload_set_upload_folder_id', $upload_folder_id);
        $file->name = apply_filters('useyourdrive_api_upload_set_file_name', $file->name);
        $file = apply_filters('useyourdrive_api_upload_set_file', $file);
        $description = apply_filters('useyourdrive_api_upload_set_description', $description);
        $overwrite = apply_filters('useyourdrive_api_upload_set_overwrite', $overwrite);

        do_action('useyourdrive_api_before_upload', $upload_folder_id, $file, $description, $overwrite);

        // If we need to overwrite a file, we first have to get the ID of that file.
        $current_entry_id = false;
        if ($overwrite) {
            $parent_folder = API::get_folder($upload_folder_id);
            $current_entry = Cache::instance()->get_node_by_name($file->name, $parent_folder);

            if (!empty($current_entry)) {
                $current_entry_id = $current_entry->get_id();
            }
        }

        // Create new Google File
        $googledrive_file = new \UYDGoogle_Service_Drive_DriveFile();
        $googledrive_file->setName($file->name);
        $googledrive_file->setMimeType($file->type);
        $googledrive_file->setDescription($description);

        // Do the actual upload
        $chunkSizeBytes = 50 * 1024 * 1024;

        App::instance()->get_sdk_client()->setDefer(true);

        try {
            if (false === $current_entry_id) {
                $googledrive_file->setParents([$upload_folder_id]);
                $request = App::instance()->get_drive()->files->create($googledrive_file, ['supportsAllDrives' => true]);
            } else {
                $request = App::instance()->get_drive()->files->update($current_entry_id, $googledrive_file, ['supportsAllDrives' => true]);
            }
        } catch (\Exception $ex) {
            Helpers::log_error('File not uploaded to the cloud.', 'API', ['file_name' => $file->name], __LINE__, $ex);

            return false;
        }

        // Create a media file upload to represent our upload process.
        $media = new \UYDGoogle_Http_MediaFileUpload(
            App::instance()->get_sdk_client(),
            $request,
            $file->type,
            null,
            true,
            $chunkSizeBytes
        );

        $media->setFileSize($file->size);

        try {
            $upload_status = false;
            $handle = fopen($file->tmp_path, 'rb');
            while (!$upload_status && !feof($handle)) {
                Helpers::set_time_limit(60);
                $chunk = fread($handle, $chunkSizeBytes);
                $upload_status = $media->nextChunk($chunk);
            }

            fclose($handle);
        } catch (\Exception $ex) {
            @fclose($handle);
            Helpers::log_error('File not uploaded to the cloud.', 'API', ['file_name' => $file->name], __LINE__, $ex);

            return false;
        }

        App::instance()->get_sdk_client()->setDefer(false);

        usleep(500000); // wait a 0.5 sec so Google can create a thumbnail.
        $node = self::get_entry($upload_status->getId());

        do_action('useyourdrive_log_event', 'useyourdrive_uploaded_entry', $node);

        do_action('useyourdrive_api_after_upload', $node);

        return $node;
    }

    /**
     * Get a shortened url via the requested service.
     *
     * @param string $url
     * @param string $service
     * @param array  $params  Add extra data that can be used for certain services, e.g. ['name' => $node->get_name()]
     *
     * @return API_Exception|string The shortened url
     */
    public static function shorten_url($url, $service = null, $params = [])
    {
        if (empty($service)) {
            $service = Settings::get('shortlinks');
        }

        $service = apply_filters('useyourdrive_api_shorten_url_set_service', $service);

        do_action('useyourdrive_api_before_shorten_url', $url, $service, $params);

        if (false !== strpos($url, 'localhost')) {
            // Most APIs don't support localhosts
            return $url;
        }

        try {
            switch ($service) {
                case 'Bit.ly':
                    $response = wp_remote_post('https://api-ssl.bitly.com/v4/shorten', [
                        'body' => json_encode(
                            [
                                'long_url' => $url,
                            ]
                        ),
                        'headers' => [
                            'Authorization' => 'Bearer '.Settings::get('bitly_apikey'),
                            'Content-Type' => 'application/json',
                        ],
                    ]);

                    $data = json_decode($response['body'], true);

                    return $data['link'];

                case 'Shorte.st':
                    $response = wp_remote_get('https://api.shorte.st/s/'.Settings::get('shortest_apikey').'/'.$url);

                    $data = json_decode($response['body'], true);

                    return $data['shortenedUrl'];

                case 'Tinyurl':
                    $response = wp_remote_post('https://api.tinyurl.com/create?api_token='.Settings::get('tinyurl_apikey'), [
                        'body' => json_encode(
                            [
                                'url' => $url,
                                'domain' => Settings::get('tinyurl_domain'),
                            ]
                        ),
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                    ]);

                    $data = json_decode($response['body'], true);

                    return (!empty($data['errors'])) ? htmlspecialchars(reset($data['errors']), ENT_QUOTES) : $data['data']['tiny_url'];

                case 'Rebrandly':
                    $response = wp_remote_post('https://api.rebrandly.com/v1/links', [
                        'body' => json_encode(
                            [
                                'title' => isset($params['name']) ? $params['name'] : '',
                                'destination' => $url,
                                'domain' => ['fullName' => Settings::get('rebrandly_domain')],
                            ]
                        ),
                        'headers' => [
                            'apikey' => Settings::get('rebrandly_apikey'),
                            'Content-Type' => 'application/json',
                            'workspace' => Settings::get('rebrandly_workspace'),
                        ],
                    ]);

                    $data = json_decode($response['body'], true);

                    return 'https://'.$data['shortUrl'];

                case 'None':
                default:
                    break;
            }
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return $url;
        }

        $url = apply_filters('useyourdrive_api_'.$service.'_shorten_url', $url, $params);
        $shortened_url = apply_filters('useyourdrive_api_shorten_url_set_shortened_url', $url, $params);

        do_action('useyourdrive_api_after_shorten_url', $shortened_url);

        return $shortened_url;
    }

    /**
     * Rename a file/folder.
     *
     * @param string $id       The entry that should be renamed
     * @param string $new_name The new name
     *
     * @return API_Exception|CacheNode
     */
    public static function rename($id, $new_name)
    {
        $update_request = new \UYDGoogle_Service_Drive_DriveFile();
        $update_request->setName($new_name);

        // Patch the file or folder
        return self::patch($id, $update_request);
    }

    /**
     * Update an file. This can be e.g. used to rename a file.
     *
     * @param string                            $id             ID of the entry that should be updated
     * @param UYDGoogle_Service_Drive_DriveFile $update_request The requested patch
     * @param array                             $_params        API request parameters
     *
     * @return API_Exception|CacheNode
     */
    public static function patch($id, \UYDGoogle_Service_Drive_DriveFile $update_request, $_params = [])
    {
        $update_request = apply_filters('useyourdrive_api_patch_set_update_request', $update_request);

        $params = array_merge([
            'fields' => 'id',
            'supportsAllDrives' => true,
        ], $_params);

        do_action('useyourdrive_api_before_patch', $id, $update_request);

        try {
            App::instance()->get_drive()->files->update($id, $update_request, $params);
            $api_entry = App::instance()->get_drive()->files->get($id, ['fields' => self::$apifilefields, 'supportsAllDrives' => true]);

            $entry = new Entry($api_entry);
            $node = Cache::instance()->add_to_cache($entry);
            Cache::instance()->update_cache();
        } catch (\Exception $ex) {
            Cache::instance()->reset_cache();
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            throw new API_Exception(esc_html__('Failed to patch file.', 'wpcloudplugins'));
        }

        do_action('useyourdrive_api_after_patch', $node);

        return $node;
    }

    /**
     * Download a file.
     *
     * @param string $id          ID of the file you want to download
     * @param string $mimetype    Export a file to a specified mimetype. Can be used for Google documents. Default $mimetype = 'default'
     * @param bool   $force_proxy Download the file via your server. If false, the plugin will redirect the user instead if possible. Default $force_proxy = false;
     */
    public static function download($id, $mimetype = 'default', $force_proxy = false)
    {
        $cached_node = self::get_entry($id);

        $download = new Download($cached_node, $mimetype, $force_proxy);
        $download->start_download();

        exit;
    }

    /**
     * Get the shared url for a file or folder.
     *
     * Create a shared url for a file or folder and update its sharing permissions. By default, a public shared link will be created. Google Business can request an expiring shared link or set a password.
     *
     * @param string $id     ID of the entry for which you want to create the shared url
     * @param array  $params Additional params for sharing permissions. Default $params = ['role' => 'reader']
     *
     * @return API_Exception|string Returns the shared url
     */
    public static function create_shared_url($id, $params = ['role' => 'reader'])
    {
        // Check the permissions and set it if possible
        if (!self::has_permission($id, [$params['role']])) {
            self::set_permission($id, $params['role']);
        }

        $node = self::get_entry($id);

        $entry = $node->get_entry();

        $url = $entry->get_preview_link();

        // Add Resources key to give permission to access the item
        if ($entry->has_resourcekey()) {
            $url .= "&resourcekey={$entry->get_resourcekey()}";
        }

        // Build View only link
        $url = str_replace('edit?usp=drivesdk', 'view', $url);
        $url = str_replace('preview?rm=minimal', 'view', $url);
        $url = str_replace('preview', 'view', $url);

        return apply_filters('useyourdrive_api_create_shared_url_set_url', $url, $params, $node);
    }

    /**
     * Create an url to an editable view of the file.
     *
     * @param string $id     ID of the entry for which you want to create the editable url
     * @param array  $params Additional params for sharing permissions. Default $params = ['role' => 'writer']
     *
     * @return API_Exception|string
     */
    public static function create_edit_url($id, $params = ['role' => 'writer'])
    {
        // Get file meta data
        $node = self::get_entry($id);

        if (false === $node) {
            Helpers::log_error('Failed to find entry.', 'API', ['entry_id' => $id], __LINE__);

            return false;
        }

        $entry = $node->get_entry();
        if (false === $entry->edit_supported_by_cloud()) {
            Helpers::log_error('File cannot be edited..', 'API', ['entry_id' => $id], __LINE__);

            return false;
        }

        $params = apply_filters('useyourdrive_api_create_edit_url_set_params', $params);

        do_action('useyourdrive_api_before_create_edit_url', $id, $params);

        self::create_shared_url($id, $params);

        $entry = $node->get_entry();
        $mimetype = $entry->get_mimetype();

        $arguments = 'edit?usp=drivesdk';

        switch ($mimetype) {
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/vnd.google-apps.document':
                $url = 'https://docs.google.com/document/d/'.$node->get_id().'/'.$arguments;

                break;

            case 'application/vnd.ms-excel':
            case 'application/vnd.ms-excel.sheet.macroenabled.12':
            case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
            case 'application/vnd.google-apps.spreadsheet':
                $url = 'https://docs.google.com/spreadsheets/d/'.$node->get_id().'/'.$arguments;

                break;

            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.slideshow':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
            case 'application/vnd.google-apps.presentation':
                $url = 'https://docs.google.com/presentation/d/'.$node->get_id().'/'.$arguments;

                break;

            case 'application/vnd.google-apps.drawing':
                $url = 'https://docs.google.com/drawings/d/'.$node->get_id().'/'.$arguments;

                break;

            default:
                return false;

                break;
        }

        // Add Resources key to give permission to access the item
        if ($entry->has_resourcekey()) {
            $url .= "&resourcekey={$entry->get_resourcekey()}";
        }

        do_action('useyourdrive_api_after_create_edit_url', $url);

        return apply_filters('useyourdrive_api_create_edit_url_set_url', $url, $params, $node);
    }

    /**
     * Set Sharing permissions for file/folder.
     *
     * @param string $id              ID of the entry
     * @param string $permission_role The requested shared permission. See https://developers.google.com/drive/api/guides/ref-roles; Default $permission_role = 'reader'
     * @param string $requested_for   The action for which this permission change is being requested
     *
     * @return bool
     */
    public static function set_permission($id, $permission_role = 'reader', $requested_for = '')
    {
        $cached_node = self::get_entry($id);

        // Return if we can't update the sharing permissions
        if ('Yes' !== Settings::get('manage_permissions') || !$cached_node->get_entry()->get_permission('canshare')) {
            return false;
        }

        $permission_type = ('' === Settings::get('permission_domain')) ? 'anyone' : 'domain';
        $permission_domain = ('' === Settings::get('permission_domain')) ? null : Settings::get('permission_domain');

        $new_permission = new \UYDGoogle_Service_Drive_Permission();
        $new_permission->setType($permission_type);
        $new_permission->setRole($permission_role);
        $new_permission->setAllowFileDiscovery(false);
        if (null !== $permission_domain) {
            $new_permission->setDomain($permission_domain);
        }

        $new_permission = apply_filters('useyourdrive_api_create_shared_url_set_params', $new_permission, $cached_node);

        $params = [
            'supportsAllDrives' => true,
        ];

        $params = apply_filters('useyourdrive_api_create_shared_url_set_apiparams', $params, $cached_node);

        do_action('useyourdrive_api_before_create_shared_url', $cached_node, $new_permission);

        try {
            $updated_permission = App::instance()->get_drive()->permissions->create($cached_node->get_id(), $new_permission, $params);

            $users = $cached_node->get_entry()->get_permission('users');
            $users[$updated_permission->getId()] = ['type' => $updated_permission->getType(), 'role' => $updated_permission->getRole(), 'domain' => $updated_permission->getDomain()];
            $cached_node->get_entry()->set_permissions_by_key('users', $users);
            Cache::instance()->add_to_cache($cached_node->get_entry());
        } catch (\Exception $ex) {
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return false;
        }

        do_action('useyourdrive_log_event', 'useyourdrive_updated_metadata', $cached_node, ['metadata_field' => 'Sharing Permissions', 'requested_for' => $requested_for, 'permission_id' => $updated_permission->getId()]);

        do_action('useyourdrive_api_after_create_shared_url', $cached_node->get_entry()->get_preview_link());

        return true;
    }

    /**
     * Check if file/folder has required sharing permissions.
     *
     * @param string   $id              ID of the entry
     * @param string[] $permission_role The requested shared permission. See https://developers.google.com/drive/api/guides/ref-roles. Default $permission_role = ['reader', 'writer']
     * @param bool     $force_update    use value in cache if available or check via API
     *
     * @return bool
     */
    public static function has_permission($id, $permission_role = ['reader', 'writer'], $force_update = false)
    {
        $cached_node = self::get_entry($id);

        $entry = $cached_node->get_entry();
        $permission_type = ('' === Settings::get('permission_domain')) ? 'anyone' : 'domain';
        $permission_domain = ('' === Settings::get('permission_domain')) ? null : Settings::get('permission_domain');

        $users = $entry->get_permission('users');

        // If the permissions are not yet set, grab them via the API
        if (empty($users) && $cached_node->get_entry()->get_permission('canshare') || true === $force_update) {
            $users = [];

            $params = [
                'fields' => 'kind,nextPageToken,permissions(kind,id,type,role,domain,permissionDetails(permissionType,role))',
                'pageSize' => 100,
                'supportsAllDrives' => true,
            ];

            $nextpagetoken = null;
            // Get all files in folder
            while ($nextpagetoken || null === $nextpagetoken) {
                try {
                    if (null !== $nextpagetoken) {
                        $params['pageToken'] = $nextpagetoken;
                    }

                    $more_permissions = App::instance()->get_drive()->permissions->listPermissions($entry->get_id(), $params);
                    $users = array_merge($users, $more_permissions->getPermissions());
                    $nextpagetoken = (null !== $more_permissions->getNextPageToken()) ? $more_permissions->getNextPageToken() : false;
                } catch (\Exception $ex) {
                    Helpers::log_error('', 'API', null, __LINE__, $ex);

                    return false;
                }
            }

            $entry_permission = [];
            foreach ($users as $user) {
                $entry_permission[$user->getId()] = ['type' => $user->getType(), 'role' => $user->getRole(), 'domain' => $user->getDomain()];
            }
            $entry->set_permissions_by_key('users', $entry_permission);
            Cache::instance()->add_to_cache($entry);
        }

        $users = $entry->get_permission('users');

        if (!empty($users)) {
            foreach ($users as $user) {
                if (($user['type'] === $permission_type) && in_array($user['role'], $permission_role) && ($user['domain'] === $permission_domain)) {
                    return true;
                }
            }
        }

        /* For shared files not owned by account, the sharing permissions cannot be viewed or set.
         * In that case, just check if the file is public shared
         */
        if (in_array('reader', $permission_role)) {
            $check_url = 'https://drive.google.com/file/d/'.$entry->get_id().'/view';

            // Add Resources key to give permission to access the item
            if ($entry->has_resourcekey()) {
                $check_url .= "&resourcekey={$entry->get_resourcekey()}";
            }

            $request = new \UYDGoogle_Http_Request($check_url, 'GET');
            App::instance()->get_sdk_client()->getIo()->setOptions([CURLOPT_FOLLOWLOCATION => 0]);
            $httpRequest = App::instance()->get_sdk_client()->getIo()->makeRequest($request);
            curl_close(App::instance()->get_sdk_client()->getIo()->getHandler());

            if (200 == $httpRequest->getResponseHttpCode()) {
                $users['anyoneWithLink'] = ['type' => 'anyone', 'role' => 'reader', 'domain' => $permission_domain];
                $entry->set_permissions_by_key('users', $users);
                Cache::instance()->add_to_cache($entry);

                return true;
            }
        }

        return false;
    }
}

/**
 * API_Exception Class.
 *
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */
class API_Exception extends \Exception {}
