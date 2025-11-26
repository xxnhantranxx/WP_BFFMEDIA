<?php

/*
 * Plugin Name: WP Cloud Plugins - Google Drive (Use-your-Drive)
 * Plugin URI: https://www.wpcloudplugins.com/plugins/use-your-drive-wordpress-plugin-for-google-drive/
 * Description: Say hello to the most popular WordPress Google Drive plugin! Start using the Cloud even more efficiently by integrating it into your website.
 * Version: 3.4.2.1
 * Author: WP Cloud Plugins
 * Author URI: https://www.wpcloudplugins.com
 * Text Domain: wpcloudplugins
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

namespace TheLion\UseyourDrive;

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Set constants
define('USEYOURDRIVE_VERSION', '3.4.2.1');
define('USEYOURDRIVE_ROOTPATH', plugins_url('', __FILE__));
define('USEYOURDRIVE_ROOTDIR', __DIR__);
define('USEYOURDRIVE_SLUG', plugin_basename(__FILE__));
define('USEYOURDRIVE_ADMIN_URL', admin_url('admin-ajax.php'));

if (!defined('USEYOURDRIVE_CACHE_SITE_FOLDERS')) {
    define('USEYOURDRIVE_CACHE_SITE_FOLDERS', false);
}

if (!defined('USEYOURDRIVE_CACHEDIR')) {
    define('USEYOURDRIVE_CACHEDIR', WP_CONTENT_DIR.'/use-your-drive-cache/'.(USEYOURDRIVE_CACHE_SITE_FOLDERS ? get_current_blog_id().'/' : ''));
}
if (!defined('USEYOURDRIVE_CACHEURL')) {
    define('USEYOURDRIVE_CACHEURL', content_url().'/use-your-drive-cache/'.(USEYOURDRIVE_CACHE_SITE_FOLDERS ? get_current_blog_id().'/' : ''));
}

require_once USEYOURDRIVE_ROOTDIR . '/includes/Autoload.php';

class Core
{
    public static $slug = 'useyourdrive';

    /**
     * WP Cloud Plugins plugin ID.
     */
    public static $plugin_id = 6219776;

    /**
     * The single instance of the class.
     *
     * @var Core
     */
    protected static $_instance;

    /**
     * Construct the plugin object.
     */
    public function __construct()
    {
        add_action('init', [$this, 'init']);

        // Admin
        Admin::load();

        // License
        add_action('init', [__NAMESPACE__.'\License', 'init']);

        // Updater
        add_action('init', [__NAMESPACE__.'\Update', 'init']);

        // Modules
        Modules::instance();

        // Shortcodes
        add_shortcode('useyourdrive', [__NAMESPACE__.'\Shortcodes', 'do_shortcode']);

        // Check if plugin requirements are met.
        // Do this after the Shortcode hook to make sure that the raw shortcode
        // Will not become visible when plugin isn't meeting the requirements
        if (false === $this->can_run_plugin()) {
            return false;
        }

        // Enqueue Scripts and Styles
        add_action('wp_enqueue_scripts', [$this, 'load_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'load_styles']);

        // Integrations
        add_action('init', [__NAMESPACE__.'\Integrations', 'init'], 9);
        // Divi and WooCommerce hooks are triggered before init, and we need to make sure that the plugin is loaded before that
        add_action('plugins_loaded', [__NAMESPACE__.'\Integrations', 'init'], 9);

        // AJAX requests
        add_action('init', [__NAMESPACE__.'\AjaxRequest', 'instance']);

        // User Folder / Dynamic Folders
        add_action('user_register', [__NAMESPACE__.'\UserFolders', 'user_register']);
        add_action('profile_update', [__NAMESPACE__.'\UserFolders', 'user_profile_update'], 100, 2);
        add_action('delete_user', [__NAMESPACE__.'\UserFolders', 'user_delete']);
        add_filter('wp_pre_insert_user_data', [__NAMESPACE__.'\UserFolders', 'store_custom_user_metadata'], 10, 4);

        // Hook to send notification emails when authorization is lost
        add_action('useyourdrive_lost_authorisation_notification', [$this, 'send_lost_authorisation_notification'], 10, 1);

        // Cron action to reset the cache
        add_action('useyourdrive_reset_cache', [__NAMESPACE__.'\Processor', 'reset_complete_cache']);

        // Cron action to restore permissions
        add_action('useyourdrive_restore_permissions', [__NAMESPACE__.'\Client', 'restore_sharing_permissions']);

        // Compatibility function for manually linked folders for older versions
        add_filter('get_user_option_use_your_drive_linkedto', [__NAMESPACE__.'\UserFolders', 'convert_old_manually_linked_folders_value'], 10, 1);
        add_filter('site_option_use_your_drive_guestlinkedto', [__NAMESPACE__.'\UserFolders', 'convert_old_manually_linked_folders_value'], 10, 1);
    }

    /**
     * Core WP Cloud Plugin Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return Core - Core instance
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

    public function init()
    {
        // Localize
        $i18n_dir = dirname(plugin_basename(__FILE__)).'/languages/';
        load_plugin_textdomain('wpcloudplugins', false, $i18n_dir);

        // Cron Jobs
        $cron = wp_next_scheduled('useyourdrive_reset_cache');
        if (false === $cron) {
            wp_schedule_event(time(), 'daily', 'useyourdrive_reset_cache');
        }

        $cron_restore_permissions = wp_next_scheduled('useyourdrive_restore_permissions');
        if (false === $cron_restore_permissions) {
            wp_schedule_event(time(), 'hourly', 'useyourdrive_restore_permissions');
        }

        // Load Event hooks
        Events::instance();
    }

    /**
     * Check to see if the requirements for the plugin are met.
     *
     * @return bool - requirements are met
     */
    public static function can_run_plugin()
    {
        // Check minimum PHP version
        if (version_compare(PHP_VERSION, '7.4') < 0) {
            return false;
        }

        // Check if cURL is present and its functions can be used

        $disabled_php_functions = explode(',', ini_get('disable_functions'));
        if (!function_exists('curl_init') || in_array('curl_init', $disabled_php_functions)) {
            Helpers::log_error('cURL (curl_init) is not available', 'Plugin Requirements');

            return false;
        }

        if (!function_exists('curl_exec') || in_array('curl_exec', $disabled_php_functions)) {
            Helpers::log_error('cURL (curl_exec) is not available', 'Plugin Requirements');

            return false;
        }

        // Check Cache Folder
        if (!file_exists(USEYOURDRIVE_CACHEDIR)) {
            @mkdir(USEYOURDRIVE_CACHEDIR, 0700);
        }

        if (!is_writable(USEYOURDRIVE_CACHEDIR)) {
            @chmod(USEYOURDRIVE_CACHEDIR, 0700);

            if (!is_writable(USEYOURDRIVE_CACHEDIR)) {
                Helpers::log_error(sprintf('Cache %s folder is not writable', USEYOURDRIVE_CACHEDIR), 'Plugin Requirements');

                return false;
            }
        }

        // Add .htaccess file for Apache servers for extra security
        // Skip checks for WP Engine
        global $is_apache;
        if ($is_apache && !file_exists(USEYOURDRIVE_CACHEDIR.'/.htaccess') && !function_exists('is_wpe')) {
            return copy(USEYOURDRIVE_ROOTDIR.'/cache/.htaccess', USEYOURDRIVE_CACHEDIR.'/.htaccess');
        }

        return true;
    }

    public static function is_network_authorized()
    {
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH.'/wp-admin/includes/plugin.php';
        }

        $network_settings = get_site_option(Settings::$db_network_key, []);

        return isset($network_settings['network_wide']) && is_plugin_active_for_network(USEYOURDRIVE_SLUG) && ('Yes' === $network_settings['network_wide']);
    }

    public function load_scripts()
    {
        if (defined('USEYOURDRIVE_SCRIPTS_LOADED')) {
            return;
        }

        $version = USEYOURDRIVE_VERSION;

        if (!is_user_logged_in() && '' !== Settings::get('recaptcha_sitekey')) {
            $url = add_query_arg(
                [
                    'render' => Settings::get('recaptcha_sitekey'),
                ],
                'https://www.google.com/recaptcha/api.js'
            );

            wp_register_script('google-recaptcha', $url, [], '3.0', true);
        }

        if ('Yes' === Settings::get('polyfill')) {
            wp_register_script('WPCloudPlugins.Polyfill', 'https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?features=es6,html5-elements,NodeList.prototype.forEach,Element.prototype.classList,CustomEvent,Object.entries,Object.assign,document.querySelector,URL&flags=gated', false, true);
        } else {
            wp_register_script('WPCloudPlugins.Polyfill', false);
        }

        // load in footer
        wp_register_script('jQuery.iframe-transport', plugins_url('vendors/jquery-file-upload/js/jquery.iframe-transport.js', __FILE__), ['jquery', 'jquery-ui-widget'], false, true);
        wp_register_script('jQuery.fileupload-uyd', plugins_url('vendors/jquery-file-upload/js/jquery.fileupload.js', __FILE__), ['jquery', 'jquery-ui-widget'], false, true);
        wp_register_script('jQuery.fileupload-process', plugins_url('vendors/jquery-file-upload/js/jquery.fileupload-process.js', __FILE__), ['jquery', 'jquery-ui-widget'], false, true);
        wp_register_script('UseyourDrive.UploadBox', plugins_url('includes/js/UploadBox.min.js?v='.$version, __FILE__), ['jQuery.iframe-transport', 'jQuery.fileupload-uyd', 'jQuery.fileupload-process', 'jquery', 'jquery-ui-widget', 'WPCloudPlugins.Libraries'], $version, true);

        wp_register_script('UseyourDrive.Carousel', plugins_url('includes/js/Carousel.min.js?v='.$version, __FILE__), ['imagesloaded', 'jquery', 'jquery-ui-widget', 'UseyourDrive'], $version, true);

        wp_register_script('UseyourDrive.Proofing', plugins_url('includes/js/Proofing.min.js?v='.$version, __FILE__), ['underscore', 'imagesloaded', 'jquery', 'jquery-ui-widget', 'UseyourDrive'], $version, true);
        wp_register_script('UseyourDrive.ProofingDashboard', plugins_url('includes/js/ProofingDashboard.min.js?v='.$version, __FILE__), ['underscore', 'jquery', 'jquery-ui-selectable', 'UseyourDrive.AdminUI'], $version, true);

        wp_register_script('WPCloudPlugins.Libraries', plugins_url('vendors/library.min.js?v='.$version, __FILE__), ['WPCloudPlugins.Polyfill', 'jquery'], $version, true);
        wp_register_script('Tagify', plugins_url('vendors/tagify/tagify.min.js', __FILE__), ['WPCloudPlugins.Polyfill'], $version, true);
        wp_register_script('UseyourDrive', plugins_url('includes/js/Main.min.js?v='.$version, __FILE__), ['jquery', 'jquery-ui-widget', 'WPCloudPlugins.Libraries'], $version, true);

        // Scripts for the Admin Dashboard
        wp_register_script('UseyourDrive.AdminUI', plugins_url('includes/js/AdminUI.min.js', __FILE__), ['jquery', 'jquery-effects-fade', 'jquery-ui-widget', 'Tagify', 'WPCloudPlugins.Libraries'], $version, true);

        // -Settings
        wp_register_script('WPCloudPlugins.ColorPicker', USEYOURDRIVE_ROOTPATH.'/vendors/wp-color-picker-alpha/wp-color-picker-alpha.min.js', ['wp-color-picker'], '3.0.0', true);
        wp_register_script('UseyourDrive.AdminSettings', plugins_url('includes/js/Admin.min.js', __FILE__), ['UseyourDrive', 'WPCloudPlugins.ColorPicker', 'UseyourDrive.AdminUI'], $version, true);

        // -Dashboard
        wp_register_script('Flatpickr', plugins_url('vendors/flatpickr/flatpickr.min.js', __FILE__), [], $version, true);
        wp_register_script('WPCloudPlugins.Datatables', plugins_url('vendors/datatables/datatables.min.js', __FILE__), ['jquery'], $version, true);
        wp_register_script('WPCloudPlugins.ChartJs', plugins_url('vendors/chartjs/chartjs.min.js', __FILE__), ['jquery'], $version, true);
        wp_register_script('UseyourDrive.Dashboard', plugins_url('includes/js/Dashboard.min.js', __FILE__), ['Flatpickr', 'WPCloudPlugins.Datatables', 'WPCloudPlugins.ChartJs', 'UseyourDrive.AdminUI'], $version, true);

        // -Module Configurator
        wp_register_script('UseyourDrive.DocumentEmbedder', plugins_url('includes/js/DocumentEmbedder.min.js', __FILE__), ['UseyourDrive.AdminUI'], $version, true);
        wp_register_script('UseyourDrive.DocumentLinker', plugins_url('includes/js/DocumentLinker.min.js', __FILE__), ['UseyourDrive.AdminUI'], $version, true);
        wp_register_script('UseyourDrive.ShortcodeBuilder', plugins_url('includes/js/ShortcodeBuilder.min.js', __FILE__), ['UseyourDrive.AdminUI'], $version, true);

        // -Link Personal Folders
        wp_register_script('UseyourDrive.PrivateFolders', plugins_url('includes/js/LinkUsers.min.js', __FILE__), ['UseyourDrive.AdminUI', 'UseyourDrive'], $version, true);

        $post_max_size_bytes = min(Helpers::return_bytes(ini_get('post_max_size')), Helpers::return_bytes(ini_get('upload_max_filesize')));

        $localize = [
            'plugin_ver' => USEYOURDRIVE_VERSION,
            'plugin_url' => plugins_url('', __FILE__),
            'ajax_url' => USEYOURDRIVE_ADMIN_URL,
            'cookie_path' => COOKIEPATH,
            'cookie_domain' => COOKIE_DOMAIN,
            'uuid' => (!isset($_COOKIE['WPCP_UUID'])) ? wp_generate_uuid4() : \esc_js($_COOKIE['WPCP_UUID']),
            'is_mobile' => wp_is_mobile(),
            'is_rtl' => is_rtl(),
            'recaptcha' => is_user_logged_in() || (isset($_REQUEST['elementor-preview'])) ? '' : Settings::get('recaptcha_sitekey'),
            'shortlinks' => 'None' === Settings::get('shortlinks') ? false : Settings::get('shortlinks'),
            'remember_last_location' => 'Yes' === Settings::get('remember_last_location'),
            'content_skin' => Settings::get('colors[style]'),
            'icons_set' => Settings::get('icon_set'),
            'lightbox_skin' => Settings::get('lightbox_skin'),
            'lightbox_path' => Settings::get('lightbox_path'),
            'lightbox_rightclick' => Settings::get('lightbox_rightclick'),
            'lightbox_showheader' => Settings::get('lightbox_showheader'),
            'lightbox_showcaption' => Settings::get('lightbox_showcaption'),
            'lightbox_thumbnailbar' => Settings::get('lightbox_thumbnailbar'),
            'post_max_size' => $post_max_size_bytes,
            'google_analytics' => (('Yes' === Settings::get('google_analytics')) ? 1 : 0),
            'log_events' => (('Yes' === Settings::get('log_events')) ? 1 : 0),
            'share_buttons' => array_keys(array_filter(Settings::get('share_buttons'), function ($value) {return 'enabled' === $value; })),
            'refresh_nonce' => wp_create_nonce('useyourdrive-get-filelist'),
            'gallery_nonce' => wp_create_nonce('useyourdrive-get-gallery'),
            'proofing_nonce' => wp_create_nonce('useyourdrive-proofing'),
            'getplaylist_nonce' => wp_create_nonce('useyourdrive-get-playlist'),
            'upload_nonce' => wp_create_nonce('useyourdrive-upload-file'),
            'delete_nonce' => wp_create_nonce('useyourdrive-delete-entries'),
            'rename_nonce' => wp_create_nonce('useyourdrive-rename-entry'),
            'copy_nonce' => wp_create_nonce('useyourdrive-copy-entries'),
            'move_nonce' => wp_create_nonce('useyourdrive-move-entries'),
            'shortcut_nonce' => wp_create_nonce('useyourdrive-create-shortcuts'),
            'import_nonce' => wp_create_nonce('useyourdrive-import-entries'),
            'log_nonce' => wp_create_nonce('useyourdrive-event-log'),
            'description_nonce' => wp_create_nonce('useyourdrive-edit-description-entry'),
            'createentry_nonce' => wp_create_nonce('useyourdrive-create-entry'),
            'getplaylist_nonce' => wp_create_nonce('useyourdrive-get-playlist'),
            'shortenurl_nonce' => wp_create_nonce('useyourdrive-shorten-url'),
            'createzip_nonce' => wp_create_nonce('useyourdrive-create-zip'),
            'createlink_nonce' => wp_create_nonce('useyourdrive-create-link'),
            'recaptcha_nonce' => wp_create_nonce('useyourdrive-check-recaptcha'),
            'login_nonce' => wp_create_nonce('useyourdrive-module-login'),
            'lead_nonce' => wp_create_nonce('useyourdrive-module-lead'),
            'str_loading' => esc_html__('Hang on. Waiting for the files...', 'wpcloudplugins'),
            'str_processing' => esc_html__('Processing...', 'wpcloudplugins'),
            'str_success' => esc_html__('Success', 'wpcloudplugins'),
            'str_error' => esc_html__('Error', 'wpcloudplugins'),
            'str_inqueue' => esc_html__('Waiting', 'wpcloudplugins'),
            'str_upload' => esc_html__('Upload', 'wpcloudplugins'),
            'str_uploading_start' => esc_html__('Start upload', 'wpcloudplugins'),
            'str_uploading_no_limit' => esc_html__('Unlimited', 'wpcloudplugins'),
            'str_uploading' => esc_html__('Uploading...', 'wpcloudplugins'),
            'str_uploading_failed' => esc_html__('File not uploaded successfully', 'wpcloudplugins'),
            'str_uploading_failed_msg' => esc_html__('The following file(s) are not uploaded succesfully:', 'wpcloudplugins'),
            'str_uploading_failed_in_form' => esc_html__('The form cannot be submitted. Please remove all files that are not successfully attached.', 'wpcloudplugins'),
            'str_uploading_cancelled' => esc_html__('Upload is cancelled', 'wpcloudplugins'),
            'str_uploading_convert' => esc_html__('Converting', 'wpcloudplugins'),
            'str_uploading_convert_failed' => esc_html__('Converting failed', 'wpcloudplugins'),
            'str_uploading_required_data' => esc_html__('Please fill in the required fields first', 'wpcloudplugins'),
            'str_error_title' => esc_html__('Error', 'wpcloudplugins'),
            'str_close_title' => esc_html__('Close', 'wpcloudplugins'),
            'str_start_title' => esc_html__('Start', 'wpcloudplugins'),
            'str_download_title' => esc_html__('Download', 'wpcloudplugins'),
            'str_import_title' => esc_html__('Import to Media Library', 'wpcloudplugins'),
            'str_import' => esc_html__('Import', 'wpcloudplugins'),
            'str_cancel_title' => esc_html__('Cancel', 'wpcloudplugins'),
            'str_delete_title' => esc_html__('Delete', 'wpcloudplugins'),
            'str_move_title' => esc_html__('Move', 'wpcloudplugins'),
            'str_shortcut_title' => esc_html__('Add shortcut', 'wpcloudplugins'),
            'str_copy_title' => esc_html__('Copy', 'wpcloudplugins'),
            'str_save_title' => esc_html__('Save', 'wpcloudplugins'),
            'str_copy_to_clipboard_title' => esc_html__('Copy to clipboard', 'wpcloudplugins'),
            'str_copied_to_clipboard' => esc_html__('Copied to clipboard!', 'wpcloudplugins'),
            'str_delete' => esc_html__('Do you really want to delete:', 'wpcloudplugins'),
            'str_delete_multiple' => esc_html__('Do you really want to delete these files?', 'wpcloudplugins'),
            'str_rename_failed' => esc_html__("That doesn't work. Are there any illegal characters (<>:\"/\\|?*) in the filename?", 'wpcloudplugins'),
            'str_rename_title' => esc_html__('Rename', 'wpcloudplugins'),
            'str_rename' => esc_html__('Rename to:', 'wpcloudplugins'),
            'str_add_description' => esc_html__('Add a description...', 'wpcloudplugins'),
            'str_edit_description' => esc_html__('Edit description', 'wpcloudplugins'),
            'str_no_filelist' => esc_html__('No content received. Try to reload this page.', 'wpcloudplugins'),
            'str_recaptcha_failed' => esc_html__("Oops! We couldn't verify that you're not a robot :(. Please try refreshing the page.", 'wpcloudplugins'),
            'str_create_title' => esc_html__('Create', 'wpcloudplugins'),
            'str_enter_name' => esc_html__('Enter a name...', 'wpcloudplugins'),
            'str_create_folder' => esc_html__('Add folder', 'wpcloudplugins'),
            'str_create_document' => esc_html__('Create document', 'wpcloudplugins'),
            'str_select_account' => esc_html__('Select Account', 'wpcloudplugins'),
            'str_zip_title' => esc_html__('Create zip file', 'wpcloudplugins'),
            'str_zip_nofiles' => esc_html__('No files found or selected', 'wpcloudplugins'),
            'str_zip_createzip' => esc_html__('Creating zip file', 'wpcloudplugins'),
            'str_zip_selected' => esc_html__('(x) selected', 'wpcloudplugins'),
            'str_share_link' => esc_html__('Share', 'wpcloudplugins'),
            'str_shareon' => esc_html__('Share on', 'wpcloudplugins'),
            'str_direct_link' => esc_html__('Create direct link', 'wpcloudplugins'),
            'str_create_shared_link' => esc_html__('Creating shared link...', 'wpcloudplugins'),
            'str_previous_title' => esc_html__('Previous', 'wpcloudplugins'),
            'str_next_title' => esc_html__('Next', 'wpcloudplugins'),
            'str_xhrError_title' => esc_html__('This content failed to load', 'wpcloudplugins'),
            'str_imgError_title' => esc_html__('This image failed to load', 'wpcloudplugins'),
            'str_startslideshow' => esc_html__('Start slideshow', 'wpcloudplugins'),
            'str_stopslideshow' => esc_html__('Stop slideshow', 'wpcloudplugins'),
            'str_nolink' => esc_html__('Not yet linked to a folder', 'wpcloudplugins'),
            'str_close_title' => esc_html__('Close', 'wpcloudplugins'),
            'str_details_title' => esc_html__('Details', 'wpcloudplugins'),
            'str_copied_to_clipboard' => esc_html__('Copied to clipboard!', 'wpcloudplugins'),
            'str_module_updated_success' => esc_html__('Module successfully updated.', 'wpcloudplugins'),
            'str_module_updated_failed' => esc_html__('The module could not be updated.', 'wpcloudplugins'),
            'str_module_added_success' => esc_html__('Successfully added module.', 'wpcloudplugins'),
            'str_module_added_failed' => esc_html__('The module could not be added.', 'wpcloudplugins'),
            'str_module_deleted_success' => esc_html__('Module successfully deleted.', 'wpcloudplugins'),
            'str_module_deleted_failed' => esc_html__('The module could not be deleted.', 'wpcloudplugins'),
            'str_files_limit' => esc_html__('Maximum number of files exceeded', 'wpcloudplugins'),
            'str_filetype_not_allowed' => esc_html__('File type not allowed', 'wpcloudplugins'),
            'str_item' => esc_html__('Item', 'wpcloudplugins'),
            'str_items' => esc_html__('Items', 'wpcloudplugins'),
            'str_search_results' => esc_html__('Results for %s', 'wpcloudplugins'),
            'str_max_file_size' => esc_html__('File is too large', 'wpcloudplugins'),
            'str_min_file_size' => esc_html__('File is too small', 'wpcloudplugins'),
            'str_iframe_loggedin' => "<div class='empty_iframe'><div class='empty_iframe_container'><div class='empty_iframe_img'></div><h1>".esc_html__('Still Waiting?', 'wpcloudplugins').'</h1><span>'.esc_html__("If the document doesn't open, you are probably trying to access a protected file which requires a login.", 'wpcloudplugins')." <strong><a href='#' target='_blank' class='empty_iframe_link'>".esc_html__('Try to open the file in a new window.', 'wpcloudplugins').'</a></strong></span></div></div>',
        ];

        $localize_dashboard = [
            'ajax_url' => USEYOURDRIVE_ADMIN_URL,
            'admin_nonce' => wp_create_nonce('useyourdrive-admin-action'),
            'content_skin' => Settings::get('colors[style]'),
            'modules_url' => USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-getpopup&type=shortcodebuilder',
        ];

        $page = isset($_GET['page']) ? '?page='.$_GET['page'] : '';
        $location = get_admin_url(null, 'admin.php'.$page);

        $localize_admin = [
            'ajax_url' => USEYOURDRIVE_ADMIN_URL,
            'activate_url' => 'https://www.wpcloudplugins.com/updates_v2/activate.php?init=1&siteurl='.License::get_home_url().'&client_url='.strtr(base64_encode($location), ' + /=', '-_~').'&plugin_id='.self::$plugin_id,
            'admin_nonce' => wp_create_nonce('useyourdrive-admin-action'),
            'is_network' => is_network_admin(),
        ];

        wp_localize_script('UseyourDrive', 'UseyourDrive_vars', $localize);
        wp_localize_script('UseyourDrive.Dashboard', 'UseyourDrive_Report_Vars', $localize_dashboard);
        wp_localize_script('UseyourDrive.AdminSettings', 'UseyourDrive_Admin_vars', $localize_admin);

        if ('Yes' === Settings::get('always_load_scripts')) {
            $mediaplayer = Modules\Mediaplayer::load_skin();

            if (!empty($mediaplayer)) {
                $mediaplayer->load_scripts();
                $mediaplayer->load_styles();
            }

            wp_enqueue_script('jquery-effects-core');
            wp_enqueue_script('jquery-effects-fade');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('UseyourDrive.UploadBox');
            wp_enqueue_script('UseyourDrive');
        }

        define('USEYOURDRIVE_SCRIPTS_LOADED', true);
    }

    public function load_styles()
    {
        if (defined('USEYOURDRIVE_STYLES_LOADED')) {
            return;
        }

        $is_rtl_css = (is_rtl() ? '-rtl' : '');

        $version = USEYOURDRIVE_VERSION;

        $skin = Settings::get('lightbox_skin');
        wp_register_style('ilightbox', plugins_url('vendors/iLightBox/css/ilightbox.css', __FILE__));
        wp_register_style('ilightbox-skin-useyourdrive', plugins_url('vendors/iLightBox/'.$skin.'-skin/skin.css', __FILE__));

        wp_register_style('Eva-Icons', plugins_url('vendors/eva-icons/eva-icons.min.css', __FILE__), false, $version);

        wp_register_style('WPCloudPlugins.Modals', plugins_url("css/modal.min{$is_rtl_css}.css", __FILE__), [], $version);
        wp_register_style('UseyourDrive', plugins_url("css/main.min{$is_rtl_css}.css", __FILE__), ['Eva-Icons', 'WPCloudPlugins.Modals'], $version);
        wp_add_inline_style('UseyourDrive', CSS::generate_inline_css());

        // Styles for the Admin Dashboard
        wp_register_style('WPCloudPlugins.AdminUI', plugins_url("css/admin.min{$is_rtl_css}.css", __FILE__), ['wp-color-picker', 'WPCloudPlugins.Datatables', 'Flatpickr', 'Eva-Icons'], $version);
        wp_register_style('WPCloudPlugins.Datatables', plugins_url('vendors/datatables/datatables.min.css', __FILE__), [], $version);
        wp_register_style('Flatpickr', plugins_url('vendors/flatpickr/flatpickr.min.css', __FILE__), [], $version);
        wp_register_style('UseyourDrive.Adminbar', plugins_url('css/admin-bar.min.css', __FILE__), [], $version);

        if ('Yes' === Settings::get('always_load_scripts')) {
            wp_enqueue_style('ilightbox');
            wp_enqueue_style('ilightbox-skin-useyourdrive');
            wp_enqueue_style('Eva-Icons');
            wp_enqueue_style('UseyourDrive');
        }

        define('USEYOURDRIVE_STYLES_LOADED', true);
    }

    public function send_lost_authorisation_notification($account_id = null)
    {
        $account = Accounts::instance()->get_account_by_id($account_id);

        // If account isn't longer present in the account list, remove it from the CRON job
        if (empty($account)) {
            if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]))) {
                wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]);
            }

            return false;
        }

        $subject = get_bloginfo().' | '.sprintf(esc_html__('ACTION REQUIRED: WP Cloud Plugin lost authorization to %s account', 'wpcloudplugins'), 'Google Drive').':'.(!empty($account) ? $account->get_email() : '');
        $colors = Settings::get('colors');

        $template = apply_filters('useyourdrive_set_lost_authorization_template', USEYOURDRIVE_ROOTDIR.'/templates/notifications/lost_authorization.php', $this);
        $recipients = Settings::get('lostauthorization_notification');

        ob_start();

        include_once $template;
        $htmlmessage = Helpers::compress_html(ob_get_clean());

        // Send mail
        try {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $recipients = array_unique(array_map('trim', explode(',', $recipients)));

            foreach ($recipients as $recipient) {
                wp_mail($recipient, $subject, $htmlmessage, $headers);
            }
        } catch (\Exception $ex) {
            Helpers::log_error(__('Could not send email'), 'Notification', null, null, $ex);
        }
    }

    /**
     * Reset plugin to factory settings.
     */
    public static function do_factory_reset()
    {
        // Remove Database settings
        delete_option('use_your_drive_settings');
        delete_site_option('useyourdrive_network_settings');
        delete_site_option('use_your_drive_guestlinkedto');
        delete_option('use_your_drive_uniqueID');

        delete_site_option('useyourdrive_purchaseid');
        delete_option('use_your_drive_activated');

        delete_option('use_your_drive_version');

        // Delete all modules
        Modules::uninstall();

        // Remove Event Log
        Events::uninstall();

        // Remove Cache Files
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
            $path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
        }

        @rmdir(USEYOURDRIVE_CACHEDIR);

        // Remove Cron Jobs
        $reset_cron_job = wp_next_scheduled('useyourdrive_reset_cache');
        if (false !== $reset_cron_job) {
            wp_unschedule_event($reset_cron_job, 'useyourdrive_reset_cache');
        }

        $reset_cron_restore_permissions = wp_next_scheduled('useyourdrive_restore_permissions');
        if (false === $reset_cron_restore_permissions) {
            wp_unschedule_event($reset_cron_restore_permissions, 'useyourdrive_restore_permissions');
        }

        Helpers::purge_cache_others();
    }
}

// Installation and uninstallation hooks
register_activation_hook(__FILE__, __NAMESPACE__.'\useyourdrive_network_activate');
register_deactivation_hook(__FILE__, __NAMESPACE__.'\useyourdrive_network_deactivate');
register_uninstall_hook(__FILE__, __NAMESPACE__.'\useyourdrive_network_uninstall');

$UseyourDrive = Core::instance();

// Core alias for backwards compatibility
class_alias('\TheLion\UseyourDrive\Core', '\TheLion\UseyourDrive\Main');

// API alias
class_alias('\TheLion\UseyourDrive\API', '\WPCP_GDRIVE_API');

/**
 * Activate the plugin on network.
 *
 * @param mixed $network_wide
 */
function useyourdrive_network_activate($network_wide)
{
    if (is_multisite() && $network_wide) { // See if being activated on the entire network or one blog
        global $wpdb;

        // Get this so we can switch back to it later
        $current_blog = $wpdb->blogid;
        // For storing the list of activated blogs
        $activated = [];

        // Get all blogs in the network and activate plugin on each one
        foreach (get_sites() as $site) {
            switch_to_blog($site->blog_id);
            useyourdrive_activate(); // The normal activation function
            $activated[] = $site->blog_id;
        }

        // Switch back to the current blog
        switch_to_blog($current_blog);

        // Store the array for a later function
        update_site_option('use_your_drive_activated', $activated);
    } else { // Running on a single blog
        useyourdrive_activate(); // The normal activation function
    }
}

/**
 * Activate the plugin.
 */
function useyourdrive_activate()
{
    Settings::add_settings_to_db();

    update_option('use_your_drive_version', USEYOURDRIVE_VERSION);

    // Set a transient to indicate activation
    set_transient('useyourdrive_admin_installation_redirect', true, 30);

    // Install Event Log
    Events::install_database();
}

/**
 * Deactivate the plugin on network.
 *
 * @param mixed $network_wide
 */
function useyourdrive_network_deactivate($network_wide)
{
    if (is_multisite() && $network_wide) { // See if being activated on the entire network or one blog
        global $wpdb;

        // Get this so we can switch back to it later
        $current_blog = $wpdb->blogid;

        // If the option does not exist, plugin was not set to be network active
        if (false === get_site_option('use_your_drive_activated')) {
            return false;
        }

        // Get all blogs in the network
        $activated = get_site_option('use_your_drive_activated'); // Array of blogs with the plugin activated

        foreach (get_sites() as $site) {
            if (!in_array($site->blog_id, $activated)) { // Plugin is not activated on that blog
                switch_to_blog($site->blog_id);
                useyourdrive_deactivate();
            }
        }

        // Switch back to the current blog
        switch_to_blog($current_blog);

        // Store the array for a later function
        update_site_option('use_your_drive_activated', $activated);
    } else { // Running on a single blog
        useyourdrive_deactivate();
    }
}

/**
 * Deactivate the plugin.
 */
function useyourdrive_deactivate()
{
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(USEYOURDRIVE_CACHEDIR, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
        if ('.htaccess' === $path->getFilename()) {
            continue;
        }

        if ('access_token' === $path->getExtension()) {
            continue;
        }

        $path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
    }

    foreach (Accounts::instance()->list_accounts() as $account_id => $account) {
        if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]))) {
            wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]);
        }
    }

    if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification'))) {
        wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification');
    }

    if (false !== ($timestamp = wp_next_scheduled('useyourdrive_reset_cache'))) {
        wp_unschedule_event($timestamp, 'useyourdrive_reset_cache');
    }

    if (false !== ($timestamp = wp_next_scheduled('useyourdrive_restore_permissions'))) {
        wp_unschedule_event($timestamp, 'useyourdrive_restore_permissions');
    }
}

function useyourdrive_network_uninstall($network_wide)
{
    if (is_multisite() && $network_wide) { // See if being activated on the entire network or one blog
        global $wpdb;

        // Get this so we can switch back to it later
        $current_blog = $wpdb->blogid;

        // If the option does not exist, plugin was not set to be network active
        if (false === get_site_option('use_your_drive_activated')) {
            return false;
        }

        // Get all blogs in the network
        $activated = get_site_option('use_your_drive_activated'); // Array of blogs with the plugin activated

        foreach (get_sites() as $site) {
            if (!in_array($site->blog_id, $activated)) { // Plugin is not activated on that blog
                switch_to_blog($site->blog_id);
                useyourdrive_uninstall();
            }
        }

        // Switch back to the current blog
        switch_to_blog($current_blog);

        delete_option('use_your_drive_activated');
        delete_site_option('useyourdrive_network_settings');
    } else { // Running on a single blog
        useyourdrive_uninstall();
    }
}

function useyourdrive_uninstall()
{
    $settings = get_option('use_your_drive_settings', []);

    if (isset($settings['uninstall_reset']) && 'Yes' === $settings['uninstall_reset']) {
        Core::do_factory_reset();
    }

    $reset_cron_job = wp_next_scheduled('useyourdrive_reset_cache');
    if (false !== $reset_cron_job) {
        wp_unschedule_event($reset_cron_job, 'useyourdrive_reset_cache');
    }

    // Remove pending notifications
    foreach (Accounts::instance()->list_accounts() as $account_id => $account) {
        if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]))) {
            wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]);
        }
    }

    if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification'))) {
        wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification');
    }

    $reset_cron_restore_permissions = wp_next_scheduled('useyourdrive_restore_permissions');
    if (false === $reset_cron_restore_permissions) {
        wp_unschedule_event($reset_cron_restore_permissions, 'useyourdrive_restore_permissions');
    }
}

// add new cron schedule to update cache every 20 minutes
if (!function_exists('wpcp_cron_schedules')) {
    function wpcp_cron_schedules($schedules)
    {
        if (!isset($schedules['wpcp_20min'])) {
            $schedules['wpcp_20min'] = [
                'interval' => 1200,
                'display' => 'Once every 20 minutes',
            ];
        }

        return $schedules;
    }

    add_filter('cron_schedules', __NAMESPACE__.'\wpcp_cron_schedules');
}