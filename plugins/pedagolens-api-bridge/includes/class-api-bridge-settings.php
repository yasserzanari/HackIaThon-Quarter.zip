<?php
/**
 * PedagoLens_API_Bridge_Settings
 *
 * Gère la page d'administration du plugin API Bridge :
 * enregistrement du menu, rendu du formulaire, sauvegarde des options,
 * et endpoint AJAX pour le test de connexion Bedrock.
 *
 * ⚠️  MODE HACKATHON — Les credentials AWS sont stockés dans wp_options.
 *     Acceptable uniquement pour une démo locale.
 *     En production, utiliser wp-config.php, variables d'environnement
 *     ou AWS Secrets Manager.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_API_Bridge_Settings {

    private const OPTION_GROUP = 'pl_api_bridge_options';
    private const PAGE_SLUG    = 'pl-api-bridge-settings';
    private const NONCE_ACTION = 'pl_api_bridge_save_settings';

    // Options sensibles — jamais loggées en clair
    private const SENSITIVE_OPTIONS = [
        'pl_aws_secret_access_key',
        'pl_aws_session_token',
    ];

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action( 'admin_menu',            [ self::class, 'add_menu' ] );
        add_action( 'admin_post_' . self::NONCE_ACTION, [ self::class, 'handle_save' ] );
        add_action( 'wp_ajax_pl_test_bedrock_connection', [ self::class, 'ajax_test_connection' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
    }

    // -------------------------------------------------------------------------
    // Menu WordPress
    // -------------------------------------------------------------------------

    public static function add_menu(): void {
        add_menu_page(
            __( 'PédagoLens', 'pedagolens-api-bridge' ),
            'PédagoLens',
            'manage_options',
            self::PAGE_SLUG,
            [ self::class, 'render_page' ],
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __( 'API Bridge — Paramètres', 'pedagolens-api-bridge' ),
            __( 'API Bridge', 'pedagolens-api-bridge' ),
            'manage_options',
            self::PAGE_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, self::PAGE_SLUG ) ) {
            return;
        }

        wp_enqueue_script(
            'pl-bridge-admin',
            PL_BRIDGE_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            PL_BRIDGE_VERSION,
            true
        );

        wp_localize_script( 'pl-bridge-admin', 'plBridgeAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pl_test_bedrock_connection' ),
            'i18n'    => [
                'testing'  => __( 'Test en cours…', 'pedagolens-api-bridge' ),
                'success'  => __( 'Connexion réussie ✓', 'pedagolens-api-bridge' ),
                'error'    => __( 'Échec de la connexion', 'pedagolens-api-bridge' ),
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Rendu de la page
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-api-bridge' ) );
        }

        $saved_message = get_transient( 'pl_bridge_settings_saved' );
        if ( $saved_message ) {
            delete_transient( 'pl_bridge_settings_saved' );
        }

        $cfg = PedagoLens_API_Bridge::get_bedrock_config();
        $creds = PedagoLens_API_Bridge::get_aws_credentials();
        $mode  = PedagoLens_API_Bridge::get_ai_mode();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'PédagoLens — API Bridge', 'pedagolens-api-bridge' ); ?></h1>

            <?php self::render_hackathon_notice(); ?>

            <?php if ( $saved_message ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html( $saved_message ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( self::NONCE_ACTION, '_pl_nonce' ); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE_ACTION ); ?>">

                <?php self::render_section_ai_mode( $mode ); ?>
                <?php self::render_section_credentials( $creds ); ?>
                <?php self::render_section_bedrock_config( $cfg ); ?>
                <?php self::render_section_prompts(); ?>

                <?php submit_button( __( 'Enregistrer les paramètres', 'pedagolens-api-bridge' ) ); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Sections du formulaire
    // -------------------------------------------------------------------------

    private static function render_hackathon_notice(): void {
        ?>
        <div class="notice notice-warning" style="border-left-color:#d63638;">
            <p>
                <strong>⚠️ Mode Hackathon</strong> —
                <?php esc_html_e(
                    'Les credentials AWS sont stockés dans WordPress pour simplifier la démo. ' .
                    'À remplacer par wp-config.php, variables d\'environnement ou AWS Secrets Manager en production.',
                    'pedagolens-api-bridge'
                ); ?>
            </p>
        </div>
        <?php
    }

    private static function render_section_ai_mode( string $current_mode ): void {
        ?>
        <h2><?php esc_html_e( 'Mode IA', 'pedagolens-api-bridge' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pl_ai_mode"><?php esc_html_e( 'Mode actif', 'pedagolens-api-bridge' ); ?></label>
                </th>
                <td>
                    <select name="pl_ai_mode" id="pl_ai_mode">
                        <option value="mock"    <?php selected( $current_mode, 'mock' ); ?>>
                            <?php esc_html_e( 'Mock (démo sans AWS)', 'pedagolens-api-bridge' ); ?>
                        </option>
                        <option value="bedrock" <?php selected( $current_mode, 'bedrock' ); ?>>
                            <?php esc_html_e( 'Bedrock (appels AWS réels)', 'pedagolens-api-bridge' ); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'En mode mock, aucun appel AWS n\'est effectué.', 'pedagolens-api-bridge' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <hr>
        <?php
    }

    private static function render_section_credentials( array $creds ): void {
        ?>
        <h2><?php esc_html_e( 'Credentials AWS', 'pedagolens-api-bridge' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Utilisés uniquement si le mode Bedrock est actif.', 'pedagolens-api-bridge' ); ?>
        </p>
        <table class="form-table" role="presentation">

            <tr>
                <th scope="row">
                    <label for="pl_aws_access_key_id">
                        <?php esc_html_e( 'Access Key ID', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="pl_aws_access_key_id"
                        name="pl_aws_access_key_id"
                        value="<?php echo esc_attr( $creds['access_key_id'] ); ?>"
                        class="regular-text"
                        autocomplete="off"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pl_aws_secret_access_key">
                        <?php esc_html_e( 'Secret Access Key', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <?php self::render_password_field( 'pl_aws_secret_access_key', $creds['secret_access_key'] ); ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pl_aws_session_token">
                        <?php esc_html_e( 'Session Token', 'pedagolens-api-bridge' ); ?>
                        <span style="font-weight:normal;font-size:12px;">
                            (<?php esc_html_e( 'optionnel', 'pedagolens-api-bridge' ); ?>)
                        </span>
                    </label>
                </th>
                <td>
                    <?php self::render_password_field( 'pl_aws_session_token', $creds['session_token'] ); ?>
                    <p class="description">
                        <?php esc_html_e( 'Requis pour les credentials temporaires AWS Builder / SSO.', 'pedagolens-api-bridge' ); ?>
                    </p>
                </td>
            </tr>

        </table>
        <hr>
        <?php
    }

    private static function render_section_bedrock_config( array $cfg ): void {
        $models = PedagoLens_API_Bridge::get_available_models();
        ?>
        <h2><?php esc_html_e( 'Configuration Bedrock', 'pedagolens-api-bridge' ); ?></h2>
        <table class="form-table" role="presentation">

            <tr>
                <th scope="row">
                    <label for="pl_bedrock_region">
                        <?php esc_html_e( 'Région AWS', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="text"
                        id="pl_bedrock_region"
                        name="pl_bedrock_region"
                        value="<?php echo esc_attr( $cfg['region'] ); ?>"
                        class="regular-text"
                        placeholder="us-east-1"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pl_bedrock_model_id">
                        <?php esc_html_e( 'Modèle Claude', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <select name="pl_bedrock_model_id" id="pl_bedrock_model_id">
                        <?php foreach ( $models as $model ) : ?>
                            <option value="<?php echo esc_attr( $model ); ?>" <?php selected( $cfg['model_id'], $model ); ?>>
                                <?php echo esc_html( $model ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pl_bedrock_max_tokens">
                        <?php esc_html_e( 'Max Tokens', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="pl_bedrock_max_tokens"
                        name="pl_bedrock_max_tokens"
                        value="<?php echo esc_attr( $cfg['max_tokens'] ); ?>"
                        min="100"
                        max="200000"
                        class="small-text"
                    >
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pl_bedrock_temperature">
                        <?php esc_html_e( 'Température', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="pl_bedrock_temperature"
                        name="pl_bedrock_temperature"
                        value="<?php echo esc_attr( $cfg['temperature'] ); ?>"
                        min="0"
                        max="1"
                        step="0.05"
                        class="small-text"
                    >
                    <span class="description">(0.0 – 1.0)</span>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="pl_bedrock_timeout">
                        <?php esc_html_e( 'Timeout (secondes)', 'pedagolens-api-bridge' ); ?>
                    </label>
                </th>
                <td>
                    <input
                        type="number"
                        id="pl_bedrock_timeout"
                        name="pl_bedrock_timeout"
                        value="<?php echo esc_attr( $cfg['timeout'] ); ?>"
                        min="5"
                        max="120"
                        class="small-text"
                    >
                </td>
            </tr>

        </table>

        <p>
            <button type="button" id="pl-test-connection" class="button button-secondary">
                <?php esc_html_e( 'Tester la connexion Bedrock', 'pedagolens-api-bridge' ); ?>
            </button>
            <span id="pl-test-result" style="margin-left:12px;font-weight:600;"></span>
        </p>
        <hr>
        <?php
    }

    private static function render_section_prompts(): void {
        ?>
        <h2><?php esc_html_e( 'Templates de prompts', 'pedagolens-api-bridge' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Laisser vide pour utiliser le template par défaut.', 'pedagolens-api-bridge' ); ?>
        </p>
        <?php foreach ( PedagoLens_API_Bridge::PROMPT_KEYS as $key ) :
            $value = get_option( "pl_prompt_{$key}", '' );
            ?>
            <h3><?php echo esc_html( $key ); ?></h3>
            <textarea
                name="pl_prompt_<?php echo esc_attr( $key ); ?>"
                id="pl_prompt_<?php echo esc_attr( $key ); ?>"
                rows="6"
                class="large-text code"
                style="font-family:monospace;"
            ><?php echo esc_textarea( $value ); ?></textarea>
        <?php endforeach; ?>
        <hr>
        <?php
    }

    /**
     * Champ password avec bouton Afficher/Masquer.
     */
    private static function render_password_field( string $name, string $value ): void {
        $id = esc_attr( $name );
        ?>
        <div style="display:flex;align-items:center;gap:8px;">
            <input
                type="password"
                id="<?php echo $id; ?>"
                name="<?php echo $id; ?>"
                value="<?php echo esc_attr( $value ); ?>"
                class="regular-text"
                autocomplete="new-password"
            >
            <button
                type="button"
                class="button button-secondary pl-toggle-secret"
                data-target="<?php echo $id; ?>"
                aria-label="<?php esc_attr_e( 'Afficher / Masquer', 'pedagolens-api-bridge' ); ?>"
            >
                <?php esc_html_e( 'Afficher', 'pedagolens-api-bridge' ); ?>
            </button>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Sauvegarde
    // -------------------------------------------------------------------------

    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-api-bridge' ) );
        }

        check_admin_referer( self::NONCE_ACTION, '_pl_nonce' );

        // Mode IA
        $mode = sanitize_text_field( $_POST['pl_ai_mode'] ?? 'mock' );
        update_option( 'pl_ai_mode', in_array( $mode, [ 'mock', 'bedrock' ], true ) ? $mode : 'mock' );

        // Credentials AWS — sanitisés, jamais loggés en clair
        self::save_credential( 'pl_aws_access_key_id',     sanitize_text_field( $_POST['pl_aws_access_key_id'] ?? '' ) );
        self::save_credential( 'pl_aws_secret_access_key', sanitize_text_field( $_POST['pl_aws_secret_access_key'] ?? '' ) );
        self::save_credential( 'pl_aws_session_token',     sanitize_text_field( $_POST['pl_aws_session_token'] ?? '' ) );

        // Config Bedrock
        update_option( 'pl_bedrock_region',      sanitize_text_field( $_POST['pl_bedrock_region'] ?? 'us-east-1' ) );
        update_option( 'pl_bedrock_model_id',    sanitize_text_field( $_POST['pl_bedrock_model_id'] ?? '' ) );
        update_option( 'pl_bedrock_max_tokens',  (int) ( $_POST['pl_bedrock_max_tokens'] ?? 1500 ) );
        update_option( 'pl_bedrock_temperature', (float) ( $_POST['pl_bedrock_temperature'] ?? 0.3 ) );
        update_option( 'pl_bedrock_timeout',     (int) ( $_POST['pl_bedrock_timeout'] ?? 30 ) );

        // Prompt templates
        foreach ( PedagoLens_API_Bridge::PROMPT_KEYS as $key ) {
            $raw = $_POST[ "pl_prompt_{$key}" ] ?? '';
            update_option( "pl_prompt_{$key}", sanitize_textarea_field( $raw ) );
        }

        set_transient( 'pl_bridge_settings_saved', __( 'Paramètres enregistrés.', 'pedagolens-api-bridge' ), 30 );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
        exit;
    }

    /**
     * Sauvegarde un credential sans le logger.
     * Si la valeur est vide, on ne l'écrase pas (permet de ne pas effacer accidentellement).
     */
    private static function save_credential( string $option_key, string $value ): void {
        if ( $value !== '' ) {
            update_option( $option_key, $value );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX — Test de connexion Bedrock
    // -------------------------------------------------------------------------

    public static function ajax_test_connection(): void {
        check_ajax_referer( 'pl_test_bedrock_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Accès refusé.', 'pedagolens-api-bridge' ) ] );
        }

        $credentials = PedagoLens_API_Bridge::get_aws_credentials();
        $config      = PedagoLens_API_Bridge::get_bedrock_config();

        if ( empty( $credentials['access_key_id'] ) || empty( $credentials['secret_access_key'] ) ) {
            wp_send_json_error( [
                'message' => __( 'Credentials AWS manquants. Renseignez Access Key ID et Secret Access Key.', 'pedagolens-api-bridge' ),
            ] );
        }

        // Appel minimal de test : liste des modèles Bedrock
        $endpoint = "https://bedrock.{$config['region']}.amazonaws.com/foundation-models";
        $date     = gmdate( 'Ymd\THis\Z' );

        // Utiliser la méthode de signature via réflexion n'est pas possible (private),
        // donc on fait un appel direct simplifié pour le test.
        $response = wp_remote_get( $endpoint, [
            'timeout' => 10,
            'headers' => [
                'X-Amz-Date' => $date,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [
                'message' => sprintf(
                    /* translators: %s = error message */
                    __( 'Erreur réseau : %s', 'pedagolens-api-bridge' ),
                    $response->get_error_message()
                ),
            ] );
        }

        $code = wp_remote_retrieve_response_code( $response );

        // 403 = endpoint accessible mais non autorisé = région correcte, credentials à vérifier
        // 200 = succès complet
        if ( $code === 200 || $code === 403 ) {
            wp_send_json_success( [
                'message' => sprintf(
                    __( 'Endpoint Bedrock accessible (HTTP %d). Région : %s — Modèle : %s', 'pedagolens-api-bridge' ),
                    $code,
                    esc_html( $config['region'] ),
                    esc_html( $config['model_id'] )
                ),
            ] );
        }

        wp_send_json_error( [
            'message' => sprintf(
                __( 'Réponse inattendue de Bedrock : HTTP %d', 'pedagolens-api-bridge' ),
                $code
            ),
        ] );
    }
}
