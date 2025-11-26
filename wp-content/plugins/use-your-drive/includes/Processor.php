<?php

/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

use TheLion\UseyourDrive\Modules\Module;

defined('ABSPATH') || exit;

class Processor
{
    public $options = [];
    public $mobile = false;

    /**
     * The single instance of the class.
     *
     * @var Processor
     */
    protected static $_instance;
    protected $listtoken = '';
    protected $_rootFolder;
    protected $_lastFolder;
    protected $_folderPath;
    protected $_requestedEntry;

    /**
     * Construct the plugin object.
     */
    public function __construct()
    {
        register_shutdown_function([static::class, 'do_shutdown']);

        if (isset($_REQUEST['mobile']) && ('true' === $_REQUEST['mobile'])) {
            $this->mobile = true;
        }

        // If the user wants a hard refresh, set this globally
        if (isset($_REQUEST['hardrefresh']) && 'true' === $_REQUEST['hardrefresh'] && (!defined('FORCE_REFRESH'))) {
            define('FORCE_REFRESH', true);
        }
    }

    /**
     * Processor Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return Processor - Processor instance
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

    public function start_process()
    {
        if (!isset($_REQUEST['action'])) {
            Helpers::log_error('Action is missing from request', 'Processor', null, __LINE__);

            \http_response_code(400);

            exit;
        }

        $requested_action = $_REQUEST['action'];

        if (isset($_REQUEST['account_id'])) {
            $requested_account_id = $_REQUEST['account_id'];
            $requested_account = Accounts::instance()->get_account_by_id($requested_account_id);
            if (null !== $requested_account) {
                App::set_current_account($requested_account);
            } else {
                Helpers::log_error('Cannot use the requested account as it is not linked with the plugin', 'Processor', ['account_id' => sanitize_key($requested_account_id)], __LINE__);

                exit;
            }
        }

        do_action('useyourdrive_before_start_process', $_REQUEST['action'], $this);

        $authorized = AjaxRequest::is_action_authorized();

        if ((true === $authorized) && ('useyourdrive-revoke' === $_REQUEST['action'])) {
            $data = ['account_id' => App::get_current_account()->get_id(), 'action' => 'revoke', 'success' => false];
            if (Helpers::check_user_role(Settings::get('permissions_edit_settings'))) {
                if (null === App::get_current_account()) {
                    echo json_encode($data);

                    exit;
                }

                if ('true' === $_REQUEST['force']) {
                    Accounts::instance()->remove_account(App::get_current_account()->get_id());
                } else {
                    App::instance()->revoke_token(App::get_current_account());
                }

                $data['success'] = true;
            }

            echo json_encode($data);

            exit;
        }

        if (!isset($_REQUEST['listtoken'])) {
            Helpers::log_error('Token is missing from request', 'Processor', null, __LINE__);

            exit;
        }

        $this->listtoken = sanitize_key($_REQUEST['listtoken']);
        $this->options = Shortcodes::instance()->get_shortcode_by_id($this->listtoken);

        if (false === $this->options) {
            Helpers::log_error('Token is invalid', 'Processor', ['action' => sanitize_key($requested_action)], __LINE__);

            exit;
        }

        if (false === User::can_view() || !apply_filters('useyourdrive_module_is_visible', true)) {
            Helpers::log_error('User does not have the permission to view the plugin', 'Processor', null, __LINE__);

            \http_response_code(401);

            exit;
        }

        if ('useyourdrive-module-login' !== $_REQUEST['action'] && !Restrictions::unlock_module()) {
            Helpers::log_error('Process not started as the user has not unlocked the module', 'Processor', null, __LINE__);

            \http_response_code(401);

            exit;
        }

        if (!in_array($_REQUEST['action'], ['useyourdrive-module-login', 'useyourdrive-module-lead']) && !LeadCapture::unlock_module()) {
            Helpers::log_error('Process not started as the user has not entered lead information', 'Processor', null, __LINE__);

            \http_response_code(401);

            exit;
        }

        if (null === App::get_current_account() || false === App::get_current_account()->get_authorization()->has_access_token()) {
            Helpers::log_error('Account is not linked to the plugin', 'Processor', null, __LINE__);

            return new \WP_Error('broke', '<strong>'.sprintf(esc_html__('%s needs your help!', 'wpcloudplugins'), 'Use-your-Drive').'</strong> '.esc_html__('Connect your account!', 'wpcloudplugins').'.');
        }

        Client::instance();

        // Set rootFolder
        if ('manual' === $this->options['userfolders']) {
            $manual_user_id = apply_filters('useyourdrive_set_user_id_for_manual_personal_folder', get_current_user_id(), $this);
            $userfolder = UserFolders::instance()->get_manually_linked_folder_for_user($manual_user_id);
            if (is_wp_error($userfolder) || false === $userfolder) {
                Helpers::log_error('Failed to find a manually linked folder for user', 'Processor', null, __LINE__);

                exit('-1');
            }
            $this->_rootFolder = $userfolder->get_id();
        } elseif (('auto' === $this->options['userfolders']) && !Helpers::check_user_role($this->options['view_user_folders_role'])) {
            $userfolder = UserFolders::instance()->get_auto_linked_folder_for_user();

            if (is_wp_error($userfolder) || false === $userfolder) {
                Helpers::log_error('Failed to find a n auto linked folder for user', 'Processor', null, __LINE__);

                exit('-1');
            }
            $this->_rootFolder = $userfolder->get_id();
        } else {
            $this->_rootFolder = $this->options['root'];
        }

        // Open Sub Folder if needed
        if (!empty($this->options['subfolder']) && '/' !== $this->options['subfolder']) {
            $sub_folder_path = apply_filters('useyourdrive_set_subfolder_path', Placeholders::apply($this->options['subfolder'], $this), $this->options, $this);
            $subfolder = API::get_sub_folder_by_path($this->_rootFolder, $sub_folder_path, true);

            if (is_wp_error($subfolder) || false === $subfolder) {
                Helpers::log_error('The subfolder cannot be found or cannot be created.', 'Processor', null, __LINE__);

                exit('-1');
            }
            $this->_rootFolder = $subfolder->get_id();
        }

        $this->_lastFolder = $this->_rootFolder;
        if (isset($_REQUEST['lastFolder']) && '' !== $_REQUEST['lastFolder']) {
            $this->_lastFolder = $_REQUEST['lastFolder'];
        }

        $this->_requestedEntry = $this->_lastFolder;
        if (isset($_REQUEST['id']) && '' !== $_REQUEST['id']) {
            $this->_requestedEntry = $_REQUEST['id'];
        }

        // Remove all cache files for current shortcode when refreshing, otherwise check for new changes
        if (defined('FORCE_REFRESH')) {
            CacheRequest::clear_request_cache();
            self::reset_complete_cache(false);
        } else {
            Cache::instance()->pull_for_changes($this->_lastFolder);
        }

        if (!empty($_REQUEST['folderPath'])) {
            $this->_folderPath = json_decode(base64_decode($_REQUEST['folderPath']), true);

            if (false === $this->_folderPath || null === $this->_folderPath || !is_array($this->_folderPath)) {
                // Build path when starting somewhere in the folder
                $current_folder = Client::instance()->get_folder($this->_lastFolder);

                if (empty($current_folder)) {
                    $this->_folderPath = [$this->_rootFolder];
                } elseif ($current_folder['folder']->get_id() === $this->_rootFolder) {
                    $this->_folderPath = [];
                } elseif (!empty($current_folder)) {
                    $parents = $current_folder['folder']->get_all_parent_folders();
                    $folder_path = [];

                    foreach ($parents as $parent_id => $parent) {
                        if ($parent_id === $this->_rootFolder && 'drive' !== $this->_rootFolder) {
                            break;
                        }
                        $folder_path[] = $parent_id;
                    }

                    $this->_folderPath = array_reverse($folder_path);
                } else {
                    $this->_folderPath = [$this->_rootFolder];
                }
            }

            $key = array_search($this->_requestedEntry, $this->_folderPath);
            if (false !== $key) {
                array_splice($this->_folderPath, $key);
                if (0 === count($this->_folderPath)) {
                    $this->_folderPath = [$this->_rootFolder];
                }
            }
        } else {
            $this->_folderPath = [$this->_rootFolder];
        }

        // Check if the request is cached
        if (in_array($_REQUEST['action'], ['useyourdrive-get-filelist', 'useyourdrive-get-gallery', 'useyourdrive-get-playlist', 'useyourdrive-thumbnail'])) {
            // And Set GZIP compression if possible
            $this->_set_gzip_compression();

            if (!defined('FORCE_REFRESH')) {
                $cached_request = new CacheRequest();
                if ($cached_request->is_cached()) {
                    echo $cached_request->get_cached_response();

                    exit;
                }
            }
        }

        do_action('useyourdrive_start_process', $_REQUEST['action'], $this);

        switch ($_REQUEST['action']) {
            case 'useyourdrive-get-filelist':
                if ('proofing' === $this->get_shortcode_option('mode')) {
                    $browser = new Modules\Proofing();
                } else {
                    $browser = new Modules\Filebrowser();
                }

                if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && '1' === $this->options['search']) { // Search files
                    $browser->search_files();
                } else {
                    $browser->get_files_list(); // Read folder
                }

                break;

            case 'useyourdrive-download':
                if (false === User::can_download()) {
                    exit;
                }

                Client::instance()->download_entry();

                break;

            case 'useyourdrive-preview':
                if (false === User::can_preview()) {
                    exit;
                }

                Client::instance()->preview_entry();

                break;

            case 'useyourdrive-edit':
                if (false === User::can_edit()) {
                    exit;
                }

                Client::instance()->edit_entry();

                break;

            case 'useyourdrive-thumbnail':
                if (isset($_REQUEST['type']) && 'folder-thumbnails' === $_REQUEST['type']) {
                    $thumbnails = Client::instance()->get_folder_thumbnails();
                    $response = json_encode($thumbnails);

                    $cached_request = new CacheRequest();
                    $cached_request->add_cached_response($response);

                    echo $response;
                } else {
                    Client::instance()->build_thumbnail();
                }

                break;

            case 'useyourdrive-create-zip':
                if (false === User::can_download()) {
                    exit;
                }

                $request_id = $_REQUEST['request_id'];

                switch ($_REQUEST['type']) {
                    case 'do-zip':
                        $zip = new Zip($request_id);
                        $zip->do_zip();

                        break;

                    case 'get-progress':
                        Zip::get_status($request_id);

                        break;

                    default:
                        exit;
                }

                break;

            case 'useyourdrive-embedded':
                $as_editable = isset($_REQUEST['editable']) && 'true' === $_REQUEST['editable'];
                $links = Client::instance()->create_links($as_editable);
                echo json_encode($links);

                break;

            case 'useyourdrive-create-link':
                $as_editable = isset($_REQUEST['editable']) && 'true' === $_REQUEST['editable'];

                if (isset($_REQUEST['entries'])) {
                    $links = Client::instance()->create_links($as_editable);
                    echo json_encode($links);
                } else {
                    $link = Client::instance()->create_link(null, $as_editable);
                    echo json_encode($link);
                }

                break;

            case 'useyourdrive-get-gallery':
                if ('carousel' === $_REQUEST['type']) {
                    $carousel = new Modules\Carousel($this);
                    $carousel->get_images_list();
                } else {
                    $gallery = new Modules\Gallery();

                    if (isset($_REQUEST['query']) && !empty($_REQUEST['query']) && '1' === $this->options['search']) { // Search files
                        $gallery->search_image_files();
                    } else {
                        $gallery->get_images_list(); // Read folder
                    }
                }

                break;

            case 'useyourdrive-upload-file':
                $user_can_upload = User::can_upload();

                if (is_wp_error($authorized) || false === $user_can_upload) {
                    exit;
                }

                $upload_processor = new Upload();

                switch ($_REQUEST['type']) {
                    case 'upload-preprocess':
                        $upload_processor->upload_pre_process();

                        break;

                    case 'get-status':
                        $upload_processor->get_upload_status();

                        break;

                    case 'get-direct-url':
                        $upload_processor->do_upload_direct();

                        break;

                    case 'upload-convert':
                        $upload_processor->upload_convert();

                        break;

                    case 'upload-postprocess':
                        $upload_processor->upload_post_process();

                        break;

                    default:
                        break;
                }

                exit;

            case 'useyourdrive-delete-entries':
                // Check if user is allowed to delete entry
                $user_can_delete = User::can_delete_files() || User::can_delete_folders();

                if (is_wp_error($authorized) || false === $user_can_delete) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to delete file.', 'wpcloudplugins')]);

                    exit;
                }

                $entries_to_delete = [];
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $entries_to_delete[] = $requested_id;
                }

                $entries = Client::instance()->delete_entries($entries_to_delete);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry)) {
                        echo json_encode(['result' => '-1', 'msg' => esc_html__('Not all files could be deleted.', 'wpcloudplugins')]);

                        exit;
                    }
                }
                echo json_encode(['result' => '1', 'msg' => esc_html__('File was deleted.', 'wpcloudplugins')]);

                exit;

            case 'useyourdrive-rename-entry':
                // Check if user is allowed to rename entry
                $user_can_rename = User::can_rename_files() || User::can_rename_folders();

                if (false === $user_can_rename) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to rename file.', 'wpcloudplugins')]);

                    exit;
                }

                // Strip unsafe characters
                $newname = stripslashes(rawurldecode($_REQUEST['newname']));
                $new_filename = Helpers::filter_filename($newname, false);

                $file = Client::instance()->rename_entry($new_filename);

                if (is_wp_error($file)) {
                    echo json_encode(['result' => '-1', 'msg' => $file->get_error_message()]);
                } else {
                    echo json_encode(['result' => '1', 'msg' => esc_html__('File was renamed.', 'wpcloudplugins')]);
                }

                exit;

            case 'useyourdrive-copy-entries':
                // Check if user is allowed to rename entry
                $user_can_copy = User::can_copy_files() || User::can_copy_folders();

                if (false === $user_can_copy) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to copy file.', 'wpcloudplugins')]);

                    exit;
                }

                $entries_to_move = [];
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $entries_to_move[] = $requested_id;
                }

                $entries = Client::instance()->move_entries($entries_to_move, $_REQUEST['target'], true);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry) || empty($entry)) {
                        echo json_encode(['result' => '-1', 'msg' => esc_html__('Not all files could be moved.', 'wpcloudplugins')]);

                        exit;
                    }
                }
                echo json_encode(['result' => '1', 'msg' => esc_html__('Successfully moved to new location.', 'wpcloudplugins')]);

                exit;

            case 'useyourdrive-move-entries':
                // Check if user is allowed to move entry
                $user_can_move = User::can_move_files() || User::can_move_folders();

                if (false === $user_can_move) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to move file.', 'wpcloudplugins')]);

                    exit;
                }

                $entries_to_move = [];
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $entries_to_move[] = $requested_id;
                }

                $entries = Client::instance()->move_entries($entries_to_move, $_REQUEST['target']);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry) || empty($entry)) {
                        echo json_encode(['result' => '-1', 'msg' => esc_html__('Not all files could be moved.', 'wpcloudplugins')]);

                        exit;
                    }
                }
                echo json_encode(['result' => '1', 'msg' => esc_html__('Successfully moved to new location.', 'wpcloudplugins')]);

                exit;

            case 'useyourdrive-create-shortcuts':
                $user_can_create_shortcut = User::can_create_shortcuts_files() || User::can_create_shortcuts_folder();

                if (false === $user_can_create_shortcut) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to create shortcut', 'wpcloudplugins')]);

                    exit;
                }

                $shortcuts_to_create = [];
                foreach ($_REQUEST['entries'] as $requested_id) {
                    $shortcuts_to_create[] = $requested_id;
                }

                $entries = Client::instance()->create_shortcuts($shortcuts_to_create, $_REQUEST['target']);

                foreach ($entries as $entry) {
                    if (is_wp_error($entry) || empty($entry)) {
                        echo json_encode(['result' => '-1', 'msg' => esc_html__('Not all shortcuts could be created', 'wpcloudplugins')]);

                        exit;
                    }
                }
                echo json_encode(['result' => '1', 'msg' => esc_html__('Successfully added shortcut in new location', 'wpcloudplugins')]);

                exit;

            case 'useyourdrive-edit-description-entry':
                // Check if user is allowed to rename entry
                $user_can_editdescription = User::can_edit_description();

                if (false === $user_can_editdescription) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to edit description.', 'wpcloudplugins')]);

                    exit;
                }

                $newdescription = sanitize_textarea_field(wp_unslash($_REQUEST['newdescription']));
                $result = Client::instance()->update_description($newdescription);

                if (is_wp_error($result)) {
                    echo json_encode(['result' => '-1', 'msg' => $result->get_error_message()]);
                } else {
                    echo json_encode(['result' => '1', 'msg' => esc_html__('Description was edited.', 'wpcloudplugins'), 'description' => $result]);
                }

                exit;

            case 'useyourdrive-create-entry':
                // Strip unsafe characters
                $_name = stripslashes(rawurldecode($_REQUEST['name']));
                $new_name = Helpers::filter_filename($_name, false);
                $mimetype = $_REQUEST['mimetype'];

                // Check if user is allowed
                $user_can_create_entry = ('application/vnd.google-apps.folder' === $mimetype) ? User::can_add_folders() : User::can_create_document();

                if (false === $user_can_create_entry) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to add file.', 'wpcloudplugins')]);

                    exit;
                }

                $file = Client::instance()->add_entry($new_name, $mimetype);

                if (is_wp_error($file)) {
                    echo json_encode(['result' => '-1', 'msg' => $file->get_error_message()]);
                } else {
                    $message = htmlspecialchars($file->get_name().' '.esc_html__('was added', 'wpcloudplugins'));
                    echo json_encode(['result' => '1', 'msg' => $message]);
                }

                exit;

            case 'useyourdrive-proofing':
                Proofing::instance()->process_ajax_request();

                exit;

            case 'useyourdrive-get-playlist':
                $mediaplayer = new Modules\Mediaplayer();
                $mediaplayer->get_media_list();

                break;

            case 'useyourdrive-stream':
                Client::instance()->stream_entry();

                break;

            case 'useyourdrive-shorten-url':
                if (false === User::can_deeplink()) {
                    exit;
                }

                $cached_node = Client::instance()->get_entry();
                $url = esc_url_raw($_REQUEST['url']);

                $shortened_url = API::shorten_url($url, null, ['name' => $cached_node->get_name()]);

                $data = [
                    'id' => $cached_node->get_id(),
                    'name' => $cached_node->get_name(),
                    'url' => $shortened_url,
                ];

                echo json_encode($data);

                exit;

            case 'useyourdrive-event-log':
                return;

            case 'useyourdrive-getads':
                $ads_url = ('' !== $this->get_shortcode_option('ads_tag_url') ? htmlspecialchars_decode($this->get_shortcode_option('ads_tag_url')) : Settings::get('mediaplayer_ads_tagurl'));
                $ads_id = md5($ads_url);

                $response_body = get_transient("wpcp-ads-{$ads_id}");

                if (false === $response_body) {
                    $options = [
                        'headers' => [
                            'user-agent' => 'WPCP '.USEYOURDRIVE_VERSION,
                        ],
                    ];

                    $response = wp_remote_get($ads_url, $options);
                    if (!empty($response) && !\is_wp_error($response)) {
                        $response_body = wp_remote_retrieve_body($response);
                        set_transient("wpcp-ads-{$ads_id}", $response_body, MINUTE_IN_SECONDS);
                    }
                }

                header('Content-Type: text/xml; charset=UTF-8');
                echo $response_body;

                exit;

            case 'useyourdrive-module-login':
                $password = $_POST['module_password'] ?? '';

                if (Restrictions::instance()->unlock_module(sanitize_text_field($password))) {
                    wp_send_json_success(Processor::instance()->get_shortcode_option('password_hash'));
                } else {
                    wp_send_json_error();
                }

                exit;

            case 'useyourdrive-module-lead':
                $email = sanitize_email($_POST['email'] ?? '');

                if (LeadCapture::instance()->unlock_module(sanitize_text_field($email))) {
                    wp_send_json_success();
                } else {
                    wp_send_json_error();
                }

                exit;

            case 'useyourdrive-import-entries':
                // Check if user is allowed to import
                $user_can_import = User::can_import();

                if (false === $user_can_import) {
                    echo json_encode(['result' => '-1', 'msg' => esc_html__('Failed to import file.', 'wpcloudplugins')]);

                    exit;
                }

                foreach ($_REQUEST['entries'] as $requested_id) {
                    $node = Client::instance()->get_entry($requested_id);

                    if (!empty($node)) {
                        Import::instance()->add_to_media_library($node);
                    }
                }

                exit;

            default:
                Helpers::log_error('Invalid AJAX request received.', 'Processor', ['action' => sanitize_key($requested_action)], __LINE__);

                exit('Use-your-Drive: '.esc_html__('Invalid AJAX request received.', 'wpcloudplugins'));
        }

        exit;
    }

    public function create_from_shortcode($atts)
    {
        $atts = (is_string($atts)) ? [] : $atts;
        $atts = $this->remove_deprecated_options($atts);

        $defaults = [
            'id' => '',
            'singleaccount' => '1',
            'account' => false,
            'startaccount' => false,
            'dir' => false,
            'items' => '',
            'subfolder' => false,
            'class' => '',
            'module_id' => '',
            'startid' => false,
            'mode' => 'files',
            'userfolders' => 'off',
            'usertemplatedir' => '',
            'viewuserfoldersrole' => 'administrator',
            'userfoldernametemplate' => '',
            'showfiles' => '1',
            'maxfiles' => '-1',
            'showfolders' => '1',
            'filesize' => '1',
            'filedate' => '1',
            'fileinfo_on_hover' => '0',
            'hoverthumbs' => '1',
            'filelayout' => 'grid',
            'allow_switch_view' => '1',
            'showext' => '1',
            'sortfield' => 'name',
            'sortorder' => 'asc',
            'show_tree' => '0',
            'show_header' => '1',
            'showbreadcrumb' => '1',
            'candownloadzip' => '0',
            'candownloadfolder_as_zip' => '1',
            'canpopout' => '0',
            'lightbox_imagesource' => 'default',
            'lightboxnavigation' => '1',
            'lightboxthumbs' => '1',
            'showsharelink' => '0',
            'showrefreshbutton' => '1',
            'use_custom_roottext' => '1',
            'roottext' => esc_html__('Start', 'wpcloudplugins'),
            'search' => '1',
            'searchrole' => 'all',
            'searchcontents' => '0',
            'searchfrom' => 'parent',
            'searchterm' => '',
            'include' => '*',
            'includeext' => '*',
            'exclude' => '*',
            'excludeext' => '*',
            'maxwidth' => '100%',
            'maxheight' => '',
            'scrolltotop' => '1',
            'viewrole' => 'administrator|editor|author|contributor|subscriber|guest',
            'display_loginscreen' => '0',
            'password' => '',
            'requires_lead' => '0',
            'onclick' => 'download',
            'previewrole' => 'all',
            'downloadrole' => 'administrator|editor|author|contributor|subscriber|guest',
            'sharerole' => 'all',
            'edit' => '0',
            'editrole' => 'administrator|editor|author',
            'edittype' => 'minimal',
            'previewinline' => '1',
            'previewnewtab' => '1',
            'lightbox_open' => '0',
            'slideshow' => '0',
            'pausetime' => '5000',
            'showfilenames' => '0',
            'show_descriptions' => '0',
            'showdescriptionsontop' => '0',
            'targetheight' => '300',
            'mediaskin' => '',
            'mediabuttons' => 'prevtrack|playpause|nexttrack|volume|current|duration|fullscreen',
            'media_ratio' => '16:9',
            'autoplay' => '0',
            'showplaylist' => '1',
            'showplaylistonstart' => '1',
            'playlistinline' => '0',
            'playlistautoplay' => '1',
            'playlistloop' => '0',
            'playlistthumbnails' => '1',
            'playlist_search' => '0',
            'linktoshop' => '',
            'ads' => '0',
            'ads_tag_url' => '',
            'ads_skipable' => '1',
            'ads_skipable_after' => '',
            'axis' => 'horizontal',
            'padding' => '',
            'border_radius' => '',
            'description_position' => 'hover',
            'navigation_dots' => '1',
            'navigation_arrows' => '1',
            'slide_items' => '3',
            'slide_height' => '300px',
            'slide_by' => '1',
            'slide_speed' => '300',
            'slide_center' => '0',
            'slide_auto_size' => '0',
            'carousel_autoplay' => '1',
            'pausetime' => '5000',
            'hoverpause' => '0',
            'direction' => 'forward',
            'notificationupload' => '0',
            'notificationdownload' => '0',
            'notificationdeletion' => '0',
            'notificationmove' => '0',
            'notificationcopy' => '0',
            'notificationemail' => '%admin_email%',
            'notification_skipemailcurrentuser' => '0',
            'notification_from_name' => '',
            'notification_from_email' => '',
            'notification_replyto_email' => '',
            'proofing_use_labels' => '',
            'proofing_max_items' => '0',
            'proofing_labels' => '',
            'upload' => '0',
            'upload_folder' => '1',
            'upload_auto_start' => '1',
            'upload_filename' => '%file_name%%file_extension%',
            'upload_create_shared_link' => '0',
            'upload_create_shared_link_folder' => '0',
            'upload_button_text' => '',
            'upload_button_text_plural' => '',
            'uploadext' => '.',
            'uploadrole' => 'administrator|editor|author|contributor|subscriber',
            'upload_keep_filedate' => '0',
            'minfilesize' => '0',
            'maxfilesize' => '0',
            'maxnumberofuploads' => '-1',
            'convert' => '0',
            'convertformats' => 'all',
            'overwrite' => '0',
            'delete' => '0',
            'deletefilesrole' => 'administrator|editor',
            'deletefoldersrole' => 'administrator|editor',
            'deletetotrash' => '1',
            'rename' => '0',
            'renamefilesrole' => 'administrator|editor',
            'renamefoldersrole' => 'administrator|editor',
            'move' => '0',
            'movefilesrole' => 'administrator|editor',
            'movefoldersrole' => 'administrator|editor',
            'copy' => '0',
            'copyfilesrole' => 'administrator|editor',
            'copyfoldersrole' => 'administrator|editor',
            'create_shortcuts' => '0',
            'create_shortcut_files_role' => 'administrator|editor',
            'create_shortcut_folders_role' => 'administrator|editor',
            'editdescription' => '0',
            'editdescriptionrole' => 'administrator|editor',
            'addfolder' => '0',
            'addfolderrole' => 'administrator|editor',
            'createdocument' => '0',
            'createdocumentrole' => 'administrator|editor',
            'import' => '0',
            'usage_period' => 'default',
            'download_limits' => '0',
            'downloads_per_user' => '',
            'downloads_per_user_per_file' => '',
            'zip_downloads_per_user' => '',
            'bandwidth_per_user' => '',
            'download_limits_excluded_roles' => '',
            'deeplink' => '0',
            'deeplinkrole' => 'all',
            'single_button' => '0',
            'single_button_label' => '',
            'embed_ratio' => '1.414:1',
            'embed_type' => 'readonly',
            'embed_direct_media' => '0',
            'popup' => '0',
            'post_id' => empty($atts['popup']) ? get_the_ID() : null,
            'wc_order_id' => null,
            'wc_product_id' => null,
            'wc_item_id' => null,
            'themestyle' => 'default',
            'demo' => '0',
        ];

        // Read shortcode & Create a unique identifier
        $shortcode = shortcode_atts($defaults, $atts, 'useyourdrive');
        $this->listtoken = md5(serialize($defaults).serialize($shortcode).USEYOURDRIVE_AUTH_KEY);
        extract($shortcode);

        $cached_shortcode = Shortcodes::instance()->get_shortcode_by_id($this->listtoken);

        if (false === $cached_shortcode) {
            switch ($mode) {
                case 'files':
                    $edittype = 'full';

                    break;

                case 'gallery':
                    $includeext = ('*' == $includeext) ? 'gif|jpg|jpeg|png|bmp|cr2|crw|raw|tif|tiff|webp|heic|dng|mp4|m4v|ogg|ogv|webmv' : $includeext;
                    $uploadext = ('.' == $uploadext) ? 'gif|jpg|jpeg|png|bmp|cr2|crw|raw|tif|tiff|webp|heic|dng|mp4|m4v|ogg|ogv|webmv' : $uploadext;

                    break;

                case 'carousel':
                    $includeext = ('*' == $includeext) ? 'gif|jpg|jpeg|png|bmp|cr2|crw|raw|tif|tiff|webp|heic|pdf' : $includeext;

                    break;

                case 'search':
                    $edittype = 'full';
                    $searchfrom = 'root';

                    break;

                case 'list':
                case 'button':
                    $candownloadzip = '1';

                    break;

                case 'proofing':
                    $allow_switch_view = '0';
                    $requires_lead = '1';

                    break;

                default:
                    break;
            }

            if (!empty($account)) {
                $singleaccount = '1';
            }

            if ('0' === $singleaccount) {
                $dir = 'drive';
                $account = false;
            }

            if (empty($account)) {
                $primary_account = Accounts::instance()->get_primary_account();
                if (null !== $primary_account) {
                    $account = $primary_account->get_id();
                }
            }

            $account_class = Accounts::instance()->get_account_by_id($account);
            if (null === $account_class || false === $account_class->get_authorization()->is_valid()) {
                Helpers::log_error('Module cannot be rendered. The requested account is not associated with the plugin.', 'Processor', ['account_id' => $account], __LINE__);

                return '&#9888; <strong>'.esc_html__('Module cannot be rendered as the requested content is not (longer) accessible. Contact the administrator to get access.', 'wpcloudplugins').'</strong>';
            }

            App::set_current_account($account_class);

            $rootfolder = API::get_root_folder();
            if (empty($rootfolder)) {
                Helpers::log_error('Module cannot be rendered. The requested folder is no longer accessible.', 'Processor', null, __LINE__);

                return '&#9888; <strong>'.esc_html__('Module cannot be rendered as the requested content is not (longer) accessible. Contact the administrator to get access.', 'wpcloudplugins').'</strong>';
            }

            $rootfolderid = $rootfolder->get_id();

            if (empty($dir)) {
                $main_folder = API::get_main_folder();
                $dir = $main_folder->get_id();
            }

            if (false !== $subfolder) {
                $subfolder = Helpers::clean_folder_path('/'.rtrim($subfolder, '/'));
            }

            // Make module read-only in case using a readonly scope
            if ('0' === $singleaccount && App::get_current_account()->is_drive_readonly()) {
                $upload = '0';
                $delete = '0';
                $rename = '0';
                $move = '0';
                $copy = '0';
                $create_shortcuts = '0';
                $editdescription = '0';
                $addfolder = '0';
                $createdocument = '0';
            }

            $convertformats = explode('|', $convertformats);

            // Explode roles
            $viewrole = explode('|', $viewrole);
            $previewrole = explode('|', $previewrole);
            $downloadrole = explode('|', $downloadrole);
            $sharerole = explode('|', $sharerole);
            $editrole = explode('|', $editrole);
            $uploadrole = explode('|', $uploadrole);
            $deletefilesrole = explode('|', $deletefilesrole);
            $deletefoldersrole = explode('|', $deletefoldersrole);
            $renamefilesrole = explode('|', $renamefilesrole);
            $renamefoldersrole = explode('|', $renamefoldersrole);
            $movefilesrole = explode('|', $movefilesrole);
            $movefoldersrole = explode('|', $movefoldersrole);
            $copyfilesrole = explode('|', $copyfilesrole);
            $copyfoldersrole = explode('|', $copyfoldersrole);
            $create_shortcut_files_role = explode('|', $create_shortcut_files_role);
            $create_shortcut_folders_role = explode('|', $create_shortcut_folders_role);
            $editdescriptionrole = explode('|', $editdescriptionrole);
            $addfolderrole = explode('|', $addfolderrole);
            $createdocumentrole = explode('|', $createdocumentrole);
            $viewuserfoldersrole = explode('|', $viewuserfoldersrole);
            $deeplinkrole = explode('|', $deeplinkrole);
            $mediabuttons = explode('|', $mediabuttons);
            $searchrole = explode('|', $searchrole);
            $download_limits_excluded_roles = explode('|', $download_limits_excluded_roles);

            // Explode items
            if (!empty($items)) {
                $items = explode('|', $items);
            }

            $this->options = [
                'id' => $id,
                'single_account' => $singleaccount,
                'account' => $account,
                'startaccount' => $startaccount,
                'root' => $dir,
                'items' => $items,
                'subfolder' => $subfolder,
                'class' => $class,
                'module_id' => $module_id,
                'base' => $rootfolderid,
                'startid' => $startid,
                'mode' => $mode,
                'userfolders' => $userfolders,
                'user_template_dir' => $usertemplatedir,
                'view_user_folders_role' => $viewuserfoldersrole,
                'user_folder_name_template' => $userfoldernametemplate,
                'mediaskin' => $mediaskin,
                'mediabuttons' => $mediabuttons,
                'media_ratio' => $media_ratio,
                'autoplay' => $autoplay,
                'showplaylist' => $showplaylist,
                'showplaylistonstart' => $showplaylistonstart,
                'playlistinline' => $playlistinline,
                'playlistautoplay' => $playlistautoplay,
                'playlistloop' => $playlistloop,
                'playlistthumbnails' => $playlistthumbnails,
                'playlist_search' => $playlist_search,
                'linktoshop' => $linktoshop,
                'ads' => $ads,
                'ads_tag_url' => $ads_tag_url,
                'ads_skipable' => $ads_skipable,
                'ads_skipable_after' => $ads_skipable_after,
                'show_files' => $showfiles,
                'show_folders' => $showfolders,
                'show_filesize' => $filesize,
                'show_filedate' => $filedate,
                'fileinfo_on_hover' => $fileinfo_on_hover,
                'hover_thumbs' => $hoverthumbs,
                'max_files' => $maxfiles,
                'filelayout' => $filelayout,
                'allow_switch_view' => $allow_switch_view,
                'show_ext' => $showext,
                'sort_field' => $sortfield,
                'sort_order' => $sortorder,
                'show_tree' => $show_tree,
                'show_header' => $show_header,
                'show_breadcrumb' => $showbreadcrumb,
                'can_download_zip' => $candownloadzip,
                'can_download_folder_as_zip' => $candownloadfolder_as_zip,
                'can_popout' => $canpopout,
                'lightbox_imagesource' => $lightbox_imagesource,
                'lightbox_navigation' => $lightboxnavigation,
                'lightbox_thumbnails' => $lightboxthumbs,
                'show_sharelink' => $showsharelink,
                'show_refreshbutton' => $showrefreshbutton,
                'use_custom_roottext' => $use_custom_roottext,
                'root_text' => $roottext,
                'search' => $search,
                'search_role' => $searchrole,
                'searchcontents' => $searchcontents,
                'searchfrom' => $searchfrom,
                'searchterm' => $searchterm,
                'include' => explode('|', htmlspecialchars_decode($include)),
                'include_ext' => explode('|', strtolower($includeext)),
                'exclude' => explode('|', htmlspecialchars_decode($exclude)),
                'exclude_ext' => explode('|', strtolower($excludeext)),
                'maxwidth' => $maxwidth,
                'maxheight' => $maxheight,
                'scrolltotop' => $scrolltotop,
                'view_role' => $viewrole,
                'display_loginscreen' => $display_loginscreen,
                'password' => $password,
                'password_hash' => empty($password) ? '' : wp_hash_password($password),
                'requires_lead' => $requires_lead,
                'onclick' => ('none' !== $previewrole) ? $onclick : 'download',
                'preview_role' => $previewrole,
                'download_role' => $downloadrole,
                'share_role' => $sharerole,
                'edit' => $edit,
                'edit_role' => $editrole,
                'edit_type' => $edittype,
                'previewinline' => ('none' === $previewrole) ? '0' : $previewinline,
                'previewnewtab' => ('none' === $previewrole) ? '0' : $previewnewtab,
                'axis' => $axis,
                'padding' => $padding,
                'border_radius' => $border_radius,
                'description_position' => $description_position,
                'navigation_dots' => $navigation_dots,
                'navigation_arrows' => $navigation_arrows,
                'slide_items' => $slide_items,
                'slide_height' => $slide_height,
                'slide_by' => $slide_by,
                'slide_speed' => $slide_speed,
                'slide_center' => $slide_center,
                'slide_auto_size' => $slide_auto_size,
                'carousel_autoplay' => $carousel_autoplay,
                'pausetime' => $pausetime,
                'hoverpause' => $hoverpause,
                'direction' => $direction,
                'notificationupload' => $notificationupload,
                'notificationdownload' => $notificationdownload,
                'notificationdeletion' => $notificationdeletion,
                'notificationmove' => $notificationmove,
                'notificationcopy' => $notificationcopy,
                'notificationemail' => $notificationemail,
                'notification_skip_email_currentuser' => $notification_skipemailcurrentuser,
                'notification_from_name' => $notification_from_name,
                'notification_from_email' => $notification_from_email,
                'notification_replyto_email' => $notification_replyto_email,
                'proofing_max_items' => $proofing_max_items,
                'proofing_use_labels' => $proofing_use_labels,
                'proofing_labels' => !empty($proofing_labels) ? explode('|', $proofing_labels) : [],
                'upload' => $upload,
                'upload_folder' => $upload_folder,
                'upload_auto_start' => $upload_auto_start,
                'upload_filename' => $upload_filename,
                'upload_create_shared_link' => $upload_create_shared_link,
                'upload_create_shared_link_folder' => $upload_create_shared_link_folder,
                'upload_button_text' => $upload_button_text,
                'upload_button_text_plural' => $upload_button_text_plural,
                'upload_ext' => strtolower($uploadext),
                'upload_role' => $uploadrole,
                'upload_keep_filedate' => $upload_keep_filedate,
                'minfilesize' => $minfilesize,
                'maxfilesize' => $maxfilesize,
                'maxnumberofuploads' => $maxnumberofuploads,
                'convert' => $convert,
                'convert_formats' => $convertformats,
                'overwrite' => $overwrite,
                'delete' => $delete,
                'delete_files_role' => $deletefilesrole,
                'delete_folders_role' => $deletefoldersrole,
                'deletetotrash' => $deletetotrash,
                'rename' => $rename,
                'rename_files_role' => $renamefilesrole,
                'rename_folders_role' => $renamefoldersrole,
                'move' => $move,
                'move_files_role' => $movefilesrole,
                'move_folders_role' => $movefoldersrole,
                'copy' => $copy,
                'copy_files_role' => $copyfilesrole,
                'copy_folders_role' => $copyfoldersrole,
                'create_shortcuts' => $create_shortcuts,
                'create_shortcut_files_role' => $create_shortcut_files_role,
                'create_shortcut_folders_role' => $create_shortcut_folders_role,
                'editdescription' => $editdescription,
                'editdescription_role' => $editdescriptionrole,
                'addfolder' => $addfolder,
                'addfolder_role' => $addfolderrole,
                'create_document' => $createdocument,
                'create_document_role' => $createdocumentrole,
                'import' => $import,
                'deeplink' => $deeplink,
                'deeplink_role' => $deeplinkrole,
                'show_filenames' => $showfilenames,
                'show_descriptions' => $show_descriptions,
                'show_descriptions_on_top' => $showdescriptionsontop,
                'targetheight' => $targetheight,
                'lightbox_open' => $lightbox_open,
                'slideshow' => $slideshow,
                'pausetime' => $pausetime,
                'usage_period' => $usage_period,
                'download_limits' => $download_limits,
                'downloads_per_user' => $downloads_per_user,
                'downloads_per_user_per_file' => $downloads_per_user_per_file,
                'zip_downloads_per_user' => $zip_downloads_per_user,
                'bandwidth_per_user' => $bandwidth_per_user,
                'download_limits_excluded_roles' => $download_limits_excluded_roles,
                'single_button' => $single_button,
                'single_button_label' => $single_button_label,
                'embed_ratio' => $embed_ratio,
                'embed_type' => $embed_type,
                'embed_direct_media' => $embed_direct_media,
                'popup' => $popup,
                'post_id' => $post_id,
                'themestyle' => $themestyle,
                'demo' => $demo,
                'expire' => strtotime('+1 weeks'),
                'listtoken' => $this->listtoken, ];

            $this->options = apply_filters('useyourdrive_shortcode_add_options', $this->options, $this, $atts);

            $this->save_shortcodes();

            $this->options = apply_filters('useyourdrive_shortcode_set_options', $this->options, $this, $atts);

            // Create userfolders if needed

            if ('auto' === $this->options['userfolders'] && ('Yes' === Settings::get('userfolder_onfirstvisit'))) {
                $allusers = [];
                $roles = $this->options['view_role'];

                foreach ($roles as $role) {
                    $users_query = new \WP_User_Query([
                        'fields' => 'all_with_meta',
                        'role' => $role,
                        'orderby' => 'display_name',
                    ]);
                    $results = $users_query->get_results();
                    if ($results) {
                        $allusers = array_merge($allusers, $results);
                    }
                }

                UserFolders::instance()->create_user_folders($allusers);
            }
        } else {
            $this->options = apply_filters('useyourdrive_shortcode_set_options', $cached_shortcode, $this, $atts);
        }

        if (empty($this->options['startaccount'])) {
            App::set_current_account_by_id($this->options['account']);
        } else {
            App::set_current_account_by_id($this->options['startaccount']);
        }

        if (null === App::get_current_account() || false === App::get_current_account()->get_authorization()->has_access_token()) {
            return '&#9888; <strong>'.esc_html__('Module cannot be rendered as the requested content is not (longer) accessible. Contact the administrator to get access.', 'wpcloudplugins').'</strong>';
        }

        // Render the module
        \ob_start();
        Module::render($this->options['mode']);

        return \ob_get_clean();
    }

    public function get_last_folder()
    {
        return $this->_lastFolder;
    }

    public function get_root_folder()
    {
        return $this->_rootFolder;
    }

    public function get_folder_path()
    {
        return $this->_folderPath;
    }

    public function get_listtoken()
    {
        return $this->listtoken;
    }

    public function sort_filelist($foldercontents)
    {
        $sort_field = $this->get_shortcode_option('sort_field') ?? 'name';
        $sort_order = $this->get_shortcode_option('sort_order') ?? 'asc';

        if (isset($_REQUEST['sort'])) {
            list($sort_field, $sort_order) = explode(':', $_REQUEST['sort']);
        }

        if (!empty($foldercontents)) {
            // Sort Filelist, folders first
            $sort = [];

            if ('shuffle' === $sort_field) {
                shuffle($foldercontents);

                return $foldercontents;
            }

            switch ($sort_field) {
                case 'size':
                    $sort_field = 'size';

                    break;

                case 'modified':
                    $sort_field = 'last_edited';

                    break;

                case 'created':
                    $sort_field = 'created_time';

                    break;

                case 'name':
                default:
                    $sort_field = 'name';

                    break;
            }

            switch ($sort_order) {
                case 'desc':
                    $sort_order = SORT_DESC;

                    break;

                case 'asc':
                default:
                    $sort_order = SORT_ASC;

                    break;
            }

            list($sort_field, $sort_order) = apply_filters('useyourdrive_sort_filelist_settings', [$sort_field, $sort_order], $foldercontents, $this);

            foreach ($foldercontents as $k => $v) {
                if ($v instanceof EntryAbstract) {
                    $sort['is_dir'][$k] = $v->is_dir();
                    $sort['sort'][$k] = strtolower($v->{'get_'.$sort_field}());
                } else {
                    $sort['is_dir'][$k] = $v['is_dir'];
                    $sort['sort'][$k] = $v[$sort_field];
                }
            }

            // Sort by dir desc and then by name asc
            array_multisort($sort['is_dir'], SORT_DESC, SORT_REGULAR, $sort['sort'], $sort_order, SORT_NATURAL | SORT_FLAG_CASE, $foldercontents, SORT_ASC);
        }

        $foldercontents = apply_filters('useyourdrive_sort_filelist', $foldercontents, $sort_field, $sort_order, $this);

        return $foldercontents;
    }

    public function send_notification_email($notification_type, $entries)
    {
        $notification = new Notification($notification_type, $entries);
        $notification->send_notification();
    }

    // Check if $entry is allowed

    public function _is_entry_authorized(CacheNode $cachedentry)
    {
        $entry = $cachedentry->get_entry();

        if (empty($entry)) {
            return false;
        }
        // Return in case a direct call is being made, and no shortcode is involved
        if (empty($this->options)) {
            return true;
        }

        // Action for custom filters
        $is_authorized_hook = apply_filters('useyourdrive_is_entry_authorized', true, $cachedentry, $this);
        if (false === $is_authorized_hook) {
            return false;
        }

        // Check if the entry is in the list of items
        $items = Processor::instance()->get_shortcode_option('items');
        if (!empty($items)) {
            foreach (Processor::instance()->get_shortcode_option('items') as $item_id) {
                if ($entry->get_id() === $item_id) {
                    return true;
                }
            }

            return false;
        }

        // Skip entry if its a file, and we dont want to show files
        if ($entry->is_file() && ('0' === $this->get_shortcode_option('show_files'))) {
            return false;
        }
        // Skip entry if its a folder, and we dont want to show folders
        if ($entry->is_dir() && ('0' === $this->get_shortcode_option('show_folders')) && ($entry->get_id() !== $this->get_requested_entry())) {
            return false;
        }

        // Only add allowed files to array
        $extension = $entry->get_extension();
        $allowed_extensions = $this->get_shortcode_option('include_ext');
        if ('*' != $allowed_extensions[0] && $entry->is_file()) {
            if (!empty($extension) && in_array(strtolower($extension), $allowed_extensions)) {
                // File has allowed extension
            } elseif (in_array(strtolower($entry->get_mimetype()), $allowed_extensions)) {
                // File has allowed mimetype
            } else {
                // File not allowed
                return false;
            }
        }

        // Hide files with extensions
        $hide_extensions = $this->get_shortcode_option('exclude_ext');
        if ($entry->is_file() && !empty($extension) && in_array(strtolower($extension), $hide_extensions) && '*' != $hide_extensions[0]) {
            return false;
        }

        // skip excluded folders and files
        $hide_entries = $this->get_shortcode_option('exclude');
        if ('*' != $hide_entries[0]) {
            $match = false;
            foreach ($hide_entries as $hide_entry) {
                if (fnmatch($hide_entry, $entry->get_name())) {
                    $match = true;

                    break; // Entry matches by expression (wildcards * , ?)
                }
                if ($hide_entry === $entry->get_id()) {
                    $match = true;

                    break; // Entry matches by ID
                }

                if (fnmatch($hide_entry, $entry->get_mimetype())) {
                    $match = true;

                    break; // Entry matches by Mimetype
                }
            }

            if (true === $match) {
                return false;
            }
        }

        // only allow included folders and files
        $include_entries = $this->get_shortcode_option('include');
        if ('*' != $include_entries[0]) {
            if (!($entry->is_dir() && ($entry->get_id() === $this->get_requested_entry() || $entry->get_id() === $this->get_root_folder()))) {
                $match = false;
                foreach ($include_entries as $include_entry) {
                    if (fnmatch($include_entry, $entry->get_name())) {
                        $match = true;

                        break; // Entry matches by expression (wildcards * , ?)
                    }
                    if ($include_entry === $entry->get_id()) {
                        $match = true;

                        break; // Entry matches by ID
                    }

                    if (fnmatch($include_entry, $entry->get_mimetype())) {
                        $match = true;

                        break; // Entry matches by Mimetype
                    }
                }

                if (false === $match) {
                    return false;
                }
            }
        }

        // Make sure that files and folders from hidden folders are not allowed
        if ('*' != $hide_entries[0]) {
            foreach ($hide_entries as $hidden_entry) {
                $cached_hidden_entry = Cache::instance()->get_node_by_name($hidden_entry);

                if (false === $cached_hidden_entry) {
                    $cached_hidden_entry = Cache::instance()->get_node_by_id($hidden_entry);
                }

                if (false !== $cached_hidden_entry && $cached_hidden_entry->is_dir()) {
                    foreach ($cached_hidden_entry->get_children() as $child) {
                        if ($child->get_id() === $cachedentry->get_id()) {
                            return false;
                        }
                    }
                }
            }
        }

        // Is entry in the selected root Folder?
        if ('Yes' === Settings::get('cloud_security_folder_check') && false === $cachedentry->is_in_folder($this->get_root_folder())) {
            return false;
        }

        return true;
    }

    public function is_filtering_entries()
    {
        if ('0' === $this->get_shortcode_option('show_files')) {
            return true;
        }

        if ('0' === $this->get_shortcode_option('show_folders')) {
            return true;
        }

        $allowed_extensions = $this->get_shortcode_option('include_ext');
        if ('*' !== $allowed_extensions[0]) {
            return true;
        }

        $hide_extensions = $this->get_shortcode_option('exclude_ext');
        if ('*' !== $hide_extensions[0]) {
            return true;
        }

        $hide_entries = $this->get_shortcode_option('exclude');
        if ('*' !== $hide_entries[0]) {
            return true;
        }
        $include_entries = $this->get_shortcode_option('include');
        if ('*' !== $include_entries[0]) {
            return true;
        }

        return false;
    }

    public function embed_image($entryid)
    {
        $cachedentry = Client::instance()->get_entry($entryid, false);

        if (false === $cachedentry) {
            return false;
        }

        if (in_array($cachedentry->get_entry()->get_extension(), ['jpg', 'jpeg', 'gif', 'png', 'webp', 'heic'])) {
            // Redirect to thumbnail itself
            header("Location: https://drive.google.com/thumbnail?id={$cachedentry->get_id()}&sz=w1920");

            exit;
        }

        return true;
    }

    public function set_requested_entry($entry_id)
    {
        return $this->_requestedEntry = $entry_id;
    }

    public function get_requested_entry()
    {
        return $this->_requestedEntry;
    }

    public function get_import_formats()
    {
        return [
            'application/x-vnd.oasis.opendocument.presentation' => 'application/vnd.google-apps.presentation',
            'text/tab-separated-values' => 'application/vnd.google-apps.spreadsheet',
            'image/jpeg' => 'application/vnd.google-apps.document',
            'image/bmp' => 'application/vnd.google-apps.document',
            'image/gif' => 'application/vnd.google-apps.document',
            'application/vnd.ms-excel.sheet.macroenabled.12' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => 'application/vnd.google-apps.document',
            'application/vnd.ms-powerpoint.presentation.macroenabled.12' => 'application/vnd.google-apps.presentation',
            'application/vnd.ms-word.template.macroenabled.12' => 'application/vnd.google-apps.document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'application/vnd.google-apps.document',
            'image/pjpeg' => 'application/vnd.google-apps.document',
            'application/vnd.google-apps.script+text/plain' => 'application/vnd.google-apps.script',
            'application/vnd.ms-excel' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.sun.xml.writer' => 'application/vnd.google-apps.document',
            'application/vnd.ms-word.document.macroenabled.12' => 'application/vnd.google-apps.document',
            'application/vnd.ms-powerpoint.slideshow.macroenabled.12' => 'application/vnd.google-apps.presentation',
            'text/rtf' => 'application/vnd.google-apps.document',
            'text/plain' => 'application/vnd.google-apps.document',
            'application/vnd.oasis.opendocument.spreadsheet' => 'application/vnd.google-apps.spreadsheet',
            'application/x-vnd.oasis.opendocument.spreadsheet' => 'application/vnd.google-apps.spreadsheet',
            'image/png' => 'application/vnd.google-apps.document',
            'application/x-vnd.oasis.opendocument.text' => 'application/vnd.google-apps.document',
            'application/msword' => 'application/vnd.google-apps.document',
            'application/pdf' => 'application/vnd.google-apps.document',
            'application/json' => 'application/vnd.google-apps.script',
            'application/x-msmetafile' => 'application/vnd.google-apps.drawing',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.ms-powerpoint' => 'application/vnd.google-apps.presentation',
            'application/vnd.ms-excel.template.macroenabled.12' => 'application/vnd.google-apps.spreadsheet',
            'image/x-bmp' => 'application/vnd.google-apps.document',
            'application/rtf' => 'application/vnd.google-apps.document',
            'application/vnd.openxmlformats-officedocument.presentationml.template' => 'application/vnd.google-apps.presentation',
            'image/x-png' => 'application/vnd.google-apps.document',
            'text/html' => 'application/vnd.google-apps.document',
            'application/vnd.oasis.opendocument.text' => 'application/vnd.google-apps.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'application/vnd.google-apps.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.google-apps.script+json' => 'application/vnd.google-apps.script',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow' => 'application/vnd.google-apps.presentation',
            'application/vnd.ms-powerpoint.template.macroenabled.12' => 'application/vnd.google-apps.presentation',
            'text/csv' => 'application/vnd.google-apps.spreadsheet',
            'application/vnd.oasis.opendocument.presentation' => 'application/vnd.google-apps.presentation',
            'image/jpg' => 'application/vnd.google-apps.document',
            'text/richtext' => 'application/vnd.google-apps.document',
        ];
    }

    public function is_mobile()
    {
        return $this->mobile;
    }

    public function get_shortcode()
    {
        return $this->options;
    }

    public function get_shortcode_option($key)
    {
        if (!isset($this->options[$key])) {
            return null;
        }

        return $this->options[$key];
    }

    public function set_shortcode($listtoken)
    {
        $cached_shortcode = Shortcodes::instance()->get_shortcode_by_id($listtoken);

        if ($cached_shortcode) {
            $this->options = $cached_shortcode;
            $this->listtoken = $listtoken;
        }

        return $this->options;
    }

    public function _set_gzip_compression()
    {
        // Compress file list if possible
        if ('Yes' === Settings::get('gzipcompression')) {
            $zlib = ('' == ini_get('zlib.output_compression') || !ini_get('zlib.output_compression')) && ('ob_gzhandler' != ini_get('output_handler'));
            if (true === $zlib && extension_loaded('zlib') && !in_array('ob_gzhandler', ob_list_handlers())) {
                ob_start('ob_gzhandler');
            }
        }
    }

    public static function reset_complete_cache($including_shortcodes = false)
    {
        if (!file_exists(USEYOURDRIVE_CACHEDIR)) {
            return false;
        }

        if (\function_exists('wp_cache_supports') && \wp_cache_supports('flush_group')) {
            \wp_cache_flush_group('wpcp-'.CORE::$slug.'-nodes');
            \wp_cache_flush_group('wpcp-'.CORE::$slug.'-limits');
            \wp_cache_flush_group('wpcp-'.CORE::$slug.'-entries');
            \wp_cache_flush_group('wpcp-'.CORE::$slug.'-other');
        }

        require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-base.php';

        require_once ABSPATH.'wp-admin/includes/class-wp-filesystem-direct.php';

        $wp_file_system = new \WP_Filesystem_Direct(false);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            if ('.htaccess' === $path->getFilename()) {
                continue;
            }

            if ('access_token' === $path->getExtension()) {
                continue;
            }

            if ('css' === $path->getExtension()) {
                continue;
            }

            if ('log' === $path->getExtension()) {
                continue;
            }

            if (false === $including_shortcodes && 'shortcodes' === $path->getExtension()) {
                continue;
            }

            if ('index' === $path->getExtension()) {
                // index files can be locked during purge request
                $fp = fopen($path->getPathname(), 'w');

                if (false === $fp) {
                    continue;
                }

                if (flock($fp, LOCK_EX)) {
                    ftruncate($fp, 0);
                    flock($fp, LOCK_UN);
                }
            }

            try {
                $wp_file_system->delete($path->getPathname(), true);
            } catch (\Exception $ex) {
                continue;
            }
        }

        return true;
    }

    public static function do_shutdown()
    {
        $error = error_get_last();

        if (null === $error) {
            return;
        }

        if (E_ERROR !== $error['type']) {
            return;
        }

        if (isset($error['file']) && false !== strpos($error['file'], USEYOURDRIVE_ROOTDIR)) {
            Helpers::log_error('The cache has been reset.', 'Cache', null, __LINE__);

            // fatal error has occured
            Cache::instance()->reset_cache();
        }
    }

    protected function remove_deprecated_options($options = [])
    {
        // Deprecated Shuffle, v1.3
        if (isset($options['shuffle'])) {
            unset($options['shuffle']);
            $options['sortfield'] = 'shuffle';
        }

        if (isset($options['user_upload_folders'])) {
            $options['userfolders'] = $options['user_upload_folders'];
            unset($options['user_upload_folders']);
        }

        // Changed Userfolders, v1.3
        if (isset($options['userfolders']) && '1' === $options['userfolders']) {
            $options['userfolders'] = 'auto';
        }

        if (isset($options['partiallastrow'])) {
            unset($options['partiallastrow']);
        }

        // Changed Rename/Delete/Move Folders & Files v1.5.2
        if (isset($options['move_role'])) {
            $options['move_files_role'] = $options['move_role'];
            $options['move_folders_role'] = $options['move_role'];
            unset($options['move_role']);
        }

        if (isset($options['rename_role'])) {
            $options['rename_files_role'] = $options['rename_role'];
            $options['rename_folders_role'] = $options['rename_role'];
            unset($options['rename_role']);
        }

        if (isset($options['delete_role'])) {
            $options['delete_files_role'] = $options['delete_role'];
            $options['delete_folders_role'] = $options['delete_role'];
            unset($options['delete_role']);
        }

        // Changed 'ext' to 'include_ext' v1.5.2
        if (isset($options['ext'])) {
            $options['include_ext'] = $options['ext'];
            unset($options['ext']);
        }

        if (isset($options['maxfiles']) && empty($options['maxfiles'])) {
            unset($options['maxfiles']);
        }

        // Convert bytes in version before 1.8 to MB
        if (isset($options['maxfilesize']) && !empty($options['maxfilesize']) && ctype_digit($options['maxfilesize'])) {
            $options['maxfilesize'] = Helpers::bytes_to_size_1024($options['maxfilesize']);
        }

        // Changed 'covers' to 'playlistthumbnails'
        if (isset($options['covers'])) {
            $options['playlistthumbnails'] = $options['covers'];
            unset($options['covers']);
        }

        // Changed default shortcode options for forms
        if (
            (isset($options['class']) && !isset($options['upload_auto_start']))
            && (
                false !== strpos($options['class'], 'cf7_upload_box')
                || false !== strpos($options['class'], 'gf_upload_box')
                || false !== strpos($options['class'], 'wpform_upload_box')
                || false !== strpos($options['class'], 'formidableforms_upload_box')
                || false !== strpos($options['class'], 'ninjaforms_upload_box')
            )
        ) {
            $options['upload_auto_start'] = '0';
        }

        // Changed forcedownload to allow_preview
        if (isset($options['forcedownload']) && '1' === $options['forcedownload']) {
            $options['allowpreview'] = '0';
            unset($options['forcedownload']);
        }

        if (isset($options['userfolders']) && '0' === $options['userfolders']) {
            $options['userfolders'] = 'off';
        }

        if (!empty($options['mode']) && in_array($options['mode'], ['video', 'audio']) && isset($options['linktomedia'])) {
            if ('0' === $options['linktomedia']) {
                $options['downloadrole'] = empty($options['downloadrole']) ? 'none' : $options['downloadrole'];
            } else {
                $options['downloadrole'] = empty($options['downloadrole']) ? 'all' : $options['downloadrole'];
            }
            unset($options['linktomedia']);
        }

        if (isset($options['allowpreview']) && '0' === $options['allowpreview']) {
            unset($options['allowpreview']);
            $options['previewrole'] = 'none';
        }

        if (isset($options['upload_filename_prefix'])) {
            $options['upload_filename'] = $options['upload_filename_prefix'].(isset($options['upload_filename']) ? $options['upload_filename'] : '%file_name%%file_extension%');
            unset($options['upload_filename_prefix']);
        }

        if (isset($options['hideplaylist'])) {
            $options['showplaylist'] = '0' === $options['hideplaylist'];
        }

        if (isset($options['mcepopup'])) {
            $options['popup'] = $options['mcepopup'];
            unset($options['mcepopup']);
        }

        if (isset($options['popup']) && 'woocommerce' === $options['popup']) {
            $options['popup'] = 'selector';
        }

        // Usage Limits now uses periods
        if (isset($options['downloads_per_user_per_day'])) {
            $options['downloads_per_user'] = $options['downloads_per_user_per_day'];
            unset($options['downloads_per_user_per_day']);
        }

        if (isset($options['zip_downloads_per_user_per_day'])) {
            $options['zip_downloads_per_user'] = $options['zip_downloads_per_user_per_day'];
            unset($options['zip_downloads_per_user_per_day']);
        }

        if (isset($options['bandwidth_per_user_per_day'])) {
            $options['bandwidth_per_user'] = $options['bandwidth_per_user_per_day'];
            unset($options['bandwidth_per_user_per_day']);
        }

        // Changed ..._thumbnail to just thumbnail)
        if (isset($options['lightbox_imagesource']) && false !== strpos($options['lightbox_imagesource'], 'thumbnail')) {
            $options['lightbox_imagesource'] = 'thumbnail';
        }

        return $options;
    }

    protected function save_shortcodes()
    {
        Shortcodes::instance()->set_shortcode($this->listtoken, $this->options);
        Shortcodes::instance()->update_cache();
    }
}
