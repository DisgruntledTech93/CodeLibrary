<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RCL_Exporter {
    public static function build_pack( $post_ids = array(), $collection_slug = '', $include_branding = false, $include_examples = true ) {
        $args = array(
            'post_type'      => RCL_Library::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
        );

        $post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );
        if ( $post_ids ) {
            $args['post__in'] = $post_ids;
            $args['orderby']  = 'post__in';
        } elseif ( $collection_slug ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => RCL_Library::TAX_COLLECTION,
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $collection_slug ),
                ),
            );
        }

        $posts    = get_posts( $args );
        $settings = RCL_Library::get_settings();

        $pack = array(
            'schema' => 'reference-code-library/v2',
            'pack'   => array(
                'id'          => sanitize_title( $settings['title'] ),
                'name'        => $settings['title'],
                'version'     => '1.0.0',
                'description' => $settings['intro'],
                'exported_at' => gmdate( 'c' ),
                'generator'   => 'Reference Code Library ' . RCL_VERSION,
            ),
            'collections' => array(),
            'entries'     => array(),
        );

        if ( $include_branding ) {
            $pack['branding'] = self::export_branding( $settings );
        }

        $collection_ids = array();
        foreach ( $posts as $post ) {
            $terms = wp_get_post_terms( $post->ID, RCL_Library::TAX_COLLECTION );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $collection_ids[ $term->term_id ] = $term;
                }
            }
        }

        foreach ( $collection_ids as $term ) {
            $pack['collections'][] = array(
                'id'          => $term->slug,
                'name'        => $term->name,
                'description' => $term->description,
                'icon'        => (string) get_term_meta( $term->term_id, '_moa_icon', true ),
                'class'       => (string) get_term_meta( $term->term_id, '_moa_class', true ),
                'order'       => (int) get_term_meta( $term->term_id, '_moa_order', true ),
            );
        }

        usort(
            $pack['collections'],
            static function( $a, $b ) {
                if ( $a['order'] === $b['order'] ) {
                    return strcasecmp( $a['name'], $b['name'] );
                }
                return $a['order'] <=> $b['order'];
            }
        );

        $export_files = array();
        $used_names   = array();

        foreach ( $posts as $post ) {
            $collections = wp_get_post_terms( $post->ID, RCL_Library::TAX_COLLECTION );
            $statuses    = wp_get_post_terms( $post->ID, RCL_Library::TAX_STATUS );
            $tags        = wp_get_post_terms( $post->ID, RCL_Library::TAX_TAG );

            $source_key = get_post_meta( $post->ID, '_moa_source_key', true );
            if ( ! $source_key ) {
                $source_key = $post->post_name ?: sanitize_title( $post->post_title );
            }

            $entry = array(
                'id'                   => $source_key,
                'title'                => $post->post_title,
                'collection'           => $collections && ! is_wp_error( $collections ) ? $collections[0]->slug : '',
                'status'               => $statuses && ! is_wp_error( $statuses ) ? $statuses[0]->name : 'Working reference',
                'technology'           => (string) get_post_meta( $post->ID, '_moa_technology', true ),
                'code_language'        => (string) get_post_meta( $post->ID, '_moa_code_language', true ),
                'summary'              => (string) get_post_meta( $post->ID, '_moa_summary', true ),
                'use_when'             => (string) get_post_meta( $post->ID, '_moa_use_when', true ),
                'implementation_notes' => (string) get_post_meta( $post->ID, '_moa_implementation_notes', true ),
                'code'                 => (string) get_post_meta( $post->ID, '_moa_code', true ),
                'reference_url'        => (string) get_post_meta( $post->ID, '_moa_source_url', true ),
                'tags'                 => $tags && ! is_wp_error( $tags ) ? wp_list_pluck( $tags, 'name' ) : array(),
                'order'                => (int) $post->menu_order,
            );

            if ( $include_examples ) {
                $entry['examples'] = array();
                $examples          = RCL_Library::sanitize_examples( get_post_meta( $post->ID, RCL_Library::META_EXAMPLES, true ) );
                foreach ( $examples as $index => $example ) {
                    $attachment_id = (int) $example['attachment_id'];
                    $file          = get_attached_file( $attachment_id );
                    $relative      = '';

                    $extension = $file && is_readable( $file ) ? self::supported_image_extension( $file ) : '';
                    if ( $extension ) {
                        $base      = sanitize_file_name( pathinfo( $file, PATHINFO_FILENAME ) );
                        $base      = $base ?: 'screenshot';
                        $name      = sanitize_file_name( $source_key . '-' . ( $index + 1 ) . '-' . $base . '.' . $extension );
                        $name      = self::unique_filename( $name, $used_names );
                        $relative  = 'images/' . $name;
                        $export_files[ $relative ] = $file;
                    }

                    $item = array(
                        'type'    => RCL_Library::sanitize_example_type( $example['type'] ),
                        'alt'     => (string) $example['alt'],
                        'caption' => (string) $example['caption'],
                        'order'   => (int) $example['order'],
                    );

                    if ( $relative ) {
                        $item['file'] = $relative;
                    } else {
                        $url = wp_get_attachment_url( $attachment_id );
                        if ( ! $url ) {
                            continue;
                        }
                        $item['url'] = $url;
                    }

                    $entry['examples'][] = $item;
                }
            }

            $pack['entries'][] = $entry;
        }

        if ( $export_files ) {
            $pack['_rcl_export_files'] = $export_files;
        }

        return $pack;
    }

    private static function supported_image_extension( $file ) {
        $mime = wp_get_image_mime( $file );
        $map  = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );
        return isset( $map[ $mime ] ) ? $map[ $mime ] : '';
    }

    private static function unique_filename( $filename, &$used_names ) {
        $candidate = $filename;
        $info      = pathinfo( $filename );
        $base      = $info['filename'];
        $extension = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
        $suffix    = 2;

        while ( isset( $used_names[ strtolower( $candidate ) ] ) ) {
            $candidate = $base . '-' . $suffix . $extension;
            ++$suffix;
        }

        $used_names[ strtolower( $candidate ) ] = true;
        return $candidate;
    }

    private static function export_branding( $settings ) {
        $keys = array(
            'title',
            'eyebrow',
            'intro',
            'primary_color',
            'secondary_color',
            'accent_color',
            'focus_color',
            'background_color',
            'card_color',
            'text_color',
            'muted_color',
            'border_color',
            'layout_preset',
            'show_hero',
            'show_stats',
            'show_start_here',
            'show_collection_descriptions',
        );

        $branding = array();
        foreach ( $keys as $key ) {
            $branding[ $key ] = $settings[ $key ];
        }
        return $branding;
    }

    public static function blank_template() {
        return array(
            'schema' => 'reference-code-library/v2',
            'pack'   => array(
                'id'          => 'your-library-id',
                'name'        => 'Your Library Name',
                'version'     => '1.0.0',
                'description' => 'Describe the purpose of this library pack.',
            ),
            'branding' => array(
                'title'         => 'Your Code Library',
                'eyebrow'       => 'Optional organization or descriptor',
                'intro'         => 'Optional introductory text.',
                'primary_color' => '#203a5f',
                'accent_color'  => '#5f9bbc',
            ),
            'collections' => array(
                array(
                    'id'          => 'example-collection',
                    'name'        => 'Example Collection',
                    'description' => 'Explain what belongs in this collection.',
                    'icon'        => 'EX',
                    'class'       => 'example',
                    'order'       => 10,
                ),
            ),
            'entries' => array(),
        );
    }

    public static function example_pack() {
        $pack = self::blank_template();
        $pack['pack']['id']          = 'example-code-library';
        $pack['pack']['name']        = 'Example Code Library';
        $pack['pack']['description'] = 'A small demonstration pack showing every supported entry field.';
        $pack['entries'][] = array(
            'id'                   => 'accessible-focus-example',
            'title'                => 'Visible Focus Example',
            'collection'           => 'example-collection',
            'status'               => 'Working reference',
            'technology'           => 'CSS',
            'code_language'        => 'css',
            'summary'              => 'Adds a clearly visible focus indicator to interactive elements.',
            'use_when'             => 'Use when a theme removes or weakens keyboard focus styling.',
            'implementation_notes' => 'Confirm the selected colors meet contrast requirements against surrounding backgrounds.',
            'code'                 => ":focus-visible {\n    outline: 3px solid #ffcc05;\n    outline-offset: 3px;\n}",
            'reference_url'        => '',
            'tags'                 => array( 'focus', 'keyboard', 'accessibility' ),
            'examples'             => array(),
            'order'                => 10,
        );
        return $pack;
    }

    public static function send_library_pack( $pack, $filename_base ) {
        $files = isset( $pack['_rcl_export_files'] ) && is_array( $pack['_rcl_export_files'] ) ? $pack['_rcl_export_files'] : array();
        unset( $pack['_rcl_export_files'] );

        $filename_base = sanitize_file_name( $filename_base );
        if ( ! $files ) {
            self::send_download( $pack, $filename_base . '.json' );
        }

        $temp_dir = trailingslashit( get_temp_dir() ) . 'rcl-export-' . wp_generate_uuid4();
        if ( ! wp_mkdir_p( $temp_dir . '/images' ) ) {
            wp_die( esc_html__( 'WordPress could not create a temporary export directory.', 'reference-code-library' ) );
        }

        foreach ( $files as $relative => $source ) {
            $relative = str_replace( '\\', '/', $relative );
            if ( 0 !== validate_file( $relative ) || 0 !== strpos( $relative, 'images/' ) || ! is_readable( $source ) ) {
                self::delete_directory( $temp_dir );
                wp_die( esc_html__( 'A screenshot could not be safely added to the export.', 'reference-code-library' ) );
            }

            $destination = trailingslashit( $temp_dir ) . $relative;
            if ( ! copy( $source, $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
                self::delete_directory( $temp_dir );
                wp_die( esc_html__( 'A screenshot could not be copied into the export.', 'reference-code-library' ) );
            }
        }

        $json = wp_json_encode( $pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json || false === file_put_contents( $temp_dir . '/library.json', $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            self::delete_directory( $temp_dir );
            wp_die( esc_html__( 'The library manifest could not be written.', 'reference-code-library' ) );
        }

        $zip_path = trailingslashit( get_temp_dir() ) . $filename_base . '-' . wp_generate_password( 8, false, false ) . '.zip';
        $result   = self::create_zip( $temp_dir, $zip_path );
        if ( is_wp_error( $result ) ) {
            self::delete_directory( $temp_dir );
            wp_die( esc_html( $result->get_error_message() ) );
        }

        if ( headers_sent() ) {
            self::delete_directory( $temp_dir );
            wp_delete_file( $zip_path );
            wp_die( esc_html__( 'The download could not start because output was already sent.', 'reference-code-library' ) );
        }

        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename_base . '.zip"' );
        header( 'Content-Length: ' . (string) filesize( $zip_path ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        self::delete_directory( $temp_dir );
        wp_delete_file( $zip_path );
        exit;
    }

    private static function create_zip( $source_dir, $zip_path ) {
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                return new WP_Error( 'rcl_zip_create', __( 'The ZIP export could not be created.', 'reference-code-library' ) );
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ( $iterator as $file ) {
                if ( ! $file->isFile() ) {
                    continue;
                }
                $path     = $file->getPathname();
                $relative = ltrim( str_replace( '\\', '/', substr( $path, strlen( $source_dir ) ) ), '/' );
                $zip->addFile( $path, $relative );
            }
            $zip->close();
            return true;
        }

        require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        $archive = new PclZip( $zip_path );
        $result  = $archive->create( $source_dir, PCLZIP_OPT_REMOVE_PATH, $source_dir );
        if ( 0 === $result ) {
            return new WP_Error( 'rcl_zip_create', __( 'The ZIP export could not be created on this server.', 'reference-code-library' ) );
        }
        return true;
    }

    public static function send_download( $data, $filename ) {
        unset( $data['_rcl_export_files'] );

        if ( headers_sent() ) {
            wp_die( esc_html__( 'The download could not start because output was already sent.', 'reference-code-library' ) );
        }

        $filename = sanitize_file_name( $filename );
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
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
