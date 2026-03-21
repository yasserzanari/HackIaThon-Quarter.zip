<?php
/**
 * PedagoLens_Admin_Profiles
 *
 * Gère les pages admin de gestion des profils pédagogiques :
 * menu, liste (WP_List_Table), édition, AJAX (toggle actif, réordonnancement, suppression).
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Admin_Profiles {

    private const MENU_SLUG      = 'pl-profiles';
    private const NONCE_SAVE     = 'pl_save_profile';
    private const NONCE_AJAX     = 'pl_profiles_ajax';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action( 'admin_menu',                        [ self::class, 'add_menus' ] );
        add_action( 'admin_post_pl_save_profile',        [ self::class, 'handle_save' ] );
        add_action( 'wp_ajax_pl_toggle_profile',         [ self::class, 'ajax_toggle' ] );
        add_action( 'wp_ajax_pl_reorder_profiles',       [ self::class, 'ajax_reorder' ] );
        add_action( 'wp_ajax_pl_delete_profile',         [ self::class, 'ajax_delete' ] );
        add_action( 'wp_ajax_pl_duplicate_profile',      [ self::class, 'ajax_duplicate' ] );
        add_action( 'wp_ajax_pl_export_profiles',        [ self::class, 'ajax_export' ] );
        add_action( 'wp_ajax_pl_import_profile',         [ self::class, 'ajax_import' ] );
        add_action( 'admin_enqueue_scripts',             [ self::class, 'enqueue_assets' ] );
    }

    // -------------------------------------------------------------------------
    // Menus
    // -------------------------------------------------------------------------

    public static function add_menus(): void {
        global $menu;

        // Vérifier si le menu parent pl-api-bridge-settings existe déjà (enregistré par api-bridge)
        $bridge_menu_exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'pl-api-bridge-settings' ) {
                    $bridge_menu_exists = true;
                    break;
                }
            }
        }

        if ( ! $bridge_menu_exists ) {
            add_menu_page(
                'PédagoLens',
                'PédagoLens',
                'manage_options',
                'pl-pedagolens',
                '__return_empty_string',
                'dashicons-welcome-learn-more',
                30
            );
        }

        $parent = $bridge_menu_exists ? 'pl-api-bridge-settings' : 'pl-pedagolens';

        add_submenu_page(
            $parent,
            __( 'Profils d\'apprenants', 'pedagolens-core' ),
            __( 'Profils d\'apprenants', 'pedagolens-core' ),
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'render_list_page' ]
        );

        add_submenu_page(
            $parent,
            __( 'Ajouter un profil', 'pedagolens-core' ),
            __( 'Ajouter un profil', 'pedagolens-core' ),
            'manage_options',
            'pl-profile-new',
            [ self::class, 'render_edit_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'pl-profile' ) ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script(
            'pl-core-admin',
            PEDAGOLENS_PLUGIN_URL . 'assets/js/admin-profiles.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            PEDAGOLENS_VERSION,
            true
        );
        wp_localize_script( 'pl-core-admin', 'plCoreAdmin', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( self::NONCE_AJAX ),
            'confirmDelete' => __( 'Supprimer ce profil ? Cette action est irréversible.', 'pedagolens-core' ),
            'editUrl'       => admin_url( 'admin.php?page=pl-profile-edit&slug=' ),
        ] );

        wp_enqueue_style(
            'pl-core-admin',
            PEDAGOLENS_PLUGIN_URL . 'assets/css/admin-profiles.css',
            [],
            PEDAGOLENS_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // Page liste
    // -------------------------------------------------------------------------

    public static function render_list_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-core' ) );
        }

        $profiles = PedagoLens_Profile_Manager::get_all( active_only: false );
        $saved    = get_transient( 'pl_profile_notice' );
        if ( $saved ) {
            delete_transient( 'pl_profile_notice' );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Profils d\'apprenants', 'pedagolens-core' ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved ); ?></p></div>
            <?php endif; ?>

            <div style="margin-bottom:12px;display:flex;gap:8px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pl-profile-new' ) ); ?>" class="button button-primary">
                    + <?php esc_html_e( 'Ajouter un profil', 'pedagolens-core' ); ?>
                </a>
                <button type="button" id="pl-export-profiles" class="button">
                    <?php esc_html_e( 'Exporter tous les profils', 'pedagolens-core' ); ?>
                </button>
                <label class="button" style="cursor:pointer;">
                    <?php esc_html_e( 'Importer un profil JSON', 'pedagolens-core' ); ?>
                    <input type="file" id="pl-import-profile" accept=".json" style="display:none;">
                </label>
            </div>

            <table class="wp-list-table widefat fixed striped" id="pl-profiles-table">
                <thead>
                    <tr>
                        <th style="width:30px;"></th>
                        <th><?php esc_html_e( 'Nom', 'pedagolens-core' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'pedagolens-core' ); ?></th>
                        <th><?php esc_html_e( 'Statut', 'pedagolens-core' ); ?></th>
                        <th><?php esc_html_e( 'Ordre', 'pedagolens-core' ); ?></th>
                        <th><?php esc_html_e( 'System prompt', 'pedagolens-core' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'pedagolens-core' ); ?></th>
                    </tr>
                </thead>
                <tbody id="pl-profiles-sortable">
                    <?php foreach ( $profiles as $profile ) : ?>
                        <tr data-slug="<?php echo esc_attr( $profile['slug'] ); ?>">
                            <td class="pl-drag-handle" style="cursor:grab;text-align:center;">⠿</td>
                            <td><strong><?php echo esc_html( $profile['name'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $profile['slug'] ); ?></code></td>
                            <td>
                                <span class="pl-status-badge pl-status-<?php echo $profile['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $profile['is_active']
                                        ? esc_html__( 'Actif', 'pedagolens-core' )
                                        : esc_html__( 'Inactif', 'pedagolens-core' ); ?>
                                </span>
                            </td>
                            <td><?php echo (int) $profile['sort_order']; ?></td>
                            <td>
                                <?php
                                $prompt_preview = $profile['system_prompt'] ?? '';
                                echo esc_html( $prompt_preview !== ''
                                    ? mb_substr( $prompt_preview, 0, 80 ) . ( mb_strlen( $prompt_preview ) > 80 ? '…' : '' )
                                    : '—'
                                );
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=pl-profile-edit&slug=' . urlencode( $profile['slug'] ) ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Modifier', 'pedagolens-core' ); ?>
                                </a>
                                <button type="button" class="button button-small pl-duplicate-profile" data-slug="<?php echo esc_attr( $profile['slug'] ); ?>">
                                    <?php esc_html_e( 'Dupliquer', 'pedagolens-core' ); ?>
                                </button>
                                <button type="button" class="button button-small pl-toggle-profile" data-slug="<?php echo esc_attr( $profile['slug'] ); ?>" data-active="<?php echo $profile['is_active'] ? '1' : '0'; ?>">
                                    <?php echo $profile['is_active']
                                        ? esc_html__( 'Désactiver', 'pedagolens-core' )
                                        : esc_html__( 'Activer', 'pedagolens-core' ); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete pl-delete-profile" data-slug="<?php echo esc_attr( $profile['slug'] ); ?>">
                                    <?php esc_html_e( 'Supprimer', 'pedagolens-core' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Page édition
    // -------------------------------------------------------------------------

    public static function render_edit_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-core' ) );
        }

        $slug    = sanitize_text_field( $_GET['slug'] ?? '' );
        $profile = $slug ? PedagoLens_Profile_Manager::get( $slug ) : null;
        $is_new  = $profile === null;

        if ( $is_new ) {
            $profile = [
                'slug'             => '',
                'name'             => '',
                'description'      => '',
                'is_active'        => true,
                'sort_order'       => 1,
                'system_prompt'    => '',
                'resources'        => '',
                'scoring_grid'     => [],
                'inject_resources' => true,
                'inject_scoring'   => true,
            ];
        }

        $title = $is_new
            ? __( 'Ajouter un profil', 'pedagolens-core' )
            : sprintf( __( 'Modifier : %s', 'pedagolens-core' ), esc_html( $profile['name'] ) );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $title ); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( self::NONCE_SAVE, '_pl_nonce' ); ?>
                <input type="hidden" name="action" value="pl_save_profile">
                <input type="hidden" name="original_slug" value="<?php echo esc_attr( $slug ); ?>">

                <?php self::render_section_identity( $profile ); ?>
                <?php self::render_section_system_prompt( $profile ); ?>
                <?php self::render_section_resources( $profile ); ?>
                <?php self::render_section_scoring_grid( $profile ); ?>
                <?php self::render_section_preview( $profile ); ?>

                <p>
                    <?php submit_button( __( 'Enregistrer', 'pedagolens-core' ), 'primary', 'submit', false ); ?>
                    &nbsp;
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" class="button">
                        <?php esc_html_e( 'Annuler', 'pedagolens-core' ); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Sections du formulaire d'édition
    // -------------------------------------------------------------------------

    private static function render_section_identity( array $p ): void {
        ?>
        <h2><?php esc_html_e( 'A — Identité', 'pedagolens-core' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="pl_name"><?php esc_html_e( 'Nom', 'pedagolens-core' ); ?></label></th>
                <td>
                    <input type="text" id="pl_name" name="pl_name" value="<?php echo esc_attr( $p['name'] ); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="pl_slug"><?php esc_html_e( 'Slug', 'pedagolens-core' ); ?></label></th>
                <td>
                    <input type="text" id="pl_slug" name="pl_slug" value="<?php echo esc_attr( $p['slug'] ); ?>" class="regular-text" pattern="[a-z][a-z0-9_\-]*" required>
                    <p class="description"><?php esc_html_e( 'Minuscules, tirets et underscores uniquement. Auto-généré depuis le nom.', 'pedagolens-core' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="pl_description"><?php esc_html_e( 'Description', 'pedagolens-core' ); ?></label></th>
                <td>
                    <input type="text" id="pl_description" name="pl_description" value="<?php echo esc_attr( $p['description'] ); ?>" class="large-text" maxlength="200">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Statut', 'pedagolens-core' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pl_is_active" value="1" <?php checked( $p['is_active'] ); ?>>
                        <?php esc_html_e( 'Profil actif', 'pedagolens-core' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="pl_sort_order"><?php esc_html_e( 'Ordre', 'pedagolens-core' ); ?></label></th>
                <td>
                    <input type="number" id="pl_sort_order" name="pl_sort_order" value="<?php echo (int) $p['sort_order']; ?>" min="1" class="small-text">
                </td>
            </tr>
        </table>
        <hr>
        <?php
    }

    private static function render_section_system_prompt( array $p ): void {
        ?>
        <h2><?php esc_html_e( 'B — Prompt système IA', 'pedagolens-core' ); ?></h2>
        <textarea
            id="pl_system_prompt"
            name="pl_system_prompt"
            rows="16"
            class="large-text"
            style="font-family:monospace;min-height:400px;"
        ><?php echo esc_textarea( $p['system_prompt'] ); ?></textarea>
        <p>
            <label>
                <input type="checkbox" name="pl_inject_resources" value="1" <?php checked( $p['inject_resources'] ?? true ); ?>>
                <?php esc_html_e( 'Injecter les ressources dans le prompt', 'pedagolens-core' ); ?>
            </label>
            &nbsp;&nbsp;
            <label>
                <input type="checkbox" name="pl_inject_scoring" value="1" <?php checked( $p['inject_scoring'] ?? true ); ?>>
                <?php esc_html_e( 'Injecter la grille de scoring dans le prompt', 'pedagolens-core' ); ?>
            </label>
        </p>
        <hr>
        <?php
    }

    private static function render_section_resources( array $p ): void {
        ?>
        <h2><?php esc_html_e( 'C — Ressources scientifiques', 'pedagolens-core' ); ?></h2>
        <textarea
            id="pl_resources"
            name="pl_resources"
            rows="10"
            class="large-text"
            style="min-height:300px;"
            placeholder="<?php esc_attr_e( 'Références, articles, notes en Markdown…', 'pedagolens-core' ); ?>"
        ><?php echo esc_textarea( $p['resources'] ); ?></textarea>
        <hr>
        <?php
    }

    private static function render_section_scoring_grid( array $p ): void {
        $grid = ! empty( $p['scoring_grid'] ) ? $p['scoring_grid'] : [
            [ 'min' => 90, 'max' => 100, 'label' => 'Très accessible', 'color' => 'green' ],
            [ 'min' => 70, 'max' => 89,  'label' => 'Accessible',       'color' => 'blue' ],
            [ 'min' => 50, 'max' => 69,  'label' => 'Difficile',         'color' => 'yellow' ],
            [ 'min' => 30, 'max' => 49,  'label' => 'Très difficile',    'color' => 'orange' ],
            [ 'min' => 0,  'max' => 29,  'label' => 'Inaccessible',      'color' => 'red' ],
        ];
        ?>
        <h2><?php esc_html_e( 'D — Grille de scoring', 'pedagolens-core' ); ?></h2>
        <table class="widefat" style="max-width:600px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Min', 'pedagolens-core' ); ?></th>
                    <th><?php esc_html_e( 'Max', 'pedagolens-core' ); ?></th>
                    <th><?php esc_html_e( 'Label', 'pedagolens-core' ); ?></th>
                    <th><?php esc_html_e( 'Couleur', 'pedagolens-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $grid as $i => $row ) : ?>
                    <tr>
                        <td><input type="number" name="pl_grid[<?php echo $i; ?>][min]" value="<?php echo (int) $row['min']; ?>" min="0" max="100" class="small-text"></td>
                        <td><input type="number" name="pl_grid[<?php echo $i; ?>][max]" value="<?php echo (int) $row['max']; ?>" min="0" max="100" class="small-text"></td>
                        <td><input type="text"   name="pl_grid[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $row['label'] ); ?>" class="regular-text"></td>
                        <td>
                            <select name="pl_grid[<?php echo $i; ?>][color]">
                                <?php foreach ( [ 'green', 'blue', 'yellow', 'orange', 'red', 'grey' ] as $color ) : ?>
                                    <option value="<?php echo esc_attr( $color ); ?>" <?php selected( $row['color'], $color ); ?>><?php echo esc_html( $color ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <hr>
        <?php
    }

    private static function render_section_preview( array $p ): void {
        ?>
        <h2><?php esc_html_e( 'E — Prévisualisation', 'pedagolens-core' ); ?></h2>
        <button type="button" id="pl-preview-prompt" class="button">
            <?php esc_html_e( 'Prévisualiser le prompt complet', 'pedagolens-core' ); ?>
        </button>
        <div id="pl-preview-modal" style="display:none;margin-top:12px;padding:16px;background:#f6f7f7;border:1px solid #ccd0d4;border-radius:4px;">
            <pre id="pl-preview-content" style="white-space:pre-wrap;font-family:monospace;font-size:13px;"></pre>
            <p id="pl-preview-tokens" style="color:#666;font-size:12px;"></p>
        </div>
        <hr>
        <?php
    }

    // -------------------------------------------------------------------------
    // Sauvegarde
    // -------------------------------------------------------------------------

    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-core' ) );
        }

        check_admin_referer( self::NONCE_SAVE, '_pl_nonce' );

        $grid = [];
        foreach ( $_POST['pl_grid'] ?? [] as $row ) {
            $grid[] = [
                'min'   => (int) ( $row['min'] ?? 0 ),
                'max'   => (int) ( $row['max'] ?? 100 ),
                'label' => sanitize_text_field( $row['label'] ?? '' ),
                'color' => sanitize_text_field( $row['color'] ?? 'grey' ),
            ];
        }

        $profile_data = [
            'slug'             => sanitize_text_field( $_POST['pl_slug'] ?? '' ),
            'name'             => sanitize_text_field( $_POST['pl_name'] ?? '' ),
            'description'      => sanitize_text_field( $_POST['pl_description'] ?? '' ),
            'is_active'        => isset( $_POST['pl_is_active'] ),
            'sort_order'       => (int) ( $_POST['pl_sort_order'] ?? 1 ),
            'system_prompt'    => sanitize_textarea_field( $_POST['pl_system_prompt'] ?? '' ),
            'resources'        => sanitize_textarea_field( $_POST['pl_resources'] ?? '' ),
            'scoring_grid'     => $grid,
            'inject_resources' => isset( $_POST['pl_inject_resources'] ),
            'inject_scoring'   => isset( $_POST['pl_inject_scoring'] ),
        ];

        $saved = PedagoLens_Profile_Manager::save( $profile_data );

        $notice = $saved
            ? __( 'Profil enregistré.', 'pedagolens-core' )
            : __( 'Erreur : slug invalide ou en conflit.', 'pedagolens-core' );

        set_transient( 'pl_profile_notice', $notice, 30 );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    public static function ajax_toggle(): void {
        self::verify_ajax_nonce();

        $slug    = sanitize_text_field( $_POST['slug'] ?? '' );
        $profile = PedagoLens_Profile_Manager::get( $slug );

        if ( ! $profile ) {
            wp_send_json_error( [ 'message' => 'Profil introuvable.' ] );
        }

        $profile['is_active'] = ! $profile['is_active'];
        PedagoLens_Profile_Manager::save( $profile );

        wp_send_json_success( [ 'is_active' => $profile['is_active'] ] );
    }

    public static function ajax_reorder(): void {
        self::verify_ajax_nonce();

        $slugs = array_map( 'sanitize_text_field', $_POST['slugs'] ?? [] );
        PedagoLens_Profile_Manager::reorder( $slugs );

        wp_send_json_success();
    }

    public static function ajax_delete(): void {
        self::verify_ajax_nonce();

        $slug   = sanitize_text_field( $_POST['slug'] ?? '' );
        $result = PedagoLens_Profile_Manager::delete( $slug );

        if ( $result ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( [ 'message' => __( 'Suppression impossible : profil utilisé dans des analyses existantes.', 'pedagolens-core' ) ] );
        }
    }

    public static function ajax_duplicate(): void {
        self::verify_ajax_nonce();

        $slug     = sanitize_text_field( $_POST['slug'] ?? '' );
        $new_slug = $slug . '-copie-' . time();
        $result   = PedagoLens_Profile_Manager::duplicate( $slug, $new_slug );

        if ( $result ) {
            wp_send_json_success( [ 'new_slug' => $new_slug ] );
        } else {
            wp_send_json_error( [ 'message' => 'Duplication échouée.' ] );
        }
    }

    public static function ajax_export(): void {
        self::verify_ajax_nonce();

        $profiles = PedagoLens_Profile_Manager::get_all( active_only: false );
        wp_send_json_success( [ 'profiles' => $profiles ] );
    }

    public static function ajax_import(): void {
        self::verify_ajax_nonce();

        // Accept raw JSON (not sanitized yet — save() handles sanitization)
        $raw_json = wp_unslash( $_POST['json'] ?? '' );
        $profile  = json_decode( $raw_json, true );

        if ( ! is_array( $profile ) || empty( $profile['slug'] ) ) {
            wp_send_json_error( [ 'message' => 'JSON invalide ou slug manquant.' ] );
        }

        // Map system_prompt_template → system_prompt if needed
        if ( empty( $profile['system_prompt'] ) && ! empty( $profile['system_prompt_template'] ) ) {
            $profile['system_prompt'] = $profile['system_prompt_template'];
        }

        // Map references array → resources text if needed
        if ( empty( $profile['resources'] ) && ! empty( $profile['references'] ) && is_array( $profile['references'] ) ) {
            $profile['resources'] = implode( "\n", $profile['references'] );
        }

        // Ensure defaults for fields the rich JSON may not have
        if ( ! isset( $profile['is_active'] ) ) {
            $profile['is_active'] = true;
        }
        if ( ! isset( $profile['scoring_grid'] ) ) {
            $profile['scoring_grid'] = null; // save() will use default
        }

        $existing = PedagoLens_Profile_Manager::get( $profile['slug'] );
        if ( $existing && empty( $_POST['overwrite'] ) ) {
            wp_send_json_error( [
                'message'   => 'Un profil avec ce slug existe déjà.',
                'conflict'  => true,
                'slug'      => $profile['slug'],
            ] );
        }

        $result = PedagoLens_Profile_Manager::save( $profile );
        $result ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Sauvegarde échouée — slug invalide ou conflit.' ] );
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private static function verify_ajax_nonce(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }
        check_ajax_referer( self::NONCE_AJAX, 'nonce' );
    }
}
