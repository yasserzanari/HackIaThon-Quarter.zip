<?php
/**
 * PedagoLens_Workbench_Admin
 *
 * Interface admin du workbench :
 * - Vue projet avec sections et panneau latéral de scores
 * - Suggestions IA avec delta d'impact par profil
 * - Actions apply / reject / historique
 * - Éditeur de sections inline
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Workbench_Admin {

    private const MENU_SLUG  = 'pl-course-workbench';
    private const NONCE_AJAX = 'pl_workbench_ajax';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action( 'admin_menu',                            [ self::class, 'add_menu' ] );
        add_action( 'wp_ajax_pl_get_suggestions',            [ self::class, 'ajax_get_suggestions' ] );
        add_action( 'wp_ajax_pl_apply_suggestion',           [ self::class, 'ajax_apply_suggestion' ] );
        add_action( 'wp_ajax_pl_reject_suggestion',          [ self::class, 'ajax_reject_suggestion' ] );
        add_action( 'wp_ajax_pl_save_section',               [ self::class, 'ajax_save_section' ] );
        add_action( 'wp_ajax_pl_get_versions',               [ self::class, 'ajax_get_versions' ] );
        add_action( 'wp_ajax_pl_add_section',                [ self::class, 'ajax_add_section' ] );
        add_action( 'wp_ajax_pl_upload_file',                [ self::class, 'ajax_upload_file' ] );
        add_action( 'wp_ajax_pl_preview_suggestion',         [ self::class, 'ajax_preview_suggestion' ] );
        add_action( 'wp_ajax_pl_download_modified',          [ self::class, 'ajax_download_modified' ] );
        add_action( 'wp_ajax_pl_analyze_all_sections',       [ self::class, 'ajax_analyze_all_sections' ] );
        add_action( 'admin_enqueue_scripts',                 [ self::class, 'enqueue_assets' ] );
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
            __( 'Workbench', 'pedagolens-course-workbench' ),
            __( 'Workbench', 'pedagolens-course-workbench' ),
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
            'pl-workbench-admin',
            PL_WORKBENCH_PLUGIN_URL . 'assets/js/workbench-admin.js',
            [ 'jquery' ],
            PL_WORKBENCH_VERSION,
            true
        );

        wp_localize_script( 'pl-workbench-admin', 'plWorkbench', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( self::NONCE_AJAX ),
            'projectId'  => (int) ( $_GET['project_id'] ?? 0 ),
        ] );

        wp_enqueue_style(
            'pl-workbench-admin',
            PL_WORKBENCH_PLUGIN_URL . 'assets/css/workbench-admin.css',
            [],
            PL_WORKBENCH_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // Page principale
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-course-workbench' ) );
        }

        $project_id = (int) ( $_GET['project_id'] ?? 0 );

        if ( ! $project_id ) {
            self::render_project_list();
            return;
        }

        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== 'pl_project' ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Projet introuvable.', 'pedagolens-course-workbench' ) . '</p></div>';
            return;
        }

        self::render_workbench( $project );
    }

    // -------------------------------------------------------------------------
    // Liste des projets
    // -------------------------------------------------------------------------

    private static function render_project_list(): void {
        $projects = get_posts( [
            'post_type'      => 'pl_project',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Workbench — Projets', 'pedagolens-course-workbench' ); ?></h1>

            <?php if ( empty( $projects ) ) : ?>
                <p><?php esc_html_e( 'Aucun projet. Créez-en un depuis le tableau de bord enseignant.', 'pedagolens-course-workbench' ); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Titre', 'pedagolens-course-workbench' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'pedagolens-course-workbench' ); ?></th>
                            <th><?php esc_html_e( 'Modifié le', 'pedagolens-course-workbench' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $projects as $p ) : ?>
                            <tr>
                                <td><?php echo esc_html( $p->post_title ); ?></td>
                                <td><code><?php echo esc_html( get_post_meta( $p->ID, '_pl_project_type', true ) ); ?></code></td>
                                <td><?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( get_post_meta( $p->ID, '_pl_updated_at', true ) ?: $p->post_modified ) ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&project_id=' . $p->ID ) ); ?>" class="button button-primary button-small">
                                        <?php esc_html_e( 'Ouvrir', 'pedagolens-course-workbench' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Vue workbench d'un projet
    // -------------------------------------------------------------------------

    private static function render_workbench( WP_Post $project ): void {
        $project_type = get_post_meta( $project->ID, '_pl_project_type', true ) ?: 'magistral';
        $sections     = PedagoLens_Course_Workbench::get_content_sections( $project->ID );

        // Decode broken Unicode escapes in section data
        foreach ( $sections as &$s ) {
            if ( isset( $s['title'] ) )   { $s['title']   = self::decode_unicode( $s['title'] ); }
            if ( isset( $s['content'] ) ) { $s['content'] = self::decode_unicode( $s['content'] ); }
        }
        unset( $s );

        $profiles     = class_exists( 'PedagoLens_Profile_Manager' )
            ? PedagoLens_Profile_Manager::get_all( active_only: true )
            : [];

        // Scores existants (dernière analyse du projet)
        $raw_scores   = get_post_meta( $project->ID, '_pl_profile_scores', true );
        $scores       = is_string( $raw_scores ) ? (array) json_decode( $raw_scores, true ) : [];
        ?>
        <div class="wrap pl-workbench-wrap">

            <div class="pl-workbench-header">
                <h1>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>">←</a>
                    <?php echo esc_html( $project->post_title ); ?>
                    <span class="pl-project-type-badge pl-type-<?php echo esc_attr( $project_type ); ?>">
                        <?php echo esc_html( $project_type ); ?>
                    </span>
                </h1>
                <button type="button" id="pl-add-section" class="button">
                    + <?php esc_html_e( 'Ajouter une section', 'pedagolens-course-workbench' ); ?>
                </button>
            </div>

            <div class="pl-workbench-layout">

                <!-- Colonne principale : sections -->
                <div class="pl-workbench-main">
                    <?php if ( empty( $sections ) ) : ?>
                        <div class="pl-empty-sections">
                            <p><?php esc_html_e( 'Aucune section. Ajoutez du contenu pour commencer.', 'pedagolens-course-workbench' ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $sections as $section ) : ?>
                            <?php self::render_section_block( $section, $project->ID ); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Panneau latéral : scores par profil -->
                <div class="pl-workbench-sidebar">
                    <div class="pl-sidebar-card">
                        <h3><?php esc_html_e( 'Scores par profil', 'pedagolens-course-workbench' ); ?></h3>
                        <div id="pl-sidebar-scores">
                            <?php if ( empty( $scores ) ) : ?>
                                <p class="pl-sidebar-empty">
                                    <?php esc_html_e( 'Analysez une section pour voir les scores.', 'pedagolens-course-workbench' ); ?>
                                </p>
                            <?php else : ?>
                                <?php self::render_score_bars( $scores ); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modale historique des versions -->
        <div id="pl-versions-modal" style="display:none;">
            <div class="pl-modal-overlay">
                <div class="pl-modal-box">
                    <h2><?php esc_html_e( 'Historique des versions', 'pedagolens-course-workbench' ); ?></h2>
                    <div id="pl-versions-content"></div>
                    <button type="button" id="pl-versions-close" class="button">
                        <?php esc_html_e( 'Fermer', 'pedagolens-course-workbench' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Bloc section
    // -------------------------------------------------------------------------

    private static function render_section_block( array $section, int $project_id ): void {
        $section_id = esc_attr( $section['id'] ?? '' );
        $title      = esc_html( $section['title'] ?? 'Section' );
        $content    = esc_textarea( $section['content'] ?? '' );
        ?>
        <div class="pl-section-block" id="pl-section-<?php echo $section_id; ?>" data-section-id="<?php echo $section_id; ?>">

            <div class="pl-section-header">
                <h2 class="pl-section-title"><?php echo $title; ?></h2>
                <div class="pl-section-actions">
                    <button type="button" class="button button-small pl-btn-suggestions" data-section-id="<?php echo $section_id; ?>">
                        💡 <?php esc_html_e( 'Suggestions IA', 'pedagolens-course-workbench' ); ?>
                    </button>
                    <button type="button" class="button button-small pl-btn-history" data-section-id="<?php echo $section_id; ?>">
                        🕐 <?php esc_html_e( 'Historique', 'pedagolens-course-workbench' ); ?>
                    </button>
                </div>
            </div>

            <div class="pl-section-editor">
                <textarea
                    class="pl-section-content large-text"
                    data-section-id="<?php echo $section_id; ?>"
                    rows="8"
                ><?php echo $content; ?></textarea>
                <div class="pl-section-save-row">
                    <button type="button" class="button button-primary pl-btn-save-section" data-section-id="<?php echo $section_id; ?>">
                        <?php esc_html_e( 'Enregistrer', 'pedagolens-course-workbench' ); ?>
                    </button>
                    <span class="pl-save-status"></span>
                </div>
            </div>

            <!-- Zone suggestions (remplie par AJAX) -->
            <div class="pl-suggestions-zone" id="pl-suggestions-<?php echo $section_id; ?>" style="display:none;"></div>

        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Barres de scores
    // -------------------------------------------------------------------------

    public static function render_score_bars( array $scores ): void {
        if ( empty( $scores ) ) {
            return;
        }

        $profile_labels = self::get_profile_labels();

        // Global average circle
        $valid_scores = array_map( 'intval', array_values( $scores ) );
        $global_score = (int) round( array_sum( $valid_scores ) / max( 1, count( $valid_scores ) ) );
        $global_score = max( 0, min( 100, $global_score ) );
        $circumference = 283;
        $offset        = $circumference - ( $circumference * $global_score / 100 );
        $global_color  = $global_score >= 80 ? '#00a32a' : ( $global_score >= 60 ? '#2271b1' : ( $global_score >= 40 ? '#dba617' : '#d63638' ) );
        ?>
        <div class="pl-score-global pl-score-global--admin">
            <svg class="pl-score-circle" viewBox="0 0 100 100" width="90" height="90">
                <circle class="pl-score-circle-bg" cx="50" cy="50" r="45" />
                <circle class="pl-score-circle-fill" cx="50" cy="50" r="45"
                    stroke="<?php echo esc_attr( $global_color ); ?>"
                    style="--score-offset:<?php echo esc_attr( $offset ); ?>;" />
            </svg>
            <div class="pl-score-global-value">
                <span class="pl-score-global-num"><?php echo $global_score; ?></span>
                <span class="pl-score-global-label">Global</span>
            </div>
        </div>
        <?php
        foreach ( $scores as $slug => $score ) :
            $score = max( 0, min( 100, (int) $score ) );
            $color = self::score_color( $score );
            $info  = $profile_labels[ $slug ] ?? [ 'label' => ucfirst( str_replace( '_', ' ', $slug ) ), 'icon' => '📊' ];
            ?>
            <div class="pl-score-row">
                <span class="pl-score-label" title="<?php echo esc_attr( $slug ); ?>">
                    <?php echo $info['icon']; ?> <?php echo esc_html( $info['label'] ); ?>
                </span>
                <div class="pl-score-bar-wrap">
                    <div class="pl-score-bar" style="width:<?php echo $score; ?>%;background:<?php echo esc_attr( $color ); ?>;"></div>
                </div>
                <span class="pl-score-value"><?php echo $score; ?></span>
            </div>
        <?php endforeach;
    }

    // -------------------------------------------------------------------------
    // AJAX — Suggestions
    // -------------------------------------------------------------------------

    public static function ajax_get_suggestions(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        $section_id = sanitize_text_field( $_POST['section_id'] ?? '' );

        // Mock mode — return realistic pre-generated suggestions
        $ai_mode = get_option( 'pl_ai_mode', 'mock' );
        if ( $ai_mode === 'mock' ) {
            $mock_result = self::get_mock_suggestions( $section_id, $project_id );

            ob_start();
            $context = sanitize_text_field( $_POST['context'] ?? '' );
            if ( 'front' === $context ) {
                self::render_front_score_bars( $mock_result['profile_scores'] );
            } else {
                self::render_score_bars( $mock_result['profile_scores'] );
            }
            $scores_html = ob_get_clean();
            update_post_meta( $project_id, '_pl_profile_scores', wp_json_encode( $mock_result['profile_scores'] ) );

            // Cache suggestions
            $raw   = get_post_meta( $project_id, '_pl_last_suggestions', true );
            $cache = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
            $cache[ $section_id ] = $mock_result['suggestions'];
            update_post_meta( $project_id, '_pl_last_suggestions', wp_json_encode( $cache ) );

            ob_start();
            self::render_suggestions_html( $mock_result['suggestions'], $section_id );
            $html = ob_get_clean();

            wp_send_json_success( [
                'html'           => $html,
                'scores_html'    => $scores_html,
                'profile_scores' => $mock_result['profile_scores'],
                'count'          => count( $mock_result['suggestions'] ),
            ] );
            return;
        }

        $result = PedagoLens_Course_Workbench::get_suggestions( $project_id, $section_id );

        if ( empty( $result['success'] ) ) {
            wp_send_json_error( [ 'message' => $result['error_message'] ?? 'Erreur.' ] );
        }

        // Mettre en cache les suggestions pour apply
        $raw   = get_post_meta( $project_id, '_pl_last_suggestions', true );
        $cache = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
        $cache[ $section_id ] = $result['suggestions'] ?? [];
        update_post_meta( $project_id, '_pl_last_suggestions', wp_json_encode( $cache ) );

        ob_start();
        self::render_suggestions_html( $result['suggestions'] ?? [], $section_id );
        $html = ob_get_clean();

        // Mettre à jour les scores dans le sidebar si présents
        $scores_html = '';
        if ( ! empty( $result['profile_scores'] ) ) {
            $context = sanitize_text_field( $_POST['context'] ?? '' );
            ob_start();
            if ( 'front' === $context ) {
                self::render_front_score_bars( $result['profile_scores'] );
            } else {
                self::render_score_bars( $result['profile_scores'] );
            }
            $scores_html = ob_get_clean();
            update_post_meta( $project_id, '_pl_profile_scores', wp_json_encode( $result['profile_scores'] ) );
        }

        wp_send_json_success( [
            'html'           => $html,
            'scores_html'    => $scores_html,
            'profile_scores' => $result['profile_scores'] ?? [],
            'count'          => count( $result['suggestions'] ?? [] ),
        ] );
    }

    private static function render_suggestions_html( array $suggestions, string $section_id ): void {
        if ( empty( $suggestions ) ) {
            echo '<p>' . esc_html__( 'Aucune suggestion pour cette section.', 'pedagolens-course-workbench' ) . '</p>';
            return;
        }

        $type_labels = [
            'reformulation'   => 'Reformulation',
            'ajout'           => 'Ajout',
            'suppression'     => 'Suppression',
            'restructuration' => 'Restructuration',
        ];
        $type_icons = [
            'reformulation'   => '💡',
            'ajout'           => '✨',
            'suppression'     => '⚠️',
            'restructuration' => '🔄',
        ];
        $type_css = [
            'reformulation'   => 'improve',
            'ajout'           => 'enrich',
            'suppression'     => 'problem',
            'restructuration' => 'restructure',
        ];

        $profile_labels = self::get_profile_labels();
        ?>
        <div class="pl-suggestions-list">
            <div class="pl-suggestions-header-bar">
                <h4><?php esc_html_e( 'Suggestions IA', 'pedagolens-course-workbench' ); ?></h4>
                <span class="pl-suggestions-count"><?php echo count( $suggestions ); ?></span>
            </div>
            <div class="pl-suggestions-scroll">
            <?php foreach ( $suggestions as $idx => $sug ) :
                $sug_id    = esc_attr( $sug['id'] ?? '' );
                $mod_type  = $sug['modification_type'] ?? 'reformulation';
                $impact    = max( 0, min( 100, (int) ( $sug['impact_score'] ?? 50 ) ) );
                $slide     = (int) ( $sug['slide_num'] ?? 0 );
                $css_type  = $type_css[ $mod_type ] ?? 'improve';
                ?>
                <div class="pl-sug-card pl-suggestion-card pl-sug-card--<?php echo esc_attr( $css_type ); ?>" id="pl-sug-<?php echo $sug_id; ?>"
                     data-type="<?php echo esc_attr( $mod_type ); ?>"
                     data-section-id="<?php echo esc_attr( $section_id ); ?>"
                     style="animation-delay:<?php echo $idx * 80; ?>ms;">

                    <!-- Card header: type badge + meta -->
                    <div class="pl-sug-card-header">
                        <span class="pl-sug-type-icon"><?php echo esc_html( $type_icons[ $mod_type ] ?? '📝' ); ?></span>
                        <div class="pl-sug-card-meta">
                            <span class="pl-sug-type-label pl-sug-type-label--<?php echo esc_attr( $css_type ); ?>">
                                <?php echo esc_html( $type_labels[ $mod_type ] ?? ucfirst( $mod_type ) ); ?>
                            </span>
                            <?php if ( $slide > 0 ) : ?>
                                <span class="pl-sug-slide-num">Diapo <?php echo $slide; ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $sug['profile_target'] ) ) :
                                $pt_info = $profile_labels[ $sug['profile_target'] ] ?? null;
                                ?>
                                <span class="pl-sug-profile-tag">
                                    <?php echo $pt_info ? $pt_info['icon'] . ' ' : ''; ?><?php echo esc_html( $pt_info ? $pt_info['label'] : $sug['profile_target'] ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="pl-sug-impact-badge" title="Impact: <?php echo $impact; ?>%"><?php echo $impact; ?>%</span>
                    </div>

                    <!-- Rationale (full text, not truncated) -->
                    <?php if ( ! empty( $sug['rationale'] ) ) : ?>
                        <p class="pl-sug-card-rationale"><?php echo esc_html( $sug['rationale'] ); ?></p>
                    <?php endif; ?>

                    <!-- Diff visuel: original barré rouge → proposé vert -->
                    <?php if ( ! empty( $sug['original'] ) || ! empty( $sug['proposed'] ) ) : ?>
                    <div class="pl-sug-card-diff">
                        <?php if ( ! empty( $sug['original'] ) ) : ?>
                            <div class="pl-sug-diff-old">
                                <span class="pl-sug-diff-label">Avant</span>
                                <span class="pl-sug-diff-text"><?php echo esc_html( $sug['original'] ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $sug['proposed'] ) ) : ?>
                            <div class="pl-sug-diff-new">
                                <span class="pl-sug-diff-label">Après</span>
                                <span class="pl-sug-diff-text"><?php echo esc_html( $sug['proposed'] ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Impact deltas -->
                    <?php if ( ! empty( $sug['impact_delta'] ) ) : ?>
                        <div class="pl-sug-card-deltas">
                            <?php foreach ( $sug['impact_delta'] as $slug => $delta ) :
                                $sign  = $delta >= 0 ? '+' : '';
                                $class = $delta >= 0 ? 'pl-delta-pos' : 'pl-delta-neg';
                                $d_info = $profile_labels[ $slug ] ?? null;
                                $d_label = $d_info ? $d_info['icon'] . ' ' . $d_info['label'] : $slug;
                                ?>
                                <span class="pl-delta <?php echo $class; ?>">
                                    <?php echo esc_html( "{$sign}{$delta} " ); ?><?php echo $d_label; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action buttons -->
                    <div class="pl-sug-card-actions">
                        <button type="button"
                            class="pl-sug-btn pl-sug-btn--apply pl-btn-apply"
                            data-project-id="<?php echo esc_attr( $_POST['project_id'] ?? '' ); ?>"
                            data-section-id="<?php echo esc_attr( $section_id ); ?>"
                            data-suggestion-id="<?php echo $sug_id; ?>">
                            ✓ <?php esc_html_e( 'Appliquer', 'pedagolens-course-workbench' ); ?>
                        </button>
                        <button type="button"
                            class="pl-sug-btn pl-sug-btn--preview pl-btn-preview"
                            data-section-id="<?php echo esc_attr( $section_id ); ?>"
                            data-suggestion-id="<?php echo $sug_id; ?>">
                            👁 <?php esc_html_e( 'Prévisualiser', 'pedagolens-course-workbench' ); ?>
                        </button>
                        <button type="button"
                            class="pl-sug-btn pl-sug-btn--reject pl-btn-reject"
                            data-project-id="<?php echo esc_attr( $_POST['project_id'] ?? '' ); ?>"
                            data-section-id="<?php echo esc_attr( $section_id ); ?>"
                            data-suggestion-id="<?php echo $sug_id; ?>">
                            ✗ <?php esc_html_e( 'Rejeter', 'pedagolens-course-workbench' ); ?>
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — Apply / Reject
    // -------------------------------------------------------------------------

    public static function ajax_apply_suggestion(): void {
        self::verify_nonce();

        $project_id    = (int) ( $_POST['project_id']   ?? 0 );
        $section_id    = sanitize_text_field( $_POST['section_id']   ?? '' );
        $suggestion_id = sanitize_text_field( (string) ( $_POST['suggestion_id'] ?? '' ) );
        $proposed      = wp_unslash( $_POST['proposed_content'] ?? '' );

        if ( ! $project_id || ! $section_id || ! $suggestion_id ) {
            wp_send_json_error( [ 'message' => 'Paramètres manquants.' ] );
        }

        $ok = PedagoLens_Course_Workbench::apply_suggestion( $project_id, $section_id, $suggestion_id );

        // Fallback: apply proposed content directly if cache lookup failed
        if ( ! $ok && ! empty( $proposed ) ) {
            $sections = PedagoLens_Course_Workbench::get_content_sections( $project_id );
            foreach ( $sections as &$s ) {
                if ( ( $s['id'] ?? '' ) === $section_id ) {
                    PedagoLens_Course_Workbench::save_version( $project_id, $section_id, $s['content'] ?? '' );
                    $s['content'] = $proposed;
                    break;
                }
            }
            unset( $s );
            PedagoLens_Course_Workbench::save_content_sections( $project_id, $sections );
            $ok = true;
        }

        if ( $ok ) {
            $sections = PedagoLens_Course_Workbench::get_content_sections( $project_id );
            $content  = '';
            foreach ( $sections as $s ) {
                if ( ( $s['id'] ?? '' ) === $section_id ) {
                    $content = $s['content'] ?? '';
                    break;
                }
            }

            // Return current scores if available
            $raw_scores = get_post_meta( $project_id, '_pl_profile_scores', true );
            $scores     = is_string( $raw_scores ) ? (array) json_decode( $raw_scores, true ) : [];

            wp_send_json_success( [
                'new_content'    => $content,
                'scores'         => $scores,
                'suggestion_id'  => $suggestion_id,
                'section_id'     => $section_id,
            ] );
        } else {
            wp_send_json_error( [ 'message' => 'Application de la suggestion échouée.' ] );
        }
    }

    public static function ajax_reject_suggestion(): void {
        self::verify_nonce();

        $project_id   = (int) ( $_POST['project_id']   ?? 0 );
        $section_id   = sanitize_text_field( $_POST['section_id']   ?? '' );
        $suggestion_id = sanitize_text_field( $_POST['suggestion_id'] ?? '' );

        PedagoLens_Course_Workbench::reject_suggestion( $project_id, $section_id, $suggestion_id );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // AJAX — Sauvegarder une section
    // -------------------------------------------------------------------------

    public static function ajax_save_section(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        $section_id = sanitize_text_field( $_POST['section_id'] ?? '' );
        $content    = sanitize_textarea_field( $_POST['content'] ?? '' );

        $sections = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $updated  = false;

        foreach ( $sections as &$s ) {
            if ( ( $s['id'] ?? '' ) === $section_id ) {
                // Sauvegarder une version avant modification
                PedagoLens_Course_Workbench::save_version( $project_id, $section_id, $s['content'] ?? '' );
                $s['content'] = $content;
                $updated = true;
                break;
            }
        }

        if ( ! $updated ) {
            wp_send_json_error( [ 'message' => 'Section introuvable.' ] );
        }

        PedagoLens_Course_Workbench::save_content_sections( $project_id, $sections );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // AJAX — Historique des versions
    // -------------------------------------------------------------------------

    public static function ajax_get_versions(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        $section_id = sanitize_text_field( $_POST['section_id'] ?? '' );

        $versions = PedagoLens_Course_Workbench::compare_versions( $project_id, $section_id );

        ob_start();
        if ( empty( $versions ) ) {
            echo '<p>' . esc_html__( 'Aucune version sauvegardée.', 'pedagolens-course-workbench' ) . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__( 'Version', 'pedagolens-course-workbench' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'pedagolens-course-workbench' ) . '</th>';
            echo '<th>' . esc_html__( 'Aperçu', 'pedagolens-course-workbench' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( array_reverse( $versions ) as $v ) {
                echo '<tr>';
                echo '<td>' . esc_html( 'v' . ( $v['version_no'] ?? '?' ) ) . '</td>';
                echo '<td>' . esc_html( wp_date( 'Y-m-d H:i', strtotime( $v['saved_at'] ?? '' ) ) ) . '</td>';
                echo '<td><code>' . esc_html( mb_substr( $v['content'] ?? '', 0, 80 ) ) . '…</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Ajouter une section
    // -------------------------------------------------------------------------

    public static function ajax_add_section(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        $title      = sanitize_text_field( $_POST['title'] ?? 'Nouvelle section' );
        $content    = sanitize_textarea_field( $_POST['content'] ?? '' );
        $context    = sanitize_text_field( $_POST['context'] ?? 'admin' );

        $sections   = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $new_section = [
            'id'      => 'section_' . uniqid(),
            'title'   => $title,
            'content' => $content,
        ];
        $sections[] = $new_section;

        PedagoLens_Course_Workbench::save_content_sections( $project_id, $sections );

        ob_start();
        if ( $context === 'front' ) {
            self::render_front_section( $new_section, $project_id, count( $sections ) );
        } else {
            self::render_section_block( $new_section, $project_id );
        }
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function verify_nonce(): void {
        // Allow teachers and admins (front-end + admin)
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;
        $allowed = in_array( 'administrator', $roles, true )
                || in_array( 'pedagolens_teacher', $roles, true )
                || current_user_can( 'manage_options' );

        if ( ! $allowed ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }
        check_ajax_referer( self::NONCE_AJAX, 'nonce' );
    }

    private static function score_color( int $score ): string {
        if ( $score >= 80 ) return '#00a32a';
        if ( $score >= 60 ) return '#2271b1';
        if ( $score >= 40 ) return '#dba617';
        return '#d63638';
    }

    // -------------------------------------------------------------------------
    // Rendu front-end — Éditeur PowerPoint (3 colonnes, 100vh, pas de scroll)
    // -------------------------------------------------------------------------

    public static function render_front( int $project_id ): string {
        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== 'pl_project' ) {
            return '<div class="pl-notice pl-notice-error"><p>Projet introuvable.</p></div>';
        }

        // --- Data loading ---
        $project_type = get_post_meta( $project_id, '_pl_project_type', true ) ?: 'magistral';
        $sections     = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $raw_scores   = get_post_meta( $project_id, '_pl_profile_scores', true );
        $scores       = is_string( $raw_scores ) ? (array) json_decode( $raw_scores, true ) : [];
        $raw_files    = get_post_meta( $project_id, '_pl_uploaded_files', true );
        $files        = is_string( $raw_files ) ? (array) json_decode( $raw_files, true ) : [];
        $summary      = get_post_meta( $project_id, '_pl_summary', true ) ?: '';

        $raw_slide_images = get_post_meta( $project_id, '_pl_slide_images', true );
        $slide_images     = is_string( $raw_slide_images ) ? (array) json_decode( $raw_slide_images, true ) : [];

        $raw_visual_data  = get_post_meta( $project_id, '_pl_slide_visual_data', true );
        $visual_data      = is_string( $raw_visual_data ) ? (array) json_decode( $raw_visual_data, true ) : [];

        $courses_page = get_page_by_path( 'cours-projets' );
        $back_url     = $courses_page ? get_permalink( $courses_page ) : home_url( '/' );

        $type_labels = [
            'magistral'      => 'Magistral',
            'exercice'       => 'Exercice',
            'travail_equipe' => 'Travail d\'équipe',
            'evaluation'     => 'Évaluation',
        ];
        $type_icons = [
            'magistral'      => '🎓',
            'exercice'       => '📝',
            'travail_equipe' => '👥',
            'evaluation'     => '📋',
        ];

        $profiles = class_exists( 'PedagoLens_Profile_Manager' )
            ? PedagoLens_Profile_Manager::get_all( active_only: true )
            : [];

        $total_slides = count( $sections );

        // Decode broken Unicode escapes in section data
        foreach ( $sections as &$s ) {
            if ( isset( $s['title'] ) )   { $s['title']   = self::decode_unicode( $s['title'] ); }
            if ( isset( $s['content'] ) ) { $s['content'] = self::decode_unicode( $s['content'] ); }
        }
        unset( $s );

        // Build sections JSON for JS slide navigation
        $sections_for_js = array_values( array_map( function( $s ) {
            return [
                'id'              => $s['id'] ?? '',
                'title'           => $s['title'] ?? 'Section',
                'content'         => $s['content'] ?? '',
                'slide_image_url' => $s['slide_image_url'] ?? '',
                'slide_num'       => (int) ( $s['slide_num'] ?? 0 ),
            ];
        }, $sections ) );

        // Enqueue assets + localize BEFORE ob_start
        wp_enqueue_style(
            'pl-workbench-front',
            PL_WORKBENCH_PLUGIN_URL . 'assets/css/workbench-admin.css',
            [],
            PL_WORKBENCH_VERSION
        );
        wp_enqueue_script(
            'pl-workbench-front',
            PL_WORKBENCH_PLUGIN_URL . 'assets/js/workbench-admin.js',
            [ 'jquery' ],
            PL_WORKBENCH_VERSION,
            true
        );
        // Load cached suggestions for persistence between visits
        $raw_suggestions    = get_post_meta( $project_id, '_pl_last_suggestions', true );
        $cached_suggestions = is_string( $raw_suggestions ) ? (array) json_decode( $raw_suggestions, true ) : [];

        // Pre-render cached suggestions HTML per section for JS hydration
        $cached_suggestions_html = [];
        foreach ( $cached_suggestions as $sec_id => $sec_suggestions ) {
            if ( ! is_array( $sec_suggestions ) || empty( $sec_suggestions ) ) {
                continue;
            }
            ob_start();
            self::render_suggestions_html( $sec_suggestions, $sec_id );
            $cached_suggestions_html[ $sec_id ] = ob_get_clean();
        }

        wp_localize_script( 'pl-workbench-front', 'plWorkbench', [
            'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( self::NONCE_AJAX ),
            'projectId'             => $project_id,
            'slideImages'           => $slide_images,
            'sections'              => $sections_for_js,
            'totalSlides'           => $total_slides,
            'visualData'            => $visual_data,
            'cachedSuggestionsHtml' => $cached_suggestions_html,
        ] );

        // First section data (for no-JS fallback)
        $first         = ! empty( $sections ) ? $sections[0] : null;
        $first_id      = $first ? esc_attr( $first['id'] ?? '' ) : '';
        $first_title   = $first ? ( $first['title'] ?? 'Section' ) : '';
        $first_content = $first ? ( $first['content'] ?? '' ) : '';
        $first_img     = $first ? ( $first['slide_image_url'] ?? '' ) : '';
        $first_num     = $first ? (int) ( $first['slide_num'] ?? 1 ) : 0;

        ob_start();
        ?>
        <div class="pl-editor" data-project-id="<?php echo esc_attr( $project_id ); ?>">

            <!-- HEADER BAR (compact, 56px) -->
            <header class="pl-editor-header">
                <a href="<?php echo esc_url( $back_url ); ?>" class="pl-editor-back">← Retour</a>
                <h1 class="pl-editor-title"><?php echo esc_html( $project->post_title ); ?></h1>
                <span class="pl-editor-type-badge pl-stitch-type-<?php echo esc_attr( $project_type ); ?>">
                    <?php echo esc_html( ( $type_icons[ $project_type ] ?? '📄' ) . ' ' . ( $type_labels[ $project_type ] ?? $project_type ) ); ?>
                </span>
                <div class="pl-editor-header-spacer"></div>
                <button type="button" id="pl-upload-trigger" class="pl-editor-btn pl-editor-btn-outline">Importer</button>
                <button type="button" id="pl-add-section" class="pl-editor-btn pl-editor-btn-outline">+ Section</button>
                <button type="button" id="pl-view-toggle" class="pl-editor-btn pl-editor-btn-outline" title="Basculer vue visuelle / texte">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    Vue visuelle
                </button>
                <button type="button" id="pl-wb-save-version" class="pl-editor-btn pl-editor-btn-primary">Sauvegarder</button>
                <button type="button" id="pl-download-pptx" class="pl-editor-btn pl-editor-btn-outline" style="display:none;">Télécharger PPTX</button>
            </header>

            <!-- BODY: 3 columns -->
            <div class="pl-editor-body">

                <!-- LEFT COLUMN: Filmstrip -->
                <div class="pl-editor-left-col">

                    <!-- Filmstrip (slide thumbnails) -->
                    <aside class="pl-editor-filmstrip" id="pl-filmstrip">
                    <div class="pl-filmstrip-header">
                        <span class="pl-filmstrip-title">Diapositives</span>
                        <button type="button" class="pl-filmstrip-toggle" id="pl-filmstrip-toggle" title="Réduire">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                    </div>
                    <div class="pl-filmstrip-list" id="pl-filmstrip-list">
                        <?php foreach ( $sections as $i => $sec ) :
                            $sec_id    = esc_attr( $sec['id'] ?? '' );
                            $sec_title = $sec['title'] ?? 'Section';
                            $sec_text  = $sec['content'] ?? '';
                            $sec_img   = $sec['slide_image_url'] ?? '';
                            $active_cl = $i === 0 ? ' pl-filmstrip-item--active' : '';
                        ?>
                        <div class="pl-filmstrip-item<?php echo $active_cl; ?>" data-slide-index="<?php echo esc_attr( $i ); ?>" data-section-id="<?php echo $sec_id; ?>">
                            <span class="pl-filmstrip-num"><?php echo esc_html( $i + 1 ); ?></span>
                            <div class="pl-filmstrip-info">
                                <span class="pl-filmstrip-item-title"><?php echo esc_html( mb_substr( $sec_title, 0, 30 ) ); ?></span>
                                <span class="pl-filmstrip-item-preview"><?php echo esc_html( mb_substr( $sec_text, 0, 40 ) ); ?></span>
                            </div>
                            <?php if ( $sec_img ) : ?>
                            <img class="pl-filmstrip-thumb-img" src="<?php echo esc_url( $sec_img ); ?>" alt="<?php echo esc_attr( 'Slide ' . ( $i + 1 ) ); ?>" loading="lazy" />
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                </div><!-- .pl-editor-left-col -->

                <!-- CENTER: Canvas (single slide editor) -->
                <main class="pl-editor-canvas" id="pl-editor-canvas">
                    <div class="pl-canvas-nav">
                        <button type="button" class="pl-canvas-nav-btn" id="pl-slide-prev" title="Diapositive précédente">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <span class="pl-canvas-counter" id="pl-slide-counter">Diapositive 1 / <?php echo esc_html( $total_slides ); ?></span>
                        <button type="button" class="pl-canvas-nav-btn" id="pl-slide-next" title="Diapositive suivante">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                    <div class="pl-scores-strip" id="pl-scores-strip">
                        <div id="pl-sidebar-scores" class="pl-scores-strip-inner">
                            <?php if ( ! empty( $scores ) ) : ?>
                                <?php self::render_front_score_bars( $scores ); ?>
                            <?php else : ?>
                                <p class="pl-scores-strip-empty">Analysez pour voir les scores</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pl-canvas-slide" id="pl-canvas-slide">
                        <?php if ( $first ) : ?>
                        <div class="pl-canvas-slide-inner" data-section-id="<?php echo $first_id; ?>" data-slide-num="<?php echo esc_attr( $first_num ); ?>">
                            <?php if ( $first_img ) : ?>
                            <div class="pl-canvas-slide-image">
                                <img src="<?php echo esc_url( $first_img ); ?>" alt="<?php echo esc_attr( 'Diapositive ' . $first_num ); ?>" />
                            </div>
                            <?php endif; ?>
                            <h2 class="pl-canvas-slide-title" contenteditable="false"><?php echo esc_html( $first_title ); ?></h2>
                            <textarea class="pl-section-content pl-canvas-textarea" data-section-id="<?php echo $first_id; ?>" rows="12"><?php echo esc_textarea( $first_content ); ?></textarea>
                        </div>
                        <?php else : ?>
                        <div class="pl-canvas-empty">
                            <p>Aucune section. Importez un fichier ou ajoutez du contenu.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="pl-canvas-toolbar" id="pl-canvas-toolbar">
                        <button type="button" class="pl-editor-btn pl-editor-btn-accent pl-btn-suggestions" id="pl-canvas-suggestions-btn" data-section-id="<?php echo $first_id; ?>">
                            Suggestions IA
                        </button>
                        <button type="button" class="pl-editor-btn pl-editor-btn-ghost pl-btn-history" id="pl-canvas-history-btn" data-section-id="<?php echo $first_id; ?>">
                            Historique
                        </button>
                        <button type="button" class="pl-editor-btn pl-editor-btn-sm pl-editor-btn-primary pl-btn-save-section" id="pl-canvas-save-btn" data-section-id="<?php echo $first_id; ?>">
                            Enregistrer
                        </button>
                        <button type="button" class="pl-editor-btn pl-editor-btn-sm pl-editor-btn-ghost pl-btn-undo" id="pl-canvas-undo-btn" data-section-id="<?php echo $first_id; ?>" style="display:none;">
                            Annuler
                        </button>
                        <span class="pl-save-status" id="pl-canvas-save-status"></span>
                    </div>
                </main>

                <!-- RIGHT: AI Panel -->
                <aside class="pl-editor-panel" id="pl-editor-panel">

                    <!-- Suggestions IA -->
                    <div class="pl-panel-section">
                        <div class="pl-panel-section-header">
                            <h3>Suggestions IA</h3>
                            <span class="pl-panel-ai-badge">Active</span>
                        </div>
                        <div id="pl-panel-suggestions" class="pl-panel-suggestions">
                            <p class="pl-panel-empty">Cliquez sur « Suggestions IA » pour obtenir des recommandations.</p>
                        </div>
                        <button type="button" id="pl-analyze-all" class="pl-editor-btn pl-editor-btn-glow pl-editor-btn-full" style="margin-top:12px;">
                            Analyser toutes les diapositives
                        </button>
                    </div>

                </aside>

            </div><!-- .pl-editor-body -->

        </div><!-- .pl-editor -->

        <!-- Modale historique des versions -->
        <div id="pl-versions-modal" style="display:none;">
            <div class="pl-modal-overlay pl-stitch-modal-overlay">
                <div class="pl-modal-box pl-stitch-modal-box">
                    <h2>Historique des versions</h2>
                    <div id="pl-versions-content"></div>
                    <button type="button" id="pl-versions-close" class="pl-stitch-btn pl-stitch-btn-outline">Fermer</button>
                </div>
            </div>
        </div>

        <!-- Modale Ajouter une section -->
        <div id="pl-modal-add-section" class="pl-stitch-modal" style="display:none;">
            <div class="pl-stitch-modal-overlay"></div>
            <div class="pl-stitch-modal-content">
                <div class="pl-stitch-modal-header">
                    <h2>Ajouter une section</h2>
                    <button type="button" class="pl-stitch-modal-close">&times;</button>
                </div>
                <div class="pl-stitch-modal-body">
                    <label class="pl-stitch-label">Titre de la section</label>
                    <input type="text" id="pl-new-section-title" class="pl-stitch-input" placeholder="Ex: Introduction, Chapitre 1..." autofocus />
                    <label class="pl-stitch-label" style="margin-top:16px;">Contenu (optionnel)</label>
                    <textarea id="pl-new-section-content" class="pl-stitch-textarea" rows="4" placeholder="Ajoutez du contenu initial..."></textarea>
                </div>
                <div class="pl-stitch-modal-footer">
                    <button type="button" class="pl-stitch-btn pl-stitch-btn-outline pl-stitch-modal-cancel">Annuler</button>
                    <button type="button" id="pl-confirm-add-section" class="pl-stitch-btn pl-stitch-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Ajouter la section
                    </button>
                </div>
            </div>
        </div>

        <!-- Modale Importer un fichier -->
        <div id="pl-modal-import" class="pl-stitch-modal" style="display:none;">
            <div class="pl-stitch-modal-overlay"></div>
            <div class="pl-stitch-modal-content pl-stitch-modal-lg">
                <div class="pl-stitch-modal-header">
                    <h2>Importer un fichier</h2>
                    <button type="button" class="pl-stitch-modal-close">&times;</button>
                </div>
                <div class="pl-stitch-modal-body">
                    <div id="pl-upload-zone" class="pl-stitch-upload-zone">
                        <div class="pl-stitch-upload-dropzone" id="pl-dropzone">
                            <div class="pl-stitch-upload-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </div>
                            <p class="pl-stitch-upload-text">Glissez vos fichiers ici ou <label for="pl-file-input" class="pl-stitch-upload-browse">parcourez</label></p>
                            <p class="pl-stitch-upload-hint">PowerPoint (.pptx), Word (.docx), PDF (.pdf) — Limite : 25 Mo</p>
                            <input type="file" id="pl-file-input" accept=".pptx,.docx,.pdf" multiple style="display:none;" />
                        </div>
                        <div id="pl-upload-progress" class="pl-upload-progress" style="display:none;">
                            <div class="pl-progress-bar-wrap">
                                <div class="pl-progress-bar" id="pl-progress-bar"></div>
                            </div>
                            <span class="pl-progress-text" id="pl-progress-text">Téléversement…</span>
                        </div>
                        <div id="pl-upload-result" class="pl-upload-result" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide Viewer Modal -->
        <div id="pl-slide-viewer" class="pl-slide-viewer-modal" style="display:none;">
            <div class="pl-slide-viewer-overlay" onclick="closeSlideViewer()"></div>
            <button type="button" class="pl-slide-viewer-close" onclick="closeSlideViewer()" aria-label="Fermer">&times;</button>
            <button type="button" class="pl-slide-viewer-prev" onclick="slideViewerPrev()" aria-label="Diapositive précédente">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <img id="pl-slide-viewer-img" class="pl-slide-viewer-image" src="" alt="Diapositive" />
            <button type="button" class="pl-slide-viewer-next" onclick="slideViewerNext()" aria-label="Diapositive suivante">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            <div id="pl-slide-viewer-counter" class="pl-slide-viewer-counter">Diapositive 1 / 1</div>
        </div>

        <!-- Preview Suggestion Modal (avant/après) -->
        <div id="pl-preview-modal" class="pl-stitch-modal" style="display:none;">
            <div class="pl-stitch-modal-overlay pl-preview-modal-overlay"></div>
            <div class="pl-stitch-modal-content pl-stitch-modal-lg pl-preview-modal-content">
                <div class="pl-stitch-modal-header">
                    <h2>Prévisualisation de la suggestion</h2>
                    <button type="button" class="pl-stitch-modal-close">&times;</button>
                </div>
                <div class="pl-stitch-modal-body">
                    <div class="pl-preview-slide-img" id="pl-preview-slide-img" style="display:none;">
                        <img src="" alt="Diapositive" />
                    </div>
                    <div class="pl-preview-split">
                        <div class="pl-preview-before">
                            <h4>Avant</h4>
                            <div id="pl-preview-original" class="pl-preview-text"></div>
                        </div>
                        <div class="pl-preview-after">
                            <h4>Après</h4>
                            <div id="pl-preview-proposed" class="pl-preview-text"></div>
                        </div>
                    </div>
                    <div id="pl-preview-rationale" class="pl-preview-rationale" style="display:none;"></div>
                </div>
                <div class="pl-stitch-modal-footer">
                    <button type="button" class="pl-stitch-btn pl-stitch-btn-outline pl-stitch-modal-cancel">Fermer</button>
                    <button type="button" id="pl-preview-apply" class="pl-stitch-btn pl-stitch-btn-apply">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Appliquer cette suggestion
                    </button>
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Render a single section card for AJAX responses (add_section, upload).
     * Returns a simple section data block — JS handles slide navigation.
     */
    private static function render_front_section( array $section, int $project_id, int $section_num = 0 ): void {
        $section_id = esc_attr( $section['id'] ?? '' );
        $title      = $section['title'] ?? 'Section';
        $content    = $section['content'] ?? '';
        $slide_img  = $section['slide_image_url'] ?? '';
        $slide_num  = (int) ( $section['slide_num'] ?? $section_num );
        ?>
        <div class="pl-filmstrip-item" data-slide-index="<?php echo esc_attr( $section_num - 1 ); ?>" data-section-id="<?php echo $section_id; ?>">
            <span class="pl-filmstrip-num"><?php echo esc_html( $section_num ); ?></span>
            <div class="pl-filmstrip-info">
                <span class="pl-filmstrip-item-title"><?php echo esc_html( mb_substr( $title, 0, 30 ) ); ?></span>
                <span class="pl-filmstrip-item-preview"><?php echo esc_html( mb_substr( $content, 0, 40 ) ); ?></span>
            </div>
            <?php if ( $slide_img ) : ?>
            <img class="pl-filmstrip-thumb-img" src="<?php echo esc_url( $slide_img ); ?>" alt="<?php echo esc_attr( 'Slide ' . $section_num ); ?>" loading="lazy" />
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Profile labels mapping: slug → human-readable name + emoji.
     */
    private static function get_profile_labels(): array {
        return [
            'concentration_tdah'   => [ 'label' => 'TDAH / Concentration', 'icon' => '🧠' ],
            'surcharge_cognitive'  => [ 'label' => 'Surcharge cognitive',  'icon' => '⚡' ],
            'langue_seconde'       => [ 'label' => 'Langue seconde',       'icon' => '🌍' ],
            'faible_autonomie'     => [ 'label' => 'Faible autonomie',     'icon' => '🤝' ],
            'anxieux_consignes'    => [ 'label' => 'Anxiété / Consignes',  'icon' => '😰' ],
            'avance_rapide'        => [ 'label' => 'Avancé rapide',        'icon' => '🚀' ],
            'usage_passif_ia'      => [ 'label' => 'Usage passif IA',      'icon' => '🤖' ],
            // Legacy/fallback keys
            'tdah'                 => [ 'label' => 'TDAH / Concentration', 'icon' => '🧠' ],
            'cognitive_overload'   => [ 'label' => 'Surcharge cognitive',  'icon' => '⚡' ],
            'second_language'      => [ 'label' => 'Langue seconde',       'icon' => '🌍' ],
            'low_autonomy'         => [ 'label' => 'Faible autonomie',     'icon' => '🤝' ],
            'anxiety'              => [ 'label' => 'Anxiété / Consignes',  'icon' => '😰' ],
            'advanced'             => [ 'label' => 'Avancé rapide',        'icon' => '🚀' ],
            'passive_ai'           => [ 'label' => 'Usage passif IA',      'icon' => '🤖' ],
        ];
    }

    /**
     * Render animated score bars for front-end Stitch sidebar.
     * Includes SVG circle for global score + per-profile bars with labels.
     */
    public static function render_front_score_bars( array $scores ): void {
        if ( empty( $scores ) ) {
            return;
        }

        $profile_labels = self::get_profile_labels();

        // Compute global average score
        $valid_scores = array_map( 'intval', array_values( $scores ) );
        $global_score = (int) round( array_sum( $valid_scores ) / max( 1, count( $valid_scores ) ) );
        $global_score = max( 0, min( 100, $global_score ) );

        // SVG circle parameters (radius=45, circumference≈283)
        $circumference = 283;
        $offset        = $circumference - ( $circumference * $global_score / 100 );
        $global_color  = $global_score >= 80 ? '#10b981' : ( $global_score >= 60 ? '#3b82f6' : ( $global_score >= 40 ? '#f59e0b' : '#ef4444' ) );
        ?>
        <div class="pl-score-global">
            <svg class="pl-score-circle" viewBox="0 0 100 100" width="110" height="110">
                <circle class="pl-score-circle-bg" cx="50" cy="50" r="45" />
                <circle class="pl-score-circle-fill" cx="50" cy="50" r="45"
                    stroke="<?php echo esc_attr( $global_color ); ?>"
                    style="--score-offset:<?php echo esc_attr( $offset ); ?>;" />
            </svg>
            <div class="pl-score-global-value">
                <span class="pl-score-global-num"><?php echo $global_score; ?></span>
                <span class="pl-score-global-label">Score global</span>
            </div>
        </div>

        <div class="pl-score-profiles">
        <?php foreach ( $scores as $slug => $score ) :
            $score       = max( 0, min( 100, (int) $score ) );
            $color_class = $score >= 80 ? 'pl-bar-high' : ( $score >= 60 ? 'pl-bar-mid' : ( $score >= 40 ? 'pl-bar-warn' : 'pl-bar-low' ) );
            $info        = $profile_labels[ $slug ] ?? [ 'label' => ucfirst( str_replace( '_', ' ', $slug ) ), 'icon' => '📊' ];
            ?>
            <div class="pl-score-profile-row">
                <div class="pl-score-profile-header">
                    <span class="pl-score-profile-icon"><?php echo $info['icon']; ?></span>
                    <span class="pl-score-profile-name"><?php echo esc_html( $info['label'] ); ?></span>
                    <span class="pl-score-profile-num"><?php echo $score; ?></span>
                </div>
                <div class="pl-score-profile-bar-track">
                    <div class="pl-score-profile-bar-fill <?php echo $color_class; ?>" style="--score-w:<?php echo $score; ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — Preview suggestion (avant/après)
    // -------------------------------------------------------------------------

    public static function ajax_preview_suggestion(): void {
        self::verify_nonce();

        $project_id    = (int) ( $_POST['project_id'] ?? 0 );
        $section_id    = sanitize_text_field( $_POST['section_id'] ?? '' );
        $suggestion_id = sanitize_text_field( $_POST['suggestion_id'] ?? '' );

        // Get cached suggestions
        $raw   = get_post_meta( $project_id, '_pl_last_suggestions', true );
        $cache = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
        $suggestions = $cache[ $section_id ] ?? [];

        $suggestion = null;
        foreach ( $suggestions as $sug ) {
            if ( ( $sug['id'] ?? '' ) === $suggestion_id ) {
                $suggestion = $sug;
                break;
            }
        }

        if ( ! $suggestion ) {
            wp_send_json_error( [ 'message' => 'Suggestion introuvable.' ] );
        }

        // Get slide image URL if available
        $sections = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $slide_image_url = '';
        foreach ( $sections as $s ) {
            if ( ( $s['id'] ?? '' ) === $section_id ) {
                $slide_image_url = $s['slide_image_url'] ?? '';
                break;
            }
        }

        wp_send_json_success( [
            'original'        => $suggestion['original'] ?? '',
            'proposed'        => $suggestion['proposed'] ?? '',
            'rationale'       => $suggestion['rationale'] ?? '',
            'slide_image_url' => $slide_image_url,
            'section_id'      => $section_id,
            'suggestion_id'   => $suggestion_id,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Download modified PPTX
    // -------------------------------------------------------------------------

    public static function ajax_download_modified(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) {
            wp_send_json_error( [ 'message' => 'ID de projet manquant.' ] );
        }

        // Find the original PPTX attachment
        $raw_files = get_post_meta( $project_id, '_pl_uploaded_files', true );
        $files     = is_string( $raw_files ) ? (array) json_decode( $raw_files, true ) : [];

        $pptx_attachment_id = 0;
        foreach ( $files as $f ) {
            if ( ( $f['ext'] ?? '' ) === 'pptx' && ! empty( $f['attachment_id'] ) ) {
                $pptx_attachment_id = (int) $f['attachment_id'];
                break;
            }
        }

        if ( ! $pptx_attachment_id ) {
            wp_send_json_error( [ 'message' => 'Aucun fichier PPTX original trouvé.' ] );
        }

        $original_path = get_attached_file( $pptx_attachment_id );
        if ( ! $original_path || ! file_exists( $original_path ) ) {
            wp_send_json_error( [ 'message' => 'Fichier PPTX introuvable sur le serveur.' ] );
        }

        $sections    = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $output_path = self::generate_modified_pptx( $original_path, $sections, $project_id );

        if ( ! $output_path ) {
            wp_send_json_error( [ 'message' => 'Erreur lors de la génération du PPTX modifié.' ] );
        }

        $upload_dir = wp_upload_dir();
        $url        = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $output_path );

        wp_send_json_success( [
            'url'      => $url,
            'filename' => basename( $output_path ),
        ] );
    }

    /**
     * Generate a modified PPTX by injecting updated section content into slide XML.
     */
    private static function generate_modified_pptx( string $original_path, array $sections, int $project_id ): ?string {
        $upload_dir = wp_upload_dir();
        $dest_dir   = $upload_dir['basedir'] . '/pedagolens/exports/' . $project_id;
        if ( ! is_dir( $dest_dir ) ) {
            mkdir( $dest_dir, 0755, true );
        }

        $output_path = $dest_dir . '/modified-' . time() . '.pptx';
        copy( $original_path, $output_path );

        $zip = new \ZipArchive();
        if ( $zip->open( $output_path ) !== true ) {
            return null;
        }

        // Map sections by slide_num
        $by_slide = [];
        foreach ( $sections as $sec ) {
            $sn = (int) ( $sec['slide_num'] ?? 0 );
            if ( $sn > 0 ) {
                $by_slide[ $sn ] = $sec['content'] ?? '';
            }
        }

        // For each slide that has updated content, replace all <a:t> text
        foreach ( $by_slide as $slide_num => $new_content ) {
            $slide_xml = $zip->getFromName( "ppt/slides/slide{$slide_num}.xml" );
            if ( $slide_xml === false ) {
                continue;
            }

            // Simple approach: replace the first <a:t> block with the new content
            // and clear subsequent ones. This preserves XML structure.
            $lines     = explode( "\n", $new_content );
            $line_idx  = 0;
            $modified  = preg_replace_callback(
                '/<a:t>([^<]*)<\/a:t>/',
                function ( $matches ) use ( $lines, &$line_idx ) {
                    if ( $line_idx < count( $lines ) ) {
                        $replacement = htmlspecialchars( $lines[ $line_idx ], ENT_XML1, 'UTF-8' );
                        $line_idx++;
                        return '<a:t>' . $replacement . '</a:t>';
                    }
                    return $matches[0];
                },
                $slide_xml
            );

            if ( $modified ) {
                $zip->addFromString( "ppt/slides/slide{$slide_num}.xml", $modified );
            }
        }

        $zip->close();
        return $output_path;
    }

    // -------------------------------------------------------------------------
    // AJAX — Analyze all sections at once
    // -------------------------------------------------------------------------

    public static function ajax_analyze_all_sections(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) {
            wp_send_json_error( [ 'message' => 'ID de projet manquant.' ] );
        }

        $sections = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        if ( empty( $sections ) ) {
            wp_send_json_error( [ 'message' => 'Aucune section à analyser.' ] );
        }

        $ai_mode = get_option( 'pl_ai_mode', 'mock' );

        $all_suggestions = [];
        $all_html        = '';
        $latest_scores   = [];

        foreach ( $sections as $sec ) {
            $section_id = $sec['id'] ?? '';
            if ( ! $section_id ) {
                continue;
            }

            // Mock mode — use pre-generated suggestions instead of calling Bedrock
            if ( $ai_mode === 'mock' ) {
                $mock_result = self::get_mock_suggestions( $section_id, $project_id );
                $result = [
                    'success'        => true,
                    'suggestions'    => $mock_result['suggestions'],
                    'profile_scores' => $mock_result['profile_scores'],
                ];
            } else {
                $result = PedagoLens_Course_Workbench::get_suggestions( $project_id, $section_id );
            }

            if ( ! empty( $result['success'] ) && ! empty( $result['suggestions'] ) ) {
                // Cache suggestions
                $raw   = get_post_meta( $project_id, '_pl_last_suggestions', true );
                $cache = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
                $cache[ $section_id ] = $result['suggestions'];
                update_post_meta( $project_id, '_pl_last_suggestions', wp_json_encode( $cache ) );

                ob_start();
                self::render_suggestions_html( $result['suggestions'], $section_id );
                $html = ob_get_clean();

                $all_suggestions[ $section_id ] = [
                    'html'  => $html,
                    'count' => count( $result['suggestions'] ),
                ];
            }

            if ( ! empty( $result['profile_scores'] ) ) {
                $latest_scores = $result['profile_scores'];
            }
        }

        // Update scores
        $scores_html = '';
        if ( ! empty( $latest_scores ) ) {
            update_post_meta( $project_id, '_pl_profile_scores', wp_json_encode( $latest_scores ) );
            ob_start();
            self::render_front_score_bars( $latest_scores );
            $scores_html = ob_get_clean();
        }

        $total = array_sum( array_column( $all_suggestions, 'count' ) );

        wp_send_json_success( [
            'sections'    => $all_suggestions,
            'scores_html' => $scores_html,
            'total'       => $total,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Upload de fichier + extraction de texte
    // -------------------------------------------------------------------------

    public static function ajax_upload_file(): void {
        self::verify_nonce();

        $project_id = (int) ( $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) {
            wp_send_json_error( [ 'message' => 'ID de projet manquant.' ] );
        }

        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== 'pl_project' ) {
            wp_send_json_error( [ 'message' => 'Projet introuvable.' ] );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( [ 'message' => 'Aucun fichier reçu.' ] );
        }

        $file = $_FILES['file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        $allowed = [ 'pptx', 'docx', 'pdf' ];
        if ( ! in_array( $ext, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => 'Type de fichier non supporté. Acceptés : .pptx, .docx, .pdf' ] );
        }

        // Upload as WP attachment
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload( $file, [ 'test_form' => false ] );
        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( [ 'message' => $upload['error'] ] );
        }

        $attachment_id = wp_insert_attachment( [
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_mime_type' => $upload['type'],
            'post_status'    => 'inherit',
            'post_parent'    => $project_id,
        ], $upload['file'] );

        // Extract text
        $extracted_sections = [];
        $filepath = $upload['file'];

        switch ( $ext ) {
            case 'pptx':
                $extracted_sections = self::extract_pptx( $filepath );
                break;
            case 'docx':
                $extracted_sections = self::extract_docx( $filepath );
                break;
            case 'pdf':
                $extracted_sections = self::extract_pdf( $filepath );
                break;
        }

        // Convert PPTX slides to images + extract visual layout data
        $slide_images = [];
        $visual_data  = [];
        if ( $ext === 'pptx' ) {
            $slide_images = self::convert_pptx_to_images( $filepath, $attachment_id );
            update_post_meta( $project_id, '_pl_slide_images', wp_json_encode( $slide_images ) );

            // Extract visual layout data (positions, sizes, formatting, images)
            $visual_data = self::extract_pptx_visual( $filepath, $project_id );
            update_post_meta( $project_id, '_pl_slide_visual_data', wp_json_encode( $visual_data ) );

            // Link each section to its corresponding slide image
            foreach ( $extracted_sections as $idx => &$sec ) {
                if ( isset( $slide_images[ $idx ] ) ) {
                    $sec['slide_image_url'] = $slide_images[ $idx ]['url'];
                    $sec['slide_num']       = $slide_images[ $idx ]['slide_num'];
                }
            }
            unset( $sec );
        }

        // Merge new sections into project
        $existing = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        foreach ( $extracted_sections as $sec ) {
            $existing[] = $sec;
        }
        PedagoLens_Course_Workbench::save_content_sections( $project_id, $existing );

        // Track uploaded files
        $raw_files = get_post_meta( $project_id, '_pl_uploaded_files', true );
        $files     = is_string( $raw_files ) ? (array) json_decode( $raw_files, true ) : [];
        $files[]   = [
            'name'          => $file['name'],
            'attachment_id' => $attachment_id,
            'ext'           => $ext,
            'uploaded_at'   => gmdate( 'c' ),
        ];
        update_post_meta( $project_id, '_pl_uploaded_files', wp_json_encode( $files ) );

        // Build HTML for new sections
        ob_start();
        foreach ( $extracted_sections as $sec ) {
            self::render_front_section( $sec, $project_id );
        }
        $sections_html = ob_get_clean();

        // Build file row HTML
        $icon = match( $ext ) {
            'pptx' => '📊',
            'docx' => '📝',
            'pdf'  => '📕',
            default => '📄',
        };
        $file_html = '<div class="pl-file-row"><span class="pl-file-icon">' . $icon . '</span><span class="pl-file-name">' . esc_html( $file['name'] ) . '</span></div>';

        wp_send_json_success( [
            'message'       => count( $extracted_sections ) . ' section(s) extraite(s) de ' . esc_html( $file['name'] ),
            'sections_html' => $sections_html,
            'file_html'     => $file_html,
            'count'         => count( $extracted_sections ),
            'slide_images'  => $slide_images,
            'visual_data'   => $visual_data,
        ] );
    }

    /**
     * Extract text from PowerPoint (.pptx) — reads slide XML for <a:t> tags.
     */
    private static function extract_pptx( string $filepath ): array {
        $sections = [];
        $zip = new \ZipArchive();

        if ( $zip->open( $filepath ) !== true ) {
            return $sections;
        }

        $slide_num = 1;
        while ( true ) {
            $xml_content = $zip->getFromName( "ppt/slides/slide{$slide_num}.xml" );
            if ( $xml_content === false ) {
                break;
            }

            $text = self::extract_xml_tags( $xml_content, 'a:t' );
            if ( trim( $text ) !== '' ) {
                $sections[] = [
                    'id'      => 'section_' . uniqid(),
                    'title'   => "Diapositive {$slide_num}",
                    'content' => trim( $text ),
                ];
            }
            $slide_num++;
        }

        $zip->close();
        return $sections;
    }

    /**
     * Convert PPTX slides to PNG images via LibreOffice + pdftoppm.
     *
     * @param string $filepath      Absolute path to the .pptx file.
     * @param int    $attachment_id  WP attachment ID (used for folder naming).
     * @return array  Array of [ slide_num, url, width, height ] per slide.
     */
    private static function convert_pptx_to_images( string $filepath, int $attachment_id ): array {
        // 1. Temporary directory for conversion
        $tmp_dir = sys_get_temp_dir() . '/pl-slides-' . $attachment_id;
        if ( ! is_dir( $tmp_dir ) ) {
            mkdir( $tmp_dir, 0755, true );
        }

        // 2. PPTX → PDF via LibreOffice headless
        $pdf_cmd = sprintf(
            'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg( $tmp_dir ),
            escapeshellarg( $filepath )
        );
        shell_exec( $pdf_cmd );

        $pdf_name = pathinfo( basename( $filepath ), PATHINFO_FILENAME ) . '.pdf';
        $pdf_path = $tmp_dir . '/' . $pdf_name;

        if ( ! file_exists( $pdf_path ) ) {
            return []; // Conversion failed
        }

        // 3. PDF → PNG per page via pdftoppm (poppler-utils)
        $img_prefix = $tmp_dir . '/slide';
        $img_cmd = sprintf(
            'pdftoppm -png -r 150 %s %s 2>&1',
            escapeshellarg( $pdf_path ),
            escapeshellarg( $img_prefix )
        );
        shell_exec( $img_cmd );

        // 4. Collect generated images and move to uploads
        $upload_dir   = wp_upload_dir();
        $dest_dir     = $upload_dir['basedir'] . '/pedagolens/slides/' . $attachment_id;
        if ( ! is_dir( $dest_dir ) ) {
            mkdir( $dest_dir, 0755, true );
        }

        $dest_url_base = $upload_dir['baseurl'] . '/pedagolens/slides/' . $attachment_id;

        $images = glob( $tmp_dir . '/slide-*.png' );
        sort( $images );

        $result    = [];
        $slide_num = 1;
        foreach ( $images as $img ) {
            $dest_file = $dest_dir . '/slide-' . $slide_num . '.png';
            copy( $img, $dest_file );

            $size     = @getimagesize( $dest_file );
            $result[] = [
                'slide_num' => $slide_num,
                'url'       => $dest_url_base . '/slide-' . $slide_num . '.png',
                'width'     => $size[0] ?? 960,
                'height'    => $size[1] ?? 540,
            ];
            $slide_num++;
        }

        // Cleanup tmp
        array_map( 'unlink', glob( $tmp_dir . '/*' ) );
        @rmdir( $tmp_dir );

        return $result;
    }

    /**
     * Extract text from Word (.docx) — reads document.xml for <w:t> tags.
     */
    private static function extract_docx( string $filepath ): array {
        $sections = [];
        $zip = new \ZipArchive();

        if ( $zip->open( $filepath ) !== true ) {
            return $sections;
        }

        $xml_content = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( $xml_content === false ) {
            return $sections;
        }

        $text = self::extract_xml_tags( $xml_content, 'w:t' );
        if ( trim( $text ) === '' ) {
            return $sections;
        }

        // Split into paragraphs/sections by double newlines or large chunks
        $paragraphs = preg_split( '/\n{2,}/', $text );
        $chunk      = '';
        $chunk_num  = 1;

        foreach ( $paragraphs as $para ) {
            $para = trim( $para );
            if ( $para === '' ) continue;

            $chunk .= $para . "\n\n";

            // Create a section every ~500 chars or at the end
            if ( mb_strlen( $chunk ) > 500 ) {
                $sections[] = [
                    'id'      => 'section_' . uniqid(),
                    'title'   => "Section {$chunk_num}",
                    'content' => trim( $chunk ),
                ];
                $chunk = '';
                $chunk_num++;
            }
        }

        if ( trim( $chunk ) !== '' ) {
            $sections[] = [
                'id'      => 'section_' . uniqid(),
                'title'   => "Section {$chunk_num}",
                'content' => trim( $chunk ),
            ];
        }

        return $sections;
    }

    /**
     * Extract text from PDF using pdftotext if available.
     */
    private static function extract_pdf( string $filepath ): array {
        $sections = [];
        $text     = '';

        // Try pdftotext
        $pdftotext = trim( (string) shell_exec( 'which pdftotext 2>/dev/null' ) );
        if ( $pdftotext ) {
            $escaped = escapeshellarg( $filepath );
            $text    = (string) shell_exec( "{$pdftotext} {$escaped} - 2>/dev/null" );
        }

        if ( trim( $text ) === '' ) {
            // Fallback: just create one section noting the file was uploaded
            $sections[] = [
                'id'      => 'section_' . uniqid(),
                'title'   => 'Document PDF importé',
                'content' => '(Extraction automatique non disponible — pdftotext requis. Le fichier a été sauvegardé.)',
            ];
            return $sections;
        }

        // Split by page breaks or large chunks
        $pages = preg_split( '/\f/', $text );
        $page_num = 1;
        foreach ( $pages as $page ) {
            $page = trim( $page );
            if ( $page === '' ) continue;
            $sections[] = [
                'id'      => 'section_' . uniqid(),
                'title'   => "Page {$page_num}",
                'content' => $page,
            ];
            $page_num++;
        }

        return $sections;
    }

    /**
     * Extract text content from XML by tag name.
     */
    private static function extract_xml_tags( string $xml, string $tag ): string {
        $text = '';
        $dom  = new \DOMDocument();

        // Suppress warnings for malformed XML
        libxml_use_internal_errors( true );
        $dom->loadXML( $xml );
        libxml_clear_errors();

        // Use regex as fallback-friendly approach for namespaced tags
        if ( preg_match_all( '/<' . preg_quote( $tag, '/' ) . '[^>]*>(.*?)<\/' . preg_quote( $tag, '/' ) . '>/s', $xml, $matches ) ) {
            $last_was_newline = false;
            foreach ( $matches[1] as $i => $t ) {
                $decoded = html_entity_decode( $t, ENT_QUOTES | ENT_XML1, 'UTF-8' );
                $text .= $decoded . ' ';

                // Add newline between paragraphs (heuristic: after each run of text)
                if ( $i > 0 && $i % 8 === 0 ) {
                    $text .= "\n";
                }
            }
        }

        return $text;
    }

    /**
     * Extract visual layout data from a PPTX file.
     * Parses each slide's XML to get element positions, sizes, text formatting, and images.
     * Returns structured data that can be rendered as positioned HTML elements.
     *
     * @param string $filepath Path to the .pptx file.
     * @param int    $project_id Project ID for storing extracted media.
     * @return array Array of slides, each containing positioned elements.
     */
    private static function extract_pptx_visual( string $filepath, int $project_id ): array {
        $slides = [];
        $zip    = new \ZipArchive();

        if ( $zip->open( $filepath ) !== true ) {
            return $slides;
        }

        // EMU to px conversion: 1 inch = 914400 EMU, 1 inch = 96 px at 96 DPI
        // PowerPoint default slide: 12192000 x 6858000 EMU = 960 x 540 px (at 96 DPI)
        $emu_to_px = 96.0 / 914400.0;

        // Slide dimensions (default 10" x 7.5" widescreen = 16:9)
        $slide_w_px = 960;
        $slide_h_px = 540;

        // Try to get actual slide size from presentation.xml
        $pres_xml = $zip->getFromName( 'ppt/presentation.xml' );
        if ( $pres_xml ) {
            if ( preg_match( '/<p:sldSz[^>]*cx="(\d+)"[^>]*cy="(\d+)"/', $pres_xml, $sz ) ) {
                $slide_w_px = round( (int) $sz[1] * $emu_to_px );
                $slide_h_px = round( (int) $sz[2] * $emu_to_px );
            }
        }

        // Parse relationships for each slide to resolve image references
        // Also extract media files
        $media_dir = wp_upload_dir()['basedir'] . '/pedagolens/slides/' . $project_id . '/media/';
        $media_url = wp_upload_dir()['baseurl'] . '/pedagolens/slides/' . $project_id . '/media/';
        if ( ! is_dir( $media_dir ) ) {
            wp_mkdir_p( $media_dir );
        }

        $slide_num = 1;
        while ( true ) {
            $slide_xml = $zip->getFromName( "ppt/slides/slide{$slide_num}.xml" );
            if ( $slide_xml === false ) {
                break;
            }

            // Parse slide relationships to map rId → media file
            $rels_xml  = $zip->getFromName( "ppt/slides/_rels/slide{$slide_num}.xml.rels" );
            $rels_map  = [];
            if ( $rels_xml ) {
                if ( preg_match_all( '/<Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"[^>]*Type="([^"]+)"/', $rels_xml, $rm, PREG_SET_ORDER ) ) {
                    foreach ( $rm as $r ) {
                        $rels_map[ $r[1] ] = [ 'target' => $r[2], 'type' => $r[3] ];
                    }
                }
            }

            // Extract slide background color
            $bg_color = '#ffffff';
            if ( preg_match( '/<p:bg>.*?<a:srgbClr val="([0-9a-fA-F]{6})"/', $slide_xml, $bgm ) ) {
                $bg_color = '#' . $bgm[1];
            } elseif ( preg_match( '/<p:bg>.*?<a:prstClr val="([^"]+)"/', $slide_xml, $bgm2 ) ) {
                $bg_color = self::pptx_preset_color( $bgm2[1] );
            }

            $elements = [];

            // Extract shapes (text boxes) — <p:sp> elements
            // Use a regex-based approach since DOMDocument with namespaces is fragile
            if ( preg_match_all( '/<p:sp\b[^>]*>.*?<\/p:sp>/s', $slide_xml, $sp_matches ) ) {
                foreach ( $sp_matches[0] as $sp_xml ) {
                    $el = self::parse_pptx_shape( $sp_xml, $emu_to_px );
                    if ( $el ) {
                        $elements[] = $el;
                    }
                }
            }

            // Extract images — <p:pic> elements
            if ( preg_match_all( '/<p:pic\b[^>]*>.*?<\/p:pic>/s', $slide_xml, $pic_matches ) ) {
                foreach ( $pic_matches[0] as $pic_xml ) {
                    $el = self::parse_pptx_picture( $pic_xml, $emu_to_px, $rels_map, $zip, $media_dir, $media_url, $slide_num );
                    if ( $el ) {
                        $elements[] = $el;
                    }
                }
            }

            $slides[] = [
                'slide_num'  => $slide_num,
                'width'      => $slide_w_px,
                'height'     => $slide_h_px,
                'bg_color'   => $bg_color,
                'elements'   => $elements,
            ];

            $slide_num++;
        }

        $zip->close();
        return $slides;
    }

    /**
     * Parse a <p:sp> (shape/text box) element from PPTX XML.
     */
    private static function parse_pptx_shape( string $sp_xml, float $emu_to_px ): ?array {
        // Get position and size from <a:off> and <a:ext>
        $x = $y = $w = $h = 0;
        if ( preg_match( '/<a:off[^>]*x="(\d+)"[^>]*y="(\d+)"/', $sp_xml, $off ) ) {
            $x = round( (int) $off[1] * $emu_to_px );
            $y = round( (int) $off[2] * $emu_to_px );
        }
        if ( preg_match( '/<a:ext[^>]*cx="(\d+)"[^>]*cy="(\d+)"/', $sp_xml, $ext ) ) {
            $w = round( (int) $ext[1] * $emu_to_px );
            $h = round( (int) $ext[2] * $emu_to_px );
        }

        if ( $w < 5 || $h < 5 ) {
            return null; // Skip tiny/invisible shapes
        }

        // Extract text runs with formatting
        $paragraphs = [];
        if ( preg_match_all( '/<a:p\b[^>]*>(.*?)<\/a:p>/s', $sp_xml, $p_matches ) ) {
            foreach ( $p_matches[1] as $p_xml ) {
                $para = [ 'runs' => [], 'align' => 'left' ];

                // Paragraph alignment
                if ( preg_match( '/<a:pPr[^>]*algn="([^"]+)"/', $p_xml, $algn ) ) {
                    $para['align'] = match( $algn[1] ) {
                        'ctr'  => 'center',
                        'r'    => 'right',
                        'just' => 'justify',
                        default => 'left',
                    };
                }

                // Text runs
                if ( preg_match_all( '/<a:r>(.*?)<\/a:r>/s', $p_xml, $r_matches ) ) {
                    foreach ( $r_matches[1] as $r_xml ) {
                        $run = [ 'text' => '', 'bold' => false, 'italic' => false, 'size' => 18, 'color' => '#000000' ];

                        // Text content
                        if ( preg_match( '/<a:t>(.*?)<\/a:t>/s', $r_xml, $t ) ) {
                            $run['text'] = html_entity_decode( $t[1], ENT_QUOTES | ENT_XML1, 'UTF-8' );
                        }

                        // Run properties
                        if ( preg_match( '/<a:rPr([^>]*)/', $r_xml, $rpr ) ) {
                            $rpr_attrs = $rpr[1];
                            if ( preg_match( '/b="1"/', $rpr_attrs ) ) $run['bold'] = true;
                            if ( preg_match( '/i="1"/', $rpr_attrs ) ) $run['italic'] = true;
                            if ( preg_match( '/sz="(\d+)"/', $rpr_attrs, $sz ) ) {
                                $run['size'] = round( (int) $sz[1] / 100 ); // hundredths of a point → pt
                            }
                        }

                        // Run color
                        if ( preg_match( '/<a:rPr[^>]*>.*?<a:solidFill>.*?<a:srgbClr val="([0-9a-fA-F]{6})".*?<\/a:solidFill>/s', $r_xml, $clr ) ) {
                            $run['color'] = '#' . $clr[1];
                        }

                        if ( $run['text'] !== '' ) {
                            $paragraphs[] = $para;
                            $para['runs'][] = $run;
                        }
                    }
                }

                // Also check for <a:fld> (field) text
                if ( preg_match_all( '/<a:fld[^>]*>.*?<a:t>(.*?)<\/a:t>.*?<\/a:fld>/s', $p_xml, $fld ) ) {
                    foreach ( $fld[1] as $ft ) {
                        $para['runs'][] = [ 'text' => html_entity_decode( $ft, ENT_QUOTES | ENT_XML1, 'UTF-8' ), 'bold' => false, 'italic' => false, 'size' => 14, 'color' => '#666666' ];
                    }
                }

                if ( ! empty( $para['runs'] ) ) {
                    $paragraphs[] = $para;
                }
            }
        }

        // Skip shapes with no text
        if ( empty( $paragraphs ) ) {
            // Check if it's a filled shape (rectangle, etc.) — still render it
            $has_fill = preg_match( '/<a:solidFill>/', $sp_xml );
            if ( ! $has_fill ) {
                return null;
            }
        }

        // Shape fill color
        $fill_color = null;
        if ( preg_match( '/<p:spPr>.*?<a:solidFill>.*?<a:srgbClr val="([0-9a-fA-F]{6})".*?<\/a:solidFill>/s', $sp_xml, $fc ) ) {
            $fill_color = '#' . $fc[1];
        }

        return [
            'type'       => 'text',
            'x'          => $x,
            'y'          => $y,
            'w'          => $w,
            'h'          => $h,
            'paragraphs' => $paragraphs,
            'fill'       => $fill_color,
        ];
    }

    /**
     * Parse a <p:pic> (picture) element from PPTX XML.
     */
    private static function parse_pptx_picture( string $pic_xml, float $emu_to_px, array $rels_map, \ZipArchive $zip, string $media_dir, string $media_url, int $slide_num ): ?array {
        $x = $y = $w = $h = 0;
        if ( preg_match( '/<a:off[^>]*x="(\d+)"[^>]*y="(\d+)"/', $pic_xml, $off ) ) {
            $x = round( (int) $off[1] * $emu_to_px );
            $y = round( (int) $off[2] * $emu_to_px );
        }
        if ( preg_match( '/<a:ext[^>]*cx="(\d+)"[^>]*cy="(\d+)"/', $pic_xml, $ext ) ) {
            $w = round( (int) $ext[1] * $emu_to_px );
            $h = round( (int) $ext[2] * $emu_to_px );
        }

        if ( $w < 5 || $h < 5 ) return null;

        // Get image reference (rId)
        $img_url = '';
        if ( preg_match( '/<a:blip[^>]*r:embed="([^"]+)"/', $pic_xml, $blip ) ) {
            $rid = $blip[1];
            if ( isset( $rels_map[ $rid ] ) ) {
                $target = $rels_map[ $rid ]['target'];
                // Target is relative like "../media/image1.png"
                $media_path = preg_replace( '/^\.\.\//', 'ppt/', $target );
                $media_data = $zip->getFromName( $media_path );
                if ( $media_data !== false ) {
                    $filename = basename( $media_path );
                    $dest     = $media_dir . 'slide' . $slide_num . '_' . $filename;
                    if ( ! file_exists( $dest ) ) {
                        file_put_contents( $dest, $media_data );
                    }
                    $img_url = $media_url . 'slide' . $slide_num . '_' . $filename;
                }
            }
        }

        if ( ! $img_url ) return null;

        return [
            'type' => 'image',
            'x'    => $x,
            'y'    => $y,
            'w'    => $w,
            'h'    => $h,
            'src'  => $img_url,
        ];
    }

    // -------------------------------------------------------------------------
    // Mock — Pre-generated suggestions for demo mode
    // -------------------------------------------------------------------------

    private static function get_mock_suggestions( string $section_id, int $project_id ): array {
        $sections = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $content  = '';
        $title    = '';
        $slide_num = 0;
        foreach ( $sections as $idx => $s ) {
            if ( ( $s['id'] ?? '' ) === $section_id ) {
                $content   = $s['content'] ?? '';
                $title     = $s['title'] ?? '';
                $slide_num = $idx + 1;
                break;
            }
        }

        // Generate 3 realistic suggestions per section
        $suggestions = [];
        $base_id     = substr( md5( $section_id ), 0, 8 );

        // Get first 120 chars of content as "original"
        $original_snippet = mb_substr( trim( $content ), 0, 120 );

        $suggestions[] = [
            'id'                => 'mock_' . $base_id . '_1',
            'section'           => $section_id,
            'modification_type' => 'reformulation',
            'rationale'         => 'Cette section contient des phrases longues et complexes qui peuvent surcharger les étudiants avec un profil TDAH ou en surcharge cognitive. Découper en étapes numérotées améliore la lisibilité.',
            'original'          => $original_snippet,
            'proposed'          => '📋 Étapes à suivre :' . "\n" . '1. Lisez attentivement la consigne ci-dessous.' . "\n" . '2. Identifiez les mots-clés importants.' . "\n" . '3. ' . mb_substr( $original_snippet, 0, 60 ) . "\n" . '4. Vérifiez votre compréhension avec un pair.',
            'impact_score'      => 78,
            'slide_num'         => $slide_num,
            'profile_target'    => 'concentration_tdah',
            'impact_delta'      => [
                'concentration_tdah'  => 12,
                'surcharge_cognitive' => 8,
                'langue_seconde'      => 5,
                'faible_autonomie'    => 10,
            ],
        ];

        $suggestions[] = [
            'id'                => 'mock_' . $base_id . '_2',
            'section'           => $section_id,
            'modification_type' => 'ajout',
            'rationale'         => 'Ajouter un exemple concret aide les étudiants en langue seconde et ceux avec une faible autonomie à mieux comprendre les attentes.',
            'original'          => '',
            'proposed'          => '💡 Exemple attendu : « Dans ce texte, l\'auteur utilise la métaphore du voyage pour illustrer le passage du temps. Cette figure de style crée un effet de nostalgie chez le lecteur. »',
            'impact_score'      => 85,
            'slide_num'         => $slide_num,
            'profile_target'    => 'langue_seconde',
            'impact_delta'      => [
                'concentration_tdah'  => 3,
                'surcharge_cognitive' => 6,
                'langue_seconde'      => 15,
                'faible_autonomie'    => 11,
            ],
        ];

        $suggestions[] = [
            'id'                => 'mock_' . $base_id . '_3',
            'section'           => $section_id,
            'modification_type' => 'restructuration',
            'rationale'         => 'Les critères d\'évaluation sont implicites. Les rendre explicites réduit l\'anxiété et aide les étudiants à faible autonomie à s\'auto-évaluer.',
            'original'          => 'Rédigez une analyse en mobilisant les concepts vus au cours.',
            'proposed'          => 'Rédigez une analyse en suivant ces critères :' . "\n" . '✅ Identifier 2 procédés stylistiques (4 pts)' . "\n" . '✅ Expliquer l\'effet sur le lecteur (4 pts)' . "\n" . '✅ Relier au thème principal (2 pts)',
            'impact_score'      => 92,
            'slide_num'         => $slide_num,
            'profile_target'    => 'anxieux_consignes',
            'impact_delta'      => [
                'concentration_tdah'  => 7,
                'surcharge_cognitive' => 10,
                'langue_seconde'      => 4,
                'faible_autonomie'    => 14,
                'anxieux_consignes'   => 18,
            ],
        ];

        // Mock profile scores
        $profile_scores = [
            'concentration_tdah'  => 58 + rand( 0, 5 ),
            'surcharge_cognitive' => 54 + rand( 0, 5 ),
            'langue_seconde'      => 61 + rand( 0, 5 ),
            'faible_autonomie'    => 49 + rand( 0, 5 ),
            'anxieux_consignes'   => 52 + rand( 0, 5 ),
            'avance_rapide'       => 82 + rand( 0, 5 ),
            'usage_passif_ia'     => 65 + rand( 0, 5 ),
        ];

        return [
            'suggestions'    => $suggestions,
            'profile_scores' => $profile_scores,
        ];
    }

    /**
     * Convert PowerPoint preset color name to hex.
     */
    /**
     * Decode broken Unicode escape sequences stored as literal text.
     * Converts patterns like "u00e9" → "é", "u00e8" → "è", etc.
     */
    private static function decode_unicode( string $text ): string {
        // Match patterns like u00e9, u00c9, u2019, u00e0 etc. (4-hex-digit unicode escapes without backslash)
        return preg_replace_callback( '/u([0-9a-fA-F]{4})/', function( $m ) {
            return mb_convert_encoding( pack( 'H*', $m[1] ), 'UTF-8', 'UCS-2BE' );
        }, $text );
    }

    private static function pptx_preset_color( string $name ): string {
        $colors = [
            'black'   => '#000000', 'white'  => '#ffffff', 'red'    => '#ff0000',
            'green'   => '#00ff00', 'blue'   => '#0000ff', 'yellow' => '#ffff00',
            'dkBlue'  => '#00008b', 'ltGray' => '#d3d3d3', 'dkGray' => '#a9a9a9',
        ];
        return $colors[ $name ] ?? '#ffffff';
    }

}
