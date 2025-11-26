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

class App
{
    /**
     * The single instance of the class.
     *
     * @var App
     */
    protected static $_instance;

    /**
     * @var bool
     */
    private $_own_app = false;

    /**
     * @var string
     */
    private $_app_key;

    /**
     * @var string
     */
    private $_app_secret;

    /**
     * @var string
     */
    private $_app_token;

    /**
     * @var \UYDGoogle_Service_Oauth2
     */
    private $_user_info_service;

    /**
     * @var \UYDGoogle_Service_Drive
     */
    private $_google_drive_service;

    /**
     * @var \UYDGoogle_Client
     */
    private static $_sdk_client;

    /**
     * @var Account
     */
    private static $_current_account;

    /**
     * We don't save your data or share it.
     * It is used for an easy and one-click authorization process that will always work!
     *
     * @var string
     */
    private $_auth_url = 'https://www.wpcloudplugins.com/use-your-drive/index.php';

    public function __construct()
    {
        // Call back for refresh token function in SDK client
        add_action('use-your-drive-refresh-token', [$this, 'refresh_token'], 10, 1);

        if (!function_exists('useyourdrive_api_php_client_autoload')) {
            require_once USEYOURDRIVE_ROOTDIR.'/vendors/Google-sdk/src/Google/autoload.php';
        }

        if (!class_exists('UYDGoogle_Client') || (!method_exists('UYDGoogle_Client', 'getLibraryVersion'))) {
            $reflector = new \ReflectionClass('UYDGoogle_Client');
            $error = 'Conflict with other Google Library: '.$reflector->getFileName();

            throw new \Exception($error);
        }
    }

    /**
     * App Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return App - App instance
     *
     * @static
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            $app = new self();
        } else {
            $app = self::$_instance;
        }

        if (empty($app::$_sdk_client)) {
            try {
                $app->start_sdk_client(App::get_current_account());
            } catch (\Exception $ex) {
                self::$_instance = $app;

                return self::$_instance;
            }
        }

        self::$_instance = $app;

        if (null !== App::get_current_account()) {
            $app->get_sdk_client(App::get_current_account());
        }

        return self::$_instance;
    }

    public function process_authorization()
    {
        if (!empty($_GET['ver']) && isset($_GET['code'])) {
            Processor::reset_complete_cache();
            $this->create_access_token();
        }

        // Close oAuth popup and refresh plugin settings page. Only possible with inline javascript.
        echo '<script type="text/javascript">localStorage.setItem("wpcp_refreshParent", "true"); window.close();</script>';

        exit;
    }

    public function has_plugin_own_app()
    {
        return $this->_own_app;
    }

    public function get_auth_url()
    {
        return self::get_sdk_client()->createAuthUrl();
    }

    /**
     * @return \UYDGoogle_Client
     */
    public function start_sdk_client(?Account $account = null)
    {
        try {
            self::$_sdk_client = new \UYDGoogle_Client();
            self::$_sdk_client->getLibraryVersion();
        } catch (\Exception $ex) {
            Helpers::log_error('Cannot start client', 'App', null, __LINE__, $ex);

            return $ex;
        }

        self::$_sdk_client->setApplicationName('WordPress Use-your-Drive '.USEYOURDRIVE_VERSION);
        self::$_sdk_client->setClientId($this->get_app_key());
        self::$_sdk_client->setClientSecret($this->get_app_secret());
        self::$_sdk_client->setRedirectUri($this->_auth_url);
        self::$_sdk_client->setApprovalPrompt('force');
        self::$_sdk_client->setAccessType('offline');
        self::$_sdk_client->setIncludeGrantedScopes(true);

        if (!empty($account)) {
            self::$_sdk_client->setLoginHint($account->get_email());
        }

        if (Core::is_network_authorized() || is_network_admin()) {
            $state = network_admin_url('admin.php?page=UseyourDrive_network_settings&action=useyourdrive_authorization');
        } else {
            $state = admin_url('admin.php?page=UseyourDrive_settings&action=useyourdrive_authorization');
        }

        $state .= '&license='.(string) License::get();
        $state .= '&siteurl='.License::get_home_url();

        self::$_sdk_client->setState(strtr(base64_encode($state), '+/=', '-_~'));

        $this->set_logger();

        if (null === $account) {
            return self::$_sdk_client;
        }

        self::set_current_account($account);

        $authorization = $account->get_authorization();

        if (false === $authorization->has_access_token()) {
            return self::$_sdk_client;
        }

        $access_token = $authorization->get_access_token();

        if (empty($access_token)) {
            return self::$_sdk_client;
        }

        self::$_sdk_client->setAccessToken($access_token);

        // Check if the AccessToken is still valid
        if (false === self::$_sdk_client->isAccessTokenExpired()) {
            return self::$_sdk_client;
        }

        // If we end up here, we have to refresh the token
        return $this->refresh_token($account);
    }

    public function refresh_token(?Account $account = null)
    {
        $authorization = $account->get_authorization();
        $access_token = $authorization->get_access_token();

        if (!flock($authorization->get_token_file_handle(), LOCK_EX | LOCK_NB)) {
            Helpers::log_error('Wait till another process has renewed the Authorization Token', 'App', null, __LINE__);

            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that
             * the previous process died and unlock the file manually
             */
            $requires_unlock = ((filemtime($authorization->get_token_location()) + 60) < time());

            // Temporarily workaround when flock is disabled. Can cause problems when plugin is used in multiple processes
            if (false !== strpos(ini_get('disable_functions'), 'flock')) {
                $requires_unlock = false;
            }

            if ($requires_unlock) {
                $authorization->unlock_token_file();
            }

            if (flock($authorization->get_token_file_handle(), LOCK_SH)) {
                clearstatcache();
                rewind($authorization->get_token_file_handle());
                $access_token = fread($authorization->get_token_file_handle(), filesize($authorization->get_token_location()));
                Helpers::log_error('New Authorization Token has been received by another process.', 'App', null, __LINE__);
                self::$_sdk_client->setAccessToken($access_token);
                $authorization->unlock_token_file();

                return self::$_sdk_client;
            }
        }

        // Stop if we need to get a new AccessToken but somehow ended up without a refreshtoken
        $refresh_token = self::$_sdk_client->getRefreshToken();

        if (empty($refresh_token)) {
            Helpers::log_error('No Refresh Token found during the renewing of the current token. This will stop the authorization completely.', 'App', null, __LINE__);
            $authorization->set_is_valid(false);
            $authorization->unlock_token_file();
            $this->revoke_token($account);

            return false;
        }

        // Refresh token
        try {
            self::$_sdk_client->refreshToken($refresh_token);

            // Store the new token
            $new_accestoken = self::$_sdk_client->getAccessToken();
            $authorization->set_access_token($new_accestoken);

            $authorization->unlock_token_file();

            if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]))) {
                wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]);
            }
        } catch (\Exception $ex) {
            $authorization->set_is_valid(false);
            $authorization->unlock_token_file();
            Helpers::log_error('Cannot refresh Authorization Token.', 'App', null, __LINE__, $ex);

            if (!wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()])) {
                wp_schedule_event(time(), 'daily', 'useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]);
            }

            Processor::reset_complete_cache();

            throw $ex;
        }

        return self::$_sdk_client;
    }

    public function set_logger()
    {
        $log_file = USEYOURDRIVE_CACHEDIR.'/api.log';
        if ('Yes' === Settings::get('api_log')) {
            // Logger
            self::get_sdk_client()->setClassConfig('UYDGoogle_Logger_File', [
                'file' => $log_file,
                'mode' => 0640,
                'lock' => true, ]);

            self::get_sdk_client()->setClassConfig('UYDGoogle_Logger_Abstract', [
                'level' => 'debug', // 'warning' or 'debug'
                'log_format' => "[%datetime%] %level%: %message% %context%\n",
                'date_format' => 'd/M/Y:H:i:s O',
                'allow_newlines' => true, ]);

            self::get_sdk_client()->setLogger(new \UYDGoogle_Logger_File(self::get_sdk_client()));

            // Delete log file if it exceeds 100MB or is older than 1 week
            if (\file_exists($log_file)) {
                $file_size = \filesize($log_file);
                $file_age = time() - filemtime($log_file);

                if ($file_size > 100 * 1024 * 1024 || $file_age > 7 * 24 * 60 * 60) {
                    unlink($log_file);
                }
            }
        }
    }

    public static function download_api_log()
    {
        if (!file_exists(USEYOURDRIVE_CACHEDIR.'/api.log')) {
            exit;
        }

        $filename = date('Y-m-d-h-i').' - Google Drive API.log';

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; '.sprintf('filename="%s"; ', rawurlencode($filename)).sprintf("filename*=utf-8''%s", rawurlencode($filename)));

        readfile(USEYOURDRIVE_CACHEDIR.'/api.log');

        exit;
    }

    public function create_access_token()
    {
        try {
            $code = sanitize_text_field($_REQUEST['code']);
            $scopes = sanitize_text_field($_REQUEST['scope']);

            $scopes = array_filter(explode(' ', $scopes), function ($url) {
                return filter_var($url, FILTER_VALIDATE_URL);
            });

            // Fetch the Access Token
            $access_token = self::get_sdk_client()->authenticate($code);

            // Get & Update User Information
            $account_data = $this->get_user()->userinfo->get();
            $account = new Account($account_data->getId(), $account_data->getName(), $account_data->getEmail(), $account_data->getPicture());
            $account->set_scopes($scopes);
            $account->get_authorization()->set_access_token($access_token);
            $account->get_authorization()->unlock_token_file();

            Accounts::instance()->add_account($account);

            delete_transient('useyourdrive_'.$account->get_id().'_is_authorized');
        } catch (\Exception $ex) {
            Helpers::log_error('Cannot generate Access Token.', 'App', null, __LINE__, $ex);

            return new \WP_Error('broke', esc_html__('Error communicating with API:', 'wpcloudplugins').$ex->getMessage());
        }

        Processor::reset_complete_cache();

        return true;
    }

    public function revoke_token(Account $account)
    {
        Helpers::log_error('Authorization for account revoked.', 'App', ['account_id' => $account->get_id(), 'account_email' => $account->get_email()], __LINE__);

        // Reset Personal Folders Back-End if the account it is pointing to is deleted
        $personal_folders_data = Settings::get('userfolder_backend_auto_root', []);
        if (is_array($personal_folders_data) && isset($personal_folders_data['account']) && $personal_folders_data['account'] === $account->get_id()) {
            Settings::save('userfolder_backend_auto_root', []);
        }

        Processor::reset_complete_cache();

        if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]))) {
            wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]);
        }

        Core::instance()->send_lost_authorisation_notification($account->get_id());

        try {
            self::get_sdk_client()->revokeToken();
        } catch (\Exception $ex) {
            Helpers::log_error('Authorization for account cannot be revoked.', 'App', ['account_id' => $account->get_id(), 'account_email' => $account->get_email()], __LINE__, $ex);
        }

        Accounts::instance()->remove_account($account->get_id());

        delete_transient('useyourdrive_'.$account->get_id().'_is_authorized');

        return true;
    }

    public function get_app_key()
    {
        if (empty($this->_app_key)) {
            $license = License::validate();
            $this->_app_key = $license['appdata']['key'] ?? null;
            $this->_app_token = $license['appdata']['token'] ?? null;

            if (!empty($own_key = Settings::get('googledrive_app_client_id'))) {
                $this->_app_key = $own_key;
                $this->_own_app = true;
            }
        }

        return $this->_app_key;
    }

    public function get_app_secret()
    {
        if (empty($this->_app_secret)) {
            $license = License::validate();
            $this->_app_secret = $license['appdata']['secret'] ?? null;

            if (!empty($own_secret = Settings::get('googledrive_app_client_secret'))) {
                $this->_app_secret = $own_secret;
                $this->_own_app = true;
            }
        }

        return $this->_app_secret;
    }

    /**
     * @param null|Account $account
     *
     * @return \UYDGoogle_Client
     */
    public static function get_sdk_client($account = null)
    {
        if (empty(self::$_sdk_client)) {
            self::$_sdk_client = self::instance()->start_sdk_client();
        }

        if (!empty($account)) {
            self::set_current_account($account);
        }

        return self::$_sdk_client;
    }

    /**
     * @return \UYDGoogle_Service_Oauth2
     */
    public function get_user()
    {
        if (empty($this->_user_info_service)) {
            $client = self::get_sdk_client();
            $this->_user_info_service = new \UYDGoogle_Service_Oauth2($client);
        }

        return $this->_user_info_service;
    }

    /**
     * @return \UYDGoogle_Service_Drive
     */
    public function get_drive()
    {
        if (empty($this->_google_drive_service)) {
            $client = self::get_sdk_client();
            $this->_google_drive_service = new \UYDGoogle_Service_Drive($client);
        }

        return $this->_google_drive_service;
    }

    /**
     * @return Account
     */
    public static function get_current_account()
    {
        if (empty(self::$_current_account) && null !== Processor::instance()->get_shortcode()) {
            $account = Accounts::instance()->get_account_by_id(Processor::instance()->get_shortcode_option('account'));
            if (!empty($account)) {
                self::set_current_account($account);
            }
        }

        return self::$_current_account;
    }

    public static function set_current_account(Account $account)
    {
        if (self::$_current_account !== $account) {
            self::$_current_account = $account;
            Cache::instance_unload();

            if (empty(self::$_sdk_client)) {
                self::instance();
            }

            $scopes = $account->get_scopes();
            self::$_sdk_client->setScopes($scopes);

            if ($account->get_authorization()->has_access_token()) {
                self::$_sdk_client->setAccessToken($account->get_authorization()->get_access_token());
            }
        }

        return self::$_current_account;
    }

    public static function set_current_account_by_id(string $account_id)
    {
        $account = Accounts::instance()->get_account_by_id($account_id);

        if (empty($account)) {
            Helpers::log_error('Cannot use the requested account as it is not linked with the plugin. Plugin falls back to primary account.', 'App', ['account_id' => $account_id], __LINE__);

            $account = Accounts::instance()->get_primary_account();

            if (empty($account)) {
                self::$_current_account = null;

                return self::$_current_account;
            }
        }

        return self::set_current_account($account);
    }

    public static function clear_current_account()
    {
        self::$_current_account = null;
        Cache::instance_unload();
    }

    public function get_auth_uri()
    {
        return $this->_auth_url;
    }
}
