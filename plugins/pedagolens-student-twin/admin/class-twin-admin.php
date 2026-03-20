<?php
/**
 * PedagoLens_Twin_Admin
 *
 * Interface admin du jumeau numérique :
 * - Page de configuration (garde-fous, comportement)
 * - Interface de conversation étudiant (shortcode + page admin)
 * - Historique des sessions
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Twin_Admin {

    private const MENU_SLUG  = 'pl-student-twin';
    private const NONCE_AJAX = 'pl_twin_ajax';
    private const NONCE_SAVE = 'pl_twin_settings_save';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action( 'admin_menu',                        [ self::class, 'add_menu' ] );
        add_action( 'admin_post_pl_save_twin_settings',  [ self::class, 'handle_save_settings' ] );
        add_action( 'wp_ajax_pl_twin_start_session',     [ self::class, 'ajax_start_session' ] );
        add_action( 'wp_ajax_pl_twin_send_message',      [ self::class, 'ajax_send_message' ] );
        add_action( 'wp_ajax_pl_twin_end_session',       [ self::class, 'ajax_end_session' ] );
        add_action( 'wp_ajax_pl_twin_get_history',       [ self::class, 'ajax_get_history' ] );
        add_action( 'wp_ajax_nopriv_pl_twin_start_session', [ self::class, 'ajax_start_session' ] );
        add_action( 'wp_ajax_nopriv_pl_twin_send_message',  [ self::class, 'ajax_send_message' ] );
        add_action( 'wp_ajax_nopriv_pl_twin_end_session',   [ self::class, 'ajax_end_session' ] );
        add_action( 'admin_enqueue_scripts',             [ self::class, 'enqueue_assets' ] );
        add_shortcode( 'pedagolens_twin',                [ self::class, 'render_shortcode' ] );
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public static function add_menu(): void {
        global $menu;

        $bridge_menu_exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'pl-api-bridge-settings' ) {
                    $bridge_menu_exists = true;
                    break;
                }
            }
        }

        $parent = $bridge_menu_exists ? 'pl-api-bridge-settings' : 'pl-pedagolens';

        add_submenu_page(
            $parent,
            __( 'Jumeau Étudiant', 'pedagolens-student-twin' ),
            __( 'Jumeau Étudiant', 'pedagolens-student-twin' ),
            'manage_options',
            self::MENU_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
            return;
        }

        wp_enqueue_script(
            'pl-twin-admin',
            PL_TWIN_PLUGIN_URL . 'assets/js/twin-admin.js',
            [ 'jquery' ],
            PL_TWIN_VERSION,
            true
        );

        wp_localize_script( 'pl-twin-admin', 'plTwin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_AJAX ),
        ] );

        wp_enqueue_style(
            'pl-twin-admin',
            PL_TWIN_PLUGIN_URL . 'assets/css/twin-admin.css',
            [],
            PL_TWIN_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // Page principale (onglets)
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-student-twin' ) );
        }

        $tab = sanitize_key( $_GET['tab'] ?? 'settings' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Jumeau Numérique Étudiant', 'pedagolens-student-twin' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php
                $tabs = [
                    'settings' => __( 'Configuration', 'pedagolens-student-twin' ),
                    'demo'     => __( 'Démo conversation', 'pedagolens-student-twin' ),
                    'sessions' => __( 'Sessions', 'pedagolens-student-twin' ),
                ];
                foreach ( $tabs as $key => $label ) :
                    $active = $tab === $key ? ' nav-tab-active' : '';
                    $url    = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $key );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo $active; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="pl-twin-tab-content">
                <?php
                match ( $tab ) {
                    'demo'     => self::render_demo_tab(),
                    'sessions' => self::render_sessions_tab(),
                    default    => self::render_settings_tab(),
                };
                ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Onglet Configuration
    // -------------------------------------------------------------------------

    private static function render_settings_tab(): void {
        $raw    = get_option( 'pl_guardrails_config', [] );
        $config = is_string( $raw ) ? ( json_decode( $raw, true ) ?? [] ) : (array) $raw;

        $max_length       = (int) ( $config['max_length'] ?? 2000 );
        $forbidden_topics = implode( "\n", (array) ( $config['forbidden_topics'] ?? [] ) );
        $ai_enabled       = ! empty( $config['ai_guardrail_enabled'] );

        $twin_raw      = get_option( 'pl_student_twin_settings', [] );
        $twin_settings = is_string( $twin_raw ) ? ( json_decode( $twin_raw, true ) ?? [] ) : (array) $twin_raw;
        $twin_name     = sanitize_text_field( $twin_settings['twin_name'] ?? 'Léa' );
        $twin_intro    = sanitize_textarea_field( $twin_settings['intro_message'] ?? '' );

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Paramètres enregistrés.', 'pedagolens-student-twin' ) . '</p></div>';
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( self::NONCE_SAVE, '_pl_twin_nonce' ); ?>
            <input type="hidden" name="action" value="pl_save_twin_settings">

            <h2><?php esc_html_e( 'Comportement du jumeau', 'pedagolens-student-twin' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="twin_name"><?php esc_html_e( 'Nom du jumeau', 'pedagolens-student-twin' ); ?></label></th>
                    <td><input type="text" id="twin_name" name="twin_name" value="<?php echo esc_attr( $twin_name ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="intro_message"><?php esc_html_e( 'Message d\'introduction', 'pedagolens-student-twin' ); ?></label></th>
                    <td>
                        <textarea id="intro_message" name="intro_message" rows="4" class="large-text"><?php echo esc_textarea( $twin_intro ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Message affiché au début de chaque session.', 'pedagolens-student-twin' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Garde-fous', 'pedagolens-student-twin' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="max_length"><?php esc_html_e( 'Longueur max du message (caractères)', 'pedagolens-student-twin' ); ?></label></th>
                    <td><input type="number" id="max_length" name="max_length" value="<?php echo esc_attr( $max_length ); ?>" min="100" max="10000" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="forbidden_topics"><?php esc_html_e( 'Sujets interdits', 'pedagolens-student-twin' ); ?></label></th>
                    <td>
                        <textarea id="forbidden_topics" name="forbidden_topics" rows="6" class="large-text"><?php echo esc_textarea( $forbidden_topics ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Un mot-clé par ligne.', 'pedagolens-student-twin' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Vérification IA', 'pedagolens-student-twin' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="ai_guardrail_enabled" value="1" <?php checked( $ai_enabled ); ?>>
                            <?php esc_html_e( 'Activer la vérification IA des messages (appel Bedrock supplémentaire)', 'pedagolens-student-twin' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Enregistrer', 'pedagolens-student-twin' ) ); ?>
        </form>
        <?php
    }

    // -------------------------------------------------------------------------
    // Onglet Démo
    // -------------------------------------------------------------------------

    private static function render_demo_tab(): void {
        $twin_raw      = get_option( 'pl_student_twin_settings', [] );
        $twin_settings = is_string( $twin_raw ) ? ( json_decode( $twin_raw, true ) ?? [] ) : (array) $twin_raw;
        $twin_name     = esc_html( $twin_settings['twin_name'] ?? 'Léa' );
        $intro_message = esc_html( $twin_settings['intro_message'] ?? 'Bonjour ! Je suis ton jumeau numérique. Comment puis-je t\'aider avec ton cours ?' );

        $courses = get_posts( [ 'post_type' => 'pl_course', 'posts_per_page' => -1, 'post_status' => 'publish' ] );
        ?>
        <div class="pl-twin-demo-wrap">
            <div class="pl-twin-setup">
                <h3><?php esc_html_e( 'Démarrer une session de démo', 'pedagolens-student-twin' ); ?></h3>
                <label for="pl-demo-course"><?php esc_html_e( 'Cours :', 'pedagolens-student-twin' ); ?></label>
                <select id="pl-demo-course">
                    <option value="0"><?php esc_html_e( '— Sélectionner un cours —', 'pedagolens-student-twin' ); ?></option>
                    <?php foreach ( $courses as $c ) : ?>
                        <option value="<?php echo esc_attr( $c->ID ); ?>"><?php echo esc_html( $c->post_title ); ?></option>
                    <?php endforeach; ?>
                    <?php if ( empty( $courses ) ) : ?>
                        <option value="1"><?php esc_html_e( 'Français 101 (démo)', 'pedagolens-student-twin' ); ?></option>
                    <?php endif; ?>
                </select>
                <button type="button" id="pl-twin-start" class="button button-primary"><?php esc_html_e( 'Démarrer', 'pedagolens-student-twin' ); ?></button>
            </div>

            <div id="pl-twin-chat" style="display:none;">
                <div class="pl-chat-header">
                    <strong><?php echo $twin_name; ?></strong>
                    <button type="button" id="pl-twin-end" class="button button-small"><?php esc_html_e( 'Terminer la session', 'pedagolens-student-twin' ); ?></button>
                </div>
                <div id="pl-chat-messages" class="pl-chat-messages">
                    <div class="pl-chat-bubble pl-bubble-assistant"><?php echo $intro_message; ?></div>
                </div>
                <div class="pl-chat-input-row">
                    <textarea id="pl-chat-input" rows="2" placeholder="<?php esc_attr_e( 'Pose ta question…', 'pedagolens-student-twin' ); ?>"></textarea>
                    <button type="button" id="pl-chat-send" class="button button-primary"><?php esc_html_e( 'Envoyer', 'pedagolens-student-twin' ); ?></button>
                </div>
                <div id="pl-chat-follow-ups" class="pl-chat-follow-ups"></div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Onglet Sessions
    // -------------------------------------------------------------------------

    private static function render_sessions_tab(): void {
        $sessions = get_posts( [ 'post_type' => 'pl_interaction', 'posts_per_page' => 20, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC' ] );
        ?>
        <h2><?php esc_html_e( 'Sessions récentes', 'pedagolens-student-twin' ); ?></h2>
        <?php if ( empty( $sessions ) ) : ?>
            <p><?php esc_html_e( 'Aucune session enregistrée.', 'pedagolens-student-twin' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Session ID', 'pedagolens-student-twin' ); ?></th>
                    <th><?php esc_html_e( 'Étudiant', 'pedagolens-student-twin' ); ?></th>
                    <th><?php esc_html_e( 'Cours', 'pedagolens-student-twin' ); ?></th>
                    <th><?php esc_html_e( 'Démarrée', 'pedagolens-student-twin' ); ?></th>
                    <th><?php esc_html_e( 'Terminée', 'pedagolens-student-twin' ); ?></th>
                    <th><?php esc_html_e( 'Messages', 'pedagolens-student-twin' ); ?></th>
                    <th><?php esc_html_e( 'Garde-fous', 'pedagolens-student-twin' ); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ( $sessions as $s ) :
                        $session_id = get_post_meta( $s->ID, '_pl_session_id', true );
                        $student_id = (int) get_post_meta( $s->ID, '_pl_student_id', true );
                        $course_id  = (int) get_post_meta( $s->ID, '_pl_course_id',  true );
                        $started    = get_post_meta( $s->ID, '_pl_started_at', true );
                        $ended      = get_post_meta( $s->ID, '_pl_ended_at',   true );
                        $raw_msgs   = get_post_meta( $s->ID, '_pl_messages', true );
                        $msgs       = is_string( $raw_msgs ) ? json_decode( $raw_msgs, true ) : [];
                        $msg_count  = is_array( $msgs ) ? count( $msgs ) : 0;
                        $raw_grd    = get_post_meta( $s->ID, '_pl_guardrails_applied', true );
                        $grd        = is_string( $raw_grd ) ? json_decode( $raw_grd, true ) : [];
                        $grd_count  = is_array( $grd ) ? count( $grd ) : 0;
                        $student    = $student_id ? get_userdata( $student_id ) : null;
                        $course     = $course_id  ? get_post( $course_id )      : null;
                        ?>
                        <tr>
                            <td><code><?php echo esc_html( mb_substr( $session_id, 0, 8 ) . '…' ); ?></code></td>
                            <td><?php echo esc_html( $student ? $student->display_name : "#{$student_id}" ); ?></td>
                            <td><?php echo esc_html( $course ? $course->post_title : "#{$course_id}" ); ?></td>
                            <td><?php echo esc_html( $started ? wp_date( 'Y-m-d H:i', strtotime( $started ) ) : '—' ); ?></td>
                            <td><?php echo esc_html( $ended ? wp_date( 'Y-m-d H:i', strtotime( $ended ) ) : '—' ); ?></td>
                            <td><?php echo esc_html( $msg_count ); ?></td>
                            <td><?php echo $grd_count > 0 ? '<span style="color:#d63638;font-weight:600;">' . esc_html( $grd_count ) . '</span>' : '0'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    // -------------------------------------------------------------------------
    // Sauvegarde des settings
    // -------------------------------------------------------------------------

    public static function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-student-twin' ) );
        }
        check_admin_referer( self::NONCE_SAVE, '_pl_twin_nonce' );

        $forbidden_raw = sanitize_textarea_field( $_POST['forbidden_topics'] ?? '' );
        $forbidden     = array_filter( array_map( 'trim', explode( "\n", $forbidden_raw ) ) );

        $guardrail_config = [
            'max_length'           => max( 100, (int) ( $_POST['max_length'] ?? 2000 ) ),
            'forbidden_topics'     => array_values( $forbidden ),
            'ai_guardrail_enabled' => ! empty( $_POST['ai_guardrail_enabled'] ),
        ];
        update_option( 'pl_guardrails_config', wp_json_encode( $guardrail_config ) );

        $twin_settings = [
            'twin_name'     => sanitize_text_field( $_POST['twin_name'] ?? 'Léa' ),
            'intro_message' => sanitize_textarea_field( $_POST['intro_message'] ?? '' ),
        ];
        update_option( 'pl_student_twin_settings', wp_json_encode( $twin_settings ) );

        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=settings&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    public static function ajax_start_session(): void {
        self::verify_nonce();
        $course_id  = (int) ( $_POST['course_id']  ?? 0 );
        $student_id = get_current_user_id();
        $session_id = PedagoLens_Student_Twin::start_session( $student_id, $course_id );
        if ( empty( $session_id ) ) {
            wp_send_json_error( [ 'message' => 'Impossible de démarrer la session.' ] );
        }
        wp_send_json_success( [ 'session_id' => $session_id ] );
    }

    public static function ajax_send_message(): void {
        self::verify_nonce();
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        $message    = sanitize_textarea_field( $_POST['message'] ?? '' );
        if ( empty( $session_id ) || empty( $message ) ) {
            wp_send_json_error( [ 'message' => 'Paramètres manquants.' ] );
        }
        $result = PedagoLens_Student_Twin::send_message( $session_id, $message );
        if ( empty( $result['success'] ) ) {
            wp_send_json_error( [ 'message' => $result['error_message'] ?? 'Erreur.' ] );
        }
        wp_send_json_success( $result );
    }

    public static function ajax_end_session(): void {
        self::verify_nonce();
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        $ok         = PedagoLens_Student_Twin::end_session( $session_id );
        $ok ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Session introuvable.' ] );
    }

    public static function ajax_get_history(): void {
        self::verify_nonce();
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
        $result     = PedagoLens_Student_Twin::get_history( $session_id );
        if ( empty( $result['success'] ) ) {
            wp_send_json_error( [ 'message' => $result['error_message'] ?? 'Erreur.' ] );
        }
        wp_send_json_success( $result );
    }

    // -------------------------------------------------------------------------
    // Shortcode [pedagolens_twin]
    // -------------------------------------------------------------------------

    public static function render_shortcode( array $atts ): string {
        $atts = shortcode_atts( [ 'course_id' => 0 ], $atts );

        // --- Not logged in ---
        if ( ! is_user_logged_in() ) {
            wp_enqueue_style( 'pl-twin-front', PL_TWIN_PLUGIN_URL . 'assets/css/twin-admin.css', [], PL_TWIN_VERSION );
            ob_start();
            ?>
            <div class="pl-twin-dashboard pl-twin-logged-out">
                <div class="pl-twin-logged-out-card">
                    <span class="pl-twin-lock-icon" aria-hidden="true">🔒</span>
                    <h2><?php esc_html_e( 'Accès restreint', 'pedagolens-student-twin' ); ?></h2>
                    <p><?php esc_html_e( 'Connectez-vous pour accéder à votre jumeau numérique et commencer à apprendre.', 'pedagolens-student-twin' ); ?></p>
                    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="pl-twin-login-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 6px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg><?php esc_html_e( 'Se connecter', 'pedagolens-student-twin' ); ?>
                    </a>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // --- Logged-in student ---
        $twin_raw      = get_option( 'pl_student_twin_settings', [] );
        $twin_settings = is_string( $twin_raw ) ? ( json_decode( $twin_raw, true ) ?? [] ) : (array) $twin_raw;
        $twin_name     = esc_html( $twin_settings['twin_name'] ?? 'Léa' );
        $intro         = esc_html( $twin_settings['intro_message'] ?? 'Bonjour ! Je suis ton jumeau numérique. Comment puis-je t\'aider avec ton cours ?' );

        $courses = get_posts( [ 'post_type' => 'pl_course', 'posts_per_page' => -1, 'post_status' => 'publish' ] );

        $current_user_id = get_current_user_id();
        $sessions = get_posts( [
            'post_type' => 'pl_interaction', 'posts_per_page' => 15, 'post_status' => 'publish',
            'orderby' => 'date', 'order' => 'DESC',
            'meta_query' => [ [ 'key' => '_pl_student_id', 'value' => $current_user_id ] ],
        ] );

        wp_enqueue_script( 'pl-twin-front', PL_TWIN_PLUGIN_URL . 'assets/js/twin-admin.js', [ 'jquery' ], PL_TWIN_VERSION, true );
        wp_localize_script( 'pl-twin-front', 'plTwin', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( self::NONCE_AJAX ),
            'courseId'     => (int) $atts['course_id'],
            'twinName'     => $twin_name,
            'introMessage' => $intro,
            'i18n'         => [
                'send'           => __( 'Envoyer', 'pedagolens-student-twin' ),
                'typing'         => __( 'est en train d\'écrire…', 'pedagolens-student-twin' ),
                'sessionEnded'   => __( 'Session terminée. À bientôt !', 'pedagolens-student-twin' ),
                'networkError'   => __( 'Erreur réseau. Réessaie.', 'pedagolens-student-twin' ),
                'guardrailLabel' => __( 'Garde-fou déclenché', 'pedagolens-student-twin' ),
                'newSession'     => __( 'Nouvelle session', 'pedagolens-student-twin' ),
                'endSession'     => __( 'Terminer la session', 'pedagolens-student-twin' ),
            ],
        ] );
        wp_enqueue_style( 'pl-twin-front', PL_TWIN_PLUGIN_URL . 'assets/css/twin-admin.css', [], PL_TWIN_VERSION );

        ob_start();
        ?>
        <div class="pl-twin-dashboard" data-twin-name="<?php echo esc_attr( $twin_name ); ?>" data-intro="<?php echo esc_attr( $intro ); ?>">
            <header class="pl-twin-header">
                <div class="pl-twin-header-left">
                    <span class="pl-twin-robot-icon" aria-hidden="true">🤖</span>
                    <div>
                        <h1 class="pl-twin-title"><?php esc_html_e( 'Mon Jumeau Numérique', 'pedagolens-student-twin' ); ?></h1>
                        <span class="pl-twin-subtitle"><?php echo $twin_name; ?> · <span class="pl-twin-status-dot" aria-hidden="true"></span> <?php esc_html_e( 'En ligne', 'pedagolens-student-twin' ); ?></span>
                    </div>
                </div>
                <div class="pl-twin-header-right">
                    <label for="pl-twin-course-select" class="screen-reader-text"><?php esc_html_e( 'Sélectionner un cours', 'pedagolens-student-twin' ); ?></label>
                    <select id="pl-twin-course-select" class="pl-twin-course-select">
                        <option value="0"><?php esc_html_e( '— Choisir un cours —', 'pedagolens-student-twin' ); ?></option>
                        <?php foreach ( $courses as $c ) : ?>
                            <option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( (int) $atts['course_id'], $c->ID ); ?>><?php echo esc_html( $c->post_title ); ?></option>
                        <?php endforeach; ?>
                        <?php if ( empty( $courses ) ) : ?>
                            <option value="1"><?php esc_html_e( 'Cours démo', 'pedagolens-student-twin' ); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </header>
            <div class="pl-twin-body">
                <main class="pl-twin-chat-area">
                    <div id="pl-twin-messages" class="pl-twin-messages" role="log" aria-live="polite"></div>
                    <div id="pl-twin-follow-ups" class="pl-twin-follow-ups"></div>
                    <div class="pl-twin-input-bar">
                        <textarea id="pl-twin-input" class="pl-twin-input" rows="1" placeholder="<?php esc_attr_e( 'Pose ta question…', 'pedagolens-student-twin' ); ?>" disabled aria-label="<?php esc_attr_e( 'Message', 'pedagolens-student-twin' ); ?>"></textarea>
                        <button type="button" id="pl-twin-send" class="pl-twin-send-btn" disabled aria-label="<?php esc_attr_e( 'Envoyer', 'pedagolens-student-twin' ); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </div>
                </main>
                <aside class="pl-twin-sidebar">
                    <div class="pl-twin-sidebar-actions">
                        <button type="button" id="pl-twin-new-session" class="pl-twin-btn pl-twin-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            <?php esc_html_e( 'Nouvelle session', 'pedagolens-student-twin' ); ?>
                        </button>
                        <button type="button" id="pl-twin-end-session" class="pl-twin-btn pl-twin-btn-danger" disabled>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                            <?php esc_html_e( 'Terminer la session', 'pedagolens-student-twin' ); ?>
                        </button>
                    </div>
                    <div class="pl-twin-sidebar-history">
                        <h3 class="pl-twin-sidebar-title"><?php esc_html_e( 'Sessions précédentes', 'pedagolens-student-twin' ); ?></h3>
                        <ul id="pl-twin-history-list" class="pl-twin-history-list">
                            <?php if ( empty( $sessions ) ) : ?>
                                <li class="pl-twin-history-empty"><?php esc_html_e( 'Aucune session.', 'pedagolens-student-twin' ); ?></li>
                            <?php else : ?>
                                <?php foreach ( $sessions as $s ) :
                                    $sid     = get_post_meta( $s->ID, '_pl_session_id', true );
                                    $cid     = (int) get_post_meta( $s->ID, '_pl_course_id', true );
                                    $started = get_post_meta( $s->ID, '_pl_started_at', true );
                                    $ended   = get_post_meta( $s->ID, '_pl_ended_at', true );
                                    $course  = $cid ? get_post( $cid ) : null;
                                    $raw_m   = get_post_meta( $s->ID, '_pl_messages', true );
                                    $msgs    = is_string( $raw_m ) ? json_decode( $raw_m, true ) : [];
                                    $count   = is_array( $msgs ) ? count( $msgs ) : 0;
                                    ?>
                                    <li class="pl-twin-history-item" data-session-id="<?php echo esc_attr( $sid ); ?>">
                                        <span class="pl-twin-history-course"><?php echo esc_html( $course ? $course->post_title : '#' . $cid ); ?></span>
                                        <span class="pl-twin-history-meta">
                                            <?php echo esc_html( $started ? wp_date( 'd/m H:i', strtotime( $started ) ) : '—' ); ?>
                                            · <?php echo esc_html( $count ); ?> msg
                                            <?php if ( $ended ) : ?><span class="pl-twin-history-badge-ended">✓</span><?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private static function verify_nonce(): void {
        check_ajax_referer( self::NONCE_AJAX, 'nonce' );
    }
}
