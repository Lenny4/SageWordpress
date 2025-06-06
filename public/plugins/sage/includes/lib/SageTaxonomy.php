<?php

namespace App\lib;

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
     */
    public ?string $taxonomy = null;

    /**
     * The plural name for the taxonomy terms.
     */
    public ?string $plural = null;

    /**
     * The singular name for the taxonomy terms.
     */
    public ?string $single = null;

    /**
     * The array of post types to which this taxonomy applies.
     */
    public ?array $post_types = null;

    /**
     * Taxonomy constructor.
     *
     * @param string $taxonomy Taxonomy variable nnam.
     * @param string $plural Taxonomy plural name.
     * @param string $single Taxonomy singular name.
     * @param array $post_types Affected post types.
     * @param array|null $taxonomy_args Taxonomy additional args.
     */
    public function __construct(
        string        $taxonomy = '',
        string        $plural = '',
        string        $single = '',
        array         $post_types = [],
        public ?array $taxonomy_args = []
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
            $post_types = [$post_types];
        }

        $this->post_types = $post_types;

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
        $labels = [
            'name' => $this->plural,
            'singular_name' => $this->single,
            'menu_name' => $this->plural,
            'all_items' => sprintf(__('All %s', Sage::TOKEN), $this->plural),
            'edit_item' => sprintf(__('Edit %s', Sage::TOKEN), $this->single),
            'view_item' => sprintf(__('View %s', Sage::TOKEN), $this->single),
            'update_item' => sprintf(__('Update %s', Sage::TOKEN), $this->single),
            'add_new_item' => sprintf(__('Add New %s', Sage::TOKEN), $this->single),
            'new_item_name' => sprintf(__('New %s Name', Sage::TOKEN), $this->single),
            'parent_item' => sprintf(__('Parent %s', Sage::TOKEN), $this->single),
            'parent_item_colon' => sprintf(__('Parent %s:', Sage::TOKEN), $this->single),
            'search_items' => sprintf(__('Search %s', Sage::TOKEN), $this->plural),
            'popular_items' => sprintf(__('Popular %s', Sage::TOKEN), $this->plural),
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', Sage::TOKEN), $this->plural),
            'add_or_remove_items' => sprintf(__('Add or remove %s', Sage::TOKEN), $this->plural),
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', Sage::TOKEN), $this->plural),
            'not_found' => sprintf(__('No %s found', Sage::TOKEN), $this->plural)];
        $args = ['label' => $this->plural, 'labels' => apply_filters($this->taxonomy . '_labels', $labels), 'hierarchical' => true, 'public' => true, 'show_ui' => true, 'show_in_nav_menus' => true, 'show_tagcloud' => true, 'meta_box_cb' => null, 'show_admin_column' => true, 'show_in_quick_edit' => true, 'update_count_callback' => '', 'show_in_rest' => true, 'rest_base' => $this->taxonomy, 'rest_controller_class' => 'WP_REST_Terms_Controller', 'query_var' => $this->taxonomy, 'rewrite' => true, 'sort' => ''];

        $args = array_merge($args, $this->taxonomy_args);

        register_taxonomy($this->taxonomy, $this->post_types, apply_filters($this->taxonomy . '_register_args', $args, $this->taxonomy, $this->post_types));
    }

}
