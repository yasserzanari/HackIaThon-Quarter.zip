<?php
/**
 * PedagoLens_Dashboard_Admin
 *
 * Interface admin du tableau de bord enseignant :
 * liste des cours, déclenchement d'analyse (AJAX), affichage des scores/recommandations,
 * gestion des projets.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Dashboard_Admin {

    private const MENU_SLUG  = 'pl-teacher-dashboard';
    private const NONCE_AJAX = 'pl_dashboard_ajax';

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function register(): void {
        add_action( 'admin_menu',                          [ self::class, 'add_menu' ] );
        add_action( 'wp_ajax_pl_analyze_course',           [ self::class, 'ajax_analyze' ] );
        add_action( 'wp_ajax_pl_create_project',           [ self::class, 'ajax_create_project' ] );
        add_action( 'wp_ajax_pl_create_course',            [ self::class, 'ajax_create_course' ] );
        add_action( 'admin_enqueue_scripts',               [ self::class, 'enqueue_assets' ] );
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
            __( 'Tableau de bord enseignant', 'pedagolens-teacher-dashboard' ),
            __( 'Dashboard', 'pedagolens-teacher-dashboard' ),
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
            'pl-dashboard-admin',
            PL_DASHBOARD_PLUGIN_URL . 'assets/js/dashboard-admin.js',
            [ 'jquery' ],
            PL_DASHBOARD_VERSION,
            true
        );

        wp_localize_script( 'pl-dashboard-admin', 'plDashboard', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_AJAX ),
            'i18n'    => [
                'analyzing'    => __( 'Analyse en cours…', 'pedagolens-teacher-dashboard' ),
                'analyzeError' => __( 'Erreur lors de l\'analyse.', 'pedagolens-teacher-dashboard' ),
            ],
        ] );

        wp_enqueue_style(
            'pl-dashboard-admin',
            PL_DASHBOARD_PLUGIN_URL . 'assets/css/dashboard-admin.css',
            [],
            PL_DASHBOARD_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // Page principale
    // -------------------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'pedagolens-teacher-dashboard' ) );
        }

        $courses = self::get_courses();
        $mode    = get_option( 'pl_ai_mode', 'mock' );
        ?>
        <div class="wrap pl-dashboard">
            <h1><?php esc_html_e( 'Tableau de bord enseignant', 'pedagolens-teacher-dashboard' ); ?></h1>

            <?php if ( $mode === 'mock' ) : ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e( 'Mode mock actif — les analyses utilisent des données de démonstration.', 'pedagolens-teacher-dashboard' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( empty( $courses ) ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'Aucun cours trouvé.', 'pedagolens-teacher-dashboard' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pl_course' ) ); ?>">
                            <?php esc_html_e( 'Créer un cours', 'pedagolens-teacher-dashboard' ); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <div class="pl-courses-grid">
                    <?php foreach ( $courses as $course ) : ?>
                        <?php self::render_course_card( $course ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Carte cours
    // -------------------------------------------------------------------------

    private static function render_course_card( WP_Post $course ): void {
        $course_type = get_post_meta( $course->ID, '_pl_course_type', true ) ?: 'magistral';
        $analyses    = self::get_latest_analysis( $course->ID );
        $projects    = PedagoLens_Teacher_Dashboard::get_projects( $course->ID );
        ?>
        <div class="pl-course-card" id="pl-course-<?php echo (int) $course->ID; ?>">

            <div class="pl-course-header">
                <h2><?php echo esc_html( $course->post_title ); ?></h2>
                <span class="pl-course-type-badge pl-type-<?php echo esc_attr( $course_type ); ?>">
                    <?php echo esc_html( $course_type ); ?>
                </span>
            </div>

            <div class="pl-course-actions">
                <button
                    type="button"
                    class="button button-primary pl-btn-analyze"
                    data-course-id="<?php echo (int) $course->ID; ?>"
                >
                    <?php esc_html_e( 'Analyser', 'pedagolens-teacher-dashboard' ); ?>
                </button>
                <button
                    type="button"
                    class="button pl-btn-new-project"
                    data-course-id="<?php echo (int) $course->ID; ?>"
                    data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
                >
                    + <?php esc_html_e( 'Nouveau projet', 'pedagolens-teacher-dashboard' ); ?>
                </button>
                <a href="<?php echo esc_url( get_edit_post_link( $course->ID ) ); ?>" class="button button-small">
                    <?php esc_html_e( 'Modifier', 'pedagolens-teacher-dashboard' ); ?>
                </a>
            </div>

            <!-- Zone résultats d'analyse (remplie par AJAX) -->
            <div class="pl-analysis-result" id="pl-analysis-<?php echo (int) $course->ID; ?>">
                <?php if ( $analyses ) : ?>
                    <?php self::render_analysis_result( $analyses ); ?>
                <?php endif; ?>
            </div>

            <!-- Liste des projets -->
            <?php if ( ! empty( $projects ) ) : ?>
                <div class="pl-projects-list">
                    <h3><?php esc_html_e( 'Projets', 'pedagolens-teacher-dashboard' ); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Titre', 'pedagolens-teacher-dashboard' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'pedagolens-teacher-dashboard' ); ?></th>
                                <th><?php esc_html_e( 'Créé le', 'pedagolens-teacher-dashboard' ); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $projects as $project ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $project['title'] ); ?></td>
                                    <td><code><?php echo esc_html( $project['type'] ); ?></code></td>
                                    <td><?php echo esc_html( wp_date( 'Y-m-d', strtotime( $project['created_at'] ) ) ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=pl-course-workbench&project_id=' . (int) $project['id'] ) ); ?>" class="button button-small">
                                            <?php esc_html_e( 'Ouvrir', 'pedagolens-teacher-dashboard' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Rendu résultat d'analyse — Design System Stitch
    // -------------------------------------------------------------------------

    /**
     * Affiche le résultat d'analyse avec le design Stitch.
     *
     * Inspiré du template analyse_de_contenu_p_dagolens.
     * Manrope/Inter fonts, glass cards, mesh gradients, CSS-only charts,
     * score indicators, recommandations prioritaires, impact estimé.
     *
     * @param array $analysis {
     *     @type array  $profile_scores   slug => score (0-100)
     *     @type array  $recommendations  [ [section, text, priority, profile_target], … ]
     *     @type array  $impact_estimates suggestion_key => [ profile_slug => delta_int, … ]
     *     @type string $summary          Résumé narratif
     *     @type int    $analysis_id      Post ID de l'analyse
     * }
     */
    public static function render_analysis_result( array $analysis ): void {
        $scores   = $analysis['profile_scores']   ?? [];
        $recs     = $analysis['recommendations']  ?? [];
        $summary  = $analysis['summary']          ?? '';
        $impacts  = $analysis['impact_estimates'] ?? [];

        $profiles = [];
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $profiles = PedagoLens_Profile_Manager::get_all( active_only: true );
        }

        if ( empty( $scores ) && empty( $recs ) && empty( $summary ) ) {
            return;
        }

        // Compute average score
        $score_vals = array_map( 'intval', array_values( $scores ) );
        $avg_score  = ! empty( $score_vals )
            ? (int) round( array_sum( $score_vals ) / count( $score_vals ) )
            : 0;

        $score_label = $avg_score >= 80 ? 'Excellent'
            : ( $avg_score >= 60 ? 'Bon'
            : ( $avg_score >= 40 ? 'Modéré' : 'À améliorer' ) );

        $score_label_color = $avg_score >= 80 ? '#00236f'
            : ( $avg_score >= 60 ? '#004754'
            : ( $avg_score >= 40 ? '#712ae2' : '#ba1a1a' ) );

        // Analysis date
        $analyzed_at = '';
        if ( ! empty( $analysis['analysis_id'] ) ) {
            $a_post = get_post( (int) $analysis['analysis_id'] );
            if ( $a_post ) {
                $analyzed_at = wp_date( 'j F Y à H:i', strtotime( $a_post->post_date ) );
            }
        }

        // SVG gauge math
        $circumference   = 2 * M_PI * 88; // r=88
        $dash_offset     = $circumference - ( $circumference * $avg_score / 100 );

        // Profile label resolver
        $resolve_label = function ( string $slug ) use ( $profiles ): string {
            foreach ( $profiles as $p ) {
                if ( ( $p['slug'] ?? '' ) === $slug ) {
                    return $p['name'] ?? $p['label'] ?? $slug;
                }
            }
            return ucfirst( str_replace( [ '-', '_' ], ' ', $slug ) );
        };

        // Score color resolver
        $score_color = function ( int $s ): string {
            if ( $s >= 80 ) return '#00236f';
            if ( $s >= 60 ) return '#004754';
            if ( $s >= 40 ) return '#712ae2';
            return '#ba1a1a';
        };

        // Unique ID to scope styles
        $uid = 'pl-ar-' . wp_unique_id();
        ?>
        <div id="<?php echo esc_attr( $uid ); ?>" style="font-family:'Inter','Segoe UI',system-ui,sans-serif;color:#191c1e;padding:1.5rem 0 0;">

        <!-- Scoped Stitch styles (inline, no Tailwind) -->
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Inter:wght@400;500;600;700&display=swap');
            #<?php echo esc_attr( $uid ); ?> *,
            #<?php echo esc_attr( $uid ); ?> *::before,
            #<?php echo esc_attr( $uid ); ?> *::after { box-sizing: border-box; }
            #<?php echo esc_attr( $uid ); ?> .pla-headline { font-family: 'Manrope', sans-serif; }
            #<?php echo esc_attr( $uid ); ?> .pla-card {
                background: #fff;
                border-radius: 1.5rem;
                box-shadow: 0 10px 40px rgba(25,28,30,.06);
                border: 1px solid rgba(197,197,211,.1);
                transition: box-shadow .3s ease, transform .3s ease;
            }
            #<?php echo esc_attr( $uid ); ?> .pla-card:hover {
                box-shadow: 0 20px 50px rgba(25,28,30,.1);
                transform: translateY(-1px);
            }
            #<?php echo esc_attr( $uid ); ?> .pla-ia-glow {
                box-shadow: 0 0 24px rgba(113,42,226,.12);
            }
            #<?php echo esc_attr( $uid ); ?> .pla-bar-track {
                height: .5rem; width: 100%; background: #f2f4f6;
                border-radius: 9999px; overflow: hidden;
            }
            #<?php echo esc_attr( $uid ); ?> .pla-bar-fill {
                height: 100%; border-radius: 9999px;
                transition: width 1.4s cubic-bezier(.25,.8,.25,1);
            }
            #<?php echo esc_attr( $uid ); ?> .pla-gauge-circle {
                transition: stroke-dashoffset 1.6s cubic-bezier(.25,.8,.25,1);
            }
            #<?php echo esc_attr( $uid ); ?> .pla-rec-item:hover {
                background: #f2f4f6;
            }
            #<?php echo esc_attr( $uid ); ?> .pla-impact-row:hover {
                background: #f7f9fb;
            }
            #<?php echo esc_attr( $uid ); ?> .pla-tag {
                display: inline-flex; padding: .1875rem .625rem;
                border-radius: 9999px; font-size: .625rem;
                font-weight: 700; text-transform: uppercase;
                letter-spacing: .05em;
            }
            @media (max-width: 782px) {
                #<?php echo esc_attr( $uid ); ?> .pla-grid-top { grid-template-columns: 1fr !important; }
                #<?php echo esc_attr( $uid ); ?> .pla-grid-profiles { grid-template-columns: 1fr !important; }
                #<?php echo esc_attr( $uid ); ?> .pla-grid-2col { grid-template-columns: 1fr !important; }
                #<?php echo esc_attr( $uid ); ?> .pla-grid-pillars { grid-template-columns: 1fr 1fr !important; }
            }
        </style>

        <!-- Section header -->
        <div style="margin-bottom:1.75rem;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1rem;">
            <div>
                <p style="color:#712ae2;font-weight:700;font-size:.6875rem;letter-spacing:.12em;text-transform:uppercase;margin:0 0 .375rem;">Analyses IA</p>
                <h3 class="pla-headline" style="font-size:1.75rem;font-weight:800;color:#00236f;margin:0;letter-spacing:-.02em;">Résultat de l'analyse</h3>
                <?php if ( $analyzed_at ) : ?>
                <p style="color:#757682;margin:.375rem 0 0;font-size:.8125rem;">Analysé le <?php echo esc_html( $analyzed_at ); ?></p>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.5rem;">
                <span class="pla-tag" style="background:rgba(172,237,255,.25);color:#004e5c;">Diagnostic IA</span>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- ROW 1 : Score global gauge + Scores par profil                -->
        <!-- ============================================================= -->
        <div class="pla-grid-top" style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;margin-bottom:1.5rem;">

            <!-- Gauge card -->
            <div class="pla-card pla-ia-glow" style="padding:2rem;position:relative;overflow:hidden;display:flex;flex-direction:column;align-items:center;border:1px solid rgba(113,42,226,.06);">
                <div style="position:absolute;top:1rem;right:1rem;opacity:.15;font-size:2.5rem;color:#712ae2;">✨</div>
                <h4 style="color:#757682;font-weight:500;font-size:.8125rem;margin:0 0 1.5rem;display:flex;align-items:center;gap:.375rem;align-self:flex-start;">
                    <span style="color:#712ae2;">⚡</span> Indice Global de Clarté
                </h4>
                <div style="position:relative;width:11rem;height:11rem;">
                    <svg viewBox="0 0 192 192" style="width:100%;height:100%;transform:rotate(-90deg);">
                        <circle cx="96" cy="96" r="88" fill="transparent" stroke="#f2f4f6" stroke-width="8"></circle>
                        <circle class="pla-gauge-circle" cx="96" cy="96" r="88" fill="transparent"
                                stroke="<?php echo esc_attr( $score_label_color ); ?>"
                                stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="<?php echo round( $circumference, 2 ); ?>"
                                stroke-dashoffset="<?php echo round( $dash_offset, 2 ); ?>"></circle>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                        <span class="pla-headline" style="font-size:2.75rem;font-weight:900;color:#00236f;letter-spacing:-.03em;line-height:1;"><?php echo (int) $avg_score; ?><span style="font-size:1rem;">%</span></span>
                        <span style="font-size:.5625rem;font-weight:700;color:<?php echo esc_attr( $score_label_color ); ?>;text-transform:uppercase;letter-spacing:.12em;margin-top:.25rem;"><?php echo esc_html( $score_label ); ?></span>
                    </div>
                </div>
                <p style="text-align:center;font-size:.75rem;color:#757682;margin:1.25rem 0 0;line-height:1.5;">
                    Score moyen calculé sur <?php echo count( $scores ); ?> profil(s) étudiant(s).
                </p>
            </div>

            <!-- Profile scores card -->
            <div class="pla-card" style="padding:2rem;">
                <h4 class="pla-headline" style="font-size:1.125rem;font-weight:700;color:#00236f;margin:0 0 1.75rem;">Compréhension par profil étudiant</h4>
                <div class="pla-grid-profiles" style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem 2.5rem;">
                    <?php foreach ( $scores as $slug => $score ) :
                        $score     = max( 0, min( 100, (int) $score ) );
                        $bar_color = $score_color( $score );
                        $label     = $resolve_label( $slug );
                        $hint      = $score < 50 ? 'Risque identifié' : ( $score < 70 ? 'Point d\'attention' : '' );
                    ?>
                    <div style="margin-bottom:.25rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.375rem;">
                            <span style="font-size:.8125rem;font-weight:600;color:#191c1e;"><?php echo esc_html( $label ); ?></span>
                            <span style="font-size:.8125rem;font-weight:700;color:#00236f;"><?php echo $score; ?>%</span>
                        </div>
                        <div class="pla-bar-track">
                            <div class="pla-bar-fill" style="width:<?php echo $score; ?>%;background:<?php echo esc_attr( $bar_color ); ?>;"></div>
                        </div>
                        <?php if ( $hint ) : ?>
                        <p style="font-size:.625rem;color:#757682;font-style:italic;margin:.25rem 0 0;"><?php echo esc_html( $hint ); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- ROW 2 : Four pillars indicators                               -->
        <!-- ============================================================= -->
        <?php
        $pillars = [
            [ 'icon' => '🧠', 'label' => 'Charge Cognitive',  'value' => $avg_score >= 70 ? 'Modérée' : ( $avg_score >= 50 ? 'Élevée' : 'Critique' ), 'border' => '#00236f' ],
            [ 'icon' => '❓', 'label' => 'Ambiguïté',         'value' => $avg_score >= 75 ? 'Faible' : ( $avg_score >= 55 ? 'Modérée' : 'Élevée' ),    'border' => '#712ae2' ],
            [ 'icon' => '🌐', 'label' => 'Accessibilité',     'value' => $avg_score >= 80 ? 'Optimale' : ( $avg_score >= 60 ? 'Correcte' : 'Limitée' ),'border' => '#004754' ],
            [ 'icon' => '✅', 'label' => 'Évaluation',        'value' => $avg_score >= 70 ? 'Très Claire' : ( $avg_score >= 50 ? 'Claire' : 'Confuse' ),'border' => '#757682' ],
        ];
        ?>
        <div class="pla-grid-pillars" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
            <?php foreach ( $pillars as $p ) : ?>
            <div style="background:#f2f4f6;border-radius:.75rem;padding:1.25rem;border-left:4px solid <?php echo esc_attr( $p['border'] ); ?>;">
                <span style="font-size:1.25rem;display:block;margin-bottom:.375rem;"><?php echo $p['icon']; ?></span>
                <p style="font-size:.625rem;font-weight:700;color:#757682;text-transform:uppercase;margin:0 0 .25rem;letter-spacing:.05em;"><?php echo esc_html( $p['label'] ); ?></p>
                <p class="pla-headline" style="font-size:1.25rem;font-weight:900;color:#00236f;margin:0;"><?php echo esc_html( $p['value'] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================= -->
        <!-- ROW 3 : Risques + Recommandations (2 colonnes Stitch)         -->
        <!-- ============================================================= -->
        <?php
        $risks       = array_filter( $recs, fn( $r ) => ( (int) ( $r['priority'] ?? 99 ) ) <= 2 );
        $suggestions = array_filter( $recs, fn( $r ) => ( (int) ( $r['priority'] ?? 99 ) ) > 2 );
        $wb_page     = get_page_by_path( 'workbench' );
        $wb_url      = $wb_page ? get_permalink( $wb_page ) : admin_url( 'admin.php?page=pl-course-workbench' );
        ?>
        <?php if ( ! empty( $recs ) ) : ?>
        <div class="pla-grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

            <!-- Risques identifiés -->
            <div class="pla-card" style="padding:2rem;border:1px solid rgba(186,26,26,.08);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
                    <span style="display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;background:rgba(255,218,214,.5);color:#93000a;border-radius:.5rem;font-size:1.1rem;">⚠️</span>
                    <h4 class="pla-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Risques Identifiés</h4>
                </div>
                <?php if ( ! empty( $risks ) ) : ?>
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    <?php foreach ( $risks as $risk ) : ?>
                    <div style="padding:1rem;background:rgba(186,26,26,.04);border-left:2px solid #ba1a1a;border-radius:0 .5rem .5rem 0;">
                        <h5 style="font-size:.875rem;font-weight:700;color:#93000a;margin:0 0 .25rem;"><?php echo esc_html( $risk['section'] ?? 'Risque' ); ?></h5>
                        <p style="font-size:.8125rem;color:#444651;margin:0;line-height:1.6;"><?php echo esc_html( $risk['text'] ?? '' ); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p style="font-size:.875rem;color:#757682;margin:0;">Aucun risque critique détecté. 🎉</p>
                <?php endif; ?>
            </div>

            <!-- Recommandations IA -->
            <div class="pla-card" style="padding:2rem;border:1px solid rgba(113,42,226,.08);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
                    <span style="display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;background:rgba(234,221,255,.5);color:#712ae2;border-radius:.5rem;font-size:1.1rem;">💡</span>
                    <h4 class="pla-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Recommandations IA</h4>
                </div>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <?php $idx = 1; foreach ( $recs as $rec ) :
                        $priority = (int) ( $rec['priority'] ?? 99 );
                    ?>
                    <div class="pla-rec-item" style="display:flex;gap:1rem;align-items:flex-start;padding:1rem;border-radius:.75rem;transition:background .2s ease;">
                        <span style="flex-shrink:0;font-size:1.25rem;font-weight:900;color:#712ae2;font-family:'Manrope',sans-serif;"><?php echo str_pad( $idx, 2, '0', STR_PAD_LEFT ); ?></span>
                        <div style="flex:1;min-width:0;">
                            <h5 style="font-size:.875rem;font-weight:700;color:#00236f;margin:0 0 .25rem;"><?php echo esc_html( $rec['section'] ?? '' ); ?></h5>
                            <p style="font-size:.8125rem;color:#444651;margin:0;line-height:1.6;"><?php echo esc_html( $rec['text'] ?? '' ); ?></p>
                            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.375rem;">
                                <?php if ( ! empty( $rec['profile_target'] ) ) : ?>
                                <span class="pla-tag" style="background:rgba(234,221,255,.3);color:#712ae2;"><?php echo esc_html( $resolve_label( $rec['profile_target'] ) ); ?></span>
                                <?php endif; ?>
                                <?php if ( $priority <= 2 ) : ?>
                                <span class="pla-tag" style="background:rgba(186,26,26,.08);color:#ba1a1a;">Prioritaire</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?php echo esc_url( add_query_arg( 'rec_section', urlencode( $rec['section'] ?? '' ), $wb_url ) ); ?>"
                           style="flex-shrink:0;display:inline-flex;align-items:center;gap:.25rem;padding:.375rem .75rem;border:1px solid rgba(117,118,130,.2);background:#fff;color:#00236f;font-weight:600;font-size:.6875rem;border-radius:.5rem;text-decoration:none;transition:background .2s;"
                           onmouseover="this.style.background='#f2f4f6'" onmouseout="this.style.background='#fff'">
                            📝 Atelier
                        </a>
                    </div>
                    <?php $idx++; endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- ROW 4 : Impact estimé après correction                        -->
        <!-- ============================================================= -->
        <?php if ( ! empty( $impacts ) && is_array( $impacts ) ) : ?>
        <div class="pla-card" style="padding:2rem;margin-bottom:1.5rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
                <h4 class="pla-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Simulation d'impact après correction</h4>
                <span class="pla-tag" style="background:rgba(172,237,255,.25);color:#004e5c;">Prévisionnel</span>
            </div>
            <table style="width:100%;text-align:left;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid rgba(197,197,211,.15);">
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Suggestion</th>
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Profil impacté</th>
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Gain estimé</th>
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Tendance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $impacts as $sug_key => $deltas ) :
                        if ( ! is_array( $deltas ) ) { continue; }
                        $sug_label = ucfirst( str_replace( '_', ' ', $sug_key ) );
                        foreach ( $deltas as $profile_slug => $delta ) :
                            $delta     = (int) $delta;
                            $abs_delta = abs( $delta );
                            $trend     = $delta > 0 ? '📈' : ( $delta < 0 ? '📉' : '➡️' );
                            $trend_color = $delta > 0 ? '#004754' : ( $delta < 0 ? '#ba1a1a' : '#757682' );
                    ?>
                    <tr class="pla-impact-row" style="border-bottom:1px solid rgba(197,197,211,.06);transition:background .2s ease;">
                        <td style="padding:1rem 0;font-size:.8125rem;font-weight:600;color:#00236f;"><?php echo esc_html( $sug_label ); ?></td>
                        <td style="padding:1rem 0;font-size:.8125rem;color:#444651;"><?php echo esc_html( $resolve_label( $profile_slug ) ); ?></td>
                        <td style="padding:1rem 0;font-size:.8125rem;font-weight:700;color:<?php echo esc_attr( $trend_color ); ?>;"><?php echo ( $delta > 0 ? '+' : '' ) . $delta; ?> pts</td>
                        <td style="padding:1rem 0;">
                            <span style="display:flex;align-items:center;gap:.25rem;color:<?php echo esc_attr( $trend_color ); ?>;font-size:.75rem;font-weight:700;">
                                <?php echo $trend; ?> <?php echo $abs_delta >= 7 ? 'Fort' : ( $abs_delta >= 4 ? 'Modéré' : 'Léger' ); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- ROW 5 : Résumé narratif                                       -->
        <!-- ============================================================= -->
        <?php if ( $summary ) : ?>
        <div class="pla-card" style="padding:2rem;margin-bottom:1.5rem;">
            <h4 class="pla-headline" style="font-size:1.125rem;font-weight:700;color:#00236f;margin:0 0 1rem;">Résumé de l'analyse</h4>
            <div style="border-left:3px solid #712ae2;padding-left:1.25rem;">
                <p style="font-size:.9375rem;color:#444651;line-height:1.7;margin:0;font-style:italic;"><?php echo esc_html( $summary ); ?></p>
            </div>
        </div>
        <?php endif; ?>

        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // AJAX — Analyser un cours
    // -------------------------------------------------------------------------

    public static function ajax_analyze(): void {
        check_ajax_referer( self::NONCE_AJAX, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'pedagolens_teacher' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( ! $course_id ) {
            wp_send_json_error( [ 'message' => 'course_id manquant.' ] );
        }

        $result = PedagoLens_Teacher_Dashboard::analyze_course( $course_id );

        if ( empty( $result['success'] ) ) {
            wp_send_json_error( [
                'message'    => $result['error_message'] ?? 'Analyse échouée.',
                'error_code' => $result['error_code']    ?? 'pl_analysis_failed',
            ] );
        }

        // Générer le HTML du résultat pour injection directe
        ob_start();
        self::render_analysis_result( $result );
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Créer un projet
    // -------------------------------------------------------------------------

    public static function ajax_create_project(): void {
        check_ajax_referer( self::NONCE_AJAX, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'pedagolens_teacher' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        $type      = sanitize_text_field( $_POST['type']  ?? 'magistral' );
        $title     = sanitize_text_field( $_POST['title'] ?? '' );

        if ( ! $course_id || ! $title ) {
            wp_send_json_error( [ 'message' => 'Paramètres manquants.' ] );
        }

        $project_id = PedagoLens_Teacher_Dashboard::create_project( $course_id, $type, $title );

        if ( ! $project_id ) {
            wp_send_json_error( [ 'message' => 'Création du projet échouée.' ] );
        }

        wp_send_json_success( [
            'project_id'  => $project_id,
            'workbench_url' => admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $project_id ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Créer un cours
    // -------------------------------------------------------------------------

    public static function ajax_create_course(): void {
        check_ajax_referer( self::NONCE_AJAX, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'pedagolens_teacher' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }

        $title       = sanitize_text_field( $_POST['title'] ?? '' );
        $course_type = sanitize_text_field( $_POST['course_type'] ?? 'magistral' );

        if ( ! $title ) {
            wp_send_json_error( [ 'message' => 'Le titre est requis.' ] );
        }

        $allowed_types = [ 'magistral', 'exercice', 'evaluation', 'travail_equipe' ];
        if ( ! in_array( $course_type, $allowed_types, true ) ) {
            $course_type = 'magistral';
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'pl_course',
            'post_title'  => $title,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Création du cours échouée.' ] );
        }

        update_post_meta( $post_id, '_pl_course_type', $course_type );

        wp_send_json_success( [
            'course_id' => $post_id,
            'title'     => $title,
            'type'      => $course_type,
        ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function get_courses(): array {
        return get_posts( [
            'post_type'      => 'pl_course',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
    }

    private static function get_latest_analysis( int $course_id ): ?array {
        $posts = get_posts( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [ [
                'key'   => '_pl_course_id',
                'value' => $course_id,
                'type'  => 'NUMERIC',
            ] ],
        ] );

        if ( empty( $posts ) ) {
            return null;
        }

        $id = $posts[0]->ID;
        return [
            'analysis_id'      => $id,
            'profile_scores'   => PedagoLens_Teacher_Dashboard::get_profile_scores( $id ),
            'recommendations'  => PedagoLens_Teacher_Dashboard::get_recommendations( $id ),
            'summary'          => get_post_meta( $id, '_pl_summary', true ),
            'impact_estimates' => json_decode( get_post_meta( $id, '_pl_impact_estimates', true ) ?: '[]', true ),
        ];
    }

    private static function score_color( int $score ): string {
        if ( $score >= 80 ) return '#00a32a';
        if ( $score >= 60 ) return '#2271b1';
        if ( $score >= 40 ) return '#dba617';
        return '#d63638';
    }

    private static function score_color_class( int $score ): string {
        if ( $score >= 80 ) return 'pl-score-green';
        if ( $score >= 60 ) return 'pl-score-blue';
        if ( $score >= 40 ) return 'pl-score-yellow';
        return 'pl-score-red';
    }

    // -------------------------------------------------------------------------
    // Vue détaillée d'un cours — Design Stitch (inline CSS)
    // -------------------------------------------------------------------------

    /**
     * Affiche la vue détaillée d'un cours avec scores par profil,
     * recommandations et sections du contenu.
     * Design Stitch : Manrope/Inter, glass cards, mesh gradients, score bars.
     * Tout le CSS est inline — aucun CDN Tailwind.
     *
     * @param int $course_id ID du cours (pl_course).
     */
    public static function render_course_detail( int $course_id ): void {
        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== 'pl_course' ) {
            echo '<div style="padding:2rem;color:#ba1a1a;font-family:Inter,sans-serif;">'
               . esc_html__( 'Cours introuvable.', 'pedagolens-teacher-dashboard' )
               . '</div>';
            return;
        }

        /* ── Données ─────────────────────────────────────────── */
        $course_type = get_post_meta( $course_id, '_pl_course_type', true ) ?: 'magistral';
        $sections    = get_post_meta( $course_id, '_pl_sections', true );
        $sections    = is_string( $sections ) ? (array) json_decode( $sections, true ) : ( is_array( $sections ) ? $sections : [] );

        $analysis = self::get_latest_analysis( $course_id );
        $scores   = $analysis['profile_scores']   ?? [];
        $recs     = $analysis['recommendations']  ?? [];
        $summary  = $analysis['summary']          ?? '';

        $profiles  = PedagoLens_Teacher_Dashboard::get_active_profiles();
        $avg_score = 0;
        if ( ! empty( $scores ) ) {
            $avg_score = (int) round( array_sum( array_map( 'intval', $scores ) ) / count( $scores ) );
        }

        $teacher_name = wp_get_current_user()->display_name;

        /* ── Couleurs Stitch ─────────────────────────────────── */
        $c = [
            'primary'       => '#00236f',
            'primary_ctr'   => '#1e3a8a',
            'secondary'     => '#712ae2',
            'tertiary'      => '#004754',
            'error'         => '#ba1a1a',
            'surface'       => '#f7f9fb',
            'surface_low'   => '#f2f4f6',
            'surface_high'  => '#e6e8ea',
            'card'          => '#ffffff',
            'text'          => '#191c1e',
            'text_sub'      => '#444651',
            'text_muted'    => '#757682',
            'outline'       => 'rgba(197,197,211,.1)',
            'shadow'        => '0 10px 40px rgba(25,28,30,.06)',
            'grad'          => 'linear-gradient(135deg,#00236f 0%,#1e3a8a 100%)',
            'mesh'          => 'radial-gradient(ellipse at 20% 0%,rgba(113,42,226,.06) 0%,transparent 60%),'
                             . 'radial-gradient(ellipse at 80% 100%,rgba(0,71,84,.05) 0%,transparent 60%)',
        ];

        /* ── Helper inline pour couleur de score ─────────────── */
        $sc = function ( int $s ) use ( $c ): string {
            if ( $s >= 80 ) return $c['primary'];
            if ( $s >= 60 ) return $c['tertiary'];
            if ( $s >= 40 ) return $c['secondary'];
            return $c['error'];
        };

        $label_for = function ( string $slug ) use ( $profiles ): string {
            foreach ( $profiles as $p ) {
                if ( ( $p['slug'] ?? '' ) === $slug ) {
                    return $p['name'] ?? $p['label'] ?? $slug;
                }
            }
            return ucfirst( str_replace( [ '-', '_' ], ' ', $slug ) );
        };

        /* ── Rendu ───────────────────────────────────────────── */
        ?>
        <div style="font-family:'Inter',system-ui,sans-serif;background:<?php echo $c['surface']; ?>;color:<?php echo $c['text']; ?>;min-height:100vh;background-image:<?php echo $c['mesh']; ?>;">

        <?php /* ── Header ──────────────────────────────────── */ ?>
        <header style="background:<?php echo $c['surface_low']; ?>;padding:2.5rem 3rem 2rem;border-bottom:1px solid <?php echo $c['outline']; ?>;">
            <nav style="display:flex;align-items:center;gap:.4rem;font-size:.8125rem;color:<?php echo $c['text_sub']; ?>;margin-bottom:1.25rem;">
                <span>Cours</span>
                <span style="font-size:.7rem;">›</span>
                <span>Analyses IA</span>
                <span style="font-size:.7rem;">›</span>
                <span style="color:<?php echo $c['primary']; ?>;font-weight:600;">Détails</span>
            </nav>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1.5rem;">
                <div>
                    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;">
                        <span style="display:inline-flex;padding:.25rem .75rem;border-radius:9999px;background:rgba(172,237,255,.2);color:#004e5c;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">
                            <?php echo esc_html( $course_type ); ?>
                        </span>
                        <?php if ( $analysis ) : ?>
                        <span style="display:flex;align-items:center;gap:.25rem;font-size:.625rem;font-weight:700;color:<?php echo $c['secondary']; ?>;text-transform:uppercase;letter-spacing:.05em;">
                            ✓ Analysé
                        </span>
                        <?php endif; ?>
                    </div>
                    <h2 style="font-family:'Manrope',sans-serif;font-size:2.5rem;font-weight:800;color:<?php echo $c['primary']; ?>;margin:0;letter-spacing:-.02em;line-height:1.15;">
                        <?php echo esc_html( $course->post_title ); ?>
                    </h2>
                    <p style="color:<?php echo $c['text_sub']; ?>;margin:.5rem 0 0;font-size:.875rem;font-weight:500;">
                        <?php esc_html_e( 'Enseignant :', 'pedagolens-teacher-dashboard' ); ?>
                        <span style="color:<?php echo $c['text']; ?>;font-weight:600;"><?php echo esc_html( $teacher_name ); ?></span>
                    </p>
                </div>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( get_edit_post_link( $course_id ) ); ?>"
                       style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border:1px solid <?php echo $c['primary']; ?>;color:<?php echo $c['primary']; ?>;font-weight:700;font-size:.875rem;border-radius:.75rem;text-decoration:none;background:transparent;cursor:pointer;transition:background .2s;"
                       onmouseover="this.style.background='rgba(0,35,111,.05)'" onmouseout="this.style.background='transparent'">
                        ✏️ <?php esc_html_e( 'Éditer le contenu', 'pedagolens-teacher-dashboard' ); ?>
                    </a>
                    <button type="button"
                            class="pl-btn-analyze"
                            data-course-id="<?php echo (int) $course_id; ?>"
                            style="display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:<?php echo $c['grad']; ?>;color:#fff;font-weight:700;font-size:.875rem;border-radius:.75rem;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(0,35,111,.25);transition:opacity .2s;"
                            onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
                        🔄 <?php esc_html_e( 'Relancer l\'analyse', 'pedagolens-teacher-dashboard' ); ?>
                    </button>
                </div>
            </div>
        </header>

        <div style="padding:2.5rem 3rem;">

        <?php /* ── KPI Bento Grid ──────────────────────────── */ ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;margin-bottom:3rem;">
            <?php
            $kpis = [
                [
                    'label' => __( 'Score Moyen', 'pedagolens-teacher-dashboard' ),
                    'value' => $avg_score,
                    'suffix'=> '/100',
                    'color' => $c['primary'],
                    'glow'  => true,
                    'extra' => '',
                ],
                [
                    'label' => __( 'Profils analysés', 'pedagolens-teacher-dashboard' ),
                    'value' => count( $scores ),
                    'suffix'=> __( 'profils', 'pedagolens-teacher-dashboard' ),
                    'color' => $c['error'],
                    'glow'  => false,
                    'extra' => '',
                ],
                [
                    'label' => __( 'Recommandations', 'pedagolens-teacher-dashboard' ),
                    'value' => count( $recs ),
                    'suffix'=> __( 'actions', 'pedagolens-teacher-dashboard' ),
                    'color' => $c['secondary'],
                    'glow'  => true,
                    'extra' => '',
                ],
                [
                    'label' => __( 'Sections du cours', 'pedagolens-teacher-dashboard' ),
                    'value' => count( $sections ),
                    'suffix'=> __( 'chapitres', 'pedagolens-teacher-dashboard' ),
                    'color' => $c['tertiary'],
                    'glow'  => false,
                    'extra' => '',
                ],
            ];
            foreach ( $kpis as $kpi ) :
                $glow_css = $kpi['glow']
                    ? 'position:relative;overflow:hidden;'
                    : '';
                $glow_after = $kpi['glow']
                    ? '<div style="position:absolute;top:0;right:0;width:40px;height:40px;background:radial-gradient(circle,rgba(113,42,226,.12) 0%,transparent 70%);border-radius:0 1.5rem 0 0;pointer-events:none;"></div>'
                    : '';
            ?>
            <div style="background:<?php echo $c['card']; ?>;padding:1.5rem;border-radius:1.5rem;box-shadow:<?php echo $c['shadow']; ?>;border:1px solid <?php echo $c['outline']; ?>;<?php echo $glow_css; ?>">
                <?php echo $glow_after; ?>
                <p style="font-size:.625rem;font-weight:700;color:<?php echo $c['text_sub']; ?>;text-transform:uppercase;letter-spacing:.1em;margin:0 0 1rem;">
                    <?php echo esc_html( $kpi['label'] ); ?>
                </p>
                <div style="display:flex;align-items:baseline;gap:.5rem;">
                    <span style="font-family:'Manrope',sans-serif;font-size:3rem;font-weight:900;color:<?php echo esc_attr( $kpi['color'] ); ?>;letter-spacing:-.03em;line-height:1.1;">
                        <?php echo str_pad( (string) $kpi['value'], 2, '0', STR_PAD_LEFT ); ?>
                    </span>
                    <span style="color:<?php echo $c['text_sub']; ?>;font-weight:700;font-size:.875rem;">
                        <?php echo esc_html( $kpi['suffix'] ); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php /* ── Scores par profil ───────────────────────── */ ?>
        <?php if ( ! empty( $scores ) ) : ?>
        <div style="display:grid;grid-template-columns:8fr 4fr;gap:1.5rem;margin-bottom:3rem;">
            <div style="background:<?php echo $c['card']; ?>;padding:2rem;border-radius:1.5rem;box-shadow:<?php echo $c['shadow']; ?>;border:1px solid <?php echo $c['outline']; ?>;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
                    <h3 style="font-family:'Manrope',sans-serif;font-size:1.25rem;font-weight:700;color:<?php echo $c['primary']; ?>;margin:0;">
                        <?php esc_html_e( 'Scores par profil pédagogique', 'pedagolens-teacher-dashboard' ); ?>
                    </h3>
                    <div style="display:flex;gap:1rem;">
                        <span style="display:flex;align-items:center;gap:.35rem;font-size:.625rem;font-weight:700;text-transform:uppercase;color:<?php echo $c['text_muted']; ?>;">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:rgba(0,35,111,.2);"></span> Faible
                        </span>
                        <span style="display:flex;align-items:center;gap:.35rem;font-size:.625rem;font-weight:700;text-transform:uppercase;color:<?php echo $c['text_muted']; ?>;">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?php echo $c['primary']; ?>;"></span> Élevé
                        </span>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:1.5rem;">
                    <?php foreach ( $scores as $slug => $score ) :
                        $score     = max( 0, min( 100, (int) $score ) );
                        $bar_color = $sc( $score );
                        $label     = $label_for( $slug );
                    ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                            <span style="font-size:.875rem;font-weight:600;color:<?php echo $c['text']; ?>;"><?php echo esc_html( $label ); ?></span>
                            <span style="font-size:.875rem;font-weight:700;color:<?php echo $c['primary']; ?>;"><?php echo $score; ?>%</span>
                        </div>
                        <div style="height:.5rem;width:100%;background:<?php echo $c['surface_low']; ?>;border-radius:9999px;overflow:hidden;">
                            <div style="height:100%;width:<?php echo $score; ?>%;background:<?php echo esc_attr( $bar_color ); ?>;border-radius:9999px;transition:width 1.2s cubic-bezier(.25,.8,.25,1);"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ( $summary ) : ?>
                <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid <?php echo $c['outline']; ?>;">
                    <p style="font-size:.875rem;font-weight:500;color:<?php echo $c['text_sub']; ?>;font-style:italic;line-height:1.6;margin:0;">
                        "<?php echo esc_html( $summary ); ?>"
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <?php /* ── Radar placeholder ───────────────────── */ ?>
            <div style="background:<?php echo $c['card']; ?>;padding:2rem;border-radius:1.5rem;box-shadow:<?php echo $c['shadow']; ?>;border:1px solid <?php echo $c['outline']; ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                <h3 style="font-family:'Manrope',sans-serif;font-size:1rem;font-weight:700;color:<?php echo $c['primary']; ?>;margin:0 0 1.5rem;align-self:flex-start;">
                    <?php esc_html_e( 'Radar des profils', 'pedagolens-teacher-dashboard' ); ?>
                </h3>
                <div style="width:180px;height:180px;border-radius:50%;border:2px dashed rgba(117,118,130,.2);display:flex;align-items:center;justify-content:center;">
                    <div style="text-align:center;">
                        <span style="font-family:'Manrope',sans-serif;font-size:2.5rem;font-weight:900;color:<?php echo $c['primary']; ?>;"><?php echo $avg_score; ?></span>
                        <p style="font-size:.75rem;color:<?php echo $c['secondary']; ?>;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin:.25rem 0 0;">
                            <?php
                            if ( $avg_score >= 80 )      { esc_html_e( 'Excellent', 'pedagolens-teacher-dashboard' ); }
                            elseif ( $avg_score >= 60 )   { esc_html_e( 'Bon', 'pedagolens-teacher-dashboard' ); }
                            elseif ( $avg_score >= 40 )   { esc_html_e( 'Moyen', 'pedagolens-teacher-dashboard' ); }
                            else                          { esc_html_e( 'À améliorer', 'pedagolens-teacher-dashboard' ); }
                            ?>
                        </p>
                    </div>
                </div>
                <p style="font-size:.75rem;color:<?php echo $c['text_muted']; ?>;margin:1rem 0 0;text-align:center;">
                    <?php esc_html_e( 'Graphique radar — intégration Chart.js à venir', 'pedagolens-teacher-dashboard' ); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ── Recommandations ─────────────────────────── */ ?>
        <?php if ( ! empty( $recs ) ) : ?>
        <div style="background:<?php echo $c['card']; ?>;padding:2rem;border-radius:1.5rem;box-shadow:<?php echo $c['shadow']; ?>;border:1px solid <?php echo $c['outline']; ?>;margin-bottom:3rem;">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
                <span style="display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;background:rgba(234,221,255,.5);color:<?php echo $c['secondary']; ?>;border-radius:.5rem;font-size:1.1rem;">💡</span>
                <h3 style="font-family:'Manrope',sans-serif;font-size:1.25rem;font-weight:700;color:<?php echo $c['primary']; ?>;margin:0;">
                    <?php esc_html_e( 'Recommandations', 'pedagolens-teacher-dashboard' ); ?>
                </h3>
            </div>
            <div style="display:flex;flex-direction:column;gap:.75rem;">
                <?php $idx = 1; foreach ( $recs as $rec ) :
                    $priority    = (int) ( $rec['priority'] ?? 99 );
                    $priority_bg = $priority <= 2 ? $c['error'] : ( $priority <= 4 ? '#ff9100' : '#2979ff' );
                ?>
                <div style="display:flex;gap:1rem;align-items:flex-start;padding:1rem;border-radius:.75rem;transition:background .2s;"
                     onmouseover="this.style.background='<?php echo $c['surface_low']; ?>'" onmouseout="this.style.background='transparent'">
                    <span style="flex-shrink:0;font-family:'Manrope',sans-serif;font-size:1.25rem;font-weight:900;color:<?php echo $c['secondary']; ?>;">
                        <?php echo str_pad( (string) $idx, 2, '0', STR_PAD_LEFT ); ?>
                    </span>
                    <div style="flex:1;min-width:0;">
                        <?php if ( ! empty( $rec['section'] ) ) : ?>
                        <h4 style="font-size:.875rem;font-weight:700;color:<?php echo $c['primary']; ?>;margin:0 0 .25rem;">
                            <?php echo esc_html( $rec['section'] ); ?>
                        </h4>
                        <?php endif; ?>
                        <p style="font-size:.8125rem;color:<?php echo $c['text_sub']; ?>;margin:0;line-height:1.6;">
                            <?php echo esc_html( $rec['text'] ?? '' ); ?>
                        </p>
                        <?php if ( ! empty( $rec['profile_target'] ) ) : ?>
                        <span style="display:inline-flex;padding:.2rem .6rem;border-radius:9999px;background:rgba(234,221,255,.3);color:<?php echo $c['secondary']; ?>;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:.5rem;">
                            <?php echo esc_html( $rec['profile_target'] ); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <span style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:50%;background:<?php echo esc_attr( $priority_bg ); ?>;color:#fff;font-size:.625rem;font-weight:800;">
                        <?php echo $priority; ?>
                    </span>
                </div>
                <?php $idx++; endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ── Sections du contenu ─────────────────────── */ ?>
        <?php if ( ! empty( $sections ) ) : ?>
        <div style="background:<?php echo $c['card']; ?>;padding:2rem;border-radius:1.5rem;box-shadow:<?php echo $c['shadow']; ?>;border:1px solid <?php echo $c['outline']; ?>;margin-bottom:3rem;">
            <h3 style="font-family:'Manrope',sans-serif;font-size:1.25rem;font-weight:700;color:<?php echo $c['primary']; ?>;margin:0 0 1.5rem;">
                <?php esc_html_e( 'Sections du cours', 'pedagolens-teacher-dashboard' ); ?>
            </h3>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                <?php foreach ( $sections as $i => $section ) :
                    $sec_title   = is_array( $section ) ? ( $section['title'] ?? $section['name'] ?? '' ) : (string) $section;
                    $sec_score   = is_array( $section ) ? (int) ( $section['score'] ?? 0 ) : 0;
                    $sec_status  = is_array( $section ) ? ( $section['status'] ?? '' ) : '';
                    $is_critical = $sec_status === 'critical' || $sec_score < 40;
                    $num         = str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT );
                ?>
                <div style="display:flex;align-items:center;gap:1rem;padding:1rem;border-radius:.75rem;border:1px solid <?php echo $is_critical ? 'rgba(186,26,26,.15)' : $c['outline']; ?>;background:<?php echo $is_critical ? 'rgba(186,26,26,.03)' : 'transparent'; ?>;transition:background .2s;"
                     onmouseover="this.style.background='<?php echo $c['surface_low']; ?>'" onmouseout="this.style.background='<?php echo $is_critical ? 'rgba(186,26,26,.03)' : 'transparent'; ?>'">
                    <span style="flex-shrink:0;font-family:'Manrope',sans-serif;font-size:1rem;font-weight:800;color:<?php echo $c['text_muted']; ?>;"><?php echo $num; ?></span>
                    <span style="flex:1;font-size:.875rem;font-weight:600;color:<?php echo $c['text']; ?>;"><?php echo esc_html( $sec_title ); ?></span>
                    <?php if ( $sec_score > 0 ) : ?>
                    <div style="width:120px;">
                        <div style="height:.375rem;width:100%;background:<?php echo $c['surface_high']; ?>;border-radius:9999px;overflow:hidden;">
                            <div style="height:100%;width:<?php echo $sec_score; ?>%;background:<?php echo esc_attr( $sc( $sec_score ) ); ?>;border-radius:9999px;"></div>
                        </div>
                    </div>
                    <span style="flex-shrink:0;font-size:.75rem;font-weight:700;color:<?php echo esc_attr( $sc( $sec_score ) ); ?>;min-width:2.5rem;text-align:right;"><?php echo $sec_score; ?>%</span>
                    <?php endif; ?>
                    <?php if ( $is_critical ) : ?>
                    <span title="<?php esc_attr_e( 'Section critique', 'pedagolens-teacher-dashboard' ); ?>" style="flex-shrink:0;color:<?php echo $c['error']; ?>;font-size:1.1rem;">⚠️</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ── Zone AJAX pour re-analyse ───────────────── */ ?>
        <div id="pl-analysis-<?php echo (int) $course_id; ?>" class="pl-analysis-result"></div>

        </div><?php /* fin padding */ ?>

        <?php /* ── Footer Stitch ───────────────────────────── */ ?>
        <footer style="background:<?php echo $c['primary']; ?>;padding:3rem 2rem;border-radius:2rem 2rem 0 0;color:#fff;margin-top:3rem;">
            <div style="max-width:72rem;margin:0 auto;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:2rem;">
                <div>
                    <h2 style="font-family:'Manrope',sans-serif;font-size:1.25rem;font-weight:900;margin:0 0 .5rem;">PédagoLens AI</h2>
                    <p style="font-size:.75rem;opacity:.8;margin:0;">© <?php echo gmdate( 'Y' ); ?> PédagoLens AI. L'excellence éditoriale au service de l'éducation.</p>
                </div>
                <div style="display:flex;gap:2rem;font-size:.75rem;font-weight:500;">
                    <span style="color:rgba(255,255,255,.6);">Confidentialité</span>
                    <span style="color:rgba(255,255,255,.6);">Conditions</span>
                    <span style="color:rgba(255,255,255,.6);">Support</span>
                </div>
            </div>
        </footer>

        </div><?php /* fin wrap */ ?>
        <?php
    }
}
