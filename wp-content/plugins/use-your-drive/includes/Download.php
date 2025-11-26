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

class Download
{
    /**
     * @var CacheNode
     */
    public $cached_node;

    /**
     * @var string 'proxy'|'redirect'
     */
    private $_download_method;

    /**
     * Should the file be streamed using the server as middleman.
     *
     * @var bool
     */
    private $_force_proxy;

    /**
     * Url to the content of the file. Is only public when file is shared.
     *
     * @var string
     */
    private $_content_url;

    /**
     * Mimetype of the download.
     *
     * @var string
     */
    private $_mimetype;

    /**
     * Extension of the file.
     *
     * @var string
     */
    private $_extension;

    /**
     * Is the download streamed (for media files).
     *
     * @var bool
     */
    private $_is_stream = false;

    /**
     * There isn't a direcly download link available for those files and security check by Google prevents them to be downloaded directly.
     *
     * @var int
     */
    private $_max_file_size_without_warning = 26214400;

    public function __construct(CacheNode $cached_node, $mimetype = 'default', $force_proxy = false)
    {
        $this->cached_node = $cached_node;

        // Use the orginial entry if the file/folder is a shortcut
        if ($cached_node->is_shortcut()) {
            $original_node = $cached_node->get_original_node();

            if (!empty($original_node)) {
                $this->cached_node = $original_node;
            }
        }

        $this->_force_proxy = $force_proxy;

        $this->_mimetype = $_REQUEST['mimetype'] ?? $mimetype;
        $this->_extension = $_REQUEST['extension'] ?? $this->get_entry()->get_extension();
        $this->_is_stream = (isset($_REQUEST['action']) && 'useyourdrive-stream' === $_REQUEST['action']);

        // Update mimetype for Google Docs
        $export_formats = $this->cached_node->get_entry()->get_save_as();

        if ('default' === $this->_mimetype && !empty($export_formats)) {
            $format = $export_formats['PDF'] ?? reset($export_formats);
            $this->_mimetype = $format['mimetype'];
            $this->_extension = $format['extension'];
        }

        $this->_set_content_url();

        wp_using_ext_object_cache(false);

        $this->_init_download_method();
    }

    public function start_download()
    {
        // Check if usage limits are hit
        if (!$this->is_stream() && $download_limit_hit_message = Restrictions::has_reached_download_limit($this->get_cached_node()->get_id(), false)) {
            header('Content-disposition: attachment; filename=Download limit exceeded - '.$this->get_cached_node()->get_name().'.empty');

            if ('Firefox' === Helpers::get_browser_name()) {
                header('Content-type: text/plain');
                echo $download_limit_hit_message;

                exit;
            }

            http_response_code(429);

            exit;
        }

        // Execute download Hook
        do_action('useyourdrive_download', $this->get_cached_node(), $this);

        if (isset($_REQUEST['raw'])) {
            $this->_process_download();

            return;
        }

        // Log Download
        if ('default' === $this->_mimetype) {
            $event_type = $this->is_stream() ? 'useyourdrive_streamed_entry' : 'useyourdrive_downloaded_entry';

            if ('useyourdrive_streamed_entry' === $event_type && in_array($this->get_entry()->get_extension(), ['vtt', 'srt'])) {
                // Don't log VTT captions when requested for video stream
            } else {
                do_action('useyourdrive_log_event', $event_type, $this->get_cached_node());
            }
        } else {
            do_action('useyourdrive_log_event', 'useyourdrive_downloaded_entry', $this->get_cached_node(), ['exported' => strtoupper($this->get_extension())]);
        }

        // Send email if needed
        if ('1' === Processor::instance()->get_shortcode_option('notificationdownload') && !$this->is_stream()) {
            Processor::instance()->send_notification_email('download', [$this->get_cached_node()]);
        }

        // Finally, start the download
        $this->_process_download();
    }

    public function redirect_to_content()
    {
        // Files larger than a certain size can only be streamed unfortunately as Google prevents them to be downloaded directly.
        if ($this->get_entry()->get_size() < $this->_max_file_size_without_warning) {
            header('Location: '.$this->get_content_url());

            exit;
        }

        // Download larger files via export link if possible, otherwise start streaming
        if ($this->_can_redirect_for_large_file()) {
            $this->_save_redirect_for_large_file();
            $this->_redirect_for_large_file($this->get_content_url());
        } else {
            $this->set_download_method('proxy');
            $this->_process_download();
        }

        exit;
    }

    public function redirect_to_export()
    {
        header('Location: '.$this->get_content_url());

        exit;
    }

    public function stream_content()
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        @session_write_close();

        // Stop WP from buffering, and discard the current buffer.
        $levels = ob_get_level();
        for ($i = 0; $i < $levels; ++$i) {
            ob_end_clean();
        }

        $chunk_size = $this->get_chunk_size();

        $size = $this->get_cached_node()->get_entry()->get_size();

        $length = $size;           // Content length
        $start = 0;               // Start byte
        $end = $size - 1;       // End byte
        header('Accept-Ranges: bytes');
        header('Content-Type: '.$this->get_cached_node()->get_entry()->get_mimetype());

        $seconds_to_cache = 60 * 60 * 24;
        $ts = gmdate('D, d M Y H:i:s', time() + $seconds_to_cache).' GMT';
        header("Expires: {$ts}");
        header('Pragma: cache');
        header("Cache-Control: max-age={$seconds_to_cache}");

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_end = $end;
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            if (false !== strpos($range, ',')) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes {$start}-{$end}/{$size}");

                exit;
            }

            if ('-' == $range) {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = (int) $range[0];

                if (isset($range[1]) && is_numeric($range[1])) {
                    $c_end = (int) $range[1];
                } else {
                    $c_end = $size;
                }

                if ($c_end - $c_start > $chunk_size) {
                    $c_end = $c_start + $chunk_size;
                }
            }
            $c_end = ($c_end > $end) ? $end : $c_end;

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes {$start}-{$end}/{$size}");

                exit;
            }

            $start = $c_start;

            $end = $c_end;
            $length = $end - $start + 1;
            header('HTTP/1.1 206 Partial Content');
        }

        header("Content-Range: bytes {$start}-{$end}/{$size}");
        header('Content-Length: '.$length);

        $chunk_start = $start;

        Helpers::set_time_limit(0);

        while ($chunk_start <= $end) {
            // Output the chunk

            $chunk_end = ((($chunk_start + $chunk_size) > $end) ? $end : $chunk_start + $chunk_size);
            $this->_stream_get_chunk($chunk_start, $chunk_end);

            $chunk_start = $chunk_end + 1;

            $this->throttle();
        }
    }

    /**
     * Callback function for CURLOPT_WRITEFUNCTION, This is what prints the chunk.
     *
     * @param CurlHandle $ch
     * @param string     $str
     *
     * @return type
     */
    public function _stream_chunk_to_output($ch, $str)
    {
        echo $str;

        return strlen($str);
    }

    /**
     * Exports are generated on the fly via the API.
     */
    public function export_content()
    {
        if ('redirect' === $this->get_download_method() && $this->get_entry()->get_size() <= 10485760 && API::has_permission($this->get_cached_node()->get_id())) {
            header('Location: '.$this->get_entry()->get_export_link($this->_mimetype));

            return;
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        @session_write_close();

        // Stop WP from buffering, and discard the current buffer.
        $levels = ob_get_level();
        for ($i = 0; $i < $levels; ++$i) {
            ob_end_clean();
        }

        $filename = apply_filters('useyourdrive_download_set_filename', $this->get_cached_node()->get_name().'.'.$this->get_extension(), $this->get_cached_node());
        header('Content-Disposition: attachment; '.sprintf('filename="%s"; ', rawurlencode($filename)).sprintf("filename*=utf-8''%s", rawurlencode($filename)));
        $this->_stream_get_chunk(null, null, false);

        exit;
    }

    public function get_api_url()
    {
        if ('default' !== $this->_mimetype) {
            return 'https://www.googleapis.com/drive/v3/files/'.$this->get_cached_node()->get_id().'/export?alt=media&mimeType='.$this->_mimetype;
        }

        return 'https://www.googleapis.com/drive/v3/files/'.$this->get_cached_node()->get_id().'?alt=media';
    }

    public function get_content_url()
    {
        return $this->_content_url;
    }

    public function get_download_method()
    {
        return $this->_download_method;
    }

    public function get_cached_node()
    {
        return $this->cached_node;
    }

    public function get_entry()
    {
        return $this->get_cached_node()->get_entry();
    }

    public function get_mimetype()
    {
        return $this->_mimetype;
    }

    public function get_extension()
    {
        return $this->_extension;
    }

    public function is_stream()
    {
        return $this->_is_stream;
    }

    public function get_force_proxy()
    {
        return $this->_force_proxy;
    }

    public function set_force_proxy($_force_proxy)
    {
        $this->_force_proxy = $_force_proxy;
    }

    public function set_download_method($method)
    {
        $this->_download_method = $method;
    }

    public static function throttle()
    {
        switch (Settings::get('server_throttle')) {
            case 'low':
                $usleep = 50 * 1000;

                break;

            case 'medium':
                $usleep = 200 * 1000;

                break;

            case 'high':
                $usleep = 1000 * 1000;

                break;

            case 'off':
            default:
                $usleep = 0;

                break;
        }

        usleep($usleep);
    }

    public static function get_chunk_size()
    {
        switch (Settings::get('server_throttle')) {
            case 'medium':
                $chunk_size = 1024 * 1024 * 20;

                break;

            case 'high':
                $chunk_size = 1024 * 1024 * 2;

                break;

            case 'low':
            case 'off':
            default:
                $chunk_size = 1024 * 1024 * 50;

                break;
        }

        return min(Helpers::get_free_memory_available() - (1024 * 1024 * 5), $chunk_size); // Chunks size or less if memory isn't sufficient;
    }

    private function _can_redirect_for_large_file()
    {
        // Unclear what the exact limit for automated queries is.
        // Higher values will likely cause download problems.
        $downloads_per_hour = 0;

        $latest_downloads = get_site_option('wpcp_google_download_list', []);
        $hour_ago = strtotime('-1 hour');

        foreach ($latest_downloads as $i => $time) {
            if ($time < $hour_ago) {
                unset($latest_downloads[$i]);
            }
        }

        return count($latest_downloads) < $downloads_per_hour;
    }

    private function _save_redirect_for_large_file()
    {
        $latest_downloads = get_site_option('wpcp_google_download_list', []);
        $latest_downloads[] = time();

        update_site_option('wpcp_google_download_list', $latest_downloads);
    }

    private function _redirect_for_large_file($web_url)
    {
        // Redirect to final download url if it is still cached.
        $download_url = get_transient('useyourdrive'.(($this->is_stream()) ? 'stream' : 'download').'_'.$this->get_entry()->get_id());
        if (!empty($download_url)) {
            header('Location: '.$download_url);

            return true;
        }

        // Add Resources key to give permission to access the item
        if ($this->get_entry()->has_resourcekey()) {
            $headers['X-Goog-Drive-Resource-Keys'] = $this->get_entry()->get_id().'/'.$this->get_entry()->get_resourcekey();
        }

        $web_url = \str_replace('drive.google.com/uc?id=', 'drive.usercontent.google.com/download?id=', $web_url);

        $wp_remote_options = [
            'headers' => [
                'user-agent' => 'WPCP '.USEYOURDRIVE_VERSION,
            ],
        ];

        $response = wp_remote_get($web_url, $wp_remote_options);
        if (!empty($response) && !\is_wp_error($response)) {
            $response_body = wp_remote_retrieve_body($response);
            $headers = \wp_remote_retrieve_headers($response);
        } else {
            $this->set_download_method('proxy');
            $this->_process_download();

            exit;
        }

        // If location is found, set in cache and redirect user to url
        if (isset($headers['location'])) {
            set_transient('useyourdrive'.(($this->is_stream()) ? 'stream' : 'download').'_'.$this->get_entry()->get_id(), $headers['location'], MINUTE_IN_SECONDS * 2);

            header('Location: '.$headers['location']);

            return true;
        }

        $cookie = null;
        if (!empty($headers['set-cookie'])) {
            $cookie = new \WP_Http_Cookie($headers['set-cookie']);
        }

        // If no location is found, try find the download url in the body and load that url instead
        preg_match_all('/ type="hidden" name="(?<name>.*?)" value="(?<value>.*?)"/m', $response_body, $params, PREG_SET_ORDER, 0);

        $found_redirect = false;
        $download_url = 'https://drive.usercontent.google.com/download?id='.$this->get_entry()->get_id();
        foreach ($params as $param) {
            if ('id' === $param['name']) {
                continue;
            }

            if ('uuid' === $param['name']) {
                $found_redirect = true;
            }

            $download_url .= '&'.$param['name'].'='.$param['value'];
        }

        if ($found_redirect) {
            $wp_remote_options['cookies'] = $cookie;
            $response = wp_remote_head($download_url, $wp_remote_options);

            set_transient('useyourdrive'.(($this->is_stream()) ? 'stream' : 'download').'_'.$this->get_entry()->get_id(), $download_url, MINUTE_IN_SECONDS * 2);

            header('Location: '.$download_url);

            return true;
        }

        // If nothing works, fallback to proxy method
        $this->set_download_method('proxy');
        $this->_process_download();

        exit;
    }

    private function _set_content_url()
    {
        $direct_download_link = $this->get_entry()->get_direct_download_link();

        // Set download URL for binary files
        if ('default' === $this->get_mimetype() && !empty($direct_download_link)) {
            return $this->_content_url = $direct_download_link;
        }

        // Set download URL for exporting documents with specific mimetype
        return $this->_content_url = $this->get_entry()->get_export_link($this->_mimetype);
    }

    /**
     * Set the download method for this entry
     * Files can be streamed using the server as a proxy ('proxy') or
     * the user can be redirected to download url ('redirect').
     *
     * As the Google API doesn't offer temporarily download links,
     * the specific download method depends on several settings
     *
     * @return 'proxy'|'redirect'
     */
    private function _init_download_method()
    {
        // Is plugin forced to use the proxy method via the plugin options?
        if ($this->_force_proxy || 'proxy' === Settings::get('download_method')) {
            return $this->_download_method = 'proxy';
        }

        // Is download via shared links prohibitted by API?
        $copy_disabled = $this->get_entry()->get_permission('copyRequiresWriterPermission');
        if ($copy_disabled) {
            return $this->_download_method = 'proxy';
        }

        // Is file already shared ?
        $is_shared = API::has_permission($this->get_cached_node()->get_id());
        if ($is_shared) {
            return $this->_download_method = 'redirect';
        }

        // Can the sharing permissions of the file be updated via the plugin?
        $can_update_permissions = ('Yes' === Settings::get('manage_permissions')) && $this->get_entry()->get_permission('canshare');
        if (false === $can_update_permissions) {
            return $this->_download_method = 'proxy';
        }

        // Update the Sharing Permissions
        $is_sharing_permission_updated = API::set_permission($this->get_cached_node()->get_id(), 'reader', 'download');
        if (false === $is_sharing_permission_updated) {
            return $this->_download_method = 'proxy';
        }

        return $this->_download_method = 'redirect';
    }

    private function _process_download()
    {
        switch ($this->get_download_method()) {
            case 'redirect':
                if ('default' === $this->_mimetype) {
                    $this->redirect_to_content();
                } else {
                    $this->export_content();
                }

                break;

            case 'proxy':
            default:
                if ('default' === $this->_mimetype) {
                    if (isset($_REQUEST['action']) && 'useyourdrive-stream' === $_REQUEST['action']) {
                        $this->stream_content();
                    } else {
                        $filename = apply_filters('useyourdrive_download_set_filename', $this->get_cached_node()->get_name(), $this->get_cached_node());
                        header('Content-Disposition: attachment; '.sprintf('filename="%s"; ', rawurlencode($filename)).sprintf("filename*=utf-8''%s", rawurlencode($filename)));
                        $this->stream_content();
                    }
                } else {
                    $this->export_content();
                }

                break;
        }

        exit;
    }

    /**
     * Function to get a range of bytes via the API.
     *
     * @param int   $start
     * @param int   $end
     * @param mixed $chunked
     */
    private function _stream_get_chunk($start, $end, $chunked = true)
    {
        if ($chunked) {
            $headers = ['Range' => 'bytes='.$start.'-'.$end];
        }

        // Add Resources key to give permission to access the item
        if ($this->get_entry()->has_resourcekey()) {
            $headers['X-Goog-Drive-Resource-Keys'] = $this->get_entry()->get_id().'/'.$this->get_entry()->get_resourcekey();
        }

        $request = new \UYDGoogle_Http_Request($this->get_api_url(), 'GET', $headers);
        $request->disableGzip();

        App::instance()->get_sdk_client()->getIo()->setOptions(
            [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RANGE => null,
                CURLOPT_NOBODY => null,
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => [$this, '_stream_chunk_to_output'],
                CURLOPT_CONNECTTIMEOUT => null,
                CURLOPT_TIMEOUT => null,
            ]
        );

        App::instance()->get_sdk_client()->getAuth()->authenticatedRequest($request);
    }
}
