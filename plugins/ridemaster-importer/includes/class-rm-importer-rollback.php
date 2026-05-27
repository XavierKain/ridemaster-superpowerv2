<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tracks entities created during a single import call so we can clean up
 * on partial failure. Pre-existing entities are NEVER touched — only ones
 * created in this invocation.
 */
class RM_Importer_Rollback {

    private $user_ids       = [];
    private $coach_post_ids = [];
    private $spot_ids       = [];
    private $hotel_ids      = [];
    private $camp_id        = null;
    private $attachment_ids = [];

    public function track_user( int $id ): void          { $this->user_ids[]       = $id; }
    public function track_coach_post( int $id ): void    { $this->coach_post_ids[] = $id; }
    public function track_spot( int $id ): void          { $this->spot_ids[]       = $id; }
    public function track_hotel( int $id ): void         { $this->hotel_ids[]      = $id; }
    public function track_camp( int $id ): void          { $this->camp_id          = $id; }
    public function track_attachment( int $id ): void    { $this->attachment_ids[] = $id; }

    /**
     * Delete everything tracked. Returns a summary array.
     */
    public function rollback(): array {
        // CRITICAL: load user.php BEFORE any post deletion. The main plugin's
        // class-cleanup.php hooks into wp_delete_post for coach CPTs and calls
        // wp_delete_user as a cascade — if user.php isn't loaded yet, the
        // cascade fatal-errors halfway through our rollback.
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $deleted = [
            'attachments' => 0,
            'camp'        => false,
            'hotels'      => 0,
            'spots'       => 0,
            'coaches'     => 0,
            'users'       => 0,
        ];

        foreach ( $this->attachment_ids as $id ) {
            if ( wp_delete_attachment( $id, true ) ) {
                $deleted['attachments']++;
            }
        }

        if ( $this->camp_id ) {
            if ( wp_delete_post( $this->camp_id, true ) ) {
                $deleted['camp'] = true;
            }
        }

        foreach ( $this->hotel_ids as $id ) {
            if ( wp_delete_post( $id, true ) ) $deleted['hotels']++;
        }

        foreach ( $this->spot_ids as $id ) {
            if ( wp_delete_post( $id, true ) ) $deleted['spots']++;
        }

        foreach ( $this->coach_post_ids as $id ) {
            if ( wp_delete_post( $id, true ) ) $deleted['coaches']++;
        }

        foreach ( $this->user_ids as $id ) {
            if ( wp_delete_user( $id ) ) $deleted['users']++;
        }

        return $deleted;
    }
}
