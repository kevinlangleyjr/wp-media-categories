<?php
/**
 *	Plugin Name: WP Media Categories
 *	Plugin URI: https://github.com/kevinlangleyjr/wp-media-categories
 *	Description: Adds a custom taxononomy (media-categories) Media Categories to the Media Library
 *	Author: Kevin Langley Jr.
 *	Version: 0.0.1
 *	Author URI: http://kevinlangleyjr.com
 */

require_once __DIR__ . '/inc/Walker_WP_Media_Taxonomy_Checklist.php';

class WP_Media_Categories {

	CONST TAXONOMY = 'media-category';
	CONST VERSION = '0.0.4';

	static function init() {
		self::register_taxonomy();

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'add_category_dropdown' ) );
		add_action( 'wp_ajax_save-attachment-compat', array( __CLASS__, 'ajax_save_attachment_compat' ) );

		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'add_attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'attachment_fields_to_save' ), 0, 2 );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'filter_ajax_query_attachments_args' ) );
	}

	static function enqueue_scripts() {

		if ( wp_script_is( 'media-editor' ) ) {

			$terms = get_terms( self::TAXONOMY, array(
				'hide_empty' => false,
				'hierarchical' => true
			) );

			$terms = wp_list_pluck( $terms, 'name', 'term_id' );

			wp_enqueue_style( 'wp-media-categories-media-views', plugins_url( 'css/wp-media-categories-media-views.css', __FILE__ ), false, self::VERSION );
			wp_enqueue_script( 'wp-media-categories-media-views', plugins_url( 'js/wp-media-categories-media-views.js', __FILE__ ), array( 'media-views' ), self::VERSION, true );

			wp_localize_script( 'wp-media-categories-media-views', 'WP_Media_Categories', array(
				'terms' => $terms
			) );
		}
	}


	static function register_taxonomy() {
		register_taxonomy( self::TAXONOMY, array( 'attachment' ), array(
			'hierarchical' => true,
			'show_admin_column' => true
		) );
	}

	static function add_category_dropdown(){
		global $pagenow;
		if ( 'upload.php' == $pagenow ) {
			$args = array(
				'taxonomy'        => self::TAXONOMY,
				'name'            => self::TAXONOMY,
				'show_option_all' => __( 'View all media categories' ),
				'hide_empty'      => false,
				'hierarchical'    => true,
				'orderby'         => 'name',
				'show_count'      => false,
				'value_field'     => 'slug'
			);

			if ( isset( $_GET['media-category'] ) && '' != $_GET['media-category'] ) {
				$args['selected'] = sanitize_text_field( $_GET['media-category'] );
			}

			wp_dropdown_categories( $args );
		}
	}

	static function add_attachment_fields_to_edit( $form_fields, $post ){
		$terms = get_object_term_cache( $post->ID, self::TAXONOMY );
		$field = array();

		$taxonomy_obj = (array) get_taxonomy( self::TAXONOMY );
		if ( ! $taxonomy_obj['public'] || ! $taxonomy_obj['show_ui'] ) {
			continue;
		}

		if ( false === $terms ) {
			$terms = wp_get_object_terms( $post->ID, self::TAXONOMY );
		}

		$values = wp_list_pluck( $terms, 'term_id' );

		ob_start();

		wp_terms_checklist( $post->ID, array(
			'taxonomy' => self::TAXONOMY,
			'checked_ontop' => false,
			'walker' => new Walker_WP_Media_Taxonomy_Checklist( $post->ID )
		) );

		$output = ob_get_clean();

		if( !empty( $output ) ) {
			$output = '<ul class="term-list">' . $output . '</ul>';
			$output .= wp_nonce_field( 'save_attachment_media_categories', 'media_category_nonce', false, false );
		} else {
			$output = '<ul class="term-list"><li>No ' . $taxonomy_obj['label'] . '</li></ul>';
		}

		$field = array(
			'label' => !empty( $taxonomy_obj['label'] ) ? $taxonomy_obj['label'] : self::TAXONOMY,
			'value' => join(', ', $values),
			'show_in_edit' => false,
			'input' => 'html',
			'html' => $output
		);

		$form_fields[self::TAXONOMY] = $field;

		return $form_fields;
	}

	static function attachment_fields_to_save( $post, $attachment_data ) {
		if(
			defined('DOING_AJAX') && DOING_AJAX &&
			isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'save-attachment-compat' &&
			isset( $_REQUEST['id'] ) && isset( $_REQUEST['media_category_nonce'] ) &&
			wp_verify_nonce( $_REQUEST['media_category_nonce'], 'save_attachment_media_categories' )
		) {
			$attachment_id = intval( $_REQUEST['id'] );

			if( empty( $attachment_id ) ){
				wp_send_json_error();
			}

			foreach ( get_attachment_taxonomies($post) as $taxonomy ) {
				if ( isset($_REQUEST['tax_input'][$taxonomy]) ){
					$terms = array_filter( array_map('intval', $_REQUEST['tax_input'][$taxonomy]) );
					wp_set_object_terms($attachment_id, $terms, $taxonomy, false);
				} else {
					wp_set_object_terms($attachment_id, array(), $taxonomy, false);
				}
			}
		}
		return $post;
	}

	static function filter_ajax_query_attachments_args( $query_args ) {
		if( isset( $_REQUEST['query']['media-category'] ) ){
			$media_category = intval( sanitize_text_field( $_REQUEST['query']['media-category'] ) );
			if( is_numeric( $media_category ) ){
				$query_args['tax_query'] = array( array(
					'taxonomy'	=> self::TAXONOMY,
					'field'		=> 'id',
					'terms'		=> $media_category
				) );

				unset( $query_args['media-category'] );
			}
		}
		return $query_args;
	}
}

add_action( 'init', array( 'WP_Media_Categories', 'init' ) );