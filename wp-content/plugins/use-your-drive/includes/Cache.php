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

class Cache
{
    /**
     * The single instance of the class.
     *
     * @var Cache
     */
    protected static $_instance;

    /**
     * Set after how much time the cached noded should be refreshed.
     * This value can be overwritten by Cloud Service Cache classes
     * Default:  needed for download/thumbnails urls (1 hour?).
     *
     * @var int
     */
    protected $_max_entry_age = 1800;

    /**
     * The file name of the requested cache. This will be set in construct.
     *
     * @var string
     */
    private $_cache_name;

    /**
     * Contains the location to the cache file.
     *
     * @var string
     */
    private $_cache_location;

    /**
     * Contains the file handle in case the plugin has to work
     * with a file for unlocking/locking.
     *
     * @var type
     */
    private $_cache_file_handle;

    /**
     * $_nodes contains all the cached files that are present
     * in the Cache File or Database.
     *
     * @var CacheNode[]
     */
    private $_nodes = [];

    /**
     * ID of the root node.
     *
     * @var string
     */
    private $_root_node_id;

    /**
     * Is set to true when a change has been made in the cache.
     * Forcing the plugin to save the cache when needed.
     *
     * @var bool
     */
    private $_updated = false;

    /**
     * $_last_update contains a timestamp of the latest check
     * for new updates.
     *
     * @var array
     */
    private $_last_check_for_update = [];

    /**
     * $_last_id contains an ID of the latest update check
     * This can be anything (e.g. a File ID or Change ID), it differs per Cloud Service.
     *
     * @var array
     */
    private $_last_check_token = [];

    /**
     * How often do we need to poll for changes? (default: 15 minutes)
     * Each Cloud service has its own optimum setting.
     * WARNING: Please don't lower this setting when you are not using your own Apps!!!
     *
     * @var int
     */
    private $_max_change_age = 900;

    public function __construct()
    {
        $cache_id = get_current_blog_id();
        if (null !== App::get_current_account()) {
            $cache_id = App::get_current_account()->get_id();
        }

        $this->_cache_name = Helpers::filter_filename($cache_id, false).'.index';
        $this->_cache_location = USEYOURDRIVE_CACHEDIR.'/'.$this->_cache_name;

        // Load Cache
        $this->load_cache();
    }

    public function __destruct()
    {
        $this->update_cache();
    }

    /**
     * Cache Instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return Cache - Cache instance
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

    public static function instance_unload()
    {
        if (is_null(self::$_instance)) {
            return;
        }

        self::instance()->update_cache();
        self::$_instance = null;
    }

    public function load_cache()
    {
        $cache = $this->_read_local_cache('close');

        if (function_exists('gzdecode')) {
            $cache = @gzdecode($cache);
        }

        // Unserialize the Cache, and reset if it became somehow corrupt
        if (!empty($cache) && !is_array($cache)) {
            $this->_unserialize_cache($cache);
        }

        // Set all Parent and Children
        if (!empty($this->_nodes)) {
            foreach ($this->_nodes as $node) {
                $this->init_cache_node($node);
            }
        }
    }

    public function init_cache_node($node = [])
    {
        $id = $node['_id'];
        $node = $this->_nodes[$id] = new CacheNode($node);

        if ($node->has_parent()) {
            $parent_id = $node->get_parent();

            if ($parent_id instanceof CacheNode) {
                return $node;
            }

            $parent_node = $this->_nodes[$parent_id] ?? false;

            if (!$parent_node instanceof CacheNode) {
                $parent_node = $this->init_cache_node($parent_node);
            }

            if (false !== $parent_node) {
                $node->set_parent($parent_node);
            }
        }

        if ($node->has_children()) {
            foreach ($node->get_children() as $child) {
                if ($child instanceof CacheNode) {
                    continue;
                }

                $child_id = $child;
                $child_node = $this->_nodes[$child_id] ?? false;

                if (!$child_node instanceof CacheNode) {
                    $child_node = $this->init_cache_node($child_node);
                }

                if (false !== $child_node) {
                    $child_node->set_parent($node);
                }
            }
        }

        return $node;
    }

    public function reset_cache()
    {
        if (\function_exists('wp_cache_supports') && \wp_cache_supports('flush_group')) {
            \wp_cache_flush_group('wpcp-'.CORE::$slug.'-nodes');
            \wp_cache_flush_group('wpcp-'.CORE::$slug.'-entries');
        }

        $this->_nodes = [];
        $this->reset_last_check_for_update();
        $this->reset_last_check_token();
        $this->update_cache();

        return true;
    }

    public function update_cache($clear_request_cache = true)
    {
        if ($this->is_updated() && !empty(App::get_current_account())) {
            // Clear Cached Requests, not needed if we only pulled for updates without receiving any changes
            if ($clear_request_cache && null !== App::get_current_account()) {
                CacheRequest::clear_local_cache_for_shortcode(App::get_current_account()->get_id(), Processor::instance()->get_listtoken());
            }

            // Save each loaded folder
            foreach ($this->get_nodes() as $node) {
                if ($node->is_initialized() && $node->is_dir() && $node->is_updated()) {
                    $node->update_cache();
                }
            }

            $this->_save_local_cache();

            $this->set_updated(false);
        }
    }

    public function is_cached($value, $findby = 'id', $as_parent = false)
    {
        // Find the node by ID/NAME
        $node = null;
        if ('id' === $findby) {
            $node = $this->get_node_by_id($value);
        } elseif ('name' === $findby) {
            $node = $this->get_node_by_name($value);
        }

        // Return if nothing can be found in the cache
        if (empty($node)) {
            return false;
        }

        if (null === $node->get_entry()) {
            return false;
        }

        if (!$as_parent && !$node->is_loaded()) {
            return false;
        }

        // Check if the children of the node are loaded.
        if (!$as_parent && !$node->has_loaded_children()) {
            return false;
        }

        // Check if the requested node is expired
        if (!$as_parent && $node->is_expired()) {
            return false;
        }

        return $node;
    }

    /**
     * @return CacheNode
     */
    public function add_to_cache(EntryAbstract $entry)
    {
        // Check if entry is present in cache
        $cached_node = $this->get_node_by_id($entry->get_id());

        /* If entry is not yet present in the cache,
         * create a new Node
         */
        if (false === $cached_node) {
            $cached_node = $this->add_node($entry);
        }

        if ($cached_node->is_virtual_folder()) {
            $entry->set_name($cached_node->get_name());
        }

        $cached_node->set_name($entry->get_name());

        $cached_node->set_updated();
        $this->set_updated();

        // Set new Expire date
        if ($entry->is_file()) {
            $cached_node->set_expired(time() + $this->get_max_entry_age());
        } else {
            $cached_node->set_is_dir();
        }

        // FIX: FOLDERS SHARED WITH ACCOUNT NOT ALWAYS PROVIDE PARENT INFOMRATION WHEN ITEM ITSELF IS LOADED
        if ('shared-with-me' === $entry->get_parent_id() && $cached_node->has_parent() && 'shared-with-me' !== $cached_node->get_parent()->get_id()) {
            $entry->set_parent_id($cached_node->get_parent()->get_id());
        }
        // END FIX;

        // Set new Entry in node
        $cached_node->set_entry($entry);
        $cached_node->set_loaded(true);

        // Set Loaded_Children to true if entry isn't a folder
        if ($entry->is_file()) {
            $cached_node->set_loaded_children(true);
        }

        // If $entry doesn't have a parent, it is the root or an orphan entry
        if (!$entry->has_parent()) {
            $cached_node->set_parent_found(true);
            $this->set_updated();

            return $cached_node;
        }

        /*
         * If parent of $entry doesn't exist in our cache yet,
         * We need to get it via the API
         */
        $parent_id = $entry->get_parent_id();

        $parent_in_tree = $this->is_cached($parent_id, 'id', 'as_parent');
        if ($parent_in_tree) {
            $cached_node->set_parent($parent_in_tree);
            $parent_in_tree->set_updated();
        } else {
            Client::instance()->get_folder($parent_id, false);
        }

        $cached_node->set_parent_found(true);

        // If entry is a shortcut, make sure that the original file is also present in cache
        if ($entry->is_shortcut()) {
            $target_id = $entry->get_shortcut_details('targetId');

            $cached_node->set_original_node_id($target_id);

            $shortcut_node = $this->get_node_by_id($target_id);
            if (false === $shortcut_node || null === $shortcut_node->get_entry()) {
                // Try to find the original node.
                $result = Client::instance()->get_entry($target_id, false);

                if (empty($result)) {
                    // It is possible that the shortcut is pointing to a removed file.
                    // In that case make the node inaccessible
                    $this->remove_from_cache($entry->get_id(), 'deleted');
                    Helpers::log_error('Shortcut points to a removed file', 'Cache', ['entry' => $entry->get_id()], __LINE__);

                    $this->set_updated();

                    // Return the cached Node
                    return $cached_node;
                }
                $shortcut_node = $this->get_node_by_id($target_id);
            }

            // Use Thumbnails from original file
            $entry->set_icon($shortcut_node->get_entry()->get_icon());
            $entry->set_thumbnail_icon($shortcut_node->get_entry()->get_thumbnail_icon());
            $entry->set_thumbnail_small($shortcut_node->get_entry()->get_thumbnail_small());
            $entry->set_thumbnail_small_cropped($shortcut_node->get_entry()->get_thumbnail_small_cropped());
            $entry->set_thumbnail_large($shortcut_node->get_entry()->get_thumbnail_large());
            $entry->set_thumbnail_original($shortcut_node->get_entry()->get_thumbnail_original());
            $cached_node->set_entry($entry);

            $shortcut_node->add_original_node_for([$entry->get_id()]);
        }

        $this->set_updated();

        // Return the cached Node
        return $cached_node;
    }

    public function remove_from_cache($entry_id, $reason = 'update')
    {
        $node = $this->get_node_by_id($entry_id);

        if (false === $node) {
            return false;
        }

        $node->set_updated();

        if ('update' === $reason) {
            $node->remove_parent();
        } elseif ('moved' === $reason) {
            $node->remove_parent();
        } elseif ('deleted' === $reason) {
            $node->remove_parent();
            $node->delete_cache();
            unset($this->_nodes[$entry_id]);
        }

        $this->set_updated();

        return true;
    }

    /**
     * @return bool|CacheNode
     */
    public function get_root_node()
    {
        if (0 === count($this->get_nodes())) {
            return false;
        }

        return $this->get_node_by_id($this->get_root_node_id());
    }

    public function get_root_node_id()
    {
        return $this->_root_node_id;
    }

    public function set_root_node_id($id)
    {
        return $this->_root_node_id = $id;
    }

    public function get_node_by_id($id, $loadoninit = true)
    {
        if (!isset($this->_nodes[$id])) {
            return false;
        }

        if ($loadoninit && !$this->_nodes[$id]->is_initialized() && $this->_nodes[$id]->is_dir()) {
            $this->_nodes[$id]->load();
        }

        return $this->_nodes[$id];
    }

    public function get_node_by_name($search_name, $parent = null)
    {
        if (!$this->has_nodes()) {
            return false;
        }

        $search_name = apply_filters('useyourdrive_cache_node_by_name_set_search_name', $search_name, $this);

        $parent_id = ($parent instanceof CacheNode) ? $parent->get_id() : $parent;

        /**
         * @var CacheNode $node
         */
        foreach ($this->_nodes as $node) {
            $node_name = apply_filters('useyourdrive_cache_node_by_name_set_node_name', $node->get_name(), $this);

            if ($node_name === $search_name) {
                if (null === $parent) {
                    return $node;
                }

                if ($node->is_in_folder($parent_id)) {
                    return $node;
                }
            }
        }

        return false;
    }

    public function get_drive_id_by_entry_id($entry_id)
    {
        $node = $this->get_node_by_id($entry_id);

        if (false === $node) {
            return null;
        }

        if (null !== $node->get_drive_id()) {
            return $node->get_drive_id();
        }

        if ($node->has_parent()) {
            return $this->get_drive_id_by_entry_id($node->get_parent()->get_id());
        }

        return null;
    }

    public function get_shortcut_nodes_by_id($id)
    {
        $shortcut_nodes = [];
        foreach ($this->_nodes as $node) {
            if ($id === $node->get_original_node_id()) {
                $shortcut_nodes[] = $node;
            }
        }

        return $shortcut_nodes;
    }

    public function has_nodes()
    {
        return count($this->_nodes) > 0;
    }

    /**
     * @return CacheNode[]
     */
    public function get_nodes()
    {
        return $this->_nodes;
    }

    public function add_node(EntryAbstract $entry)
    {
        $cached_node = new CacheNode(
            [
                '_id' => $entry->get_id(),
                '_drive_id' => $entry->get_drive_id(),
                '_account_id' => App::get_current_account()->get_id(),
                '_name' => $entry->get_name(),
                '_initialized' => true,
            ]
        );

        return $this->set_node($cached_node);
    }

    public function set_node(CacheNode $node)
    {
        $id = $node->get_id();
        $this->_nodes[$id] = $node;

        return $this->_nodes[$id];
    }

    public function pull_for_changes($folder_id, $force_update = false, $buffer = 10)
    {
        $force = (defined('FORCE_REFRESH') ? true : $force_update);

        if (empty($folder_id)) {
            $folder_id = Processor::instance()->get_shortcode_option('root');
        }

        $drive_id = $this->get_drive_id_by_entry_id($folder_id);

        // Check if we need to check for updates
        $current_time = time();
        $last_check_time = $this->get_last_check_for_update($drive_id);
        $update_needed = ($last_check_time + $this->get_max_change_age());
        if (($current_time < $update_needed) && !$force) {
            return true;
        }

        if (true === $force && ($last_check_time > $current_time - $buffer)) {
            // Don't pull again if the request was within $buffer seconds
            return true;
        }

        // Reset Cache if the last time we used this cache is more than a day ago
        if (!empty($last_check_time) && $last_check_time < ($current_time - 60 * 60 * 24)) {
            Processor::reset_complete_cache(false);
            $this->set_last_check_for_update($drive_id);

            $this->update_cache();

            return true;
        }

        if (in_array($drive_id, ['drive', null])) {
            // Reset the complete Drive cache including shared folders and shared with me
            if ($force) {
                Processor::reset_complete_cache(false);
            }

            $this->set_last_check_for_update($drive_id);

            $this->update_cache();

            return true;
        }

        if (!$this->_create_local_lock(LOCK_EX | LOCK_NB)) {
            return false;
        }

        $result = Client::instance()->get_changes($drive_id, $this->get_last_check_token($drive_id));

        if (empty($result)) {
            return false;
        }

        $this->set_last_check_token($drive_id, $result['new_change_token']);
        $this->set_last_check_for_update($drive_id);

        if (is_array($result['changes']) && count($result['changes']) > 0) {
            $this->_process_changes($result['changes']);

            if (!defined('HAS_CHANGES')) {
                define('HAS_CHANGES', true);
            }

            $this->update_cache();

            return true;
        }

        $this->update_cache(false);
    }

    public function is_updated()
    {
        return $this->_updated;
    }

    public function set_updated($value = true)
    {
        $this->_updated = (bool) $value;

        return $this->_updated;
    }

    public function get_cache_name()
    {
        return $this->_cache_name;
    }

    public function get_cache_location()
    {
        return $this->_cache_location;
    }

    public function get_last_check_for_update($drive_id)
    {
        if (!isset($this->_last_check_for_update[(string) $drive_id])) {
            $this->_last_check_for_update[$drive_id] = null;
            $this->set_updated();
        }

        return $this->_last_check_for_update[(string) $drive_id];
    }

    public function reset_last_check_for_update()
    {
        $this->_last_check_for_update = [];
        $this->set_updated();
    }

    public function set_last_check_for_update($drive_id)
    {
        $this->_last_check_for_update[(string) $drive_id] = time();
        $this->set_updated();

        return $this->_last_check_for_update[(string) $drive_id];
    }

    public function reset_last_check_token()
    {
        $this->_last_check_token = [];
        $this->set_updated();
    }

    public function get_last_check_token($drive_id)
    {
        if (!isset($this->_last_check_token[(string) $drive_id])) {
            $this->_last_check_token[(string) $drive_id] = null;
        }

        return $this->_last_check_token[(string) $drive_id];
    }

    public function set_last_check_token($drive_id, $token)
    {
        $this->_last_check_token[(string) $drive_id] = $token;

        return $this->_last_check_token[(string) $drive_id];
    }

    public function get_max_entry_age()
    {
        return $this->_max_entry_age;
    }

    public function set_max_entry_age($value)
    {
        return $this->_max_entry_age = $value;
    }

    public function get_max_change_age()
    {
        return $this->_max_change_age;
    }

    public function set_max_change_age($value)
    {
        return $this->_max_change_age = $value;
    }

    protected function _read_local_cache($close = false)
    {
        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $this->_create_local_lock(LOCK_SH);
        }

        // Clear PHP’s stat cache so filesize() is up-to-date
        clearstatcache();
        rewind($this->_get_cache_file_handle());

        $contents = '';

        // Read until end-of-file in 8 192-byte chunks
        while (filesize($this->get_cache_location()) > 0 && !feof($this->_get_cache_file_handle())) {
            $chunk = fread($this->_get_cache_file_handle(), 8192);
            if (false === $chunk) {
                // sth went wrong—break out and return what we have
                break;
            }
            $contents .= $chunk;
        }

        if (false !== $close) {
            $this->_unlock_local_cache();
        }

        return $contents;
    }

    protected function _create_local_lock($type)
    {
        // Check if file exists
        $file = $this->get_cache_location();

        if (!file_exists($file)) {
            @file_put_contents($file, $this->_serialize_cache());

            if (!is_writable($file)) {
                Helpers::log_error('Cache file is not writable', 'Cache', ['file' => $file], __LINE__);

                exit(sprintf('Cache file (%s) is not writable', $file));
            }
        }

        // Check if the file is more than 1 minute old.
        $requires_unlock = ((filemtime($file) + 60) < time());

        // Temporarily workaround when flock is disabled. Can cause problems when plugin is used in multiple processes
        $flock_disabled = (false !== strpos(ini_get('disable_functions'), 'flock'));
        if ($flock_disabled) {
            $requires_unlock = false;
        }

        // Check if file is already opened and locked in this process
        $handle = $this->_get_cache_file_handle();
        if (empty($handle)) {
            $handle = fopen($file, 'c+');
            if (!is_resource($handle)) {
                Helpers::log_error('Cache file is not writable', 'Cache', ['file' => $file], __LINE__);

                exit(sprintf('Cache file (%s) is not writable', $file));
            }
            $this->_set_cache_file_handle($handle);
        }

        Helpers::set_time_limit(60);

        if (!flock($this->_get_cache_file_handle(), $type)) {
            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that
             * the previous process died and unlock the file manually
             */
            if ($requires_unlock) {
                $this->_unlock_local_cache();
                $handle = fopen($file, 'c+');
                $this->_set_cache_file_handle($handle);
            } elseif (false === $flock_disabled) {
                return false;
            }
            // Try to lock the file again
            flock($this->_get_cache_file_handle(), LOCK_EX);
        }
        Helpers::set_time_limit(60);

        touch($file);

        return true;
    }

    protected function _save_local_cache()
    {
        if (!$this->_create_local_lock(LOCK_EX)) {
            return false;
        }

        if (empty($this->_get_cache_file_handle())) {
            return false;
        }

        $data = $this->_serialize_cache($this);

        ftruncate($this->_get_cache_file_handle(), 0);
        rewind($this->_get_cache_file_handle());

        fwrite($this->_get_cache_file_handle(), $data);

        $this->_unlock_local_cache();
        $this->set_updated(false);

        return true;
    }

    protected function _unlock_local_cache()
    {
        $handle = $this->_get_cache_file_handle();
        if (!empty($handle)) {
            flock($this->_get_cache_file_handle(), LOCK_UN);
            fclose($this->_get_cache_file_handle());
            $this->_set_cache_file_handle(null);
        }

        clearstatcache();

        return true;
    }

    protected function _set_cache_file_handle($handle)
    {
        return $this->_cache_file_handle = $handle;
    }

    protected function _get_cache_file_handle()
    {
        return $this->_cache_file_handle;
    }

    private function _process_changes($changes = [])
    {
        foreach ($changes as $entry_id => $change) {
            if ('deleted' === $change) {
                $this->remove_from_cache($entry_id, 'deleted');
            } else {
                $this->remove_from_cache($entry_id, 'update');
                // Update cache with new entry
                if ($change instanceof EntryAbstract) {
                    $this->add_to_cache($change);
                }
            }
        }

        $this->set_updated(true);
    }

    private function _serialize_cache()
    {
        $nodes_index = [];
        foreach ($this->_nodes as $id => $node) {
            $nodes_index[$id] = $node->to_index();
        }

        $data = [
            '_nodes' => $nodes_index,
            '_root_node_id' => $this->_root_node_id,
            '_last_check_token' => $this->_last_check_token,
            '_last_check_for_update' => $this->_last_check_for_update,
        ];

        $data_str = serialize($data);

        if (function_exists('gzencode')) {
            $data_str = gzencode($data_str);
        }

        return $data_str;
    }

    private function _unserialize_cache($data)
    {
        $values = unserialize($data);
        if (false !== $values) {
            foreach ($values as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}
