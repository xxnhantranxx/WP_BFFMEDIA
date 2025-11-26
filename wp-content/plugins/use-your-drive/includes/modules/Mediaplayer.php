<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.0
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive\Modules;

use TheLion\UseyourDrive\App;
use TheLion\UseyourDrive\CacheNode;
use TheLion\UseyourDrive\CacheRequest;
use TheLion\UseyourDrive\Client;
use TheLion\UseyourDrive\Entry;
use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Restrictions;
use TheLion\UseyourDrive\Settings;
use TheLion\UseyourDrive\User;

defined('ABSPATH') || exit;

class Mediaplayer
{
    protected $_folder;
    protected $_items;

    public function get_media_list()
    {
        $this->_folder = Client::instance()->get_folder();

        if (false === $this->_folder) {
            exit;
        }

        $subfolders = Client::instance()->get_entries_in_subfolders($this->_folder['folder']);
        $this->_folder['contents'] = array_merge($subfolders, $this->_folder['contents']);
        $this->_items = $this->createItems();

        if (count($this->_items) > 0) {
            $response = json_encode($this->_items);

            $cached_request = new CacheRequest();
            $cached_request->add_cached_response($response);

            header('Content-Type: application/json');
            echo $response;
        }

        exit;
    }

    public function setFolder($folder)
    {
        $this->_folder = $folder;
    }

    public function createItems()
    {
        $covers = [];
        $captions = [];

        // Add covers and Captions
        if (count($this->_folder['contents']) > 0) {
            foreach ($this->_folder['contents'] as $key => $node) {
                $child = $node->get_entry();

                if (!isset($child->extension)) {
                    continue;
                }

                if (in_array(strtolower($child->extension), ['png', 'jpg', 'jpeg'])) {
                    // Add images to cover array
                    $covertitle = str_replace('.'.$child->get_extension(), '', $child->get_name());
                    $coverthumb = $child->get_thumbnail_small_cropped();
                    $covers[$covertitle] = $coverthumb;
                    unset($this->_folder['contents'][$key]);
                } elseif (in_array(strtolower($child->extension), ['vtt', 'srt'])) {
                    /*
                     * SRT | VTT files are supported for captions:.
                     *
                     * Filename: Videoname.Caption Label.Language.VTT|SRT
                     */

                    preg_match('/(?<name>.*).(?<label>\w*).(?<language>\w*)\.(srt|vtt)$/Uu', $child->get_name(), $match, PREG_UNMATCHED_AS_NULL, 0);

                    if (empty($match) || empty($match['language'])) {
                        continue;
                    }

                    $video_name = $match['name'];

                    if (!isset($captions[$video_name])) {
                        $captions[$video_name] = [];
                    }

                    if (false === array_search($match['label'], array_column($captions[$video_name], 'label'))) {
                        $captions[$video_name][] = [
                            'label' => $match['label'],
                            'language' => $match['language'],
                            'src' => USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-stream&account_id='.App::get_current_account()->get_uuid().'&id='.$child->get_id().'&dl=1&caption=1&listtoken='.Processor::instance()->get_listtoken(),
                        ];
                    }

                    unset($this->_folder['contents'][$key]);
                }
            }
        }

        $files = [];

        // Create Filelist array
        if (count($this->_folder['contents']) > 0) {
            foreach ($this->_folder['contents'] as $node) {
                $child = $node->get_entry();

                if (false === $this->is_media_file($node)) {
                    continue;
                }

                // Check if entry is allowed
                if (!Processor::instance()->_is_entry_authorized($node)) {
                    continue;
                }

                // Use the orginial entry if the file/folder is a shortcut
                if ($node->is_shortcut()) {
                    $original_node = $node->get_original_node();

                    if (empty($original_node)) {
                        // If the shortcut is pointing to an entry that doesn't longer exists
                        continue;
                    }

                    $original_entry = $original_node->get_entry();

                    if (empty($original_entry)) {
                        continue;
                    }

                    $child = $original_entry;
                }

                $basename = $child->get_basename();
                $extension = $child->get_extension();
                $foldername = $node->get_parent()->get_name();

                if (isset($covers[$basename])) {
                    $thumbnail = $covers[$basename];
                } elseif (isset($covers[$foldername])) {
                    $thumbnail = $covers[$foldername];
                } else {
                    $thumbnail = $child->get_thumbnail_small_cropped();
                }
                $thumbnailsmall = str_replace('=w500-h375-c-nu', '=s512', $thumbnail);
                $poster = str_replace('=w500-h375-c-nu', '=s1024', $thumbnail);

                $folder_str = dirname($node->get_path($this->_folder['folder']->get_id()));
                $folder_str = trim(str_replace('\\', '/', $folder_str), '/');
                $path = trim($folder_str.'/'.$basename, '/');

                // combine same files with different extensions
                if (!isset($files[$path])) {
                    $source_url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-stream&account_id='.App::get_current_account()->get_uuid().'&id='.$child->get_id().'&dl=1&listtoken='.Processor::instance()->get_listtoken();
                    if ('Yes' !== Settings::get('google_analytics')) {
                        $cached_source_url = get_transient('useyourdrive_stream_'.$child->get_id().'_'.$child->get_extension());
                        if (false !== $cached_source_url && false === filter_var($cached_source_url, FILTER_VALIDATE_URL)) {
                            $source_url = $cached_source_url;
                        }
                    }

                    $last_edited = $child->get_last_edited();
                    $localtime = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($last_edited)));

                    $files[$path] = [
                        'title' => $basename,
                        'name' => $path,
                        'artist' => $child->get_description(),
                        'is_dir' => false,
                        'folder' => $folder_str,
                        'poster' => $poster,
                        'thumb' => $thumbnailsmall,
                        'size' => $child->get_size(),
                        'id' => $child->get_id(),
                        'last_edited' => $last_edited,
                        'last_edited_date_str' => !empty($last_edited) ? date_i18n(get_option('date_format'), strtotime($localtime)) : '',
                        'last_edited_time_str' => !empty($last_edited) ? date_i18n(get_option('time_format'), strtotime($localtime)) : '',
                        'created_time' => $child->get_created_time(),
                        'created_str' => $child->get_created_time_str(),
                        'download' => (User::can_download() && !Restrictions::has_reached_download_limit($child->get_id())) ? str_replace('useyourdrive-stream', 'useyourdrive-download', $source_url) : false,
                        'share' => User::can_share(),
                        'deeplink' => User::can_deeplink(),
                        'source' => $source_url,
                        'captions' => isset($captions[$basename]) ? $captions[$basename] : [],
                        'type' => Helpers::get_mimetype($extension),
                        'extension' => $extension,
                        'height' => $child->get_media('height'),
                        'width' => $child->get_media('width'),
                        'duration' => $child->get_media('duration') / 1000, // ms to sec,
                        'linktoshop' => ('' !== Processor::instance()->get_shortcode_option('linktoshop')) ? Processor::instance()->get_shortcode_option('linktoshop') : false,
                    ];
                }
            }

            $files = Processor::instance()->sort_filelist($files);
        }

        if ('-1' !== Processor::instance()->get_shortcode_option('max_files')) {
            $files = array_slice($files, 0, Processor::instance()->get_shortcode_option('max_files'));
        }

        return $files;
    }

    public function is_media_file(CacheNode $node)
    {
        $entry = $node->get_entry();

        if ($entry->is_dir()) {
            return false;
        }

        // Use the orginial entry if the file/folder is a shortcut
        if ($node->is_shortcut()) {
            $original_entry = $node->get_original_node()->get_entry();
            $original_entry->set_shortcut_details($node->get_entry()->get_shortcut_details());
            $entry = $original_entry;
        }

        $extension = $entry->get_extension();
        $mimetype = $entry->get_mimetype();

        if ('audio' === Processor::instance()->get_shortcode_option('mode')) {
            $allowedextensions = ['mp3', 'm4a', 'ogg', 'oga', 'wav'];
            $allowedimimetypes = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-wav'];
        } else {
            $allowedextensions = ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'webm'];
            $allowedimimetypes = ['video/mp4', 'video/ogg', 'video/webm'];
        }

        if (!empty($extension) && in_array($extension, $allowedextensions)) {
            return true;
        }

        return in_array($mimetype, $allowedimimetypes);
    }

    public static function render($attributes = [])
    {
        $shortcode = Processor::instance()->get_shortcode();
        $mediaplayer = self::load_skin($shortcode['mediaskin']);

        $attributes += [
            'data-list' => 'media',
            'data-layout' => $shortcode['filelayout'],
        ];

        echo "<div class='wpcp-module UseyourDrive media ".$shortcode['mode']." jsdisabled' ".Module::parse_attributes($attributes).'>';

        Password::render();

        $mediaplayer->load_player();

        echo '</div>';
    }

    public static function load_skin($mediaplayer = null)
    {
        if (empty($mediaplayer)) {
            $mediaplayer = Settings::get('mediaplayer_skin');
        }

        if (file_exists(USEYOURDRIVE_ROOTDIR.'/skins/'.$mediaplayer.'/Player.php')) {
            require_once USEYOURDRIVE_ROOTDIR.'/skins/'.$mediaplayer.'/Player.php';
        } else {
            Helpers::log_error(sprintf('Media Player Skin %s is missing', $mediaplayer), 'MediaPlayer', null, __LINE__);

            return self::load_skin(null);
        }

        try {
            $class = '\TheLion\UseyourDrive\MediaPlayers\\'.$mediaplayer;

            return new $class();
        } catch (\Exception $ex) {
            Helpers::log_error(sprintf('Media Player Skin %s is invalid', $mediaplayer), 'MediaPlayer', null, __LINE__);

            return false;
        }
    }
}
