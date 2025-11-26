<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

use ZipStream\Option\Archive;
use ZipStream\Option\File;
use ZipStream\ZipStream;

defined('ABSPATH') || exit;

class Zip
{
    /**
     * Unique ID.
     *
     * @var string
     */
    public $request_id;

    /**
     * Name of the zip file.
     *
     * @var string
     */
    public $zip_name;

    /**
     * Files that need to be added to ZIP.
     *
     * @var array
     */
    public $files = [];

    /**
     * Folders that need to be created in ZIP.
     *
     * @var array
     */
    public $folders = [];

    /**
     * Number of bytes that are downloaded so far.
     *
     * @var int
     */
    public $bytes_so_far = 0;

    /**
     * Bytes that need to be download in total.
     *
     * @var int
     */
    public $bytes_total = 0;

    /**
     * Current status.
     *
     * @var string
     */
    public $current_action = 'starting';

    /**
     * Message describing the current status.
     *
     * @var string
     */
    public $current_action_str = '';

    /**
     * @var \TheLion\UseyourDrive\CacheNode[]
     */
    public $entries_downloaded = [];

    /**
     * @var ZipStream
     */
    private $_zip_handler;

    /**
     * Constructor for the Zip class.
     *
     * @param string $request_id unique ID for the request
     */
    public function __construct($request_id)
    {
        $this->request_id = $request_id;

        require_once USEYOURDRIVE_ROOTDIR.'/vendors/ZipStream/vendor/autoload.php';
    }

    /**
     * Main function creating the ZIP file for files and folders which are requested via $_REQUEST.
     */
    public function do_zip()
    {
        $this->initialize();
        $this->current_action = 'indexing';
        $this->current_action_str = esc_html__('Selecting files...', 'wpcloudplugins');

        $this->index();
        $this->create();

        do_action('useyourdrive_log_event', 'useyourdrive_downloaded_zip', Processor::instance()->get_requested_entry(), ['name' => $this->zip_name, 'files' => count($this->files), 'size' => $this->bytes_total]);

        $this->add_folders();

        $this->current_action = 'downloading';
        $this->add_files();

        $this->current_action = 'finalizing';
        $this->current_action_str = esc_html__('Almost ready', 'wpcloudplugins');
        $this->set_progress();
        $this->finalize();

        $this->current_action = 'finished';
        $this->current_action_str = esc_html__('Finished', 'wpcloudplugins');
        $this->set_progress();

        exit;
    }

    /**
     * Load the ZIP library and make sure that the root folder is loaded.
     */
    public function initialize()
    {
        ignore_user_abort(false);

        // Check if file/folder is cached and still valid
        $cachedfolder = Client::instance()->get_folder();

        if (false === $cachedfolder || false === $cachedfolder['folder']) {
            return new \WP_Error('broke', esc_html__("Requested directory isn't allowed", 'wpcloudplugins'));
        }

        $folder = $cachedfolder['folder']->get_entry();

        // Check if entry is allowed
        if (!Processor::instance()->_is_entry_authorized($cachedfolder['folder'])) {
            return new \WP_Error('broke', esc_html__("Requested directory isn't allowed", 'wpcloudplugins'));
        }

        $this->zip_name = basename($folder->get_name()).'_'.time().'.zip';

        $single_entry = null;
        if (isset($_REQUEST['files']) && 1 === count($_REQUEST['files'])) {
            $single_entry = Client::instance()->get_entry($_REQUEST['files'][0]);
            $this->zip_name = basename($single_entry->get_name()).'_'.time().'.zip';
        }

        if ($download_limit_hit_message = Restrictions::has_reached_download_limit($single_entry ?? $folder->get_id(), false, 'download_zip')) {
            $this->current_action = 'finished';
            $this->current_action_str = $download_limit_hit_message;
            $this->set_progress();

            http_response_code(429);

            exit;
        }

        $this->set_progress();

        // Stop WP from buffering, and discard the current buffer.
        $levels = ob_get_level();
        for ($i = 0; $i < $levels; ++$i) {
            ob_end_clean();
        }
    }

    /**
     * Create the ZIP File.
     */
    public function create()
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        @session_write_close();

        $options = new Archive();
        $options->setSendHttpHeaders(true);
        $options->setFlushOutput(true);
        $options->setContentType('application/octet-stream');
        header('X-Accel-Buffering: no');

        // create a new zipstream object
        $this->_zip_handler = new ZipStream(Helpers::filter_filename($this->zip_name), $options);

        $this->_clear_temp_folder();
    }

    /**
     * Create a list of files and folders that need to be zipped.
     */
    public function index()
    {
        $requested_ids = [Processor::instance()->get_requested_entry()];

        if (isset($_REQUEST['files'])) {
            $requested_ids = $_REQUEST['files'];
        }

        foreach ($requested_ids as $fileid) {
            $cached_entry = Client::instance()->get_entry($fileid);

            if (false === $cached_entry) {
                continue;
            }

            $data = Client::instance()->get_files_recursive($cached_entry);

            $this->files = array_merge($this->files, $data['files']);
            $this->folders = array_merge($this->folders, $data['folders']);
            $this->bytes_total += $data['bytes_total'];

            $this->current_action_str = esc_html__('Selecting files...', 'wpcloudplugins').' ('.count($this->files).')';
            $this->set_progress();
        }
    }

    /**
     * Add Folders to Zip file.
     */
    public function add_folders()
    {
        if (count($this->folders) > 0) {
            foreach ($this->folders as $key => $folder) {
                $this->_zip_handler->addFile($folder, '');
                unset($this->folders[$key]);
            }
        }
    }

    /**
     * Add all requests files to Zip file.
     */
    public function add_files()
    {
        if (count($this->files) > 0) {
            foreach ($this->files as $key => $file) {
                // Skip file if the download limit is reached
                if ($download_limit_hit_message = Restrictions::has_reached_download_limit($file['ID'], false)) {
                    $fileOptions = new File();
                    $fileOptions->setComment($download_limit_hit_message);

                    $this->_zip_handler->addFile(trim($file['path'], '/').'.download-limit-exceeded', '', $fileOptions);

                    unset($this->files[$key]);

                    continue;
                }

                $this->add_file_to_zip($file);

                unset($this->files[$key]);

                $cached_entry = Cache::instance()->get_node_by_id($file['ID']);
                $this->entries_downloaded[] = $cached_entry;

                do_action('useyourdrive_log_event', 'useyourdrive_downloaded_entry', $cached_entry, ['as_zip' => true]);

                $this->current_action_str = esc_html__('Downloading...', 'wpcloudplugins').'<br/>('.Helpers::bytes_to_size_1024($this->bytes_so_far).' / '.Helpers::bytes_to_size_1024($this->bytes_total).')';
                $this->set_progress();
            }
        }
    }

    /**
     * Download the request file and add it to the ZIP.
     *
     * @param array $file
     */
    public function add_file_to_zip($file)
    {
        Helpers::set_time_limit(0);

        // get file
        $cached_entry = Cache::instance()->get_node_by_id($file['ID']);

        $tmpfname = tempnam(sys_get_temp_dir(), 'WPC-');
        $download_stream = fopen($tmpfname, 'w+b');
        $stream_meta_data = stream_get_meta_data($download_stream);

        // If the script terminates unexpectedly, the temporary file may not be deleted.
        // This handler tries to resolve that.
        register_shutdown_function(function () use ($download_stream, $stream_meta_data) {
            if (is_resource($download_stream)) {
                fclose($download_stream);
            }
            if (!empty($stream_meta_data['uri']) && @file_exists($stream_meta_data['uri'])) {
                @unlink($stream_meta_data['uri']);
            }
        });

        $headers = [];

        // Add Resources key to give permission to access the item
        if ($cached_entry->get_entry()->has_resourcekey()) {
            $headers['X-Goog-Drive-Resource-Keys'] = $cached_entry->get_id().'/'.$cached_entry->get_entry()->get_resourcekey();
        }

        $request = new \UYDGoogle_Http_Request($file['url'], 'GET', $headers);
        App::instance()->get_sdk_client()->getIo()->setOptions(
            [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FILE => $download_stream,
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => null,
                CURLOPT_TIMEOUT => null,
            ]
        );

        try {
            App::instance()->get_sdk_client()->getAuth()->authenticatedRequest($request);
            curl_close(App::instance()->get_sdk_client()->getIo()->getHandler());
        } catch (\Exception $ex) {
            fclose($download_stream);
            @unlink($stream_meta_data['uri']);
            Helpers::log_error('', 'API', null, __LINE__, $ex);

            return;
        }

        rewind($download_stream);

        $this->bytes_so_far += $file['bytes'];

        $fileOptions = new File();
        if (!empty($cached_entry->get_entry()->get_last_edited())) {
            $date = new \DateTime();
            $date->setTimestamp(strtotime($cached_entry->get_entry()->get_last_edited()));
            $fileOptions->setTime($date);
        }
        $fileOptions->setComment((string) $cached_entry->get_entry()->get_description());

        try {
            $this->_zip_handler->addFileFromStream(trim($file['path'], '/'), $download_stream, $fileOptions);
        } catch (\Exception $ex) {
            fclose($download_stream);
            @unlink($stream_meta_data['uri']);
            Helpers::log_error('Cannot add file to ZIP.', 'Zip', null, __LINE__, $ex);

            $this->current_action = 'failed';
            $this->set_progress();

            exit;
        }

        fclose($download_stream);
        @unlink($stream_meta_data['uri']);

        Download::throttle();
    }

    /**
     * Finalize the zip file.
     */
    public function finalize()
    {
        $this->set_progress();

        // Close zip
        $this->_zip_handler->finish();

        // Send email if needed
        if ('1' === Processor::instance()->get_shortcode_option('notificationdownload')) {
            Processor::instance()->send_notification_email('download', $this->entries_downloaded);
        }

        // Download Zip Hook
        do_action('useyourdrive_download_zip', $this->entries_downloaded);
    }

    /**
     * Received progress information for the ZIP process from database.
     *
     * @param string $request_id
     */
    public static function get_progress($request_id)
    {
        return get_transient('useyourdrive_zip_'.substr($request_id, 0, 40));
    }

    /**
     * Set current progress information for ZIP process in database.
     */
    public function set_progress()
    {
        $status = [
            'id' => $this->request_id,
            'status' => [
                'bytes_so_far' => $this->bytes_so_far,
                'bytes_total' => $this->bytes_total,
                'percentage' => ($this->bytes_total > 0) ? (round(($this->bytes_so_far / $this->bytes_total) * 100)) : 0,
                'progress' => $this->current_action,
                'progress_str' => $this->current_action_str,
            ],
        ];

        // Update progress
        return set_transient('useyourdrive_zip_'.substr($this->request_id, 0, 40), $status, HOUR_IN_SECONDS);
    }

    /**
     * Get progress information for the ZIP process
     * Used to display a progress percentage on Front-End.
     *
     * @param string $request_id
     */
    public static function get_status($request_id)
    {
        // Try to get the upload status of the file
        for ($_try = 1; $_try < 6; ++$_try) {
            $result = self::get_progress($request_id);

            if (false !== $result) {
                if ('failed' === $result['status']['progress'] || 'finished' === $result['status']['progress']) {
                    delete_transient('useyourdrive_zip_'.substr($request_id, 0, 40));
                }

                break;
            }

            // Wait a moment, perhaps the upload still needs to start
            usleep(500000 * $_try);
        }

        if (false === $result) {
            $result = ['file' => false, 'status' => ['bytes_down_so_far' => 0, 'total_bytes_down_expected' => 0, 'percentage' => 0, 'progress' => 'failed']];
        }

        echo json_encode($result);

        exit;
    }

    /**
     * Clear temporary files older than specific number of hours.
     *
     * @param int $max_age_hours
     */
    private function _clear_temp_folder($max_age_hours = 2)
    {
        // Define the temp directory and file pattern
        $temp_dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $file_pattern = $temp_dir.'WPC-*';

        // Define the age limit (12 hours in seconds)
        $max_age = $max_age_hours * 3600;
        $current_time = time();

        // Get all files matching the pattern
        $files = glob($file_pattern);

        if (false !== $files) {
            foreach ($files as $file) {
                // Check if it's a file and not a directory
                if (is_file($file)) {
                    // Get the file's modification time
                    $file_mod_time = filemtime($file);
                    if (false !== $file_mod_time && ($current_time - $file_mod_time) > $max_age) {
                        // Attempt to delete the file
                        @unlink($file);
                    }
                }
            }
        }
    }
}
