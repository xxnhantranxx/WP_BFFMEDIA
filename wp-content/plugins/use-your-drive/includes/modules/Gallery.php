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
use TheLion\UseyourDrive\Entry;
use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Restrictions;
use TheLion\UseyourDrive\Search;
use TheLion\UseyourDrive\Settings;
use TheLion\UseyourDrive\Tree;
use TheLion\UseyourDrive\User;

defined('ABSPATH') || exit;

class Gallery
{
    public static $enqueued_scripts = false;
    protected $_folder;
    protected $_items;
    protected $_search = false;
    protected $_parentfolders = [];

    public function get_images_list()
    {
        $this->_folder = Client::instance()->get_folder();

        if (false !== $this->_folder) {
            // Create Image Array
            $this->_items = $this->createItems();

            $this->renderImagesList();
        }
    }

    public function search_image_files()
    {
        if ('POST' !== $_SERVER['REQUEST_METHOD'] || !User::can_search()) {
            exit(-1);
        }

        $this->_search = true;
        $this->_folder = Client::instance()->get_folder();

        $_REQUEST['query'] = wp_kses(stripslashes($_REQUEST['query']), 'strip');
        $search = new Search();
        $this->_folder['contents'] = $search->do_search($_REQUEST['query'], $this->_folder['folder']);

        if (false !== $this->_folder) {
            // Create Gallery array
            $this->_items = $this->createItems();

            $this->renderImagesList();
        }
    }

    public function setFolder($folder)
    {
        $this->_folder = $folder;
    }

    public function setParentFolder()
    {
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

    public function renderImagesList()
    {
        // Create HTML Filelist
        $imageslist_html = '';

        $filescount = 0;
        $folderscount = 0;

        $folder_path = Processor::instance()->get_folder_path();
        $parentid = end($folder_path);

        if (count($this->_items) > 0) {
            $imageslist_html = "<div class='images image-collage'>";
            foreach ($this->_items as $item) {
                // Render folder div
                if ($item->is_dir()) {
                    $imageslist_html .= $this->renderDir($item);

                    $isparent = (!empty($parentid) && $item->get_id() === $parentid);
                    if (!$isparent) {
                        ++$folderscount;
                    }
                }
            }
        }

        $imageslist_html .= $this->renderNewFolder();

        if (count($this->_items) > 0) {
            foreach ($this->_items as $item) {
                // Render file div
                if (!$item->is_dir()) {
                    $imageslist_html .= $this->renderFile($item);
                    ++$filescount;
                }
            }

            $imageslist_html .= '</div>';
        }

        if (true === $this->_search) {
            $lastFolder = Processor::instance()->get_last_folder();
        } else {
            $lastFolder = $this->_folder['folder']->get_entry()->get_id();
        }

        $tree = new Tree($this->_folder['folder']->get_entry()->get_id());

        $response = json_encode([
            'tree' => $tree->get_structure(),
            'lastFolder' => $lastFolder,
            'accountId' => '0' === Processor::instance()->get_shortcode_option('popup') ? $this->_folder['folder']->get_account_uuid() : $this->_folder['folder']->get_account_id(),
            'virtual' => false === $this->_search && in_array($this->_folder['folder']->get_virtual_folder(), ['drive', 'shared-drives', 'computers', 'shared-with-me']),
            'readonly' => App::get_current_account()->is_drive_readonly(),
            'html' => $imageslist_html,
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

    public function renderDir($item)
    {
        $return = '';

        $target_height = Processor::instance()->get_shortcode_option('targetheight');
        $target_width = round($target_height * (4 / 3));

        $classmoveable = (User::can_move_folders()) ? 'moveable' : '';

        // Check if $item is parent
        $folder_path = Processor::instance()->get_folder_path();
        $parentid = end($folder_path);
        $isparent = ((!empty($parentid) && $item->get_id() === $parentid) || in_array($item->get_id(), array_keys($this->_parentfolders)));

        $folder_thumbnails = $item->get_folder_thumbnails();
        $has_thumbnails = (isset($folder_thumbnails['expires']) && $folder_thumbnails['expires'] > time());

        if ($isparent) {
            $return .= "<div class='image-container image-folder pf' data-id='".$item->get_id()."' data-name='".$item->get_basename()."'>";
        } else {
            $loadthumbs = $has_thumbnails ? '' : 'loadthumbs';
            $return .= "<div class='image-container image-folder entry {$classmoveable} {$loadthumbs}' data-id='".$item->get_id()."' data-name='".$item->get_basename()."'>";
        }

        $return .= "<a href='javascript:void(0);' title='".$item->get_name()."'>";

        $return .= "<div class='preloading'></div>";

        $return .= "<img class='image-folder-img' alt='' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' width='{$target_width}' height='{$target_height}' style='width:{$target_width}px !important;height:{$target_height}px !important; '/>";

        if ($has_thumbnails) {
            $iimages = 1;

            foreach ($folder_thumbnails['thumbs'] as $folder_thumbnail) {
                $thumb_url = $item->get_thumbnail_with_size('h'.round($target_height * 2).'-w'.round($target_width * 2).'-nu', $folder_thumbnail);
                $thumb_url = (false === strpos($thumb_url, 'useyourdrive-thumbnail')) ? $thumb_url : $thumb_url.'&account_id='.$this->_folder['folder']->get_account_uuid().'&listtoken='.Processor::instance()->get_listtoken();

                $return .= "<div class='folder-thumb thumb{$iimages}' style='width:".$target_width.'px;height:'.$target_height.'px;background-image: url('.$thumb_url.")'></div>";
                ++$iimages;
            }
        }

        $text = $item->get_name();
        $text = apply_filters('useyourdrive_gallery_entry_text', $text, $item, $this);

        $return .= "<div class='folder-text'><span><i class='eva eva-folder'></i>&nbsp;&nbsp;".($isparent ? '<strong>'.esc_html__('Previous folder', 'wpcloudplugins').' ('.$text.')</strong>' : $text).'</span></div>';
        $return .= '</a>';

        if (!$isparent) {
            $return .= "<div class='entry-info'>";
            $return .= $this->renderDescription($item);
            $return .= $this->renderButtons($item);
            $return .= $this->renderActionMenu($item);

            if (
                '1' === Processor::instance()->get_shortcode_option('show_header')
                 && ((User::can_download_zip() && '1' === Processor::instance()->get_shortcode_option('candownloadfolder_as_zip')) || User::can_delete_files() || User::can_move_files() || User::can_copy_files())
            ) {
                $return .= "<div class='entry_checkbox entry-info-button '><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-info-".Processor::instance()->get_listtoken()."-{$item->get_id()}'/><label for='checkbox-info-".Processor::instance()->get_listtoken()."-{$item->get_id()}'></label></div>";
            }

            $return .= '</div>';
        }

        $return .= "<div class='entry-top-actions'>";
        $return .= $this->renderDescription($item);
        $return .= $this->renderButtons($item);
        $return .= $this->renderActionMenu($item);

        if (
            '1' === Processor::instance()->get_shortcode_option('show_header')
             && ((User::can_download_zip() && '1' === Processor::instance()->get_shortcode_option('candownloadfolder_as_zip')) || User::can_delete_files() || User::can_move_files() || User::can_copy_files())
        ) {
            $return .= "<div class='entry_checkbox entry-info-button '><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'/><label for='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'></label></div>";
        }

        $return .= '</div>';

        $return .= "</div>\n";

        return $return;
    }

    public function renderFile($item)
    {
        $target_height = Processor::instance()->get_shortcode_option('targetheight');

        $classmoveable = (User::can_move_files()) ? 'moveable' : '';

        $return = "<div class='image-container entry {$classmoveable}' data-id='".$item->get_id()."' data-name='".$item->get_name()."'>";

        $thumbnail_url = $item->get_thumbnail_with_size('w200-h200-nu');
        $link = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&dl=1&listtoken='.Processor::instance()->get_listtoken();

        $lightbox_type = 'image';
        $lightbox_data = 'data-options="thumbnail: \''.$thumbnail_url.'\'"';
        if (in_array($item->get_extension(), ['mp4', 'm4v', 'ogg', 'ogv', 'webmv'])) {
            $link = \str_replace('download', 'stream', $link);
            $lightbox_data = 'data-options="thumbnail: \''.$thumbnail_url.'\', mousewheel:false, html5video:{h264:\''.$link.'\', poster: \''.$item->get_thumbnail_large().'\', preload:\'auto\'}, videoType:\''.$item->get_mimetype().'\'"';
            $lightbox_type = 'video';
        } elseif (('default' === Processor::instance()->get_shortcode_option('lightbox_imagesource') && 'original' === Settings::get('loadimages')) || 'original' === Processor::instance()->get_shortcode_option('lightbox_imagesource')) {
            $link = USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-preview&account_id='.App::get_current_account()->get_uuid().'&id='.urlencode($item->get_id()).'&raw=1&listtoken='.Processor::instance()->get_listtoken();
        } elseif (('default' === Processor::instance()->get_shortcode_option('lightbox_imagesource') && 'thumbnail' === Settings::get('loadimages')) || 'thumbnail' === Processor::instance()->get_shortcode_option('lightbox_imagesource') || false === User::can_download() || 'heic' === $item->get_extension()) {
            $link = $item->get_thumbnail_large();
        }

        $description = htmlentities($item->get_description(), ENT_QUOTES | ENT_HTML401);
        $data_description = ((!empty($item->description)) ? "data-caption='{$description}'" : '');

        $return .= "<a href='".$link."' title='".$item->get_basename()."' class='ilightbox-group' data-type='{$lightbox_type}' {$lightbox_data} rel='ilightbox[".Processor::instance()->get_listtoken()."]' data-entry-id='{$item->get_id()}' {$data_description}>";

        $return .= "<div class='preloading'></div>";

        $width = $height = $target_height;
        if ($item->get_media('width')) {
            $width = round(($target_height / $item->get_media('height')) * $item->get_media('width'));
        }

        $return .= "<img referrerPolicy='no-referrer' class='preloading' alt='{$description}' 'src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$item->get_thumbnail_with_size('h'.round($target_height * 1).'-nu')."' data-src-retina='".$item->get_thumbnail_with_size('h'.round($target_height * 2).'-nu')."' width='{$width}' height='{$height}' style='width:{$width}px !important;height:{$height}px !important; '/>";

        $text = '';
        if ('1' === Processor::instance()->get_shortcode_option('show_filenames')) {
            $text = $item->get_basename();
            $text = apply_filters('useyourdrive_gallery_entry_text', $text, $item, $this);
            $return .= "<div class='entry-text'><span>".$text.'</span></div>';
        }

        $return .= '</a>';

        if (false === empty($item->description)) {
            $return .= '<div class="entry-inline-description '.('1' === Processor::instance()->get_shortcode_option('show_descriptions_on_top') ? ' description-visible ' : '').('1' === Processor::instance()->get_shortcode_option('show_filenames') ? ' description-above-name ' : '').'"><span>'.nl2br($item->get_description()).'</span></div>';
        }

        $return .= "<div class='entry-info' data-id='{$item->get_id()}'>";
        $return .= "<div class='entry-info-name'>";
        $caption = apply_filters('useyourdrive_gallery_lightbox_caption', $item->get_basename(), $item, $this);
        $return .= '<span>'.$caption.'</span></div>';
        $return .= $this->renderButtons($item);
        $return .= "</div>\n";

        $return .= "<div class='entry-top-actions'>";

        if ('1' === Processor::instance()->get_shortcode_option('show_filenames')) {
            $return .= $this->renderDescription($item);
        }

        $return .= $this->renderButtons($item);
        $return .= $this->renderActionMenu($item);

        if (
            '1' === Processor::instance()->get_shortcode_option('show_header')
             && (User::can_download_zip() || User::can_delete_files() || User::can_move_files() || User::can_copy_files())
        ) {
            $return .= "<div class='entry_checkbox entry-info-button '><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'/><label for='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'></label></div>";
        }

        $return .= '</div>';
        $return .= "</div>\n";

        return $return;
    }

    public function renderDescription($item)
    {
        $html = '';

        $has_description = (false === empty($item->description));

        $metadata = [];
        if ('1' === Processor::instance()->get_shortcode_option('show_filedate')) {
            $metadata['modified'] = '<strong>'.esc_html__('Modified', 'wpcloudplugins').' <i class="eva eva-clock-outline"></i></strong><br/>'.$item->get_last_edited_str(false);
        }

        if ('1' === Processor::instance()->get_shortcode_option('show_filesize') && $item->get_size() > 0) {
            $metadata['size'] = '<strong>'.esc_html__('File Size', 'wpcloudplugins').' <i class="eva eva-info-outline"></i></strong><br/> '.Helpers::bytes_to_size_1024($item->get_size());
        }

        if (false === $has_description && empty($metadata) && !$this->_search) {
            return $html; // Don't display description button if there is no description and no metadata to display
        }

        $html .= "<div class='entry-info-button entry-description-button ".(($has_description) ? '-visible' : '')."' tabindex='0'><i class='eva eva-info-outline eva-lg'></i>\n";
        $html .= "<div class='tippy-content-holder'>";
        $html .= "<div class='description-textbox'>";
        $html .= "<div class='description-file-name'>".htmlspecialchars($item->get_name(), ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, 'UTF-8').'</div>';
        $html .= ($has_description) ? "<div class='description-text'>".nl2br($item->get_description()).'</div>' : '';

        if (!empty($metadata)) {
            $html .= "<div class='description-file-info'>".implode('<br/><br/> ', array_filter($metadata)).'</div>';
        }

        if ($this->_search) {
            $parent_node = Cache::instance()->get_node_by_id($item->parent_id);
            if (!empty($parent_node)) {
                $path = $parent_node->get_name();
                $html .= "<div class='description-file-info'><button class='button secondary folder search-location' data-id='{$parent_node->get_id()}'><i class='eva eva-folder-outline'></i> ".$path.'</button></div>';
            }
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function renderButtons($item)
    {
        $html = '';

        if (User::can_share()) {
            $html .= "<div class='entry-info-button entry_action_shortlink' title='".esc_html__('Share', 'wpcloudplugins')."' tabindex='0'><i class='eva eva-share-outline eva-lg'></i>\n";
            $html .= '</div>';
        }

        if (User::can_deeplink()) {
            $html .= "<div class='entry-info-button entry_action_deeplink' title='".esc_html__('Direct link', 'wpcloudplugins')."' tabindex='0'><i class='eva eva-link eva-lg'></i>\n";
            $html .= '</div>';
        }

        if (User::can_download() && $item->is_file()) {
            $is_limit_reached = Restrictions::has_reached_download_limit($item->get_id()) ? 'disabled' : '';
            $html .= "<div class='entry-info-button entry_action_download' title='".esc_html__('Download', 'wpcloudplugins')."' tabindex='0'><a href='".USEYOURDRIVE_ADMIN_URL.'?action=useyourdrive-download&account_id='.App::get_current_account()->get_uuid().'&id='.$item->get_id().'&dl=1&listtoken='.Processor::instance()->get_listtoken()."' download='".$item->get_name()."' class='entry_action_download {$is_limit_reached}' title='".esc_html__('Download', 'wpcloudplugins')."'><i class='eva eva-download eva-lg'></i></a>\n";
            $html .= '</div>';
        }

        return $html;
    }

    public function renderActionMenu($item)
    {
        $html = '';

        $permissions = $item->get_permissions();

        $usercanread = User::can_download();
        $usercanrename = $permissions['canrename'] && ($item->is_dir()) ? User::can_rename_folders() : User::can_rename_files();
        $usercanmove = $permissions['canmove'] && (($item->is_dir()) ? User::can_move_folders() : User::can_move_files());
        $usercandelete = $permissions['candelete'] && (($item->is_dir()) ? User::can_delete_folders() : User::can_delete_files());
        $usercaneditdescription = User::can_edit_description();
        $usercancopy = (($item->is_dir()) ? User::can_copy_folders() : User::can_copy_files());

        // Download
        if ($usercanread && $item->is_dir() && '1' === Processor::instance()->get_shortcode_option('can_download_zip')) {
            $is_limit_reached = Restrictions::has_reached_download_limit($item->get_id()) ? 'disabled' : '';

            $html .= "<li><a class='entry_action_download {$is_limit_reached}' download='".$item->get_name()."' data-name='".$item->get_name()."' title='".esc_html__('Download', 'wpcloudplugins')."'><i class='eva eva-download eva-lg'></i>&nbsp;".esc_html__('Download', 'wpcloudplugins').'</a></li>';

            if ($usercaneditdescription || $usercanrename || $usercanmove) {
                $html .= "<li class='list-separator'></li>";
            }
        }

        // Descriptions
        if ($usercaneditdescription) {
            if (empty($item->description)) {
                $html .= "<li><a class='entry_action_description' title='".esc_html__('Add description', 'wpcloudplugins')."'><i class='eva eva-message-square-outline eva-lg'></i>&nbsp;".esc_html__('Add description', 'wpcloudplugins').'</a></li>';
            } else {
                $html .= "<li><a class='entry_action_description' title='".esc_html__('Edit description', 'wpcloudplugins')."'><i class='eva eva-message-square-outline eva-lg'></i>&nbsp;".esc_html__('Edit description', 'wpcloudplugins').'</a></li>';
            }
        }

        // Rename
        if ($usercanrename) {
            $html .= "<li><a class='entry_action_rename' title='".esc_html__('Rename', 'wpcloudplugins')."'><i class='eva eva-edit-2-outline eva-lg'></i>&nbsp;".esc_html__('Rename', 'wpcloudplugins').'</a></li>';
        }

        // Move
        if ($usercanmove) {
            $html .= "<li><a class='entry_action_move' title='".esc_html__('Move to', 'wpcloudplugins')."'><i class='eva eva-corner-down-right eva-lg'></i>&nbsp;".esc_html__('Move to', 'wpcloudplugins').'</a></li>';
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
        $html = '';

        if (
            false === User::can_add_folders()
            || true === $this->_search
            || '1' === Processor::instance()->get_shortcode_option('show_breadcrumb')
        ) {
            return $html;
        }

        $height = Processor::instance()->get_shortcode_option('targetheight');
        $html .= "<div class='image-container image-folder image-add-folder grey newfolder' data-mimetype='application/vnd.google-apps.folder'>";
        $html .= "<a title='".esc_html__('Add folder', 'wpcloudplugins')."'>";
        $html .= "<img class='preloading' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".plugins_url('css/images/gallery-add-folder.png', dirname(__FILE__))."' data-src-retina='".plugins_url('css/images/gallery-add-folder.png', dirname(__FILE__))."' width='{$height}' height='{$height}' style='width:".$height.'px;height:'.$height."px;'/>";
        $html .= "<div class='folder-text'><span><i class='eva eva-folder-add-outline eva-lg'></i>&nbsp;&nbsp;".esc_html__('Add folder', 'wpcloudplugins').'</span></div>';
        $html .= '</a>';
        $html .= "</div>\n";

        return $html;
    }

    public function createItems()
    {
        $imagearray = [];

        $this->setParentFolder();

        // Add folders and files to filelist
        if (count($this->_folder['contents']) > 0) {
            foreach ($this->_folder['contents'] as $node) {
                $child = $node->get_entry();

                // Use the orginial entry if the file/folder is a shortcut
                if ($node->is_shortcut()) {
                    $child = $node->get_original_node()->get_entry();
                    $child->set_shortcut_details($node->get_entry()->get_shortcut_details());
                }

                // Check if entry is allowed
                if (!Processor::instance()->_is_entry_authorized($node)) {
                    continue;
                }

                // Check if entry has thumbnail
                if (!$child->has_own_thumbnail() && $child->is_file()) {
                    continue;
                }

                $imagearray[] = $child;
            }

            $imagearray = Processor::instance()->sort_filelist($imagearray);
        }

        // Limit the number of files if needed
        if ('-1' !== Processor::instance()->get_shortcode_option('max_files')) {
            $imagearray = array_slice($imagearray, 0, Processor::instance()->get_shortcode_option('max_files'));
        }

        // Add 'back to Previous folder' if needed
        if (isset($this->_folder['folder'])) {
            $folder = $this->_folder['folder']->get_entry();

            if ($this->_search || $folder->get_id() === Processor::instance()->get_root_folder()) {
                return $imagearray;
            }
            if ('1' === Processor::instance()->get_shortcode_option('show_breadcrumb')) {
                return $imagearray;
            }

            // Get previous folder ID from Folder Path if possible//
            $folder_path = Processor::instance()->get_folder_path();
            $parentid = end($folder_path);
            if (!empty($parentid)) {
                $parentfolder = Client::instance()->get_folder($parentid);
                array_unshift($imagearray, $parentfolder['folder']->get_entry());

                return $imagearray;
            }

            // Otherwise, list the parents directly
            foreach ($this->_parentfolders as $parentfolder) {
                array_unshift($imagearray, $parentfolder);
            }
        }

        return $imagearray;
    }

    public static function render($attributes = [])
    {
        self::enqueue_scripts();

        $shortcode = Processor::instance()->get_shortcode();

        $attributes += [
            'data-list' => 'gallery',
            'data-query' => $shortcode['searchterm'],
            'data-lightboxnav' => $shortcode['lightbox_navigation'],
            'data-lightboxthumbs' => $shortcode['lightbox_thumbnails'],
            'data-lightboxopen' => $shortcode['lightbox_open'],
            'data-targetheight' => $shortcode['targetheight'],
            'data-slideshow' => $shortcode['slideshow'],
            'data-pausetime' => $shortcode['pausetime'],
            'data-showfilenames' => $shortcode['show_filenames'],
        ];

        echo "<div class='wpcp-module UseyourDrive wpcp-gallery jsdisabled' ".Module::parse_attributes($attributes).'>';

        Password::render();

        include sprintf('%s/templates/modules/gallery.php', USEYOURDRIVE_ROOTDIR);

        Upload::render();

        echo '</div>';
    }

    public static function enqueue_scripts()
    {
        Filebrowser::enqueue_scripts();
    }
}