<?php

use Uncanny_Automator\Recipe;

/**
 * Class Add_Wpcp_UseyourDrive_Integration.
 */
class Add_Wpcp_UseyourDrive_Integration
{
    use Recipe\Integrations;

    /**
     * Add_Wpcp_UseyourDrive_Integration constructor.
     */
    public function __construct()
    {
        $this->setup();
    }

    protected function setup()
    {
        $this->set_integration('wpcp-useyourdrive');
        $this->set_external_integration(true);
        $this->set_name('Google Drive');
        $this->set_icon('google_drive_logo.svg');
        $this->set_icon_path(USEYOURDRIVE_ROOTDIR.'/css/images/');
        $this->set_plugin_file_path(USEYOURDRIVE_ROOTDIR.'/use-your-drive.php');
    }

    /**
     * @return bool
     */
    public function plugin_active()
    {
        return true;
    }
}
