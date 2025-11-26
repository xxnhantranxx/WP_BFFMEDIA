<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.11
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

use ReCaptcha\ReCaptcha;

defined('ABSPATH') || exit;

class AjaxRequest
{
    /**
     * The single instance of the class.
     *
     * @var AjaxRequest
     */
    protected static $_instance;

    public function __construct()
    {
        $this->set_hooks();
    }

    /**
     * AjaxRequest Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return AjaxRequest - AjaxRequest instance
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

    public function set_hooks()
    {
        // Ajax calls
        add_action('wp_ajax_nopriv_useyourdrive-get-filelist', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-get-filelist', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-search', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-search', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-get-gallery', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-get-gallery', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-proofing', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-proofing', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-upload-file', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-upload-file', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-delete-entries', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-delete-entries', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-rename-entry', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-rename-entry', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-move-entries', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-move-entries', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-copy-entries', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-copy-entries', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-move-entries', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-move-entries', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-create-shortcuts', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-create-shortcuts', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-edit-description-entry', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-edit-description-entry', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-create-entry', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-create-entry', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-get-playlist', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-get-playlist', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-create-zip', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-create-zip', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-download', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-download', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-stream', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-stream', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-preview', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-preview', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-edit', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-edit', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-thumbnail', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-thumbnail', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-check-recaptcha', [$this, 'check_recaptcha']);
        add_action('wp_ajax_useyourdrive-check-recaptcha', [$this, 'check_recaptcha']);

        add_action('wp_ajax_nopriv_useyourdrive-create-link', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-create-link', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-embedded', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-shorten-url', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-shorten-url', [$this, 'do_action']);

        add_action('wp_ajax_useyourdrive-getpopup', [$this, 'get_popup']);
        add_action('wp_ajax_useyourdrive-module-preview', [$this, 'preview_shortcode']);

        add_action('wp_ajax_nopriv_useyourdrive-import-entries', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-import-entries', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-getads', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-getads', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-module-login', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-module-login', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-module-lead', [$this, 'do_action']);
        add_action('wp_ajax_useyourdrive-module-lead', [$this, 'do_action']);

        add_action('wp_ajax_nopriv_useyourdrive-embed-image', [$this, 'embed_image']);
        add_action('wp_ajax_useyourdrive-embed-image', [$this, 'embed_image']);

        add_action('wp_ajax_useyourdrive-linkusertofolder', [$this, 'user_folder_link']);
        add_action('wp_ajax_useyourdrive-unlinkusertofolder', [$this, 'user_folder_unlink']);
        add_action('wp_ajax_useyourdrive-rating-asked', [$this, 'rating_asked']);
    }

    public function do_action()
    {
        if (!isset($_REQUEST['action'])) {
            return false;
        }

        require_once ABSPATH.'wp-includes/pluggable.php';
        Processor::instance()->start_process();

        exit;
    }

    public function get_popup()
    {
        switch ($_REQUEST['type']) {
            case 'shortcodebuilder':
                ShortcodeBuilder::instance()->render();

                break;

            case 'modules':
                if (!empty($_REQUEST['module']) || !empty($_REQUEST['shortcode'])) {
                    ShortcodeBuilder::instance()->render();
                } else {
                    Modules::instance()->render();
                }

                break;

            case 'links':
                include_once USEYOURDRIVE_ROOTDIR.'/templates/admin/documents_linker.php';

                break;

            case 'embedded':
                include_once USEYOURDRIVE_ROOTDIR.'/templates/admin/documents_embedder.php';

                break;

            default:
                exit;
        }

        exit;
    }

    public function check_recaptcha()
    {
        if (!isset($_REQUEST['action']) || !isset($_REQUEST['response'])) {
            echo json_encode(['verified' => false]);

            exit;
        }

        check_ajax_referer($_REQUEST['action']);

        require_once USEYOURDRIVE_ROOTDIR.'/vendors/reCAPTCHA/autoload.php';
        $secret = Settings::get('recaptcha_secret');
        $recaptcha = new ReCaptcha($secret);

        $resp = $recaptcha->setExpectedAction('wpcloudplugins')
            ->setScoreThreshold(0.5)
            ->verify($_REQUEST['response'], Helpers::get_user_ip())
        ;

        if ($resp->isSuccess()) {
            echo json_encode(['verified' => true]);
        } else {
            echo json_encode(['verified' => false, 'msg' => $resp->getErrorCodes()]);
        }

        exit;
    }

    public function preview_shortcode()
    {
        check_ajax_referer('useyourdrive-module-preview');

        include_once USEYOURDRIVE_ROOTDIR.'/templates/admin/shortcode_previewer.php';

        exit;
    }

    public function embed_image()
    {
        $entryid = $_REQUEST['id'] ?? null;

        if (empty($entryid)) {
            exit('-1');
        }

        if (!isset($_REQUEST['account_id'])) {
            // Fallback for old embed urls without account info

            $primary_account = Accounts::instance()->get_primary_account();
            if (false === $primary_account) {
                exit('-1');
            }
            $account_id = $primary_account->get_id();
        } else {
            $account_id = $_REQUEST['account_id'];
        }

        App::set_current_account_by_id($account_id);
        Processor::instance()->embed_image($entryid);

        exit;
    }

    public function rating_asked()
    {
        update_option('use_your_drive_rating_asked', true);
    }

    public function user_folder_link()
    {
        check_ajax_referer('useyourdrive-create-link');

        $folder_id = sanitize_text_field(rawurldecode($_REQUEST['id']));
        $account_id = sanitize_text_field(rawurldecode($_REQUEST['account_id']));

        $linkedto = [
            'folderid' => $folder_id,
            'accountid' => $account_id,
        ];

        $userid = $_REQUEST['userid'];

        if (Helpers::check_user_role(Settings::get('permissions_link_users'))) {
            UserFolders::instance()->manually_link_folder($userid, $linkedto);
        }
    }

    public function user_folder_unlink()
    {
        check_ajax_referer('useyourdrive-create-link');

        $userid = $_REQUEST['userid'];
        $personal_folder_key = $_REQUEST['personal_folder_key'];

        if (Helpers::check_user_role(Settings::get('permissions_link_users'))) {
            UserFolders::instance()->manually_unlink_folder($userid, $personal_folder_key);
        }
    }

    public static function is_action_authorized($hook = false)
    {
        // Check if AJAX calls are coming from site own domain
        $ajax_domain_verification = ('Yes' === Settings::get('ajax_domain_verification'));
        $do_domain_verification = apply_filters('useyourdrive_do_domain_verification', $ajax_domain_verification);
        if ($do_domain_verification) {
            $refer = Helpers::get_page_url();
            if (!empty($refer)) {
                $refer_host = parse_url($refer, PHP_URL_HOST);
                $origin_host = parse_url(get_site_url(), PHP_URL_HOST);

                if (!empty($refer_host) && 0 !== strcasecmp($refer_host, $origin_host)) {
                    Helpers::log_error('AJAX request is blocked as it is sent from a different domain.', 'Processor', ['refer_host' => $refer_host, 'origin_host' => $origin_host], __LINE__);

                    exit;
                }
            }
        }

        // Nonce validation
        $nonce_verification = ('Yes' === Settings::get('nonce_validation'));
        $allow_nonce_verification = apply_filters('use_your_drive_allow_nonce_verification', $nonce_verification);

        if ($allow_nonce_verification && isset($_REQUEST['action']) && (false === $hook) && is_user_logged_in()) {
            $is_authorized = false;

            $requested_action = $_REQUEST['action'];

            switch ($requested_action) {
                case 'useyourdrive-upload-file':
                case 'useyourdrive-get-filelist':
                case 'useyourdrive-get-gallery':
                case 'useyourdrive-get-playlist':
                case 'useyourdrive-proofing':
                case 'useyourdrive-rename-entry':
                case 'useyourdrive-copy-entries':
                case 'useyourdrive-create-shortcuts':
                case 'useyourdrive-move-entries':
                case 'useyourdrive-edit-description-entry':
                case 'useyourdrive-create-entry':
                case 'useyourdrive-create-zip':
                case 'useyourdrive-delete-entries':
                case 'useyourdrive-event-log':
                case 'useyourdrive-shorten-url':
                case 'useyourdrive-module-login':
                case 'useyourdrive-module-lead':
                case 'useyourdrive-module-preview':
                case 'useyourdrive-import-entries':
                    $is_authorized = check_ajax_referer($requested_action, false, false);

                    break;

                case 'useyourdrive-create-link':
                case 'useyourdrive-embedded':
                    $is_authorized = check_ajax_referer('useyourdrive-create-link', false, false);

                    break;

                case 'useyourdrive-revoke':
                    return false !== check_ajax_referer('useyourdrive-admin-action', false, false);

                    break;

                case 'useyourdrive-download':
                case 'useyourdrive-stream':
                case 'useyourdrive-preview':
                case 'useyourdrive-thumbnail':
                case 'useyourdrive-edit':
                case 'useyourdrive-getpopup':
                case 'useyourdrive-getads':
                    $is_authorized = true;

                    break;

                case 'edit': // Required for integration one Page/Post pages
                    $is_authorized = true;

                    break;

                case 'editpost': // Required for integrations
                case 'wpseo_filter_shortcodes':
                case 'elementor':
                case 'elementor_ajax':
                case 'frm_insert_field':
                    return false;

                default:
                    $is_authorized = apply_filters('useyourdrive_nonce_verification', false, $requested_action);

                    if (false === $is_authorized) {
                        Helpers::log_error('Invalid AJAX request received.', 'Processor', ['action' => sanitize_key($requested_action)], __LINE__);

                        exit;
                    }
            }

            if (false === $is_authorized) {
                Helpers::log_error('Invalid AJAX nonce received.', 'Processor', ['action' => sanitize_key($requested_action)], __LINE__);

                exit;
            }
        }

        return true;
    }
}
