<?php
/**
 * Plugin Name: RideMaster Schema
 * Description: Adds enriched Schema.org JSON-LD on single pages for spots (TouristAttraction), hotels (LodgingBusiness), coaches (Person) and camps (Product with offers). Complements Yoast SEO and WooCommerce default schemas.
 * Version: 1.0.0
 * Author: RideMaster
 * Text Domain: ridemaster-schema
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RM_SCHEMA_VERSION', '1.0.0' );

add_action( 'wp_head', 'rm_schema_output', 99 );

function rm_schema_output() {
    if ( ! is_singular() ) {
        return;
    }
    $post = get_queried_object();
    if ( ! $post || ! isset( $post->ID ) ) {
        return;
    }

    $schema = null;

    if ( $post->post_type === 'spot' ) {
        $schema = rm_schema_spot( $post );
    } elseif ( $post->post_type === 'hotel' ) {
        $schema = rm_schema_hotel( $post );
    } elseif ( $post->post_type === 'coach' ) {
        $schema = rm_schema_coach( $post );
    } elseif ( $post->post_type === 'product' && has_term( 'camp', 'product_cat', $post->ID ) ) {
        $schema = rm_schema_camp( $post );
    }

    if ( $schema ) {
        echo "\n<script type=\"application/ld+json\">\n"
            . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . "\n</script>\n";
    }
}

/**
 * Strip empty / null fields recursively before output.
 */
function rm_schema_clean( $data ) {
    if ( is_array( $data ) ) {
        $out = [];
        foreach ( $data as $k => $v ) {
            $v = rm_schema_clean( $v );
            if ( $v !== null && $v !== '' && $v !== [] ) {
                $out[ $k ] = $v;
            }
        }
        return $out;
    }
    return $data;
}

function rm_schema_image_url( $post_id ) {
    $thumb = get_post_thumbnail_id( $post_id );
    return $thumb ? wp_get_attachment_url( $thumb ) : '';
}

function rm_schema_spot( $post ) {
    $country = get_post_meta( $post->ID, 'spot_country', true );
    $region  = get_post_meta( $post->ID, 'spot_region', true );
    $loc     = get_post_meta( $post->ID, 'spot_location', true ); // "lat,lng"
    $lat = $lng = null;
    if ( $loc && strpos( $loc, ',' ) !== false ) {
        list( $lat, $lng ) = array_map( 'trim', explode( ',', $loc, 2 ) );
    }

    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'TouristAttraction',
        '@id'         => get_permalink( $post->ID ) . '#spot',
        'name'        => $post->post_title,
        'description' => wp_strip_all_tags( $post->post_content ),
        'image'       => rm_schema_image_url( $post->ID ),
        'url'         => get_permalink( $post->ID ),
        'address'     => [
            '@type'           => 'PostalAddress',
            'addressCountry'  => $country,
            'addressRegion'   => $region,
            'addressLocality' => $post->post_title,
        ],
        'touristType' => 'Kitesurf, Wingfoil and Parakite enthusiasts',
    ];

    if ( $lat && $lng && is_numeric( $lat ) && is_numeric( $lng ) ) {
        $data['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => round((float) $lat, 6),
            'longitude' => round((float) $lng, 6),
        ];
    }

    return rm_schema_clean( $data );
}

function rm_schema_hotel( $post ) {
    $country = get_post_meta( $post->ID, 'hotel_country', true );
    $address = get_post_meta( $post->ID, 'hotel_address', true );
    $desc    = get_post_meta( $post->ID, '_description', true );

    return rm_schema_clean( [
        '@context'    => 'https://schema.org',
        '@type'       => 'LodgingBusiness',
        '@id'         => get_permalink( $post->ID ) . '#hotel',
        'name'        => $post->post_title,
        'description' => $desc ? wp_strip_all_tags( $desc ) : '',
        'image'       => rm_schema_image_url( $post->ID ),
        'url'         => get_permalink( $post->ID ),
        'address'     => [
            '@type'          => 'PostalAddress',
            'addressCountry' => $country,
            'streetAddress'  => $address,
        ],
    ] );
}

function rm_schema_coach( $post ) {
    $bio       = get_post_meta( $post->ID, 'coach_bio', true );
    $location  = get_post_meta( $post->ID, 'coach_location', true );
    $instagram = get_post_meta( $post->ID, 'instagram', true );
    $youtube   = get_post_meta( $post->ID, 'youtube', true );
    $website   = get_post_meta( $post->ID, 'website', true );

    $same_as = [];
    if ( $instagram ) {
        $same_as[] = preg_match( '|^https?://|', $instagram )
            ? $instagram
            : 'https://instagram.com/' . ltrim( $instagram, '@' );
    }
    if ( $youtube ) {
        $same_as[] = $youtube;
    }
    if ( $website ) {
        $same_as[] = $website;
    }

    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Person',
        '@id'         => get_permalink( $post->ID ) . '#person',
        'name'        => $post->post_title,
        'description' => $bio ? wp_strip_all_tags( $bio ) : '',
        'image'       => rm_schema_image_url( $post->ID ),
        'url'         => get_permalink( $post->ID ),
        'jobTitle'    => 'Kitesurf / Wingfoil / Parakite Coach',
        'sameAs'      => $same_as,
    ];

    if ( $location ) {
        $data['homeLocation'] = [
            '@type' => 'Place',
            'name'  => $location,
        ];
    }

    return rm_schema_clean( $data );
}

function rm_schema_camp( $post ) {
    $price      = get_post_meta( $post->ID, '_price', true );
    $stock      = (int) get_post_meta( $post->ID, '_stock', true );
    $start_date = get_post_meta( $post->ID, 'camp_start_date', true );
    $end_date   = get_post_meta( $post->ID, 'camp_end_date', true );
    $max_spots  = (int) get_post_meta( $post->ID, '_stock', true );

    // Find coach + spot via JetEngine relations
    global $wpdb;
    $table = $wpdb->prefix . 'jet_rel_default';
    $coach_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d LIMIT 1",
        20, $post->ID
    ) );
    $spot_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d LIMIT 1",
        18, $post->ID
    ) );

    $coach_name   = $coach_id ? get_the_title( $coach_id ) : '';
    $spot_name    = $spot_id ? get_the_title( $spot_id ) : '';
    $spot_country = $spot_id ? get_post_meta( $spot_id, 'spot_country', true ) : '';

    // Availability
    $availability = ( $stock > 0 ) ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut';

    $offer = [
        '@type'         => 'Offer',
        'url'           => get_permalink( $post->ID ),
        'priceCurrency' => 'EUR',
        'price'         => $price ? (float) $price : 0,
        'availability'  => $availability,
        'seller'        => [ '@type' => 'Organization', 'name' => $coach_name ?: 'Ridemaster' ],
    ];
    if ( $start_date ) {
        $offer['validFrom']        = $start_date;
        $offer['priceValidUntil']  = $start_date;
    }

    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        '@id'         => get_permalink( $post->ID ) . '#camp',
        'name'        => $post->post_title,
        'description' => wp_strip_all_tags( wp_trim_words( $post->post_content, 50 ) ),
        'image'       => rm_schema_image_url( $post->ID ),
        'url'         => get_permalink( $post->ID ),
        'category'    => 'Kiteboarding camp',
        'brand'       => [ '@type' => 'Brand', 'name' => 'Ridemaster' ],
        'sku'         => 'camp-' . $post->ID,
        'offers'      => $offer,
    ];

    // Add additionalProperty entries for coach, spot, dates, capacity.
    $props = [];
    if ( $coach_name ) {
        $props[] = [ '@type' => 'PropertyValue', 'name' => 'Coach', 'value' => $coach_name ];
    }
    if ( $spot_name ) {
        $props[] = [
            '@type' => 'PropertyValue',
            'name'  => 'Spot',
            'value' => $spot_country ? $spot_name . ', ' . $spot_country : $spot_name,
        ];
    }
    if ( $start_date && $end_date ) {
        $props[] = [ '@type' => 'PropertyValue', 'name' => 'Start date', 'value' => $start_date ];
        $props[] = [ '@type' => 'PropertyValue', 'name' => 'End date',   'value' => $end_date ];
    }
    if ( $max_spots > 0 ) {
        $props[] = [ '@type' => 'PropertyValue', 'name' => 'Max participants', 'value' => $max_spots ];
    }
    if ( $props ) {
        $data['additionalProperty'] = $props;
    }

    return rm_schema_clean( $data );
}
