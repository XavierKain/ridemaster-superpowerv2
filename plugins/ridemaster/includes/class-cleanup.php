<?php
/**
 * RM_Cleanup — Data integrity and cascade deletion.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Cleanup {

	/**
	 * Coach IDs currently being deleted (recursion guard).
	 *
	 * @var int[]
	 */
	private static $deleting_coach = [];

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'delete_user',        [ $this, 'on_delete_user' ] );
		add_action( 'wp_trash_post',      [ $this, 'on_trash_coach' ] );
		add_action( 'before_delete_post', [ $this, 'on_delete_coach' ] );
		add_action( 'before_delete_post', [ $this, 'on_delete_camp' ] );
	}

	/*------------------------------------------------------------------
	 * Helper
	 *----------------------------------------------------------------*/

	/**
	 * Delete or trash every camp (product) linked to a coach.
	 *
	 * @param int  $coach_post_id Coach post ID.
	 * @param bool $force_delete  True = permanently delete, false = trash.
	 */
	private function cascade_camps( $coach_post_id, $force_delete = false ) {

		$camps = get_posts( [
			'post_type'   => 'product',
			'meta_key'    => '_coach_post_id',
			'meta_value'  => $coach_post_id,
			'numberposts' => -1,
			'post_status' => 'any',
			'fields'      => 'ids',
		] );

		foreach ( $camps as $camp_id ) {
			if ( $force_delete ) {
				wp_delete_post( $camp_id, true );
			} else {
				wp_trash_post( $camp_id );
			}
		}
	}

	/*------------------------------------------------------------------
	 * 1. User deleted — remove coach + camps
	 *----------------------------------------------------------------*/

	/**
	 * Hook: delete_user
	 *
	 * @param int $user_id The ID of the user being deleted.
	 */
	public function on_delete_user( $user_id ) {

		// Prevent recursion: on_delete_coach may call wp_delete_user which re-enters here.
		static $deleting_users = [];
		if ( in_array( $user_id, $deleting_users, true ) ) {
			return;
		}
		$deleting_users[] = $user_id;

		$coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );

		if ( ! $coach_post_id ) {
			return;
		}

		$this->cascade_camps( $coach_post_id, true );
		wp_delete_post( $coach_post_id, true );
	}

	/*------------------------------------------------------------------
	 * 2. Coach trashed — trash camps
	 *----------------------------------------------------------------*/

	/**
	 * Hook: wp_trash_post
	 *
	 * @param int $post_id The ID of the post being trashed.
	 */
	public function on_trash_coach( $post_id ) {

		if ( get_post_type( $post_id ) !== 'coach' ) {
			return;
		}

		$this->cascade_camps( $post_id, false );
	}

	/*------------------------------------------------------------------
	 * 3. Coach permanently deleted — delete camps, user, relations
	 *----------------------------------------------------------------*/

	/**
	 * Hook: before_delete_post
	 *
	 * @param int $post_id The ID of the post about to be deleted.
	 */
	public function on_delete_coach( $post_id ) {

		if ( get_post_type( $post_id ) !== 'coach' ) {
			return;
		}

		// Prevent recursion.
		if ( in_array( $post_id, self::$deleting_coach, true ) ) {
			return;
		}
		self::$deleting_coach[] = $post_id;

		// Cascade-delete all linked camps.
		$this->cascade_camps( $post_id, true );

		// Delete the post author unless they are an administrator.
		$post = get_post( $post_id );

		if ( $post && $post->post_author ) {
			$user = get_userdata( $post->post_author );

			if ( $user && ! in_array( 'administrator', (array) $user->roles, true ) ) {
				delete_user_meta( $user->ID, 'coach_post_id' );
				wp_delete_user( $user->ID );
			}
		}

		// Clean JetEngine relations.
		global $wpdb;
		$table = $wpdb->prefix . 'jet_rel_default';

		$wpdb->delete(
			$table,
			[ 'child_object_id' => $post_id ],
			[ '%d' ]
		);

		$wpdb->delete(
			$table,
			[ 'parent_object_id' => $post_id ],
			[ '%d' ]
		);
	}

	/*------------------------------------------------------------------
	 * 4. Camp (product) permanently deleted — clean relations
	 *----------------------------------------------------------------*/

	/**
	 * Hook: before_delete_post
	 *
	 * @param int $post_id The ID of the post about to be deleted.
	 */
	public function on_delete_camp( $post_id ) {

		if ( get_post_type( $post_id ) !== 'product' ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'jet_rel_default';

		$wpdb->delete(
			$table,
			[ 'child_object_id' => $post_id ],
			[ '%d' ]
		);
	}
}
