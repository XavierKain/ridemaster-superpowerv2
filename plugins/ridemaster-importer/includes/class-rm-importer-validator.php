<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Importer_Validator {

    /**
     * Valid term slugs per taxonomy. Source of truth = live site audit 2026-05-27.
     * Used to reject unknown slugs BEFORE attempting to write to the DB.
     */
    const ALLOWED_TERMS = [
        'sport'         => [ 'kitesurf', 'parakite', 'wingfoil' ],
        'level'         => [ 'beginner', 'intermediate', 'advanced', 'expert' ],
        'language'      => [ 'english', 'french', 'german', 'italian', 'portuguese', 'spanish' ],
        'water-type'    => [ 'flat-water', 'waves', 'choppy', 'mixed' ],
        'camp-status'   => [ 'open', 'full', 'cancelled' ],
        'coach-status'  => [ 'pending', 'validated', 'suspended' ],
    ];

    /**
     * Validate an import-camp payload.
     *
     * @param array $payload Raw decoded JSON body.
     * @return true|WP_Error true if valid, WP_Error with detailed message otherwise.
     */
    public static function validate( array $payload ) {
        if ( empty( $payload['import_source_url'] ) || ! filter_var( $payload['import_source_url'], FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'import_source_url is required and must be a valid URL', [ 'status' => 400 ] );
        }

        if ( empty( $payload['camp'] ) || ! is_array( $payload['camp'] ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'camp object is required', [ 'status' => 400 ] );
        }

        $camp = $payload['camp'];

        // Required camp fields.
        foreach ( [ 'title', 'price_eur', 'max_spots', 'start_date', 'end_date' ] as $required ) {
            if ( ! isset( $camp[ $required ] ) || $camp[ $required ] === '' ) {
                return new WP_Error( 'INVALID_PAYLOAD', "camp.$required is required", [ 'status' => 400 ] );
            }
        }

        // Numeric sanity — price must be non-negative number, max_spots a positive int.
        if ( ! is_numeric( $camp['price_eur'] ) || $camp['price_eur'] < 0 ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'camp.price_eur must be a non-negative number', [ 'status' => 400 ] );
        }
        if ( ! is_numeric( $camp['max_spots'] ) || (int) $camp['max_spots'] < 1 ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'camp.max_spots must be a positive integer', [ 'status' => 400 ] );
        }

        // Date format + calendar validity (catches e.g. 2026-13-99 which would pass the regex).
        foreach ( [ 'start_date', 'end_date' ] as $df ) {
            if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $camp[ $df ], $m ) ) {
                return new WP_Error( 'INVALID_PAYLOAD', "camp.$df must be YYYY-MM-DD", [ 'status' => 400 ] );
            }
            if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
                return new WP_Error( 'INVALID_PAYLOAD', "camp.$df is not a valid calendar date", [ 'status' => 400 ] );
            }
        }

        // end_date must be on or after start_date.
        if ( strtotime( $camp['end_date'] ) < strtotime( $camp['start_date'] ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'camp.end_date must be on or after camp.start_date', [ 'status' => 400 ] );
        }

        // Slug enumerations.
        $checks = [
            [ 'sport',       isset( $camp['sport'] ) ? [ $camp['sport'] ] : [] ],
            [ 'level',       (array) ( $camp['level'] ?? [] ) ],
            [ 'language',    (array) ( $camp['languages'] ?? [] ) ],
            [ 'camp-status', isset( $camp['camp_status'] ) ? [ $camp['camp_status'] ] : [] ],
        ];
        foreach ( $checks as [ $tax, $slugs ] ) {
            foreach ( $slugs as $slug ) {
                if ( ! in_array( $slug, self::ALLOWED_TERMS[ $tax ], true ) ) {
                    return new WP_Error(
                        'INVALID_PAYLOAD',
                        "Unknown $tax slug '$slug'. Allowed: " . implode( ', ', self::ALLOWED_TERMS[ $tax ] ),
                        [ 'status' => 400 ]
                    );
                }
            }
        }

        return true;
    }
}
