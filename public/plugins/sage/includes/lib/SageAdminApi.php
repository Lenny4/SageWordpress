<?php

namespace App\lib;

use App\Sage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin API class.
 */
final class SageAdminApi
{

    /**
     * Constructor function
     */
    public function __construct(public ?Sage $sage)
    {
        add_action('save_post', function (int $post_id = 0): void {
            $this->save_meta_boxes($post_id);
        }, 10, 1);
    }

    /**
     * Save metabox fields.
     *
     * @param integer $post_id Post ID.
     */
    public function save_meta_boxes(int $post_id = 0): void
    {

        if ($post_id === 0) {
            return;
        }

        $post_type = get_post_type($post_id);

        $fields = apply_filters($post_type . '_custom_fields', [], $post_type);

        if (!is_array($fields) || [] === $fields) {
            return;
        }

        foreach ($fields as $field) {
            if (isset($_REQUEST[$field['id']])) {
                update_post_meta($post_id, $field['id'], $this->validate_field($_REQUEST[$field['id']], $field['type']));
            } else {
                update_post_meta($post_id, $field['id'], '');
            }
        }
    }

    /**
     * Validate form field
     *
     * @param string $data Submitted value.
     * @param string $type Type of field to validate.
     * @return string       Validated value
     */
    public function validate_field(string $data = '', string $type = 'text'): string
    {

        return match ($type) {
            'text' => esc_attr($data),
            'url' => esc_url($data),
            'email' => is_email($data),
            default => $data,
        };
    }

    /**
     * Add meta box to the dashboard.
     *
     * @param string $id Unique ID for metabox.
     * @param string $title Display title of metabox.
     * @param array $post_types Post types to which this metabox applies.
     * @param string $context Context in which to display this metabox ('advanced' or 'side').
     * @param string $priority Priority of this metabox ('default', 'low' or 'high').
     * @param array|null $callback_args Any axtra arguments that will be passed to the display function for this metabox.
     */
    public function add_meta_box(
        string $id = '',
        string $title = '',
        array  $post_types = [],
        string $context = 'advanced',
        string $priority = 'default',
        array  $callback_args = null,
    ): void
    {

        // Get post type(s).
        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }

        // Generate each metabox.
        foreach ($post_types as $post_type) {
            add_meta_box($id, $title, function (object $post, array $args): void {
                $this->meta_box_content($post, $args);
            }, $post_type, $context, $priority, $callback_args);
        }
    }

    /**
     * Display metabox content
     *
     * @param object $post Post object.
     * @param array $args Arguments unique to this metabox.
     */
    public function meta_box_content(object $post, array $args): void
    {

        $fields = apply_filters($post->post_type . '_custom_fields', [], $post->post_type);

        if (!is_array($fields) || [] === $fields) {
            return;
        }

        echo '<div class="custom-field-panel">' . "\n";

        foreach ($fields as $field) {

            if (!isset($field['metabox'])) {
                continue;
            }

            if (!is_array($field['metabox'])) {
                $field['metabox'] = [$field['metabox']];
            }

            if (in_array($args['id'], $field['metabox'], true)) {
                $this->display_meta_box_field($field, $post);
            }
        }

        echo '</div>' . "\n";

    }

    /**
     * Dispay field in metabox
     *
     * @param array $field Field data.
     * @param object|null $post Post object.
     */
    public function display_meta_box_field(array $field = [], object $post = null): void
    {

        if (!is_array($field) || [] === $field) {
            return;
        }

        $field = '<p class="form-field"><label for="' . $field['id'] . '">' . $field['label'] . '</label>' . $this->display_field($field, $post, false) . '</p>' . "\n";

        echo $field;
    }

    /**
     * Generate HTML for displaying fields.
     *
     * @param array $data Data array.
     * @param object|null $post Post object.
     * @param boolean $echo Whether to echo the field HTML or return it.
     */
    public function display_field(array $data = [], object $post = null, bool $echo = true): string
    {

        // Get field info.
        $field = $data['field'] ?? $data;

        // Check for prefix on option name.
        $option_name = '';
        if (isset($data['prefix'])) {
            $option_name = $data['prefix'];
        }

        // Get saved data.
        $data = '';
        $option_name .= $field['id'];
        if ($post !== null) {

            // Get saved field data.
            $option = get_post_meta($post->ID, $field['id'], true);

            // Get data to display in field.
        } else {

            // Get saved option.
            $option = get_option($option_name);

            // Get data to display in field.
        }

        if (isset($option)) {
            $data = $option;
        }

        // Show default data if no option saved and default is supplied.
        if (false === $data && isset($field['default'])) {
            $data = $field['default'];
        } elseif (false === $data) {
            $data = '';
        }

        $html = '';

        switch ($field['type']) {

            case 'text':
            case 'url':
            case 'email':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '" />' . "\n";
                break;

            case 'password':
            case 'number':
            case 'hidden':
                $min = '';
                if (isset($field['min'])) {
                    $min = ' min="' . esc_attr($field['min']) . '"';
                }

                $max = '';
                if (isset($field['max'])) {
                    $max = ' max="' . esc_attr($field['max']) . '"';
                }

                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '"' . $min . $max . '/>' . "\n";
                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="" />' . "\n";
                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '">' . $data . '</textarea><br/>' . "\n";
                break;

            case 'checkbox':
                $checked = '';
                if ('on' === $data) {
                    $checked = 'checked="checked"';
                }

                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                break;

            case 'checkbox_multi':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if (in_array($k, (array)$data, true)) {
                        $checked = true;
                    }

                    $html .= '<p><label for="' . esc_attr($field['id'] . '_' . $k) . '" class="checkbox_multi"><input type="checkbox" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '[]" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label></p> ';
                }

                break;

            case 'radio':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if ($k === $data) {
                        $checked = true;
                    }

                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }

                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if ((string)$k === (string)$data) {
                        $selected = true;
                    }

                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }

                $html .= '</select> ';
                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) . '" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if (in_array($k, (array)$data, true)) {
                        $selected = true;
                    }

                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }

                $html .= '</select> ';
                break;

            case 'image':
                $image_thumb = '';
                if ($data) {
                    $image_thumb = wp_get_attachment_thumb_url($data);
                }

                $html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image', 'sage') . '" data-uploader_button_text="' . __('Use image', 'sage') . '" class="image_upload_button button" value="' . __('Upload new image', 'sage') . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __('Remove image', 'sage') . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
                break;

            case 'color':
                ?>
                <div class="color-picker" style="position:relative;">
                    <input type="text" name="<?php esc_attr_e($option_name); ?>" class="color"
                           value="<?php esc_attr_e($data); ?>"/>
                    <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;"
                         class="colorpicker"></div>
                </div>
                <?php
                break;

            case 'editor':
                wp_editor(
                    $data,
                    $option_name,
                    ['textarea_name' => $option_name]
                );
                break;
            case '2_select_multi':
                $html .= $this->sage->twig->render('common/2_select_multi.html.twig', [
                    'optionName' => $option_name,
                    'field' => $field,
                    'data' => $data,
                ]);
                break;

        }

        switch ($field['type']) {

            case 'checkbox_multi':
            case 'radio':
            case 'select_multi':
            case '2_select_multi':
                $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;

            default:
                if ($post === null) {
                    $html .= '<label for="' . esc_attr($field['id']) . '">' . "\n";
                }

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                if ($post === null) {
                    $html .= '</label>' . "\n";
                }

                break;
        }

        if (!$echo) {
            return $html;
        }

        echo $html;
        return '';
    }

}
