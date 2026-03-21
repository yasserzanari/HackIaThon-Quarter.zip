<?php
/**
 * PedagoLens_Teacher_Dashboard
 *
 * Logique métier : analyse de cours, persistance des analyses,
 * lecture des scores/recommandations, gestion des projets.
 * Front-end : Design System Stitch (Manrope/Inter, Material tokens, glass cards).
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Teacher_Dashboard {

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init(): void {
        PedagoLens_Dashboard_Admin::register();
    }

    // -------------------------------------------------------------------------
    // Analyse
    // -------------------------------------------------------------------------

    public static function analyze_course( int $course_id ): array {
        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== 'pl_course' ) {
            return self::error( 'pl_course_not_found', "Cours introuvable : {$course_id}" );
        }

        $profiles = self::get_active_profiles();
        if ( empty( $profiles ) ) {
            return self::error( 'pl_no_profiles_configured', 'Aucun profil actif configuré.' );
        }

        do_action( 'pedagolens_before_analysis', $course_id );

        $sections = get_post_meta( $course_id, '_pl_sections', true );
        $params   = [
            'course_id'    => $course_id,
            'course_title' => $course->post_title,
            'content'      => $course->post_content,
            'sections'     => is_array( $sections ) ? wp_json_encode( $sections ) : '',
            'course_type'  => get_post_meta( $course_id, '_pl_course_type', true ) ?: 'magistral',
            'profiles'     => wp_json_encode( array_column( $profiles, 'slug' ) ),
        ];

        $result = PedagoLens_API_Bridge::invoke( 'course_analysis', $params );

        if ( empty( $result['success'] ) ) {
            do_action( 'pedagolens_after_analysis', $course_id, $result );
            return $result;
        }

        $result = self::normalize_scores( $result, $profiles );
        $analysis_id = self::save_analysis( $course_id, $result );
        $result['analysis_id'] = $analysis_id;

        do_action( 'pedagolens_after_analysis', $course_id, $result );
        return $result;
    }

    public static function save_analysis( int $course_id, array $result ): int {
        $course = get_post( $course_id );
        $title  = sprintf(
            'Analyse — %s — %s',
            $course ? $course->post_title : "Cours #{$course_id}",
            wp_date( 'Y-m-d H:i' )
        );

        $post_id = wp_insert_post( [
            'post_type'   => 'pl_analysis',
            'post_title'  => $title,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            PedagoLens_Core::log( 'error', 'save_analysis — wp_insert_post échoué', [ 'course_id' => $course_id ] );
            return 0;
        }

        update_post_meta( $post_id, '_pl_course_id',        $course_id );
        update_post_meta( $post_id, '_pl_profile_scores',   wp_json_encode( $result['profile_scores']   ?? [] ) );
        update_post_meta( $post_id, '_pl_recommendations',  wp_json_encode( $result['recommendations']  ?? [] ) );
        update_post_meta( $post_id, '_pl_raw_response',     wp_json_encode( $result ) );
        update_post_meta( $post_id, '_pl_analyzed_at',      gmdate( 'c' ) );
        update_post_meta( $post_id, '_pl_summary',          sanitize_textarea_field( $result['summary'] ?? '' ) );
        update_post_meta( $post_id, '_pl_impact_estimates', wp_json_encode( $result['impact_estimates'] ?? [] ) );

        return $post_id;
    }

    public static function get_profile_scores( int $analysis_id ): array {
        $raw = get_post_meta( $analysis_id, '_pl_profile_scores', true );
        return is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
    }

    public static function get_recommendations( int $analysis_id ): array {
        $raw  = get_post_meta( $analysis_id, '_pl_recommendations', true );
        $recs = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
        usort( $recs, fn( $a, $b ) => ( $a['priority'] ?? 99 ) <=> ( $b['priority'] ?? 99 ) );
        return $recs;
    }

    // -------------------------------------------------------------------------
    // Projets
    // -------------------------------------------------------------------------

    public static function create_project( int $course_id, string $type, string $title ): int {
        $allowed_types = [ 'magistral', 'exercice', 'evaluation', 'travail_equipe' ];
        if ( ! in_array( $type, $allowed_types, true ) ) {
            return 0;
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'pl_project',
            'post_title'  => sanitize_text_field( $title ),
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return 0;
        }

        $now = gmdate( 'c' );
        update_post_meta( $post_id, '_pl_course_id',         $course_id );
        update_post_meta( $post_id, '_pl_project_type',      $type );
        update_post_meta( $post_id, '_pl_content_sections',  wp_json_encode( [] ) );
        update_post_meta( $post_id, '_pl_profile_scores',    wp_json_encode( [] ) );
        update_post_meta( $post_id, '_pl_recommendations',   wp_json_encode( [] ) );
        update_post_meta( $post_id, '_pl_impact_estimates',  wp_json_encode( [] ) );
        update_post_meta( $post_id, '_pl_versions',          wp_json_encode( [] ) );
        update_post_meta( $post_id, '_pl_created_at',        $now );
        update_post_meta( $post_id, '_pl_updated_at',        $now );

        return $post_id;
    }

    public static function get_projects( int $course_id ): array {
        $query = new WP_Query( [
            'post_type'      => 'pl_project',
            'posts_per_page' => -1,
            'meta_query'     => [ [
                'key'   => '_pl_course_id',
                'value' => $course_id,
                'type'  => 'NUMERIC',
            ] ],
            'orderby' => 'date',
            'order'   => 'DESC',
        ] );

        $projects = [];
        foreach ( $query->posts as $post ) {
            $projects[] = [
                'id'           => $post->ID,
                'title'        => $post->post_title,
                'type'         => get_post_meta( $post->ID, '_pl_project_type', true ),
                'created_at'   => get_post_meta( $post->ID, '_pl_created_at',   true ),
                'updated_at'   => get_post_meta( $post->ID, '_pl_updated_at',   true ),
            ];
        }

        return $projects;
    }

    // -------------------------------------------------------------------------
    // Rendu front-end — Design System Stitch
    // -------------------------------------------------------------------------

    /**
     * Rendu HTML du dashboard enseignant (shortcode).
     * Design System Stitch : Manrope/Inter, Material tokens, glass cards, rounded-[1.5rem].
     */
    public static function render_front(): string {
        if ( ! is_user_logged_in() ) {
            $login_url = esc_url( wp_login_url( get_permalink() ) );
            return '<div class="pl-stitch-notice pl-stitch-notice--warning"><p>Vous devez être connecté pour accéder au tableau de bord. <a href="' . $login_url . '">Se connecter</a></p></div>';
        }

        $user       = wp_get_current_user();
        $is_teacher = in_array( 'pedagolens_teacher', (array) $user->roles, true )
                   || in_array( 'administrator',      (array) $user->roles, true );

        if ( ! $is_teacher ) {
            return '<div class="pl-stitch-notice pl-stitch-notice--error"><p>Accès réservé aux enseignants.</p></div>';
        }

        // Detect analysis detail mode
        $analysis_detail_id = isset( $_GET['analysis_id'] ) ? absint( $_GET['analysis_id'] ) : 0;
        if ( $analysis_detail_id ) {
            return self::render_analysis_content( $analysis_detail_id );
        }

        // Detect course detail mode
        $course_detail_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
        if ( $course_detail_id ) {
            return self::render_course_detail( $course_detail_id );
        }

        wp_enqueue_style(
            'pl-dashboard-front',
            PL_DASHBOARD_PLUGIN_URL . 'assets/css/dashboard-admin.css',
            [],
            PL_DASHBOARD_VERSION
        );
        wp_enqueue_script(
            'pl-dashboard-front',
            PL_DASHBOARD_PLUGIN_URL . 'assets/js/dashboard-admin.js',
            [],
            PL_DASHBOARD_VERSION,
            true
        );

        $courses = get_posts( [
            'post_type'      => 'pl_course',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $mode      = get_option( 'pl_ai_mode', 'mock' );
        $firstname = esc_html( $user->first_name ?: $user->display_name );

        $total_courses  = count( $courses );
        $total_projects = 0;
        $all_scores     = [];
        $courses_data   = [];

        foreach ( $courses as $course ) {
            $course_type = get_post_meta( $course->ID, '_pl_course_type', true ) ?: 'magistral';
            $projects    = self::get_projects( $course->ID );
            $total_projects += count( $projects );

            $analysis = self::get_latest_analysis_front( $course->ID );
            if ( $analysis ) {
                foreach ( ( $analysis['profile_scores'] ?? [] ) as $s ) {
                    $all_scores[] = (int) $s;
                }
            }

            $courses_data[] = [
                'post'     => $course,
                'type'     => $course_type,
                'projects' => $projects,
                'analysis' => $analysis,
            ];
        }

        $total_analyses = self::count_all_analyses();
        $avg_score      = ! empty( $all_scores ) ? (int) round( array_sum( $all_scores ) / count( $all_scores ) ) : 0;

        // Recent analyses
        $recent_analyses = get_posts( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ] );

        // URLs
        $workbench_page = get_page_by_path( 'workbench' );
        $workbench_url  = $workbench_page ? get_permalink( $workbench_page ) : admin_url( 'admin.php?page=pl-course-workbench' );
        $current_url    = get_permalink();

        ob_start();
        ?>
        <div class="pl-stitch-wrap" style="font-family:'Inter',sans-serif;background:#f7f9fb;color:#191c1e;min-height:100vh;">
        <style>
            .pl-stitch-wrap *,.pl-stitch-wrap *::before,.pl-stitch-wrap *::after{box-sizing:border-box}
            .pl-stitch-headline{font-family:'Manrope',sans-serif}
            .pl-stitch-card{background:#fff;border-radius:1.5rem;box-shadow:0 10px 40px rgba(25,28,30,.06);border:1px solid rgba(197,197,211,.1);transition:all .3s ease}
            .pl-stitch-card:hover{box-shadow:0 25px 50px -12px rgba(0,0,0,.12);transform:translateY(-2px)}
            .pl-stitch-btn-primary{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:linear-gradient(135deg,#00236f,#1e3a8a);color:#fff;font-weight:700;font-size:.875rem;border-radius:.75rem;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(0,35,111,.25);transition:all .3s ease;text-decoration:none;font-family:'Inter',sans-serif}
            .pl-stitch-btn-primary:hover{opacity:.9;box-shadow:0 8px 25px rgba(0,35,111,.35);color:#fff;text-decoration:none}
            .pl-stitch-btn-outline{display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1.25rem;border:1px solid rgba(117,118,130,.2);background:#fff;color:#00236f;font-weight:600;font-size:.8125rem;border-radius:.75rem;cursor:pointer;transition:all .3s ease;text-decoration:none;font-family:'Inter',sans-serif}
            .pl-stitch-btn-outline:hover{background:#f2f4f6;border-color:#00236f;color:#00236f;text-decoration:none}
            .pl-stitch-kpi-value{font-family:'Manrope',sans-serif;font-size:3rem;font-weight:900;color:#00236f;letter-spacing:-.03em;line-height:1.1}
            .pl-stitch-notice{padding:1rem 1.5rem;border-radius:.75rem;margin-bottom:1.25rem;font-size:.875rem}
            .pl-stitch-notice--warning{background:rgba(255,193,7,.08);border:1px solid rgba(255,193,7,.2);color:#7a5900}
            .pl-stitch-notice--error{background:rgba(186,26,26,.06);border:1px solid rgba(186,26,26,.15);color:#93000a}
            .pl-stitch-notice p{margin:0}
            .pl-stitch-notice a{color:inherit;text-decoration:underline}
            .pl-stitch-score-bar-wrap{height:.5rem;width:100%;background:#f2f4f6;border-radius:9999px;overflow:hidden}
            .pl-stitch-score-bar{height:100%;border-radius:9999px;transition:width 1.2s cubic-bezier(.25,.8,.25,1)}
            .pl-stitch-tag{display:inline-flex;padding:.25rem .75rem;border-radius:9999px;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
            @media(max-width:768px){
                .pl-stitch-grid-4{grid-template-columns:1fr 1fr !important}
                .pl-stitch-grid-courses{grid-template-columns:1fr !important}
                .pl-stitch-grid-2{grid-template-columns:1fr !important}
            }
            @media(max-width:480px){
                .pl-stitch-grid-4{grid-template-columns:1fr !important}
            }
        </style>

        <!-- Header -->
        <header style="padding:2rem 3rem 1.5rem;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1.5rem;">
            <div>
                <p style="color:#712ae2;font-weight:700;font-size:.8125rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 .5rem">Enseignement & IA</p>
                <h2 class="pl-stitch-headline" style="font-size:2.5rem;font-weight:800;color:#00236f;margin:0;letter-spacing:-.02em;">Tableau de bord</h2>
                <p style="color:#444651;margin:.5rem 0 0;font-size:.9375rem;">Bonjour, <?php echo $firstname; ?> 👋</p>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <button type="button" class="pl-stitch-btn-primary" id="pl-btn-add-course">
                    <span style="font-size:1.1rem">+</span> Nouveau cours
                </button>
                <button type="button" class="pl-stitch-btn-outline" onclick="document.getElementById('pl-btn-add-course').click()">
                    📊 Analyser un cours
                </button>
            </div>
        </header>

        <div style="padding:0 3rem 3rem;">

            <!-- KPI Stats Row -->
            <div class="pl-stitch-grid-4" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;margin-bottom:3rem;">
                <?php
                $kpis = [
                    [ 'label' => 'Cours',        'value' => $total_courses,  'icon' => '📚', 'color' => '#00236f' ],
                    [ 'label' => 'Analyses',      'value' => $total_analyses, 'icon' => '🔍', 'color' => '#712ae2' ],
                    [ 'label' => 'Projets',       'value' => $total_projects, 'icon' => '📄', 'color' => '#004754' ],
                    [ 'label' => 'Score moyen',   'value' => $avg_score,      'icon' => '🏆', 'color' => '#00236f' ],
                ];
                foreach ( $kpis as $kpi ) : ?>
                <div class="pl-stitch-card" style="padding:1.75rem;position:relative;overflow:hidden;">
                    <div style="position:absolute;top:0;right:0;width:6rem;height:6rem;background:<?php echo esc_attr( $kpi['color'] ); ?>;opacity:.04;border-radius:0 0 0 100%;margin-right:-2rem;margin-top:-2rem;"></div>
                    <p style="color:#444651;font-weight:500;font-size:.875rem;margin:0 0 1rem;"><?php echo esc_html( $kpi['label'] ); ?></p>
                    <div style="display:flex;align-items:baseline;gap:.5rem;">
                        <span class="pl-stitch-kpi-value pl-stat-number" data-target="<?php echo (int) $kpi['value']; ?>" style="color:<?php echo esc_attr( $kpi['color'] ); ?>">0</span>
                    </div>
                    <p style="font-size:.75rem;color:#757682;margin:.75rem 0 0;font-style:italic;"><?php echo esc_html( $kpi['icon'] ); ?> <?php echo esc_html( $kpi['label'] ); ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Mes cours -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
                <h3 class="pl-stitch-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Mes cours</h3>
                <span style="font-size:.75rem;font-weight:700;padding:.25rem .75rem;border-radius:9999px;background:rgba(0,35,111,.06);color:#00236f;"><?php echo (int) $total_courses; ?> cours</span>
            </div>

            <?php if ( empty( $courses_data ) ) : ?>
                <div class="pl-stitch-card" style="padding:3rem;text-align:center;">
                    <p style="color:#757682;margin:0;">Aucun cours trouvé. Créez votre premier cours pour commencer.</p>
                </div>
            <?php else : ?>
            <div class="pl-stitch-grid-courses" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:1.5rem;margin-bottom:3rem;">
                <?php foreach ( $courses_data as $cd ) :
                    $course      = $cd['post'];
                    $course_type = $cd['type'];
                    $projects    = $cd['projects'];
                    $analysis    = $cd['analysis'];
                    $last_score  = '';
                    $last_date   = '';
                    if ( $analysis && ! empty( $analysis['analysis_id'] ) ) {
                        $a_post = get_post( $analysis['analysis_id'] );
                        if ( $a_post ) { $last_date = wp_date( 'j M Y', strtotime( $a_post->post_date ) ); }
                        $scores_vals = array_values( $analysis['profile_scores'] ?? [] );
                        if ( ! empty( $scores_vals ) ) {
                            $last_score = (int) round( array_sum( $scores_vals ) / count( $scores_vals ) );
                        }
                    }
                    $detail_url = add_query_arg( 'course_id', $course->ID, $current_url );
                ?>
                <div class="pl-stitch-card" style="padding:0;overflow:hidden;" data-course-id="<?php echo (int) $course->ID; ?>">
                    <div style="padding:1.5rem 1.5rem 1rem;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:.75rem;">
                            <h4 class="pl-stitch-headline" style="font-size:1rem;font-weight:700;color:#00236f;margin:0;line-height:1.4;"><?php echo esc_html( $course->post_title ); ?></h4>
                            <span class="pl-stitch-tag" style="background:rgba(0,35,111,.06);color:#00236f;flex-shrink:0;"><?php echo esc_html( $course_type ); ?></span>
                        </div>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.8125rem;color:#757682;margin-bottom:1rem;">
                            <span>📅 <?php echo esc_html( get_the_date( 'j M Y', $course ) ); ?></span>
                            <span>📄 <?php echo count( $projects ); ?> projet(s)</span>
                            <?php if ( $last_date ) : ?><span>🔍 <?php echo esc_html( $last_date ); ?></span><?php endif; ?>
                        </div>
                        <?php if ( $last_score ) : ?>
                        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
                            <span style="font-size:.75rem;font-weight:600;color:#444651;">Score</span>
                            <div class="pl-stitch-score-bar-wrap">
                                <div class="pl-stitch-score-bar pl-score-bar" data-score="<?php echo $last_score; ?>" style="width:0%;background:<?php echo self::stitch_score_color( $last_score ); ?>;"></div>
                            </div>
                            <span style="font-size:.8125rem;font-weight:700;color:#00236f;"><?php echo $last_score; ?>%</span>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                            <button class="pl-stitch-btn-primary pl-btn-analyze-front" data-course-id="<?php echo (int) $course->ID; ?>" style="padding:.5rem 1rem;font-size:.75rem;">🔍 Analyser</button>
                            <a href="<?php echo esc_url( $detail_url ); ?>" class="pl-stitch-btn-outline" style="padding:.5rem 1rem;font-size:.75rem;">📂 Détails</a>
                        </div>
                    </div>
                    <div id="pl-analysis-result-<?php echo (int) $course->ID; ?>" class="pl-analysis-front-result"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Analyses récentes -->
            <?php if ( ! empty( $recent_analyses ) ) : ?>
            <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;">
                <div class="pl-stitch-card" style="padding:2rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
                        <h3 class="pl-stitch-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Analyses récentes</h3>
                        <span style="color:#757682;font-size:1.25rem;">📋</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0;">
                        <?php foreach ( $recent_analyses as $ra ) :
                            $ra_course_id = (int) get_post_meta( $ra->ID, '_pl_course_id', true );
                            $ra_course    = $ra_course_id ? get_post( $ra_course_id ) : null;
                            $ra_date      = human_time_diff( strtotime( $ra->post_date ), current_time( 'timestamp' ) );
                        ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:.875rem 0;border-bottom:1px solid rgba(197,197,211,.15);">
                            <div style="display:flex;align-items:center;gap:.75rem;">
                                <span style="color:#712ae2;">✨</span>
                                <span style="font-size:.875rem;font-weight:500;color:#191c1e;"><?php echo esc_html( $ra_course ? $ra_course->post_title : $ra->post_title ); ?></span>
                            </div>
                            <span style="font-size:.75rem;color:#757682;">Il y a <?php echo esc_html( $ra_date ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        </div>

        <script type="application/json" id="pl-courses-json"><?php
            $json_data = [];
            foreach ( $courses_data as $cd ) {
                $c = $cd['post'];
                $json_data[] = [
                    'id'       => $c->ID,
                    'title'    => $c->post_title,
                    'type'     => $cd['type'],
                    'date'     => get_the_date( 'j M Y', $c ),
                    'projects' => array_map( function( $p ) use ( $workbench_page ) {
                        $wb = $workbench_page
                            ? get_permalink( $workbench_page ) . '?project_id=' . $p['id']
                            : admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $p['id'] );
                        return [
                            'id'    => $p['id'],
                            'title' => $p['title'],
                            'type'  => $p['type'],
                            'date'  => $p['created_at'] ? wp_date( 'j M Y', strtotime( $p['created_at'] ) ) : '',
                            'url'   => $wb,
                        ];
                    }, $cd['projects'] ),
                ];
            }
            echo wp_json_encode( $json_data );
        ?></script>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Tâche 9.5 — Vue détaillée du cours (Design System Stitch)
    // -------------------------------------------------------------------------

    /**
     * Rendu de la page détail d'un cours.
     * Inspiré du template d_tails_du_cours_p_dagolens.
     */
    public static function render_course_detail( int $course_id ): string {
        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== 'pl_course' ) {
            return '<div class="pl-stitch-notice pl-stitch-notice--error"><p>Cours introuvable.</p></div>';
        }

        wp_enqueue_style(
            'pl-dashboard-front',
            PL_DASHBOARD_PLUGIN_URL . 'assets/css/dashboard-admin.css',
            [],
            PL_DASHBOARD_VERSION
        );
        wp_enqueue_script(
            'pl-dashboard-front',
            PL_DASHBOARD_PLUGIN_URL . 'assets/js/dashboard-admin.js',
            [],
            PL_DASHBOARD_VERSION,
            true
        );

        $course_type = get_post_meta( $course_id, '_pl_course_type', true ) ?: 'magistral';
        $analysis    = self::get_latest_analysis_front( $course_id );
        $projects    = self::get_projects( $course_id );
        $profiles    = self::get_active_profiles();

        $scores = $analysis['profile_scores'] ?? [];
        $recs   = $analysis['recommendations'] ?? [];
        $avg_score = 0;
        if ( ! empty( $scores ) ) {
            $avg_score = (int) round( array_sum( array_map( 'intval', $scores ) ) / count( $scores ) );
        }

        $back_url = remove_query_arg( 'course_id' );

        // Workbench URL
        $workbench_page = get_page_by_path( 'workbench' );
        $workbench_url  = $workbench_page ? get_permalink( $workbench_page ) : admin_url( 'admin.php?page=pl-course-workbench' );

        ob_start();
        ?>
        <div class="pl-stitch-wrap" style="font-family:'Inter',sans-serif;background:#f7f9fb;color:#191c1e;min-height:100vh;">
        <style>
            .pl-stitch-wrap *,.pl-stitch-wrap *::before,.pl-stitch-wrap *::after{box-sizing:border-box}
            .pl-stitch-headline{font-family:'Manrope',sans-serif}
            .pl-stitch-card{background:#fff;border-radius:1.5rem;box-shadow:0 10px 40px rgba(25,28,30,.06);border:1px solid rgba(197,197,211,.1);transition:all .3s ease}
            .pl-stitch-btn-primary{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:linear-gradient(135deg,#00236f,#1e3a8a);color:#fff;font-weight:700;font-size:.875rem;border-radius:.75rem;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(0,35,111,.25);transition:all .3s ease;text-decoration:none;font-family:'Inter',sans-serif}
            .pl-stitch-btn-primary:hover{opacity:.9;color:#fff;text-decoration:none}
            .pl-stitch-btn-outline{display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1.25rem;border:1px solid rgba(117,118,130,.2);background:#fff;color:#00236f;font-weight:600;font-size:.8125rem;border-radius:.75rem;cursor:pointer;transition:all .3s ease;text-decoration:none;font-family:'Inter',sans-serif}
            .pl-stitch-btn-outline:hover{background:#f2f4f6;border-color:#00236f;color:#00236f;text-decoration:none}
            .pl-stitch-kpi-value{font-family:'Manrope',sans-serif;font-size:3rem;font-weight:900;color:#00236f;letter-spacing:-.03em;line-height:1.1}
            .pl-stitch-score-bar-wrap{height:.5rem;width:100%;background:#f2f4f6;border-radius:9999px;overflow:hidden}
            .pl-stitch-score-bar{height:100%;border-radius:9999px;transition:width 1.2s cubic-bezier(.25,.8,.25,1)}
            .pl-stitch-tag{display:inline-flex;padding:.25rem .75rem;border-radius:9999px;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
            @media(max-width:768px){
                .pl-stitch-grid-4{grid-template-columns:1fr 1fr !important}
                .pl-stitch-grid-2{grid-template-columns:1fr !important}
            }
        </style>

        <!-- Header -->
        <header style="background:#f2f4f6;padding:2rem 3rem;">
            <nav style="display:flex;align-items:center;gap:.5rem;font-size:.8125rem;color:#444651;margin-bottom:1rem;">
                <a href="<?php echo esc_url( $back_url ); ?>" style="color:#444651;text-decoration:none;">Tableau de bord</a>
                <span style="font-size:.75rem;">›</span>
                <span style="color:#00236f;font-weight:600;"><?php echo esc_html( $course->post_title ); ?></span>
            </nav>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1.5rem;">
                <div>
                    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem;">
                        <span class="pl-stitch-tag" style="background:rgba(172,237,255,.2);color:#004e5c;"><?php echo esc_html( $course_type ); ?></span>
                        <?php if ( $analysis ) : ?>
                        <span style="display:flex;align-items:center;gap:.25rem;font-size:.625rem;font-weight:700;color:#712ae2;text-transform:uppercase;letter-spacing:.05em;">✓ Analysé</span>
                        <?php endif; ?>
                    </div>
                    <h2 class="pl-stitch-headline" style="font-size:2.25rem;font-weight:800;color:#00236f;margin:0;letter-spacing:-.02em;"><?php echo esc_html( $course->post_title ); ?></h2>
                    <p style="color:#444651;margin:.5rem 0 0;font-size:.875rem;">Enseignant : <strong style="color:#191c1e;"><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong></p>
                </div>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( $workbench_url ); ?>" class="pl-stitch-btn-outline">📝 Ouvrir dans l'atelier</a>
                    <button class="pl-stitch-btn-primary pl-btn-analyze-front" data-course-id="<?php echo (int) $course_id; ?>">🔄 Relancer l'analyse</button>
                </div>
            </div>
        </header>

        <div style="padding:2.5rem 3rem;">

            <!-- Summary KPI Cards -->
            <div class="pl-stitch-grid-4" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;margin-bottom:3rem;">
                <div class="pl-stitch-card" style="padding:1.5rem;position:relative;overflow:hidden;">
                    <p style="font-size:.625rem;font-weight:700;color:#444651;text-transform:uppercase;letter-spacing:.1em;margin:0 0 1rem;">Score Moyen</p>
                    <div style="display:flex;align-items:baseline;gap:.5rem;">
                        <span class="pl-stitch-kpi-value"><?php echo $avg_score; ?></span>
                        <span style="color:#444651;font-weight:700;font-size:1.25rem;">/100</span>
                    </div>
                </div>
                <div class="pl-stitch-card" style="padding:1.5rem;">
                    <p style="font-size:.625rem;font-weight:700;color:#444651;text-transform:uppercase;letter-spacing:.1em;margin:0 0 1rem;">Profils analysés</p>
                    <div style="display:flex;align-items:baseline;gap:.5rem;">
                        <span class="pl-stitch-kpi-value" style="color:#712ae2;"><?php echo str_pad( count( $scores ), 2, '0', STR_PAD_LEFT ); ?></span>
                        <span style="color:#444651;font-weight:700;font-size:.875rem;">profils</span>
                    </div>
                </div>
                <div class="pl-stitch-card" style="padding:1.5rem;">
                    <p style="font-size:.625rem;font-weight:700;color:#444651;text-transform:uppercase;letter-spacing:.1em;margin:0 0 1rem;">Recommandations</p>
                    <div style="display:flex;align-items:baseline;gap:.5rem;">
                        <span class="pl-stitch-kpi-value" style="color:#004754;"><?php echo str_pad( count( $recs ), 2, '0', STR_PAD_LEFT ); ?></span>
                        <span style="color:#444651;font-weight:700;font-size:.875rem;">actions</span>
                    </div>
                </div>
                <div class="pl-stitch-card" style="padding:1.5rem;">
                    <p style="font-size:.625rem;font-weight:700;color:#444651;text-transform:uppercase;letter-spacing:.1em;margin:0 0 1rem;">Projets</p>
                    <div style="display:flex;align-items:baseline;gap:.5rem;">
                        <span class="pl-stitch-kpi-value"><?php echo str_pad( count( $projects ), 2, '0', STR_PAD_LEFT ); ?></span>
                        <span style="color:#444651;font-weight:700;font-size:.875rem;">projets</span>
                    </div>
                </div>
            </div>

            <!-- Radar Chart Placeholder + Scores par profil -->
            <div class="pl-stitch-grid-2" style="display:grid;grid-template-columns:5fr 7fr;gap:1.5rem;margin-bottom:3rem;">
                <!-- Radar placeholder -->
                <div class="pl-stitch-card" style="padding:2rem;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:320px;"
                     data-radar-scores="<?php echo esc_attr( wp_json_encode( $scores ) ); ?>">
                    <h3 class="pl-stitch-headline" style="font-size:1rem;font-weight:700;color:#00236f;margin:0 0 1.5rem;align-self:flex-start;">Radar des profils</h3>
                    <div style="width:220px;height:220px;border-radius:50%;border:2px dashed rgba(117,118,130,.2);display:flex;align-items:center;justify-content:center;position:relative;">
                        <div style="text-align:center;">
                            <span class="pl-stitch-headline" style="font-size:2.5rem;font-weight:900;color:#00236f;"><?php echo $avg_score; ?></span>
                            <p style="font-size:.75rem;color:#712ae2;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin:.25rem 0 0;">
                                <?php echo $avg_score >= 80 ? 'Excellent' : ( $avg_score >= 60 ? 'Bon' : ( $avg_score >= 40 ? 'Moyen' : 'À améliorer' ) ); ?>
                            </p>
                        </div>
                    </div>
                    <p style="font-size:.75rem;color:#757682;margin:1rem 0 0;text-align:center;">Graphique radar — intégration Chart.js à venir</p>
                </div>

                <!-- Scores par profil -->
                <div class="pl-stitch-card" style="padding:2rem;">
                    <h3 class="pl-stitch-headline" style="font-size:1.125rem;font-weight:700;color:#00236f;margin:0 0 2rem;">Scores par profil pédagogique</h3>
                    <?php if ( ! empty( $scores ) ) : ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem 3rem;">
                        <?php foreach ( $scores as $slug => $score ) :
                            $score     = max( 0, min( 100, (int) $score ) );
                            $bar_color = self::stitch_score_color( $score );
                            $label     = self::profile_label( $slug, $profiles );
                        ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                                <span style="font-size:.875rem;font-weight:600;color:#191c1e;"><?php echo esc_html( $label ); ?></span>
                                <span style="font-size:.875rem;font-weight:700;color:#00236f;"><?php echo $score; ?>%</span>
                            </div>
                            <div class="pl-stitch-score-bar-wrap">
                                <div class="pl-stitch-score-bar pl-score-bar" data-score="<?php echo $score; ?>" style="width:0%;background:<?php echo esc_attr( $bar_color ); ?>;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                    <p style="color:#757682;font-size:.875rem;">Aucune analyse disponible. Lancez une analyse pour voir les scores.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recommandations -->
            <?php if ( ! empty( $recs ) ) : ?>
            <div class="pl-stitch-card" style="padding:2rem;margin-bottom:3rem;">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
                    <span style="display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;background:rgba(234,221,255,.5);color:#712ae2;border-radius:.5rem;font-size:1.1rem;">💡</span>
                    <h3 class="pl-stitch-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Recommandations</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    <?php $idx = 1; foreach ( $recs as $rec ) :
                        $priority     = (int) ( $rec['priority'] ?? 99 );
                        $priority_bg  = $priority <= 2 ? '#ba1a1a' : ( $priority <= 4 ? '#ff9100' : '#2979ff' );
                    ?>
                    <div style="display:flex;gap:1rem;align-items:flex-start;padding:1rem;border-radius:.75rem;transition:background .2s;" onmouseover="this.style.background='#f2f4f6'" onmouseout="this.style.background='transparent'">
                        <span style="flex-shrink:0;font-size:1.25rem;font-weight:900;color:#712ae2;"><?php echo str_pad( $idx, 2, '0', STR_PAD_LEFT ); ?></span>
                        <div style="flex:1;">
                            <h4 style="font-size:.875rem;font-weight:700;color:#00236f;margin:0 0 .25rem;"><?php echo esc_html( $rec['section'] ?? '' ); ?></h4>
                            <p style="font-size:.8125rem;color:#444651;margin:0;line-height:1.6;"><?php echo esc_html( $rec['text'] ?? '' ); ?></p>
                            <?php if ( ! empty( $rec['profile_target'] ) ) : ?>
                            <span class="pl-stitch-tag" style="background:rgba(234,221,255,.3);color:#712ae2;margin-top:.5rem;"><?php echo esc_html( $rec['profile_target'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <span style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:50%;background:<?php echo esc_attr( $priority_bg ); ?>;color:#fff;font-size:.625rem;font-weight:800;"><?php echo $priority; ?></span>
                    </div>
                    <?php $idx++; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Analysis result zone (for AJAX re-analysis) -->
            <div id="pl-analysis-result-<?php echo (int) $course_id; ?>" class="pl-analysis-front-result"></div>

            <!-- Back button -->
            <div style="margin-top:2rem;">
                <a href="<?php echo esc_url( $back_url ); ?>" class="pl-stitch-btn-outline">← Retour au tableau de bord</a>
            </div>

        </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Tâche 9.6 — Vue complète d'analyse (Design System Stitch)
    // -------------------------------------------------------------------------

    /**
     * Rendu pleine page d'un résultat d'analyse.
     *
     * Fidèle au template Stitch analyse_de_contenu_p_dagolens :
     * - Vue d'ensemble avec résumé narratif
     * - Gauge SVG du score global + barres par profil
     * - Quatre piliers indicateurs
     * - Risques identifiés + Recommandations IA avec priorité/profil cible
     * - Simulation d'impact après correction
     * - Bouton « Ouvrir dans l'atelier »
     *
     * @param int $analysis_id Post ID (pl_analysis).
     * @return string HTML complet.
     */
    public static function render_analysis_content( int $analysis_id ): string {
        $analysis_post = get_post( $analysis_id );
        if ( ! $analysis_post || $analysis_post->post_type !== 'pl_analysis' ) {
            return '<div class="pl-stitch-notice pl-stitch-notice--error"><p>Analyse introuvable.</p></div>';
        }

        wp_enqueue_style(
            'pl-dashboard-front',
            PL_DASHBOARD_PLUGIN_URL . 'assets/css/dashboard-admin.css',
            [],
            PL_DASHBOARD_VERSION
        );
        wp_enqueue_script(
            'pl-dashboard-front',
            PL_DASHBOARD_PLUGIN_URL . 'assets/js/dashboard-admin.js',
            [],
            PL_DASHBOARD_VERSION,
            true
        );

        /* ── Données ─────────────────────────────────────── */
        $course_id = (int) get_post_meta( $analysis_id, '_pl_course_id', true );
        $course    = $course_id ? get_post( $course_id ) : null;
        $scores    = self::get_profile_scores( $analysis_id );
        $recs      = self::get_recommendations( $analysis_id );
        $summary   = get_post_meta( $analysis_id, '_pl_summary', true );
        $impacts   = json_decode( get_post_meta( $analysis_id, '_pl_impact_estimates', true ) ?: '[]', true );
        $profiles  = self::get_active_profiles();

        $course_title = $course ? $course->post_title : $analysis_post->post_title;
        $analyzed_at  = wp_date( 'j F Y à H:i', strtotime( $analysis_post->post_date ) );

        $avg_score   = 0;
        $score_vals  = array_map( 'intval', array_values( $scores ) );
        if ( ! empty( $score_vals ) ) {
            $avg_score = (int) round( array_sum( $score_vals ) / count( $score_vals ) );
        }

        $score_label = $avg_score >= 80 ? 'Excellent'
            : ( $avg_score >= 60 ? 'Bon'
            : ( $avg_score >= 40 ? 'Modéré' : 'À améliorer' ) );

        $circumference = 2 * M_PI * 88;
        $dash_offset   = $circumference - ( $circumference * $avg_score / 100 );

        // Workbench URL
        $workbench_page = get_page_by_path( 'workbench' );
        $workbench_url  = $workbench_page ? get_permalink( $workbench_page ) : admin_url( 'admin.php?page=pl-course-workbench' );

        $back_url = remove_query_arg( 'analysis_id' );

        // Separate high-priority risks from recommendations
        $risks = array_filter( $recs, fn( $r ) => ( (int) ( $r['priority'] ?? 99 ) ) <= 2 );
        $suggestions = array_filter( $recs, fn( $r ) => ( (int) ( $r['priority'] ?? 99 ) ) > 2 );

        // Four pillars
        $pillars = [
            [ 'icon' => '🧠', 'label' => 'Charge Cognitive',  'value' => $avg_score >= 70 ? 'Modérée' : ( $avg_score >= 50 ? 'Élevée' : 'Critique' ), 'border' => '#00236f' ],
            [ 'icon' => '❓', 'label' => 'Ambiguïté',         'value' => $avg_score >= 75 ? 'Faible' : ( $avg_score >= 55 ? 'Modérée' : 'Élevée' ),    'border' => '#712ae2' ],
            [ 'icon' => '🌐', 'label' => 'Accessibilité',     'value' => $avg_score >= 80 ? 'Optimale' : ( $avg_score >= 60 ? 'Correcte' : 'Limitée' ),'border' => '#004754' ],
            [ 'icon' => '✅', 'label' => 'Évaluation',        'value' => $avg_score >= 70 ? 'Très Claire' : ( $avg_score >= 50 ? 'Claire' : 'Confuse' ),'border' => '#757682' ],
        ];

        ob_start();
        ?>
        <div class="pl-stitch-wrap" style="font-family:'Inter',sans-serif;background:#f7f9fb;color:#191c1e;min-height:100vh;">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Inter:wght@400;500;600;700&display=swap');
            .pl-stitch-wrap *,.pl-stitch-wrap *::before,.pl-stitch-wrap *::after{box-sizing:border-box}
            .pl-stitch-headline{font-family:'Manrope',sans-serif}
            .pl-ac-card{background:rgba(255,255,255,.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-radius:1.5rem;box-shadow:0 10px 40px rgba(25,28,30,.06);border:1px solid rgba(197,197,211,.1);transition:all .3s ease}
            .pl-ac-card:hover{box-shadow:0 20px 50px rgba(25,28,30,.1);transform:translateY(-1px)}
            .pl-ac-ia-glow{box-shadow:0 0 24px rgba(113,42,226,.12)}
            .pl-ac-bar-track{height:.5rem;width:100%;background:#f2f4f6;border-radius:9999px;overflow:hidden}
            .pl-ac-bar-fill{height:100%;border-radius:9999px;transition:width 1.4s cubic-bezier(.25,.8,.25,1)}
            .pl-ac-gauge{transition:stroke-dashoffset 1.6s cubic-bezier(.25,.8,.25,1)}
            .pl-ac-tag{display:inline-flex;padding:.1875rem .625rem;border-radius:9999px;font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
            .pl-ac-rec:hover{background:#f2f4f6}
            .pl-ac-impact-row:hover{background:#f7f9fb}
            .pl-ac-btn-primary{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:linear-gradient(135deg,#00236f,#1e3a8a);color:#fff;font-weight:700;font-size:.875rem;border-radius:.75rem;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(0,35,111,.25);transition:all .3s ease;text-decoration:none;font-family:'Inter',sans-serif}
            .pl-ac-btn-primary:hover{opacity:.9;color:#fff;text-decoration:none}
            .pl-ac-btn-outline{display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1.25rem;border:1px solid rgba(117,118,130,.2);background:#fff;color:#00236f;font-weight:600;font-size:.8125rem;border-radius:.75rem;cursor:pointer;transition:all .3s ease;text-decoration:none;font-family:'Inter',sans-serif}
            .pl-ac-btn-outline:hover{background:#f2f4f6;color:#00236f;text-decoration:none}
            @media(max-width:768px){
                .pl-ac-grid-top{grid-template-columns:1fr !important}
                .pl-ac-grid-profiles{grid-template-columns:1fr !important}
                .pl-ac-grid-2col{grid-template-columns:1fr !important}
                .pl-ac-grid-pillars{grid-template-columns:1fr 1fr !important}
            }
            @media(max-width:480px){
                .pl-ac-grid-pillars{grid-template-columns:1fr !important}
            }
        </style>

        <!-- ============================================================= -->
        <!-- HEADER                                                        -->
        <!-- ============================================================= -->
        <header style="padding:2rem 3rem 1.5rem;">
            <nav style="display:flex;align-items:center;gap:.5rem;font-size:.8125rem;color:#444651;margin-bottom:1rem;">
                <a href="<?php echo esc_url( $back_url ); ?>" style="color:#444651;text-decoration:none;">Tableau de bord</a>
                <span style="font-size:.75rem;">›</span>
                <span>Analyses IA</span>
                <span style="font-size:.75rem;">›</span>
                <span style="font-weight:600;color:#00236f;"><?php echo esc_html( $course_title ); ?></span>
            </nav>
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:1.5rem;">
                <div>
                    <h2 class="pl-stitch-headline" style="font-size:2.5rem;font-weight:800;color:#00236f;margin:0;letter-spacing:-.02em;">Analyse de contenu</h2>
                    <p style="color:#444651;margin:.5rem 0 0;max-width:40rem;font-size:.9375rem;">
                        <?php if ( $summary ) : ?>
                            <?php echo esc_html( mb_strimwidth( $summary, 0, 160, '…' ) ); ?>
                        <?php else : ?>
                            Diagnostic prédictif de l'accessibilité pédagogique — <?php echo esc_html( $course_title ); ?>.
                        <?php endif; ?>
                    </p>
                    <p style="color:#757682;margin:.375rem 0 0;font-size:.8125rem;">Analysé le <?php echo esc_html( $analyzed_at ); ?></p>
                </div>
                <div style="display:flex;gap:.5rem;">
                    <a href="<?php echo esc_url( $back_url ); ?>" class="pl-ac-btn-outline">← Retour</a>
                </div>
            </div>
        </header>

        <div style="padding:0 3rem 3rem;">

        <!-- ============================================================= -->
        <!-- ROW 1 : Score global gauge + Scores par profil (Bento)        -->
        <!-- ============================================================= -->
        <div class="pl-ac-grid-top" style="display:grid;grid-template-columns:4fr 8fr;gap:1.5rem;margin-bottom:2rem;">

            <!-- Gauge card -->
            <div class="pl-ac-card pl-ac-ia-glow" style="padding:2rem;position:relative;overflow:hidden;display:flex;flex-direction:column;align-items:center;border:1px solid rgba(113,42,226,.06);">
                <div style="position:absolute;top:1rem;right:1rem;opacity:.15;font-size:2.5rem;color:#712ae2;">✨</div>
                <h3 style="color:#757682;font-weight:500;font-size:.8125rem;margin:0 0 1.5rem;display:flex;align-items:center;gap:.375rem;align-self:flex-start;">
                    <span style="color:#712ae2;">⚡</span> Indice Global de Clarté
                </h3>
                <div style="position:relative;width:12rem;height:12rem;">
                    <svg viewBox="0 0 192 192" style="width:100%;height:100%;transform:rotate(-90deg);">
                        <circle cx="96" cy="96" r="88" fill="transparent" stroke="#f2f4f6" stroke-width="8"></circle>
                        <circle class="pl-ac-gauge" cx="96" cy="96" r="88" fill="transparent"
                                stroke="#712ae2" stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="<?php echo round( $circumference, 2 ); ?>"
                                stroke-dashoffset="<?php echo round( $dash_offset, 2 ); ?>"></circle>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                        <span class="pl-stitch-headline" style="font-size:3rem;font-weight:900;color:#00236f;letter-spacing:-.03em;line-height:1;"><?php echo $avg_score; ?><span style="font-size:1.25rem;">%</span></span>
                        <span style="font-size:.5625rem;font-weight:700;color:#712ae2;text-transform:uppercase;letter-spacing:.12em;margin-top:.25rem;"><?php echo esc_html( $score_label ); ?></span>
                    </div>
                </div>
                <p style="text-align:center;font-size:.75rem;color:#757682;margin:1.25rem 0 0;line-height:1.5;">
                    Score moyen calculé sur <?php echo count( $scores ); ?> profil(s) étudiant(s).
                </p>
            </div>

            <!-- Profile comprehension bars -->
            <div class="pl-ac-card" style="padding:2rem;">
                <h3 class="pl-stitch-headline" style="font-size:1.125rem;font-weight:700;color:#00236f;margin:0 0 2rem;">Compréhension par profil étudiant</h3>
                <div class="pl-ac-grid-profiles" style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem 3rem;">
                    <?php foreach ( $scores as $slug => $score ) :
                        $score     = max( 0, min( 100, (int) $score ) );
                        $bar_color = self::stitch_score_color( $score );
                        $label     = self::profile_label( $slug, $profiles );
                        $hint      = $score < 50 ? 'Risque identifié — attention requise.' : ( $score < 70 ? 'Point d\'attention.' : '' );
                    ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.375rem;">
                            <span style="font-size:.875rem;font-weight:600;color:#191c1e;"><?php echo esc_html( $label ); ?></span>
                            <span style="font-size:.875rem;font-weight:700;color:#00236f;"><?php echo $score; ?>%</span>
                        </div>
                        <div class="pl-ac-bar-track">
                            <div class="pl-ac-bar-fill pl-score-bar" data-score="<?php echo $score; ?>" style="width:0%;background:<?php echo esc_attr( $bar_color ); ?>;"></div>
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
        <div class="pl-ac-grid-pillars" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;">
            <?php foreach ( $pillars as $p ) : ?>
            <div style="background:#f2f4f6;border-radius:.75rem;padding:1.25rem;border-left:4px solid <?php echo esc_attr( $p['border'] ); ?>;">
                <span style="font-size:1.25rem;display:block;margin-bottom:.375rem;"><?php echo $p['icon']; ?></span>
                <p style="font-size:.625rem;font-weight:700;color:#757682;text-transform:uppercase;margin:0 0 .25rem;letter-spacing:.05em;"><?php echo esc_html( $p['label'] ); ?></p>
                <p class="pl-stitch-headline" style="font-size:1.25rem;font-weight:900;color:#00236f;margin:0;"><?php echo esc_html( $p['value'] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================= -->
        <!-- ROW 3 : Risques + Recommandations (2 colonnes)                -->
        <!-- ============================================================= -->
        <div class="pl-ac-grid-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">

            <!-- Risques identifiés -->
            <section class="pl-ac-card" style="padding:2rem;border:1px solid rgba(186,26,26,.08);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
                    <span style="display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;background:rgba(255,218,214,.5);color:#93000a;border-radius:.5rem;font-size:1.1rem;">⚠️</span>
                    <h3 class="pl-stitch-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Risques Identifiés</h3>
                </div>
                <?php if ( ! empty( $risks ) ) : ?>
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    <?php foreach ( $risks as $risk ) : ?>
                    <div style="padding:1rem;background:rgba(186,26,26,.04);border-left:2px solid #ba1a1a;border-radius:0 .5rem .5rem 0;">
                        <h4 style="font-size:.875rem;font-weight:700;color:#93000a;margin:0 0 .25rem;"><?php echo esc_html( $risk['section'] ?? 'Risque' ); ?></h4>
                        <p style="font-size:.8125rem;color:#444651;margin:0;line-height:1.6;"><?php echo esc_html( $risk['text'] ?? '' ); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p style="font-size:.875rem;color:#757682;margin:0;">Aucun risque critique détecté. 🎉</p>
                <?php endif; ?>
            </section>

            <!-- Recommandations IA -->
            <section class="pl-ac-card" style="padding:2rem;border:1px solid rgba(113,42,226,.08);">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;">
                    <span style="display:flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;background:rgba(234,221,255,.5);color:#712ae2;border-radius:.5rem;font-size:1.1rem;">💡</span>
                    <h3 class="pl-stitch-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Recommandations IA</h3>
                </div>
                <?php if ( ! empty( $recs ) ) : ?>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <?php $idx = 1; foreach ( $recs as $rec ) :
                        $priority = (int) ( $rec['priority'] ?? 99 );
                    ?>
                    <div class="pl-ac-rec" style="display:flex;gap:1rem;align-items:flex-start;padding:1rem;border-radius:.75rem;transition:background .2s ease;">
                        <span style="flex-shrink:0;font-size:1.25rem;font-weight:900;color:#712ae2;font-family:'Manrope',sans-serif;"><?php echo str_pad( $idx, 2, '0', STR_PAD_LEFT ); ?></span>
                        <div style="flex:1;min-width:0;">
                            <h4 style="font-size:.875rem;font-weight:700;color:#00236f;margin:0 0 .25rem;"><?php echo esc_html( $rec['section'] ?? '' ); ?></h4>
                            <p style="font-size:.8125rem;color:#444651;margin:0;line-height:1.6;"><?php echo esc_html( $rec['text'] ?? '' ); ?></p>
                            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem;">
                                <?php if ( ! empty( $rec['profile_target'] ) ) : ?>
                                <span class="pl-ac-tag" style="background:rgba(234,221,255,.3);color:#712ae2;"><?php echo esc_html( self::profile_label( $rec['profile_target'], $profiles ) ); ?></span>
                                <?php endif; ?>
                                <?php if ( $priority <= 2 ) : ?>
                                <span class="pl-ac-tag" style="background:rgba(186,26,26,.08);color:#ba1a1a;">Prioritaire</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?php echo esc_url( add_query_arg( 'rec_section', urlencode( $rec['section'] ?? '' ), $workbench_url ) ); ?>"
                           class="pl-ac-btn-outline" style="flex-shrink:0;padding:.375rem .75rem;font-size:.6875rem;">
                            📝 Atelier
                        </a>
                    </div>
                    <?php $idx++; endforeach; ?>
                </div>
                <?php else : ?>
                <p style="font-size:.875rem;color:#757682;margin:0;">Aucune recommandation pour cette analyse.</p>
                <?php endif; ?>
            </section>
        </div>

        <!-- ============================================================= -->
        <!-- ROW 4 : Simulation d'impact après correction                  -->
        <!-- ============================================================= -->
        <?php if ( ! empty( $impacts ) && is_array( $impacts ) ) : ?>
        <div class="pl-ac-card" style="padding:2rem;margin-bottom:2rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
                <h3 class="pl-stitch-headline" style="font-size:1.25rem;font-weight:700;color:#00236f;margin:0;">Simulation d'impact après correction</h3>
                <span class="pl-ac-tag" style="background:rgba(172,237,255,.25);color:#004e5c;">Prévisionnel</span>
            </div>
            <div style="overflow-x:auto;">
            <table style="width:100%;text-align:left;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid rgba(197,197,211,.15);">
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Indicateur</th>
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">État Actuel</th>
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Post-Correction</th>
                        <th style="padding-bottom:.875rem;font-size:.6875rem;font-weight:700;color:#757682;text-transform:uppercase;letter-spacing:.05em;">Progression</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $impacts as $impact ) :
                        if ( ! is_array( $impact ) ) { continue; }
                        $indicator = $impact['indicator'] ?? $impact['label'] ?? '';
                        $current   = $impact['current']   ?? '';
                        $projected = $impact['projected']  ?? $impact['after'] ?? '';
                        $change    = $impact['change']     ?? $impact['delta'] ?? '';
                    ?>
                    <tr class="pl-ac-impact-row" style="border-bottom:1px solid rgba(197,197,211,.06);transition:background .2s ease;">
                        <td style="padding:1.25rem 0;font-size:.875rem;font-weight:600;color:#00236f;"><?php echo esc_html( $indicator ); ?></td>
                        <td style="padding:1.25rem 0;font-size:.875rem;color:#444651;"><?php echo esc_html( $current ); ?></td>
                        <td style="padding:1.25rem 0;font-size:.875rem;font-weight:700;color:#004754;"><?php echo esc_html( $projected ); ?></td>
                        <td style="padding:1.25rem 0;">
                            <span style="display:flex;align-items:center;gap:.25rem;color:#004754;font-size:.75rem;font-weight:700;">
                                📈 <?php echo esc_html( $change ); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- ROW 5 : Résumé narratif                                       -->
        <!-- ============================================================= -->
        <?php if ( $summary ) : ?>
        <div class="pl-ac-card" style="padding:2rem;margin-bottom:2rem;">
            <h3 class="pl-stitch-headline" style="font-size:1.125rem;font-weight:700;color:#00236f;margin:0 0 1rem;">Résumé de l'analyse</h3>
            <div style="border-left:3px solid #712ae2;padding-left:1.25rem;">
                <p style="font-size:.9375rem;color:#444651;line-height:1.7;margin:0;font-style:italic;"><?php echo esc_html( $summary ); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- Action buttons                                                -->
        <!-- ============================================================= -->
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a href="<?php echo esc_url( $workbench_url ); ?>" class="pl-ac-btn-primary">📝 Ouvrir dans l'atelier</a>
            <?php if ( $course_id ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'course_id', $course_id, $back_url ) ); ?>" class="pl-ac-btn-outline">📂 Détails du cours</a>
            <?php endif; ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="pl-ac-btn-outline">← Tableau de bord</a>
        </div>

        </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Retourne la dernière analyse d'un cours (pour le front).
     */
    private static function get_latest_analysis_front( int $course_id ): ?array {
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
            'analysis_id'     => $id,
            'profile_scores'  => self::get_profile_scores( $id ),
            'recommendations' => self::get_recommendations( $id ),
            'summary'         => get_post_meta( $id, '_pl_summary', true ),
            'impact_estimates' => json_decode( get_post_meta( $id, '_pl_impact_estimates', true ) ?: '[]', true ),
        ];
    }

    private static function count_analyses( int $course_id ): int {
        $q = new WP_Query( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [
                'key'   => '_pl_course_id',
                'value' => $course_id,
                'type'  => 'NUMERIC',
            ] ],
        ] );
        return $q->found_posts;
    }

    private static function count_all_analyses(): int {
        $q = new WP_Query( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        return $q->found_posts;
    }

    public static function get_active_profiles(): array {
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            return PedagoLens_Profile_Manager::get_all( active_only: true );
        }
        return [];
    }

    private static function normalize_scores( array $result, array $profiles ): array {
        $scores      = $result['profile_scores'] ?? [];
        $normalized  = [];

        foreach ( $profiles as $profile ) {
            $slug              = $profile['slug'];
            $normalized[$slug] = isset( $scores[$slug] ) ? (int) $scores[$slug] : 0;
        }

        $result['profile_scores'] = $normalized;
        return $result;
    }

    /**
     * Couleur Stitch pour un score (grille de scoring).
     */
    private static function stitch_score_color( int $score ): string {
        if ( $score >= 80 ) return '#00236f';   // primary — excellent
        if ( $score >= 60 ) return '#004754';   // tertiary-container — bon
        if ( $score >= 40 ) return '#712ae2';   // secondary — moyen
        return '#ba1a1a';                        // error — à améliorer
    }

    /**
     * Résout le label lisible d'un profil à partir de son slug.
     */
    private static function profile_label( string $slug, array $profiles ): string {
        foreach ( $profiles as $p ) {
            if ( ( $p['slug'] ?? '' ) === $slug ) {
                return $p['name'] ?? $p['label'] ?? $slug;
            }
        }
        return ucfirst( str_replace( [ '-', '_' ], ' ', $slug ) );
    }

    private static function error( string $code, string $message, array $context = [] ): array {
        PedagoLens_Core::log( 'error', $message, $context );
        return [
            'success'       => false,
            'error_code'    => $code,
            'error_message' => $message,
            'context'       => $context,
        ];
    }
}
