<?php
/**
 * Post type declaration file.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post type declaration class.
 */
final class SagePostType
{

    /**
     * The name for the custom post type.
     */
    public ?string $post_type = null;

    /**
     * The plural name for the custom post type posts.
     */
    public ?string $plural = null;

    /**
     * The singular name for the custom post type posts.
     */
    public ?string $single = null;

    /**
     * Constructor
     *
     * @param string $post_type Post type.
     * @param string $plural Post type plural name.
     * @param string $single Post type singular name.
     * @param string $description Post type description.
     * @param array $options Post type options.
     */
    public function __construct(
        string         $post_type = '',
        string         $plural = '',
        string         $single = '',
        public ?string $description = '',
        public ?array  $options = [],
    )
    {

        if ($post_type === '' || $plural === '' || $single === '') {
            return;
        }

        // Post type name and labels.
        $this->post_type = $post_type;
        $this->plural = $plural;
        $this->single = $single;

        // Regsiter post type.
        add_action('init', function (): void {
            $this->register_post_type();
        });

        // Display custom update messages for posts edits.
        add_filter('post_updated_messages', fn(array $messages = []): array => $this->updated_messages($messages));
        add_filter('bulk_post_updated_messages', fn(array $bulk_messages = [], array $bulk_counts = []): array => $this->bulk_updated_messages($bulk_messages, $bulk_counts), 10, 2);
    }

    /**
     * Register new post type
     */
    public function register_post_type(): void
    {
        $labels = [
            'name' => $this->plural,
            'singular_name' => $this->single,
            'name_admin_bar' => $this->single,
            'add_new' => _x('Add New', $this->post_type, 'sage'),
            'add_new_item' => sprintf(__('Add New %s', 'sage'), $this->single),
            'edit_item' => sprintf(__('Edit %s', 'sage'), $this->single),
            'new_item' => sprintf(__('New %s', 'sage'), $this->single),
            'all_items' => sprintf(__('All %s', 'sage'), $this->plural),
            'view_item' => sprintf(__('View %s', 'sage'), $this->single),
            'search_items' => sprintf(__('Search %s', 'sage'), $this->plural),
            'not_found' => sprintf(__('No %s Found', 'sage'), $this->plural),
            'not_found_in_trash' => sprintf(__('No %s Found In Trash', 'sage'), $this->plural),
            'parent_item_colon' => sprintf(__('Parent %s'), $this->single),
            'menu_name' => $this->plural,
        ];

        $args = [
            'labels' => apply_filters($this->post_type . '_labels', $labels),
            'description' => $this->description,
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base' => $this->post_type,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports' => ['title', 'editor', 'excerpt', 'comments', 'thumbnail'],
            'menu_position' => 5,
            'menu_icon' => 'dashicons-admin-post'
        ];

        $args = array_merge($args, $this->options);

        register_post_type($this->post_type, apply_filters($this->post_type . '_register_args', $args, $this->post_type));
    }

    /**
     * Set up admin messages for post type
     *
     * @param array $messages Default message.
     * @return array           Modified messages.
     */
    public function updated_messages(array $messages = []): array
    {
        global $post, $post_ID;
        $messages[$this->post_type] = [
            0 => '',
            1 => sprintf(__('%1$s updated. %2$sView %3$s%4$s.', 'sage'), $this->single, '<a href="' . esc_url(get_permalink($post_ID)) . '">', $this->single, '</a>'),
            2 => __('Custom field updated.', 'sage'),
            3 => __('Custom field deleted.', 'sage'),
            4 => sprintf(__('%1$s updated.', 'sage'), $this->single),
            5 => isset($_GET['revision']) ? sprintf(__('%1$s restored to revision from %2$s.', 'sage'), $this->single, wp_post_revision_title((int)$_GET['revision'], false)) : false,
            6 => sprintf(__('%1$s published. %2$sView %3$s%4s.', 'sage'), $this->single, '<a href="' . esc_url(get_permalink($post_ID)) . '">', $this->single, '</a>'),
            7 => sprintf(__('%1$s saved.', 'sage'), $this->single),
            8 => sprintf(__('%1$s submitted. %2$sPreview post%3$s%4$s.', 'sage'), $this->single, '<a target="_blank" href="' . esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))) . '">', $this->single, '</a>'),
            9 => sprintf(__('%1$s scheduled for: %2$s. %3$sPreview %4$s%5$s.', 'sage'), $this->single, '<strong>' . date_i18n(__('M j, Y @ G:i', 'sage'), strtotime((string)$post->post_date)) . '</strong>', '<a target="_blank" href="' . esc_url(get_permalink($post_ID)) . '">', $this->single, '</a>'),
            10 => sprintf(__('%1$s draft updated. %2$sPreview %3$s%4$s.', 'sage'), $this->single, '<a target="_blank" href="' . esc_url(add_query_arg('preview', 'true', get_permalink($post_ID))) . '">', $this->single, '</a>')
        ];

        return $messages;
    }

    /**
     * Set up bulk admin messages for post type
     *
     * @param array $bulk_messages Default bulk messages.
     * @param array $bulk_counts Counts of selected posts in each status.
     * @return array                Modified messages.
     */
    public function bulk_updated_messages(array $bulk_messages = [], array $bulk_counts = []): array
    {

        $bulk_messages[$this->post_type] = [
            'updated' => sprintf(_n('%1$s %2$s updated.', '%1$s %3$s updated.', $bulk_counts['updated'], 'sage'), $bulk_counts['updated'], $this->single, $this->plural),
            'locked' => sprintf(_n('%1$s %2$s not updated, somebody is editing it.', '%1$s %3$s not updated, somebody is editing them.', $bulk_counts['locked'], 'sage'), $bulk_counts['locked'], $this->single, $this->plural),
            'deleted' => sprintf(_n('%1$s %2$s permanently deleted.', '%1$s %3$s permanently deleted.', $bulk_counts['deleted'], 'sage'), $bulk_counts['deleted'], $this->single, $this->plural),
            'trashed' => sprintf(_n('%1$s %2$s moved to the Trash.', '%1$s %3$s moved to the Trash.', $bulk_counts['trashed'], 'sage'), $bulk_counts['trashed'], $this->single, $this->plural),
            'untrashed' => sprintf(_n('%1$s %2$s restored from the Trash.', '%1$s %3$s restored from the Trash.', $bulk_counts['untrashed'], 'sage'), $bulk_counts['untrashed'], $this->single, $this->plural)
        ];

        return $bulk_messages;
    }

}
