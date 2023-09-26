<?php
/**
 * Taxonomy functions file.
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Taxonomy functions class.
 */
final class SageTaxonomy
{

    /**
     * The name for the taxonomy.
     *
     * @access  public
     * @since   1.0.0
     */
    public ?string $taxonomy = null;

    /**
     * The plural name for the taxonomy terms.
     *
     * @access  public
     * @since   1.0.0
     */
    public ?string $plural = null;

    /**
     * The singular name for the taxonomy terms.
     *
     * @access  public
     * @since   1.0.0
     */
    public ?string $single = null;

    /**
     * The array of post types to which this taxonomy applies.
     *
     * @access  public
     * @since   1.0.0
     */
    public ?array $post_types = null;

    /**
     * The array of taxonomy arguments
     *
     * @access  public
     * @since   1.0.0
     */
    public ?array $taxonomy_args = null;

    /**
     * Taxonomy constructor.
     *
     * @param string $taxonomy Taxonomy variable nnam.
     * @param string $plural Taxonomy plural name.
     * @param string $single Taxonomy singular name.
     * @param array $post_types Affected post types.
     * @param array $tax_args Taxonomy additional args.
     */
    public function __construct(
        string $taxonomy = '',
        string $plural = '',
        string $single = '',
        array  $post_types = [],
        array  $tax_args = []
    )
    {

        if ($taxonomy === '' || $plural === '' || $single === '') {
            return;
        }

        // Post type name and labels.
        $this->taxonomy = $taxonomy;
        $this->plural = $plural;
        $this->single = $single;
        if (!is_array($post_types)) {
            $post_types = array($post_types);
        }

        $this->post_types = $post_types;
        $this->taxonomy_args = $tax_args;

        // Register taxonomy.
        add_action('init', function (): void {
            $this->register_taxonomy();
        });
    }

    /**
     * Register new taxonomy
     */
    public function register_taxonomy(): void
    {
        //phpcs:disable
        $labels = array(
            'name' => $this->plural,
            'singular_name' => $this->single,
            'menu_name' => $this->plural,
            'all_items' => sprintf(__('All %s', 'sage'), $this->plural),
            'edit_item' => sprintf(__('Edit %s', 'sage'), $this->single),
            'view_item' => sprintf(__('View %s', 'sage'), $this->single),
            'update_item' => sprintf(__('Update %s', 'sage'), $this->single),
            'add_new_item' => sprintf(__('Add New %s', 'sage'), $this->single),
            'new_item_name' => sprintf(__('New %s Name', 'sage'), $this->single),
            'parent_item' => sprintf(__('Parent %s', 'sage'), $this->single),
            'parent_item_colon' => sprintf(__('Parent %s:', 'sage'), $this->single),
            'search_items' => sprintf(__('Search %s', 'sage'), $this->plural),
            'popular_items' => sprintf(__('Popular %s', 'sage'), $this->plural),
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'sage'), $this->plural),
            'add_or_remove_items' => sprintf(__('Add or remove %s', 'sage'), $this->plural),
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'sage'), $this->plural),
            'not_found' => sprintf(__('No %s found', 'sage'), $this->plural),
        );
        //phpcs:enable
        $args = array(
            'label' => $this->plural,
            'labels' => apply_filters($this->taxonomy . '_labels', $labels),
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'meta_box_cb' => null,
            'show_admin_column' => true,
            'show_in_quick_edit' => true,
            'update_count_callback' => '',
            'show_in_rest' => true,
            'rest_base' => $this->taxonomy,
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'query_var' => $this->taxonomy,
            'rewrite' => true,
            'sort' => '',
        );

        $args = array_merge($args, $this->taxonomy_args);

        register_taxonomy($this->taxonomy, $this->post_types, apply_filters($this->taxonomy . '_register_args', $args, $this->taxonomy, $this->post_types));
    }

}
