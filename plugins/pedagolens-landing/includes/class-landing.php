<?php
/**
 * PedagoLens_Landing
 *
 * Shortcodes front-end pour toutes les pages publiques PédagoLens.
 * - Landing page    : [pedagolens_landing]
 * - Dashboard prof  : [pedagolens_teacher_dashboard]
 * - Dashboard étud  : [pedagolens_student_dashboard]
 * - Cours & Projets : [pedagolens_courses]
 * - Workbench       : [pedagolens_workbench]
 * - Compte          : [pedagolens_account]
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Landing {

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init(): void {
        PedagoLens_Landing_Admin::register();

        add_shortcode( 'pedagolens_landing',           [ self::class, 'shortcode_landing' ] );
        add_shortcode( 'pedagolens_teacher_dashboard', [ self::class, 'shortcode_teacher_dashboard' ] );
        add_shortcode( 'pedagolens_student_dashboard', [ self::class, 'shortcode_student_dashboard' ] );
        add_shortcode( 'pedagolens_courses',           [ self::class, 'shortcode_courses' ] );
        add_shortcode( 'pedagolens_workbench',         [ self::class, 'shortcode_workbench' ] );
        add_shortcode( 'pedagolens_account',           [ self::class, 'shortcode_account' ] );

        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_front_assets' ] );

        // AJAX front-end pour workbench (enseignants connectés)
        if ( ! has_action( 'wp_ajax_pl_get_suggestions' ) ) {
            add_action( 'wp_ajax_pl_get_suggestions',   [ 'PedagoLens_Workbench_Admin', 'ajax_get_suggestions' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_apply_suggestion' ) ) {
            add_action( 'wp_ajax_pl_apply_suggestion',  [ 'PedagoLens_Workbench_Admin', 'ajax_apply_suggestion' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_reject_suggestion' ) ) {
            add_action( 'wp_ajax_pl_reject_suggestion', [ 'PedagoLens_Workbench_Admin', 'ajax_reject_suggestion' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_save_section' ) ) {
            add_action( 'wp_ajax_pl_save_section',      [ 'PedagoLens_Workbench_Admin', 'ajax_save_section' ] );
        }
        // AJAX front-end pour sauvegarde profil compte
        if ( ! has_action( 'wp_ajax_pl_save_account_profile' ) ) {
            add_action( 'wp_ajax_pl_save_account_profile', [ self::class, 'ajax_save_account_profile' ] );
        }

        // AJAX front-end pour sauvegarde difficultés étudiant
        if ( ! has_action( 'wp_ajax_pl_save_student_difficulties' ) ) {
            add_action( 'wp_ajax_pl_save_student_difficulties', [ self::class, 'ajax_save_student_difficulties' ] );
        }

        // AJAX front-end pour teacher dashboard
        if ( ! has_action( 'wp_ajax_pl_analyze_course' ) ) {
            add_action( 'wp_ajax_pl_analyze_course',    [ 'PedagoLens_Dashboard_Admin', 'ajax_analyze' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_create_project' ) ) {
            add_action( 'wp_ajax_pl_create_project',    [ 'PedagoLens_Dashboard_Admin', 'ajax_create_project' ] );
        }
    }

    // -------------------------------------------------------------------------
    // Assets front-end
    // -------------------------------------------------------------------------

    public static function enqueue_front_assets(): void {
        wp_enqueue_style(
            'pl-landing',
            PL_LANDING_PLUGIN_URL . 'assets/css/landing.css',
            [],
            PL_LANDING_VERSION
        );

        // Toujours charger le JS front (animations landing + AJAX dashboards)
        wp_enqueue_script(
            'pl-landing-front',
            PL_LANDING_PLUGIN_URL . 'assets/js/landing-front.js',
            [],
            PL_LANDING_VERSION,
            true
        );

        $has_dashboard = class_exists( 'PedagoLens_Dashboard_Admin' );
        $has_twin      = class_exists( 'PedagoLens_Twin_Admin' );
        $has_workbench = class_exists( 'PedagoLens_Workbench_Admin' );

        wp_localize_script( 'pl-landing-front', 'plFront', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonces'  => [
                'dashboard' => $has_dashboard ? wp_create_nonce( 'pl_dashboard_ajax' ) : '',
                'twin'      => $has_twin      ? wp_create_nonce( 'pl_twin_ajax' )      : '',
                'workbench' => $has_workbench ? wp_create_nonce( 'pl_workbench_ajax' ) : '',
            ],
            'i18n' => [
                'analyzing'    => 'Analyse en cours…',
                'analyzeError' => 'Erreur lors de l\'analyse.',
                'sending'      => 'Envoi…',
                'saving'       => 'Enregistrement…',
                'sessionEnded' => 'Session terminée. À bientôt !',
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // [pedagolens_landing] — Page d'accueil marketing
    // -------------------------------------------------------------------------

    public static function shortcode_landing( array $atts ): string {
        $s        = self::get_settings();
        $cta_url  = esc_url( $s['cta_url'] ?? '#demo' );

        // ── Badges profils ──
        $profiles_html = '';
        $default_profiles = [
            'TDAH',
            'Surcharge cognitive',
            'Langue seconde',
            'Faible autonomie',
            'Anxi&eacute;t&eacute; aux consignes',
            "Trouble d'apprentissage",
            'D&eacute;crochage potentiel',
        ];

        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $db_profiles = PedagoLens_Profile_Manager::get_all( active_only: true );
            if ( ! empty( $db_profiles ) ) {
                $i = 0;
                foreach ( $db_profiles as $p ) {
                    $name           = esc_html( $p['name'] ?? $p['slug'] );
                    $delay          = $i++;
                    $profiles_html .= '<span class="pl-hero-profile-badge pl-animate-in pl-delay-' . esc_attr( $delay ) . '">' . $name . '</span>';
                }
            }
        }

        if ( '' === $profiles_html ) {
            $i = 0;
            foreach ( $default_profiles as $pname ) {
                $delay          = $i++;
                $profiles_html .= '<span class="pl-hero-profile-badge pl-animate-in pl-delay-' . esc_attr( $delay ) . '">' . $pname . '</span>';
            }
        }

        // ── Navigation links ──
        $nav_links = self::get_nav_links();
        $nav_html  = '';
        foreach ( $nav_links as $label => $url ) {
            $nav_html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
        }

        // ── Features ──
        $features = [
            [ 'icon' => '&#128269;', 'title' => 'Analyse p&eacute;dagogique IA',    'desc' => 'Analysez vos cours selon 7 profils d&rsquo;apprenants en quelques secondes gr&acirc;ce &agrave; AWS Bedrock.' ],
            [ 'icon' => '&#9999;',   'title' => 'Atelier de cours (Workbench)',       'desc' => 'Recevez des suggestions concr&egrave;tes et appliquez-les en un clic pour am&eacute;liorer l&rsquo;accessibilit&eacute;.' ],
            [ 'icon' => '&#129302;', 'title' => 'Jumeau num&eacute;rique &eacute;tudiant', 'desc' => 'Simulez l&rsquo;exp&eacute;rience d&rsquo;un &eacute;tudiant avec des garde-fous p&eacute;dagogiques int&eacute;gr&eacute;s.' ],
            [ 'icon' => '&#128202;', 'title' => 'Tableau de bord enseignant',        'desc' => 'Visualisez les scores par profil, suivez l&rsquo;&eacute;volution et priorisez vos am&eacute;liorations.' ],
        ];

        $features_html = '';
        $fi = 0;
        foreach ( $features as $f ) {
            $delay          = $fi++;
            $features_html .= '<div class="pl-feature-card pl-animate-in pl-delay-' . esc_attr( $delay ) . '">'
                . '<span class="pl-feature-icon">' . $f['icon'] . '</span>'
                . '<h3>' . $f['title'] . '</h3>'
                . '<p>' . $f['desc'] . '</p>'
                . '</div>';
        }

        // ── Steps ──
        $steps = [
            [ 'title' => 'Analyse du cours par profils',          'desc' => 'L&rsquo;IA examine votre contenu &agrave; travers le prisme de chaque profil d&rsquo;apprenant.' ],
            [ 'title' => 'Score /100 par profil',                 'desc' => 'Chaque profil re&ccedil;oit un score d&rsquo;accessibilit&eacute; clair et actionnable.' ],
            [ 'title' => 'Propositions d&rsquo;am&eacute;lioration', 'desc' => 'Des suggestions concr&egrave;tes, contextualis&eacute;es et applicables en un clic.' ],
            [ 'title' => 'Mesure de l&rsquo;impact',              'desc' => 'Visualisez l&rsquo;effet de chaque modification sur les scores avant de l&rsquo;appliquer.' ],
            [ 'title' => 'Optimisation continue',                 'desc' => 'It&eacute;rez jusqu&rsquo;&agrave; atteindre l&rsquo;accessibilit&eacute; optimale pour tous les profils.' ],
        ];

        $steps_html = '';
        $si = 0;
        foreach ( $steps as $step ) {
            $si++;
            $steps_html .= '<div class="pl-step pl-animate-in">'
                . '<div class="pl-step-number">' . esc_html( $si ) . '</div>'
                . '<div class="pl-step-content">'
                . '<h3>' . $step['title'] . '</h3>'
                . '<p>' . $step['desc'] . '</p>'
                . '</div></div>';
        }

        // ── Score demo bars ──
        $demo_scores = [
            'TDAH'                     => 42,
            'Surcharge cognitive'      => 58,
            'Langue seconde'           => 35,
            'Faible autonomie'         => 67,
            'Anxi&eacute;t&eacute;'   => 51,
            'Trouble d&rsquo;apprentissage' => 44,
            'D&eacute;crochage'        => 73,
        ];

        $scores_html = '';
        foreach ( $demo_scores as $label => $score ) {
            $scores_html .= '<div class="pl-score-row">'
                . '<span class="pl-score-label">' . $label . '</span>'
                . '<div class="pl-score-bar-wrap">'
                . '<div class="pl-score-bar" style="--score-width:' . esc_attr( $score ) . '%;"></div>'
                . '</div>'
                . '<span class="pl-score-value">' . esc_html( $score ) . '/100</span>'
                . '</div>';
        }

        // ── Footer nav ──
        $footer_nav_html = '';
        foreach ( $nav_links as $label => $url ) {
            $footer_nav_html .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
        }

        // ── BUILD HTML ──
        ob_start();
        ?>
<div class="pl-landing-page">

    <!-- ========== NAV ========== -->
    <nav class="pl-landing-nav" role="navigation" aria-label="Navigation principale">
        <div class="pl-landing-nav-inner">
            <a href="#pl-hero" class="pl-nav-logo">P&eacute;dagoLens</a>
            <ul class="pl-nav-links">
                <?php echo $nav_html; ?>
            </ul>
        </div>
    </nav>

    <!-- ========== HERO ========== -->
    <section class="pl-hero" id="pl-hero">
        <div class="pl-hero-orb pl-hero-orb--cyan"></div>
        <div class="pl-hero-orb pl-hero-orb--accent"></div>
        <div class="pl-hero-orb pl-hero-orb--small"></div>
        <div class="pl-hero-inner">
            <div class="pl-hero-badge">&#10024; Propuls&eacute; par AWS Bedrock</div>
            <h1 class="pl-hero-title">P&eacute;dagoLens</h1>
            <p class="pl-hero-subtitle">L&rsquo;IA p&eacute;dagogique qui transforme vos cours pour chaque profil d&rsquo;&eacute;tudiant</p>
            <div class="pl-hero-profiles">
                <span class="pl-hero-profiles-label">7 profils d&rsquo;apprenants analys&eacute;s :</span>
                <div class="pl-hero-profiles-badges">
                    <?php echo $profiles_html; ?>
                </div>
            </div>
            <div class="pl-hero-cta-group">
                <div class="pl-hero-cta-buttons">
                    <a href="<?php echo $cta_url; ?>" class="pl-btn-cta">&#127891; D&eacute;couvrir la d&eacute;mo</a>
                    <a href="#pl-how" class="pl-btn-cta-outline">En savoir plus &#8595;</a>
                </div>
                <span class="pl-hero-note">Mode d&eacute;mo disponible &mdash; aucun compte requis</span>
            </div>
        </div>
    </section>

    <!-- ========== PROBLEM ========== -->
    <section class="pl-problem" id="pl-problem">
        <div class="pl-section-inner">
            <div class="pl-section-header pl-animate-in">
                <span class="pl-section-tag">&#9888;&#65039; Le probl&egrave;me</span>
                <h2 class="pl-section-title">Un cours, des dizaines de r&eacute;alit&eacute;s</h2>
                <p class="pl-section-subtitle">Les cours sont con&ccedil;us pour un &eacute;tudiant moyen. Pourtant, les classes sont compos&eacute;es de profils tr&egrave;s vari&eacute;s.</p>
            </div>
            <div class="pl-problem-content">
                <div class="pl-problem-text pl-animate-in">
                    <p><strong>TDAH, surcharge cognitive, langue seconde, faible autonomie, anxi&eacute;t&eacute; face aux consignes, troubles d&rsquo;apprentissage&hellip;</strong></p>
                    <p>Chaque profil a des besoins sp&eacute;cifiques que les enseignants n&rsquo;ont ni le temps ni les outils pour adresser individuellement. R&eacute;sultat : des &eacute;tudiants qui d&eacute;crochent, non pas par manque de capacit&eacute;, mais par manque d&rsquo;adaptation du contenu.</p>
                    <p>P&eacute;dagoLens analyse automatiquement vos cours et propose des am&eacute;liorations cibl&eacute;es pour chaque profil.</p>
                </div>
                <div class="pl-problem-stats pl-animate-in">
                    <div class="pl-stat-card">
                        <span class="pl-stat-number" data-count-to="7">0</span>
                        <span class="pl-stat-label">Profils analys&eacute;s</span>
                    </div>
                    <div class="pl-stat-card">
                        <span class="pl-stat-number" data-count-to="100" data-count-suffix="/100">0</span>
                        <span class="pl-stat-label">Score par profil</span>
                    </div>
                    <div class="pl-stat-card">
                        <span class="pl-stat-number" data-count-to="30" data-count-suffix="s">0</span>
                        <span class="pl-stat-label">Temps d&rsquo;analyse</span>
                    </div>
                    <div class="pl-stat-card">
                        <span class="pl-stat-number" data-count-to="1">0</span>
                        <span class="pl-stat-label">Clic pour am&eacute;liorer</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== HOW IT WORKS ========== -->
    <section class="pl-how" id="pl-how">
        <div class="pl-section-inner">
            <div class="pl-section-header pl-animate-in">
                <span class="pl-section-tag">&#9881;&#65039; Comment &ccedil;a marche</span>
                <h2 class="pl-section-title">5 &eacute;tapes vers l&rsquo;accessibilit&eacute;</h2>
                <p class="pl-section-subtitle">De l&rsquo;analyse &agrave; l&rsquo;optimisation, un processus fluide et guid&eacute; par l&rsquo;IA.</p>
            </div>
            <div class="pl-steps">
                <?php echo $steps_html; ?>
            </div>
            <div class="pl-score-bars pl-animate-in" style="margin-top:48px;">
                <h3 class="pl-score-section-title">Exemple de scores par profil</h3>
                <?php echo $scores_html; ?>
            </div>
        </div>
    </section>

    <!-- ========== FEATURES ========== -->
    <section class="pl-features" id="pl-features">
        <div class="pl-section-inner">
            <div class="pl-section-header pl-animate-in">
                <span class="pl-section-tag">&#128640; Fonctionnalit&eacute;s</span>
                <h2 class="pl-section-title">Tout ce dont vous avez besoin</h2>
                <p class="pl-section-subtitle">Une suite compl&egrave;te d&rsquo;outils pour transformer votre p&eacute;dagogie.</p>
            </div>
            <div class="pl-features-grid">
                <?php echo $features_html; ?>
            </div>
        </div>
    </section>

    <!-- ========== PHASE 2 ========== -->
    <section class="pl-phase2" id="pl-phase2">
        <div class="pl-section-inner">
            <div class="pl-section-header pl-animate-in">
                <span class="pl-phase2-tag">&#128302; Phase 2</span>
                <h2 class="pl-section-title">Accompagnement &eacute;tudiant</h2>
                <p class="pl-section-subtitle">L&rsquo;&eacute;tape suivante : un compagnon IA qui guide chaque &eacute;tudiant sans faire le travail &agrave; sa place.</p>
            </div>
            <div class="pl-phase2-grid">
                <div class="pl-phase2-card pl-animate-in">
                    <span class="pl-phase2-icon">&#129302;</span>
                    <h3>Jumeau num&eacute;rique &eacute;tudiant</h3>
                    <p>Un assistant IA personnalis&eacute; qui conna&icirc;t le profil de l&rsquo;&eacute;tudiant et l&rsquo;accompagne dans sa compr&eacute;hension du cours &mdash; avec des garde-fous p&eacute;dagogiques pour guider sans donner les r&eacute;ponses.</p>
                </div>
                <div class="pl-phase2-card pl-animate-in pl-delay-1">
                    <span class="pl-phase2-icon">&#128202;</span>
                    <h3>Tableau de bord des incompr&eacute;hensions</h3>
                    <p>Les enseignants acc&egrave;dent &agrave; un tableau de bord agr&eacute;g&eacute; des questions fr&eacute;quentes et des zones d&rsquo;incompr&eacute;hension, permettant d&rsquo;ajuster le cours en temps r&eacute;el.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer class="pl-landing-footer" id="pl-footer">
        <div class="pl-footer-inner">
            <span class="pl-footer-logo">P&eacute;dagoLens</span>
            <ul class="pl-footer-nav">
                <?php echo $footer_nav_html; ?>
            </ul>
            <p class="pl-footer-copy">&copy; 2026 P&eacute;dagoLens &mdash; Propuls&eacute; par AWS Bedrock</p>
        </div>
    </footer>

</div><!-- .pl-landing-page -->
        <?php
        return ob_get_clean();
    }

    /**
     * Navigation links used in header and footer.
     */
    private static function get_nav_links(): array {
        $home_url      = esc_url( home_url( '/' ) );
        $dashboard_url = esc_url( self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' ) );
        $courses_url   = esc_url( self::page_url( 'cours-projets', 'pl-course-workbench' ) );
        $twin_url      = esc_url( self::page_url( 'dashboard-etudiant', '' ) );
        $account_url   = esc_url( self::page_url( 'compte', '' ) );

        return [
            'Accueil'   => $home_url,
            'Dashboard' => $dashboard_url,
            'Cours'     => $courses_url,
            'Jumeau'    => $twin_url,
            'Compte'    => $account_url,
        ];
    }

    /**
     * Resolve a front page URL by slug, fallback to admin page.
     */
    private static function page_url( string $slug, string $admin_page ): string {
        $page = get_page_by_path( $slug );
        if ( $page ) {
            return get_permalink( $page );
        }
        if ( $admin_page ) {
            return admin_url( 'admin.php?page=' . $admin_page );
        }
        return home_url( '/' );
    }

    // -------------------------------------------------------------------------
    // [pedagolens_teacher_dashboard] — Dashboard enseignant front-end
    // Délègue au plugin pedagolens-teacher-dashboard
    // -------------------------------------------------------------------------

    public static function shortcode_teacher_dashboard( array $atts ): string {
        if ( ! class_exists( 'PedagoLens_Teacher_Dashboard' ) ) {
            return '<div class="pl-notice pl-notice-error"><p>Le plugin Teacher Dashboard n\'est pas activ&eacute;.</p></div>';
        }

        return PedagoLens_Teacher_Dashboard::render_front();
    }

    // -------------------------------------------------------------------------
    // [pedagolens_student_dashboard] — Dashboard étudiant (jumeau numérique)
    // Délègue au plugin pedagolens-student-twin
    // -------------------------------------------------------------------------

    public static function shortcode_student_dashboard( array $atts ): string {
        $atts = shortcode_atts( [ 'course_id' => 0 ], $atts );

        if ( ! class_exists( 'PedagoLens_Twin_Admin' ) ) {
            return '<div class="pl-notice pl-notice-error"><p>Le plugin Student Twin n\'est pas activ&eacute;.</p></div>';
        }

        // S'assurer que le widget twin est enqueué avec le bon nonce front-end
        wp_localize_script( 'pl-landing-front', 'plTwin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pl_twin_ajax' ),
        ] );

        return PedagoLens_Twin_Admin::render_shortcode( $atts );
    }

    // -------------------------------------------------------------------------
    // [pedagolens_courses] — Liste des cours et projets
    // -------------------------------------------------------------------------

    public static function shortcode_courses( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; vos cours.' );
        }

        $course_id = (int) ( $_GET['course_id'] ?? 0 );

        $courses = get_posts( [
            'post_type'      => 'pl_course',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        ob_start();
        ?>
        <div class="pl-front-courses">
            <div class="pl-page-header">
                <h2>Cours &amp; Projets</h2>
            </div>

            <?php if ( empty( $courses ) ) : ?>
                <div class="pl-notice pl-notice-warning">
                    <p>Aucun cours disponible.</p>
                </div>
            <?php else : ?>
                <div class="pl-courses-list">
                    <?php foreach ( $courses as $course ) :
                        $course_type = get_post_meta( $course->ID, '_pl_course_type', true ) ?: 'magistral';
                        $projects    = class_exists( 'PedagoLens_Teacher_Dashboard' )
                            ? PedagoLens_Teacher_Dashboard::get_projects( $course->ID )
                            : [];
                        $is_open     = $course_id === $course->ID;
                        ?>
                        <div class="pl-course-item <?php echo $is_open ? 'pl-course-open' : ''; ?>">
                            <div class="pl-course-row">
                                <div class="pl-course-info">
                                    <h3><?php echo esc_html( $course->post_title ); ?></h3>
                                    <span class="pl-badge pl-type-<?php echo esc_attr( $course_type ); ?>">
                                        <?php echo esc_html( $course_type ); ?>
                                    </span>
                                </div>
                                <a href="?course_id=<?php echo (int) $course->ID; ?>" class="pl-btn pl-btn-sm">
                                    <?php echo $is_open ? 'Fermer' : 'Voir les projets'; ?>
                                </a>
                            </div>

                            <?php if ( $is_open ) : ?>
                                <div class="pl-projects-panel">
                                    <?php if ( empty( $projects ) ) : ?>
                                        <p class="pl-empty">Aucun projet pour ce cours.</p>
                                    <?php else : ?>
                                        <div class="pl-projects-grid">
                                            <?php foreach ( $projects as $project ) :
                                                $workbench_page = get_page_by_path( 'workbench' );
                                                $workbench_url  = $workbench_page
                                                    ? get_permalink( $workbench_page ) . '?project_id=' . $project['id']
                                                    : admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $project['id'] );
                                                ?>
                                                <div class="pl-project-card">
                                                    <h4><?php echo esc_html( $project['title'] ); ?></h4>
                                                    <span class="pl-badge"><?php echo esc_html( $project['type'] ); ?></span>
                                                    <a href="<?php echo esc_url( $workbench_url ); ?>" class="pl-btn pl-btn-primary pl-btn-sm">
                                                        Ouvrir le Workbench
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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

    // -------------------------------------------------------------------------
    // [pedagolens_workbench] — Atelier d'édition de cours avec conseils IA
    // -------------------------------------------------------------------------

    public static function shortcode_workbench( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der au workbench.' );
        }

        $project_id = (int) ( $_GET['project_id'] ?? 0 );

        if ( ! $project_id ) {
            return '<div class="pl-notice pl-notice-warning"><p>Aucun projet s&eacute;lectionn&eacute;. <a href="' . esc_url( get_permalink( get_page_by_path( 'cours-projets' ) ) ) . '">Retour aux cours</a></p></div>';
        }

        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== 'pl_project' ) {
            return '<div class="pl-notice pl-notice-error"><p>Projet introuvable.</p></div>';
        }

        // Rediriger vers l'admin workbench pour l'édition complète
        $admin_url = admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $project_id );

        $project_title = esc_html( $project->post_title );
        $project_type  = esc_html( get_post_meta( $project_id, '_pl_project_type', true ) ?: 'magistral' );

        $sections = class_exists( 'PedagoLens_Course_Workbench' )
            ? PedagoLens_Course_Workbench::get_content_sections( $project_id )
            : [];

        $raw_scores = get_post_meta( $project_id, '_pl_profile_scores', true );
        $scores     = is_string( $raw_scores ) ? (array) json_decode( $raw_scores, true ) : [];

        ob_start();
        ?>
        <div class="pl-front-workbench">
            <div class="pl-workbench-header">
                <div>
                    <h2><?php echo $project_title; ?></h2>
                    <span class="pl-badge pl-type-<?php echo esc_attr( $project_type ); ?>"><?php echo $project_type; ?></span>
                </div>
                <a href="<?php echo esc_url( $admin_url ); ?>" class="pl-btn pl-btn-primary">
                    &#9998; &Eacute;diter dans l'atelier complet
                </a>
            </div>

            <?php if ( ! empty( $scores ) ) : ?>
                <div class="pl-scores-summary">
                    <h3>Scores par profil</h3>
                    <div class="pl-scores-bars">
                        <?php foreach ( $scores as $slug => $score ) :
                            $score = max( 0, min( 100, (int) $score ) );
                            $color = self::score_color( $score );
                            ?>
                            <div class="pl-score-row">
                                <span class="pl-score-label"><?php echo esc_html( $slug ); ?></span>
                                <div class="pl-score-bar-wrap">
                                    <div class="pl-score-bar" style="width:<?php echo $score; ?>%;background:<?php echo esc_attr( $color ); ?>;"></div>
                                </div>
                                <span class="pl-score-value"><?php echo $score; ?>/100</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( empty( $sections ) ) : ?>
                <div class="pl-notice pl-notice-info">
                    <p>Ce projet n'a pas encore de sections. <a href="<?php echo esc_url( $admin_url ); ?>">Ajouter du contenu</a></p>
                </div>
            <?php else : ?>
                <div class="pl-sections-preview">
                    <h3>Sections (<?php echo count( $sections ); ?>)</h3>
                    <?php foreach ( $sections as $section ) : ?>
                        <div class="pl-section-preview-card pl-animate-in">
                            <h4><?php echo esc_html( $section['title'] ?? 'Section' ); ?></h4>
                            <p class="pl-section-excerpt">
                                <?php echo esc_html( mb_substr( $section['content'] ?? '', 0, 200 ) ); ?>
                                <?php if ( mb_strlen( $section['content'] ?? '' ) > 200 ) echo '&hellip;'; ?>
                            </p>
                            <button class="pl-btn pl-btn-sm pl-btn-suggestions-front"
                                data-project-id="<?php echo (int) $project_id; ?>"
                                data-section-id="<?php echo esc_attr( $section['id'] ?? '' ); ?>">
                                Suggestions IA
                            </button>
                            <div class="pl-suggestions-zone"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // [pedagolens_account] — Page de compte utilisateur
    // -------------------------------------------------------------------------

    public static function shortcode_account( array $atts ): string {

        // -----------------------------------------------------------------
        // Visiteur non connecté → formulaire de connexion stylé
        // -----------------------------------------------------------------
        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() );
            ob_start();
            ?>
            <div class="pl-account-page">
                <div class="pl-account-login-card pl-glass-card pl-animate-in">
                    <div class="pl-login-icon">&#128274;</div>
                    <h2>Connexion &agrave; PédagoLens</h2>
                    <p class="pl-text-muted">Connectez-vous pour acc&eacute;der &agrave; votre espace.</p>
                    <?php wp_login_form( [
                        'redirect'       => get_permalink(),
                        'label_username' => 'Identifiant ou courriel',
                        'label_password' => 'Mot de passe',
                        'label_remember' => 'Se souvenir de moi',
                        'label_log_in'   => 'Se connecter',
                    ] ); ?>
                    <?php if ( get_option( 'users_can_register' ) ) : ?>
                        <p class="pl-login-register">Pas encore de compte ?
                            <a href="<?php echo esc_url( wp_registration_url() ); ?>">Cr&eacute;er un compte</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // -----------------------------------------------------------------
        // Utilisateur connecté
        // -----------------------------------------------------------------
        $user       = wp_get_current_user();
        $roles      = (array) $user->roles;
        $is_admin   = in_array( 'administrator',      $roles, true );
        $is_teacher = in_array( 'pedagolens_teacher', $roles, true );
        $is_student = in_array( 'pedagolens_student', $roles, true );

        $role_label = match ( true ) {
            $is_admin   => 'Administrateur',
            $is_teacher => 'Enseignant',
            $is_student => '&Eacute;tudiant',
            default     => 'Utilisateur',
        };

        $role_icon = match ( true ) {
            $is_admin   => '&#128081;',
            $is_teacher => '&#128218;',
            $is_student => '&#127891;',
            default     => '&#128100;',
        };

        $role_class = match ( true ) {
            $is_admin   => 'pl-role-admin',
            $is_teacher => 'pl-role-teacher',
            $is_student => 'pl-role-student',
            default     => 'pl-role-default',
        };

        $avatar_url = esc_url( get_avatar_url( $user->ID, [ 'size' => 120 ] ) );
        $logout_url = esc_url( wp_logout_url( home_url() ) );
        $profile_nonce = wp_create_nonce( 'pl_account_profile' );
        $diff_nonce    = wp_create_nonce( 'pl_student_difficulties' );

        ob_start();
        ?>
        <div class="pl-account-page">

            <!-- ============ PROFIL CARD ============ -->
            <div class="pl-account-profile-card pl-glass-card pl-animate-in">
                <div class="pl-account-avatar-wrap">
                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="pl-account-avatar-img" />
                </div>
                <h2 class="pl-account-name"><?php echo esc_html( $user->display_name ); ?></h2>
                <span class="pl-account-role-badge <?php echo $role_class; ?>"><?php echo $role_icon . ' ' . $role_label; ?></span>
                <p class="pl-account-email-display"><?php echo esc_html( $user->user_email ); ?></p>
                <a href="<?php echo $logout_url; ?>" class="pl-btn pl-btn-outline pl-btn-sm">D&eacute;connexion</a>
            </div>

            <!-- ============ MODIFIER MON PROFIL ============ -->
            <div class="pl-account-section pl-glass-card pl-animate-in">
                <h3>&#9998; Modifier mon profil</h3>
                <div id="pl-profile-msg" class="pl-account-msg" style="display:none;"></div>
                <form id="pl-profile-form" class="pl-account-form" autocomplete="off">
                    <input type="hidden" name="_wpnonce" value="<?php echo $profile_nonce; ?>" />
                    <div class="pl-form-group">
                        <label for="pl-display-name">Nom d'affichage</label>
                        <input type="text" id="pl-display-name" name="display_name"
                               value="<?php echo esc_attr( $user->display_name ); ?>" required />
                    </div>
                    <div class="pl-form-group">
                        <label for="pl-email">Courriel</label>
                        <input type="email" id="pl-email" name="email"
                               value="<?php echo esc_attr( $user->user_email ); ?>" required />
                    </div>
                    <div class="pl-form-group">
                        <label for="pl-password">Nouveau mot de passe <small>(laisser vide pour ne pas changer)</small></label>
                        <input type="password" id="pl-password" name="password" autocomplete="new-password" />
                    </div>
                    <button type="submit" class="pl-btn pl-btn-primary">Enregistrer</button>
                </form>
            </div>

            <?php
            // =================================================================
            // ENSEIGNANT / ADMIN — Préférences + Stats + Liens rapides
            // =================================================================
            if ( $is_admin || $is_teacher ) :
                // Stats rapides
                $nb_courses  = wp_count_posts( 'pl_course' )->publish ?? 0;
                $nb_projects = wp_count_posts( 'pl_project' )->publish ?? 0;
                $nb_analyses = (int) get_user_meta( $user->ID, '_pl_analysis_count', true );

                // Liens
                $teacher_page  = get_page_by_path( 'dashboard-enseignant' );
                $courses_page  = get_page_by_path( 'cours-projets' );
                $workbench_page = get_page_by_path( 'workbench' );
                $teacher_url   = $teacher_page  ? get_permalink( $teacher_page )  : admin_url( 'admin.php?page=pl-teacher-dashboard' );
                $courses_url   = $courses_page  ? get_permalink( $courses_page )  : admin_url( 'admin.php?page=pl-course-workbench' );
                $workbench_url = $workbench_page ? get_permalink( $workbench_page ) : admin_url( 'admin.php?page=pl-course-workbench' );
            ?>

                <!-- Stats rapides -->
                <div class="pl-account-section pl-glass-card pl-animate-in">
                    <h3>&#128202; Statistiques rapides</h3>
                    <div class="pl-account-stats-grid">
                        <div class="pl-stat-card">
                            <span class="pl-stat-number"><?php echo (int) $nb_courses; ?></span>
                            <span class="pl-stat-label">Cours</span>
                        </div>
                        <div class="pl-stat-card">
                            <span class="pl-stat-number"><?php echo (int) $nb_analyses; ?></span>
                            <span class="pl-stat-label">Analyses</span>
                        </div>
                        <div class="pl-stat-card">
                            <span class="pl-stat-number"><?php echo (int) $nb_projects; ?></span>
                            <span class="pl-stat-label">Projets</span>
                        </div>
                    </div>
                </div>

                <!-- Préférences -->
                <div class="pl-account-section pl-glass-card pl-animate-in">
                    <h3>&#9881; Mes pr&eacute;f&eacute;rences</h3>
                    <?php
                    $prefs = (array) get_user_meta( $user->ID, 'pl_teacher_prefs', true );
                    $dark  = ! empty( $prefs['dark_mode'] );
                    $notif = ! empty( $prefs['notifications'] );
                    ?>
                    <div class="pl-prefs-grid">
                        <label class="pl-toggle-row">
                            <span>&#127769; Th&egrave;me sombre</span>
                            <input type="checkbox" class="pl-toggle-input" data-pref="dark_mode" <?php checked( $dark ); ?> />
                            <span class="pl-toggle-slider"></span>
                        </label>
                        <label class="pl-toggle-row">
                            <span>&#128276; Notifications</span>
                            <input type="checkbox" class="pl-toggle-input" data-pref="notifications" <?php checked( $notif ); ?> />
                            <span class="pl-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Liens rapides -->
                <div class="pl-account-section pl-glass-card pl-animate-in">
                    <h3>&#128279; Liens rapides</h3>
                    <div class="pl-quick-links">
                        <a href="<?php echo esc_url( $teacher_url ); ?>" class="pl-quick-link-card">
                            <span class="pl-ql-icon">&#128202;</span>
                            <span>Dashboard</span>
                        </a>
                        <a href="<?php echo esc_url( $courses_url ); ?>" class="pl-quick-link-card">
                            <span class="pl-ql-icon">&#128218;</span>
                            <span>Cours</span>
                        </a>
                        <a href="<?php echo esc_url( $workbench_url ); ?>" class="pl-quick-link-card">
                            <span class="pl-ql-icon">&#9999;</span>
                            <span>Workbench</span>
                        </a>
                    </div>
                </div>

            <?php
            // =================================================================
            // ÉTUDIANT — Difficultés / troubles + Liens rapides
            // =================================================================
            elseif ( $is_student ) :
                $raw_diff     = get_user_meta( $user->ID, 'pl_student_difficulties', true );
                $difficulties = is_array( $raw_diff ) ? $raw_diff : ( is_string( $raw_diff ) && $raw_diff ? json_decode( $raw_diff, true ) : [] );
                if ( ! is_array( $difficulties ) ) $difficulties = [];

                $difficulty_options = [
                    'tdah'              => 'TDAH / Difficult&eacute;s de concentration',
                    'surcharge'         => 'Surcharge cognitive',
                    'allophone'         => 'Langue seconde / Allophone',
                    'faible_autonomie'  => 'Faible autonomie',
                    'anxiete'           => 'Anxi&eacute;t&eacute; face aux consignes',
                    'trouble_apprentissage' => "Trouble d'apprentissage",
                    'autre'             => 'Autre',
                ];

                $other_text = '';
                foreach ( $difficulties as $d ) {
                    if ( is_array( $d ) && ( $d['key'] ?? '' ) === 'autre' ) {
                        $other_text = $d['text'] ?? '';
                    }
                }
                $checked_keys = array_map( fn( $d ) => is_array( $d ) ? ( $d['key'] ?? '' ) : $d, $difficulties );

                $student_page = get_page_by_path( 'dashboard-etudiant' );
                $courses_page = get_page_by_path( 'cours-projets' );
                $student_url  = $student_page ? get_permalink( $student_page ) : '#';
                $courses_url  = $courses_page ? get_permalink( $courses_page ) : '#';
            ?>

                <!-- Mes difficultés / troubles -->
                <div class="pl-account-section pl-glass-card pl-animate-in">
                    <h3>&#128203; Mes difficult&eacute;s / troubles</h3>
                    <p class="pl-text-muted">Ces informations aident vos enseignants &agrave; adapter leur p&eacute;dagogie.</p>
                    <div id="pl-diff-msg" class="pl-account-msg" style="display:none;"></div>
                    <form id="pl-difficulties-form" class="pl-difficulties-list">
                        <input type="hidden" name="_wpnonce" value="<?php echo $diff_nonce; ?>" />
                        <?php foreach ( $difficulty_options as $key => $label ) : ?>
                            <label class="pl-difficulty-checkbox">
                                <input type="checkbox" name="difficulties[]" value="<?php echo esc_attr( $key ); ?>"
                                    <?php checked( in_array( $key, $checked_keys, true ) ); ?> />
                                <span class="pl-checkbox-custom"></span>
                                <span><?php echo $label; ?></span>
                            </label>
                            <?php if ( $key === 'autre' ) : ?>
                                <div class="pl-autre-field" style="<?php echo in_array( 'autre', $checked_keys, true ) ? '' : 'display:none;'; ?>">
                                    <input type="text" name="autre_text" placeholder="Pr&eacute;cisez…"
                                           value="<?php echo esc_attr( $other_text ); ?>" />
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" class="pl-btn pl-btn-primary">Sauvegarder</button>
                    </form>
                </div>

                <!-- Liens rapides étudiant -->
                <div class="pl-account-section pl-glass-card pl-animate-in">
                    <h3>&#128279; Liens rapides</h3>
                    <div class="pl-quick-links">
                        <a href="<?php echo esc_url( $student_url ); ?>" class="pl-quick-link-card">
                            <span class="pl-ql-icon">&#129302;</span>
                            <span>Jumeau num&eacute;rique</span>
                        </a>
                        <a href="<?php echo esc_url( $courses_url ); ?>" class="pl-quick-link-card">
                            <span class="pl-ql-icon">&#128218;</span>
                            <span>Cours</span>
                        </a>
                    </div>
                </div>

            <?php endif; ?>

        </div><!-- .pl-account-page -->

        <script>
        (function($){
            // --- Profil form (tous les rôles) ---
            $('#pl-profile-form').on('submit', function(e){
                e.preventDefault();
                var $form = $(this), $msg = $('#pl-profile-msg'), $btn = $form.find('button[type=submit]');
                $btn.prop('disabled',true).text('Enregistrement…');
                $msg.hide();
                $.post(plFront.ajaxUrl, {
                    action: 'pl_save_account_profile',
                    _wpnonce: $form.find('[name=_wpnonce]').val(),
                    display_name: $('#pl-display-name').val(),
                    email: $('#pl-email').val(),
                    password: $('#pl-password').val()
                }, function(res){
                    $msg.removeClass('pl-msg-ok pl-msg-err')
                        .addClass(res.success ? 'pl-msg-ok' : 'pl-msg-err')
                        .text(res.data?.message || (res.success ? 'Profil mis à jour.' : 'Erreur.'))
                        .show();
                    $btn.prop('disabled',false).text('Enregistrer');
                }).fail(function(){
                    $msg.addClass('pl-msg-err').text('Erreur réseau.').show();
                    $btn.prop('disabled',false).text('Enregistrer');
                });
            });

            // --- Difficultés étudiant ---
            $('[name="difficulties[]"][value="autre"]').on('change', function(){
                $('.pl-autre-field').toggle(this.checked);
            });
            $('#pl-difficulties-form').on('submit', function(e){
                e.preventDefault();
                var $form = $(this), $msg = $('#pl-diff-msg'), $btn = $form.find('button[type=submit]');
                var checked = [];
                $form.find('[name="difficulties[]"]:checked').each(function(){
                    var key = $(this).val();
                    if(key === 'autre'){
                        checked.push({key:'autre', text: $form.find('[name=autre_text]').val()});
                    } else {
                        checked.push(key);
                    }
                });
                $btn.prop('disabled',true).text('Enregistrement…');
                $msg.hide();
                $.post(plFront.ajaxUrl, {
                    action: 'pl_save_student_difficulties',
                    _wpnonce: $form.find('[name=_wpnonce]').val(),
                    difficulties: JSON.stringify(checked)
                }, function(res){
                    $msg.removeClass('pl-msg-ok pl-msg-err')
                        .addClass(res.success ? 'pl-msg-ok' : 'pl-msg-err')
                        .text(res.data?.message || (res.success ? 'Sauvegardé !' : 'Erreur.'))
                        .show();
                    $btn.prop('disabled',false).text('Sauvegarder');
                }).fail(function(){
                    $msg.addClass('pl-msg-err').text('Erreur réseau.').show();
                    $btn.prop('disabled',false).text('Sauvegarder');
                });
            });

            // --- Préférences toggle (enseignant) ---
            $('.pl-toggle-input').on('change', function(){
                var pref = $(this).data('pref'), val = this.checked ? 1 : 0;
                $.post(plFront.ajaxUrl, {
                    action: 'pl_save_account_profile',
                    _wpnonce: '<?php echo $profile_nonce; ?>',
                    pref_key: pref,
                    pref_val: val
                });
            });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX — Sauvegarde difficultés étudiant
    // -------------------------------------------------------------------------

    public static function ajax_save_student_difficulties(): void {
        check_ajax_referer( 'pl_student_difficulties' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Non authentifié.' ] );
        }

        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if ( ! in_array( 'pedagolens_student', $roles, true ) && ! in_array( 'administrator', $roles, true ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $raw = isset( $_POST['difficulties'] ) ? sanitize_text_field( wp_unslash( $_POST['difficulties'] ) ) : '[]';
        $decoded = json_decode( $raw, true );

        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( [ 'message' => 'Données invalides.' ] );
        }

        // Sanitize each entry
        $clean = [];
        foreach ( $decoded as $item ) {
            if ( is_string( $item ) ) {
                $clean[] = sanitize_key( $item );
            } elseif ( is_array( $item ) && isset( $item['key'] ) ) {
                $clean[] = [
                    'key'  => sanitize_key( $item['key'] ),
                    'text' => sanitize_text_field( $item['text'] ?? '' ),
                ];
            }
        }

        update_user_meta( $user->ID, 'pl_student_difficulties', $clean );

        wp_send_json_success( [ 'message' => 'Difficultés enregistrées.' ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Sauvegarde profil / préférences compte
    // -------------------------------------------------------------------------

    public static function ajax_save_account_profile(): void {
        check_ajax_referer( 'pl_account_profile' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Non authentifié.' ] );
        }

        $user = wp_get_current_user();

        // Préférence toggle (enseignant)
        if ( ! empty( $_POST['pref_key'] ) ) {
            $prefs = (array) get_user_meta( $user->ID, 'pl_teacher_prefs', true );
            $key   = sanitize_key( $_POST['pref_key'] );
            $prefs[ $key ] = (int) $_POST['pref_val'] ? true : false;
            update_user_meta( $user->ID, 'pl_teacher_prefs', $prefs );
            wp_send_json_success( [ 'message' => 'Préférence enregistrée.' ] );
        }

        // Profil complet
        $display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
        $email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password     = $_POST['password'] ?? '';

        if ( ! $display_name || ! $email ) {
            wp_send_json_error( [ 'message' => 'Nom et courriel requis.' ] );
        }

        // Vérifier unicité email
        if ( $email !== $user->user_email ) {
            $existing = get_user_by( 'email', $email );
            if ( $existing && $existing->ID !== $user->ID ) {
                wp_send_json_error( [ 'message' => 'Ce courriel est déjà utilisé.' ] );
            }
        }

        $update_data = [
            'ID'           => $user->ID,
            'display_name' => $display_name,
            'user_email'   => $email,
        ];

        if ( ! empty( $password ) ) {
            if ( strlen( $password ) < 6 ) {
                wp_send_json_error( [ 'message' => 'Le mot de passe doit contenir au moins 6 caractères.' ] );
            }
            $update_data['user_pass'] = $password;
        }

        $result = wp_update_user( $update_data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Profil mis à jour.' ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function render_login_notice( string $message ): string {
        $login_url = esc_url( wp_login_url( get_permalink() ) );
        return "<div class=\"pl-notice pl-notice-warning\"><p>{$message} <a href=\"{$login_url}\">Se connecter</a></p></div>";
    }

    private static function score_color( int $score ): string {
        if ( $score >= 80 ) return '#00a32a';
        if ( $score >= 60 ) return '#2271b1';
        if ( $score >= 40 ) return '#dba617';
        return '#d63638';
    }

    public static function get_settings(): array {
        $raw = get_option( 'pl_landing_settings', [] );
        if ( is_string( $raw ) ) {
            $raw = json_decode( $raw, true ) ?? [];
        }

        return wp_parse_args( (array) $raw, [
            'hero_title'    => 'PédagoLens',
            'hero_subtitle' => "L'IA pédagogique pour les enseignants du CÉGEP.",
            'cta_text'      => 'Demander une démo',
            'cta_url'       => '#',
            'primary_color' => '#2271b1',
            'features'      => self::default_features(),
        ] );
    }

    private static function default_features(): array {
        return [
            [ 'icon' => '&#128269;', 'title' => 'Analyse pédagogique IA',    'desc' => "Analysez vos cours selon 7 profils d'apprenants en quelques secondes." ],
            [ 'icon' => '&#9999;',   'title' => 'Atelier de cours',           'desc' => "Recevez des suggestions concrètes pour améliorer l'accessibilité de vos contenus." ],
            [ 'icon' => '&#129302;', 'title' => 'Jumeau numérique étudiant', 'desc' => "Simulez l'expérience d'un étudiant avec des garde-fous pédagogiques intégrés." ],
            [ 'icon' => '&#128202;', 'title' => 'Tableau de bord',           'desc' => "Visualisez les scores par profil et suivez l'évolution de vos cours." ],
        ];
    }
}
