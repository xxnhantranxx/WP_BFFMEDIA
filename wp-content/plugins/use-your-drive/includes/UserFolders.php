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

class UserFolders
{
    /**
     * The single instance of the class.
     *
     * @var UserFolders
     */
    protected static $_instance;

    /**
     * @var \stdClass|\WP_User
     */
    private $_current_user;

    /**
     * @var string
     */
    private $_user_name_template;

    /**
     * @var string
     */
    private $_user_folder_name;

    /**
     * @var Entry
     */
    private $_user_folder_entry;

    /**
     * @var \WP_User[]
     */
    private $_custom_user_metadata = [];

    public function __construct()
    {
        $this->_user_name_template = Settings::get('userfolder_name');

        $shortcode = Processor::instance()->get_shortcode();
        if (!empty($shortcode) && !empty($shortcode['user_folder_name_template'])) {
            $this->_user_name_template = $shortcode['user_folder_name_template'];
        }
    }

    /**
     * Check if the module is using dynamic folders.
     *
     * @return bool
     */
    public static function is_using_dynamic_folders()
    {
        if ('auto' === Processor::instance()->get_shortcode_option('userfolders')) {
            return true;
        }

        return false;
    }

    /**
     * Get the current user processed for the Personal Folders.
     *
     * @return \stdClass|\WP_User
     */
    public function get_current_user()
    {
        if (null === $this->_current_user) {
            if (is_user_logged_in()) {
                $this->_current_user = wp_get_current_user();
            } else {
                $username = $this->get_guest_id();

                $user = new \stdClass();
                $user->user_login = $username;
                $user->display_name = $username;
                $user->ID = $username;
                $user->user_role = esc_html__('Anonymous user', 'wpcloudplugins');
                $this->_current_user = $user;
            }
        }

        return $this->_current_user;
    }

    /**
     * UserFolders Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return UserFolders - UserFolders instance
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

    public static function user_register($user_id, $force = false)
    {
        if ('Yes' !== Settings::get('userfolder_oncreation') && false === $force) {
            return;
        }

        self::instance()->_current_user = get_user_by('id', $user_id);

        foreach (Accounts::instance()->list_accounts() as $account) {
            if (false === $account->get_authorization()->has_access_token()) {
                continue;
            }

            App::set_current_account($account);
            self::instance()->create_user_folders_for_shortcodes();
        }
    }

    /**
     * Temporarily store old user meta data when user data is updated.
     * This makes old meta data available in user_profile_update function.
     *
     * @param array    $data
     * @param bool     $update
     * @param null|int $user_id
     * @param array    $userdata
     */
    public static function store_custom_user_metadata($data, $update, $user_id, $userdata)
    {
        if (empty($update) || empty($user_id)) {
            return $data;
        }

        $old_user = new \WP_User($user_id);

        self::instance()->_custom_user_metadata[$user_id] = get_user_meta($user_id);
        clean_user_cache($old_user);

        return $data;
    }

    public static function user_profile_update($user_id, $custom_user_metadata = false, $force = false)
    {
        if ('Yes' !== Settings::get('userfolder_update') && false === $force) {
            return;
        }

        self::instance()->_current_user = get_user_by('id', $user_id);

        foreach (Accounts::instance()->list_accounts() as $account) {
            if (false === $account->get_authorization()->has_access_token()) {
                continue;
            }

            App::set_current_account($account);
            self::instance()->update_user_folder($custom_user_metadata);
        }
    }

    public static function user_delete($user_id)
    {
        if ('Yes' !== Settings::get('userfolder_remove')) {
            return;
        }

        self::instance()->_current_user = get_user_by('id', $user_id);

        foreach (Accounts::instance()->list_accounts() as $account) {
            if (false === $account->get_authorization()->has_access_token()) {
                continue;
            }

            App::set_current_account($account);
            self::instance()->remove_user_folder();
        }
    }

    public function get_auto_linked_folder_name_for_user()
    {
        if ('auto' !== Processor::instance()->get_shortcode_option('userfolders')) {
            return false;
        }

        if (!empty($this->_user_folder_name)) {
            return $this->_user_folder_name;
        }

        $this->_user_folder_name = is_user_logged_in() ? $this->get_user_name_template() : $this->get_guest_user_name();

        return $this->_user_folder_name;
    }

    public function get_auto_linked_folder_for_user()
    {
        if ('auto' !== Processor::instance()->get_shortcode_option('userfolders')) {
            return false;
        }

        if (!empty($this->_user_folder_entry)) {
            return $this->_user_folder_entry;
        }

        // Add folder if needed
        $result = $this->create_user_folder($this->get_auto_linked_folder_name_for_user(), Processor::instance()->get_shortcode(), 0);

        do_action('useyourdrive_after_private_folder_added', $result, Processor::instance());

        if (false === $result) {
            Helpers::log_error('Cannot find auto folder link for user', 'Dynamic Folders', null, __LINE__);

            exit;
        }

        $this->_user_folder_entry = $result;

        return $this->_user_folder_entry;
    }

    /**
     * Convert old manually linked folders value to new format.
     * The new value is an array of personal folders.
     *
     * @param array|bool $value
     */
    public static function convert_old_manually_linked_folders_value($value)
    {
        if (!is_array($value)) {
            return $value; // No folders linked
        }
        if (isset($value['folderid'])) {
            return ['personal-folder-'.md5($value['accountid'].$value['folderid']) => $value]; // Return an array of folders for older values
        }

        return $value; // Value is up to date
    }

    /**
     * Get manually linked personal folder for user.
     *
     * @param null|string $user_id
     *
     * @return bool|CacheNode $user_id
     */
    public function get_manually_linked_folder_for_user($user_id = null)
    {
        $shortcode = Processor::instance()->get_shortcode();
        if (!isset($shortcode['userfolders']) || 'manual' !== $shortcode['userfolders']) {
            return false;
        }

        if (!empty($this->_user_folder_entry)) {
            return $this->_user_folder_entry;
        }

        $personal_folders = $user_id ? get_user_option('use_your_drive_linkedto', $user_id) : get_site_option('use_your_drive_guestlinkedto');

        if (is_array($personal_folders) && !empty($personal_folders)) {
            $personal_folder = reset($personal_folders);
            $linked_account = isset($personal_folder['accountid']) ? Accounts::instance()->get_account_by_id($personal_folder['accountid']) : Accounts::instance()->get_primary_account();

            App::set_current_account($linked_account);

            // Untill multiple folders are supported, just return the first folder
            return $this->_user_folder_entry = Client::instance()->get_entry($personal_folder['folderid'], false);
        }
        if (null !== $user_id) {
            // User does not have a folder associated with his account, load folder for guest users
            return self::get_manually_linked_folder_for_user();
        }

        Helpers::log_error(sprintf('Cannot find manual folder link for user: %s', $this->get_current_user()->user_login), 'Dynamic Folders', null, __LINE__);

        exit(-1);
    }

    /**
     * Manually link a folder to a user.
     *
     * @param int|string $user_id
     * @param array      $linkedto
     */
    public function manually_link_folder($user_id, $linkedto)
    {
        // Set the current account by ID
        App::set_current_account_by_id($linkedto['accountid']);

        // Get the folder node
        $node = API::get_folder($linkedto['folderid']);
        $linkedto['foldertext'] = $node->get_name();
        $personal_folder_key = 'personal-folder-'.md5($linkedto['accountid'].$linkedto['folderid']);

        // Handle linking for both guest and registered users
        $option_name = ('GUEST' === $user_id) ? 'use_your_drive_guestlinkedto' : 'use_your_drive_linkedto';
        $personal_folders = ('GUEST' === $user_id) ? get_site_option($option_name) : get_user_option($option_name, $user_id);

        $personal_folders = [$personal_folder_key => $linkedto];

        // ADD: When supporting multiple folders
        // if (!is_array($personal_folders)) {
        //     $personal_folders = [];
        // }

        // $personal_folders[$personal_folder_key] = $linkedto;
        // END ADD

        if ('GUEST' === $user_id) {
            update_site_option($option_name, $personal_folders);
        } else {
            update_user_option($user_id, $option_name, $personal_folders, false);
        }

        // Return the updated personal folders as JSON
        echo json_encode($personal_folders);

        exit;
    }

    /**
     * Manually unlink a folder from a user.
     *
     * @param int|string $user_id
     * @param string     $personal_folder_key
     */
    public function manually_unlink_folder($user_id, $personal_folder_key)
    {
        $option_name = ('GUEST' === $user_id) ? 'use_your_drive_guestlinkedto' : 'use_your_drive_linkedto';
        $personal_folders = ('GUEST' === $user_id) ? get_site_option($option_name) : get_user_option($option_name, $user_id);

        if (!is_array($personal_folders)) {
            exit('-1');
        }

        unset($personal_folders[$personal_folder_key]);

        if (empty($personal_folders)) {
            $result = ('GUEST' === $user_id) ? delete_site_option($option_name) : delete_user_option($user_id, $option_name, false);
        } else {
            $result = ('GUEST' === $user_id) ? update_site_option($option_name, $personal_folders) : update_user_option($user_id, $option_name, $personal_folders, false);
        }

        if (false !== $result) {
            exit('1');
        }
    }

    public function create_user_folder($userfoldername, $shortcode, $mswaitaftercreation = 0)
    {
        Helpers::set_time_limit(60);

        $parent_folder_data = Client::instance()->get_folder($shortcode['root'], false);

        // If root folder doesn't exists
        if (empty($parent_folder_data) || '0' === $parent_folder_data['folder']->get_id()) {
            return false;
        }
        $parent_folder = $parent_folder_data['folder'];

        // Create Folder structure if required (e.g. it contains a /)
        if (true === apply_filters('useyourdrive_private_folder_name_allow_subfolders', true)) {
            $subfolders = array_filter(explode('/', $userfoldername));
            $userfoldername = array_pop($subfolders);

            foreach ($subfolders as $subfolder) {
                $parent_folder = API::get_sub_folder_by_path($parent_folder->get_id(), $subfolder, true);
            }
        }

        // First try to find the User Folder in Cache
        $userfolder = API::search_for_name_in_folder($userfoldername, $parent_folder->get_id());

        // If User Folder still isn't found, create new folder in the Cloud
        if (false === $userfolder) {
            $newfolder = new \UYDGoogle_Service_Drive_DriveFile();
            $newfolder->setName($userfoldername);
            $newfolder->setMimeType('application/vnd.google-apps.folder');
            $newfolder->setParents([$parent_folder->get_id()]);

            try {
                $api_entry = App::instance()->get_drive()->files->create($newfolder, ['fields' => Client::instance()->apifilefields, 'supportsAllDrives' => true]);

                // Wait a moment in case many folders are created at once
                usleep($mswaitaftercreation);
            } catch (\Exception $ex) {
                Helpers::log_error('Failed to add user folder.', 'Dynamic Folders', ['entry_name' => $userfoldername, 'parent_id' => $parent_folder->get_id()], __LINE__);

                return new \WP_Error('broke', esc_html__('Failed to add user folder', 'wpcloudplugins'));
            }

            // Add new file to our Cache
            $newentry = new Entry($api_entry);
            $userfolder = Cache::instance()->add_to_cache($newentry);
            Cache::instance()->update_cache();

            do_action('useyourdrive_log_event', 'useyourdrive_created_entry', $userfolder);

            do_action('useyourdrive_dynamic_folder_created', $userfolder, $shortcode);

            // Create a shared link to the folder if needed
            if ('Yes' === Settings::get('userfolder_oncreation_share')) {
                $this->share_personal_folder($userfolder);
            }
        }

        // Check if Template folder should be created
        // 1: Is there a template folder set?
        if (empty($shortcode['user_template_dir'])) {
            return $userfolder;
        }

        // Wait a moment
        usleep(50000);

        // Make sure that the folder is completely loaded before we proceed, perhaps the folder already existed and contains the template folders
        $user_folder_node = Client::instance()->get_folder($userfolder->get_id(), false);

        // In some case the API doesn't yet response with the information for the newly created folder. In that case, fallback to the folder information received earlier
        if (!empty($user_folder_node)) {
            $userfolder = $user_folder_node['folder'];
        } else {
            Helpers::log_error('Fallback to earlier received folder information for folder..', 'Dynamic Folders', ['entry_id' => $userfolder->get_id()], __LINE__);

            $user_folder_node = $userfolder;
        }

        // 2: Has the User Folder already sub folders?
        if ($userfolder->has_children()) {
            return $userfolder;
        }

        // 3: Get the Template folder
        $cached_template_folder = Client::instance()->get_folder($shortcode['user_template_dir'], false);

        // 4: Make sure that the Template folder can be used
        if (false === $cached_template_folder || false === $cached_template_folder['folder'] || false === $cached_template_folder['folder']->has_children()) {
            return $userfolder;
        }

        if ($userfolder->is_in_folder($cached_template_folder['folder']->get_id())) {
            Helpers::log_error('The new folder is in the template folder. Select another folder.', 'Dynamic Folders', ['entry_id' => $userfolder->get_id(), 'template_id' => $cached_template_folder['folder']->get_id()], __LINE__);

            return new \WP_Error('broke', esc_html__('The new folder is in the template folder. Select another folder.', 'wpcloudplugins'));
        }

        // Copy the contents of the Template Folder into the User Folder
        API::copy_folder_recursive($cached_template_folder['folder']->get_id(), $userfolder->get_id());

        return $userfolder;
    }

    public function create_user_folders_for_shortcodes()
    {
        $useyourdrivelists = Shortcodes::instance()->get_all_shortcodes();
        $current_account = App::get_current_account();

        foreach ($useyourdrivelists as $list) {
            if (!isset($list['userfolders']) || 'auto' !== $list['userfolders']) {
                continue;
            }

            if (!isset($list['account']) || $current_account->get_id() !== $list['account']) {
                continue; // Skip shortcodes that don't belong to the account that is being processed
            }

            if (false === Helpers::check_user_role($list['view_role'], $this->get_current_user())) {
                continue; // Skip shortcodes that aren't accessible for user
            }

            if (false !== strpos($list['class'], 'disable-create-personal-folder-on-registration')) {
                continue; // Skip shortcodes that explicitly have set to skip automatic folder creation
            }

            if (!empty($list['user_folder_name_template'])) {
                $this->_user_name_template = $list['user_folder_name_template'];
            } else {
                $this->_user_name_template = Settings::get('userfolder_name');
            }

            if (false === strpos($this->_user_name_template, '%')) {
                continue; // Skip shortcodes that don't have a dynamic folder template
            }

            $new_userfoldersname = $this->get_user_name_template();

            $result = $this->create_user_folder($new_userfoldersname, $list);

            do_action('useyourdrive_after_private_folder_added', $result, Processor::instance());
        }
    }

    public function create_user_folders($users = [])
    {
        if (0 === count($users)) {
            return;
        }

        foreach ($users as $user) {
            $this->_current_user = $user;
            $userfoldersname = $this->get_user_name_template();

            $result = $this->create_user_folder($userfoldersname, Processor::instance()->get_shortcode());

            do_action('useyourdrive_after_private_folder_added', $result, Processor::instance());
        }
    }

    public function remove_user_folder()
    {
        $useyourdrivelists = Shortcodes::instance()->get_all_shortcodes();
        $current_account = App::get_current_account();

        // Apply Batch
        $do_delete = false;

        $batch = new \UYDGoogle_Http_Batch(App::instance()->get_sdk_client());

        foreach ($useyourdrivelists as $list) {
            if (!isset($list['userfolders']) || 'auto' !== $list['userfolders']) {
                continue;
            }

            if (!isset($list['account']) || $current_account->get_id() !== $list['account']) {
                continue; // Skip shortcodes that don't belong to the account that is being processed
            }

            if (false === Helpers::check_user_role($list['view_role'], $this->get_current_user())) {
                continue; // Skip shortcodes that aren't accessible for user
            }

            if (!empty($list['user_folder_name_template'])) {
                $this->_user_name_template = $list['user_folder_name_template'];
            } else {
                $this->_user_name_template = Settings::get('userfolder_name');
            }

            if (false === strpos($this->_user_name_template, '%')) {
                continue; // Skip shortcodes that don't have a dynamic folder template
            }

            $userfoldername = $this->get_user_name_template();

            $params = [
                'q' => "'".$list['root']."' in parents and name='".$userfoldername."' and mimeType='application/vnd.google-apps.folder' and trashed = false",

                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            try {
                App::instance()->get_sdk_client()->setUseBatch(false);
                $api_list = App::instance()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                Helpers::log_error('Failed to remove user folder.', 'Dynamic Folders', null, __LINE__, $ex);

                return false;
            }

            $api_files = $api_list->getFiles();

            // Stop when no User Folders are found
            if (0 === count($api_files)) {
                continue;
            }

            $do_delete = true;
            // Delete all the user folders that are found
            // 1: Create an the entry for Patch
            $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
            $updateentry->setTrashed(true);

            App::instance()->get_sdk_client()->setUseBatch(true);
            foreach ($api_files as $api_file) {
                $batch->add(App::instance()->get_drive()->files->update($api_file->getId(), $updateentry, ['supportsAllDrives' => true]));
            }
        }

        if ($do_delete) {
            try {
                $batch->execute();
            } catch (\Exception $ex) {
                Helpers::log_error('Failed to remove user folder.', 'Dynamic Folders', null, __LINE__, $ex);
            }
        }

        App::instance()->get_sdk_client()->setUseBatch(false);

        Processor::reset_complete_cache(false);

        return true;
    }

    public function update_user_folder($old_user)
    {
        $useyourdrivelists = Shortcodes::instance()->get_all_shortcodes();
        $current_account = App::get_current_account();

        foreach ($useyourdrivelists as $list) {
            if (!isset($list['userfolders']) || 'auto' !== $list['userfolders']) {
                continue;
            }

            if (!isset($list['account']) || $current_account->get_id() !== $list['account']) {
                continue; // Skip shortcodes that don't belong to the account that is being processed
            }

            if (false === Helpers::check_user_role($list['view_role'], $this->get_current_user())) {
                continue; // Skip shortcodes that aren't accessible for user
            }

            if (!empty($list['user_folder_name_template'])) {
                $this->_user_name_template = $list['user_folder_name_template'];
            } else {
                $this->_user_name_template = Settings::get('userfolder_name');
            }

            if (false === strpos($this->_user_name_template, '%')) {
                continue; // Skip shortcodes that don't have a dynamic folder template
            }

            $new_userfoldersname = $this->get_user_name_template();
            $old_userfoldersname = $this->get_user_name_template($old_user, ['custom_user_metadata' => $this->_custom_user_metadata[$old_user->ID] ?? null]);

            if ($new_userfoldersname === $old_userfoldersname) {
                continue;
            }

            if (defined('use_your_drive_update_user_folder_'.$list['root'].'_'.$new_userfoldersname)) {
                continue;
            }

            define('use_your_drive_update_user_folder_'.$list['root'].'_'.$new_userfoldersname, true);

            $params = [
                'q' => "'".$list['root']."' in parents and name='".$old_userfoldersname."' and mimeType='application/vnd.google-apps.folder' and trashed = false",

                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            try {
                $api_list = App::instance()->get_drive()->files->listFiles($params);
            } catch (\Exception $ex) {
                Helpers::log_error('Failed to update user folder.', 'Dynamic Folders', null, __LINE__, $ex);

                return false;
            }

            $api_files = $api_list->getFiles();

            // Stop when no User Folders are found
            if (0 === count($api_files)) {
                continue;
            }

            // Delete all the user folders that are found
            // 1: Create an the entry for Patch
            $updateentry = new \UYDGoogle_Service_Drive_DriveFile();
            $updateentry->setName($new_userfoldersname);

            foreach ($api_files as $api_file) {
                try {
                    API::patch($api_file->getId(), $updateentry);
                } catch (\Exception $ex) {
                    Helpers::log_error('Failed to update user folder.', 'Dynamic Folders', null, __LINE__, $ex);

                    continue;
                }
            }
        }

        Processor::reset_complete_cache(false);

        return true;
    }

    public function get_user_name_template($user = null, $placeholder_params = [])
    {
        if (null === $user) {
            $user = $this->get_current_user();
        }

        $placeholder_params['user_data'] = $user;

        $user_folder_name = Placeholders::apply($this->_user_name_template, Processor::instance(), $placeholder_params);

        return apply_filters('useyourdrive_private_folder_name', $user_folder_name, Processor::instance());
    }

    public function get_guest_user_name()
    {
        $user_folder_name = $this->get_user_name_template();

        if (empty($user_folder_name)) {
            $user_folder_name = $this->get_guest_id();
        }

        $prefix = Settings::get('userfolder_name_guest_prefix');

        return apply_filters('useyourdrive_private_folder_name_guests', $prefix.$user_folder_name, Processor::instance());
    }

    public function share_personal_folder(CacheNode $cached_node)
    {
        $permission = new \UYDGoogle_Service_Drive_Permission();
        $permission->setType('user');
        $permission->setRole('reader');
        $permission->setEmailAddress($this->get_current_user()->user_email);

        $permission = apply_filters('useyourdrive_api_create_shared_url_set_params', $permission, $cached_node);
        $permission = apply_filters('useyourdrive_api_create_shared_personal_folder_set_params', $permission, $cached_node);

        $params = [
            'supportsAllDrives' => true,
            'sendNotificationEmail' => true,
            'emailMessage' => sprintf(esc_html__('Hello %s, you have been granted access to your folder. You can now view and download the contents of this folder. If you have any questions or need further assistance, please do not hesitate to contact us.', 'wpcloudplugins'), $this->get_current_user()->display_name, $cached_node->get_name()),
        ];

        try {
            App::instance()->get_drive()->permissions->create($cached_node->get_id(), $permission, $params);
        } catch (\Exception $ex) {
            Helpers::log_error('Failed to share the personal folder with the user.', 'Dynamic Folders', null, __LINE__, $ex);

            return false;
        }
    }

    public static function get_guest_id()
    {
        if (!isset($_COOKIE['WPCP_UUID'])) {
            Helpers::log_error('No UUID found.', 'Dynamic Folders', null, __LINE__);

            exit;
        }

        return $_COOKIE['WPCP_UUID'];
    }
}
