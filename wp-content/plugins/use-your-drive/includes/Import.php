<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       3.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

defined('ABSPATH') || exit;

class Import
{
    /**
     * Node object representing the file to be imported.
     *
     * @var CacheNode
     */
    public $node;

    /**
     * @var string the name of the attachment
     */
    public $attachment_name;

    /**
     * URL to download the file.
     *
     * @var string
     */
    public $download_url;

    /**
     * Path where the file will be imported.
     *
     * @var string
     */
    public $import_path;

    /**
     * The single instance of the class.
     *
     * @var Import
     */
    protected static $_instance;

    /**
     * Upload directory information.
     *
     * @var array
     */
    protected static $_upload_dir;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH.'wp-admin/includes/image.php';

            include_once ABSPATH.'wp-admin/includes/media.php';
        }

        self::$_upload_dir = wp_upload_dir();
    }

    /**
     * Get the single instance of the class.
     *
     * @return Import - Import instance
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Add a file to the media library.
     *
     * @param CacheNode $node node object representing the file
     *
     * @return int attachment ID
     */
    public function add_to_media_library($node)
    {
        $this->node = $node;

        // Set Attachment name
        $this->attachment_name = sanitize_file_name($this->node->get_name());

        $this->download_url = 'https://www.googleapis.com/drive/v3/files/'.$node->get_id().'?alt=media';

        // Google Docs convert
        $export_formats = $node->get_entry()->get_save_as();
        if (!empty($export_formats)) {
            $format = $export_formats['PDF'] ?? reset($export_formats);
            $this->attachment_name = sanitize_file_name($this->node->get_name().'.'.$format['extension']);
            $this->download_url = 'https://www.googleapis.com/drive/v3/files/'.$node->get_id().'/export?alt=media&mimeType='.$format['mimetype'];
        }

        $this->download_file_to_uploads();

        return $this->insert_attachment_to_library();
    }

    /**
     * Download the file to the uploads folder.
     */
    public function download_file_to_uploads()
    {
        $upload_dir = wp_upload_dir();

        $this->import_path = $upload_dir['path'].'/'.$this->attachment_name;

        // If file exists, rename it
        if (file_exists($this->import_path)) {
            $this->import_path = $upload_dir['path'].'/'.time().'_'.$this->attachment_name;
        }

        // Stream
        $chunk_size = Download::get_chunk_size();
        $chunk_start = 0;
        $chunk_end = $this->node->get_entry()->get_size() - 1;

        Helpers::set_time_limit(0);

        // Open file for writing
        $file_handle = fopen($this->import_path, 'wb');

        try {
            while ($chunk_start <= $chunk_end) {
                // Output the chunk
                $chunk_end = ((($chunk_start + $chunk_size) > $chunk_end) ? $chunk_end : $chunk_start + $chunk_size);
                $this->_stream_get_chunk($chunk_start, $chunk_end, $file_handle);

                $chunk_start = $chunk_end + 1;

                Download::throttle();
            }
        } catch (\Exception $e) {
            Helpers::log_error('Cannot store file in upload folder', 'Import', ['file' => $this->import_path], __LINE__, $e);
        } finally {
            // Close file handle
            fclose($file_handle);
        }
    }

    /**
     * Insert the downloaded file as an attachment.
     *
     * @return int attachment ID
     */
    public function insert_attachment_to_library()
    {
        $file_path = $this->import_path;

        // Create an attachment for the file
        $file_type = wp_check_filetype(basename($file_path));

        $attachment = [
            'guid' => self::$_upload_dir['url'].'/'.basename($file_path),
            'post_mime_type' => $file_type['type'],
            'post_title' => sanitize_title($this->node->get_entry()->get_basename()),
            'post_content' => sanitize_text_field($this->node->get_entry()->get_description()),
            'post_status' => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);

        // Generate metadata and update the attachment.
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /**
     * Function to get a range of bytes via the API.
     *
     * @param int      $start
     * @param int      $end
     * @param resource $file_handle
     */
    private function _stream_get_chunk($start, $end, $file_handle)
    {
        $headers = $this->get_request_headers($start, $end);

        $request = new \UYDGoogle_Http_Request($this->download_url, 'GET', $headers);
        $request->disableGzip();

        App::instance()->get_sdk_client()->getIo()->setOptions(
            [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RANGE => null,
                CURLOPT_NOBODY => null,
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => function ($curl, $data) use ($file_handle) {
                    return fwrite($file_handle, $data);
                },
                CURLOPT_CONNECTTIMEOUT => null,
                CURLOPT_TIMEOUT => null,
            ]
        );

        App::instance()->get_sdk_client()->getAuth()->authenticatedRequest($request);
    }

    /**
     * Helper function to get headers for the request.
     *
     * @param int $start
     * @param int $end
     *
     * @return array
     */
    private function get_request_headers($start, $end)
    {
        $headers = ['Range' => 'bytes='.$start.'-'.$end];

        // Add Resources key to give permission to access the item
        if ($this->node->get_entry()->has_resourcekey()) {
            $headers['X-Goog-Drive-Resource-Keys'] = $this->node->get_id().'/'.$this->node->get_entry()->get_resourcekey();
        }

        return $headers;
    }
}
