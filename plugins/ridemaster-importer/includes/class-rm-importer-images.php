<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

class RM_Importer_Images {

    /**
     * Import one image entry (URL or base64) and return the attachment ID.
     *
     * @param array $item   { url?, base64?, filename, alt, title?, role }
     * @param int   $parent_post_id Post to attach the image to.
     * @return array { 'attachment_id' => int|null, 'error' => string|null }
     */
    public static function import( array $item, int $parent_post_id ) {
        $alt      = $item['alt']      ?? '';
        $title    = $item['title']    ?? '';
        $filename = $item['filename'] ?? '';

        // Path A: URL — server-side download via sideload.
        if ( ! empty( $item['url'] ) ) {
            $url = $item['url'];

            $ssrf_err = self::check_ssrf( $url );
            if ( $ssrf_err ) {
                return [ 'attachment_id' => null, 'error' => $ssrf_err ];
            }

            // media_sideload_image with 'id' returns just the attachment ID
            $attachment_id = media_sideload_image( $url, $parent_post_id, $title, 'id' );
            if ( is_wp_error( $attachment_id ) ) {
                return [ 'attachment_id' => null, 'error' => 'sideload_failed: ' . $attachment_id->get_error_message() ];
            }

            // Rename file on disk to SEO-friendly filename if provided.
            if ( $filename ) {
                self::rename_attachment_file( $attachment_id, $filename );
            }

            self::apply_seo_meta( $attachment_id, $alt, $title );
            return [ 'attachment_id' => $attachment_id, 'error' => null ];
        }

        // Path B: base64 fallback.
        if ( ! empty( $item['base64'] ) ) {
            $bytes = base64_decode( $item['base64'], true );
            if ( $bytes === false ) {
                return [ 'attachment_id' => null, 'error' => 'invalid_base64' ];
            }
            $name = $filename ?: ( 'import-' . wp_generate_uuid4() . '.jpg' );

            $upload = wp_upload_bits( $name, null, $bytes );
            if ( ! empty( $upload['error'] ) ) {
                return [ 'attachment_id' => null, 'error' => 'upload_bits_failed: ' . $upload['error'] ];
            }

            // Detect MIME from file contents (works for extensionless filenames too).
            // wp_get_image_mime exists since WP 5.8 and inspects the actual bytes.
            $mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $upload['file'] ) : false;
            if ( ! $mime ) {
                // Fallback to extension-based detection.
                $filetype = wp_check_filetype( basename( $upload['file'] ), null );
                $mime     = $filetype['type'] ?: 'application/octet-stream';
            }

            $attachment_id = wp_insert_attachment( [
                'post_mime_type' => $mime,
                'post_title'     => $title ?: pathinfo( $name, PATHINFO_FILENAME ),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ], $upload['file'], $parent_post_id );

            if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
                return [ 'attachment_id' => null, 'error' => 'wp_insert_attachment_failed' ];
            }

            $meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
            wp_update_attachment_metadata( $attachment_id, $meta );

            self::apply_seo_meta( $attachment_id, $alt, $title );
            return [ 'attachment_id' => $attachment_id, 'error' => null ];
        }

        return [ 'attachment_id' => null, 'error' => 'no_url_or_base64' ];
    }

    /**
     * Validate that a URL is safe to sideload from (no SSRF).
     *
     * Threat model: this endpoint requires `edit_others_posts` capability, so
     * only authenticated admins/editors can reach it. We still defend against:
     *   - private/loopback/reserved IP exfiltration (file://, http://192.168.x, http://127.0.0.1)
     *   - non-http(s) schemes
     *   - unresolvable hosts (fail closed — see below)
     *
     * KNOWN LIMITATIONS (acceptable given the auth-required surface):
     *   - DNS rebinding (TOCTOU between this check and download_url's resolve):
     *     would require an attacker-controlled DNS record with a short TTL and
     *     an admin willing to submit it. Mitigation requires a pre_http_request
     *     filter or an allowlist; out of scope for this iteration.
     *   - IPv6 literals (e.g. http://[::1]/) bypass gethostbyname which is A-record
     *     only. Reject explicitly below.
     *
     * @return string|null Error message if unsafe, null if OK.
     */
    private static function check_ssrf( string $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
            return 'scheme_not_allowed';
        }
        $host = $parsed['host'] ?? '';
        if ( empty( $host ) ) {
            return 'host_missing';
        }

        // Block IPv6 literal hosts (gethostbyname can't validate them).
        if ( strpos( $host, ':' ) !== false || ( $host[0] === '[' ) ) {
            return 'ipv6_literal_blocked';
        }

        // If the host is itself an IPv4 literal, validate directly.
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return 'private_or_reserved_ip';
            }
            return null;
        }

        // Resolve hostname to IP and reject private/local ranges.
        $ip = gethostbyname( $host );
        if ( $ip === $host ) {
            // Fail closed: don't let unresolvable hosts pass through to download_url.
            return 'unresolvable_host';
        }
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return 'private_or_reserved_ip';
        }
        return null;
    }

    /**
     * Rename the attachment's file on disk to a SEO-friendly name.
     *
     * Updates the attached file path + post slug; deletes orphaned intermediate
     * size files (e.g. `-150x150.jpg`) keyed off the OLD basename, then
     * regenerates fresh intermediate sizes from the new path.
     */
    private static function rename_attachment_file( int $attachment_id, string $desired_filename ): void {
        $original_path = get_attached_file( $attachment_id );
        if ( ! $original_path || ! file_exists( $original_path ) ) {
            return;
        }

        $info     = pathinfo( $original_path );
        $ext      = $info['extension'] ?? 'jpg';
        $dir      = $info['dirname'];
        $new_base = sanitize_file_name( $desired_filename );
        // Strip any extension the caller may have included
        $new_base = preg_replace( '/\.(jpg|jpeg|png|gif|webp)$/i', '', $new_base );

        $new_path = $dir . '/' . $new_base . '.' . $ext;
        $i = 2;
        while ( file_exists( $new_path ) ) {
            $new_path = $dir . '/' . $new_base . '-' . $i . '.' . $ext;
            $i++;
        }

        // Capture OLD intermediate size filenames before we rename the original,
        // so we can delete them and avoid orphan files on disk.
        $old_meta = wp_get_attachment_metadata( $attachment_id );
        $old_size_files = [];
        if ( ! empty( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
            foreach ( $old_meta['sizes'] as $size ) {
                if ( ! empty( $size['file'] ) ) {
                    $old_size_files[] = $dir . '/' . $size['file'];
                }
            }
        }

        if ( rename( $original_path, $new_path ) ) {
            // Delete orphaned intermediate sizes (they'd otherwise sit unused on disk).
            foreach ( $old_size_files as $orphan ) {
                if ( file_exists( $orphan ) ) {
                    @unlink( $orphan );
                }
            }

            update_attached_file( $attachment_id, $new_path );
            wp_update_post( [
                'ID'         => $attachment_id,
                'post_name'  => sanitize_title( $new_base ),
                'guid'       => str_replace( basename( $original_path ), basename( $new_path ), wp_get_attachment_url( $attachment_id ) ),
            ] );
            // Regenerate intermediate sizes from new path
            $meta = wp_generate_attachment_metadata( $attachment_id, $new_path );
            wp_update_attachment_metadata( $attachment_id, $meta );
        }
    }

    /** Apply alt text + title to the attachment for SEO + accessibility. */
    private static function apply_seo_meta( int $attachment_id, string $alt, string $title ): void {
        if ( $alt !== '' ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        }
        if ( $title !== '' ) {
            wp_update_post( [
                'ID'         => $attachment_id,
                'post_title' => sanitize_text_field( $title ),
            ] );
        }
    }
}
