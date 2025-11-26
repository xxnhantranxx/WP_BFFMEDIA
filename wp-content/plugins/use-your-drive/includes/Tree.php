<?php
/**
 * @author WP Cloud Plugins
 * @copyright Copyright (c) 2023, WP Cloud Plugins
 *
 * @since       2.14.1
 * @see https://www.wpcloudplugins.com
 */

namespace TheLion\UseyourDrive;

defined('ABSPATH') || exit;

class Tree
{
    public $top_entry_id;
    public $current_entry_id;
    public $root_entry_id;

    /**
     * @var CacheNode
     */
    public $top_cached_node;

    public $tree = [];

    private $max_nodes = 5000;

    public function __construct($current_entry_id = null, $top_entry_id = null)
    {
        $this->top_entry_id = $this->root_entry_id = Processor::instance()->get_root_folder();
        $this->current_entry_id = $current_entry_id;
        if (!empty($top_entry_id)) {
            $this->top_entry_id = $top_entry_id;
        }
    }

    public function get_structure()
    {
        $this->top_cached_node = Client::instance()->get_entry($this->top_entry_id, true);

        if (false === $this->top_cached_node) {
            return [];
        }

        $this->tree = $this->load();

        return $this->tree;
    }

    public function load()
    {
        $tree[] = $this->build_leaf($this->top_cached_node);

        if (!$this->top_cached_node->has_children()) {
            return $tree;
        }

        $children = $this->top_cached_node->get_all_child_folders();

        // First process all normal folders
        foreach ($children as $child_id => $child_node) {
            if (false === Processor::instance()->_is_entry_authorized($child_node)) {
                unset($children[$child_id]);

                continue;
            }

            if (!$child_node->is_shortcut()) {
                $tree[$child_node->get_id()] = $this->build_leaf($child_node);
                unset($children[$child_id]);
            }
        }

        // Then process all shortcuts
        $original_nodes = [];
        foreach ($children as $child_id => $child_node) {
            $leaf = $this->build_leaf($child_node);

            $leaf['li_attr'] = ['is-shortcut' => 'yes'];
            $leaf['type'] = 'shortcut';
            $leaf['a_attr']['data-id'] = $child_node->get_original_node_id();

            $tree[$child_node->get_id()] = $leaf;

            if (!isset($original_nodes[$child_node->get_original_node_id()])) {
                $original_nodes[$child_node->get_original_node_id()] = [];
            }
            $original_nodes[$child_node->get_original_node_id()][] = $child_id;
        }

        // Finally add shortcuts nodes if not yet pressent in the tree
        foreach ($original_nodes as $original_node_id => $shortcut_ids) {
            if (isset($tree[$original_node_id])) {
                continue;
            }

            $original_node = Client::instance()->get_entry($original_node_id, false);
            $subfolders = $original_node->get_all_child_folders();

            $first_shortcut_id = reset($shortcut_ids);

            $tree[$first_shortcut_id]['id'] = $original_node_id;
            foreach ($subfolders as $subfolder) {
                if (false === Processor::instance()->_is_entry_authorized($subfolder)) {
                    continue;
                }

                $leaf = $this->build_leaf($subfolder);
                $tree[$leaf['id']] = $leaf;
            }
        }

        // Make sure that $tree does not have more nodes than supported by browser (5000)
        if (count($tree) > $this->max_nodes) {
            $tree = array_slice($tree, 0, $this->max_nodes);
        }

        return \array_values($tree);
    }

    private function build_leaf(CacheNode $node)
    {
        $leaf = [
            'id' => $node->get_id(),
            'parent' => $node->has_parent() ? $node->get_parent()->get_id() : '#',
            'text' => $node->get_name(),
            'a_attr' => [
                'data-id' => $node->get_id(),
            ],
        ];

        if ($node->get_id() === $this->root_entry_id) {
            if ('1' === Processor::instance()->get_shortcode_option('use_custom_roottext')) {
                $leaf['text'] = Processor::instance()->get_shortcode_option('root_text');
            }

            $leaf['icon'] = 'eva eva-home-outline';
            $leaf['parent'] = '#';
            $leaf['state']['opened'] = true;
        }

        if ($node->get_id() === $this->current_entry_id) {
            $leaf['state']['selected'] = true;
            $leaf['state']['opened'] = true;
        }

        if ($node->has_loaded_children() && 0 === count($node->get_all_sub_folders()) && false === $node->is_shortcut()) {
            $leaf['children'] = [];
            $leaf['li_attr'] = ['has-childen' => 'no'];
        }

        return $leaf;
    }
}
