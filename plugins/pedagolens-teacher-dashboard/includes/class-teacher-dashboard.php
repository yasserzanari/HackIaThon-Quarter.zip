<?php
/**
 * PedagoLens_Teacher_Dashboard
 *
 * Logique métier : analyse de cours, persistance des analyses,
 * lecture des scores/recommandations, gestion des projets.
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

    /**
     * Déclenche une analyse IA du cours et persiste le résultat.
     *
     * @return array  Résultat structuré ou tableau d'erreur.
     */
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

        // Garantir que les scores couvrent exactement les profils actifs
        $result = self::normalize_scores( $result, $profiles );

        $analysis_id = self::save_analysis( $course_id, $result );
        $result['analysis_id'] = $analysis_id;

        do_action( 'pedagolens_after_analysis', $course_id, $result );

        return $result;
    }

    /**
     * Persiste un résultat d'analyse dans un CPT pl_analysis.
     */
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

    /**
     * Retourne les scores par profil d'une analyse.
     */
    public static function get_profile_scores( int $analysis_id ): array {
        $raw = get_post_meta( $analysis_id, '_pl_profile_scores', true );
        return is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
    }

    /**
     * Retourne les recommandations d'une analyse, triées par priorité décroissante.
     */
    public static function get_recommendations( int $analysis_id ): array {
        $raw  = get_post_meta( $analysis_id, '_pl_recommendations', true );
        $recs = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];

        usort( $recs, fn( $a, $b ) => ( $a['priority'] ?? 99 ) <=> ( $b['priority'] ?? 99 ) );

        return $recs;
    }

    // -------------------------------------------------------------------------
    // Projets
    // -------------------------------------------------------------------------

    /**
     * Crée un projet pl_project rattaché à un cours.
     */
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

    /**
     * Retourne les projets d'un cours, triés par date décroissante.
     */
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
    // Rendu front-end (shortcode délégué depuis pedagolens-landing)
    // -------------------------------------------------------------------------

    /**
     * Rendu HTML du dashboard enseignant pour le front-end (shortcode).
     * Appelé par PedagoLens_Landing via [pedagolens_teacher_dashboard].
     */
    public static function render_front(): string {
        if ( ! is_user_logged_in() ) {
            $login_url = esc_url( wp_login_url( get_permalink() ) );
            return "<div class=\"pl-notice pl-notice-warning\"><p>Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der au tableau de bord enseignant. <a href=\"{$login_url}\">Se connecter</a></p></div>";
        }

        $user       = wp_get_current_user();
        $is_teacher = in_array( 'pedagolens_teacher', (array) $user->roles, true )
                   || in_array( 'administrator',      (array) $user->roles, true );

        if ( ! $is_teacher ) {
            return '<div class="pl-notice pl-notice-error"><p>Acc&egrave;s r&eacute;serv&eacute; aux enseignants.</p></div>';
        }

        // Enqueue dashboard-specific assets on front-end
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

        $mode = get_option( 'pl_ai_mode', 'mock' );

        // --- Compute stats ---
        $total_courses  = count( $courses );
        $total_analyses = 0;
        $total_projects = 0;
        $all_scores     = [];

        $courses_data = [];
        foreach ( $courses as $course ) {
            $course_type = get_post_meta( $course->ID, '_pl_course_type', true ) ?: 'magistral';
            $projects    = self::get_projects( $course->ID );
            $total_projects += count( $projects );

            // Latest analysis
            $analysis = self::get_latest_analysis_front( $course->ID );
            if ( $analysis ) {
                $total_analyses++;
                foreach ( ( $analysis['profile_scores'] ?? [] ) as $s ) {
                    $all_scores[] = (int) $s;
                }
            }

            // Count all analyses for this course
            $analysis_count = self::count_analyses( $course->ID );
            $total_analyses = max( $total_analyses, $total_analyses ); // keep running total
            if ( $analysis_count > 1 ) {
                $total_analyses += ( $analysis_count - 1 );
            }

            $courses_data[] = [
                'post'        => $course,
                'type'        => $course_type,
                'projects'    => $projects,
                'analysis'    => $analysis,
            ];
        }

        // Reset total_analyses to actual count
        $total_analyses = self::count_all_analyses();
        $avg_score = ! empty( $all_scores ) ? (int) round( array_sum( $all_scores ) / count( $all_scores ) ) : 0;

        ob_start();
        ?>
        <div class="pl-front-dashboard pl-teacher-dashboard">

            <!-- ============ Header ============ -->
            <div class="pl-dashboard-header">
                <h2 class="pl-dashboard-title">
                    <span class="pl-icon">&#128202;</span>
                    Tableau de bord enseignant
                </h2>
                <div class="pl-header-actions">
                    <span class="pl-mode-badge pl-mode-badge--<?php echo esc_attr( $mode ); ?>">
                        <span class="pl-pulse-dot"></span>
                        <?php echo $mode === 'mock' ? 'Mode Mock' : 'AWS Bedrock'; ?>
                    </span>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pl_course' ) ); ?>" class="pl-btn-glow">
                        + Nouveau cours
                    </a>
                </div>
            </div>

            <!-- ============ Stats Overview ============ -->
            <div class="pl-stats-grid">
                <div class="pl-stat-card pl-animate-in" data-accent="courses">
                    <span class="pl-stat-icon">&#128218;</span>
                    <div class="pl-stat-number" data-target="<?php echo (int) $total_courses; ?>">0</div>
                    <div class="pl-stat-label">Cours</div>
                </div>
                <div class="pl-stat-card pl-animate-in" data-accent="analyses">
                    <span class="pl-stat-icon">&#128269;</span>
                    <div class="pl-stat-number" data-target="<?php echo (int) $total_analyses; ?>">0</div>
                    <div class="pl-stat-label">Analyses</div>
                </div>
                <div class="pl-stat-card pl-animate-in" data-accent="projects">
                    <span class="pl-stat-icon">&#128196;</span>
                    <div class="pl-stat-number" data-target="<?php echo (int) $total_projects; ?>">0</div>
                    <div class="pl-stat-label">Projets</div>
                </div>
                <div class="pl-stat-card pl-animate-in" data-accent="score">
                    <span class="pl-stat-icon">&#127942;</span>
                    <div class="pl-stat-number" data-target="<?php echo (int) $avg_score; ?>">0</div>
                    <div class="pl-stat-label">Score moyen</div>
                </div>
            </div>

            <!-- ============ Courses Grid ============ -->
            <?php if ( empty( $courses_data ) ) : ?>
                <div class="pl-notice pl-notice-warning">
                    <p>Aucun cours trouv&eacute;. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pl_course' ) ); ?>">Cr&eacute;er votre premier cours</a></p>
                </div>
            <?php else : ?>
                <h3 class="pl-section-title">
                    <span class="pl-section-icon">&#128218;</span>
                    Mes cours
                    <span class="pl-section-count"><?php echo (int) $total_courses; ?></span>
                </h3>
                <div class="pl-courses-grid">
                    <?php foreach ( $courses_data as $cd ) :
                        $course      = $cd['post'];
                        $course_type = $cd['type'];
                        $projects    = $cd['projects'];
                        $analysis    = $cd['analysis'];

                        $workbench_page = get_page_by_path( 'workbench' );
                        ?>
                        <div class="pl-course-card pl-animate-in">
                            <div class="pl-course-card-body">
                                <div class="pl-course-header">
                                    <h3><?php echo esc_html( $course->post_title ); ?></h3>
                                    <span class="pl-badge pl-type-<?php echo esc_attr( $course_type ); ?>">
                                        <?php echo esc_html( $course_type ); ?>
                                    </span>
                                </div>
                                <div class="pl-course-meta">
                                    <span>&#128197; <?php echo esc_html( get_the_date( 'j M Y', $course ) ); ?></span>
                                    <span>&#128196; <?php echo count( $projects ); ?> projet(s)</span>
                                </div>
                                <div class="pl-course-actions">
                                    <button class="pl-btn-glow pl-btn-sm pl-btn-analyze-front"
                                        data-course-id="<?php echo (int) $course->ID; ?>">
                                        &#128269; Analyser
                                    </button>
                                    <button class="pl-btn-ghost pl-btn-sm pl-btn-create-project"
                                        data-course-id="<?php echo (int) $course->ID; ?>"
                                        data-course-title="<?php echo esc_attr( $course->post_title ); ?>">
                                        + Projet
                                    </button>
                                </div>
                            </div>

                            <!-- Analysis result zone -->
                            <div id="pl-analysis-result-<?php echo (int) $course->ID; ?>" class="pl-analysis-front-result">
                                <?php if ( $analysis ) : ?>
                                    <?php PedagoLens_Dashboard_Admin::render_analysis_result( $analysis ); ?>
                                <?php endif; ?>
                            </div>

                            <!-- Projects list -->
                            <?php if ( ! empty( $projects ) ) : ?>
                                <div class="pl-projects-section">
                                    <h4>&#128196; Projets</h4>
                                    <div class="pl-project-list">
                                        <?php foreach ( $projects as $project ) :
                                            $wb_url = $workbench_page
                                                ? get_permalink( $workbench_page ) . '?project_id=' . $project['id']
                                                : admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $project['id'] );
                                            ?>
                                            <div class="pl-project-row">
                                                <span class="pl-project-title"><?php echo esc_html( $project['title'] ); ?></span>
                                                <span class="pl-badge pl-type-<?php echo esc_attr( $project['type'] ); ?>"><?php echo esc_html( $project['type'] ); ?></span>
                                                <a href="<?php echo esc_url( $wb_url ); ?>" class="pl-project-link">Ouvrir &#8594;</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

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
        ];
    }

    /**
     * Compte les analyses d'un cours.
     */
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

    /**
     * Compte toutes les analyses.
     */
    private static function count_all_analyses(): int {
        $q = new WP_Query( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        return $q->found_posts;
    }

    /**
     * Retourne les profils actifs via Profile_Manager si disponible.
     */
    public static function get_active_profiles(): array {
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            return PedagoLens_Profile_Manager::get_all( active_only: true );
        }
        return [];
    }

    /**
     * S'assure que profile_scores contient exactement les slugs des profils actifs.
     */
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
