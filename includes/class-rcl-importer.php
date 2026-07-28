<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RCL_Importer {
    const MAX_FILE_SIZE = 5242880; // 5 MB.

    public static function prepare_upload( $file ) {
        $data = self::read_uploaded_file( $file );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $normalized = self::normalize_pack( $data );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }

        $preview = self::build_preview( $normalized );
        if ( is_wp_error( $preview ) ) {
            return $preview;
        }

        $token = wp_generate_uuid4();
        $key   = self::transient_key( get_current_user_id(), $token );
        set_transient( $key, $normalized, 30 * MINUTE_IN_SECONDS );

        $preview['token'] = $token;
        return $preview;
    }

    public static function import_from_token( $token, $conflict_policy = 'update', $import_branding = false, $create_page = false ) {
        $token = sanitize_text_field( $token );
        $key   = self::transient_key( get_current_user_id(), $token );
        $data  = get_transient( $key );

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'rcl_preview_expired', __( 'The import preview expired. Upload the library pack again.', 'reference-code-library' ) );
        }

        $result = self::commit( $data, $conflict_policy, $import_branding, $create_page );
        if ( ! is_wp_error( $result ) ) {
            delete_transient( $key );
        }
        return $result;
    }

    private static function transient_key( $user_id, $token ) {
        return 'rcl_import_' . absint( $user_id ) . '_' . substr( preg_replace( '/[^a-zA-Z0-9-]/', '', $token ), 0, 40 );
    }

    private static function read_uploaded_file( $file ) {
        if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
            return new WP_Error( 'rcl_no_file', __( 'Choose a JSON or TXT library pack to upload.', 'reference-code-library' ) );
        }

        $error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ( UPLOAD_ERR_OK !== $error ) {
            return new WP_Error( 'rcl_upload_error', self::upload_error_message( $error ) );
        }

        $size = isset( $file['size'] ) ? (int) $file['size'] : 0;
        if ( $size <= 0 || $size > self::MAX_FILE_SIZE ) {
            return new WP_Error( 'rcl_file_size', __( 'The library pack must be larger than 0 bytes and no larger than 5 MB.', 'reference-code-library' ) );
        }

        $name      = sanitize_file_name( $file['name'] ?? '' );
        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, array( 'json', 'txt' ), true ) ) {
            return new WP_Error( 'rcl_file_type', __( 'Only .json and .txt files containing valid JSON are accepted.', 'reference-code-library' ) );
        }

        if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'rcl_invalid_upload', __( 'The file was not received as a valid HTTP upload.', 'reference-code-library' ) );
        }

        if ( ! is_readable( $file['tmp_name'] ) ) {
            return new WP_Error( 'rcl_unreadable_file', __( 'WordPress could not read the uploaded library pack.', 'reference-code-library' ) );
        }

        $raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $raw || '' === trim( $raw ) ) {
            return new WP_Error( 'rcl_empty_file', __( 'The uploaded library pack is empty.', 'reference-code-library' ) );
        }

        $data = json_decode( $raw, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
            return new WP_Error(
                'rcl_invalid_json',
                sprintf(
                    /* translators: %s is the JSON parser error. */
                    __( 'The uploaded file is not valid JSON: %s', 'reference-code-library' ),
                    json_last_error_msg()
                )
            );
        }

        return $data;
    }

    private static function upload_error_message( $error ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the server upload limit.', 'reference-code-library' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the form upload limit.', 'reference-code-library' ),
            UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded.', 'reference-code-library' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'reference-code-library' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'The server is missing a temporary upload directory.', 'reference-code-library' ),
            UPLOAD_ERR_CANT_WRITE => __( 'The server could not write the uploaded file.', 'reference-code-library' ),
            UPLOAD_ERR_EXTENSION  => __( 'A server extension stopped the upload.', 'reference-code-library' ),
        );
        return $messages[ $error ] ?? __( 'The file upload failed.', 'reference-code-library' );
    }

    public static function normalize_pack( $data ) {
        if ( isset( $data['patterns'] ) && ! isset( $data['entries'] ) ) {
            $data = self::convert_legacy_pack( $data );
        }

        $schema = sanitize_text_field( $data['schema'] ?? '' );
        if ( 'reference-code-library/v2' !== $schema ) {
            return new WP_Error( 'rcl_schema', __( 'The file does not use the supported reference-code-library/v2 schema.', 'reference-code-library' ) );
        }

        $pack = isset( $data['pack'] ) && is_array( $data['pack'] ) ? $data['pack'] : array();
        $name = sanitize_text_field( $pack['name'] ?? '' );
        if ( ! $name ) {
            return new WP_Error( 'rcl_pack_name', __( 'The library pack is missing pack.name.', 'reference-code-library' ) );
        }

        $normalized = array(
            'schema'      => 'reference-code-library/v2',
            'pack'        => array(
                'id'          => sanitize_title( $pack['id'] ?? $name ),
                'name'        => $name,
                'version'     => sanitize_text_field( $pack['version'] ?? '1.0.0' ),
                'description' => sanitize_textarea_field( $pack['description'] ?? '' ),
            ),
            'branding'    => self::normalize_branding( $data['branding'] ?? array() ),
            'collections' => array(),
            'entries'     => array(),
        );

        $collections = isset( $data['collections'] ) && is_array( $data['collections'] ) ? $data['collections'] : array();
        foreach ( $collections as $index => $collection ) {
            if ( ! is_array( $collection ) ) {
                return new WP_Error( 'rcl_collection_type', sprintf( __( 'Collection %d is not an object.', 'reference-code-library' ), $index + 1 ) );
            }

            $name = sanitize_text_field( $collection['name'] ?? '' );
            $id   = sanitize_title( $collection['id'] ?? ( $collection['slug'] ?? $name ) );
            if ( ! $id || ! $name ) {
                return new WP_Error( 'rcl_collection_required', sprintf( __( 'Collection %d must include an id and name.', 'reference-code-library' ), $index + 1 ) );
            }

            $normalized['collections'][ $id ] = array(
                'id'          => $id,
                'name'        => $name,
                'description' => sanitize_textarea_field( $collection['description'] ?? '' ),
                'icon'        => sanitize_text_field( $collection['icon'] ?? '' ),
                'class'       => sanitize_html_class( $collection['class'] ?? $id ),
                'order'       => (int) ( $collection['order'] ?? ( ( $index + 1 ) * 10 ) ),
            );
        }

        $entries = isset( $data['entries'] ) && is_array( $data['entries'] ) ? $data['entries'] : array();
        if ( ! $entries ) {
            return new WP_Error( 'rcl_no_entries', __( 'The library pack does not contain any entries.', 'reference-code-library' ) );
        }

        $seen_ids = array();
        foreach ( $entries as $index => $entry ) {
            if ( ! is_array( $entry ) ) {
                return new WP_Error( 'rcl_entry_type', sprintf( __( 'Entry %d is not an object.', 'reference-code-library' ), $index + 1 ) );
            }

            $title = sanitize_text_field( $entry['title'] ?? '' );
            $id    = sanitize_title( $entry['id'] ?? ( $entry['source_key'] ?? $title ) );
            if ( ! $id || ! $title ) {
                return new WP_Error( 'rcl_entry_required', sprintf( __( 'Entry %d must include an id and title.', 'reference-code-library' ), $index + 1 ) );
            }
            if ( isset( $seen_ids[ $id ] ) ) {
                return new WP_Error( 'rcl_duplicate_id', sprintf( __( 'The entry id "%s" appears more than once in the pack.', 'reference-code-library' ), $id ) );
            }
            $seen_ids[ $id ] = true;

            $collection = sanitize_title( $entry['collection'] ?? '' );
            if ( $collection && ! isset( $normalized['collections'][ $collection ] ) ) {
                return new WP_Error(
                    'rcl_missing_collection',
                    sprintf( __( 'Entry "%1$s" references the missing collection "%2$s".', 'reference-code-library' ), $title, $collection )
                );
            }

            $tags = array();
            if ( isset( $entry['tags'] ) && is_array( $entry['tags'] ) ) {
                foreach ( $entry['tags'] as $tag ) {
                    $tag = sanitize_text_field( $tag );
                    if ( $tag ) {
                        $tags[] = $tag;
                    }
                }
            }

            $code = isset( $entry['code'] ) && is_string( $entry['code'] ) ? wp_check_invalid_utf8( $entry['code'] ) : '';
            $code = str_replace( array( "\r\n", "\r" ), "\n", $code );

            $normalized['entries'][] = array(
                'id'                   => $id,
                'title'                => $title,
                'collection'           => $collection,
                'status'               => sanitize_text_field( $entry['status'] ?? 'Working reference' ),
                'technology'           => sanitize_text_field( $entry['technology'] ?? '' ),
                'code_language'        => sanitize_key( $entry['code_language'] ?? ( $entry['language'] ?? 'text' ) ),
                'summary'              => sanitize_textarea_field( $entry['summary'] ?? '' ),
                'use_when'             => sanitize_textarea_field( $entry['use_when'] ?? '' ),
                'implementation_notes' => sanitize_textarea_field( $entry['implementation_notes'] ?? '' ),
                'code'                 => $code,
                'reference_url'        => esc_url_raw( $entry['reference_url'] ?? ( $entry['source_url'] ?? '' ) ),
                'tags'                 => array_values( array_unique( $tags ) ),
                'order'                => (int) ( $entry['order'] ?? ( $index + 1 ) ),
            );
        }

        $normalized['collections'] = array_values( $normalized['collections'] );
        return $normalized;
    }

    private static function normalize_branding( $branding ) {
        if ( ! is_array( $branding ) ) {
            return array();
        }

        $map = array(
            'title'                        => 'text',
            'eyebrow'                      => 'text',
            'organization'                 => 'text',
            'intro'                        => 'textarea',
            'primary_color'                => 'color',
            'secondary_color'              => 'color',
            'accent_color'                 => 'color',
            'focus_color'                  => 'color',
            'background_color'             => 'color',
            'card_color'                   => 'color',
            'text_color'                   => 'color',
            'muted_color'                  => 'color',
            'border_color'                 => 'color',
            'layout_preset'                => 'key',
            'show_hero'                    => 'bool',
            'show_stats'                   => 'bool',
            'show_start_here'              => 'bool',
            'show_collection_descriptions' => 'bool',
        );

        $output = array();
        foreach ( $map as $key => $type ) {
            if ( ! array_key_exists( $key, $branding ) ) {
                continue;
            }

            $value = $branding[ $key ];
            if ( 'color' === $type ) {
                $value = sanitize_hex_color( $value );
            } elseif ( 'textarea' === $type ) {
                $value = sanitize_textarea_field( $value );
            } elseif ( 'key' === $type ) {
                $value = sanitize_key( $value );
            } elseif ( 'bool' === $type ) {
                $value = ! empty( $value ) ? '1' : '0';
            } else {
                $value = sanitize_text_field( $value );
            }

            if ( '' !== (string) $value ) {
                $output[ $key ] = $value;
            }
        }

        if ( empty( $output['eyebrow'] ) && ! empty( $output['organization'] ) ) {
            $output['eyebrow'] = $output['organization'];
        }
        unset( $output['organization'] );

        return $output;
    }

    private static function convert_legacy_pack( $data ) {
        $collections = array();
        foreach ( $data['collections'] ?? array() as $collection ) {
            $collections[] = array(
                'id'          => $collection['slug'] ?? '',
                'name'        => $collection['name'] ?? '',
                'description' => $collection['description'] ?? '',
                'icon'        => $collection['icon'] ?? '',
                'class'       => $collection['class'] ?? '',
                'order'       => $collection['order'] ?? 0,
            );
        }

        $entries = array();
        foreach ( $data['patterns'] ?? array() as $pattern ) {
            $entries[] = array(
                'id'                   => $pattern['source_key'] ?? '',
                'title'                => $pattern['title'] ?? '',
                'collection'           => $pattern['collection'] ?? '',
                'technology'           => $pattern['technology'] ?? '',
                'status'               => $pattern['status'] ?? 'Working reference',
                'summary'              => $pattern['summary'] ?? '',
                'use_when'             => $pattern['use_when'] ?? '',
                'implementation_notes' => $pattern['implementation_notes'] ?? '',
                'code_language'        => $pattern['code_language'] ?? 'text',
                'code'                 => $pattern['code'] ?? '',
                'order'                => $pattern['order'] ?? 0,
            );
        }

        return array(
            'schema' => 'reference-code-library/v2',
            'pack'   => array(
                'id'          => 'legacy-library-pack',
                'name'        => 'Imported Legacy Library',
                'version'     => sanitize_text_field( $data['version'] ?? '1.0.0' ),
                'description' => 'Converted automatically from the v1 bundled library format.',
            ),
            'collections' => $collections,
            'entries'     => $entries,
        );
    }

    public static function build_preview( $data ) {
        $summary = array(
            'pack'        => $data['pack'],
            'branding'    => ! empty( $data['branding'] ),
            'collections' => count( $data['collections'] ),
            'entries'     => count( $data['entries'] ),
            'new'         => 0,
            'existing'    => 0,
            'items'       => array(),
        );

        foreach ( $data['entries'] as $entry ) {
            $existing = self::find_entry( $entry['id'] );
            if ( $existing ) {
                ++$summary['existing'];
            } else {
                ++$summary['new'];
            }
            $summary['items'][] = array(
                'id'       => $entry['id'],
                'title'    => $entry['title'],
                'existing' => (bool) $existing,
            );
        }

        return $summary;
    }

    public static function commit( $data, $conflict_policy = 'update', $import_branding = false, $create_page = false ) {
        if ( ! in_array( $conflict_policy, array( 'update', 'skip', 'duplicate' ), true ) ) {
            $conflict_policy = 'update';
        }

        $collection_ids = array();
        $errors         = array();

        foreach ( $data['collections'] as $collection ) {
            $term = term_exists( $collection['id'], RCL_Library::TAX_COLLECTION );
            if ( ! $term ) {
                $term = wp_insert_term(
                    $collection['name'],
                    RCL_Library::TAX_COLLECTION,
                    array(
                        'slug'        => $collection['id'],
                        'description' => $collection['description'],
                    )
                );
            } else {
                $term_id = is_array( $term ) ? $term['term_id'] : $term;
                $term    = wp_update_term(
                    (int) $term_id,
                    RCL_Library::TAX_COLLECTION,
                    array(
                        'name'        => $collection['name'],
                        'description' => $collection['description'],
                    )
                );
            }

            if ( is_wp_error( $term ) ) {
                $errors[] = sprintf( 'Collection "%1$s": %2$s', $collection['name'], $term->get_error_message() );
                continue;
            }

            $term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
            $collection_ids[ $collection['id'] ] = $term_id;
            update_term_meta( $term_id, '_moa_icon', $collection['icon'] );
            update_term_meta( $term_id, '_moa_class', $collection['class'] );
            update_term_meta( $term_id, '_moa_order', (int) $collection['order'] );
        }

        $result = array(
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => array(),
            'page_id'  => 0,
            'branding' => false,
        );

        foreach ( $data['entries'] as $entry ) {
            $existing_id = self::find_entry( $entry['id'] );
            $source_key  = $entry['id'];

            if ( $existing_id && 'skip' === $conflict_policy ) {
                ++$result['skipped'];
                continue;
            }

            if ( $existing_id && 'duplicate' === $conflict_policy ) {
                $existing_id = 0;
                $source_key  = RCL_Library::generate_unique_source_key( $entry['id'] );
            }

            $postarr = array(
                'post_type'   => RCL_Library::POST_TYPE,
                'post_status' => 'publish',
                'post_title'  => $entry['title'],
                'post_name'   => sanitize_title( $source_key ),
                'menu_order'  => (int) $entry['order'],
            );

            if ( $existing_id ) {
                $postarr['ID'] = (int) $existing_id;
                $post_id       = wp_update_post( wp_slash( $postarr ), true );
            } else {
                $post_id = wp_insert_post( wp_slash( $postarr ), true );
            }

            if ( is_wp_error( $post_id ) ) {
                $errors[] = sprintf( 'Entry "%1$s": %2$s', $entry['title'], $post_id->get_error_message() );
                continue;
            }

            if ( $existing_id ) {
                ++$result['updated'];
            } else {
                ++$result['created'];
            }

            $map = array(
                '_moa_source_key'           => $source_key,
                '_moa_summary'              => $entry['summary'],
                '_moa_use_when'             => $entry['use_when'],
                '_moa_implementation_notes' => $entry['implementation_notes'],
                '_moa_technology'           => $entry['technology'],
                '_moa_code_language'        => $entry['code_language'],
                '_moa_code'                 => $entry['code'],
                '_moa_source_url'           => $entry['reference_url'],
            );
            foreach ( $map as $key => $value ) {
                update_post_meta( $post_id, $key, $value );
            }
            delete_post_meta( $post_id, '_moa_source_file' );

            if ( $entry['collection'] && isset( $collection_ids[ $entry['collection'] ] ) ) {
                wp_set_object_terms( $post_id, array( $collection_ids[ $entry['collection'] ] ), RCL_Library::TAX_COLLECTION, false );
            } else {
                wp_set_object_terms( $post_id, array(), RCL_Library::TAX_COLLECTION, false );
            }

            self::assign_named_term( $post_id, $entry['status'], RCL_Library::TAX_STATUS );
            wp_set_object_terms( $post_id, $entry['tags'], RCL_Library::TAX_TAG, false );
        }

        if ( $import_branding && ! empty( $data['branding'] ) ) {
            $settings = array_merge( RCL_Library::get_settings(), $data['branding'] );
            update_option( RCL_Library::OPTION_KEY, RCL_Library::sanitize_settings( $settings ) );
            $result['branding'] = true;
        }

        if ( $create_page ) {
            $page = get_page_by_path( 'code-library' );
            if ( $page ) {
                $result['page_id'] = (int) $page->ID;
            } else {
                $page_id = wp_insert_post(
                    array(
                        'post_type'    => 'page',
                        'post_status'  => 'draft',
                        'post_title'   => RCL_Library::get_settings()['title'],
                        'post_name'    => 'code-library',
                        'post_content' => '[code_library]',
                    ),
                    true
                );
                if ( is_wp_error( $page_id ) ) {
                    $errors[] = 'Landing page: ' . $page_id->get_error_message();
                } else {
                    $result['page_id'] = (int) $page_id;
                }
            }
        }

        $result['errors'] = array_merge( $errors, $result['errors'] );
        return $result;
    }

    private static function assign_named_term( $post_id, $name, $taxonomy ) {
        $name = sanitize_text_field( $name );
        if ( ! $name ) {
            wp_set_object_terms( $post_id, array(), $taxonomy, false );
            return;
        }

        $slug = sanitize_title( $name );
        $term = term_exists( $slug, $taxonomy );
        if ( ! $term ) {
            $term = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
        }
        if ( ! is_wp_error( $term ) ) {
            $term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
            wp_set_object_terms( $post_id, array( $term_id ), $taxonomy, false );
        }
    }

    public static function find_entry( $source_key ) {
        $posts = get_posts(
            array(
                'post_type'      => RCL_Library::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => '_moa_source_key',
                'meta_value'     => sanitize_title( $source_key ),
            )
        );
        return $posts ? (int) $posts[0] : 0;
    }
}
