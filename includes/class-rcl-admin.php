<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RCL_Admin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'empty_library_notice' ) );
        add_action( 'admin_post_rcl_export_library', array( $this, 'handle_export' ) );
        add_action( 'admin_post_rcl_download_template', array( $this, 'handle_template_download' ) );
        add_action( 'admin_post_rcl_download_example', array( $this, 'handle_example_download' ) );
        add_action( 'admin_post_rcl_restore_custom_css', array( $this, 'handle_restore_custom_css' ) );
    }

    public function admin_menu() {
        $parent = 'edit.php?post_type=' . RCL_Library::POST_TYPE;

        add_submenu_page(
            $parent,
            __( 'Import / Export', 'reference-code-library' ),
            __( 'Import / Export', 'reference-code-library' ),
            'manage_options',
            'rcl-import-export',
            array( $this, 'import_export_page' )
        );

        add_submenu_page(
            $parent,
            __( 'Code Library Appearance', 'reference-code-library' ),
            __( 'Appearance', 'reference-code-library' ),
            'manage_options',
            'rcl-appearance',
            array( $this, 'appearance_page' )
        );

        add_submenu_page(
            $parent,
            __( 'Code Library Help', 'reference-code-library' ),
            __( 'Help', 'reference-code-library' ),
            'edit_posts',
            'rcl-help',
            array( $this, 'help_page' )
        );
    }

    public function register_settings() {
        register_setting(
            'rcl_library_settings_group',
            RCL_Library::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( 'RCL_Library', 'sanitize_settings' ),
                'default'           => RCL_Library::get_default_settings(),
            )
        );
    }

    public function admin_assets( $hook_suffix ) {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $is_plugin_page = RCL_Library::POST_TYPE === $screen->post_type
            || false !== strpos( (string) $screen->id, 'rcl-' );

        if ( ! $is_plugin_page ) {
            return;
        }

        wp_enqueue_style(
            'rcl-admin',
            RCL_URL . 'assets/css/admin.css',
            array(),
            RCL_VERSION
        );

        if ( RCL_Library::POST_TYPE === $screen->post_type && 'post' === $screen->base ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'rcl-entry-examples',
                RCL_URL . 'assets/js/entry-examples.js',
                array( 'jquery' ),
                RCL_VERSION,
                true
            );
            wp_localize_script(
                'rcl-entry-examples',
                'rclEntryExamples',
                array(
                    'chooseTitle'  => __( 'Choose working-example screenshots', 'reference-code-library' ),
                    'useImages'    => __( 'Add selected screenshots', 'reference-code-library' ),
                    'example'      => __( 'Example', 'reference-code-library' ),
                    'typeLabel'    => __( 'Example type', 'reference-code-library' ),
                    'altLabel'     => __( 'Alternative text', 'reference-code-library' ),
                    'altHelp'      => __( 'Describe the result demonstrated by the screenshot. Leave blank only when it is decorative.', 'reference-code-library' ),
                    'captionLabel' => __( 'Caption', 'reference-code-library' ),
                    'moveUp'       => __( 'Move up', 'reference-code-library' ),
                    'moveDown'     => __( 'Move down', 'reference-code-library' ),
                    'remove'       => __( 'Remove', 'reference-code-library' ),
                    'openFullSize' => __( 'Open full-size screenshot in a new tab', 'reference-code-library' ),
                    'maxExamples'  => 20,
                    'maxMessage'   => __( 'A code entry can contain up to 20 working-example screenshots. The remaining selected images were not added.', 'reference-code-library' ),
                    'types'        => RCL_Library::get_example_types(),
                )
            );
        }

        if ( false !== strpos( (string) $screen->id, 'rcl-appearance' ) ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'rcl-admin',
                RCL_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                RCL_VERSION,
                true
            );
            wp_localize_script(
                'rcl-admin',
                'rclAdmin',
                array(
                    'chooseLogo' => __( 'Choose a library logo', 'reference-code-library' ),
                    'useLogo'    => __( 'Use this logo', 'reference-code-library' ),
                )
            );

            $editor_settings = wp_enqueue_code_editor(
                array(
                    'type'       => 'text/css',
                    'codemirror' => array(
                        'indentUnit' => 2,
                        'tabSize'    => 2,
                        'lineNumbers'=> true,
                    ),
                )
            );

            if ( false !== $editor_settings ) {
                wp_add_inline_script(
                    'code-editor',
                    sprintf(
                        'jQuery(function(){if(window.wp&&wp.codeEditor&&document.getElementById("rcl-custom-css")){wp.codeEditor.initialize("rcl-custom-css",%s);}});',
                        wp_json_encode( $editor_settings )
                    )
                );
            }
        }
    }

    public function empty_library_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || RCL_Library::POST_TYPE !== $screen->post_type ) {
            return;
        }

        $counts = wp_count_posts( RCL_Library::POST_TYPE );
        $total  = 0;
        foreach ( array( 'publish', 'draft', 'private', 'pending' ) as $status ) {
            $total += isset( $counts->$status ) ? (int) $counts->$status : 0;
        }

        if ( $total ) {
            return;
        }

        $import_url = admin_url( 'edit.php?post_type=' . RCL_Library::POST_TYPE . '&page=rcl-import-export' );
        $add_url    = admin_url( 'post-new.php?post_type=' . RCL_Library::POST_TYPE );
        echo '<div class="notice notice-info"><p><strong>Reference Code Library is ready.</strong> <a href="' . esc_url( $add_url ) . '">Add code manually</a> or <a href="' . esc_url( $import_url ) . '">import a library pack</a>.</p></div>';
    }

    public function import_export_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage library imports.', 'reference-code-library' ) );
        }

        $preview = null;
        $result  = null;
        $error   = null;

        if ( isset( $_POST['rcl_preview_import'] ) ) {
            check_admin_referer( 'rcl_preview_import_action', 'rcl_preview_import_nonce' );
            $preview = RCL_Importer::prepare_upload( $_FILES['rcl_import_file'] ?? array() );
            if ( is_wp_error( $preview ) ) {
                $error   = $preview;
                $preview = null;
            }
        }

        if ( isset( $_POST['rcl_confirm_import'] ) ) {
            check_admin_referer( 'rcl_confirm_import_action', 'rcl_confirm_import_nonce' );
            $token           = sanitize_text_field( wp_unslash( $_POST['rcl_import_token'] ?? '' ) );
            $conflict_policy = sanitize_key( wp_unslash( $_POST['rcl_conflict_policy'] ?? 'update' ) );
            $import_branding = ! empty( $_POST['rcl_import_branding'] );
            $create_page     = ! empty( $_POST['rcl_create_page'] );
            $result          = RCL_Importer::import_from_token( $token, $conflict_policy, $import_branding, $create_page );
            if ( is_wp_error( $result ) ) {
                $error  = $result;
                $result = null;
            }
        }

        echo '<div class="wrap rcl-admin-wrap"><h1>Import / Export</h1>';
        echo '<p class="rcl-admin-lead">Move complete code libraries between WordPress sites using validated JSON, TXT, or portable ZIP packs. ZIP packs can include working-example screenshots. Imported code remains inert reference text and is never executed.</p>';

        if ( $error ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( $error->get_error_message() ) . '</p></div>';
        }

        if ( $result ) {
            $message = sprintf(
                '%1$d created, %2$d updated, and %3$d skipped.',
                (int) $result['created'],
                (int) $result['updated'],
                (int) $result['skipped']
            );
            echo '<div class="notice notice-success inline"><p><strong>Import complete:</strong> ' . esc_html( $message ) . '</p>';
            if ( ! empty( $result['media_imported'] ) ) {
                echo '<p>' . esc_html( sprintf( _n( '%d screenshot was added to the Media Library.', '%d screenshots were added to the Media Library.', (int) $result['media_imported'], 'reference-code-library' ), (int) $result['media_imported'] ) ) . '</p>';
            }
            if ( ! empty( $result['branding'] ) ) {
                echo '<p>The library pack branding was applied.</p>';
            }
            if ( ! empty( $result['page_id'] ) ) {
                echo '<p><a href="' . esc_url( get_edit_post_link( $result['page_id'] ) ) . '">Review the draft library page</a>.</p>';
            }
            echo '</div>';

            if ( ! empty( $result['errors'] ) ) {
                echo '<div class="notice notice-warning inline"><p><strong>Some items need attention:</strong></p><ul class="ul-disc">';
                foreach ( $result['errors'] as $message ) {
                    echo '<li>' . esc_html( $message ) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        echo '<div class="rcl-admin-grid">';
        echo '<section class="rcl-admin-card"><h2>Import a library pack</h2>';

        if ( $preview ) {
            $this->render_import_preview( $preview );
        } else {
            $this->render_upload_form();
        }
        echo '</section>';

        echo '<section class="rcl-admin-card"><h2>Pack templates</h2><p>Start with a predictable schema instead of inventing field names by hand.</p>';
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcl_download_template' ), 'rcl_download_template' ) ) . '">Download blank template</a></p>';
        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcl_download_example' ), 'rcl_download_example' ) ) . '">Download example pack</a></p>';
        echo '<p class="description">Both files use the <code>reference-code-library/v2</code> schema. JSON-formatted <code>.txt</code> files remain supported. Portable ZIP packs contain <code>library.json</code> and an <code>images/</code> folder.</p></section>';
        echo '</div>';

        $this->render_export_section();
        echo '</div>';
    }

    private function render_upload_form() {
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field( 'rcl_preview_import_action', 'rcl_preview_import_nonce' );
        echo '<p><label for="rcl_import_file"><strong>Library pack</strong></label></p>';
        echo '<input type="file" id="rcl_import_file" name="rcl_import_file" accept=".json,.txt,.zip,application/json,text/plain,application/zip" required>';
        echo '<p class="description">Maximum size: 5 MB for JSON/TXT or 25 MB for ZIP. Screenshots inside a validated ZIP are added to the Media Library only after you confirm the import.</p>';
        submit_button( 'Validate and preview', 'primary', 'rcl_preview_import', false );
        echo '</form>';
    }

    private function render_import_preview( $preview ) {
        echo '<div class="rcl-preview-summary"><p class="rcl-preview-title">' . esc_html( $preview['pack']['name'] ) . ' <span>v' . esc_html( $preview['pack']['version'] ) . '</span></p>';
        if ( ! empty( $preview['pack']['description'] ) ) {
            echo '<p>' . esc_html( $preview['pack']['description'] ) . '</p>';
        }
        echo '<dl><div><dt>Collections</dt><dd>' . esc_html( (string) $preview['collections'] ) . '</dd></div><div><dt>Entries</dt><dd>' . esc_html( (string) $preview['entries'] ) . '</dd></div><div><dt>Screenshots</dt><dd>' . esc_html( (string) ( $preview['screenshots'] ?? 0 ) ) . '</dd></div><div><dt>New</dt><dd>' . esc_html( (string) $preview['new'] ) . '</dd></div><div><dt>Existing</dt><dd>' . esc_html( (string) $preview['existing'] ) . '</dd></div></dl></div>';

        if ( ! empty( $preview['items'] ) ) {
            echo '<details class="rcl-preview-items"><summary>Review detected entries</summary><div class="rcl-preview-table-wrap"><table class="widefat striped"><thead><tr><th>Entry</th><th>ID</th><th>Screenshots</th><th>Result</th></tr></thead><tbody>';
            foreach ( $preview['items'] as $item ) {
                echo '<tr><td>' . esc_html( $item['title'] ) . '</td><td><code>' . esc_html( $item['id'] ) . '</code></td><td>' . esc_html( (string) ( $item['screenshots'] ?? 0 ) ) . '</td><td>' . ( $item['existing'] ? 'Existing entry' : 'New entry' ) . '</td></tr>';
            }
            echo '</tbody></table></div></details>';
        }

        echo '<form method="post" class="rcl-confirm-form">';
        wp_nonce_field( 'rcl_confirm_import_action', 'rcl_confirm_import_nonce' );
        echo '<input type="hidden" name="rcl_import_token" value="' . esc_attr( $preview['token'] ) . '">';
        echo '<fieldset><legend><strong>When an entry ID already exists</strong></legend>';
        echo '<label><input type="radio" name="rcl_conflict_policy" value="update" checked> Update the existing entry</label><br>';
        echo '<label><input type="radio" name="rcl_conflict_policy" value="skip"> Skip the imported entry</label><br>';
        echo '<label><input type="radio" name="rcl_conflict_policy" value="duplicate"> Create a copy with a new ID</label></fieldset>';

        if ( ! empty( $preview['branding'] ) ) {
            echo '<p><label><input type="checkbox" name="rcl_import_branding" value="1"> Import this pack’s title, colors, and display settings</label></p>';
        }
        echo '<p><label><input type="checkbox" name="rcl_create_page" value="1" checked> Create or locate a draft page containing <code>[code_library]</code></label></p>';
        submit_button( 'Import library pack', 'primary', 'rcl_confirm_import', false );
        echo ' <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . RCL_Library::POST_TYPE . '&page=rcl-import-export' ) ) . '">Cancel</a>';
        echo '</form>';
    }

    private function render_export_section() {
        $collections = get_terms(
            array(
                'taxonomy'   => RCL_Library::TAX_COLLECTION,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );
        $entries = get_posts(
            array(
                'post_type'      => RCL_Library::POST_TYPE,
                'post_status'    => array( 'publish', 'draft', 'private' ),
                'posts_per_page' => -1,
                'orderby'        => array( 'title' => 'ASC' ),
            )
        );

        echo '<section class="rcl-admin-card rcl-export-card"><h2>Export a library pack</h2><p>Export the complete library, one collection, or a hand-picked set of entries. Exports containing screenshots are packaged as portable ZIP files.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="rcl_export_library">';
        wp_nonce_field( 'rcl_export_library', 'rcl_export_nonce' );

        echo '<fieldset class="rcl-export-options"><legend><strong>Export scope</strong></legend>';
        echo '<label><input type="radio" name="rcl_export_scope" value="all" checked> Entire library</label>';
        echo '<label><input type="radio" name="rcl_export_scope" value="collection"> One collection</label>';
        echo '<label><input type="radio" name="rcl_export_scope" value="selected"> Selected entries</label>';
        echo '</fieldset>';

        echo '<p><label for="rcl_export_collection"><strong>Collection</strong></label><br><select id="rcl_export_collection" name="rcl_export_collection"><option value="">Choose a collection</option>';
        if ( ! is_wp_error( $collections ) ) {
            foreach ( $collections as $term ) {
                echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . ' (' . esc_html( (string) $term->count ) . ')</option>';
            }
        }
        echo '</select></p>';

        echo '<details class="rcl-export-selection"><summary>Select individual entries</summary>';
        if ( $entries ) {
            echo '<div class="rcl-entry-checklist"><label class="rcl-check-all"><input type="checkbox" data-rcl-check-all> Select all entries</label>';
            foreach ( $entries as $entry ) {
                echo '<label><input type="checkbox" name="rcl_export_entries[]" value="' . esc_attr( (string) $entry->ID ) . '"> ' . esc_html( $entry->post_title ) . ' <span>(' . esc_html( $entry->post_status ) . ')</span></label>';
            }
            echo '</div>';
        } else {
            echo '<p>No entries are available to export.</p>';
        }
        echo '</details>';

        echo '<p><label><input type="checkbox" name="rcl_export_branding" value="1"> Include the current library title, colors, typography, alignment, and display settings</label><br><label class="rcl-export-suboption"><input type="checkbox" name="rcl_export_custom_css" value="1"> Include Advanced CSS when branding is included</label></p>';
        echo '<p><label><input type="checkbox" name="rcl_export_examples" value="1" checked> Include working-example screenshots</label><br><span class="description">When selected entries contain screenshots, the download is a ZIP containing <code>library.json</code> and an <code>images/</code> folder. Without screenshots, the export remains JSON.</span></p>';
        submit_button( 'Download library pack', 'primary', 'submit', false, $entries ? array() : array( 'disabled' => 'disabled' ) );
        echo '</form></section>';
    }

    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to export this library.', 'reference-code-library' ) );
        }
        check_admin_referer( 'rcl_export_library', 'rcl_export_nonce' );

        $scope            = sanitize_key( wp_unslash( $_POST['rcl_export_scope'] ?? 'all' ) );
        $include_branding = ! empty( $_POST['rcl_export_branding'] );
        $include_examples   = ! empty( $_POST['rcl_export_examples'] );
        $include_custom_css = $include_branding && ! empty( $_POST['rcl_export_custom_css'] );
        $post_ids         = array();
        $collection       = '';

        if ( 'collection' === $scope ) {
            $collection = sanitize_title( wp_unslash( $_POST['rcl_export_collection'] ?? '' ) );
            if ( ! $collection ) {
                wp_die( esc_html__( 'Choose a collection before exporting.', 'reference-code-library' ) );
            }
        } elseif ( 'selected' === $scope ) {
            $post_ids = array_map( 'absint', (array) ( $_POST['rcl_export_entries'] ?? array() ) );
            $post_ids = array_values( array_filter( $post_ids ) );
            if ( ! $post_ids ) {
                wp_die( esc_html__( 'Select at least one code entry before exporting.', 'reference-code-library' ) );
            }
        }

        $pack     = RCL_Exporter::build_pack( $post_ids, $collection, $include_branding, $include_examples, $include_custom_css );
        $filename = sanitize_title( $pack['pack']['name'] ) . '-library-pack';
        RCL_Exporter::send_library_pack( $pack, $filename );
    }

    public function handle_template_download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to download this template.', 'reference-code-library' ) );
        }
        check_admin_referer( 'rcl_download_template' );
        RCL_Exporter::send_download( RCL_Exporter::blank_template(), 'reference-code-library-blank-template.json' );
    }

    public function handle_example_download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to download this example.', 'reference-code-library' ) );
        }
        check_admin_referer( 'rcl_download_example' );
        RCL_Exporter::send_download( RCL_Exporter::example_pack(), 'reference-code-library-example-pack.json' );
    }

    public function appearance_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to change library appearance.', 'reference-code-library' ) );
        }

        $settings = RCL_Library::get_settings();
        $logo_url = $settings['logo_id'] ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'medium' ) : $settings['legacy_logo_url'];

        echo '<div class="wrap rcl-admin-wrap"><h1>Code Library Appearance</h1><p class="rcl-admin-lead">Brand the library, control typography and alignment, and add carefully scoped CSS without editing plugin files.</p>';

        if ( isset( $_GET['rcl_css_restored'] ) ) {
            echo '<div class="notice notice-success inline"><p>The previous Advanced CSS was restored.</p></div>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields( 'rcl_library_settings_group' );
        echo '<table class="form-table rcl-appearance-table" role="presentation"><tbody>';

        $this->section_row( 'Identity', 'Set the library name, introductory copy, and optional logo.' );
        $this->text_row( 'Library title', 'title', $settings['title'] );
        $this->text_row( 'Eyebrow or organization text', 'eyebrow', $settings['eyebrow'] );
        echo '<tr><th scope="row"><label for="rcl-intro">Introduction</label></th><td><textarea class="large-text" rows="4" id="rcl-intro" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[intro]">' . esc_textarea( $settings['intro'] ) . '</textarea></td></tr>';

        echo '<tr><th scope="row">Logo</th><td>';
        echo '<input type="hidden" id="rcl-logo-id" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[logo_id]" value="' . esc_attr( (string) $settings['logo_id'] ) . '">';
        echo '<input type="hidden" id="rcl-legacy-logo-url" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[legacy_logo_url]" value="' . esc_attr( $settings['legacy_logo_url'] ) . '">';
        echo '<div class="rcl-logo-preview" data-rcl-logo-preview>';
        if ( $logo_url ) {
            echo '<img src="' . esc_url( $logo_url ) . '" alt="">';
        } else {
            echo '<span>No logo selected</span>';
        }
        echo '</div><p><button type="button" class="button" data-rcl-choose-logo>Choose logo</button> <button type="button" class="button" data-rcl-remove-logo' . ( $logo_url ? '' : ' hidden' ) . '>Remove logo</button></p>';
        echo '<p class="description">The plugin uses the WordPress Media Library. A logo is optional.</p></td></tr>';
        $this->text_row( 'Logo alternative text', 'logo_alt', $settings['logo_alt'], 'text', 'Describe the organization represented by the logo. Leave blank only when the logo is decorative.' );

        $this->section_row( 'Layout and theme compatibility', 'Choose the overall presentation and stop parent theme alignment rules from drifting into the library.' );
        $this->select_row(
            'Layout preset',
            'layout_preset',
            $settings['layout_preset'],
            array( 'classic' => 'Classic', 'minimal' => 'Minimal', 'documentation' => 'Documentation' )
        );
        $this->select_row(
            'Theme style isolation',
            'style_isolation',
            $settings['style_isolation'],
            array( 'standard' => 'Standard isolation', 'relaxed' => 'Allow more theme styling' ),
            'Standard isolation is recommended when Colibri or another theme changes headings, controls, or text unexpectedly.'
        );
        $this->select_row(
            'General content alignment',
            'content_alignment',
            $settings['content_alignment'],
            array( 'start' => 'Start / left in left-to-right languages', 'center' => 'Center' )
        );
        $this->select_row(
            'Hero content alignment',
            'hero_alignment',
            $settings['hero_alignment'],
            array( 'start' => 'Start / left in left-to-right languages', 'center' => 'Center' )
        );
        $this->select_row(
            'Card content alignment',
            'card_alignment',
            $settings['card_alignment'],
            array( 'start' => 'Start / left in left-to-right languages', 'center' => 'Center' )
        );

        echo '<tr><th scope="row">Visible sections</th><td>';
        $this->checkbox( 'show_hero', 'Show the hero section', $settings );
        $this->checkbox( 'show_stats', 'Show library statistics', $settings );
        $this->checkbox( 'show_start_here', 'Show the “Start here” cards', $settings );
        $this->checkbox( 'show_collection_descriptions', 'Show collection descriptions', $settings );
        echo '</td></tr>';

        $this->section_row( 'Typography', 'Use the plugin defaults, inherit the active theme, or select custom local font stacks. The plugin does not download external fonts.' );
        $this->select_row(
            'Typography source',
            'typography_mode',
            $settings['typography_mode'],
            array( 'plugin' => 'Use Code Library typography', 'inherit' => 'Inherit typography from the active theme', 'custom' => 'Custom typography' ),
            '',
            'data-rcl-typography-mode'
        );

        foreach ( array( 'body' => 'Body font', 'heading' => 'Heading font', 'accent' => 'Accent and button font', 'code' => 'Code font' ) as $group => $label ) {
            $this->font_row( $label, $group, $settings );
        }
        $this->number_row( 'Base font size', 'base_font_size', $settings['base_font_size'], 12, 24, 'pixels' );
        $this->decimal_row( 'Line height', 'line_height', $settings['line_height'], 1.2, 2.5, 0.1, 'Use a unitless value so text can resize and reflow safely.' );
        $this->number_row( 'Code font size', 'code_font_size', $settings['code_font_size'], 11, 24, 'pixels' );

        $this->section_row( 'Colors', 'Choose colors for the library surfaces and controls. Verify contrast after saving.' );
        $this->color_row( 'Primary color', 'primary_color', $settings['primary_color'] );
        $this->color_row( 'Secondary color', 'secondary_color', $settings['secondary_color'] );
        $this->color_row( 'Accent color', 'accent_color', $settings['accent_color'] );
        $this->color_row( 'Focus indicator color', 'focus_color', $settings['focus_color'], 'Check this color against the surfaces where focus outlines appear.' );
        $this->color_row( 'Page background', 'background_color', $settings['background_color'] );
        $this->color_row( 'Card background', 'card_color', $settings['card_color'] );
        $this->color_row( 'Text color', 'text_color', $settings['text_color'] );
        $this->color_row( 'Muted text color', 'muted_color', $settings['muted_color'] );
        $this->color_row( 'Border color', 'border_color', $settings['border_color'] );

        $this->section_row( 'Sizing', 'Adjust the library container and component shape.' );
        $this->number_row( 'Content width', 'content_width', $settings['content_width'], 640, 1920, 'pixels' );
        $this->number_row( 'Border radius', 'border_radius', $settings['border_radius'], 0, 32, 'pixels' );

        $this->section_row( 'Advanced CSS', 'Styles entered here load after the main Code Library stylesheet. Scope selectors to .rcl-library so they do not leak into the rest of the site.' );
        echo '<tr><th scope="row"><label for="rcl-custom-css">Custom CSS</label></th><td>';
        echo '<label class="rcl-settings-checkbox"><input type="checkbox" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[custom_css_enabled]" value="1" ' . checked( '1', (string) $settings['custom_css_enabled'], false ) . '> Enable Advanced CSS on the frontend</label>';
        echo '<textarea class="large-text code" rows="18" id="rcl-custom-css" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[custom_css]" spellcheck="false">' . esc_textarea( $settings['custom_css'] ) . '</textarea>';
        echo '<p class="description">Do not include &lt;style&gt; tags. Maximum 50,000 characters. Example: <code>.rcl-library .rcl-hero__content { text-align: center; }</code></p>';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button( 'Save Appearance' );
        echo '</form>';

        if ( '' !== trim( $settings['custom_css_backup'] ) ) {
            echo '<hr><h2>Advanced CSS recovery</h2><p>A previous saved CSS version is available. Restoring it swaps the current and previous versions.</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="rcl_restore_custom_css">';
            wp_nonce_field( 'rcl_restore_custom_css', 'rcl_restore_custom_css_nonce' );
            submit_button( 'Restore previous CSS', 'secondary', 'submit', false );
            echo '</form>';
        }

        echo '</div>';
    }

    public function handle_restore_custom_css() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to restore library CSS.', 'reference-code-library' ) );
        }
        check_admin_referer( 'rcl_restore_custom_css', 'rcl_restore_custom_css_nonce' );

        $settings = RCL_Library::get_settings();
        $backup   = (string) $settings['custom_css_backup'];
        if ( '' !== trim( $backup ) ) {
            $settings['custom_css']         = $backup;
            $settings['custom_css_enabled'] = '1';
            update_option( RCL_Library::OPTION_KEY, RCL_Library::sanitize_settings( $settings ) );
        }

        wp_safe_redirect( admin_url( 'edit.php?post_type=' . RCL_Library::POST_TYPE . '&page=rcl-appearance&rcl_css_restored=1' ) );
        exit;
    }

    private function section_row( $heading, $description = '' ) {
        echo '<tr class="rcl-settings-section"><th colspan="2"><h2>' . esc_html( $heading ) . '</h2>';
        if ( $description ) {
            echo '<p>' . esc_html( $description ) . '</p>';
        }
        echo '</th></tr>';
    }

    private function text_row( $label, $name, $value, $type = 'text', $description = '' ) {
        $id = 'rcl-' . sanitize_html_class( $name );
        echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '">';
        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</td></tr>';
    }

    private function select_row( $label, $name, $value, $options, $description = '', $attributes = '' ) {
        $id = 'rcl-' . sanitize_html_class( $name );
        echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><select id="' . esc_attr( $id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $name ) . ']" ' . $attributes . '>';
        foreach ( $options as $option_value => $option_label ) {
            echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select>';
        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</td></tr>';
    }

    private function font_row( $label, $group, $settings ) {
        $preset_name = $group . '_font_preset';
        $custom_name = $group . '_font_custom';
        $preset_id   = 'rcl-' . sanitize_html_class( $preset_name );
        $custom_id   = 'rcl-' . sanitize_html_class( $custom_name );

        echo '<tr data-rcl-font-row><th scope="row"><label for="' . esc_attr( $preset_id ) . '">' . esc_html( $label ) . '</label></th><td>';
        echo '<select id="' . esc_attr( $preset_id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $preset_name ) . ']" data-rcl-font-preset="' . esc_attr( $group ) . '">';
        foreach ( RCL_Library::get_font_options( $group ) as $value => $option_label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $preset_name ], $value, false ) . '>' . esc_html( $option_label ) . '</option>';
        }
        echo '</select> ';
        echo '<input class="regular-text" type="text" id="' . esc_attr( $custom_id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $custom_name ) . ']" value="' . esc_attr( $settings[ $custom_name ] ) . '" data-rcl-custom-font="' . esc_attr( $group ) . '" placeholder="Open Sans, Arial, sans-serif">';
        echo '<p class="description">Custom stacks use fonts already loaded by the site or available on the visitor’s device.</p></td></tr>';
    }

    private function color_row( $label, $name, $value, $description = '' ) {
        $id = 'rcl-' . sanitize_html_class( $name );
        echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '"> <code>' . esc_html( $value ) . '</code>';
        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</td></tr>';
    }

    private function number_row( $label, $name, $value, $min, $max, $suffix = '' ) {
        $id = 'rcl-' . sanitize_html_class( $name );
        echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $name ) . ']" value="' . esc_attr( (string) $value ) . '" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '"> ' . esc_html( $suffix ) . '</td></tr>';
    }

    private function decimal_row( $label, $name, $value, $min, $max, $step, $description = '' ) {
        $id = 'rcl-' . sanitize_html_class( $name );
        echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $name ) . ']" value="' . esc_attr( (string) $value ) . '" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" step="' . esc_attr( (string) $step ) . '">';
        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
        echo '</td></tr>';
    }

    private function checkbox( $name, $label, $settings ) {
        echo '<label class="rcl-settings-checkbox"><input type="checkbox" name="' . esc_attr( RCL_Library::OPTION_KEY ) . '[' . esc_attr( $name ) . ']" value="1" ' . checked( '1', (string) $settings[ $name ], false ) . '> ' . esc_html( $label ) . '</label>';
    }

    public function help_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'reference-code-library' ) );
        }

        echo '<div class="wrap rcl-admin-wrap"><h1>Code Library Help</h1><div class="rcl-admin-grid">';
        echo '<section class="rcl-admin-card"><h2>Place the library</h2><p>Use the Gutenberg block named <strong>Code Library</strong>, or add one of these shortcodes to a page:</p><p><code>[code_library]</code></p><p><code>[code_collection slug="css"]</code></p><p><code>[code_entry slug="visible-focus-example"]</code></p><p>Legacy v1 shortcodes continue to render.</p></section>';
        echo '<section class="rcl-admin-card"><h2>Build entries</h2><p>Use <strong>Code Library → Add Code</strong> for manual entry. Add the title, context fields, language, inert code text, collection, status, optional tags, and working-example screenshots.</p><p>Each screenshot can be labeled as a before, after, result, configuration, inspector, mobile, test, or other view. Add useful alternative text and a visible caption. The stable import ID is generated automatically from the title and remains hidden from editors.</p></section>';
        echo '<section class="rcl-admin-card"><h2>Import safely</h2><p>Imports accept JSON-formatted <code>.json</code> or <code>.txt</code> files up to 5 MB, plus portable <code>.zip</code> packs up to 25 MB. ZIP packs may include <code>library.json</code> and validated images inside <code>images/</code>. Every pack is previewed before records or Media Library attachments are created.</p></section>';
        echo '<section class="rcl-admin-card"><h2>Appearance and compatibility</h2><p>Use <strong>Code Library → Appearance</strong> to choose typography, alignment, stronger theme isolation, and optional Advanced CSS. Custom CSS is presentation-only and loads after the plugin stylesheet.</p></section>';
        echo '<section class="rcl-admin-card"><h2>Execution boundary</h2><p>This plugin is a documentation and reference system. It does not evaluate stored PHP, inject stored JavaScript, or execute imported code. The separate Advanced CSS setting applies only administrator-authored presentation CSS.</p></section>';
        echo '</div></div>';
    }
}
