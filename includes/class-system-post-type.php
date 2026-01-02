<?php
/**
 * System Post Type
 *
 * Registrerar och hanterar posttypen "System" för externa systemlänkar.
 *
 * @package Tranas_Intranet
 */

// Förhindra direktåtkomst
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Klass för System-posttypen
 */
class Tranas_System_Post_Type {

    /**
     * Post type slug
     */
    const POST_TYPE = 'tranas_system';

    /**
     * Konstruktor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 10, 2 );
    }

    /**
     * Registrera posttypen "System"
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x( 'System', 'Post type general name', 'tranas-intranet' ),
            'singular_name'         => _x( 'System', 'Post type singular name', 'tranas-intranet' ),
            'menu_name'             => _x( 'System', 'Admin Menu text', 'tranas-intranet' ),
            'name_admin_bar'        => _x( 'System', 'Add New on Toolbar', 'tranas-intranet' ),
            'add_new'               => __( 'Lägg till nytt', 'tranas-intranet' ),
            'add_new_item'          => __( 'Lägg till nytt system', 'tranas-intranet' ),
            'new_item'              => __( 'Nytt system', 'tranas-intranet' ),
            'edit_item'             => __( 'Redigera system', 'tranas-intranet' ),
            'view_item'             => __( 'Visa system', 'tranas-intranet' ),
            'all_items'             => __( 'Alla system', 'tranas-intranet' ),
            'search_items'          => __( 'Sök system', 'tranas-intranet' ),
            'parent_item_colon'     => __( 'Överordnat system:', 'tranas-intranet' ),
            'not_found'             => __( 'Inga system hittades.', 'tranas-intranet' ),
            'not_found_in_trash'    => __( 'Inga system hittades i papperskorgen.', 'tranas-intranet' ),
            'featured_image'        => _x( 'Systembild', 'Overrides the "Featured Image" phrase', 'tranas-intranet' ),
            'set_featured_image'    => _x( 'Ange systembild', 'Overrides the "Set featured image" phrase', 'tranas-intranet' ),
            'remove_featured_image' => _x( 'Ta bort systembild', 'Overrides the "Remove featured image" phrase', 'tranas-intranet' ),
            'use_featured_image'    => _x( 'Använd som systembild', 'Overrides the "Use as featured image" phrase', 'tranas-intranet' ),
            'archives'              => _x( 'Systemarkiv', 'The post type archive label', 'tranas-intranet' ),
            'insert_into_item'      => _x( 'Infoga i system', 'Overrides the "Insert into post" phrase', 'tranas-intranet' ),
            'uploaded_to_this_item' => _x( 'Uppladdat till detta system', 'Overrides the "Uploaded to this post" phrase', 'tranas-intranet' ),
            'filter_items_list'     => _x( 'Filtrera systemlista', 'Screen reader text', 'tranas-intranet' ),
            'items_list_navigation' => _x( 'Systemlistnavigering', 'Screen reader text', 'tranas-intranet' ),
            'items_list'            => _x( 'Systemlista', 'Screen reader text', 'tranas-intranet' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'system' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-external',
            'supports'           => array( 'title', 'thumbnail' ),
            'show_in_rest'       => false,
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Inaktivera Gutenberg för System-posttypen
     *
     * @param bool   $use_block_editor Om block-editorn ska användas.
     * @param string $post_type        Posttypen.
     * @return bool
     */
    public function disable_gutenberg( $use_block_editor, $post_type ) {
        if ( self::POST_TYPE === $post_type ) {
            return false;
        }
        return $use_block_editor;
    }
}

