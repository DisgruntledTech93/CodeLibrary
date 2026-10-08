<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RCL_Library {
    /**
     * Legacy identifiers are intentionally retained so an existing v1 library
     * remains available after the generic v2 plugin is installed.
     */
    const POST_TYPE      = 'moa_pattern';
    const TAX_COLLECTION = 'moa_collection';
    const TAX_STATUS     = 'moa_status';
    const TAX_TAG        = 'rcl_code_tag';
    const OPTION_KEY     = 'rcl_library_settings';
    const META_EXAMPLES  = '_rcl_examples';

    private static $instance = null;
    private $active_page_id  = 0;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'register_content_types' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_entry_meta' ) );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'entry_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'entry_column_content' ), 10, 2 );
        add_filter( 'plugin_action_links_' . plugin_basename( RCL_FILE ), array( $this, 'plugin_action_links' ) );
        add_action( 'admin_init', array( $this, 'maybe_migrate_legacy_settings' ) );
    }

    public static function activate() {
        $plugin = self::instance();
        $plugin->register_content_types();
        $plugin->maybe_migrate_legacy_settings();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function maybe_migrate_legacy_settings() {
        if ( false !== get_option( self::OPTION_KEY, false ) ) {
            return;
        }

        $legacy = get_option( 'moa_library_settings', false );
        if ( ! is_array( $legacy ) ) {
            return;
        }

        $settings = self::get_default_settings();
        $map      = array(
            'title'         => 'title',
            'eyebrow'       => 'eyebrow',
            'intro'         => 'intro',
            'primary_color' => 'primary_color',
            'mid_color'     => 'secondary_color',
            'accent_color'  => 'accent_color',
            'focus_color'   => 'focus_color',
            'logo_url'      => 'legacy_logo_url',
        );

        foreach ( $map as $old_key => $new_key ) {
            if ( isset( $legacy[ $old_key ] ) && '' !== (string) $legacy[ $old_key ] ) {
                $settings[ $new_key ] = $legacy[ $old_key ];
            }
        }

        update_option( self::OPTION_KEY, self::sanitize_settings( $settings ) );
    }

    public function register_content_types() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'                  => __( 'Code Entries', 'reference-code-library' ),
                    'singular_name'         => __( 'Code Entry', 'reference-code-library' ),
                    'menu_name'             => __( 'Code Library', 'reference-code-library' ),
                    'name_admin_bar'        => __( 'Code Entry', 'reference-code-library' ),
                    'add_new'               => __( 'Add Code', 'reference-code-library' ),
                    'add_new_item'          => __( 'Add Code', 'reference-code-library' ),
                    'edit_item'             => __( 'Edit Code', 'reference-code-library' ),
                    'new_item'              => __( 'New Code Entry', 'reference-code-library' ),
                    'view_item'             => __( 'View Code Entry', 'reference-code-library' ),
                    'view_items'            => __( 'View Code Entries', 'reference-code-library' ),
                    'search_items'          => __( 'Search Code', 'reference-code-library' ),
                    'not_found'             => __( 'No code entries found.', 'reference-code-library' ),
                    'not_found_in_trash'    => __( 'No code entries found in Trash.', 'reference-code-library' ),
                    'all_items'             => __( 'All Code', 'reference-code-library' ),
                    'archives'              => __( 'Code Archives', 'reference-code-library' ),
                    'attributes'            => __( 'Code Attributes', 'reference-code-library' ),
                    'insert_into_item'      => __( 'Insert into code entry', 'reference-code-library' ),
                    'uploaded_to_this_item' => __( 'Uploaded to this code entry', 'reference-code-library' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => true,
                'show_in_rest' => true,
                'menu_icon'    => 'dashicons-editor-code',
                'supports'     => array( 'title', 'revisions', 'page-attributes' ),
                'taxonomies'   => array( self::TAX_COLLECTION, self::TAX_STATUS, self::TAX_TAG ),
                'rewrite'      => false,
                'query_var'    => false,
                'map_meta_cap' => true,
            )
        );

        register_taxonomy(
            self::TAX_COLLECTION,
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Collections', 'reference-code-library' ),
                    'singular_name' => __( 'Collection', 'reference-code-library' ),
                    'search_items'  => __( 'Search Collections', 'reference-code-library' ),
                    'all_items'     => __( 'All Collections', 'reference-code-library' ),
                    'edit_item'     => __( 'Edit Collection', 'reference-code-library' ),
                    'update_item'   => __( 'Update Collection', 'reference-code-library' ),
                    'add_new_item'  => __( 'Add New Collection', 'reference-code-library' ),
                    'new_item_name' => __( 'New Collection Name', 'reference-code-library' ),
                    'menu_name'     => __( 'Collections', 'reference-code-library' ),
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => true,
                'rewrite'           => false,
            )
        );

        register_taxonomy(
            self::TAX_STATUS,
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Statuses', 'reference-code-library' ),
                    'singular_name' => __( 'Status', 'reference-code-library' ),
                    'search_items'  => __( 'Search Statuses', 'reference-code-library' ),
                    'all_items'     => __( 'All Statuses', 'reference-code-library' ),
                    'edit_item'     => __( 'Edit Status', 'reference-code-library' ),
                    'update_item'   => __( 'Update Status', 'reference-code-library' ),
                    'add_new_item'  => __( 'Add New Status', 'reference-code-library' ),
                    'new_item_name' => __( 'New Status Name', 'reference-code-library' ),
                    'menu_name'     => __( 'Statuses', 'reference-code-library' ),
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => false,
            )
        );

        register_taxonomy(
            self::TAX_TAG,
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Code Tags', 'reference-code-library' ),
                    'singular_name' => __( 'Code Tag', 'reference-code-library' ),
                    'search_items'  => __( 'Search Code Tags', 'reference-code-library' ),
                    'all_items'     => __( 'All Code Tags', 'reference-code-library' ),
                    'edit_item'     => __( 'Edit Code Tag', 'reference-code-library' ),
                    'update_item'   => __( 'Update Code Tag', 'reference-code-library' ),
                    'add_new_item'  => __( 'Add New Code Tag', 'reference-code-library' ),
                    'new_item_name' => __( 'New Code Tag Name', 'reference-code-library' ),
                    'menu_name'     => __( 'Tags', 'reference-code-library' ),
                ),
                'public'            => false,
                'show_ui'           => true,
                'show_admin_column' => false,
                'show_in_rest'      => true,
                'hierarchical'      => false,
                'rewrite'           => false,
            )
        );

        $meta_fields = array(
            '_moa_source_key'           => 'string',
            '_moa_summary'              => 'string',
            '_moa_use_when'             => 'string',
            '_moa_implementation_notes' => 'string',
            '_moa_technology'           => 'string',
            '_moa_code_language'        => 'string',
            '_moa_code'                 => 'string',
            '_moa_source_url'           => 'string',
            '_moa_source_file'          => 'string', // Legacy data is preserved but no longer exposed in the editor.
        );

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta(
                self::POST_TYPE,
                $key,
                array(
                    'type'          => $type,
                    'single'        => true,
                    'show_in_rest'  => false,
                    'auth_callback' => static function() {
                        return current_user_can( 'edit_posts' );
                    },
                )
            );
        }
    }

    public function register_shortcodes() {
        add_shortcode( 'code_library', array( $this, 'shortcode_library' ) );
        add_shortcode( 'code_collection', array( $this, 'shortcode_collection' ) );
        add_shortcode( 'code_entry', array( $this, 'shortcode_entry' ) );

        // Backward compatibility with v1 pages.
        add_shortcode( 'mo_accessibility_library', array( $this, 'shortcode_library' ) );
        add_shortcode( 'mo_accessibility_collection', array( $this, 'shortcode_collection' ) );
        add_shortcode( 'mo_accessibility_pattern', array( $this, 'shortcode_entry' ) );
    }

    public function register_blocks() {
        wp_register_script(
            'rcl-block-editor',
            RCL_URL . 'assets/js/block-editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
            RCL_VERSION,
            true
        );

        $args = array(
            'api_version'     => 2,
            'editor_script'   => 'rcl-block-editor',
            'render_callback' => array( $this, 'render_block' ),
            'attributes'      => array(
                'collection' => array( 'type' => 'string', 'default' => '' ),
            ),
        );

        register_block_type( 'rcl/code-library', $args );

        // Existing v1 blocks still render, though only the new block appears in the inserter.
        $legacy_args = $args;
        unset( $legacy_args['editor_script'] );
        register_block_type( 'moa/accessibility-library', $legacy_args );
    }

    public function render_block( $attributes ) {
        $collection = isset( $attributes['collection'] ) ? sanitize_title( $attributes['collection'] ) : '';
        return $this->render_library( array( 'collection' => $collection ) );
    }

    public function register_frontend_assets() {
        wp_register_style(
            'reference-code-library',
            RCL_URL . 'assets/css/frontend.css',
            array(),
            RCL_VERSION
        );
        wp_register_script(
            'reference-code-library',
            RCL_URL . 'assets/js/frontend.js',
            array(),
            RCL_VERSION,
            true
        );

        if ( $this->page_uses_library() ) {
            $this->enqueue_frontend_assets();
        }
    }

    private function page_uses_library() {
        if ( ! is_singular() ) {
            return false;
        }

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) {
            return false;
        }

        $shortcodes = array(
            'code_library',
            'code_collection',
            'code_entry',
            'mo_accessibility_library',
            'mo_accessibility_collection',
            'mo_accessibility_pattern',
        );

        foreach ( $shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                return true;
            }
        }

        return has_block( 'rcl/code-library', $post->post_content )
            || has_block( 'moa/accessibility-library', $post->post_content );
    }

    private function enqueue_frontend_assets() {
        wp_enqueue_style( 'reference-code-library' );
        wp_enqueue_script( 'reference-code-library' );

        $settings = self::get_settings();
        $fonts    = self::get_resolved_font_stacks( $settings );
        $css      = sprintf(
            '.rcl-library{--rcl-primary:%1$s;--rcl-secondary:%2$s;--rcl-accent:%3$s;--rcl-focus:%4$s;--rcl-background:%5$s;--rcl-card:%6$s;--rcl-text:%7$s;--rcl-muted:%8$s;--rcl-border:%9$s;--rcl-content-width:%10$spx;--rcl-radius:%11$spx;--rcl-code-size:%12$spx;--rcl-font-body:%13$s;--rcl-font-heading:%14$s;--rcl-font-accent:%15$s;--rcl-font-code:%16$s;--rcl-base-size:%17$spx;--rcl-line-height:%18$s;--rcl-content-align:%19$s;--rcl-hero-align:%20$s;--rcl-card-align:%21$s;}',
            $settings['primary_color'],
            $settings['secondary_color'],
            $settings['accent_color'],
            $settings['focus_color'],
            $settings['background_color'],
            $settings['card_color'],
            $settings['text_color'],
            $settings['muted_color'],
            $settings['border_color'],
            (int) $settings['content_width'],
            (int) $settings['border_radius'],
            (int) $settings['code_font_size'],
            $fonts['body'],
            $fonts['heading'],
            $fonts['accent'],
            $fonts['code'],
            (int) $settings['base_font_size'],
            (float) $settings['line_height'],
            $settings['content_alignment'],
            $settings['hero_alignment'],
            $settings['card_alignment']
        );
        wp_add_inline_style( 'reference-code-library', $css );

        if ( '1' === (string) $settings['custom_css_enabled'] && '' !== trim( $settings['custom_css'] ) ) {
            wp_add_inline_style( 'reference-code-library', $settings['custom_css'] );
        }
    }

    public static function get_default_settings() {
        return array(
            'title'                        => 'Reference Code Library',
            'eyebrow'                      => 'Reusable code, documented with context',
            'intro'                        => 'A searchable library for code examples, implementation guidance, testing notes, and reusable solutions.',
            'logo_id'                      => 0,
            'logo_alt'                     => '',
            'legacy_logo_url'              => '',
            'primary_color'                => '#203a5f',
            'secondary_color'              => '#425b78',
            'accent_color'                 => '#5f9bbc',
            'focus_color'                  => '#ffcc05',
            'background_color'             => '#f3f6f8',
            'card_color'                   => '#ffffff',
            'text_color'                   => '#303942',
            'muted_color'                  => '#5f6b76',
            'border_color'                 => '#cad4dd',
            'content_width'                => 1216,
            'border_radius'                => 4,
            'code_font_size'               => 14,
            'base_font_size'               => 16,
            'line_height'                  => 1.6,
            'layout_preset'                => 'classic',
            'typography_mode'              => 'plugin',
            'body_font_preset'             => 'tahoma',
            'heading_font_preset'          => 'arial_narrow',
            'accent_font_preset'           => 'georgia',
            'code_font_preset'             => 'consolas',
            'body_font_custom'             => '',
            'heading_font_custom'          => '',
            'accent_font_custom'           => '',
            'code_font_custom'             => '',
            'content_alignment'            => 'start',
            'hero_alignment'               => 'start',
            'card_alignment'               => 'start',
            'style_isolation'               => 'standard',
            'custom_css'                   => '',
            'custom_css_backup'            => '',
            'custom_css_enabled'           => '0',
            'show_hero'                    => '1',
            'show_stats'                   => '1',
            'show_start_here'              => '1',
            'show_collection_descriptions' => '1',
        );
    }

    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_default_settings() );
    }

    public static function get_font_options( $group = 'body' ) {
        $common = array(
            'system'       => __( 'System UI', 'reference-code-library' ),
            'arial'        => __( 'Arial / Helvetica', 'reference-code-library' ),
            'tahoma'       => __( 'Tahoma / Verdana', 'reference-code-library' ),
            'trebuchet'    => __( 'Trebuchet MS', 'reference-code-library' ),
            'georgia'      => __( 'Georgia', 'reference-code-library' ),
            'times'        => __( 'Times New Roman', 'reference-code-library' ),
            'arial_narrow' => __( 'Arial Narrow / Arial', 'reference-code-library' ),
            'custom'       => __( 'Custom font stack', 'reference-code-library' ),
        );

        if ( 'code' === $group ) {
            return array(
                'consolas'   => __( 'Consolas / Liberation Mono', 'reference-code-library' ),
                'monospace'  => __( 'System monospace', 'reference-code-library' ),
                'courier'    => __( 'Courier New', 'reference-code-library' ),
                'menlo'      => __( 'Menlo / Monaco', 'reference-code-library' ),
                'custom'     => __( 'Custom monospace stack', 'reference-code-library' ),
            );
        }

        return $common;
    }

    private static function get_font_stack_map() {
        return array(
            'system'       => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
            'arial'        => 'Arial,Helvetica,sans-serif',
            'tahoma'       => 'Tahoma,Verdana,Arial,sans-serif',
            'trebuchet'    => '"Trebuchet MS",Tahoma,Arial,sans-serif',
            'georgia'      => 'Georgia,"Times New Roman",serif',
            'times'        => '"Times New Roman",Times,serif',
            'arial_narrow' => '"Arial Narrow",Arial,sans-serif',
            'consolas'     => 'Consolas,"Liberation Mono",Menlo,monospace',
            'monospace'    => 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace',
            'courier'      => '"Courier New",Courier,monospace',
            'menlo'        => 'Menlo,Monaco,Consolas,"Liberation Mono",monospace',
        );
    }

    public static function sanitize_font_stack( $value ) {
        $value = sanitize_text_field( (string) $value );
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        if ( strlen( $value ) > 300 || ! preg_match( '/^[a-zA-Z0-9_.\-\s,"\']+$/', $value ) ) {
            return '';
        }

        return $value;
    }

    public static function sanitize_custom_css( $value ) {
        $value = (string) $value;
        $value = str_replace( "\0", '', $value );
        $value = preg_replace( '#<\s*/?\s*style\b[^>]*>#i', '', $value );
        $value = preg_replace( '#<\s*/?\s*style\b#i', '', $value );
        $value = trim( $value );

        if ( strlen( $value ) > 50000 ) {
            $value = substr( $value, 0, 50000 );
        }

        return $value;
    }

    public static function get_resolved_font_stacks( $settings = null ) {
        $defaults = self::get_default_settings();
        $settings = is_array( $settings ) ? wp_parse_args( $settings, $defaults ) : self::get_settings();
        $map      = self::get_font_stack_map();

        if ( 'inherit' === $settings['typography_mode'] ) {
            return array(
                'body'    => 'inherit',
                'heading' => 'inherit',
                'accent'  => 'inherit',
                'code'    => $map['consolas'],
            );
        }

        if ( 'plugin' === $settings['typography_mode'] ) {
            return array(
                'body'    => $map[ $defaults['body_font_preset'] ],
                'heading' => $map[ $defaults['heading_font_preset'] ],
                'accent'  => $map[ $defaults['accent_font_preset'] ],
                'code'    => $map[ $defaults['code_font_preset'] ],
            );
        }

        $resolved = array();
        foreach ( array( 'body', 'heading', 'accent', 'code' ) as $group ) {
            $preset_key = $group . '_font_preset';
            $custom_key = $group . '_font_custom';
            $preset     = $settings[ $preset_key ] ?? $defaults[ $preset_key ];

            if ( 'custom' === $preset ) {
                $custom = self::sanitize_font_stack( $settings[ $custom_key ] ?? '' );
                if ( $custom ) {
                    $resolved[ $group ] = $custom;
                    continue;
                }
                $preset = $defaults[ $preset_key ];
            }

            $resolved[ $group ] = $map[ $preset ] ?? $map[ $defaults[ $preset_key ] ];
        }

        return $resolved;
    }

    public static function sanitize_settings( $input ) {
        $defaults = self::get_default_settings();
        $input    = is_array( $input ) ? $input : array();
        $current  = get_option( self::OPTION_KEY, array() );
        $current  = is_array( $current ) ? wp_parse_args( $current, $defaults ) : $defaults;
        $output   = array();

        $output['title'] = sanitize_text_field( $input['title'] ?? $defaults['title'] );
        if ( ! $output['title'] ) {
            $output['title'] = $defaults['title'];
        }
        $output['eyebrow']         = sanitize_text_field( $input['eyebrow'] ?? $defaults['eyebrow'] );
        $output['intro']           = sanitize_textarea_field( $input['intro'] ?? $defaults['intro'] );
        $output['logo_id']         = absint( $input['logo_id'] ?? 0 );
        $output['logo_alt']        = sanitize_text_field( $input['logo_alt'] ?? '' );
        $output['legacy_logo_url'] = esc_url_raw( $input['legacy_logo_url'] ?? '' );

        foreach ( array( 'primary_color', 'secondary_color', 'accent_color', 'focus_color', 'background_color', 'card_color', 'text_color', 'muted_color', 'border_color' ) as $key ) {
            $output[ $key ] = sanitize_hex_color( $input[ $key ] ?? '' ) ?: $defaults[ $key ];
        }

        $output['content_width']  = min( 1920, max( 640, absint( $input['content_width'] ?? $defaults['content_width'] ) ) );
        $output['border_radius']  = min( 32, absint( $input['border_radius'] ?? $defaults['border_radius'] ) );
        $output['code_font_size'] = min( 24, max( 11, absint( $input['code_font_size'] ?? $defaults['code_font_size'] ) ) );
        $output['base_font_size'] = min( 24, max( 12, absint( $input['base_font_size'] ?? $defaults['base_font_size'] ) ) );
        $line_height              = (float) ( $input['line_height'] ?? $defaults['line_height'] );
        $output['line_height']    = min( 2.5, max( 1.2, round( $line_height, 2 ) ) );

        $allowed_layouts          = array( 'classic', 'minimal', 'documentation' );
        $layout                   = sanitize_key( $input['layout_preset'] ?? $defaults['layout_preset'] );
        $output['layout_preset']  = in_array( $layout, $allowed_layouts, true ) ? $layout : $defaults['layout_preset'];

        $allowed_typography       = array( 'plugin', 'inherit', 'custom' );
        $typography               = sanitize_key( $input['typography_mode'] ?? $defaults['typography_mode'] );
        $output['typography_mode'] = in_array( $typography, $allowed_typography, true ) ? $typography : $defaults['typography_mode'];

        foreach ( array( 'body', 'heading', 'accent', 'code' ) as $group ) {
            $preset_key = $group . '_font_preset';
            $custom_key = $group . '_font_custom';
            $options    = self::get_font_options( $group );
            $preset     = sanitize_key( $input[ $preset_key ] ?? $defaults[ $preset_key ] );
            $output[ $preset_key ] = isset( $options[ $preset ] ) ? $preset : $defaults[ $preset_key ];
            $output[ $custom_key ] = self::sanitize_font_stack( $input[ $custom_key ] ?? '' );
        }

        $allowed_alignments = array( 'start', 'center' );
        foreach ( array( 'content_alignment', 'hero_alignment', 'card_alignment' ) as $key ) {
            $alignment     = sanitize_key( $input[ $key ] ?? $defaults[ $key ] );
            $output[ $key ] = in_array( $alignment, $allowed_alignments, true ) ? $alignment : $defaults[ $key ];
        }

        $allowed_isolation          = array( 'standard', 'relaxed' );
        $isolation                  = sanitize_key( $input['style_isolation'] ?? $defaults['style_isolation'] );
        $output['style_isolation']  = in_array( $isolation, $allowed_isolation, true ) ? $isolation : $defaults['style_isolation'];

        $new_css = self::sanitize_custom_css( $input['custom_css'] ?? '' );
        $old_css = self::sanitize_custom_css( $current['custom_css'] ?? '' );
        $backup  = self::sanitize_custom_css( $current['custom_css_backup'] ?? '' );
        if ( $new_css !== $old_css ) {
            $backup = $old_css;
        }
        $output['custom_css']         = $new_css;
        $output['custom_css_backup']  = $backup;
        $output['custom_css_enabled'] = ! empty( $input['custom_css_enabled'] ) ? '1' : '0';

        foreach ( array( 'show_hero', 'show_stats', 'show_start_here', 'show_collection_descriptions' ) as $key ) {
            $output[ $key ] = ! empty( $input[ $key ] ) ? '1' : '0';
        }

        return $output;
    }

    public static function get_example_types() {
        return array(
            'before'        => __( 'Before', 'reference-code-library' ),
            'after'         => __( 'After', 'reference-code-library' ),
            'result'        => __( 'Working result', 'reference-code-library' ),
            'configuration' => __( 'Configuration', 'reference-code-library' ),
            'inspector'     => __( 'Inspector / developer tools', 'reference-code-library' ),
            'mobile'        => __( 'Mobile / zoomed view', 'reference-code-library' ),
            'test'          => __( 'Accessibility test result', 'reference-code-library' ),
            'other'         => __( 'Other example', 'reference-code-library' ),
        );
    }

    public static function sanitize_example_type( $type ) {
        $type  = sanitize_key( $type );
        $types = self::get_example_types();
        return isset( $types[ $type ] ) ? $type : 'result';
    }

    public static function sanitize_examples( $examples ) {
        if ( ! is_array( $examples ) ) {
            return array();
        }

        $clean = array();
        foreach ( array_slice( $examples, 0, 20 ) as $index => $example ) {
            if ( ! is_array( $example ) ) {
                continue;
            }

            $attachment_id = absint( $example['attachment_id'] ?? 0 );
            if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
                continue;
            }

            $clean[] = array(
                'attachment_id' => $attachment_id,
                'type'          => self::sanitize_example_type( $example['type'] ?? 'result' ),
                'alt'           => sanitize_text_field( $example['alt'] ?? '' ),
                'caption'       => sanitize_textarea_field( $example['caption'] ?? '' ),
                'order'         => (int) ( $example['order'] ?? ( ( $index + 1 ) * 10 ) ),
            );
        }

        usort(
            $clean,
            static function( $a, $b ) {
                return (int) $a['order'] <=> (int) $b['order'];
            }
        );

        foreach ( $clean as $index => &$example ) {
            $example['order'] = ( $index + 1 ) * 10;
        }
        unset( $example );

        return $clean;
    }

    public function shortcode_library( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'collection' => '',
                'entry'      => '',
                'pattern'    => '',
            ),
            $atts,
            'code_library'
        );
        if ( empty( $atts['entry'] ) && ! empty( $atts['pattern'] ) ) {
            $atts['entry'] = $atts['pattern'];
        }
        return $this->render_library( $atts );
    }

    public function shortcode_collection( $atts = array() ) {
        $atts = shortcode_atts( array( 'slug' => '' ), $atts, 'code_collection' );
        return $this->render_library( array( 'collection' => sanitize_title( $atts['slug'] ) ) );
    }

    public function shortcode_entry( $atts = array() ) {
        $atts = shortcode_atts( array( 'slug' => '' ), $atts, 'code_entry' );
        return $this->render_library( array( 'entry' => sanitize_title( $atts['slug'] ) ) );
    }

    public function render_library( $atts = array() ) {
        $this->enqueue_frontend_assets();

        global $post;
        $this->active_page_id = $post instanceof WP_Post ? (int) $post->ID : 0;

        $entry      = ! empty( $atts['entry'] ) ? sanitize_title( $atts['entry'] ) : '';
        $collection = ! empty( $atts['collection'] ) ? sanitize_title( $atts['collection'] ) : '';
        $view       = '';
        $query      = '';

        if ( isset( $_GET['rcl_entry'] ) ) {
            $entry = sanitize_title( wp_unslash( $_GET['rcl_entry'] ) );
        } elseif ( isset( $_GET['moa_pattern'] ) ) {
            $entry = sanitize_title( wp_unslash( $_GET['moa_pattern'] ) );
        }

        if ( isset( $_GET['rcl_collection'] ) ) {
            $collection = sanitize_title( wp_unslash( $_GET['rcl_collection'] ) );
        } elseif ( isset( $_GET['moa_collection'] ) ) {
            $collection = sanitize_title( wp_unslash( $_GET['moa_collection'] ) );
        }

        if ( isset( $_GET['rcl_view'] ) ) {
            $view = sanitize_key( wp_unslash( $_GET['rcl_view'] ) );
        } elseif ( isset( $_GET['moa_view'] ) ) {
            $view = sanitize_key( wp_unslash( $_GET['moa_view'] ) );
        }

        if ( isset( $_GET['rcl_q'] ) ) {
            $query = sanitize_text_field( wp_unslash( $_GET['rcl_q'] ) );
        } elseif ( isset( $_GET['moa_q'] ) ) {
            $query = sanitize_text_field( wp_unslash( $_GET['moa_q'] ) );
        }

        $settings = self::get_settings();

        ob_start();
        echo '<div class="rcl-library rcl-layout-' . esc_attr( $settings['layout_preset'] ) . ' rcl-typography-' . esc_attr( $settings['typography_mode'] ) . ' rcl-isolation-' . esc_attr( $settings['style_isolation'] ) . '" data-rcl-library>';
        echo '<div class="rcl-library__live" aria-live="polite" aria-atomic="true"></div>';

        if ( $entry ) {
            $this->render_entry_view( $entry );
        } elseif ( $query ) {
            $this->render_search_results( $query, $collection );
        } elseif ( $collection ) {
            $this->render_collection_view( $collection );
        } elseif ( 'library' === $view ) {
            $this->render_library_overview();
        } else {
            $this->render_home();
        }

        echo '</div>';
        return ob_get_clean();
    }

    private function base_url() {
        if ( $this->active_page_id ) {
            return get_permalink( $this->active_page_id );
        }
        return home_url( '/' );
    }

    private function library_url( $args = array() ) {
        $remove = array( 'rcl_view', 'rcl_collection', 'rcl_entry', 'rcl_q', 'moa_view', 'moa_collection', 'moa_pattern', 'moa_q' );
        $base   = remove_query_arg( $remove, $this->base_url() );
        return $args ? add_query_arg( $args, $base ) : $base;
    }

    private function render_home() {
        $settings = self::get_settings();
        $counts   = $this->get_library_counts();

        if ( '1' === (string) $settings['show_hero'] ) {
            $logo = '';
            if ( $settings['logo_id'] ) {
                $logo = wp_get_attachment_image_url( (int) $settings['logo_id'], 'large' );
            }
            if ( ! $logo && ! empty( $settings['legacy_logo_url'] ) ) {
                $logo = $settings['legacy_logo_url'];
            }

            echo '<section class="rcl-hero' . ( $logo ? '' : ' rcl-hero--no-logo' ) . '" aria-labelledby="rcl-library-title">';
            if ( $logo ) {
                $alt = $settings['logo_alt'] ?: $settings['title'];
                echo '<div class="rcl-hero__brand"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $alt ) . '"></div>';
            }
            echo '<div class="rcl-hero__content">';
            echo '<p class="rcl-eyebrow">' . esc_html( $settings['eyebrow'] ) . '</p>';
            echo '<h2 id="rcl-library-title">' . esc_html( $settings['title'] ) . '</h2>';
            echo '<p>' . esc_html( $settings['intro'] ) . '</p>';
            echo '<div class="rcl-hero__tags" aria-label="Library features"><span>Searchable</span><span>Reusable</span><span>Portable</span></div>';
            echo '<div class="rcl-hero__actions"><a class="rcl-button" href="' . esc_url( $this->library_url( array( 'rcl_view' => 'library' ) ) ) . '">Browse the code library</a></div>';
            echo '</div></section>';
        } else {
            echo '<header class="rcl-page-header"><p class="rcl-eyebrow">' . esc_html( $settings['eyebrow'] ) . '</p><h2>' . esc_html( $settings['title'] ) . '</h2><p>' . esc_html( $settings['intro'] ) . '</p></header>';
        }

        if ( '1' === (string) $settings['show_stats'] ) {
            echo '<div class="rcl-stat-strip" aria-label="Current library totals">';
            $this->stat( $counts['entries'], 'code entries' );
            $this->stat( $counts['collections'], 'collections' );
            $this->stat( $counts['languages'], 'languages' );
            $this->stat( $counts['statuses'], 'statuses' );
            echo '</div>';
        }

        if ( '1' === (string) $settings['show_start_here'] ) {
            echo '<section class="rcl-section" aria-labelledby="rcl-start-h"><h2 id="rcl-start-h">Start here</h2><div class="rcl-card-grid">';
            $this->info_card( 'Explore', 'Browse by collection', 'Open a focused collection and move through individual code entries instead of hunting through one endless page.', $this->library_url( array( 'rcl_view' => 'library' ) ), 'Open the library overview', true );
            $this->info_card( 'Understand', 'Read the context first', 'Each entry can explain what the code changes, when to use it, and what deserves extra testing.' );
            $this->info_card( 'Apply carefully', 'Copy, test, and adapt', 'Stored code is displayed as reference material. The plugin never executes it, and each example should be tested in its target environment.' );
            echo '</div></section>';
        }

        echo '<section class="rcl-section" aria-labelledby="rcl-collections-h"><h2 id="rcl-collections-h">Browse the current collections</h2>';
        $this->render_collection_cards();
        echo '</section>';

        echo '<section class="rcl-section" aria-labelledby="rcl-entry-guide-h"><h2 id="rcl-entry-guide-h">What each entry tells you</h2><div class="rcl-process-grid">';
        $this->process_card( 'A', 'What it changes', 'A plain-language summary of the component, behavior, defect, or solution involved.' );
        $this->process_card( 'B', 'When to use it', 'The conditions where the code is useful, rather than pretending every snippet belongs everywhere.' );
        $this->process_card( 'C', 'What to verify', 'Implementation notes can flag assumptions, selectors, dependencies, and testing risks.' );
        echo '</div></section>';

        echo '<aside class="rcl-notice rcl-notice--warning"><strong>Reference only:</strong> Stored code is never executed by this plugin. Review, adapt, and test each entry before using it in production.</aside>';
    }

    private function render_library_overview() {
        $this->render_back_link( 'Return to the library home', $this->library_url() );
        echo '<header class="rcl-page-header"><p class="rcl-eyebrow">Code library</p><h2>Browse by collection</h2><p>Choose a collection, search the library, or open an entry to review its context and code.</p></header>';
        $this->render_search_form();
        $this->render_collection_cards();
    }

    private function get_collection_terms() {
        $terms = get_terms(
            array(
                'taxonomy'   => self::TAX_COLLECTION,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        // Terms without an order can be returned inconsistently. Stabilize the final list.
        usort(
            $terms,
            static function( $a, $b ) {
                $order_a = (int) get_term_meta( $a->term_id, '_moa_order', true );
                $order_b = (int) get_term_meta( $b->term_id, '_moa_order', true );
                if ( $order_a === $order_b ) {
                    return strcasecmp( $a->name, $b->name );
                }
                return $order_a <=> $order_b;
            }
        );

        return $terms;
    }

    private function render_collection_cards() {
        $terms    = $this->get_collection_terms();
        $settings = self::get_settings();

        if ( ! $terms ) {
            echo '<div class="rcl-empty"><p>No collections are available yet.</p></div>';
            return;
        }

        echo '<div class="rcl-library-grid">';
        foreach ( $terms as $term ) {
            $icon  = get_term_meta( $term->term_id, '_moa_icon', true );
            $class = get_term_meta( $term->term_id, '_moa_class', true );
            $url   = $this->library_url( array( 'rcl_collection' => $term->slug ) );
            echo '<a class="rcl-library-card rcl-library-card--' . esc_attr( sanitize_html_class( $class ?: $term->slug ) ) . '" href="' . esc_url( $url ) . '">';
            echo '<span class="rcl-library-card__icon" aria-hidden="true">' . esc_html( $icon ?: strtoupper( substr( $term->name, 0, 3 ) ) ) . '</span>';
            echo '<span class="rcl-library-card__body"><strong>' . esc_html( $term->name ) . '</strong>';
            if ( '1' === (string) $settings['show_collection_descriptions'] && $term->description ) {
                echo '<span>' . esc_html( $term->description ) . '</span>';
            }
            echo '<span class="rcl-library-card__count">' . esc_html( (string) $term->count ) . ' ' . esc_html( 1 === (int) $term->count ? 'entry' : 'entries' ) . '</span></span>';
            echo '</a>';
        }
        echo '</div>';
    }

    private function render_collection_view( $slug ) {
        $term = get_term_by( 'slug', $slug, self::TAX_COLLECTION );
        if ( ! $term || is_wp_error( $term ) ) {
            $this->not_found( 'Collection not found', 'The requested collection does not exist.' );
            return;
        }

        $this->render_back_link( 'All collections', $this->library_url( array( 'rcl_view' => 'library' ) ) );
        echo '<header class="rcl-page-header"><p class="rcl-eyebrow">Collection</p><h2>' . esc_html( $term->name ) . '</h2>';
        if ( $term->description ) {
            echo '<p>' . esc_html( $term->description ) . '</p>';
        }
        echo '</header>';
        $this->render_search_form( $term->slug );

        $posts = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
                'tax_query'      => array(
                    array(
                        'taxonomy' => self::TAX_COLLECTION,
                        'field'    => 'term_id',
                        'terms'    => array( $term->term_id ),
                    ),
                ),
            )
        );
        $this->render_entry_cards( $posts );
    }

    private function render_search_results( $query, $collection = '' ) {
        $this->render_back_link( 'Return to the library home', $this->library_url() );
        echo '<header class="rcl-page-header"><p class="rcl-eyebrow">Search</p><h2>Results for “' . esc_html( $query ) . '”</h2></header>';
        $this->render_search_form( $collection, $query );

        $ids = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                's'              => $query,
                'tax_query'      => $collection ? array( array(
                    'taxonomy' => self::TAX_COLLECTION,
                    'field'    => 'slug',
                    'terms'    => $collection,
                ) ) : array(),
            )
        );

        $meta_ids = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'OR',
                    array( 'key' => '_moa_summary', 'value' => $query, 'compare' => 'LIKE' ),
                    array( 'key' => '_moa_use_when', 'value' => $query, 'compare' => 'LIKE' ),
                    array( 'key' => '_moa_implementation_notes', 'value' => $query, 'compare' => 'LIKE' ),
                    array( 'key' => '_moa_technology', 'value' => $query, 'compare' => 'LIKE' ),
                    array( 'key' => '_moa_code_language', 'value' => $query, 'compare' => 'LIKE' ),
                    array( 'key' => '_moa_code', 'value' => $query, 'compare' => 'LIKE' ),
                ),
                'tax_query'      => $collection ? array( array(
                    'taxonomy' => self::TAX_COLLECTION,
                    'field'    => 'slug',
                    'terms'    => $collection,
                ) ) : array(),
            )
        );

        $ids   = array_values( array_unique( array_merge( $ids, $meta_ids ) ) );
        $posts = array();
        if ( $ids ) {
            $posts = get_posts(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'post__in'       => $ids,
                    'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
                )
            );
        }

        $this->render_entry_cards( $posts, 'No matching code entries were found.' );
    }

    private function render_entry_cards( $posts, $empty_message = 'No code entries are available in this collection yet.' ) {
        if ( ! $posts ) {
            echo '<div class="rcl-empty"><p>' . esc_html( $empty_message ) . '</p></div>';
            return;
        }

        echo '<div class="rcl-entry-grid">';
        foreach ( $posts as $item ) {
            $slug       = get_post_meta( $item->ID, '_moa_source_key', true );
            $slug       = $slug ?: $item->post_name;
            $summary    = get_post_meta( $item->ID, '_moa_summary', true );
            $technology = get_post_meta( $item->ID, '_moa_technology', true );
            $status     = $this->get_status_name( $item->ID );
            $url        = $this->library_url( array( 'rcl_entry' => $slug ) );

            echo '<article class="rcl-entry-card">';
            echo '<div class="rcl-meta-row">';
            if ( $technology ) {
                echo '<span class="rcl-chip">' . esc_html( $technology ) . '</span>';
            }
            echo $this->status_chip( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            echo '<h3><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $item ) ) . '</a></h3>';
            if ( $summary ) {
                echo '<p>' . esc_html( $summary ) . '</p>';
            }
            echo '<p class="rcl-card-link"><a href="' . esc_url( $url ) . '">Open code details<span class="screen-reader-text"> for ' . esc_html( get_the_title( $item ) ) . '</span></a></p>';
            echo '</article>';
        }
        echo '</div>';
    }

    private function render_entry_view( $slug ) {
        $posts = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_key'       => '_moa_source_key',
                'meta_value'     => $slug,
            )
        );

        if ( ! $posts ) {
            $posts = get_posts(
                array(
                    'post_type'      => self::POST_TYPE,
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'name'           => $slug,
                )
            );
        }

        if ( ! $posts ) {
            $this->not_found( 'Code entry not found', 'The requested code entry does not exist.' );
            return;
        }

        $item        = $posts[0];
        $collections = wp_get_post_terms( $item->ID, self::TAX_COLLECTION );
        $collection  = $collections && ! is_wp_error( $collections ) ? $collections[0] : null;
        $back_url    = $collection ? $this->library_url( array( 'rcl_collection' => $collection->slug ) ) : $this->library_url( array( 'rcl_view' => 'library' ) );
        $back_text   = $collection ? 'Back to ' . $collection->name : 'Back to all collections';
        $this->render_back_link( $back_text, $back_url );

        $summary    = get_post_meta( $item->ID, '_moa_summary', true );
        $technology = get_post_meta( $item->ID, '_moa_technology', true );
        $use_when   = get_post_meta( $item->ID, '_moa_use_when', true );
        $notes      = get_post_meta( $item->ID, '_moa_implementation_notes', true );
        $language   = get_post_meta( $item->ID, '_moa_code_language', true );
        $code       = get_post_meta( $item->ID, '_moa_code', true );
        $source_url = get_post_meta( $item->ID, '_moa_source_url', true );
        $status     = $this->get_status_name( $item->ID );
        $tags       = wp_get_post_terms( $item->ID, self::TAX_TAG );

        echo '<article class="rcl-entry-detail">';
        echo '<header class="rcl-page-header"><p class="rcl-eyebrow">Code entry</p><h2>' . esc_html( get_the_title( $item ) ) . '</h2>';
        if ( $summary ) {
            echo '<p class="rcl-page-lead">' . esc_html( $summary ) . '</p>';
        }
        echo '<div class="rcl-meta-row">';
        if ( $technology ) {
            echo '<span class="rcl-chip">' . esc_html( $technology ) . '</span>';
        }
        echo $this->status_chip( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ( $tags && ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                echo '<span class="rcl-chip rcl-chip--tag">' . esc_html( $tag->name ) . '</span>';
            }
        }
        echo '</div></header>';

        if ( $use_when || $notes ) {
            echo '<div class="rcl-detail-grid">';
            if ( $use_when ) {
                echo '<section class="rcl-detail"><h3>Use it when</h3><p>' . esc_html( $use_when ) . '</p></section>';
            }
            if ( $notes ) {
                echo '<section class="rcl-detail"><h3>Implementation notes</h3>';
                echo wpautop( esc_html( $notes ) );
                echo '</section>';
            }
            echo '</div>';
        }

        $examples = self::sanitize_examples( get_post_meta( $item->ID, self::META_EXAMPLES, true ) );
        if ( $examples ) {
            $types = self::get_example_types();
            echo '<section class="rcl-examples" aria-labelledby="rcl-examples-heading-' . (int) $item->ID . '">';
            echo '<h3 id="rcl-examples-heading-' . (int) $item->ID . '">' . esc_html__( 'Working examples', 'reference-code-library' ) . '</h3>';
            echo '<div class="rcl-example-grid">';
            foreach ( $examples as $example ) {
                $attachment_id = (int) $example['attachment_id'];
                $full_url      = wp_get_attachment_url( $attachment_id );
                if ( ! $full_url ) {
                    continue;
                }

                $type_label = $types[ $example['type'] ] ?? $types['result'];
                $link_label = $example['caption']
                    ? sprintf( __( 'Open full-size example: %s', 'reference-code-library' ), $example['caption'] )
                    : sprintf( __( 'Open full-size %s image for %s', 'reference-code-library' ), strtolower( $type_label ), get_the_title( $item ) );

                echo '<figure class="rcl-example">';
                echo '<div class="rcl-example__image-wrap">';
                echo wp_get_attachment_image(
                    $attachment_id,
                    'large',
                    false,
                    array(
                        'class'   => 'rcl-example__image',
                        'alt'     => $example['alt'],
                        'loading' => 'lazy',
                    )
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '</div>';
                echo '<figcaption><span class="rcl-example__type">' . esc_html( $type_label ) . '</span>';
                if ( $example['caption'] ) {
                    echo '<span class="rcl-example__caption">' . esc_html( $example['caption'] ) . '</span>';
                }
                echo '<a class="rcl-example__full-link" href="' . esc_url( $full_url ) . '" aria-label="' . esc_attr( $link_label ) . '">' . esc_html__( 'Open full-size example', 'reference-code-library' ) . '</a>';
                echo '</figcaption></figure>';
            }
            echo '</div></section>';
        }

        if ( $code ) {
            $code_id = 'rcl-code-' . (int) $item->ID;
            echo '<details class="rcl-code-details"><summary>View and copy the code</summary><div class="rcl-code-toolbar"><span>' . esc_html( strtoupper( $language ?: 'CODE' ) ) . '</span><button type="button" class="rcl-copy-button" data-copy-target="' . esc_attr( $code_id ) . '">Copy code</button></div><pre><code id="' . esc_attr( $code_id ) . '" class="language-' . esc_attr( sanitize_html_class( $language ?: 'text' ) ) . '">' . esc_html( $code ) . '</code></pre></details>';
        }

        if ( $source_url ) {
            echo '<p><a class="rcl-source-link" href="' . esc_url( $source_url ) . '">Open the reference source</a></p>';
        }
        echo '</article>';
    }

    private function render_search_form( $collection = '', $value = '' ) {
        echo '<form class="rcl-search" method="get" action="' . esc_url( $this->base_url() ) . '">';
        echo '<label for="rcl-library-search">Search the code library</label><div class="rcl-search__row"><input id="rcl-library-search" name="rcl_q" type="search" value="' . esc_attr( $value ) . '" autocomplete="off"><button type="submit">Search</button></div>';
        if ( $collection ) {
            echo '<input type="hidden" name="rcl_collection" value="' . esc_attr( $collection ) . '">';
        }
        echo '</form>';
    }

    private function render_back_link( $text, $url ) {
        echo '<nav class="rcl-back" aria-label="Code library navigation"><a href="' . esc_url( $url ) . '">← ' . esc_html( $text ) . '</a></nav>';
    }

    private function get_library_counts() {
        $entries     = wp_count_posts( self::POST_TYPE );
        $entries     = isset( $entries->publish ) ? (int) $entries->publish : 0;
        $collections = wp_count_terms( array( 'taxonomy' => self::TAX_COLLECTION, 'hide_empty' => false ) );
        $statuses    = wp_count_terms( array( 'taxonomy' => self::TAX_STATUS, 'hide_empty' => false ) );

        $languages = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            )
        );
        $language_values = array();
        foreach ( $languages as $post_id ) {
            $value = strtolower( trim( (string) get_post_meta( $post_id, '_moa_code_language', true ) ) );
            if ( $value ) {
                $language_values[] = $value;
            }
        }

        return array(
            'entries'     => $entries,
            'collections' => is_wp_error( $collections ) ? 0 : (int) $collections,
            'languages'   => count( array_unique( $language_values ) ),
            'statuses'    => is_wp_error( $statuses ) ? 0 : (int) $statuses,
        );
    }

    private function stat( $value, $label ) {
        echo '<div><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
    }

    private function info_card( $label, $title, $text, $url = '', $link_text = '', $feature = false ) {
        echo '<article class="rcl-card' . ( $feature ? ' rcl-card--feature' : '' ) . '"><span class="rcl-card__label">' . esc_html( $label ) . '</span><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p>';
        if ( $url && $link_text ) {
            echo '<p><a href="' . esc_url( $url ) . '">' . esc_html( $link_text ) . '</a></p>';
        }
        echo '</article>';
    }

    private function process_card( $number, $title, $text ) {
        echo '<article class="rcl-process-card"><span class="rcl-process-card__number" aria-hidden="true">' . esc_html( $number ) . '</span><h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p></article>';
    }

    private function get_status_name( $post_id ) {
        $terms = wp_get_post_terms( $post_id, self::TAX_STATUS );
        return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : 'Working reference';
    }

    private function status_chip( $status ) {
        $lower = strtolower( $status );
        $class = 'rcl-chip--good';
        if ( false !== strpos( $lower, 'deprecated' ) || false !== strpos( $lower, 'caution' ) || false !== strpos( $lower, 'correction' ) || false !== strpos( $lower, 'retired' ) ) {
            $class = 'rcl-chip--danger';
        } elseif ( false !== strpos( $lower, 'experimental' ) || false !== strpos( $lower, 'review' ) || false !== strpos( $lower, 'draft' ) ) {
            $class = 'rcl-chip--warning';
        }
        return '<span class="rcl-chip ' . esc_attr( $class ) . '">' . esc_html( $status ) . '</span>';
    }

    private function not_found( $title, $message ) {
        $this->render_back_link( 'Return to the library home', $this->library_url() );
        echo '<div class="rcl-empty"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $message ) . '</p></div>';
    }

    public function add_meta_boxes() {
        add_meta_box(
            'rcl-code-details',
            __( 'Code Details', 'reference-code-library' ),
            array( $this, 'render_entry_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'rcl-working-examples',
            __( 'Working Examples', 'reference-code-library' ),
            array( $this, 'render_examples_meta_box' ),
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_entry_meta_box( $post ) {
        wp_nonce_field( 'rcl_save_entry', 'rcl_entry_nonce' );

        $fields = array(
            '_moa_summary'              => array( 'Summary', 'textarea', 'Explain what this entry changes or solves.' ),
            '_moa_use_when'             => array( 'Use it when', 'textarea', 'Describe the conditions where this code is appropriate.' ),
            '_moa_implementation_notes' => array( 'Implementation notes', 'textarea', 'Document assumptions, selectors, dependencies, risks, and testing steps.' ),
            '_moa_technology'           => array( 'Technology', 'text', 'Examples: CSS, WordPress/PHP, JavaScript, HTML, SQL.' ),
            '_moa_code_language'        => array( 'Code language', 'text', 'Use a short language identifier such as css, php, js, html, json, or text.' ),
            '_moa_code'                 => array( 'Code', 'code', 'Stored as inert reference text. This plugin never executes it.' ),
            '_moa_source_url'           => array( 'Reference URL', 'url', 'Optional link to documentation, an issue, a repository, or another source.' ),
        );

        echo '<div class="rcl-admin-fields"><p class="description"><strong>Reference only:</strong> Code entered here is displayed and copied as text. It is never evaluated or injected by this plugin.</p>';
        foreach ( $fields as $key => $spec ) {
            $value = get_post_meta( $post->ID, $key, true );
            echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $spec[0] ) . '</strong></label><br>';
            if ( 'textarea' === $spec[1] || 'code' === $spec[1] ) {
                echo '<textarea class="widefat' . ( 'code' === $spec[1] ? ' code' : '' ) . '" rows="' . ( 'code' === $spec[1] ? '18' : '4' ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"' . ( 'code' === $spec[1] ? ' spellcheck="false"' : '' ) . '>' . esc_textarea( $value ) . '</textarea>';
            } else {
                echo '<input class="widefat" type="' . esc_attr( $spec[1] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
            }
            echo '<span class="description">' . esc_html( $spec[2] ) . '</span></p>';
        }
        echo '</div>';
    }

    public function render_examples_meta_box( $post ) {
        $examples = self::sanitize_examples( get_post_meta( $post->ID, self::META_EXAMPLES, true ) );
        $types    = self::get_example_types();

        echo '<p>' . esc_html__( 'Add up to 20 screenshots that show the code before, after, configured, tested, or working in place. Images remain in the WordPress Media Library.', 'reference-code-library' ) . '</p>';
        echo '<p><button type="button" class="button button-secondary" data-rcl-add-examples>' . esc_html__( 'Add screenshots', 'reference-code-library' ) . '</button></p>';
        echo '<p class="description" data-rcl-examples-empty' . ( $examples ? ' hidden' : '' ) . '>' . esc_html__( 'No screenshots have been added to this code entry yet.', 'reference-code-library' ) . '</p>';
        echo '<div class="rcl-examples-admin-list' . ( $examples ? '' : ' is-empty' ) . '" data-rcl-examples-list>';

        foreach ( $examples as $index => $example ) {
            $preview = wp_get_attachment_image_url( (int) $example['attachment_id'], 'medium' );
            $full    = wp_get_attachment_url( (int) $example['attachment_id'] );
            if ( ! $preview || ! $full ) {
                continue;
            }

            echo '<div class="rcl-example-row" data-rcl-example-index="' . esc_attr( (string) $index ) . '">';
            echo '<div class="rcl-example-row__preview"><a href="' . esc_url( $full ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'Open full-size screenshot in a new tab', 'reference-code-library' ) . '"><img src="' . esc_url( $preview ) . '" alt=""></a></div>';
            echo '<div class="rcl-example-row__fields">';
            echo '<input type="hidden" name="rcl_examples[' . esc_attr( (string) $index ) . '][attachment_id]" value="' . esc_attr( (string) $example['attachment_id'] ) . '" data-rcl-example-field="attachment_id">';
            echo '<input type="hidden" name="rcl_examples[' . esc_attr( (string) $index ) . '][order]" value="' . esc_attr( (string) $example['order'] ) . '" data-rcl-example-field="order">';
            echo '<p class="rcl-example-row__heading"><strong>' . esc_html__( 'Example', 'reference-code-library' ) . ' <span data-rcl-example-number>' . esc_html( (string) ( $index + 1 ) ) . '</span></strong></p>';
            echo '<label><span>' . esc_html__( 'Example type', 'reference-code-library' ) . '</span><select class="widefat" name="rcl_examples[' . esc_attr( (string) $index ) . '][type]" data-rcl-example-field="type">';
            foreach ( $types as $value => $label ) {
                echo '<option value="' . esc_attr( $value ) . '"' . selected( $example['type'], $value, false ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></label>';
            echo '<label><span>' . esc_html__( 'Alternative text', 'reference-code-library' ) . '</span><input type="text" class="widefat" name="rcl_examples[' . esc_attr( (string) $index ) . '][alt]" value="' . esc_attr( $example['alt'] ) . '" data-rcl-example-field="alt"><span class="description">' . esc_html__( 'Describe the result demonstrated by the screenshot. Leave blank only when the screenshot is genuinely decorative.', 'reference-code-library' ) . '</span></label>';
            echo '<label><span>' . esc_html__( 'Caption', 'reference-code-library' ) . '</span><textarea class="widefat" rows="3" name="rcl_examples[' . esc_attr( (string) $index ) . '][caption]" data-rcl-example-field="caption">' . esc_textarea( $example['caption'] ) . '</textarea></label>';
            echo '</div>';
            echo '<div class="rcl-example-row__actions"><button type="button" class="button button-secondary" data-rcl-example-up>' . esc_html__( 'Move up', 'reference-code-library' ) . '</button><button type="button" class="button button-secondary" data-rcl-example-down>' . esc_html__( 'Move down', 'reference-code-library' ) . '</button><button type="button" class="button-link-delete" data-rcl-example-remove>' . esc_html__( 'Remove', 'reference-code-library' ) . '</button></div>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function save_entry_meta( $post_id ) {
        if ( ! isset( $_POST['rcl_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rcl_entry_nonce'] ) ), 'rcl_save_entry' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $text_fields = array( '_moa_summary', '_moa_use_when', '_moa_implementation_notes', '_moa_technology', '_moa_code_language' );
        foreach ( $text_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        if ( isset( $_POST['_moa_source_url'] ) ) {
            update_post_meta( $post_id, '_moa_source_url', esc_url_raw( wp_unslash( $_POST['_moa_source_url'] ) ) );
        }

        if ( isset( $_POST['_moa_code'] ) ) {
            $code = wp_check_invalid_utf8( wp_unslash( $_POST['_moa_code'] ) );
            $code = str_replace( array( "\r\n", "\r" ), "\n", $code );
            update_post_meta( $post_id, '_moa_code', $code );
        }

        $examples = isset( $_POST['rcl_examples'] ) ? wp_unslash( $_POST['rcl_examples'] ) : array();
        update_post_meta( $post_id, self::META_EXAMPLES, self::sanitize_examples( $examples ) );

        $source_key = get_post_meta( $post_id, '_moa_source_key', true );
        if ( ! $source_key ) {
            $source_key = self::generate_unique_source_key( get_the_title( $post_id ), $post_id );
            update_post_meta( $post_id, '_moa_source_key', $source_key );
        }
    }

    public static function generate_unique_source_key( $preferred, $exclude_post_id = 0 ) {
        $base = sanitize_title( $preferred );
        if ( ! $base ) {
            $base = 'code-entry';
        }

        $candidate = $base;
        $suffix    = 2;
        while ( self::source_key_exists( $candidate, $exclude_post_id ) ) {
            $candidate = $base . '-' . $suffix;
            ++$suffix;
        }
        return $candidate;
    }

    public static function source_key_exists( $source_key, $exclude_post_id = 0 ) {
        $posts = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post__not_in'   => $exclude_post_id ? array( (int) $exclude_post_id ) : array(),
                'meta_key'       => '_moa_source_key',
                'meta_value'     => $source_key,
            )
        );
        return ! empty( $posts );
    }

    public function entry_columns( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['rcl_technology'] = __( 'Technology', 'reference-code-library' );
                $new['rcl_language']   = __( 'Language', 'reference-code-library' );
                $new['rcl_status']     = __( 'Status', 'reference-code-library' );
            }
        }
        return $new;
    }

    public function entry_column_content( $column, $post_id ) {
        if ( 'rcl_technology' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_moa_technology', true ) );
        } elseif ( 'rcl_language' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_moa_code_language', true ) );
        } elseif ( 'rcl_status' === $column ) {
            echo esc_html( $this->get_status_name( $post_id ) );
        }
    }

    public function plugin_action_links( $links ) {
        $import = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=rcl-import-export' ) ) . '">' . esc_html__( 'Import / Export', 'reference-code-library' ) . '</a>';
        $style  = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=rcl-appearance' ) ) . '">' . esc_html__( 'Appearance', 'reference-code-library' ) . '</a>';
        array_unshift( $links, $style );
        array_unshift( $links, $import );
        return $links;
    }
}
