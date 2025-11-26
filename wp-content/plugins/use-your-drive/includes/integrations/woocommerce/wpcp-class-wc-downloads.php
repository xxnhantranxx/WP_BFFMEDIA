<?php

namespace TheLion\UseyourDrive\Integrations;

use TheLion\UseyourDrive\Accounts;
use TheLion\UseyourDrive\App;
use TheLion\UseyourDrive\CacheNode;
use TheLion\UseyourDrive\Client;
use TheLion\UseyourDrive\Core;
use TheLion\UseyourDrive\Download;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Zip;

class WooCommerce_Downloads
{
    public function __construct()
    {
        // Actions
        add_action('woocommerce_download_file_force', [$this, 'do_direct_download'], 1, 2);
        add_action('woocommerce_download_file_xsendfile', [$this, 'do_xsendfile_download'], 1, 2);
        add_action('woocommerce_download_file_redirect', [$this, 'do_redirect_download'], 1, 2);

        if (class_exists('WC_Product_Documents')) {
            add_action('wp_ajax_nopriv_useyourdrive-wcpd-direct-download', [$this, 'wc_product_documents_download_via_url']);
            add_action('wp_ajax_useyourdrive-wcpd-direct-download', [$this, 'wc_product_documents_download_via_url']);
            add_filter('wc_product_documents_link_target', [$this, 'wc_product_documents_open_link_in_new_window'], 10, 4);
            add_filter('wc_product_documents_get_sections', [$this, 'wc_product_documents_update_document_urls'], 10, 3);
        }

        // Load custom scripts in the admin area
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'add_scripts']);
            add_action('edit_form_advanced', [$this, 'render_file_selector'], 1, 1); // Classic Editor on Product edit page
            add_action('block_editor_meta_box_hidden_fields', [$this, 'render_file_selector'], 1, 1); // Gutenberg Editor on Product edit page
        }

        // Add plugin to order table if a directory is downloadable
        add_action('woocommerce_order_details_before_order_table', [$this, 'render_download_folder'], 11);
        add_action('woocommerce_order_details_after_order_table', [$this, 'render_download_folder'], 11);

        // Update the download link in the order table
        add_filter('woocommerce_order_get_downloadable_items', [$this, 'filter_woocommerce_order_get_downloadable_items'], 10, 2);
    }

    /**
     * Render the File Browser to allow the user to add files to the Product.
     *
     * @return string
     */
    public function render_file_selector(?\WP_Post $post = null)
    {
        if (isset($post) && 'product' !== $post->post_type) {
            return;
        }

        include 'template_file_selector.php';
    }

    /**
     * Load all the required Script and Styles.
     */
    public function add_scripts()
    {
        $current_screen = get_current_screen();

        if (!in_array($current_screen->id, ['product', 'shop_order'])) {
            return;
        }

        Core::instance()->load_styles();
        Core::instance()->load_scripts();

        // register scripts/styles
        wp_register_style('useyourdrive-woocommerce', plugins_url('backend.css', __FILE__), USEYOURDRIVE_VERSION);
        wp_register_script('useyourdrive-woocommerce', plugins_url('backend.js', __FILE__), ['jquery'], USEYOURDRIVE_VERSION);

        // enqueue scripts/styles
        wp_enqueue_style('useyourdrive-woocommerce');
        wp_enqueue_script('useyourdrive-woocommerce');

        wp_enqueue_script('UseyourDrive.AdminUI');
        wp_enqueue_style('WPCloudPlugins.AdminUI');

        // register translations
        $translation_array = [
            'choose_from' => sprintf(esc_html__('Add File', 'wpcloudplugins'), 'Google Drive'),
            'download_url' => 'https://drive.google.com/open?action=useyourdrive-wc-direct-download&id=',
            'file_browser_url' => USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-getwoocommercepopup',
            'wcpd_url' => USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-wcpd-direct-download&id=',
            'notification_success_file_msg' => sprintf(esc_html__('%s added as downloadable file!', 'wpcloudplugins'), '{filename}'),
            'notification_failed_file_msg' => sprintf(esc_html__('Cannot add %s!', 'wpcloudplugins'), '{filename}'),
        ];

        wp_localize_script('useyourdrive-woocommerce', 'useyourdrive_woocommerce_translation', $translation_array);
    }

    /**
     * Render the Download Box on the Order View.
     *
     * @param int $order_id
     */
    public function render_download_folder($order_id)
    {
        /* Only render the upload form once
         * Preferably before the order table, but not all templates have this hook available */
        if (doing_action('woocommerce_order_details_before_order_table')) {
            remove_action('woocommerce_order_details_after_order_table', [$this, 'render_download_folder'], 11);
        }

        if (doing_action('woocommerce_order_details_after_order_table')) {
            remove_action('woocommerce_order_details_before_order_table', [$this, 'render_download_folder'], 11);
        }

        $order = new \WC_Order($order_id);

        if (false === $order->has_downloadable_item() || false === $order->is_download_permitted()) {
            return false;
        }

        foreach ($order->get_items() as $order_item) {
            $product = $order_item->get_product();

            foreach ($product->get_downloads() as $download_key => $file) {
                $url = $file->get_file();

                if (false === strpos($url, 'useyourdrive-wc-direct-download')) {
                    continue;
                }

                $cached_entry = $this->get_entry_for_download_by_url($url);

                if (empty($cached_entry)) {
                    echo '<em>'.esc_html__('File can not be found', 'wpcloudplugins').': '.$file->get_name().'</em>';

                    continue;
                }

                if ($cached_entry->get_entry()->is_file()) {
                    continue;
                }

                $download = self::get_download_by_key($order->get_id(), $product->get_id(), $download_key);

                if ('' !== $download->get_downloads_remaining()) {
                    // If download are restricted to a specific number, only allow ZIP downloads of the complete folder
                    continue;
                }

                if ('' !== $download->get_downloads_remaining() && 0 >= $download->get_downloads_remaining()) {
                    // Sorry, you have reached your download limit for this file
                    continue;
                }

                if (!is_null($download->get_access_expires()) && $download->get_access_expires()->getTimestamp() < strtotime('midnight', time())) {
                    // Sorry, this download has expired
                    continue;
                }

                $shortcode_params = [
                    'mode' => 'files',
                    'account' => $cached_entry->get_account_id(),
                    'dir' => $cached_entry->get_id(),
                    'filelayout' => 'grid',
                    'viewrole' => 'all',
                    'downloadrole' => 'all',
                    'candownloadzip' => '1',
                    'searchcontents' => '1',
                    'maxheight' => '300px',
                ];

                $shortcode_params['wc_order_id'] = $order_id;
                $shortcode_params['wc_product_id'] = $product->get_id();
                $shortcode_params['wc_item_id'] = $order_item->get_id();

                $shortcode_params = apply_filters('useyourdrive_woocommerce_download_module_params', $shortcode_params, $order, $product->get_id(), $order_item->get_id(), $this);

                echo '<h2 id="downloads_'.$cached_entry->get_id().'">'.esc_html__('Downloads', 'woocommerce').' - '.$file->get_name().'</h2>';
                echo Processor::instance()->create_from_shortcode($shortcode_params);
            }
        }
    }

    /**
     * @param string $file_path
     *
     * @return CacheNode
     */
    public function get_entry_for_download_by_url($file_path)
    {
        $download_url = parse_url($file_path);

        if (isset($download_url['query'])) {
            parse_str($download_url['query'], $download_url_query);
        } else {
            // In some occasions the file name contains a #, causing the parameters to end up in the fragment part of the url
            parse_str($download_url['fragment'], $download_url_query);
        }

        $entry_id = $download_url_query['id'];

        // Fallback for old embed urls without account info
        if (!isset($download_url_query['account_id'])) {
            $primary_account = Accounts::instance()->get_primary_account();
            if (null === $primary_account) {
                return false;
            }
            $account_id = $primary_account->get_id();
        } else {
            $account_id = $download_url_query['account_id'];
        }

        $account = Accounts::instance()->get_account_by_id($account_id);

        if (null === $account) {
            return false;
        }

        App::set_current_account($account);
        $cachedentry = Client::instance()->get_entry($entry_id, false);

        if (false === $cachedentry) {
            return false;
        }

        return $cachedentry;
    }

    public function download_entry(CacheNode $cached_entry, $force_download = false)
    {
        if ($cached_entry->get_entry()->is_dir()) {
            $force_download_as_zip = isset($_REQUEST['as_zip']);

            $product_id = absint($_GET['download_file']);
            $order_id = wc_get_order_id_by_order_key(wc_clean(wp_unslash($_GET['order'])));

            if (empty($order_id)) {
                self::download_error(esc_html__('Order not found', 'woocommerce'));
            }

            $order = wc_get_order($order_id);

            if (empty($order)) {
                self::download_error(esc_html__('Order not found', 'woocommerce'));
            }

            $download_key = empty($_GET['key']) ? '' : sanitize_text_field(wp_unslash($_GET['key']));
            $download = self::get_download_by_key($order_id, $product_id, $download_key);

            if ('' !== $download->get_downloads_remaining() || $force_download_as_zip) {
                // If download are restricted to a specific number, only allow ZIP downloads of the complete folder
                $_REQUEST['files'] = [$cached_entry->get_id()];
                Processor::instance()->set_requested_entry($cached_entry->get_id());
                $zip = new Zip($cached_entry->get_id());
                $zip->do_zip();
            } else {
                wp_redirect($order->get_view_order_url().'#downloads_'.$cached_entry->get_entry()->get_id());
            }

            exit;
        }

        $download = new Download($cached_entry, 'default', $force_download);
        $download->start_download();

        exit;
    }

    /**
     * Update the download link in the order table
     * For guest users change the download link to a ZIP download link.
     *
     * @param array    $downloads
     * @param WC_Order $order
     */
    public function filter_woocommerce_order_get_downloadable_items($downloads, $order)
    {
        $user = get_user_by('email', $order->get_billing_email());

        if (false === empty($user->ID)) {
            // Customer is registered and can access the order details page using its account
            return $downloads;
        }

        // Loop though downloads
        foreach ($downloads as $key => $download) {
            if (false === strpos($download['file']['file'], 'useyourdrive-wc-direct-download')) {
                continue;
            }

            // Replace
            $downloads[$key]['download_url'] .= '&as_zip=1';
        }

        return $downloads;
    }

    public function wc_product_documents_download_via_url()
    {
        if (!isset($_REQUEST['id'])) {
            return false;
        }

        if (!isset($_REQUEST['pid'])) {
            return false;
        }

        $entry_id = $_REQUEST['id'];
        $account_id = $_REQUEST['account_id'] ?? null;

        $product_id = $_REQUEST['pid'];
        $documents_collection = new \WC_Product_Documents_Collection($product_id);

        foreach ($documents_collection->get_sections() as $section) {
            foreach ($section->get_documents() as $position => $document) {
                $file_location = $document->get_file_location();

                if (false === strpos($file_location, 'useyourdrive-wcpd-direct-download')) {
                    continue;
                }

                // Fallback for old urls without account info
                if (empty($account_id)) {
                    $primary_account = Accounts::instance()->get_primary_account();
                    if (null === $primary_account) {
                        return false;
                    }
                    $account_id = $primary_account->get_id();
                }

                $account = Accounts::instance()->get_account_by_id($account_id);

                if (null === $account) {
                    return false;
                }

                App::set_current_account($account);

                if (false !== strpos($file_location, 'id='.$entry_id)) {
                    $cached_entry = Client::instance()->get_entry($entry_id, false);
                    $this->download_entry($cached_entry);

                    exit;
                }
            }
        }

        self::download_error(esc_html__('File not found', 'woocommerce'));
    }

    public function wc_product_documents_open_link_in_new_window($target, $product, $section, $document)
    {
        $file_location = $document->get_file_location();

        if (false === strpos($file_location, 'useyourdrive-wcpd-direct-download')) {
            return false; // Do nothing
        }

        return '_blank" class="lightbox-group" title="'.$document->get_label();
    }

    public function wc_product_documents_update_document_urls($sections, $collection, $include_empty)
    {
        $product_id = $collection->get_product_id();
        if (empty($product_id)) {
            return $sections;
        }

        foreach ($sections as $section) {
            foreach ($section->get_documents() as $position => $document) {
                $file_location = $document->get_file_location();

                if (false === strpos($file_location, 'useyourdrive-wcpd-direct-download')) {
                    continue;
                }

                if (false !== strpos($file_location, 'pid')) {
                    continue;
                }

                $section->add_document(new \WC_Product_Documents_Document($document->get_label(), $file_location.'&pid='.$collection->get_product_id()), $position);
            }
        }

        return $sections;
    }

    public function do_direct_download($file_path, $filename)
    {
        if (false === strpos($file_path, 'useyourdrive-wc-direct-download')) {
            return false; // Do nothing
        }

        $cached_entry = $this->get_entry_for_download_by_url($file_path);

        if (empty($cached_entry)) {
            self::download_error(esc_html__('File not found', 'woocommerce'));
        }

        $this->download_entry($cached_entry, true);

        exit;
    }

    public function do_xsendfile_download($file_path, $filename)
    {
        if (false === strpos($file_path, 'useyourdrive-wc-direct-download')) {
            return false; // Do nothing
        }

        // Fallback
        $this->do_direct_download($file_path, $filename);
    }

    public function do_redirect_download($file_path, $filename)
    {
        if (false === strpos($file_path, 'useyourdrive-wc-direct-download')) {
            return false; // Do nothing
        }

        $cached_entry = $this->get_entry_for_download_by_url($file_path);

        if (empty($cached_entry)) {
            self::download_error(esc_html__('File not found', 'woocommerce'));
        }

        $this->download_entry($cached_entry);

        exit;
    }

    public static function get_download_url_transient($entry_id)
    {
        return get_transient('useyourdrive_wc_download_'.$entry_id);
    }

    public static function set_download_url_transient($entry_id, $url)
    {
        // Update progress
        return set_transient('useyourdrive_wc_download_'.$entry_id, $url, HOUR_IN_SECONDS);
    }

    /**
     * Check if product is a variation
     * Upload meta data is currently only stored on the parent product.
     *
     * @param mixed $product
     *
     * @return bool
     */
    public function is_product_variation($product)
    {
        $product_type = $product->get_type();

        return 'variation' === $product_type;
    }

    /**
     * Get content type of a download.
     *
     * @param string $file_path
     *
     * @return string
     */
    private static function get_download_content_type($file_path)
    {
        $file_extension = strtolower(substr(strrchr($file_path, '.'), 1));
        $ctype = 'application/force-download';

        foreach (get_allowed_mime_types() as $mime => $type) {
            $mimes = explode('|', $mime);
            if (in_array($file_extension, $mimes)) {
                $ctype = $type;

                break;
            }
        }

        return $ctype;
    }

    /**
     * Set headers for the download.
     *
     * @param string $file_path
     * @param string $filename
     */
    private static function download_headers($file_path, $filename)
    {
        self::check_server_config();
        self::clean_buffers();
        nocache_headers();

        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-Type: '.self::get_download_content_type($file_path));
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; '.sprintf('filename="%s"; ', rawurlencode($filename)).sprintf("filename*=utf-8''%s", rawurlencode($filename)));
        header('Content-Transfer-Encoding: binary');

        if ($size = @filesize($file_path)) {
            header('Content-Length: '.$size);
        }
    }

    /**
     * Check and set certain server config variables to ensure downloads work as intended.
     */
    private static function check_server_config()
    {
        wc_set_time_limit(0);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        @session_write_close();
    }

    /**
     * Clean all output buffers.
     *
     * Can prevent errors, for example: transfer closed with 3 bytes remaining to read.
     */
    private static function clean_buffers()
    {
        if (ob_get_level()) {
            $levels = ob_get_level();
            for ($i = 0; $i < $levels; ++$i) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }
    }

    /**
     * Die with an error message if the download fails.
     *
     * @param string $message
     * @param string $title
     * @param int    $status
     */
    private static function download_error($message, $title = '', $status = 404)
    {
        if (!strstr($message, '<a ')) {
            $message .= ' <a href="'.esc_url(get_site_url()).'">Go to back</a>';
        }
        wp_die($message, $title, ['response' => $status]);
    }

    private static function get_download_by_key($order_id, $product_id, $download_key)
    {
        $data_store = \WC_Data_Store::load('customer-download');

        $download_ids = $data_store->get_downloads(
            [
                'order_id' => $order_id,
                'product_id' => $product_id,
                'download_id' => $download_key, // WPCS: input var ok, CSRF ok, sanitization ok.
                'orderby' => 'downloads_remaining',
                'order' => 'DESC',
                'limit' => 1,
                'return' => 'ids',
            ]
        );

        return new \WC_Customer_Download(current($download_ids));
    }
}

new WooCommerce_Downloads();
