<?php

namespace TheLion\UseyourDrive\Integrations;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Elementor\Plugin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add a dynamic Elementor tag that returns the value of the ACF field.
class Elementor_Dynamic_Tag extends Tag
{
    public function get_name(): string
    {
        return 'acf-useyourdrive-url';
    }

    public function get_title(): string
    {
        return esc_html__('Google Drive Item', 'elementor-acf-useyourdrive-url-dynamic-tag');
    }

    public function get_group(): array
    {
        return ['acf'];
    }

    public function get_categories(): array
    {
        return [Module::URL_CATEGORY];
    }

    public function render(): void
    {
        $field = $this->get_settings('field');

        // Make sure that ACF if installed and activated
        if (!function_exists('get_field')) {
            echo 0;

            return;
        }

        echo get_field($field);
    }

    protected function register_controls(): void
    {
        $field = $this->get_first_field();
        $first_field_name = $field ? $field['name'] : '';

        $this->add_control(
            'field',
            [
                'label' => esc_html__('Field', 'elementor-acf-useyourdrive-url-dynamic-tag'),
                'type' => 'text',
                'default' => $first_field_name, // default is first field
                'placeholder' => esc_html__('ACF field name', 'elementor-acf-useyourdrive-url-dynamic-tag'),
            ]
        );
    }

    protected function get_first_field()
    {
        $first_field = '';

        // Try to get current post ID from Elementor editor
        $post_id = null;

        if (Plugin::$instance->editor->is_edit_mode()) {
            $post_id = get_post() ? get_post()->ID : null;
        }

        // Fallback to global post ID (works on frontend)
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if ($post_id) {
            $field_groups = acf_get_field_groups(['post_id' => $post_id]);
            foreach ($field_groups as $group) {
                $fields = acf_get_fields($group['key']);
                if ($fields) {
                    foreach ($fields as $field) {
                        if ('UseyourDrive_Field' === $field['type']) {
                            $first_field = $field;

                            break 2; // stop both loops
                        }
                    }
                }
            }
        }

        return $first_field;
    }
}