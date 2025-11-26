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

class Account
{
    /**
     * Account ID.
     *
     * @var string
     */
    private $_id;

    /**
     * Identifier for front-end use.
     */
    private $_uuid;

    /**
     * Account Name.
     *
     * @var string
     */
    private $_name;

    /**
     * Account Email.
     *
     * @var string
     */
    private $_email;

    /**
     * Account profile picture (url).
     *
     * @var string
     */
    private $_image;

    /**
     * Scopes that are approved during authorization.
     */
    private $_scopes = [];

    /**
     * $_authorization contains the authorization token for the linked Cloud storage.
     *
     * @var Authorization
     */
    private $_authorization;

    public function __construct($id, $name, $email, $image = null)
    {
        $this->_id = $id;
        $this->_name = $name;
        $this->_email = $email;
        $this->_image = $image;
        $this->_authorization = new Authorization($this);

        $this->set_uuid();
    }

    public function __sleep()
    {
        // Don't store authorization class in DB */
        $keys = get_object_vars($this);
        unset($keys['_authorization']);

        return array_keys($keys);
    }

    public function __wakeup()
    {
        $this->_authorization = new Authorization($this);

        // Add old default scopes for accounts that don't yet have scopes stored
        $old_scopes = [
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
        ];

        if (empty($this->_scopes)) {
            $this->_scopes = $old_scopes;
        }
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_uuid()
    {
        if ('Yes' === Settings::get('mask_account_id')) {
            return 'wpcp-'.$this->_uuid;
        }

        return $this->_id;
    }

    public function get_name()
    {
        return $this->_name;
    }

    public function get_email()
    {
        if (empty($this->_image)) {
            return USEYOURDRIVE_ROOTPATH.'/css/images/google_drive_logo.svg';
        }

        return $this->_email;
    }

    public function get_image()
    {
        return $this->_image;
    }

    public function get_scopes()
    {
        if (true === in_array('https://www.googleapis.com/auth/userinfoemail', $this->_scopes)) {
            $this->set_scopes($this->_scopes);
            Accounts::instance()->save();
        }

        return $this->_scopes;
    }

    public function has_scope($scope)
    {
        return in_array($scope, $this->_scopes);
    }

    public function set_id($_id)
    {
        $this->_id = $_id;
    }

    public function set_uuid()
    {
        $this->_uuid = hash_hmac('md5', $this->_id, USEYOURDRIVE_AUTH_KEY);
    }

    public function set_name($_name)
    {
        $this->_name = $_name;
    }

    public function set_email($_email)
    {
        $this->_email = $_email;
    }

    public function set_image($_image)
    {
        $this->_image = $_image;
    }

    public function set_scopes($_scopes)
    {
        foreach ($_scopes as $key => $scope) {
            $_scopes[$key] = \str_replace(['userinfoemail', 'userinfoprofile', 'drivefile', 'drivereadonly', 'driveappfolder', 'driveappdata'], ['userinfo.email', 'userinfo.profile', 'drive.file', 'drive.readonly', 'drive.appfolder', 'drive.appdata'], $scope);
        }

        $this->_scopes = $_scopes;
    }

    /**
     * @return StorageInfo
     */
    public function get_storage_info()
    {
        $transient_name = 'useyourdrive_'.$this->get_id().'_driveinfo';
        $storage_info = get_transient($transient_name);

        if (empty($storage_info)) {
            App::set_current_account($this);

            $storage_info = new StorageInfo();

            if ($this->has_drive_access() || $this->has_own_app_folder_access()) {
                $storage_info_data = API::get_space_info();
                $storage_info->set_quota_total($storage_info_data->getStorageQuota()->getLimit());
                $storage_info->set_quota_used($storage_info_data->getStorageQuota()->getUsage());
            } else {
                $storage_info->set_quota_total(null);
                $storage_info->set_quota_used(0);
            }

            set_transient($transient_name, $storage_info, DAY_IN_SECONDS);
        }

        return $storage_info;
    }

    public function has_drive_access()
    {
        return $this->has_scope('https://www.googleapis.com/auth/drive') || $this->has_scope('https://www.googleapis.com/auth/drive.readonly');
    }

    public function has_own_app_folder_access()
    {
        return $this->has_scope('https://www.googleapis.com/auth/drive.file');
    }

    public function has_app_folder_access()
    {
        return $this->has_scope('https://www.googleapis.com/auth/drive.appfolder') || $this->has_scope('https://www.googleapis.com/auth/drive.appdata');
    }

    public function is_drive_readonly()
    {
        return $this->has_scope('https://www.googleapis.com/auth/drive.readonly') && false === $this->has_scope('https://www.googleapis.com/auth/drive');
    }

    /**
     * @return Authorization
     */
    public function get_authorization()
    {
        return $this->_authorization;
    }
}
