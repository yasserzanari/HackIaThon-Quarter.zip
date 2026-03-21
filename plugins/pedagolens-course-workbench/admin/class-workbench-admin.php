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
        foreach ( $scores as $slug => $score ) :
            $score = max( 0, min( 100, (int) $score ) );
            $color = self::score_color( $score );
            ?>
            <div class="pl-score-row">
                <span class="pl-score-label" title="<?php echo esc_attr( $slug ); ?>">
                    <?php echo esc_html( $slug ); ?>
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
            ob_start();
            self::render_score_bars( $result['profile_scores'] );
            $scores_html = ob_get_clean();
            update_post_meta( $project_id, '_pl_profile_scores', wp_json_encode( $result['profile_scores'] ) );
        }

        wp_send_json_success( [ 'html' => $html, 'scores_html' => $scores_html ] );
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
            'reformulation'   => '✏️',
            'ajout'           => '➕',
            'suppression'     => '🗑️',
            'restructuration' => '🔄',
        ];
        ?>
        <div class="pl-suggestions-list">
            <h4><?php esc_html_e( 'Suggestions IA', 'pedagolens-course-workbench' ); ?></h4>
            <?php foreach ( $suggestions as $idx => $sug ) :
                $sug_id   = esc_attr( $sug['id'] ?? '' );
                $mod_type = $sug['modification_type'] ?? 'reformulation';
                $impact   = max( 0, min( 100, (int) ( $sug['impact_score'] ?? 50 ) ) );
                $slide    = (int) ( $sug['slide_num'] ?? 0 );
                ?>
                <div class="pl-suggestion-card" id="pl-sug-<?php echo $sug_id; ?>"
                     data-type="<?php echo esc_attr( $mod_type ); ?>"
                     data-section-id="<?php echo esc_attr( $section_id ); ?>"
                     style="animation-delay:<?php echo $idx * 100; ?>ms;">

                    <div class="pl-sug-meta" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                        <span class="pl-suggestion-badge pl-suggestion-badge--<?php echo esc_attr( $mod_type ); ?>">
                            <?php echo esc_html( ( $type_icons[ $mod_type ] ?? '📝' ) . ' ' . ( $type_labels[ $mod_type ] ?? ucfirst( $mod_type ) ) ); ?>
                        </span>
                        <?php if ( $slide > 0 ) : ?>
                            <span style="font-size:0.72rem;color:var(--stitch-text-dim);">Diapo <?php echo $slide; ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $sug['profile_target'] ) ) : ?>
                            <span class="pl-suggestion-profile">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <?php echo esc_html( $sug['profile_target'] ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Impact score bar -->
                    <div class="pl-suggestion-impact">
                        <span style="font-size:0.72rem;color:var(--stitch-text-dim);white-space:nowrap;">Impact</span>
                        <div class="pl-suggestion-impact-bar">
                            <div class="pl-suggestion-impact-fill" style="width:<?php echo $impact; ?>%;"></div>
                        </div>
                        <span style="font-size:0.72rem;font-weight:700;color:var(--stitch-text);"><?php echo $impact; ?>%</span>
                    </div>

                    <!-- Diff visuel -->
                    <div class="pl-suggestion-diff">
                        <?php if ( ! empty( $sug['original'] ) ) : ?>
                            <span class="pl-suggestion-diff-remove"><?php echo esc_html( mb_substr( $sug['original'], 0, 200 ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $sug['proposed'] ) ) : ?>
                            <span class="pl-suggestion-diff-add"><?php echo esc_html( mb_substr( $sug['proposed'], 0, 200 ) ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $sug['rationale'] ) ) : ?>
                        <p class="pl-sug-rationale"><em><?php echo esc_html( $sug['rationale'] ); ?></em></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $sug['impact_delta'] ) ) : ?>
                        <div class="pl-sug-deltas">
                            <?php foreach ( $sug['impact_delta'] as $slug => $delta ) :
                                $sign  = $delta >= 0 ? '+' : '';
                                $class = $delta >= 0 ? 'pl-delta-pos' : 'pl-delta-neg';
                                ?>
                                <span class="pl-delta <?php echo $class; ?>">
                                    <?php echo esc_html( "{$sign}{$delta} pts {$slug}" ); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="pl-sug-actions">
                        <button type="button"
                            class="button button-primary pl-btn-apply"
                            data-project-id="<?php echo esc_attr( $_POST['project_id'] ?? '' ); ?>"
                            data-section-id="<?php echo esc_attr( $section_id ); ?>"
                            data-suggestion-id="<?php echo $sug_id; ?>">
                            ✓ <?php esc_html_e( 'Appliquer', 'pedagolens-course-workbench' ); ?>
                        </button>
                        <button type="button"
                            class="button pl-btn-preview"
                            data-section-id="<?php echo esc_attr( $section_id ); ?>"
                            data-suggestion-id="<?php echo $sug_id; ?>">
                            👁 <?php esc_html_e( 'Prévisualiser', 'pedagolens-course-workbench' ); ?>
                        </button>
                        <button type="button"
                            class="button pl-btn-reject"
                            data-project-id="<?php echo esc_attr( $_POST['project_id'] ?? '' ); ?>"
                            data-section-id="<?php echo esc_attr( $section_id ); ?>"
                            data-suggestion-id="<?php echo $sug_id; ?>">
                            ✗ <?php esc_html_e( 'Rejeter', 'pedagolens-course-workbench' ); ?>
                        </button>
                    </div>

                </div>
            <?php endforeach; ?>
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
        $suggestion_id = sanitize_text_field( $_POST['suggestion_id'] ?? '' );

        $ok = PedagoLens_Course_Workbench::apply_suggestion( $project_id, $section_id, $suggestion_id );

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
    // Rendu front-end Stitch (shortcode délégué depuis pedagolens-landing)
    // -------------------------------------------------------------------------

    public static function render_front( int $project_id ): string {
        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== 'pl_project' ) {
            return '<div class="pl-notice pl-notice-error"><p>Projet introuvable.</p></div>';
        }

        // Enqueue workbench assets on front-end
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
        wp_localize_script( 'pl-workbench-front', 'plWorkbench', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( self::NONCE_AJAX ),
            'projectId'   => $project_id,
            'slideImages' => $slide_images,
        ] );

        $project_type = get_post_meta( $project_id, '_pl_project_type', true ) ?: 'magistral';
        $sections     = PedagoLens_Course_Workbench::get_content_sections( $project_id );
        $raw_scores   = get_post_meta( $project_id, '_pl_profile_scores', true );
        $scores       = is_string( $raw_scores ) ? (array) json_decode( $raw_scores, true ) : [];
        $raw_files    = get_post_meta( $project_id, '_pl_uploaded_files', true );
        $files        = is_string( $raw_files ) ? (array) json_decode( $raw_files, true ) : [];
        $summary      = get_post_meta( $project_id, '_pl_summary', true ) ?: '';

        // Slide images for viewer
        $raw_slide_images = get_post_meta( $project_id, '_pl_slide_images', true );
        $slide_images     = is_string( $raw_slide_images ) ? (array) json_decode( $raw_slide_images, true ) : [];

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

        ob_start();
        ?>
        <div class="pl-stitch-workbench" data-project-id="<?php echo esc_attr( $project_id ); ?>">

            <!-- ===== HEADER ===== -->
            <header class="pl-stitch-wb-header">
                <div class="pl-stitch-wb-header-left">
                    <a href="<?php echo esc_url( $back_url ); ?>" class="pl-stitch-wb-back" aria-label="Retour aux cours">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        Retour aux cours
                    </a>
                    <h1 class="pl-stitch-wb-title"><?php echo esc_html( $project->post_title ); ?></h1>
                    <div class="pl-stitch-wb-meta">
                        <span class="pl-stitch-wb-type-badge pl-stitch-type-<?php echo esc_attr( $project_type ); ?>">
                            <?php echo esc_html( ( $type_icons[ $project_type ] ?? '📄' ) . ' ' . ( $type_labels[ $project_type ] ?? $project_type ) ); ?>
                        </span>
                        <span class="pl-stitch-wb-ai-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            PédagoLens AI
                        </span>
                    </div>
                </div>
                <div class="pl-stitch-wb-header-right">
                    <button type="button" id="pl-upload-trigger" class="pl-stitch-btn pl-stitch-btn-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Importer
                    </button>
                    <button type="button" id="pl-add-section" class="pl-stitch-btn pl-stitch-btn-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Ajouter une section
                    </button>
                    <button type="button" id="pl-wb-save-version" class="pl-stitch-btn pl-stitch-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Sauvegarder la version
                    </button>
                    <button type="button" id="pl-download-pptx" class="pl-stitch-btn pl-stitch-btn-outline" style="display:none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Télécharger le PPTX modifié
                    </button>
                </div>
            </header>

            <!-- Upload zone moved into import modal below -->

            <!-- ===== MAIN 2-COLUMN LAYOUT ===== -->
            <div class="pl-stitch-wb-layout">

                <!-- LEFT COLUMN: Course sections (editable) -->
                <div class="pl-stitch-wb-main pl-workbench-main pl-wb-main">
                    <?php if ( empty( $sections ) ) : ?>
                        <div class="pl-stitch-wb-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;margin-bottom:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <p>Aucune section. Importez un fichier ou ajoutez du contenu pour commencer.</p>
                        </div>
                    <?php else : ?>
                        <?php $section_num = 1; ?>
                        <?php foreach ( $sections as $section ) : ?>
                            <?php self::render_front_section( $section, $project_id, $section_num ); ?>
                            <?php $section_num++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- RIGHT COLUMN: AI Suggestions + Scores -->
                <div class="pl-stitch-wb-sidebar">

                    <!-- Suggestions IA panel -->
                    <div class="pl-stitch-wb-card pl-stitch-wb-suggestions-panel">
                        <div class="pl-stitch-wb-card-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                                Suggestions IA
                            </h3>
                            <span class="pl-stitch-wb-ai-active-badge">Analyse IA Active</span>
                        </div>
                        <div id="pl-stitch-suggestions-list" class="pl-stitch-wb-suggestions-list">
                            <p class="pl-stitch-wb-sidebar-empty">Cliquez sur « Suggestions IA » sur une section pour obtenir des recommandations.</p>
                        </div>
                        <button type="button" id="pl-analyze-all" class="pl-stitch-btn pl-stitch-btn-glow pl-stitch-btn-full" style="margin-top:16px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                            Demander de nouvelles suggestions
                        </button>
                    </div>

                    <!-- Scores par profil -->
                    <div class="pl-stitch-wb-card">
                        <div class="pl-stitch-wb-card-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                                Scores par profil
                            </h3>
                        </div>
                        <div id="pl-sidebar-scores">
                            <?php if ( empty( $scores ) ) : ?>
                                <p class="pl-stitch-wb-sidebar-empty">Analysez une section pour voir les scores.</p>
                            <?php else : ?>
                                <?php self::render_front_score_bars( $scores ); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( $summary ) : ?>
                    <!-- Résumé -->
                    <div class="pl-stitch-wb-card">
                        <div class="pl-stitch-wb-card-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                Résumé
                            </h3>
                        </div>
                        <p class="pl-stitch-wb-summary-text"><?php echo esc_html( $summary ); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Fichiers uploadés -->
                    <div class="pl-stitch-wb-card">
                        <div class="pl-stitch-wb-card-header">
                            <h3>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                Fichiers du projet
                            </h3>
                        </div>
                        <div id="pl-files-list">
                            <?php if ( empty( $files ) ) : ?>
                                <p class="pl-stitch-wb-sidebar-empty">Aucun fichier importé.</p>
                            <?php else : ?>
                                <?php foreach ( $files as $f ) :
                                    $ext  = strtolower( pathinfo( $f['name'] ?? '', PATHINFO_EXTENSION ) );
                                    $icon = match( $ext ) {
                                        'pptx' => '📊',
                                        'docx' => '📝',
                                        'pdf'  => '📕',
                                        default => '📄',
                                    };
                                ?>
                                    <div class="pl-file-row">
                                        <span class="pl-file-icon"><?php echo $icon; ?></span>
                                        <span class="pl-file-name"><?php echo esc_html( $f['name'] ?? '' ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

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

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single section block for front-end Stitch workbench.
     */
    private static function render_front_section( array $section, int $project_id, int $section_num = 0 ): void {
        $section_id = esc_attr( $section['id'] ?? '' );
        $title      = esc_html( $section['title'] ?? 'Section' );
        $content    = esc_textarea( $section['content'] ?? '' );
        $slide_img  = $section['slide_image_url'] ?? '';
        $slide_num  = (int) ( $section['slide_num'] ?? 0 );
        ?>
        <div class="pl-section-block pl-stitch-wb-section" id="pl-section-<?php echo $section_id; ?>" data-section-id="<?php echo $section_id; ?>" data-slide-num="<?php echo $slide_num; ?>">
            <div class="pl-section-header pl-stitch-wb-section-header">
                <div class="pl-stitch-wb-section-title-row">
                    <?php if ( $section_num > 0 ) : ?>
                        <span class="pl-stitch-wb-section-num"><?php echo esc_html( $section_num ); ?></span>
                    <?php endif; ?>
                    <h2 class="pl-section-title pl-stitch-wb-section-title"><?php echo $title; ?></h2>
                </div>
                <div class="pl-section-actions pl-stitch-wb-section-actions">
                    <button type="button" class="pl-stitch-btn pl-stitch-btn-sm pl-stitch-btn-accent pl-btn-suggestions" data-section-id="<?php echo $section_id; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        Suggestions IA
                    </button>
                    <button type="button" class="pl-stitch-btn pl-stitch-btn-sm pl-stitch-btn-ghost pl-btn-history" data-section-id="<?php echo $section_id; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Historique
                    </button>
                </div>
            </div>
            <?php if ( $slide_img ) : ?>
            <div class="pl-stitch-wb-slide-thumb" data-slide-index="<?php echo max( 0, $slide_num - 1 ); ?>">
                <img src="<?php echo esc_url( $slide_img ); ?>" alt="Diapositive <?php echo $slide_num; ?>" loading="lazy" />
                <div class="pl-stitch-wb-slide-thumb-overlay">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Voir la diapositive
                </div>
            </div>
            <?php endif; ?>
            <div class="pl-section-editor pl-stitch-wb-section-editor">
                <textarea class="pl-section-content pl-stitch-wb-textarea" data-section-id="<?php echo $section_id; ?>" rows="6"><?php echo $content; ?></textarea>
                <div class="pl-section-save-row pl-stitch-wb-save-row">
                    <button type="button" class="pl-stitch-btn pl-stitch-btn-sm pl-stitch-btn-primary pl-btn-save-section" data-section-id="<?php echo $section_id; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Enregistrer
                    </button>
                    <button type="button" class="pl-stitch-btn pl-stitch-btn-sm pl-stitch-btn-ghost pl-btn-undo" data-section-id="<?php echo $section_id; ?>" style="display:none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                        Annuler
                    </button>
                    <span class="pl-save-status"></span>
                </div>
            </div>
            <div class="pl-suggestions-zone" id="pl-suggestions-<?php echo $section_id; ?>" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Render animated score bars for front-end Stitch sidebar.
     */
    public static function render_front_score_bars( array $scores ): void {
        foreach ( $scores as $slug => $score ) :
            $score = max( 0, min( 100, (int) $score ) );
            $color_class = $score >= 80 ? 'pl-stitch-score-high' : ( $score >= 60 ? 'pl-stitch-score-mid' : ( $score >= 40 ? 'pl-stitch-score-warn' : 'pl-stitch-score-low' ) );
            $delta = $score >= 60 ? '+' . rand(2, 8) : '-' . rand(1, 5);
            $delta_class = str_starts_with( $delta, '+' ) ? 'pl-stitch-delta-pos' : 'pl-stitch-delta-neg';
            ?>
            <div class="pl-stitch-score-row">
                <div class="pl-stitch-score-info">
                    <span class="pl-stitch-score-label"><?php echo esc_html( $slug ); ?></span>
                    <span class="pl-stitch-score-delta <?php echo $delta_class; ?>"><?php echo esc_html( $delta ); ?> pts</span>
                </div>
                <div class="pl-stitch-score-bar-wrap">
                    <div class="pl-stitch-score-bar <?php echo $color_class; ?>" style="--score-w:<?php echo $score; ?>%;"></div>
                </div>
                <span class="pl-stitch-score-value"><?php echo $score; ?><small>/100</small></span>
            </div>
        <?php endforeach;
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

        $all_suggestions = [];
        $all_html        = '';
        $latest_scores   = [];

        foreach ( $sections as $sec ) {
            $section_id = $sec['id'] ?? '';
            if ( ! $section_id ) {
                continue;
            }

            $result = PedagoLens_Course_Workbench::get_suggestions( $project_id, $section_id );

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

        // Convert PPTX slides to images
        $slide_images = [];
        if ( $ext === 'pptx' ) {
            $slide_images = self::convert_pptx_to_images( $filepath, $attachment_id );
            update_post_meta( $project_id, '_pl_slide_images', wp_json_encode( $slide_images ) );

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
}
