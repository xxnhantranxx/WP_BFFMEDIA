<?php

namespace TheLion\UseyourDrive\Integrations;

use TheLion\UseyourDrive\Accounts;
use TheLion\UseyourDrive\API;
use TheLion\UseyourDrive\App;
use TheLion\UseyourDrive\Client;
use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Processor;

defined('ABSPATH') || exit;

class GravityPDF
{
    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        if (false === get_option('gfpdf_current_version') && false === class_exists('GFPDF_Core')) {
            return;
        }

        add_action('gfpdf_post_save_pdf', [$this, 'useyourdrive_post_save_pdf'], 10, 5);
        add_filter('gfpdf_form_settings_advanced', [$this, 'useyourdrive_add_pdf_setting'], 10, 1);
    }

    /*
     * GravityPDF
     * Basic configuration in Form Settings -> PDF:
     *
     * Always Save PDF = YES
     * [GOOGLE  DRIVE] Export PDF = YES
     * [[GOOGLE  DRIVE] ID = ID where the PDFs need to be stored
     */

    public function useyourdrive_add_pdf_setting($fields)
    {
        $fields['useyourdrive_save_to_googledrive'] = [
            'id' => 'useyourdrive_save_to_googledrive',
            'name' => '[GOOGLE  DRIVE] Export PDF',
            'desc' => 'Save the created PDF to Google Drive',
            'type' => 'radio',
            'options' => [
                'Yes' => esc_html__('Yes'),
                'No' => esc_html__('No'),
            ],
            'std' => esc_html__('No'),
        ];

        $main_account = Accounts::instance()->get_primary_account();

        $account_id = '';
        if (!empty($main_account)) {
            $account_id = $main_account->get_id();
        }

        $fields['useyourdrive_save_to_account_id'] = [
            'id' => 'useyourdrive_save_to_account_id',
            'name' => '[GOOGLE  DRIVE] Account ID',
            'desc' => 'Account ID where the PDFs need to be stored. E.g. <code>'.$account_id.'</code>. Or use <code>%upload_account_id%</code> for the Account ID for the upload location of the plugin Upload Box field.',
            'type' => 'text',
            'std' => $account_id,
        ];

        $fields['useyourdrive_save_to_googledrive_id'] = [
            'id' => 'useyourdrive_save_to_googledrive_id',
            'name' => '[GOOGLE  DRIVE] Folder ID',
            'desc' => 'Folder ID where the PDFs need to be stored. E.g. <code>0AfuC9ad2CCWUk9PVB</code>. Or use <code>%upload_folder_id%</code> for the Account ID for the upload location of the plugin Upload Box field.',
            'type' => 'text',
            'std' => '',
        ];

        return $fields;
    }

    public function useyourdrive_post_save_pdf($pdf_path, $filename, $settings, $entry, $form)
    {
        if (!isset($settings['useyourdrive_save_to_googledrive']) || 'No' === $settings['useyourdrive_save_to_googledrive']) {
            return false;
        }

        $file = (object) [
            'tmp_path' => $pdf_path,
            'type' => mime_content_type($pdf_path),
            'name' => $filename,
            'size' => filesize($pdf_path),
        ];

        if (!isset($settings['useyourdrive_save_to_account_id'])) {
            // Fall back for older PDF configurations
            $settings['useyourdrive_save_to_account_id'] = Accounts::instance()->get_primary_account()->get_id();
        }

        // Placeholders
        list($upload_account_id, $upload_folder_id) = $this->get_upload_location($entry, $form);

        if (false !== strpos($settings['useyourdrive_save_to_account_id'], '%upload_account_id%')) {
            $settings['useyourdrive_save_to_account_id'] = $upload_account_id;
        }

        if ((false !== strpos($settings['useyourdrive_save_to_googledrive_id'], '%upload_folder%'))
        || (false !== strpos($settings['useyourdrive_save_to_googledrive_id'], '%upload_folder_id%'))
        ) {
            $settings['useyourdrive_save_to_googledrive_id'] = $upload_folder_id;
        }

        $account_id = apply_filters('useyourdrive_gravitypdf_set_account_id', $settings['useyourdrive_save_to_account_id'], $settings, $entry, $form, Processor::instance());
        $folder_id = apply_filters('useyourdrive_gravitypdf_set_folder_id', $settings['useyourdrive_save_to_googledrive_id'], $settings, $entry, $form, Processor::instance());

        $cached_node = $this->useyourdrive_upload_gravify_pdf($file, $account_id, $folder_id);

        // Stop if the upload has failed
        if (empty($cached_node)) {
            return false;
        }

        // Add url to PDF file in cloud
        $pdfs = \GPDFAPI::get_entry_pdfs($entry['id']);

        foreach ($pdfs as $pid => $pdf) {
            if ('Yes' === $pdf['useyourdrive_save_to_googledrive']) {
                $pdf['useyourdrive_pdf_url'] = 'https://drive.google.com/open?id='.$cached_node->get_id();
                \GPDFAPI::update_pdf($form['id'], $pid, $pdf);
            }
        }
    }

    public function useyourdrive_upload_gravify_pdf($file, $account_id, $folder_id)
    {
        $requested_account = Accounts::instance()->get_account_by_id($account_id);
        if (null !== $requested_account) {
            App::set_current_account($requested_account);
        } else {
            Helpers::log_error('Cannot use the requested account as it is not linked with the plugin.', 'GravityPDF', ['account_id' => $account_id], __LINE__);

            exit;
        }

        try {
            return API::upload_file($file, $folder_id);
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function get_upload_location($entry, $form)
    {
        $account_id = '';
        $folder_id = '';

        if (!is_array($form['fields'])) {
            return [$account_id, $folder_id];
        }

        foreach ($form['fields'] as $field) {
            if ('useyourdrive' !== $field->type) {
                continue;
            }

            if (!isset($entry[$field->id])) {
                continue;
            }

            $uploadedfiles = json_decode($entry[$field->id]);

            if ((null !== $uploadedfiles) && (count((array) $uploadedfiles) > 0)) {
                $first_entry = reset($uploadedfiles);

                $account_id = $first_entry->account_id;
                $requested_account = Accounts::instance()->get_account_by_id($account_id);
                App::set_current_account($requested_account);

                $cached_entry = Client::instance()->get_entry($first_entry->hash, false);
                $parent = $cached_entry->get_parent();
                $folder_id = $parent->get_id();
            }
        }

        return [$account_id, $folder_id];
    }
}

new GravityPDF();
