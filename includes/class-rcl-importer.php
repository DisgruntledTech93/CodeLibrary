<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RCL_Importer {
    const MAX_FILE_SIZE       = 5242880;  // 5 MB for JSON/TXT.
    const MAX_ZIP_FILE_SIZE   = 26214400; // 25 MB for portable packs.
    const MAX_EXTRACTED_SIZE  = 52428800; // 50 MB after extraction.
    const MAX_EXAMPLE_IMAGES  = 200;
    const MAX_ENTRY_EXAMPLES  = 20;

    public static function prepare_upload( $file ) {
        self::cleanup_stale_imports();

        $read = self::read_uploaded_file( $file );
        if ( is_wp_error( $read ) ) {
            return $read;
        }

        $normalized = self::normalize_pack( $read['data'], $read['context'] );
        if ( is_wp_error( $normalized ) ) {
            if ( ! empty( $read['context']['temp_dir'] ) ) {
                self::delete_directory( $read['context']['temp_dir'] );
            }
            return $normalized;
        }

        $preview = self::build_preview( $normalized );
        if ( is_wp_error( $preview ) ) {
            if ( ! empty( $read['context']['temp_dir'] ) ) {
                self::delete_directory( $read['context']['temp_dir'] );
            }
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
        if ( ! empty( $data['_import']['temp_dir'] ) ) {
            self::delete_directory( $data['_import']['temp_dir'] );
        }
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
            return new WP_Error( 'rcl_no_file', __( 'Choose a JSON, TXT, or ZIP library pack to upload.', 'reference-code-library' ) );
        }

        $error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ( UPLOAD_ERR_OK !== $error ) {
            return new WP_Error( 'rcl_upload_error', self::upload_error_message( $error ) );
        }

        $name      = sanitize_file_name( $file['name'] ?? '' );
        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, array( 'json', 'txt', 'zip' ), true ) ) {
            return new WP_Error( 'rcl_file_type', __( 'Only .json, .txt, and .zip library packs are accepted.', 'reference-code-library' ) );
        }

        $size      = isset( $file['size'] ) ? (int) $file['size'] : 0;
        $size_limit = 'zip' === $extension ? self::MAX_ZIP_FILE_SIZE : self::MAX_FILE_SIZE;
        if ( $size <= 0 || $size > $size_limit ) {
            return new WP_Error(
                'rcl_file_size',
                'zip' === $extension
                    ? __( 'A ZIP library pack must be larger than 0 bytes and no larger than 25 MB.', 'reference-code-library' )
                    : __( 'A JSON or TXT library pack must be larger than 0 bytes and no larger than 5 MB.', 'reference-code-library' )
            );
        }

        if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'rcl_invalid_upload', __( 'The file was not received as a valid HTTP upload.', 'reference-code-library' ) );
        }

        if ( 'zip' === $extension ) {
            return self::read_zip_pack( $file['tmp_name'] );
        }

        if ( ! is_readable( $file['tmp_name'] ) ) {
            return new WP_Error( 'rcl_unreadable_file', __( 'WordPress could not read the uploaded library pack.', 'reference-code-library' ) );
        }

        $raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = self::decode_json( $raw );
        if ( is_wp_error( $data ) ) {
            return $data;
        }

        return array(
            'data'    => $data,
            'context' => array( 'type' => 'json' ),
        );
    }

    private static function read_zip_pack( $uploaded_file ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( function_exists( 'wp_zip_file_is_valid' ) && ! wp_zip_file_is_valid( $uploaded_file ) ) {
            return new WP_Error( 'rcl_invalid_zip', __( 'The uploaded ZIP file is invalid or corrupted.', 'reference-code-library' ) );
        }

        $preflight = self::preflight_zip( $uploaded_file );
        if ( is_wp_error( $preflight ) ) {
            return $preflight;
        }

        $temp_dir = trailingslashit( get_temp_dir() ) . 'rcl-import-' . time() . '-' . wp_generate_password( 8, false, false );
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            return new WP_Error( 'rcl_temp_dir', __( 'WordPress could not create a temporary import directory.', 'reference-code-library' ) );
        }

        if ( ! WP_Filesystem() ) {
            self::delete_directory( $temp_dir );
            return new WP_Error( 'rcl_filesystem', __( 'WordPress could not initialize the filesystem for this ZIP import.', 'reference-code-library' ) );
        }

        $unzipped = unzip_file( $uploaded_file, $temp_dir );
        if ( is_wp_error( $unzipped ) ) {
            self::delete_directory( $temp_dir );
            return new WP_Error( 'rcl_unzip', sprintf( __( 'The ZIP pack could not be extracted: %s', 'reference-code-library' ), $unzipped->get_error_message() ) );
        }

        $manifest = trailingslashit( $temp_dir ) . 'library.json';
        if ( ! is_readable( $manifest ) ) {
            self::delete_directory( $temp_dir );
            return new WP_Error( 'rcl_zip_manifest', __( 'A portable ZIP pack must contain library.json at the archive root.', 'reference-code-library' ) );
        }

        $total_size  = 0;
        $image_count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $temp_dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ( $iterator as $item ) {
            if ( $item->isLink() || ! $item->isFile() ) {
                self::delete_directory( $temp_dir );
                return new WP_Error( 'rcl_zip_unsafe', __( 'The ZIP pack contains an unsupported link or filesystem item.', 'reference-code-library' ) );
            }

            $path     = $item->getPathname();
            $relative = ltrim( str_replace( '\\', '/', substr( $path, strlen( $temp_dir ) ) ), '/' );
            if ( 0 !== validate_file( $relative ) ) {
                self::delete_directory( $temp_dir );
                return new WP_Error( 'rcl_zip_path', __( 'The ZIP pack contains an unsafe file path.', 'reference-code-library' ) );
            }

            $size = (int) $item->getSize();
            $total_size += $size;
            if ( $total_size > self::MAX_EXTRACTED_SIZE ) {
                self::delete_directory( $temp_dir );
                return new WP_Error( 'rcl_zip_expanded_size', __( 'The extracted ZIP pack exceeds the 50 MB safety limit.', 'reference-code-library' ) );
            }

            if ( 'library.json' === $relative ) {
                if ( $size > self::MAX_FILE_SIZE ) {
                    self::delete_directory( $temp_dir );
                    return new WP_Error( 'rcl_manifest_size', __( 'library.json exceeds the 5 MB manifest limit.', 'reference-code-library' ) );
                }
                continue;
            }

            if ( 0 !== strpos( $relative, 'images/' ) ) {
                self::delete_directory( $temp_dir );
                return new WP_Error( 'rcl_zip_contents', __( 'Portable ZIP packs may contain only library.json and image files inside images/.', 'reference-code-library' ) );
            }

            ++$image_count;
            if ( $image_count > self::MAX_EXAMPLE_IMAGES ) {
                self::delete_directory( $temp_dir );
                return new WP_Error( 'rcl_zip_image_count', __( 'The ZIP pack contains more than 200 screenshots.', 'reference-code-library' ) );
            }

            $mime = self::validated_image_mime( $path );
            if ( ! $mime ) {
                self::delete_directory( $temp_dir );
                return new WP_Error( 'rcl_zip_image_type', sprintf( __( 'The file "%s" is not a supported image.', 'reference-code-library' ), $relative ) );
            }
        }

        $raw  = file_get_contents( $manifest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = self::decode_json( $raw );
        if ( is_wp_error( $data ) ) {
            self::delete_directory( $temp_dir );
            return $data;
        }

        return array(
            'data'    => $data,
            'context' => array(
                'type'     => 'zip',
                'temp_dir' => $temp_dir,
            ),
        );
    }

    private static function preflight_zip( $uploaded_file ) {
        $items = array();

        if ( class_exists( 'ZipArchive' ) ) {
            $archive = new ZipArchive();
            $opened  = $archive->open( $uploaded_file, ZipArchive::CHECKCONS );
            if ( true !== $opened ) {
                return new WP_Error( 'rcl_invalid_zip', __( 'The uploaded ZIP file is invalid or corrupted.', 'reference-code-library' ) );
            }

            for ( $index = 0; $index < $archive->numFiles; ++$index ) {
                $stat = $archive->statIndex( $index );
                if ( ! is_array( $stat ) ) {
                    $archive->close();
                    return new WP_Error( 'rcl_zip_stat', __( 'WordPress could not inspect every file in the ZIP pack.', 'reference-code-library' ) );
                }
                $items[] = array(
                    'name'   => $stat['name'] ?? '',
                    'size'   => (int) ( $stat['size'] ?? 0 ),
                    'folder' => '/' === substr( (string) ( $stat['name'] ?? '' ), -1 ),
                );
            }
            $archive->close();
        } else {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $archive = new PclZip( $uploaded_file );
            $list    = $archive->listContent();
            if ( 0 === $list || ! is_array( $list ) ) {
                return new WP_Error( 'rcl_invalid_zip', __( 'The uploaded ZIP file is invalid or corrupted.', 'reference-code-library' ) );
            }
            foreach ( $list as $item ) {
                $items[] = array(
                    'name'   => $item['filename'] ?? '',
                    'size'   => (int) ( $item['size'] ?? 0 ),
                    'folder' => ! empty( $item['folder'] ),
                );
            }
        }

        $total_size  = 0;
        $image_count = 0;
        $has_manifest = false;

        foreach ( $items as $item ) {
            $name = ltrim( str_replace( '\\', '/', (string) $item['name'] ), '/' );
            if ( ! $name || ! empty( $item['folder'] ) ) {
                continue;
            }
            if ( 0 !== validate_file( $name ) ) {
                return new WP_Error( 'rcl_zip_path', __( 'The ZIP pack contains an unsafe file path.', 'reference-code-library' ) );
            }

            $total_size += max( 0, (int) $item['size'] );
            if ( $total_size > self::MAX_EXTRACTED_SIZE ) {
                return new WP_Error( 'rcl_zip_expanded_size', __( 'The extracted ZIP pack exceeds the 50 MB safety limit.', 'reference-code-library' ) );
            }

            if ( 'library.json' === $name ) {
                $has_manifest = true;
                if ( (int) $item['size'] > self::MAX_FILE_SIZE ) {
                    return new WP_Error( 'rcl_manifest_size', __( 'library.json exceeds the 5 MB manifest limit.', 'reference-code-library' ) );
                }
                continue;
            }

            if ( 0 !== strpos( $name, 'images/' ) ) {
                return new WP_Error( 'rcl_zip_contents', __( 'Portable ZIP packs may contain only library.json and image files inside images/.', 'reference-code-library' ) );
            }

            ++$image_count;
            if ( $image_count > self::MAX_EXAMPLE_IMAGES ) {
                return new WP_Error( 'rcl_zip_image_count', __( 'The ZIP pack contains more than 200 screenshots.', 'reference-code-library' ) );
            }
        }

        if ( ! $has_manifest ) {
            return new WP_Error( 'rcl_zip_manifest', __( 'A portable ZIP pack must contain library.json at the archive root.', 'reference-code-library' ) );
        }

        return true;
    }

    private static function decode_json( $raw ) {
        if ( false === $raw || '' === trim( (string) $raw ) ) {
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

    public static function normalize_pack( $data, $context = array() ) {
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
            '_import'     => is_array( $context ) ? $context : array(),
        );

        $collections = isset( $data['collections'] ) && is_array( $data['collections'] ) ? $data['collections'] : array();
        foreach ( $collections as $index => $collection ) {
            if ( ! is_array( $collection ) ) {
                return new WP_Error( 'rcl_collection_type', sprintf( __( 'Collection %d is not an object.', 'reference-code-library' ), $index + 1 ) );
            }

            $collection_name = sanitize_text_field( $collection['name'] ?? '' );
            $id              = sanitize_title( $collection['id'] ?? ( $collection['slug'] ?? $collection_name ) );
            if ( ! $id || ! $collection_name ) {
                return new WP_Error( 'rcl_collection_required', sprintf( __( 'Collection %d must include an id and name.', 'reference-code-library' ), $index + 1 ) );
            }

            $normalized['collections'][ $id ] = array(
                'id'          => $id,
                'name'        => $collection_name,
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

            $examples_provided = array_key_exists( 'examples', $entry );
            $examples          = array();
            if ( $examples_provided ) {
                $example_result = self::normalize_examples( $entry['examples'], $title, $context );
                if ( is_wp_error( $example_result ) ) {
                    return $example_result;
                }
                $examples = $example_result;
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
                'examples'             => $examples,
                'examples_provided'    => $examples_provided,
                'order'                => (int) ( $entry['order'] ?? ( $index + 1 ) ),
            );
        }

        $normalized['collections'] = array_values( $normalized['collections'] );
        return $normalized;
    }

    private static function normalize_examples( $examples, $entry_title, $context ) {
        if ( null === $examples ) {
            return array();
        }
        if ( ! is_array( $examples ) ) {
            return new WP_Error( 'rcl_examples_type', sprintf( __( 'Entry "%s" has an examples value that is not an array.', 'reference-code-library' ), $entry_title ) );
        }
        if ( count( $examples ) > self::MAX_ENTRY_EXAMPLES ) {
            return new WP_Error( 'rcl_examples_count', sprintf( __( 'Entry "%s" contains more than 20 screenshots.', 'reference-code-library' ), $entry_title ) );
        }

        $normalized = array();
        foreach ( $examples as $index => $example ) {
            if ( ! is_array( $example ) ) {
                return new WP_Error( 'rcl_example_type', sprintf( __( 'Screenshot %1$d for entry "%2$s" is not an object.', 'reference-code-library' ), $index + 1, $entry_title ) );
            }

            $file = isset( $example['file'] ) ? str_replace( '\\', '/', trim( (string) $example['file'] ) ) : '';
            $file = ltrim( $file, '/' );
            $url  = esc_url_raw( $example['url'] ?? '' );

            if ( $file ) {
                if ( 0 !== validate_file( $file ) || 0 !== strpos( $file, 'images/' ) ) {
                    return new WP_Error( 'rcl_example_path', sprintf( __( 'Screenshot %1$d for entry "%2$s" has an unsafe file path.', 'reference-code-library' ), $index + 1, $entry_title ) );
                }
                if ( empty( $context['temp_dir'] ) ) {
                    return new WP_Error( 'rcl_example_requires_zip', sprintf( __( 'Entry "%s" references a local screenshot file, so it must be imported from a ZIP pack.', 'reference-code-library' ), $entry_title ) );
                }

                $base = realpath( $context['temp_dir'] );
                $path = realpath( trailingslashit( $context['temp_dir'] ) . $file );
                if ( ! $base || ! $path || 0 !== strpos( $path, trailingslashit( $base ) ) || ! is_readable( $path ) || ! self::validated_image_mime( $path ) ) {
                    return new WP_Error( 'rcl_example_missing', sprintf( __( 'Screenshot file "%1$s" for entry "%2$s" is missing or invalid.', 'reference-code-library' ), $file, $entry_title ) );
                }
            } elseif ( $url ) {
                $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
                if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
                    return new WP_Error( 'rcl_example_url', sprintf( __( 'Screenshot %1$d for entry "%2$s" must use an HTTP or HTTPS URL.', 'reference-code-library' ), $index + 1, $entry_title ) );
                }
            } else {
                return new WP_Error( 'rcl_example_source', sprintf( __( 'Screenshot %1$d for entry "%2$s" must include either file or url.', 'reference-code-library' ), $index + 1, $entry_title ) );
            }

            $normalized[] = array(
                'file'    => $file,
                'url'     => $url,
                'type'    => RCL_Library::sanitize_example_type( $example['type'] ?? 'result' ),
                'alt'     => sanitize_text_field( $example['alt'] ?? '' ),
                'caption' => sanitize_textarea_field( $example['caption'] ?? '' ),
                'order'   => (int) ( $example['order'] ?? ( ( $index + 1 ) * 10 ) ),
            );
        }

        usort(
            $normalized,
            static function( $a, $b ) {
                return (int) $a['order'] <=> (int) $b['order'];
            }
        );
        return $normalized;
    }

    private static function validated_image_mime( $path ) {
        if ( ! is_readable( $path ) ) {
            return '';
        }

        $mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );
        $checked = wp_check_filetype_and_ext( $path, basename( $path ), $mimes );
        $actual  = wp_get_image_mime( $path );

        if ( empty( $checked['ext'] ) || empty( $checked['type'] ) || ! $actual || $checked['type'] !== $actual ) {
            return '';
        }

        return $actual;
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
            'screenshots' => 0,
            'new'         => 0,
            'existing'    => 0,
            'items'       => array(),
        );

        foreach ( $data['entries'] as $entry ) {
            $existing   = self::find_entry( $entry['id'] );
            $screenshots = count( $entry['examples'] );
            $summary['screenshots'] += $screenshots;
            if ( $existing ) {
                ++$summary['existing'];
            } else {
                ++$summary['new'];
            }
            $summary['items'][] = array(
                'id'          => $entry['id'],
                'title'       => $entry['title'],
                'screenshots' => $screenshots,
                'existing'    => (bool) $existing,
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
            'created'        => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'media_imported' => 0,
            'errors'         => array(),
            'page_id'        => 0,
            'branding'       => false,
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

            if ( $entry['examples_provided'] ) {
                $existing_examples = RCL_Library::sanitize_examples( get_post_meta( $post_id, RCL_Library::META_EXAMPLES, true ) );
                if ( ! $entry['examples'] ) {
                    update_post_meta( $post_id, RCL_Library::META_EXAMPLES, array() );
                } else {
                    $media_result = self::import_examples( $entry['examples'], $post_id, $entry['title'], $data['_import'] ?? array() );
                    $result['media_imported'] += $media_result['imported'];
                    $errors = array_merge( $errors, $media_result['errors'] );

                    if ( $media_result['examples'] ) {
                        update_post_meta( $post_id, RCL_Library::META_EXAMPLES, RCL_Library::sanitize_examples( $media_result['examples'] ) );
                    } elseif ( ! $existing_id ) {
                        update_post_meta( $post_id, RCL_Library::META_EXAMPLES, array() );
                    } else {
                        update_post_meta( $post_id, RCL_Library::META_EXAMPLES, $existing_examples );
                        $errors[] = sprintf( 'Entry "%s": no screenshots could be imported, so the existing gallery was preserved.', $entry['title'] );
                    }
                }
            }
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

    private static function import_examples( $examples, $post_id, $entry_title, $context ) {
        $result = array(
            'examples' => array(),
            'imported' => 0,
            'errors'   => array(),
        );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        foreach ( $examples as $index => $example ) {
            $attachment_id = 0;

            if ( ! empty( $example['file'] ) && ! empty( $context['temp_dir'] ) ) {
                $source = realpath( trailingslashit( $context['temp_dir'] ) . $example['file'] );
                $base   = realpath( $context['temp_dir'] );
                if ( $source && $base && 0 === strpos( $source, trailingslashit( $base ) ) && is_readable( $source ) ) {
                    $attachment_id = self::sideload_local_image( $source, $post_id, $example['caption'] );
                } else {
                    $attachment_id = new WP_Error( 'rcl_missing_screenshot', __( 'The screenshot file was missing from temporary storage.', 'reference-code-library' ) );
                }
            } elseif ( ! empty( $example['url'] ) ) {
                $attachment_id = media_sideload_image( $example['url'], $post_id, $example['caption'], 'id' );
            }

            if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
                $message = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : __( 'Unknown media import error.', 'reference-code-library' );
                $result['errors'][] = sprintf( 'Entry "%1$s", screenshot %2$d: %3$s', $entry_title, $index + 1, $message );
                continue;
            }

            $attachment_id = (int) $attachment_id;
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $example['alt'] );
            wp_update_post(
                array(
                    'ID'           => $attachment_id,
                    'post_parent'  => $post_id,
                    'post_excerpt' => $example['caption'],
                )
            );

            $result['examples'][] = array(
                'attachment_id' => $attachment_id,
                'type'          => $example['type'],
                'alt'           => $example['alt'],
                'caption'       => $example['caption'],
                'order'         => $example['order'],
            );
            ++$result['imported'];
        }

        return $result;
    }

    private static function sideload_local_image( $source, $post_id, $caption ) {
        $temp_file = wp_tempnam( basename( $source ) );
        if ( ! $temp_file || ! copy( $source, $temp_file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
            if ( $temp_file ) {
                wp_delete_file( $temp_file );
            }
            return new WP_Error( 'rcl_screenshot_copy', __( 'The screenshot could not be copied for Media Library import.', 'reference-code-library' ) );
        }

        $file_array = array(
            'name'     => sanitize_file_name( basename( $source ) ),
            'tmp_name' => $temp_file,
        );
        $attachment_id = media_handle_sideload( $file_array, $post_id, $caption );
        if ( is_wp_error( $attachment_id ) && file_exists( $temp_file ) ) {
            wp_delete_file( $temp_file );
        }
        return $attachment_id;
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

    private static function cleanup_stale_imports() {
        $base = trailingslashit( get_temp_dir() );
        $dirs = glob( $base . 'rcl-import-*', GLOB_ONLYDIR );
        if ( ! is_array( $dirs ) ) {
            return;
        }

        $cutoff = time() - DAY_IN_SECONDS;
        foreach ( $dirs as $directory ) {
            $modified = @filemtime( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( $modified && $modified < $cutoff ) {
                self::delete_directory( $directory );
            }
        }
    }

    private static function delete_directory( $directory ) {
        if ( ! $directory || ! is_dir( $directory ) ) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $items as $item ) {
            if ( $item->isDir() ) {
                @rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            } else {
                wp_delete_file( $item->getPathname() );
            }
        }
        @rmdir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }
}
