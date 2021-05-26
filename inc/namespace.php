<?php
/**
 * Workflow Module functions.
 *
 * @package altis/workflow
 */

namespace Altis\Workflow;

use Altis;

/**
 * Bootstrap Workflow module.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'muplugins_loaded', __NAMESPACE__ . '\\load_notifications', 0 );
	add_action( 'muplugins_loaded', __NAMESPACE__ . '\\load_publication_checklist', 0 );
	add_action( 'muplugins_loaded', __NAMESPACE__ . '\\load_duplicate_posts' );
	add_action( 'admin_menu', __NAMESPACE__ . '\\remove_duplicate_post_admin_page', 99 );
	add_action( 'admin_init', __NAMESPACE__ . '\\filter_duplicate_post_columns', 1000 );
	add_filter( 'duplicate_post_enabled_post_types', __NAMESPACE__ . '\\maybe_override_enabled_post_types' );
}

/**
 * Load notifications plugin.
 *
 * @return void
 */
function load_notifications() {
	Notifications\setup();
}

/**
 * Load the Publication Checklist feature, if enabled.
 */
function load_publication_checklist() {
	$config = Altis\get_config()['modules']['workflow']['publication-checklist'] ?? null;
	if ( ! $config ) {
		return;
	}

	if ( ! is_array( $config ) ) {
		$config = [];
	}

	if ( $config['block-on-failing'] ?? false ) {
		add_filter( 'altis.publication-checklist.block_on_failing', '__return_true' );
	}
	if ( $config['hide-column'] ?? false ) {
		add_filter( 'altis.publication-checklist.show_tasks_column', '__return_false' );
	}

	require_once Altis\ROOT_DIR . '/vendor/humanmade/publication-checklist/plugin.php';
}

/**
 * Load Yoast Duplicate Posts, if enabled.
 */
function load_duplicate_posts() {
	$config = Altis\get_config()['modules']['workflow']['clone-republish'] ?? null;

	// Bail if Clone & Republish is disabled.
	if ( ! $config ) {
		return;
	}

	// Load the main plugin file.
	require_once Altis\ROOT_DIR . '/vendor/yoast/duplicate-post/duplicate-post.php';
}

/**
 * Remove the Duplicate Post settings page.
 */
function remove_duplicate_post_admin_page() {
	remove_submenu_page( 'options-general.php', 'duplicatepost' );
}

/**
 * Override the Duplicate Post supported post types if the config option defines new or different post types than the defautls.
 *
 * Default supported post types are 'post' and 'page'.
 *
 * Note: The configured option will _replace_ any enabled post types by default.
 *
 * @param array $enabled_post_types The post types that are supported by Duplicate Post.
 * @return array The filtered array of supported post types.
 */
function maybe_override_enabled_post_types( array $enabled_post_types ) : array {
	$post_types = Altis\get_config()['modules']['workflow']['clone-republish']['post-types'] ?? null;

	if ( ! $post_types ) {
		return $enabled_post_types;
	}

	return $post_types;
}

/**
 * Remove the duplicate_post_original_item column.
 *
 * This column, on the post list, causes display/formatting issues and doesn't add any value, since the information is repeated in the title column.
 *
 * @param array $columns The array of post columns.
 *
 * @return array The filtered array of columns.
 */
function remove_duplicate_post_original_item_column( array $columns ) : array {
	unset( $columns['duplicate_post_original_item'] );
	return $columns;
}

/**
 * Removes the "Original Post" column in the post list for all supported post types.
 */
function filter_duplicate_post_columns() {
	$enabled_post_types = get_duplicate_post_types();
	foreach ( $enabled_post_types as $post_type ) {
		add_filter( "manage_{$post_type}_posts_columns", __NAMESPACE__ . '\\remove_duplicate_post_original_item_column', 11 );
	}
}

/**
 * Return an array of post types supported by Duplicate Post.
 *
 * @uses get_post_types() The get_post_types function must be loaded for this function to work properly.
 *
 * @return array The array of enabled post types.
 */
function get_duplicate_post_types() : array {
	$public_post_types = get_post_types( [ 'public' => true ], 'names' );
	$post_types = Altis\get_config()['modules']['workflow']['clone-republish']['post-types'] ?? $public_post_types;

	return apply_filters( 'duplicate_post_enabled_post_types', $post_types );
}
