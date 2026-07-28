<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RCL_Exporter {
    public static function build_pack( $post_ids = array(), $collection_slug = '', $include_branding = false ) {
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

        $posts = get_posts( $args );
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

        foreach ( $posts as $post ) {
            $collections = wp_get_post_terms( $post->ID, RCL_Library::TAX_COLLECTION );
            $statuses    = wp_get_post_terms( $post->ID, RCL_Library::TAX_STATUS );
            $tags        = wp_get_post_terms( $post->ID, RCL_Library::TAX_TAG );

            $source_key = get_post_meta( $post->ID, '_moa_source_key', true );
            if ( ! $source_key ) {
                $source_key = $post->post_name ?: sanitize_title( $post->post_title );
            }

            $pack['entries'][] = array(
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
        }

        return $pack;
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
            'code'                 => ':focus-visible {\n    outline: 3px solid #ffcc05;\n    outline-offset: 3px;\n}',
            'reference_url'        => '',
            'tags'                 => array( 'focus', 'keyboard', 'accessibility' ),
            'order'                => 10,
        );
        return $pack;
    }

    public static function send_download( $data, $filename ) {
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
}
