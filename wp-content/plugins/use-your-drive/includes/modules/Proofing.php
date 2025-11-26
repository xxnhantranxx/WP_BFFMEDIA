<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       3.1
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive\Modules;

use TheLion\UseyourDrive\Entry;
use TheLion\UseyourDrive\EntryAbstract;
use TheLion\UseyourDrive\Helpers;
use TheLion\UseyourDrive\Processor;
use TheLion\UseyourDrive\Settings;

defined('ABSPATH') || exit;

class Proofing extends Filebrowser
{
    public $has_labels = false;

    public function __construct()
    {
        $has_label_option = Processor::instance()->get_shortcode_option('proofing_use_labels');
        if ('1' === $has_label_option || ('' === $has_label_option && 'Yes' === Settings::get('proofing_use_labels'))) {
            $this->has_labels = true;
        }
    }

    public function renderFile(Entry $item)
    {
        $link = $this->renderFileNameLink($item);
        $title = $link['filename'].((('1' === Processor::instance()->get_shortcode_option('show_filesize')) && ($item->get_size() > 0)) ? ' ('.Helpers::bytes_to_size_1024($item->get_size()).')' : '');

        $classshortcut = ($item->is_shortcut()) ? 'isshortcut' : '';

        $thumbnail_small = (false === strpos($item->get_thumbnail_small(), 'useyourdrive-thumbnail')) ? $item->get_thumbnail_with_size('w500-h375') : $item->get_thumbnail_small().'&account_id='.$this->_folder['folder']->get_account_uuid().'&listtoken='.Processor::instance()->get_listtoken();

        $description = htmlentities($item->get_description(), ENT_QUOTES | ENT_HTML401);

        $return = '';
        $return .= "<div class='entry file {$classshortcut}' data-id='".$item->get_id()."' data-name='".htmlspecialchars($item->get_basename(), ENT_QUOTES | ENT_HTML401, 'UTF-8')."'>\n";
        $return .= "<div class='entry_block'>\n";

        $return .= "<div class='entry_thumbnail'><div class='entry_thumbnail-view-bottom'><div class='entry_thumbnail-view-center'>\n";
        $return .= "<div class='preloading'></div>";
        $return .= "<img referrerpolicy='no-referrer' class='preloading' alt='{$description}' src='".USEYOURDRIVE_ROOTPATH."/css/images/transparant.png' data-src='".$thumbnail_small."' data-src-retina='".$thumbnail_small."' data-src-backup='".str_replace('/64/', '/256/', $item->get_icon())."'/>";
        $return .= "</div></div></div>\n";

        $return .= "<div class='entry-info' data-id='".$item->get_id()."'>";
        $return .= "<div class='entry-info-name'>";
        $return .= '<a '.$link['url'].' '.$link['target']." class='entry_link ".$link['class']."' ".$link['onclick']." title='".$title."' ".$link['lightbox']." data-name='".$link['filename']."' data-entry-id='{$item->get_id()}' {$link['extra_attr']} >";
        $return .= '<span>'.($item->is_shortcut() ? '<i class="eva eva-share-outline"></i>&nbsp;' : '').$link['filename'].'</span>';
        $return .= '</a>';

        $return .= '</div>';

        $return .= $this->renderModifiedDate($item);
        $return .= $this->renderSize($item);
        $return .= $this->renderLabel($item);

        $return .= $this->renderCheckBox($item);

        $return .= "</div>\n";

        $return .= $link['lightbox_inline'];

        $return .= "</div>\n";
        $return .= "</div>\n";

        return $return;
    }

    public function renderCheckBox(EntryAbstract $item)
    {
        if ($item->is_dir()) {
            return '';
        }

        return "<div class='entry-info-button entry_checkbox'><input type='checkbox' name='selected-files[]' class='selected-files' value='".$item->get_id()."' id='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'/><label for='checkbox-".Processor::instance()->get_listtoken()."-{$item->get_id()}'></label></div>";
    }

    public function renderLabel(EntryAbstract $item)
    {
        if ($item->is_dir() || false === $this->has_labels) {
            return '';
        }

        return "<div class='entry-info-button entry-label-button' title='".esc_html__('Add Label', 'wpcloudplugins')."' tabindex='0'><i class='eva eva-bookmark eva-lg'></i></div>\n";
    }

    public static function render($attributes = [])
    {
        $attributes['data-max-items'] = Processor::instance()->get_shortcode_option('proofing_max_items');
        Filebrowser::render($attributes);

        self::enqueue_scripts();

        include_once sprintf('%s/templates/modules/proofing.php', USEYOURDRIVE_ROOTDIR);
    }

    public static function enqueue_scripts()
    {
        wp_enqueue_script('UseyourDrive.Proofing');
    }
}
