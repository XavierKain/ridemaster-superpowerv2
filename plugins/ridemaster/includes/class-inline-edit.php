<?php
/**
 * RideMaster Inline Edit Module
 * Inline editing for coach profiles and camp products on the frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Inline_Edit {

	/**
	 * Coach field configuration (14 fields).
	 * The field_name (array key) matches the CSS class rm-edit-{field_name}.
	 */
	private $coach_field_config = [
		'coach_first_name'       => [ 'type' => 'text', 'required' => true, 'label' => 'First Name', 'placeholder' => 'Your first name' ],
		'coach_last_name'        => [ 'type' => 'text', 'required' => true, 'label' => 'Last Name', 'placeholder' => 'Your last name' ],
		'coach_bio'              => [ 'type' => 'wysiwyg', 'label' => 'Bio', 'placeholder' => 'Describe yourself, your background, your passion...' ],
		'coach_location'         => [ 'type' => 'text', 'label' => 'Location', 'placeholder' => 'City, Country (e.g. Tarifa, Spain)' ],
		'coach_years_experience' => [ 'type' => 'number', 'min' => 0, 'max' => 50, 'label' => 'Years of Experience', 'placeholder' => 'e.g. 10' ],
		'coach_certifications'   => [ 'type' => 'repeater', 'label' => 'Certifications', 'sub_field' => 'certifications', 'placeholder' => 'Add your certifications (e.g. IKO Level 3)' ],
		'coach_experience'       => [ 'type' => 'wysiwyg', 'label' => 'Experience', 'meta_key' => 'experience', 'placeholder' => 'Describe your experience in the sport...' ],
		'coach_instagram'        => [ 'type' => 'url', 'label' => 'Instagram', 'meta_key' => 'instagram', 'placeholder' => 'https://instagram.com/your-account' ],
		'coach_youtube'          => [ 'type' => 'url', 'label' => 'YouTube', 'meta_key' => 'youtube', 'placeholder' => 'https://youtube.com/your-channel' ],
		'coach_website'          => [ 'type' => 'url', 'label' => 'Website', 'meta_key' => 'website', 'placeholder' => 'https://your-website.com' ],
		'coach_profile_photo'    => [ 'type' => 'featured_image', 'label' => 'Profile Photo', 'placeholder' => 'Add your profile photo' ],
		'coach_cover_photo'      => [ 'type' => 'image', 'label' => 'Cover Photo', 'meta_key' => 'cover_image', 'placeholder' => 'Add a cover photo' ],
		'coach_sports'           => [ 'type' => 'taxonomy', 'taxonomy' => 'sport', 'label' => 'Sports', 'placeholder' => 'Select your sports' ],
		'coach_languages'        => [ 'type' => 'taxonomy', 'taxonomy' => 'language', 'label' => 'Languages', 'placeholder' => 'Select your languages' ],
	];

	/**
	 * Camp field configuration (13 fields).
	 * Extra properties: storage, post_field, woo_sync, woo_stock, date_pair.
	 */
	private $camp_field_config = [
		'camp_title'         => [ 'type' => 'text', 'required' => true, 'label' => 'Camp Title', 'placeholder' => 'Camp name', 'storage' => 'post_field', 'post_field' => 'post_title' ],
		'camp_description'   => [ 'type' => 'wysiwyg', 'label' => 'Description', 'placeholder' => 'Describe your camp...', 'storage' => 'post_field', 'post_field' => 'post_content' ],
		'camp_thumbnail'     => [ 'type' => 'featured_image', 'label' => 'Camp Image', 'placeholder' => 'Main camp image' ],
		'camp_gallery'       => [ 'type' => 'gallery', 'label' => 'Gallery', 'placeholder' => 'Add camp photos', 'meta_key' => '_product_image_gallery' ],
		'camp_price'         => [ 'type' => 'number', 'min' => 0, 'label' => 'Price (€)', 'placeholder' => 'e.g. 990', 'meta_key' => '_regular_price', 'woo_sync' => true ],
		'camp_max_spots'     => [ 'type' => 'number', 'min' => 0, 'label' => 'Max Spots', 'placeholder' => 'e.g. 12', 'meta_key' => '_stock', 'woo_stock' => true ],
		'camp_full_date'     => [ 'type' => 'date_range', 'label' => 'Full Date', 'placeholder' => 'Select camp dates' ],
		'camp_spot'          => [ 'type' => 'cpt_select', 'post_type' => 'spot', 'label' => 'Spot', 'placeholder' => 'Select the spot' ],
		'camp_schedule'      => [ 'type' => 'textarea', 'label' => 'Schedule', 'placeholder' => 'Describe the daily schedule...' ],
		'camp_included'      => [ 'type' => 'repeater', 'label' => 'Included', 'sub_field' => 'included_in_the_camp', 'placeholder' => 'What is included in the camp' ],
		'camp_not_included'  => [ 'type' => 'repeater', 'label' => 'Not Included', 'sub_field' => 'not_included_in_the_camp', 'placeholder' => 'What is not included' ],
		'camp_sport'         => [ 'type' => 'taxonomy', 'taxonomy' => 'sport', 'label' => 'Sport', 'placeholder' => 'Select the sport' ],
		'camp_level'         => [ 'type' => 'taxonomy', 'taxonomy' => 'level', 'label' => 'Level', 'placeholder' => 'Select the level' ],
	];

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_rm_inline_save', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_rm_inline_repair', [ $this, 'ajax_repair' ] );
		add_action( 'template_redirect', [ $this, 'nocache_profile_page' ], 1 );
		add_filter( 'get_post_metadata', [ $this, 'filter_repeater_meta_for_public' ], 10, 4 );
		add_filter( 'get_post_metadata', [ $this, 'filter_wysiwyg_meta_for_dashboard' ], 20, 4 );
	}

	// =========================================================================
	// CONTEXT DETECTION
	// =========================================================================

	/**
	 * Detect the current editing context.
	 * Returns 'coach', 'camp', or false.
	 *
	 * WARNING: This method calls get_post_meta / get_user_meta (via is_camp_page).
	 * NEVER call it from a get_post_metadata filter — it will cause infinite recursion.
	 * Use is_editable_page() for lightweight page-type detection in filters.
	 */
	private function detect_context() {
		if ( $this->is_coach_profile_page() ) {
			return 'coach';
		}
		if ( $this->is_camp_page() ) {
			return 'camp';
		}
		return false;
	}

	/**
	 * Lightweight page-type check (minimal DB calls).
	 * Safe to call from get_post_metadata filters without causing recursion.
	 * Returns true if we're on a single coach page OR a single product page.
	 * Does NOT check full ownership — only used to decide filter behavior.
	 */
	private function is_editable_page() {
		if ( is_singular( 'coach' ) ) {
			return true;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if we're on a single coach page owned by the current user.
	 * This enables inline edit on the public coach single page (not the dashboard).
	 */
	private function is_coach_profile_page() {
		if ( ! is_singular( 'coach' ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			return false;
		}

		// Check ownership: the logged-in coach's coach_post_id must match the displayed post
		$user_coach_id = get_user_meta( $user_id, 'coach_post_id', true );
		return $user_coach_id && intval( $user_coach_id ) === intval( get_the_ID() );
	}

	/**
	 * Check if we're on a single product page owned by the current coach.
	 */
	private function is_camp_page() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			return false;
		}

		$product_id = get_the_ID();
		return $this->coach_owns_camp( $user_id, $product_id );
	}

	/**
	 * Check if a coach user owns a camp (product).
	 * Tries multiple methods:
	 * 1. _coach_post_id meta on product (if set by a snippet/meta box)
	 * 2. JetEngine Relations API (relation ID 20)
	 * 3. Post author match (fallback)
	 */
	private function coach_owns_camp( $user_id, $product_id ) {
		$user_coach_id = get_user_meta( $user_id, 'coach_post_id', true );

		// Method 1: Direct meta _coach_post_id on product
		$camp_coach_id = get_post_meta( $product_id, '_coach_post_id', true );
		if ( $camp_coach_id && $user_coach_id && intval( $camp_coach_id ) === intval( $user_coach_id ) ) {
			return true;
		}

		// Method 2: JetEngine Relations API
		if ( $user_coach_id && function_exists( 'jet_engine' ) && isset( jet_engine()->relations ) ) {
			try {
				$relations_manager = jet_engine()->relations;
				// Try to get the relation by ID 20
				if ( method_exists( $relations_manager, 'get_active_relations' ) ) {
					$active = $relations_manager->get_active_relations();
					if ( ! empty( $active ) ) {
						foreach ( $active as $relation ) {
							if ( method_exists( $relation, 'get_args' ) ) {
								$args = $relation->get_args();
								if ( isset( $args['id'] ) && intval( $args['id'] ) === 20 ) {
									// Found the coach-camp relation — check parents
									if ( method_exists( $relation, 'get_parents' ) ) {
										$parents = $relation->get_parents( $product_id, 'ids' );
										if ( is_array( $parents ) && in_array( intval( $user_coach_id ), array_map( 'intval', $parents ), true ) ) {
											return true;
										}
									}
									break;
								}
							}
						}
					}
				}
			} catch ( \Throwable $e ) {
				error_log( '[RM Inline Edit] JetEngine relation check error: ' . $e->getMessage() );
			}
		}

		// Method 3: Post author match (last resort fallback)
		$post = get_post( $product_id );
		if ( $post && intval( $post->post_author ) === $user_id ) {
			return true;
		}

		// Debug log to help diagnose ownership issues
		error_log( sprintf(
			'[RM Inline Edit] Ownership check FAILED — user_id=%d, user_coach_id=%s, product_id=%d, _coach_post_id=%s, post_author=%s',
			$user_id,
			$user_coach_id ?: '(empty)',
			$product_id,
			$camp_coach_id ?: '(empty)',
			$post ? $post->post_author : '(no post)'
		) );

		return false;
	}

	/**
	 * Get the related CPT post for a product via JetEngine relation.
	 * Returns the first related post ID, or 0 if none found.
	 */
	private function get_related_cpt( $post_id, $target_post_type ) {
		if ( ! function_exists( 'jet_engine' ) || ! isset( jet_engine()->relations ) ) {
			return 0;
		}

		try {
			$relations_manager = jet_engine()->relations;
			if ( ! method_exists( $relations_manager, 'get_active_relations' ) ) {
				return 0;
			}

			$active    = $relations_manager->get_active_relations();
			$post_type = get_post_type( $post_id );

			// JetEngine prefixes CPT names with "posts::" in relation args
			$obj_matches = function ( $obj, $type ) {
				return $obj === $type || $obj === 'posts::' . $type;
			};

			foreach ( $active as $relation ) {
				if ( ! method_exists( $relation, 'get_args' ) ) {
					continue;
				}
				$args       = $relation->get_args();
				$parent_obj = isset( $args['parent_object'] ) ? $args['parent_object'] : '';
				$child_obj  = isset( $args['child_object'] ) ? $args['child_object'] : '';

				$is_parent = ( $obj_matches( $parent_obj, $post_type ) && $obj_matches( $child_obj, $target_post_type ) );
				$is_child  = ( $obj_matches( $child_obj, $post_type ) && $obj_matches( $parent_obj, $target_post_type ) );

				if ( ! $is_parent && ! $is_child ) {
					continue;
				}

				if ( $is_parent && method_exists( $relation, 'get_children' ) ) {
					$children = $relation->get_children( $post_id, 'ids' );
					if ( is_array( $children ) && ! empty( $children ) ) {
						return intval( $children[0] );
					}
				}

				if ( $is_child && method_exists( $relation, 'get_parents' ) ) {
					$parents = $relation->get_parents( $post_id, 'ids' );
					if ( is_array( $parents ) && ! empty( $parents ) ) {
						return intval( $parents[0] );
					}
				}
			}

			// Debug: list all available relations
			$info = [];
			foreach ( $active as $r ) {
				if ( method_exists( $r, 'get_args' ) ) {
					$a = $r->get_args();
					$info[] = sprintf( 'ID %s: %s → %s', $a['id'] ?? '?', $a['parent_object'] ?? '?', $a['child_object'] ?? '?' );
				}
			}
			error_log( sprintf(
				'[RM Inline Edit] No relation found for %s ↔ %s. Active relations: %s',
				$post_type, $target_post_type, implode( '; ', $info )
			) );

		} catch ( \Throwable $e ) {
			error_log( '[RM Inline Edit] get_related_cpt error: ' . $e->getMessage() );
		}

		return 0;
	}

	/**
	 * Save a JetEngine relation between the current product and a CPT post.
	 */
	private function save_cpt_relation( $post_id, $target_post_type, $target_id ) {
		if ( ! function_exists( 'jet_engine' ) || ! isset( jet_engine()->relations ) ) {
			return [ 'ok' => false, 'debug' => 'JetEngine not available' ];
		}

		try {
			$active    = jet_engine()->relations->get_active_relations();
			$post_type = get_post_type( $post_id );

			// JetEngine prefixes CPT names with "posts::" in relation args
			$obj_matches = function ( $obj, $type ) {
				return $obj === $type || $obj === 'posts::' . $type;
			};

			foreach ( $active as $relation ) {
				if ( ! method_exists( $relation, 'get_args' ) ) {
					continue;
				}
				$args       = $relation->get_args();
				$parent_obj = isset( $args['parent_object'] ) ? $args['parent_object'] : '';
				$child_obj  = isset( $args['child_object'] ) ? $args['child_object'] : '';

				$is_parent = ( $obj_matches( $parent_obj, $post_type ) && $obj_matches( $child_obj, $target_post_type ) );
				$is_child  = ( $obj_matches( $child_obj, $post_type ) && $obj_matches( $parent_obj, $target_post_type ) );

				if ( ! $is_parent && ! $is_child ) {
					continue;
				}

				// Remove existing relations
				if ( $is_parent && method_exists( $relation, 'get_children' ) ) {
					$existing = $relation->get_children( $post_id, 'ids' );
					if ( is_array( $existing ) ) {
						foreach ( $existing as $eid ) {
							$relation->delete_rows( $post_id, $eid );
						}
					}
					if ( $target_id > 0 ) {
						$relation->update( $post_id, $target_id );
					}
				}

				if ( $is_child && method_exists( $relation, 'get_parents' ) ) {
					$existing = $relation->get_parents( $post_id, 'ids' );
					if ( is_array( $existing ) ) {
						foreach ( $existing as $eid ) {
							$relation->delete_rows( $eid, $post_id );
						}
					}
					if ( $target_id > 0 ) {
						$relation->update( $target_id, $post_id );
					}
				}

				return [ 'ok' => true ];
			}

			// Debug: list all available relations
			$info = [];
			foreach ( $active as $r ) {
				if ( method_exists( $r, 'get_args' ) ) {
					$a = $r->get_args();
					$info[] = sprintf( 'ID %s: %s → %s', $a['id'] ?? '?', $a['parent_object'] ?? '?', $a['child_object'] ?? '?' );
				}
			}
			$debug = sprintf(
				'No relation found for %s (post_type=%s) ↔ %s. Available: %s',
				$post_id, $post_type, $target_post_type, implode( '; ', $info )
			);
			error_log( '[RM Inline Edit] save_cpt_relation: ' . $debug );

			return [ 'ok' => false, 'debug' => $debug ];

		} catch ( \Throwable $e ) {
			error_log( '[RM Inline Edit] save_cpt_relation error: ' . $e->getMessage() );
			return [ 'ok' => false, 'debug' => $e->getMessage() ];
		}
	}

	/**
	 * Get the field config array for a given context.
	 */
	private function get_field_config( $context ) {
		if ( $context === 'camp' ) {
			return $this->camp_field_config;
		}
		return $this->coach_field_config;
	}

	/**
	 * Get all field configs merged (for filters that need to check both).
	 */
	private function get_all_field_configs() {
		return array_merge( $this->coach_field_config, $this->camp_field_config );
	}

	// =========================================================================
	// COACH POST ID
	// =========================================================================

	/**
	 * Get the current user's coach post ID (with ownership validation).
	 */
	private function get_coach_post_id( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			return false;
		}

		$post_id = get_user_meta( $user_id, 'coach_post_id', true );
		if ( ! $post_id || get_post_status( $post_id ) === false ) {
			return false;
		}

		return intval( $post_id );
	}

	// =========================================================================
	// ENQUEUE ASSETS
	// =========================================================================

	/**
	 * Enqueue frontend assets only on editable pages for coaches.
	 */
	public function enqueue_assets() {
		try {
			$this->do_enqueue_assets();
		} catch ( \Throwable $e ) {
			error_log( '[RM Inline Edit] enqueue_assets error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
	}

	private function do_enqueue_assets() {
		$context = $this->detect_context();
		if ( ! $context ) {
			return;
		}

		$user_id      = get_current_user_id();
		$field_config = $this->get_field_config( $context );

		// Determine the post ID based on context
		if ( $context === 'coach' ) {
			$post_id = $this->get_coach_post_id( $user_id );
			if ( ! $post_id ) {
				return;
			}
		} else {
			// Camp context: product ID
			$post_id = get_the_ID();
			if ( ! $post_id ) {
				return;
			}
		}

		// WordPress Media Uploader
		wp_enqueue_media();

		// Flatpickr (camp context only — date fields)
		if ( $context === 'camp' ) {
			wp_enqueue_style(
				'flatpickr',
				'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
				[],
				'4.6.13'
			);
			wp_enqueue_script(
				'flatpickr',
				'https://cdn.jsdelivr.net/npm/flatpickr',
				[],
				'4.6.13',
				true
			);
		}

		// Plugin CSS
		wp_enqueue_style(
			'rm-inline-edit',
			RM_PLUGIN_URL . 'assets/css/inline-edit.css',
			[],
			RM_VERSION
		);

		// Plugin JS
		wp_enqueue_script(
			'rm-inline-edit',
			RM_PLUGIN_URL . 'assets/js/inline-edit.js',
			$context === 'camp' ? [ 'jquery', 'flatpickr' ] : [ 'jquery' ],
			RM_VERSION,
			true
		);

		// Taxonomy data
		$taxonomies_data = [];
		foreach ( $field_config as $key => $config ) {
			if ( $config['type'] !== 'taxonomy' ) {
				continue;
			}
			$tax = $config['taxonomy'];
			$all_terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
			$current_term_ids = wp_get_object_terms( $post_id, $tax, [ 'fields' => 'ids' ] );

			$taxonomies_data[ $tax ] = [
				'terms'    => array_map( function ( $t ) {
					return [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
				}, is_array( $all_terms ) ? $all_terms : [] ),
				'selected' => is_array( $current_term_ids ) ? array_map( 'intval', $current_term_ids ) : [],
			];
		}

		// Image data
		$image_data = [];
		foreach ( $field_config as $field_name => $img_config ) {
			if ( $img_config['type'] === 'featured_image' ) {
				$img_id = get_post_thumbnail_id( $post_id );
				if ( $img_id ) {
					$img_url = wp_get_attachment_image_url( intval( $img_id ), 'large' );
					$image_data[ $field_name ] = [
						'id'  => intval( $img_id ),
						'url' => $img_url ?: '',
					];
				}
			} elseif ( $img_config['type'] === 'image' ) {
				$meta_key = isset( $img_config['meta_key'] ) ? $img_config['meta_key'] : $field_name;
				$img_id = get_post_meta( $post_id, $meta_key, true );
				if ( $img_id ) {
					$img_url = wp_get_attachment_image_url( intval( $img_id ), 'large' );
					$image_data[ $field_name ] = [
						'id'  => intval( $img_id ),
						'url' => $img_url ?: '',
					];
				}
			}
		}

		// Gallery data (camp context)
		$galleries_data = [];
		foreach ( $field_config as $field_name => $gal_config ) {
			if ( $gal_config['type'] !== 'gallery' ) {
				continue;
			}
			$meta_key  = isset( $gal_config['meta_key'] ) ? $gal_config['meta_key'] : $field_name;
			$raw_ids   = get_post_meta( $post_id, $meta_key, true );
			$image_ids = $raw_ids ? array_filter( array_map( 'intval', explode( ',', $raw_ids ) ) ) : [];
			$images    = [];
			foreach ( $image_ids as $aid ) {
				$url = wp_get_attachment_image_url( $aid, 'large' );
				if ( $url ) {
					$images[] = [ 'id' => $aid, 'url' => $url ];
				}
			}
			$galleries_data[ $field_name ] = $images;
		}

		// Date range data (camp context — reads full_date timestamps)
		$dates_data = [];
		foreach ( $field_config as $field_name => $date_config ) {
			if ( $date_config['type'] !== 'date_range' ) {
				continue;
			}
			$start_ts = get_post_meta( $post_id, 'full_date', true );
			$end_ts   = get_post_meta( $post_id, 'full_date__end_date', true );
			$dates_data[ $field_name ] = [
				'start' => $start_ts ? gmdate( 'Y-m-d', intval( $start_ts ) ) : '',
				'end'   => $end_ts ? gmdate( 'Y-m-d', intval( $end_ts ) ) : '',
			];
		}

		// Repeater data
		$repeater_data = [];
		foreach ( $field_config as $field_name => $field_cfg ) {
			if ( $field_cfg['type'] !== 'repeater' ) {
				continue;
			}
			$meta_key = isset( $field_cfg['meta_key'] ) ? $field_cfg['meta_key'] : $field_name;
			$raw = get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $raw ) ) {
				$sub_field = isset( $field_cfg['sub_field'] ) ? $field_cfg['sub_field'] : 'value';
				$clean_items = [];
				foreach ( $raw as $item ) {
					if ( is_array( $item ) && isset( $item[ $sub_field ] ) ) {
						$val = $item[ $sub_field ];
						if ( is_string( $val ) && $val !== '' ) {
							$clean_items[] = $val;
						}
					} elseif ( is_string( $item ) && $item !== '' ) {
						$clean_items[] = $item;
					}
				}
				$repeater_data[ $field_name ] = $clean_items;
			} else {
				$repeater_data[ $field_name ] = [];
			}
		}

		// CPT select data (e.g. spot CPT linked via JetEngine relation)
		$cpt_options_data = [];
		foreach ( $field_config as $field_name => $cpt_cfg ) {
			if ( $cpt_cfg['type'] !== 'cpt_select' ) {
				continue;
			}
			$target_post_type = $cpt_cfg['post_type'];
			$cpt_posts = get_posts( [
				'post_type'      => $target_post_type,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
			$options = [];
			foreach ( $cpt_posts as $cp ) {
				$options[] = [ 'id' => $cp->ID, 'title' => $cp->post_title ];
			}
			$current_id = $this->get_related_cpt( $post_id, $target_post_type );
			$cpt_options_data[ $field_name ] = [
				'options'  => $options,
				'selected' => $current_id,
			];
		}

		// Onboarding (coach context only)
		$is_profile_empty = false;
		if ( $context === 'coach' ) {
			$onboarding_done = get_user_meta( $user_id, 'rm_onboarding_done', true );
			if ( ! $onboarding_done ) {
				$coach_bio = get_post_meta( $post_id, 'coach_bio', true );
				$bio_empty = empty( $coach_bio ) || trim( $coach_bio ) === '&nbsp;';
				$has_featured = has_post_thumbnail( $post_id );
				$is_profile_empty = $bio_empty && ! $has_featured;
			}
		}

		// i18n — context-aware labels
		$i18n = [
			'editProfile'    => $context === 'camp' ? 'Edit Camp' : 'Edit Profile',
			'save'           => 'Save Changes',
			'cancel'         => 'Cancel',
			'saving'         => 'Saving...',
			'saved'          => 'Saved!',
			'error'          => 'Error. Please try again.',
			'changeImage'    => 'Change',
			'selectImage'    => 'Select Image',
			'editGallery'    => 'Edit Gallery',
			'selectImages'   => 'Select Images',
			'clickToEdit'    => 'Click to edit',
			'emptyField'     => 'Click to add...',
			'done'           => 'Done',
			'addItem'        => '+ Add',
			'unsavedChanges' => 'You have unsaved changes. Save or cancel before leaving.',
			'welcomeTitle'   => 'Welcome! Complete your coach profile',
			'welcomeText'    => 'Click on each field to fill it in. Don\'t forget to save your changes.',
		];

		wp_localize_script( 'rm-inline-edit', 'rmInlineEdit', [
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'rm_inline_edit' ),
			'postId'         => $post_id,
			'context'        => $context,
			'fields'         => $field_config,
			'taxonomies'     => $taxonomies_data,
			'cptOptions'     => $cpt_options_data,
			'images'         => $image_data,
			'galleries'      => $galleries_data,
			'dates'          => $dates_data,
			'repeaters'      => $repeater_data,
			'isProfileEmpty' => $is_profile_empty,
			'i18n'           => $i18n,
		] );
	}

	// =========================================================================
	// AJAX SAVE
	// =========================================================================

	/**
	 * AJAX handler: save field changes (dual context: coach or camp).
	 */
	public function ajax_save() {
		check_ajax_referer( 'rm_inline_edit', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Not logged in.' );
		}

		// Verify coach role
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$context = sanitize_text_field( $_POST['context'] ?? 'coach' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post.' );
		}

		// Context-specific authorization
		if ( $context === 'camp' ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== 'product' ) {
				wp_send_json_error( 'Invalid product.' );
			}
			if ( ! $this->coach_owns_camp( $user_id, $post_id ) ) {
				wp_send_json_error( 'Unauthorized — not your camp.' );
			}
		} else {
			// Coach context
			$coach_post_id = $this->get_coach_post_id( $user_id );
			if ( $coach_post_id !== $post_id ) {
				wp_send_json_error( 'Invalid post.' );
			}
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== 'coach' || intval( $post->post_author ) !== $user_id ) {
				wp_send_json_error( 'Unauthorized.' );
			}
		}

		$field_config = $this->get_field_config( $context );

		// Process fields
		$fields = isset( $_POST['fields'] ) ? $_POST['fields'] : [];
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			wp_send_json_error( 'No data.' );
		}

		$updated = [];
		$errors  = [];

		foreach ( $fields as $key => $value ) {
			if ( ! isset( $field_config[ $key ] ) ) {
				continue;
			}

			$config   = $field_config[ $key ];
			$meta_key = isset( $config['meta_key'] ) ? $config['meta_key'] : $key;

			// Validate required fields
			if ( ! empty( $config['required'] ) && empty( $value ) ) {
				$errors[ $key ] = sprintf( '%s is required.', $config['label'] );
				continue;
			}

			// Post field storage (camp_title, camp_description)
			if ( ! empty( $config['storage'] ) && $config['storage'] === 'post_field' ) {
				if ( $config['type'] === 'text' ) {
					$value = sanitize_text_field( wp_unslash( $value ) );
				} elseif ( $config['type'] === 'wysiwyg' ) {
					$value = wp_kses_post( wp_unslash( $value ) );
				}
				wp_update_post( [
					'ID'                => $post_id,
					$config['post_field'] => $value,
				] );
				$updated[ $key ] = $value;
				continue;
			}

			switch ( $config['type'] ) {
				case 'text':
					$value = sanitize_text_field( wp_unslash( $value ) );
					update_post_meta( $post_id, $meta_key, $value );
					$updated[ $key ] = $value;
					break;

				case 'textarea':
					$value = sanitize_textarea_field( wp_unslash( $value ) );
					update_post_meta( $post_id, $meta_key, $value );
					$updated[ $key ] = $value;
					break;

				case 'wysiwyg':
					$value = wp_kses_post( wp_unslash( $value ) );
					update_post_meta( $post_id, $meta_key, $value );
					$updated[ $key ] = $value;
					break;

				case 'number':
					$value = intval( preg_replace( '/[^0-9.-]/', '', $value ) );
					if ( isset( $config['min'] ) ) {
						$value = max( $config['min'], $value );
					}
					if ( isset( $config['max'] ) ) {
						$value = min( $config['max'], $value );
					}
					update_post_meta( $post_id, $meta_key, $value );

					// WooCommerce price sync
					if ( ! empty( $config['woo_sync'] ) ) {
						update_post_meta( $post_id, '_price', $value );
					}

					// WooCommerce stock sync
					if ( ! empty( $config['woo_stock'] ) ) {
						update_post_meta( $post_id, '_manage_stock', 'yes' );
						update_post_meta( $post_id, '_stock_status', $value > 0 ? 'instock' : 'outofstock' );
					}

					$updated[ $key ] = $value;
					break;

				case 'url':
					$value = esc_url_raw( wp_unslash( $value ) );
					if ( $value && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
						$errors[ $key ] = 'Invalid URL.';
						continue 2;
					}
					update_post_meta( $post_id, $meta_key, $value );
					$updated[ $key ] = $value;
					break;

				case 'image':
					$value = intval( $value );
					if ( $value > 0 ) {
						$attachment = get_post( $value );
						if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
							$errors[ $key ] = 'Invalid image.';
							continue 2;
						}
					}
					update_post_meta( $post_id, $meta_key, $value );
					$img_url = $value ? wp_get_attachment_image_url( $value, 'large' ) : '';
					$updated[ $key ] = [ 'id' => $value, 'url' => $img_url ];
					break;

				case 'featured_image':
					$value = intval( $value );
					if ( $value > 0 ) {
						$attachment = get_post( $value );
						if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
							$errors[ $key ] = 'Invalid image.';
							continue 2;
						}
						set_post_thumbnail( $post_id, $value );
					} else {
						delete_post_thumbnail( $post_id );
					}
					$img_url = $value ? wp_get_attachment_image_url( $value, 'large' ) : '';
					$updated[ $key ] = [ 'id' => $value, 'url' => $img_url ];
					break;

				case 'date_range':
					if ( ! is_array( $value ) || empty( $value['start'] ) || empty( $value['end'] ) ) {
						$errors[ $key ] = 'Both start and end dates are required.';
						continue 2;
					}
					$start = sanitize_text_field( $value['start'] );
					$end   = sanitize_text_field( $value['end'] );
					if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
						$errors[ $key ] = 'Invalid date format.';
						continue 2;
					}
					$start_ts = strtotime( $start );
					$end_ts   = strtotime( $end );
					if ( ! $start_ts || ! $end_ts ) {
						$errors[ $key ] = 'Invalid dates.';
						continue 2;
					}
					// Write individual meta keys
					update_post_meta( $post_id, 'camp_start_date', $start );
					update_post_meta( $post_id, 'camp_end_date', $end );
					// Write JetEngine full_date (3 meta keys, double underscore)
					update_post_meta( $post_id, 'full_date', $start_ts );
					update_post_meta( $post_id, 'full_date__end_date', $end_ts );
					update_post_meta( $post_id, 'full_date__config', wp_json_encode( [
						'dates' => [ [ 'date' => $start, 'is_end_date' => '1', 'end_date' => $end ] ],
					] ) );
					$updated[ $key ] = [ 'start' => $start, 'end' => $end ];
					break;

				case 'gallery':
					// Parse IDs: can be comma-separated string or array
					if ( is_array( $value ) ) {
						$ids = array_filter( array_map( 'intval', $value ) );
					} else {
						$ids = array_filter( array_map( 'intval', explode( ',', $value ) ) );
					}

					// Validate each attachment
					$valid_ids = [];
					foreach ( $ids as $aid ) {
						$attachment = get_post( $aid );
						if ( $attachment && $attachment->post_type === 'attachment' ) {
							$valid_ids[] = $aid;
						}
					}

					update_post_meta( $post_id, $meta_key, implode( ',', $valid_ids ) );

					// Return images array for JS
					$images = [];
					foreach ( $valid_ids as $aid ) {
						$url = wp_get_attachment_image_url( $aid, 'large' );
						if ( $url ) {
							$images[] = [ 'id' => $aid, 'url' => $url ];
						}
					}
					$updated[ $key ] = $images;
					break;

				case 'repeater':
					$sub_field = isset( $config['sub_field'] ) ? $config['sub_field'] : 'value';
					$items = is_array( $value ) ? $value : [];
					$repeater_data = [];
					$flat_values   = [];
					foreach ( $items as $item_value ) {
						if ( is_array( $item_value ) ) {
							$clean = isset( $item_value[ $sub_field ] )
								? sanitize_text_field( wp_unslash( $item_value[ $sub_field ] ) )
								: '';
						} else {
							$clean = sanitize_text_field( wp_unslash( (string) $item_value ) );
						}
						if ( $clean !== '' ) {
							// Nested format for JetEngine: [ ['sub_field' => 'val'], ... ]
							$repeater_data[] = [ $sub_field => $clean ];
							$flat_values[]   = $clean;
						}
					}
					delete_post_meta( $post_id, $meta_key );
					if ( ! empty( $repeater_data ) ) {
						update_post_meta( $post_id, $meta_key, $repeater_data );
					}
					$updated[ $key ] = $flat_values;
					break;

				case 'taxonomy':
					$taxonomy = $config['taxonomy'];
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue 2;
					}
					$term_ids = array_map( 'intval', (array) $value );
					$term_ids = array_filter( $term_ids );
					wp_set_object_terms( $post_id, $term_ids, $taxonomy );
					$terms = get_terms( [
						'taxonomy'   => $taxonomy,
						'include'    => $term_ids,
						'hide_empty' => false,
					] );
					$updated[ $key ] = array_map( function ( $t ) {
						return [ 'id' => $t->term_id, 'name' => $t->name ];
					}, is_array( $terms ) ? $terms : [] );
					break;

				case 'cpt_select':
					$target_id        = intval( $value );
					$target_post_type = isset( $config['post_type'] ) ? $config['post_type'] : '';
					if ( $target_id > 0 && $target_post_type ) {
						$target_post = get_post( $target_id );
						if ( ! $target_post || $target_post->post_type !== $target_post_type ) {
							$errors[ $key ] = 'Invalid selection.';
							continue 2;
						}
					}
					$result = $this->save_cpt_relation( $post_id, $target_post_type, $target_id );
					if ( is_array( $result ) && ! empty( $result['ok'] ) ) {
						$updated[ $key ] = $target_id > 0 ? [
							'id'    => $target_id,
							'title' => get_the_title( $target_id ),
						] : null;
					} else {
						$debug = is_array( $result ) && isset( $result['debug'] ) ? $result['debug'] : 'Unknown';
						$errors[ $key ] = 'Could not save relation. Debug: ' . $debug;
					}
					break;
			}
		}

		// Camp post-save: clear WooCommerce caches
		if ( $context === 'camp' && ! empty( $updated ) ) {
			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $post_id );
			}
		}

		if ( ! empty( $errors ) && empty( $updated ) ) {
			wp_send_json_error( [ 'errors' => $errors ] );
		}

		// Mark onboarding as done (coach context)
		if ( $context === 'coach' ) {
			update_user_meta( $user_id, 'rm_onboarding_done', '1' );
		}

		clean_post_cache( $post_id );

		wp_send_json_success( [
			'updated' => $updated,
			'errors'  => $errors,
		] );
	}

	// =========================================================================
	// META FILTERS
	// =========================================================================

	/**
	 * Convert flat repeater data to nested format for JetEngine on public pages.
	 * Iterates both coach and camp field configs.
	 */
	public function filter_repeater_meta_for_public( $value, $object_id, $meta_key, $single ) {
		static $is_filtering = false;
		if ( $is_filtering ) {
			return $value;
		}

		// Check if this meta key belongs to a repeater field in either config
		$field_cfg = null;
		foreach ( $this->get_all_field_configs() as $fn => $cfg ) {
			if ( $cfg['type'] !== 'repeater' ) {
				continue;
			}
			$mk = isset( $cfg['meta_key'] ) ? $cfg['meta_key'] : $fn;
			if ( $mk === $meta_key ) {
				$field_cfg = $cfg;
				break;
			}
		}
		if ( ! $field_cfg ) {
			return $value;
		}

		$is_filtering = true;
		$actual = get_post_meta( $object_id, $meta_key, true );
		$is_filtering = false;

		if ( ! is_array( $actual ) || empty( $actual ) ) {
			return $value;
		}

		$sub_field = isset( $field_cfg['sub_field'] ) ? $field_cfg['sub_field'] : 'value';

		// On dashboard: pass through raw data — JetFormBuilder repeater reads the array natively
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/coach-dashboard/' ) !== false ) {
			return $value;
		}

		// On public pages: convert flat array to nested format for JetEngine
		$first = reset( $actual );
		if ( is_array( $first ) ) {
			return $value;
		}

		$nested = array_map( function ( $v ) use ( $sub_field ) {
			return [ $sub_field => $v ];
		}, $actual );

		return $single ? [ $nested ] : [ [ $nested ] ];
	}

	/**
	 * On editable pages, return &nbsp; for empty WYSIWYG meta fields so
	 * Elementor always renders the text-editor widget.
	 * Iterates both coach and camp field configs.
	 */
	public function filter_wysiwyg_meta_for_dashboard( $value, $object_id, $meta_key, $single ) {
		static $is_filtering = false;
		if ( $is_filtering ) {
			return $value;
		}

		// Use is_editable_page() — NOT detect_context() — to avoid recursion
		if ( ! $this->is_editable_page() || ! is_user_logged_in() ) {
			return $value;
		}

		// Check both configs for WYSIWYG fields (skip post_field storage — those use post_content, not meta)
		$matched = false;
		foreach ( $this->get_all_field_configs() as $fn => $cfg ) {
			if ( $cfg['type'] !== 'wysiwyg' ) {
				continue;
			}
			// post_field storage fields don't use postmeta
			if ( ! empty( $cfg['storage'] ) && $cfg['storage'] === 'post_field' ) {
				continue;
			}
			$mk = isset( $cfg['meta_key'] ) ? $cfg['meta_key'] : $fn;
			if ( $mk === $meta_key ) {
				$matched = true;
				break;
			}
		}
		if ( ! $matched ) {
			return $value;
		}

		$is_filtering = true;
		$actual = get_post_meta( $object_id, $meta_key, true );
		$is_filtering = false;

		if ( empty( $actual ) ) {
			return [ '&nbsp;' ];
		}

		return $value;
	}

	/**
	 * Send no-cache headers on editable pages.
	 */
	public function nocache_profile_page() {
		// Use is_editable_page() to avoid recursion from meta filters
		if ( $this->is_editable_page() && is_user_logged_in() ) {
			nocache_headers();
		}
	}

	// =========================================================================
	// REPAIR TOOL
	// =========================================================================

	/**
	 * AJAX repair handler: diagnose and fix corrupted meta data.
	 */
	public function ajax_repair() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Not logged in.' );
		}

		$coach_post_id = $this->get_coach_post_id( $user_id );
		if ( ! $coach_post_id ) {
			wp_send_json_error( 'No coach post found.' );
		}

		$report = [
			'post_id' => $coach_post_id,
			'meta'    => [],
		];

		foreach ( $this->coach_field_config as $field_name => $field_cfg ) {
			if ( $field_cfg['type'] !== 'repeater' ) {
				continue;
			}
			$meta_key = isset( $field_cfg['meta_key'] ) ? $field_cfg['meta_key'] : $field_name;
			$raw = get_post_meta( $coach_post_id, $meta_key, true );

			$report['meta'][ $meta_key ] = [
				'raw_type'  => gettype( $raw ),
				'raw_value' => $raw,
			];

			if ( isset( $_REQUEST['fix'] ) && $_REQUEST['fix'] === '1' ) {
				delete_post_meta( $coach_post_id, $meta_key );
				$report['meta'][ $meta_key ]['action'] = 'DELETED';
			}
		}

		foreach ( $this->coach_field_config as $field_name => $field_cfg ) {
			if ( $field_cfg['type'] === 'repeater' || $field_cfg['type'] === 'taxonomy'
				|| $field_cfg['type'] === 'featured_image' ) {
				continue;
			}
			$meta_key = isset( $field_cfg['meta_key'] ) ? $field_cfg['meta_key'] : $field_name;
			$raw = get_post_meta( $coach_post_id, $meta_key, true );
			$report['meta'][ $meta_key ] = [
				'raw_type'  => gettype( $raw ),
				'raw_value' => ( is_string( $raw ) && strlen( $raw ) > 200 ) ? substr( $raw, 0, 200 ) . '...' : $raw,
			];
		}

		wp_send_json_success( $report );
	}
}

// Instantiated by ridemaster.php bootstrap
