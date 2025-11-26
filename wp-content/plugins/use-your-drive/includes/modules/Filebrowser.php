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
use TheLion\UseyourDrive\Cache;
use TheLion\UseyourDrive\CacheRequest;
use TheLion\UseyourDrive\Client;
use TheLion\UseyourDrive\Core;
use TheLion\UseyourDrive\Entry;
use TheLion\UseyourDrive\EntryAbstract;
use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Restrictions;
use TheLion\UseyourDrive\Search;
use TheLion\UseyourDrive\Settings;
use TheLion\UseyourDrive\Tree;
use TheLion\UseyourDrive\User;

defined('ABSPATH') || exit;

class Filebrowser
{
    public static $enqueued_scripts = false;
    protected $_folder;
    protected $_items;
    protected $_search = false;
    protected $_parentfolders = [];

    public function get_files_list()
    {
        $this->_folder = Client::instance()->get_folder();

        if (false !== $this->_folder) {
            $this->_items = $this->createItems();
            $this->renderFilelist();
        } else {
            exit('Folder is not received');
        }
    }

    public function search_files()
    {
        if ('POST' !== $_SERVER['REQUEST_METHOD'] || !User::can_search()) {
            exit(-1);
        }

        $this->_search = true;

        if ('search' === Processor::instance()->get_shortcode_option('mode')) {
            // Search Box always starts searching from top folder
            $this->_folder = Client::instance()->get_folder(Processor::instance()->get_root_folder());
        } else {
            $this->_folder = Client::instance()->get_folder();
        }

        $search = new Search();
        $_REQUEST['query'] = wp_kses(stripslashes($_REQUEST['query']), 'strip');
        $this->_folder['contents'] = $search->do_search($_REQUEST['query'], $this->_folder['folder']);

        if (false !== $this->_folder) {
            $this->_items = $this->createItems();
            $this->renderFilelist();
        }
    }

    public function setFolder($folder)
    {
        $this->_folder = $folder;
    }

    public function setParentFolder()
    {
        $this->_parentfolders = [];

        if (true === $this->_search) {
            return;
        }

        $currentfolder = $this->_folder['folder']->get_entry()->get_id();
        if ($currentfolder !== Processor::instance()->get_root_folder()) {
            // Get parent folder from known folder path
            $cacheparentfolder = Client::instance()->get_folder(Processor::instance()->get_root_folder());
            $folder_path = Processor::instance()->get_folder_path();
            $parentid = end($folder_path);
            if (false !== $parentid) {
                $cacheparentfolder = Client::instance()->get_folder($parentid);
            }

            /* Check if parent folder indeed is direct parent of entry
             * If not, return all known parents */
            $parentfolders = [];
            if (false !== $cacheparentfolder && $cacheparentfolder['folder']->has_children() && array_key_exists($currentfolder, $cacheparentfolder['folder']->get_children())) {
                $parentfolders[$cacheparentfolder['folder']->get_id()] = $cacheparentfolder['folder']->get_entry();
            } elseif ($this->_folder['folder']->has_parent()) {
                $parentfolders[$this->_folder['folder']->get_parent()->get_id()] = $this->_folder['folder']->get_parent()->get_entry();
            }
            $this->_parentfolders = $parentfolders;
        }
    }

    public function renderFilelist()
    {
        // Create HTML Filelist
        $filelist_html = '';

        $breadcrumb_class = ('1' === Processor::instance()->get_shortcode_option('show_breadcrumb')) ? 'has-breadcrumb' : 'no-breadcrumb';
        $fileinfo_class = ('1' === Processor::instance()->get_shortcode_option('fileinfo_on_hover')) ? 'has-fileinfo-on-hover' : '';

        $filescount = 0;
        $folderscount = 0;

        $folder_path = Processor::instance()->get_folder_path();
        $parentid = end($folder_path);

        $filelist_html = "<div class='files {$breadcrumb_class} {$fileinfo_class}'>";
        $filelist_html .= "<div class='folders-container'>";

        if (count($this->_items) > 0) {
            // Limit the number of files if needed
            if ('-1' !== Processor::instance()->get_shortcode_option('max_files')) {
                $this->_items = array_slice($this->_items, 0, Processor::instance()->get_shortcode_option('max_files'));
            }

            foreach ($this->_items as $item) {
                // Render folder div
                if ($item->is_dir()) {
                    $filelist_html .= $this->renderDir($item);
                    ++$folderscount;
                }
            }
        }

        if (false === $this->_search && !in_array($this->_folder['folder']->get_virtual_folder(), ['drive', 'shared-drives', 'computers', 'shared-with-me'])) {
            $filelist_html .= $this->renderNewFolder();
        }
        $filelist_html .= "</div><div class='files-container'>";

        if (count($this->_items) > 0) {
            foreach ($this->_items as $item) {
                // Render files div
                if ($item->is_file()) {
                    $filelist_html .= $this->renderFile($item);
                    ++$filescount;
                }
            }
        }

        $filelist_html .= '</div></div>';

        $raw_path = '';
        if ((true !== $this->_search) && (current_user_can('edit_posts') || current_user_can('edit_pages')) && ('true' == get_user_option('rich_editing'))) {
            $raw_path = $this->_folder['folder']->get_path('drive');
        }

        if (true === $this->_search) {
            $lastFolder = Processor::instance()->get_last_folder();
        } else {
            $lastFolder = $this->_folder['folder']->get_entry()->get_id();
        }

        $tree = new Tree($this->_folder['folder']->get_entry()->get_id());

        $response = json_encode([
            'rawpath' => $raw_path,
            'tree' => $tree->get_structure(),
            'accountId' => '0' === Processor::instance()->get_shortcode_option('popup') ? $this->_folder['folder']->get_account_uuid() : $this->_folder['folder']->get_account_id(),
            'virtual' => false === $this->_search && in_array($this->_folder['folder']->get_virtual_folder(), ['drive', 'shared-drives', 'computers', 'shared-with-me']),
            'readonly' => App::get_current_account()->is_drive_readonly(),
            'lastFolder' => $lastFolder,
            'html' => $filelist_html,
            'folderscount' => $folderscount,
            'filescount' => $filescount,
            'hasChanges' => defined('HAS_CHANGES'),
        ]);

        if (false === defined('HAS_CHANGES')) {
            $cached_request = new CacheRequest();
            $cached_request->add_cached_response($response);
        }

        header('Content-Type: application/json');
        echo $response;

        exit;
    }

    public function renderDir(EntryAbstract $item)
    {
        $return = '';

        $classmoveable = (User::can_move_folders() && !$item->is_virtual_folder()) ? 'moveable' : '';
        $classshortcut = ($item->is_shortcut()) ? 'isshortcut' : '';
        $classvirtual = $item->is_virtual_folder() ? ' isvirtual' : '';

        // Check if $item is parent
        $folder_path = Processor::instance()->get_folder_path();
        $parentid = end($folder_path);
        $isparent = ((!empty($parentid) && $item->get_id() === $parentid) || in_array($item->get_id(), array_keys($this->_parentfolders)));

        $return .= "<div class='entry {$classmoveable} {$classshortcut} {$classvirtual} folder ".($isparent ? 'pf' : '')."' data-id='".$item->get_id()."' data-name='".htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, 'UTF-8')."'>\n";
        $return .= "<div class='entry_block'>\n";
        $return .= "<div class='entry-info'>";

        if (!$isparent) {
            $return .= $this->renderCheckBox($item);
        }

        $thumburl = $isparent ? Helpers::get_iconset_url().'/prev.png' : $item->get_thumbnail_small();
        $return .= "<div class='entry-info-icon'><div class='preloading'></div><img class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='{$thumburl}' data-src-retina='{$thumburl}'/></div>";

        $return .= "<div class='entry-info-name'>";
        $return .= "<a href='javascript:void(0);' class='entry_link' title='{$item->get_basename()}'>";
        $return .= '<span>';
        $return .= (($isparent) ? '<strong>'.esc_html__('Previous folder', 'wpcloudplugins').'</strong>' : $item->get_name()).' </span>';
        $return .= '</span>';
        $return .= '</a></div>';

        if (!$isparent) {
            $return .= $this->renderItemSelect($item);
            $return .= $this->renderDescription($item);
            $return .= $this->renderActionMenu($item);
        }

        $return .= "</div>\n";

        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderFile(Entry $item)
    {
        $link = $this->renderFileNameLink($item);
        $title = $link['filename'].((('1' === Processor::instance()->get_shortcode_option('show_filesize')) && ($item->get_size() > 0)) ? ' ('.Helpers::bytes_to_size_1024($item->get_size()).')' : '');

        $classmoveable = (User::can_move_files()) ? 'moveable' : '';
        $classshortcut = ($item->is_shortcut()) ? 'isshortcut' : '';

        $thumbnail_small = (false === strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $item->get_thumbnail_with_size('w500-h375-p-k') : $item->get_thumbnail_small().'&account_id='.$this->_folder['folder']->get_account_uuid().'&listtoken='.Processor::instance()->get_listtoken();

        $description = htmlentities($item->get_description(), ENT_QUOTES | ENT_HTML401);

        $return = '';
        $return .= "<div class='entry file {$classmoveable} {$classshortcut}' data-id='".$item->get_id()."' data-name='".htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, 'UTF-8')."'>\n";
        $return .= "<div class='entry_block'>\n";

        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $return .= "<div class='preloading'></div>";
        $return .= "<img referrerpolicy='no-referrer' class='preloading' alt='{$description}' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$thumbnail_small."' data-src-retina='".$thumbnail_small."' data-src-backup='".str_replace('/64/', '/256/', $item->get_icon())."'/>";
        $return .= "</div></div></div>\n";

        if ($duration = $item->get_media('duration')) {
            $return .= "<div class='entry-duration'><i class='eva eva-arrow-right '></i> ".Helpers::convert_ms_to_time($duration).'</div>';
        }

        // Audio files can play inline without lightbox
        $inline_player = '';
        if (User::can_preview() && in_array($item->get_extension(), ['mp3', 'm4a', 'ogg', 'oga', 'flac', 'wav'])) {
            $stream_url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-stream&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&listtoken='.Processor::instance()->get_listtoken();

            $inline_player .= "<div class='entry-inline-player' data-src='{$stream_url}' type='{$item->get_mimetype()}'><i class='eva eva-play-circle-outline eva-lg'></i> <i class='eva eva-pause-circle-outline eva-lg'></i> <i class='eva eva-volume-up-outline eva-lg eva-pulse'></i>";
            $inline_player .= '</div>';
        }

        $return .= "<div class='entry-info'>";
        $return .= $this->renderCheckBox($item);
        $return .= "<div class='entry-info-icon ".(!empty($inline_player) ? 'entry-info-icon-has-player' : '')."'><img src='".$item->get_icon()."'/>{$inline_player}</div>";
        $return .= "<div class='entry-info-name'>";
        $return .= '<a '.$link['url'].' '.$link['target']." class='entry_link ".$link['class']."' ".$link['onclick']." title='".$title."' ".$link['lightbox']." data-name='".$link['filename']."' data-entry-id='{$item->get_id()}' {$link['extra_attr']} >";
        $return .= '<span>'.($item->is_shortcut() ? '<i class="eva eva-share-outline"></i>&nbsp;' : '').$link['filename'].'</span>';
        $return .= '</a>';

        $return .= '</div>';

        $return .= $this->renderItemEmbed($item);
        $return .= $this->renderItemSelect($item);
        $return .= $this->renderModifiedDate($item);
        $return .= $this->renderSize($item);
        $return .= $this->renderThumbnailHover($item);
        $return .= $this->renderDownload($item);
        $return .= $this->renderDescription($item);
        $return .= $this->renderActionMenu($item);

        $return .= "</div>\n";

        $return .= $link['lightbox_inline'];

        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderSize(EntryAbstract $item)
    {
        if ('1' === Processor::instance()->get_shortcode_option('show_filesize')) {
            $size = ($item->get_size() > 0) ? Helpers::bytes_to_size_1024($item->get_size()) : '&nbsp;';

            return "<div class='entry-info-size entry-info-metadata'>".$size.'</div>';
        }
    }

    public function renderModifiedDate(EntryAbstract $item)
    {
        if ('1' === Processor::instance()->get_shortcode_option('show_filedate')) {
            return "<div class='entry-info-modified-date entry-info-metadata'>".$item->get_last_edited_str().'</div>';
        }
    }

    public function renderCheckBox(EntryAbstract $item)
    {
        $checkbox = '';

        if ('0' === Processor::instance()->get_shortcode_option('show_header')) {
            return $checkbox;
        }

        if ($item->is_dir()) {
            if (
                in_array(Processor::instance()->get_shortcode_option('popup'), ['links', 'selector'])
                || (User::can_download_zip() && '1' === Processor::instance()->get_shortcode_option('candownloadfolder_as_zip'))
                || User::can_delete_folders()
                || User::can_move_folders()
                || User::can_copy_folders()
                || User::can_create_shortcuts_folder()) {
                $checkbox .= "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'/><label for='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'></label></div>";
            }
        } else {
            if (
                in_array(Processor::instance()->get_shortcode_option('popup'), ['links', 'embedded', 'selector'])
                || User::can_download_zip()
                 || User::can_delete_files()
                  || User::can_move_files()
                  || User::can_copy_files()
                   || User::can_create_shortcuts_files()
            ) {
                $checkbox .= "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'/><label for='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'></label></div>";
            }
        }

        return $checkbox;
    }

    public function renderFileNameLink(EntryAbstract $item)
    {
        $class = '';
        $url = '';
        $target = '';
        $onclick = '';
        $datatype = 'iframe';
        $lightbox_inline = '';
        $extra_attr = '';

        $permissions = $item->get_permissions();

        // Check if user is allowed to preview the file
        $usercanpreview = User::can_preview();
        if (
            $item->is_dir()
            || !$permissions['canpreview']
            || false === $item->get_can_preview_by_cloud()
            || false === User::can_view()) {
            $usercanpreview = false;
        }

        $usercanread = User::can_download();

        // If we don't need to create a link
        if (('0' !== Processor::instance()->get_shortcode_option('popup')) || (!$usercanpreview)) {
            if ($usercanread) {
                $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&dl=1&listtoken='.Processor::instance()->get_listtoken();
                $class = 'entry_action_download';
                $extra_attr = "download='{$item->get_name()}'";
            }

            if ('selector' === Processor::instance()->get_shortcode_option('popup')) {
                $class = 'entry-select-item';
            }

        // No Url
        } elseif ($usercanread && !$usercanpreview) {
            // If is set to force download
            $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&dl=1&listtoken='.Processor::instance()->get_listtoken();
            $class = 'entry_action_download';
            $extra_attr = "download='{$item->get_name()}'";
        } elseif ($usercanread && !$item->get_can_preview_by_cloud()) {
            // If the file doesn't have a preview
            $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&dl=1&listtoken='.Processor::instance()->get_listtoken();
            $class = 'entry_action_download';
            $extra_attr = "download='{$item->get_name()}'";

            // If file is image
            if (in_array($item->get_extension(), ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                $class = 'ilightbox-group';
                $datatype = 'image';
                $extra_attr = '';

                if (
                    ('default' === Processor::instance()->get_shortcode_option('lightbox_imagesource') && 'thumbnail' === Settings::get('loadimages'))
                    || 'thumbnail' === Processor::instance()->get_shortcode_option('lightbox_imagesource')
                    || false === User::can_download()) {
                    $url = $item->get_thumbnail_large();
                }
            } elseif (in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga', 'flac'])) {
                $datatype = 'inline';
                $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-stream&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&listtoken='.Processor::instance()->get_listtoken();
            }
        } elseif ($usercanpreview) {
            // If user can't dowload a file or can preview and file can be previewd
            $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-preview&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&listtoken='.Processor::instance()->get_listtoken();
            $onclick = "sendAnalyticsUYD('Preview', '".$item->get_basename().((!empty($item->extension)) ? '.'.$item->get_extension() : '')."');";
            $class = 'ilightbox-group';

            $own_previewer = ['doc', 'docx', 'xls', 'xlsx', 'ppt'];

            // If file is image
            if (in_array($item->get_extension(), ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                $datatype = 'image';

                if (
                    ('default' === Processor::instance()->get_shortcode_option('lightbox_imagesource') && 'thumbnail' === Settings::get('loadimages'))
                    || 'thumbnail' === Processor::instance()->get_shortcode_option('lightbox_imagesource')) {
                    $url = $item->get_thumbnail_large().'=nu';
                } else {
                    $url .= '&raw=1';
                }
            } elseif (in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'ogg', 'oga', 'flac'])) {
                $datatype = 'inline';
                $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-stream&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&listtoken='.Processor::instance()->get_listtoken();
            } elseif (!empty($item->get_extension()) && !in_array($item->get_extension(), $own_previewer)) {
                $url .= '&docviewer=1';

                if (false !== strpos($item->get_mimetype(), 'video')) {
                    $url .= '&black';
                }
            }

            // Overwrite if preview inline is disabled
            if ('0' === Processor::instance()->get_shortcode_option('previewinline')) {
                if (!in_array($item->get_extension(), ['mp3', 'm4a', 'ogg', 'oga', 'flac', 'wav'])) {
                    $datatype = 'iframe';
                    $class = 'entry_action_external_view';
                    $target = '_blank';
                    $onclick = "sendAnalyticsUYD('Preview  (new window)', '{$item->get_name()}');";
                } else {
                    $url = '#';
                    $class = 'use_inline_player';
                }
            }
        }
        // No Url

        $filename = $item->get_basename();
        $filename .= (('1' === Processor::instance()->get_shortcode_option('show_ext') && !empty($item->extension)) ? '.'.$item->get_extension() : '');

        // Lightbox Settings
        $lightbox = "rel='ilightbox[".Processor::instance()->get_listtoken()."]' ";
        $lightbox .= 'data-type="'.$datatype.'"';

        $thumbnail_small = (false === strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $item->get_thumbnail_small() : $item->get_thumbnail_small().'&account_id='.$this->_folder['folder']->get_account_uuid().'&listtoken='.Processor::instance()->get_listtoken();
        if ('iframe' === $datatype) {
            $lightbox .= 'data-options="thumbnail: \''.$thumbnail_small.'\', mousewheel: false"';
        } elseif ('inline' === $datatype) {
            $id = 'ilightbox_'.Processor::instance()->get_listtoken().'_'.md5($item->get_id());
            $html5_element = (false === strpos($item->get_mimetype(), 'video')) ? 'audio' : 'video';

            $lightbox .= ' data-options="mousewheel: false, swipe:false, thumbnail: \''.$thumbnail_small.'\'"';

            $download = 'controlsList="nodownload"';
            $lightbox_inline = '<div id="'.$id.'" class="html5_player" style="display:none;"><'.$html5_element.' controls '.$download.' preload="metadata"  poster="'.$item->get_thumbnail_large().'"> <source data-src="'.$url.'" type="'.$item->get_mimetype().'">'.esc_html__('Your browser does not support HTML5. You can only download this file', 'wpcloudplugins').'</'.$html5_element.'></div>';
            $url = '#'.$id;
        } else {
            $lightbox .= 'data-options="thumbnail: \''.$thumbnail_small.'\'"';
        }

        if ('shortcode_builder' === Processor::instance()->get_shortcode_option('popup')) {
            $url = '';
        }

        if (!empty($url)) {
            $url = "href='".$url."'";
        }
        if (!empty($target)) {
            $target = "target='".$target."'";
        }
        if (!empty($onclick)) {
            $onclick = 'onclick="'.$onclick.'"';
        }

        // Return Values
        return ['filename' => htmlspecialchars($filename, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, 'UTF-8'), 'class' => $class, 'url' => $url, 'lightbox' => $lightbox, 'lightbox_inline' => $lightbox_inline, 'target' => $target, 'onclick' => $onclick, 'extra_attr' => $extra_attr];
    }

    public function renderThumbnailHover(Entry $item)
    {
        $thumbnail_url = (false === strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $item->get_thumbnail_with_size('w500-h375-p-k') : $item->get_thumbnail_small().'&account_id='.$this->_folder['folder']->get_account_uuid().'&listtoken='.Processor::instance()->get_listtoken();

        if (
            false === $item->has_own_thumbnail()
            || empty($thumbnail_url)
            || ('0' === Processor::instance()->get_shortcode_option('hover_thumbs'))) {
            return '';
        }

        $html = "<div class='entry-info-button entry-thumbnail-button  tabindex='0'><i class='eva eva-eye-outline eva-lg'></i>\n";
        $html .= "<div class='tippy-content-holder'>";

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function renderDownload(Entry $item)
    {
        $html = '';

        $usercanread = User::can_download() && ($item->is_file() || '1' === Processor::instance()->get_shortcode_option('can_download_zip'));
        $is_limit_reached = Restrictions::has_reached_download_limit($item->get_id()) ? 'disabled' : '';

        if ($item->is_virtual_folder() || !$usercanread) {
            return $html;
        }

        $url = '';
        if ($item->is_file()) {
            $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&dl=1&&id='.$item->get_id().'&account_id='.App::get_current_account()->get_uuid().'&listtoken='.Processor::instance()->get_listtoken();
        }

        $html .= "<div class='entry-info-button entry-download-button' tabindex='0'>
            <a class='entry_action_download {$is_limit_reached}' ".(!empty($url) ? "href='{$url}'" : '')." download='".$item->get_name()."' data-name='".$item->get_name()."' title='".esc_html__('Download', 'wpcloudplugins')."'><i class='eva eva-download eva-lg'></i></a>\n";
        $html .= '</div>';

        return $html;
    }

    public function renderDescription(EntryAbstract $item)
    {
        $html = '';

        if ($item->is_virtual_folder()) {
            return '';
        }

        $metadata = [];

        if (!empty($item->get_description())) {
            $metadata['description'] = [
                'title' => '',
                'text' => nl2br($item->get_description()),
            ];
        }

        if ('1' === Processor::instance()->get_shortcode_option('show_filesize') && $item->get_size() > 0) {
            $metadata['size']
            = [
                'title' => '<i class="eva eva-info-outline"></i> '.esc_html__('File Size', 'wpcloudplugins'),
                'text' => Helpers::bytes_to_size_1024($item->get_size()),
            ];
        }

        if ('1' === Processor::instance()->get_shortcode_option('show_filedate') && !empty($item->get_last_edited_str(false))) {
            $metadata['modified']
            = [
                'title' => '<i class="eva eva-clock-outline"></i> '.esc_html__('Modified', 'wpcloudplugins'),
                'text' => $item->get_last_edited_str(false),
            ];
        }

        if ('1' === Processor::instance()->get_shortcode_option('show_filedate') && !empty($item->get_created_time_str(false))) {
            $metadata['create']
            = [
                'title' => '<i class="eva eva-star-outline"></i> '.esc_html__('Created', 'wpcloudplugins'),
                'text' => $item->get_created_time_str(false),
            ];
        }

        if ($this->_search) {
            $parent_node = Cache::instance()->get_node_by_id($item->parent_id);
            if (!empty($parent_node)) {
                $path = $parent_node->get_name();

                $metadata['path']
                = [
                    'title' => "<i class='eva eva-folder-outline'></i> ".esc_html__('Location', 'wpcloudplugins'),
                    'text' => "<button class='button secondary folder search-location' data-id='{$parent_node->get_id()}'> ".$path.'</button>',
                    'location' => $parent_node->get_path(Processor::instance()->get_root_folder()),
                ];
            }
        }

        $metadata = apply_filters('useyourdrive_filebrowser_set_description', $metadata, $item);

        $metadata = array_filter($metadata);

        if (empty($metadata)) {
            return '';
        }

        $html .= "<div class='entry-info-button entry-description-button -visible' tabindex='0' data-metadata='".base64_encode(\json_encode($metadata))."'><i class='eva eva-info-outline eva-lg'></i></div>";

        return $html;
    }

    public function renderItemEmbed(EntryAbstract $item)
    {
        if (
            'shortcode_builder' === Processor::instance()->get_shortcode_option('popup')
            && in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv', 'mp3', 'm4a', 'oga', 'wav', 'webm'])
        ) {
            return "<a class='entry-info-button entry-embed-item'><i class='eva eva-code eva-lg'></i></a>";
        }

        return '';
    }

    public function renderItemSelect(EntryAbstract $item)
    {
        $html = '';

        if (in_array(Processor::instance()->get_shortcode_option('popup'), ['personal_folders_selector', 'personal_folders_backend', 'selector'])) {
            $html .= "<div class='entry-info-button entry-select-item' title='".esc_html__('Select this item', 'wpcloudplugins')."'><i class='eva eva-checkmark-outline eva-lg'></i></div>";
        }

        return $html;
    }

    public function renderActionMenu(EntryAbstract $item)
    {
        $html = '';

        if ($item->is_virtual_folder()) {
            return $html;
        }

        $permissions = $item->get_permissions();

        $usercanpreview = User::can_preview();
        if (
            $item->is_dir()
            || !$permissions['canpreview']
            || false === $item->get_can_preview_by_cloud()
            || false === User::can_view()) {
            $usercanpreview = false;
        }

        $usercanshare = $permissions['canshare'] && User::can_share();
        $usercanread = User::can_download();
        $usercanedit = User::can_edit();
        $usercaneditdescription = User::can_edit_description();
        $usercandeeplink = User::can_deeplink();
        $usercanrename = $permissions['canrename'] && ($item->is_dir()) ? User::can_rename_folders() : User::can_rename_files();
        $usercanmove = $permissions['canmove'] && (($item->is_dir()) ? User::can_move_folders() : User::can_move_files());
        $usercancopy = (($item->is_dir()) ? User::can_copy_folders() : User::can_copy_files());
        $usercancreateshortcuts = (($item->is_dir()) ? User::can_create_shortcuts_folder() : User::can_create_shortcuts_files());
        $usercandelete = $permissions['candelete'] && (($item->is_dir()) ? User::can_delete_folders() : User::can_delete_files());

        $filename = $item->get_basename();
        $filename .= (('1' === Processor::instance()->get_shortcode_option('show_ext') && !empty($item->extension)) ? '.'.$item->get_extension() : '');

        // View
        if ($usercanpreview) {
            if ('1' === Processor::instance()->get_shortcode_option('previewinline')) {
                $html .= "<li><a class='entry_action_view' title='".esc_html__('Preview', 'wpcloudplugins')."'><i class='eva eva-eye-outline eva-lg'></i>&nbsp;".esc_html__('Preview', 'wpcloudplugins').'</a></li>';
            }

            if ($usercanread) {
                if (
                    '0' === Processor::instance()->get_shortcode_option('can_popout')
                    && !in_array(
                        $item->get_mimetype(),
                        [
                            'application/vnd.ms-excel',
                            'application/vnd.ms-excel.sheet.macroenabled.12',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.google-apps.spreadsheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'application/vnd.google-apps.presentation',
                        ]
                    )
                && (!in_array($item->get_extension(), ['jpg', 'jpeg', 'gif', 'png', 'webp']))) {
                    // Files rendered by the Google Doc previewer will have a popout button
                } elseif ('1' === Processor::instance()->get_shortcode_option('previewnewtab')) {
                    $url = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-preview&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&listtoken='.Processor::instance()->get_listtoken();
                    $onclick = "sendAnalyticsUYD('Preview (new window)', '".$item->get_basename().((!empty($item->extension)) ? '.'.$item->get_extension() : '')."');";
                    $html .= "<li><a href='{$url}' target='_blank' class='entry_action_external_view' onclick=\"{$onclick}\" title='".esc_html__('Preview in new tab', 'wpcloudplugins')."'><i class='eva eva-monitor-outline eva-lg'></i>&nbsp;".esc_html__('Preview in new tab', 'wpcloudplugins').'</a></li>';
                }
            }
        }

        // Deeplink
        if ($usercandeeplink) {
            $html .= "<li><a class='entry_action_deeplink' title='".esc_html__('Direct link', 'wpcloudplugins')."'><i class='eva eva-link eva-lg'></i>&nbsp;".esc_html__('Direct link', 'wpcloudplugins').'</a></li>';
        }

        // Shortlink
        if ($usercanshare) {
            $html .= "<li><a class='entry_action_shortlink' title='".esc_html__('Share', 'wpcloudplugins')."'><i class='eva eva-share-outline eva-lg'></i>&nbsp;".esc_html__('Share', 'wpcloudplugins').'</a></li>';
        }

        // Download
        $is_limit_reached = Restrictions::has_reached_download_limit($item->get_id()) ? 'disabled' : '';
        if ($usercanread && $item->is_file() && (0 === count($item->get_save_as()))) {
            $html .= "<li><a href='".USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&dl=1&listtoken='.Processor::instance()->get_listtoken()."' class='entry_action_download {$is_limit_reached}' download='".$item->get_name()."' data-name='".$filename."' title='".esc_html__('Download', 'wpcloudplugins')."'><i class='eva eva-download eva-lg'></i>&nbsp;".esc_html__('Download', 'wpcloudplugins').'</a></li>';
        } elseif ($usercanread && $item->is_file() && (count($item->get_save_as()) > 0)) {
            // Exportformats
            if (count($item->get_save_as()) > 0) {
                $html .= "<li class='has-menu'><a class='{$is_limit_reached}'><i class='eva eva-download eva-lg'></i>&nbsp;".esc_html__('Download as', 'wpcloudplugins').'<i class="eva eva-chevron-right eva-lg"></i></a><ul>';
                foreach ($item->get_save_as() as $name => $exportlinks) {
                    $html .= "<li><a href='".USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&dl=1&mimetype='.$exportlinks['mimetype'].'&extension='.$exportlinks['extension'].'&listtoken='.Processor::instance()->get_listtoken()."' class='entry_action_export ' download='".$item->get_name()."' data-name='".$filename."'><i class='eva eva-file-outline eva-lg'></i>&nbsp;".' '.$name.'</a>';
                }
                $html .= '</ul>';
            }
        }

        if ($usercanread && $item->is_dir() && '1' === Processor::instance()->get_shortcode_option('can_download_zip') && '1' === Processor::instance()->get_shortcode_option('candownloadfolder_as_zip')) {
            $html .= "<li><a class='entry_action_download {$is_limit_reached}' download='".$item->get_name()."' data-name='".$filename."' title='".esc_html__('Download', 'wpcloudplugins')."'><i class='eva eva-download eva-lg'></i>&nbsp;".esc_html__('Download', 'wpcloudplugins').'</a></li>';
        }

        if (
            ($usercanpreview | $usercanread | $usercandeeplink | $usercanshare)
        && ($usercaneditdescription || $usercanedit || $usercanrename || $usercanmove || $usercancopy)) {
            $html .= "<li class='list-separator'></li>";
        }

        if ($item->is_file() && '1' === Processor::instance()->get_shortcode_option('import')) {
            $html .= "<li><a class='entry_action_import' data-name='".$filename."' title='".esc_html__('Add to Media Library', 'wpcloudplugins')."'><i class='eva eva-log-in-outline eva-lg'></i>&nbsp;".esc_html__('Add to Media Library', 'wpcloudplugins').'</a></li>';

            $html .= "<li class='list-separator'></li>";
        }

        // Descriptions
        if ($usercaneditdescription) {
            if (empty($item->description)) {
                $html .= "<li><a class='entry_action_description' title='".esc_html__('Add description', 'wpcloudplugins')."'><i class='eva eva-message-square-outline eva-lg'></i>&nbsp;".esc_html__('Add description', 'wpcloudplugins').'</a></li>';
            } else {
                $html .= "<li><a class='entry_action_description' title='".esc_html__('Edit description', 'wpcloudplugins')."'><i class='eva eva-message-square-outline eva-lg'></i>&nbsp;".esc_html__('Edit description', 'wpcloudplugins').'</a></li>';
            }
        }

        // Edit
        if ($usercanedit && $item->is_file() && $item->get_can_edit_by_cloud()) {
            $html .= "<li><a href='".USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-edit&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&listtoken='.Processor::instance()->get_listtoken()."' target='_blank' class='entry_action_edit' data-name='".$filename."' title='".esc_html__('Edit (new window)', 'wpcloudplugins')."'><i class='eva eva-edit-outline eva-lg'></i>&nbsp;".esc_html__('Edit (new window)', 'wpcloudplugins').'</a></li>';
        }

        // Rename
        if ($usercanrename) {
            $html .= "<li><a class='entry_action_rename' title='".esc_html__('Rename', 'wpcloudplugins')."'><i class='eva eva-edit-2-outline eva-lg'></i>&nbsp;".esc_html__('Rename', 'wpcloudplugins').'</a></li>';
        }

        // Move
        if ($usercanmove) {
            $html .= "<li><a class='entry_action_move' title='".esc_html__('Move to', 'wpcloudplugins')."'><i class='eva eva-corner-down-right eva-lg'></i>&nbsp;".esc_html__('Move to', 'wpcloudplugins').'</a></li>';
        }

        // Create Shortcut
        if ($usercancreateshortcuts) {
            $html .= "<li><a class='entry_action_shortcut' title='".esc_html__('Add shortcut', 'wpcloudplugins')."'><i class='eva eva-plus-square-outline eva-lg'></i>&nbsp;".esc_html__('Add shortcut', 'wpcloudplugins').'</a></li>';
        }

        // Copy
        if ($usercancopy) {
            $html .= "<li><a class='entry_action_copy' title='".esc_html__('Make a copy', 'wpcloudplugins')."'><i class='eva eva-copy-outline eva-lg'></i>&nbsp;".esc_html__('Make a copy', 'wpcloudplugins').'</a></li>';
        }

        // Delete
        if ($usercandelete && ($item->get_permission('candelete') || $item->get_permission('cantrash'))) {
            $html .= "<li class='list-separator'></li>";
            $html .= "<li><a class='entry_action_delete' title='".esc_html__('Delete', 'wpcloudplugins')."'><i class='eva eva-trash-2-outline eva-lg'></i>&nbsp;".esc_html__('Delete', 'wpcloudplugins').'</a></li>';
        }

        $html = apply_filters('useyourdrive_set_action_menu', $html, $item);

        if ('' !== $html) {
            return "<div class='entry-info-button entry-action-menu-button' title='".esc_html__('More actions', 'wpcloudplugins')."' tabindex='0'><i class='eva eva-more-vertical-outline'></i><div id='menu-".$item->get_id()."' class='entry-action-menu-button-content tippy-content-holder'><ul data-id='".$item->get_id()."' data-name='".$item->get_basename()."'>".$html."</ul></div></div>\n";
        }

        return $html;
    }

    public function renderNewFolder()
    {
        $return = '';

        if (
            false === User::can_add_folders()
            || true === $this->_search
            || '1' === Processor::instance()->get_shortcode_option('show_breadcrumb')
        ) {
            return $return;
        }

        $icon_set = Settings::get('icon_set');

        $return .= "<div class='entry folder newfolder' data-mimetype='application/vnd.google-apps.folder'>\n";
        $return .= "<div class='entry_block'>\n";
        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $return .= "<a class='entry_link'><img class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$icon_set."icon_10_addfolder_xl128.png' /></a>";
        $return .= "</div></div></div>\n";

        $return .= "<div class='entry-info'>";
        $return .= "<div class='entry-info-name'>";
        $return .= "<a href='javascript:void(0);' class='entry_link' title='".esc_html__('Add folder', 'wpcloudplugins')."'><div class='entry-name-view'>";
        $return .= '<span>'.esc_html__('Add folder', 'wpcloudplugins').'</span>';
        $return .= '</div></a>';
        $return .= "</div>\n";

        $return .= "</div>\n";
        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function createItems()
    {
        $items = [];

        $this->setParentFolder();

        // Don't return any results for empty searches in the Search Box
        if ('search' === Processor::instance()->get_shortcode_option('mode') && empty($_REQUEST['query']) && $this->_folder['folder']->get_id() === Processor::instance()->get_root_folder()) {
            return $this->_folder['contents'] = [];
        }

        // Add folders and files to filelist
        if (count($this->_folder['contents']) > 0) {
            foreach ($this->_folder['contents'] as $node) {
                // Check if entry is allowed
                if (!Processor::instance()->_is_entry_authorized($node)) {
                    continue;
                }

                $items[] = $node->get_entry();
            }

            $items = Processor::instance()->sort_filelist($items);
        }

        // Add 'back to Previous folder' if needed
        if (isset($this->_folder['folder'])) {
            $folder = $this->_folder['folder']->get_entry();

            if ($this->_search || $folder->get_id() === Processor::instance()->get_root_folder()) {
                return $items;
            }

            // Get previous folder ID from Folder Path if possible//
            $folder_path = Processor::instance()->get_folder_path();
            $parentid = end($folder_path);
            if (!empty($parentid)) {
                $parentfolder = Client::instance()->get_folder($parentid);
                array_unshift($items, $parentfolder['folder']->get_entry());

                return $items;
            }

            // Otherwise, list the parents directly
            foreach ($this->_parentfolders as $parentfolder) {
                array_unshift($items, $parentfolder);
            }
        }

        return $items;
    }

    public static function render($attributes = [])
    {
        self::enqueue_scripts();

        $shortcode = Processor::instance()->get_shortcode();

        $attributes += [
            'data-list' => 'files',
            'data-query' => $shortcode['searchterm'],
            'data-popout' => $shortcode['can_popout'],
            'data-lightboxnav' => $shortcode['lightbox_navigation'],
            'data-lightboxthumbs' => $shortcode['lightbox_thumbnails'],
            'data-layout' => $shortcode['filelayout'],
            'data-action' => $shortcode['popup'],
        ];

        echo "<div class='wpcp-module UseyourDrive files jsdisabled ".('grid' === $shortcode['filelayout'] ? 'wpcp-thumb-view' : 'wpcp-list-view')."' ".Module::parse_attributes($attributes).'  >';

        Password::render();
        LeadCapture::render();

        include sprintf('%s/templates/modules/file_browser.php', USEYOURDRIVE_ROOTDIR);
        Upload::render();

        echo '</div>';
    }

    public static function render_search($attributes = [])
    {
        self::enqueue_scripts();

        $shortcode = Processor::instance()->get_shortcode();

        $attributes += [
            'data-list' => 'search',
            'data-query' => $shortcode['searchterm'],
            'data-popout' => $shortcode['can_popout'],
            'data-lightboxnav' => $shortcode['lightbox_navigation'],
            'data-lightboxthumbs' => $shortcode['lightbox_thumbnails'],
            'data-layout' => $shortcode['filelayout'],
            'data-action' => $shortcode['popup'],
        ];

        echo "<div class='wpcp-module UseyourDrive files searchlist jsdisabled' ".Module::parse_attributes($attributes).'>';

        Password::render();

        include sprintf('%s/templates/modules/search.php', USEYOURDRIVE_ROOTDIR);

        echo '</div>';
    }

    public static function enqueue_scripts()
    {
        if (true === self::$enqueued_scripts) {
            return;
        }

        Core::instance()->load_scripts();
        Core::instance()->load_styles();

        if (User::can_move_files() || User::can_move_folders()) {
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-draggable');
        }

        wp_enqueue_script('jquery-effects-core');
        wp_enqueue_script('jquery-effects-fade');
        wp_enqueue_style('ilightbox');
        wp_enqueue_style('ilightbox-skin-useyourdrive');

        self::$enqueued_scripts = true;
    }
}
