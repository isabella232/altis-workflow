<?php
/**
 * Default Notications for Workflow Module.
 *
 * @package altis/workflow
 */

namespace Altis\Workflow\Notifications;

use function Altis\get_config;
use HM\Workflows\Workflow;

/**
 * Interpret configuration and set up hooks.
 */
function setup() {
	$config = get_config()['modules']['workflow'];

	if ( $config['posts-workflow'] ?? false ) {
		add_action( 'hm.workflows.init', __NAMESPACE__ . '\\posts_workflow' );
	}

	if ( $config['editorial-workflow'] ?? false ) {
		add_action( 'hm.workflows.init', __NAMESPACE__ . '\\editorial_workflow' );
	}
}

/**
 * Add a default posts on publishing workflow.
 */
function posts_workflow() {
	// When a post is submitted for review.
	Workflow::register( 'post_submitted_for_review' )
		->when( 'draft_to_pending' )
		->what( __( 'Ready for review: "%title%" by %author%', 'altis' ) )
		->who( [ 'assignee', 'editor' ] )
		->where( 'email' )
		->where( 'dashboard' );

	// When a post is published.
	Workflow::register( 'post_published' )
		->when( 'publish_post' )
		->what( __( 'Post published: %title%', 'altis' ) )
		->who( [ 'post_author', 'assignee' ] )
		->where( 'email' )
		->where( 'dashboard' );
}

/**
 * Default editorial workflow for comments and assignments.
 */
function editorial_workflow() {
	// When assignees are updated.
	Workflow::register( 'assignee_updated' )
		->when( [
			'action' => 'add_post_meta',
			'callback' => function ( $object_id, $meta_key, $meta_value ) {
				// Don't trigger for any other meta key.
				if ( $meta_key !== 'assignees' ) {
					return null;
				}

				return [
					'post_id' => absint( $object_id ),
					'assignee' => absint( $meta_value ),
				];
			},
			'accepted_args' => 3,
		] )
		->what(
			function ( $post_id ) {
				// translators: %s = a post title.
				return sprintf( __( '"%s" has been assigned to you', 'altis' ), get_the_title( $post_id ) );
			},
			'',
			[
				'edit' => [
					'text' => __( 'Edit post', 'altis' ),
					'callback_or_url' => function ( $post_id ) {
						return get_edit_post_link( $post_id, 'raw' );
					},
					'args' => function ( $post_id ) {
						return [ 'post_id' => $post_id ];
					},
					'schema' => [
						'post_id' => 'intval',
					],
				],
			]
		)
		->who( function ( $post_id, $assignee ) {
			return get_user_by( 'id', $assignee );
		} )
		->where( 'email' )
		->where( 'dashboard' );

	// When an editorial comment is added.
	Workflow::register( 'editorial_comment_added' )
		->when( 'new_editorial_comment' )
		->what(
			// translators: %post.title% = a post title, %comment.author% = comment author's name
			__( 'New comment on: %post.title% from %comment.author%', 'altis' ),
			'%comment.text%',
		)
		->who( 'assignees' )
		->who( 'post_author' )
		->where( 'email' )
		->where( 'dashboard' );
}