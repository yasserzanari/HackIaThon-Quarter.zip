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
        add_shortcode( 'pedagolens_login',             [ self::class, 'shortcode_login' ] );
        add_shortcode( 'pedagolens_history',           [ self::class, 'shortcode_history' ] );
        add_shortcode( 'pedagolens_settings',          [ self::class, 'shortcode_settings' ] );
        add_shortcode( 'pedagolens_institutional',     [ self::class, 'shortcode_institutional' ] );
        add_shortcode( 'pedagolens_historique',        [ self::class, 'shortcode_historique' ] );
        add_shortcode( 'pedagolens_parametres',        [ self::class, 'shortcode_parametres' ] );
        add_shortcode( 'pedagolens_institutionnel',    [ self::class, 'shortcode_institutionnel' ] );

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
        if ( ! has_action( 'wp_ajax_pl_add_section' ) ) {
            add_action( 'wp_ajax_pl_add_section',       [ 'PedagoLens_Workbench_Admin', 'ajax_add_section' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_get_versions' ) ) {
            add_action( 'wp_ajax_pl_get_versions',      [ 'PedagoLens_Workbench_Admin', 'ajax_get_versions' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_upload_file' ) ) {
            add_action( 'wp_ajax_pl_upload_file',       [ 'PedagoLens_Workbench_Admin', 'ajax_upload_file' ] );
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
        if ( ! has_action( 'wp_ajax_pl_create_course' ) ) {
            add_action( 'wp_ajax_pl_create_course',     [ 'PedagoLens_Dashboard_Admin', 'ajax_create_course' ] );
        }

        // AJAX login / register (accessible aux visiteurs ET aux connectés)
        if ( ! has_action( 'wp_ajax_nopriv_pl_login' ) ) {
            add_action( 'wp_ajax_nopriv_pl_login',    [ self::class, 'ajax_login' ] );
            add_action( 'wp_ajax_pl_login',           [ self::class, 'ajax_login' ] );
        }
        if ( ! has_action( 'wp_ajax_nopriv_pl_register' ) ) {
            add_action( 'wp_ajax_nopriv_pl_register', [ self::class, 'ajax_register' ] );
            add_action( 'wp_ajax_pl_register',        [ self::class, 'ajax_register' ] );
        }

        // AJAX front-end pour sauvegarde paramètres utilisateur
        if ( ! has_action( 'wp_ajax_pl_save_settings' ) ) {
            add_action( 'wp_ajax_pl_save_settings', [ self::class, 'ajax_save_settings' ] );
        }

        // AJAX front-end pour CRUD cours (Task 16)
        if ( ! has_action( 'wp_ajax_pl_create_course_front' ) ) {
            add_action( 'wp_ajax_pl_create_course_front', [ self::class, 'ajax_create_course_front' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_update_course_front' ) ) {
            add_action( 'wp_ajax_pl_update_course_front', [ self::class, 'ajax_update_course_front' ] );
        }
        if ( ! has_action( 'wp_ajax_pl_delete_course_front' ) ) {
            add_action( 'wp_ajax_pl_delete_course_front', [ self::class, 'ajax_delete_course_front' ] );
        }

        // AJAX front-end pour création projet (Task 17)
        if ( ! has_action( 'wp_ajax_pl_create_project_front' ) ) {
            add_action( 'wp_ajax_pl_create_project_front', [ self::class, 'ajax_create_project_front' ] );
        }
    }

    // -------------------------------------------------------------------------
    // Assets front-end
    // -------------------------------------------------------------------------

    public static function enqueue_front_assets(): void {
        // Google Fonts: Manrope (titres) + Inter (corps) + Material Symbols
        wp_enqueue_style(
            'pl-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@700;800&display=swap',
            [],
            null
        );
        wp_enqueue_style(
            'pl-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'pl-landing',
            PL_LANDING_PLUGIN_URL . 'assets/css/landing.css',
            [ 'pl-google-fonts', 'pl-material-symbols' ],
            PL_LANDING_VERSION
        );

        // Toujours charger le JS front (animations landing + AJAX dashboards)
        wp_enqueue_script(
            'pl-landing-front',
            PL_LANDING_PLUGIN_URL . 'assets/js/landing-front.js',
            [ 'jquery' ],
            PL_LANDING_VERSION,
            true
        );

        $has_dashboard = class_exists( 'PedagoLens_Dashboard_Admin' );
        $has_twin      = class_exists( 'PedagoLens_Twin_Admin' );
        $has_workbench = class_exists( 'PedagoLens_Workbench_Admin' );

        wp_localize_script( 'pl-landing-front', 'plFront', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pl_nonce' ),
            'nonces'   => [
                'dashboard' => $has_dashboard ? wp_create_nonce( 'pl_dashboard_ajax' ) : '',
                'twin'      => $has_twin      ? wp_create_nonce( 'pl_twin_ajax' )      : '',
                'workbench' => $has_workbench ? wp_create_nonce( 'pl_workbench_ajax' ) : '',
                'login'     => wp_create_nonce( 'pl_login_nonce' ),
                'register'  => wp_create_nonce( 'pl_register_nonce' ),
                'settings'  => wp_create_nonce( 'pl_settings_nonce' ),
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
            $s             = self::get_settings();
            $hero_title    = esc_html( get_option( 'pl_landing_hero_title', $s['hero_title'] ?? 'PédagoLens' ) );
            $hero_subtitle = esc_html( get_option( 'pl_landing_hero_subtitle', $s['hero_subtitle'] ?? "L'IA qui révèle le potentiel de chaque élève." ) );
            $login_page    = get_page_by_path( 'connexion' );
            $login_url     = $login_page ? get_permalink( $login_page ) : wp_login_url();
            $cta_url       = esc_url( $s['cta_url'] ?? $login_url );
            $cta_text      = esc_html( $s['cta_text'] ?? 'Essai gratuit' );
            $year          = esc_html( date( 'Y' ) );

            ob_start();
            ?>
    <div class="pl-stitch-landing">

    <!-- ========== NAV ========== -->
    <header class="plx-header">
        <nav class="plx-nav">
            <div class="plx-nav-logo">
                <a href="<?php echo esc_url( home_url('/') ); ?>">
                    <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--landing-nav" />
                </a>
            </div>
            <div class="plx-nav-links">
                <a href="#plx-features" class="plx-nav-link plx-nav-link--active">Fonctionnalités</a>
                <a href="#plx-how" class="plx-nav-link">Comment ça marche</a>
                <a href="#plx-testimonials" class="plx-nav-link">Témoignages</a>
                <a href="#plx-cta" class="plx-nav-link">Contact</a>
            </div>
            <div class="plx-nav-actions">
                <?php if ( is_user_logged_in() ) :
                    $u_roles = (array) wp_get_current_user()->roles;
                    $dash_link = ( in_array( 'pedagolens_teacher', $u_roles, true ) || in_array( 'administrator', $u_roles, true ) )
                        ? self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' )
                        : self::page_url( 'dashboard-etudiant', '' );
                ?>
                    <a href="<?php echo esc_url( $dash_link ); ?>" class="plx-btn-pill">Mon Dashboard</a>
                <?php else : ?>
                    <a href="<?php echo esc_url( $login_url ); ?>" class="plx-btn-pill">Connexion</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main class="plx-main">

    <!-- ========== HERO ========== -->
    <section class="plx-hero">
        <div class="plx-hero-mesh"></div>
        <div class="plx-hero-inner">
            <div class="plx-hero-content">
                <h1 class="plx-hero-title">
                    L'IA qui transforme chaque cours en expérience d'apprentissage <span class="plx-gradient-text">personnalisée</span>
                </h1>
                <p class="plx-hero-subtitle">Aidez vos étudiants de cégep et d'université à réussir grâce à une pédagogie adaptée à chaque profil cognitif. Conçu par et pour les professeurs québécois.</p>
                <div class="plx-hero-ctas">
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="plx-btn-primary-lg">
                        Démarrer gratuitement
                        <span class="material-symbols-outlined">bolt</span>
                    </a>
                </div>
            </div>

            <!-- Mockup Card -->
            <div class="plx-hero-mockup">
                <div class="plx-glass-card plx-mockup-card">
                    <div class="plx-mockup-header">
                        <div class="plx-mockup-header-left">
                            <div class="plx-mockup-icon-wrap"><span class="material-symbols-outlined">analytics</span></div>
                            <div>
                                <h4>Tableau de bord IA</h4>
                                <p>Session A25 — Introduction à la psychologie</p>
                            </div>
                        </div>
                        <span class="plx-badge-live">SYNC LIVE</span>
                    </div>
                    <div class="plx-mockup-body">
                        <div class="plx-mockup-stat-block">
                            <div class="plx-mockup-stat-row">
                                <span class="plx-mockup-stat-label">Réception cognitive</span>
                                <span class="plx-mockup-stat-value">92%</span>
                            </div>
                            <div class="plx-progress-bar"><div class="plx-progress-fill" style="width:92%"></div></div>
                        </div>
                        <div class="plx-mockup-grid-2">
                            <div class="plx-mockup-mini-stat">
                                <span class="plx-mini-label">Engagement</span>
                                <span class="plx-mini-value plx-color-violet">78%</span>
                            </div>
                            <div class="plx-mockup-mini-stat">
                                <span class="plx-mini-label">Biais détectés</span>
                                <span class="plx-mini-value plx-color-red">02</span>
                            </div>
                        </div>
                        <div class="plx-mockup-suggestion">
                            <span class="material-symbols-outlined">auto_awesome</span>
                            <p><strong>Optimisation suggérée :</strong> Remplacez le bloc texte de la p.14 par un diagramme. +14% de mémorisation prévue pour le segment "Visuel-Spatial".</p>
                        </div>
                    </div>
                </div>
                <div class="plx-hero-blur plx-hero-blur--violet"></div>
                <div class="plx-hero-blur plx-hero-blur--blue"></div>
            </div>
        </div>
    </section>

    <!-- ========== PROBLEM ========== -->
    <section class="plx-section plx-problem">
        <div class="plx-section-inner">
            <div class="plx-problem-grid">
                <div class="plx-problem-image">
                    <div class="plx-problem-img-overlay"></div>
                    <div class="plx-problem-quote">
                        "Dans un amphithéâtre de 200 étudiants, un cours magistral standardisé laisse de côté près de 40% de l'auditoire dès les premières minutes."
                    </div>
                </div>
                <div class="plx-problem-content">
                    <h2 class="plx-section-title">Le mythe de l'étudiant moyen<br>est une barrière à la réussite.</h2>
                    <div class="plx-problem-items">
                        <div class="plx-problem-item">
                            <div class="plx-problem-icon plx-problem-icon--red">
                                <span class="material-symbols-outlined">trending_down</span>
                            </div>
                            <div>
                                <h4>Le Décrochage Invisible</h4>
                                <p>Au cégep, 38% des étudiants ne terminent pas leur programme dans les délais prévus. Le rythme unique et le manque de rétroaction personnalisée sont en cause.</p>
                            </div>
                        </div>
                        <div class="plx-problem-item">
                            <div class="plx-problem-icon plx-problem-icon--blue">
                                <span class="material-symbols-outlined">psychology_alt</span>
                            </div>
                            <div>
                                <h4>Les Barrières Cognitives</h4>
                                <p>Avec des cours magistraux de 200+ étudiants et une diversité croissante de profils (allophones, TDAH, troubles d'apprentissage), l'enseignement uniforme ne suffit plus.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== HOW IT WORKS ========== -->
    <section class="plx-section plx-how" id="plx-how">
        <div class="plx-section-inner">
            <div class="plx-section-header">
                <h2 class="plx-section-title">La boucle de l'excellence</h2>
                <p class="plx-section-subtitle">Une méthodologie en trois phases pour une pédagogie véritablement augmentée.</p>
            </div>
            <div class="plx-steps-grid">
                <div class="plx-step-card">
                    <div class="plx-step-number">01</div>
                    <div class="plx-step-icon plx-icon-blue"><span class="material-symbols-outlined">search_insights</span></div>
                    <h3>Analyser</h3>
                    <p>Cartographiez les dynamiques cognitives de votre audience via une analyse de données anonymisée et éthique.</p>
                    <a href="#" class="plx-step-link plx-link-blue">Explorer l'analyse <span class="material-symbols-outlined">arrow_forward</span></a>
                </div>
                <div class="plx-step-card">
                    <div class="plx-step-number">02</div>
                    <div class="plx-step-icon plx-icon-violet"><span class="material-symbols-outlined">model_training</span></div>
                    <h3>Simuler</h3>
                    <p>Déployez votre contenu sur un jumeau numérique de votre classe pour anticiper les zones de friction et d'ennui.</p>
                    <a href="#" class="plx-step-link plx-link-violet">Voir le jumeau <span class="material-symbols-outlined">arrow_forward</span></a>
                </div>
                <div class="plx-step-card">
                    <div class="plx-step-number">03</div>
                    <div class="plx-step-icon plx-icon-teal"><span class="material-symbols-outlined">auto_fix_high</span></div>
                    <h3>Optimiser</h3>
                    <p>Appliquez les recommandations générées pour rendre chaque minute de cours productive pour 100% des élèves.</p>
                    <a href="#" class="plx-step-link plx-link-teal">Adapter le cours <span class="material-symbols-outlined">arrow_forward</span></a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== PREMIUM FEATURES ========== -->
    <section class="plx-section plx-premium" id="plx-features">
        <div class="plx-section-inner">
            <div class="plx-premium-grid">
                <!-- Large Feature Card -->
                <div class="plx-premium-card plx-premium-card--primary plx-premium-card--wide">
                    <span class="plx-premium-tag">Data Intelligence</span>
                    <h3>Scores de compréhension prédictifs</h3>
                    <p>Identifiez précisément quels concepts bloquent pour quels segments d'étudiants avant même d'entrer en salle.</p>
                    <div class="plx-premium-visual">
                        <div class="plx-premium-placeholder-chart">
                            <div class="plx-chart-bar" style="height:60%"></div>
                            <div class="plx-chart-bar" style="height:85%"></div>
                            <div class="plx-chart-bar" style="height:45%"></div>
                            <div class="plx-chart-bar" style="height:92%"></div>
                            <div class="plx-chart-bar" style="height:70%"></div>
                            <div class="plx-chart-bar" style="height:55%"></div>
                        </div>
                    </div>
                    <div class="plx-premium-blur"></div>
                </div>
                <!-- Vertical Card -->
                <div class="plx-premium-card plx-premium-card--violet">
                    <div class="plx-premium-icon-wrap"><span class="material-symbols-outlined">auto_awesome</span></div>
                    <h3>Assistance IA en Temps Réel</h3>
                    <p>Pendant que vous créez, notre IA analyse la structure sémantique et suggère des alternatives visuelles pour les profils à mémoire eidétique.</p>
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="plx-btn-white-block">Découvrir l'Assistant</a>
                    <div class="plx-premium-sparkle"><span class="material-symbols-outlined">sparkles</span></div>
                </div>
                <!-- Bottom Feature 1 -->
                <div class="plx-premium-card plx-premium-card--dark plx-premium-card--small">
                    <div class="plx-premium-row">
                        <div class="plx-premium-icon-sm plx-icon-teal"><span class="material-symbols-outlined">shield_person</span></div>
                        <h4>Confidentialité Totale</h4>
                    </div>
                    <p>Conformité RGPD stricte. Les données étudiantes sont cryptées de bout en bout et jamais utilisées pour l'entraînement de modèles tiers.</p>
                </div>
                <!-- Bottom Feature 2 -->
                <div class="plx-premium-card plx-premium-card--light plx-premium-card--wide-bottom">
                    <div class="plx-premium-connect-inner">
                        <div>
                            <h3>Interconnectivité Native</h3>
                            <p>Intégrez PédagoLens à votre LMS existant (Moodle, Canvas, Blackboard) en un clic pour synchroniser vos cohortes automatiquement.</p>
                            <div class="plx-lms-icons">
                                <div class="plx-lms-icon">M</div>
                                <div class="plx-lms-icon">C</div>
                                <div class="plx-lms-icon">B</div>
                            </div>
                        </div>
                        <div class="plx-connect-shapes">
                            <div class="plx-shape plx-shape--circle plx-shape--blue"></div>
                            <div class="plx-shape plx-shape--square plx-shape--violet"></div>
                            <div class="plx-shape plx-shape--square plx-shape--teal"></div>
                            <div class="plx-shape plx-shape--circle plx-shape--red"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== TESTIMONIALS ========== -->
    <section class="plx-section plx-testimonials" id="plx-testimonials">
        <div class="plx-section-inner">
            <div class="plx-section-header">
                <h2 class="plx-section-title">Ils transforment l'éducation</h2>
                <p class="plx-section-subtitle">Des enseignants et institutions qui font confiance à PédagoLens.</p>
            </div>
            <div class="plx-testimonials-grid">
                <div class="plx-glass-card plx-testimonial-card">
                    <p class="plx-testimonial-text">« PédagoLens a transformé ma façon de préparer mes cours. Les scores par profil m'ont ouvert les yeux sur des angles morts que je ne soupçonnais pas. Mes étudiants en difficulté progressent enfin. »</p>
                    <div class="plx-testimonial-author">
                        <div class="plx-testimonial-avatar">ML</div>
                        <div><strong>Marie L.</strong><span>Professeure de psychologie, Cégep du Vieux Montréal</span></div>
                    </div>
                </div>
                <div class="plx-glass-card plx-testimonial-card">
                    <p class="plx-testimonial-text">« Le jumeau numérique est une révolution. Nos étudiants TDAH ont vu leur engagement augmenter de 35% en un semestre. L'outil s'intègre parfaitement à notre écosystème Moodle. »</p>
                    <div class="plx-testimonial-author">
                        <div class="plx-testimonial-avatar">PD</div>
                        <div><strong>Pierre D.</strong><span>Directeur pédagogique, UQAM</span></div>
                    </div>
                </div>
                <div class="plx-glass-card plx-testimonial-card">
                    <p class="plx-testimonial-text">« L'intégration avec Moodle est transparente. En 3 clics, toute ma cohorte de 180 étudiants était synchronisée et les analyses prêtes pour la session. »</p>
                    <div class="plx-testimonial-author">
                        <div class="plx-testimonial-avatar">SC</div>
                        <div><strong>Sophie C.</strong><span>Chargée de cours, Université Laval</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FINAL CTA ========== -->
    <section class="plx-section plx-cta" id="plx-cta">
        <div class="plx-cta-card">
            <div class="plx-cta-dots"></div>
            <div class="plx-cta-gradient"></div>
            <div class="plx-cta-content">
                <h2>Prêt à redéfinir vos<br>normes pédagogiques ?</h2>
                <p>Rejoignez les institutions visionnaires qui placent l'équité cognitive au cœur de leur stratégie de réussite.</p>
                <div class="plx-cta-buttons">
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="plx-btn-white-lg">Commencer maintenant</a>
                    <a href="#plx-features" class="plx-btn-ghost-white-lg">Contacter un expert</a>
                </div>
            </div>
        </div>
    </section>

    </main>

    <!-- ========== FOOTER ========== -->
    <footer class="plx-footer">
        <div class="plx-footer-inner">
            <div class="plx-footer-grid">
                <div class="plx-footer-brand">
                    <div class="plx-nav-logo">
                        <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--footer" />
                        <span class="plx-logo-text-white">PédagoLens</span>
                    </div>
                    <p>L'intelligence artificielle dédiée à l'équité pédagogique. Nous aidons les éducateurs à bâtir un futur où personne n'est laissé pour compte.</p>
                    <div class="plx-footer-social">
                        <a href="#" class="plx-social-link"><span class="material-symbols-outlined">public</span></a>
                        <a href="#" class="plx-social-link"><span class="material-symbols-outlined">alternate_email</span></a>
                        <a href="#" class="plx-social-link"><span class="material-symbols-outlined">chat_bubble</span></a>
                    </div>
                </div>
                <div class="plx-footer-col">
                    <h4>Solution</h4>
                    <ul>
                        <li><a href="#">Plateforme IA</a></li>
                        <li><a href="#">Analyse Cognitive</a></li>
                        <li><a href="#">Jumeau Numérique</a></li>
                        <li><a href="#">Intégrations</a></li>
                    </ul>
                </div>
                <div class="plx-footer-col">
                    <h4>Ressources</h4>
                    <ul>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Webinaires</a></li>
                        <li><a href="#">Études de cas</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                <div class="plx-footer-col">
                    <h4>Entreprise</h4>
                    <ul>
                        <li><a href="#">À propos</a></li>
                        <li><a href="#">Partenaires</a></li>
                        <li><a href="#">Éthique IA</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="plx-footer-col">
                    <h4>Légal</h4>
                    <ul>
                        <li><a href="#">Confidentialité</a></li>
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">Loi 25 (Québec)</a></li>
                        <li><a href="#">Cookies</a></li>
                    </ul>
                </div>
            </div>
            <div class="plx-footer-bottom">
                <p>&copy; <?php echo $year; ?> PédagoLens AI. Tous droits réservés.</p>
                <div class="plx-footer-lang">
                    <span class="material-symbols-outlined">language</span>
                    <span>Français (Québec)</span>
                </div>
            </div>
        </div>
    </footer>

    </div><!-- .pl-stitch-landing -->
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
    // [pedagolens_teacher_dashboard] — Dashboard enseignant front-end (Stitch)
    // -------------------------------------------------------------------------

    
        public static function shortcode_teacher_dashboard( array $atts ): string {
            if ( ! is_user_logged_in() ) {
                return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der au tableau de bord.' );
            }

            $user       = wp_get_current_user();
            $first_name = esc_html( $user->first_name ?: $user->display_name );

            // URLs
            $courses_url   = esc_url( self::page_url( 'cours-projets', 'pl-course-workbench' ) );
            $workbench_url = esc_url( self::page_url( 'workbench', 'pl-course-workbench' ) );
            $history_url   = esc_url( self::page_url( 'historique', '' ) );
            $settings_url  = esc_url( self::page_url( 'parametres', '' ) );

            // Stats
            $nb_courses  = (int) ( wp_count_posts( 'pl_course' )->publish ?? 0 );
            $nb_analyses = (int) get_user_meta( $user->ID, '_pl_analysis_count', true );
            $nb_projects = (int) ( wp_count_posts( 'pl_project' )->publish ?? 0 );
            $avg_score   = 78; // mock

            // Recent courses
            $recent_courses = get_posts( [
                'post_type'      => 'pl_course',
                'posts_per_page' => 4,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            ] );

            // Recent projects (ateliers/séances)
            $recent_projects = get_posts( [
                'post_type'      => 'pl_project',
                'posts_per_page' => 4,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            ] );

            ob_start();
            echo self::render_header('Dashboard > Enseignant');
            echo '<div class="pl-app-layout">';
            echo self::render_sidebar('dashboard');
            echo '<main class="pl-app-main">';
            ?>
    <div class="pl-dash-page pl-dash-page--fw">

        <!-- ── Header compact ──────────────────────────────────── -->
        <div class="pl-dash-header pl-dash-header--fw">
            <div>
                <h1 class="pl-dash-title pl-dash-title--fw">Bonjour, <?php echo $first_name; ?> &#128075;</h1>
                <p class="pl-dash-subtitle">Votre tableau de bord p&eacute;dagogique</p>
            </div>
            <a href="<?php echo $courses_url; ?>" class="pl-dash-cta-card">
                <span class="pl-dash-cta-icon"><span class="material-symbols-outlined">add_circle</span></span>
                <span class="pl-dash-cta-text">Nouveau cours</span>
            </a>
        </div>

        <!-- ── KPI Grid (4 cards) ──────────────────────────────── -->
        <div class="pl-dash-kpi-grid pl-dash-kpi-grid--fw">
            <div class="pl-dash-kpi-card pl-dash-kpi-card--fw">
                <div class="pl-dash-kpi-icon pl-dash-icon-blue"><span class="material-symbols-outlined">menu_book</span></div>
                <div class="pl-dash-kpi-info">
                    <span class="pl-dash-kpi-value pl-dash-kpi-value--fw"><?php echo $nb_courses; ?></span>
                    <span class="pl-dash-kpi-label">Cours</span>
                </div>
            </div>
            <div class="pl-dash-kpi-card pl-dash-kpi-card--fw">
                <div class="pl-dash-kpi-icon pl-dash-icon-violet"><span class="material-symbols-outlined">analytics</span></div>
                <div class="pl-dash-kpi-info">
                    <span class="pl-dash-kpi-value pl-dash-kpi-value--fw"><?php echo $nb_analyses; ?></span>
                    <span class="pl-dash-kpi-label">Analyses</span>
                </div>
            </div>
            <div class="pl-dash-kpi-card pl-dash-kpi-card--fw">
                <div class="pl-dash-kpi-icon pl-dash-icon-green"><span class="material-symbols-outlined">folder_open</span></div>
                <div class="pl-dash-kpi-info">
                    <span class="pl-dash-kpi-value pl-dash-kpi-value--fw"><?php echo $nb_projects; ?></span>
                    <span class="pl-dash-kpi-label">S&eacute;ances</span>
                </div>
            </div>
            <div class="pl-dash-kpi-card pl-dash-kpi-card--fw">
                <div class="pl-dash-kpi-icon pl-dash-icon-amber"><span class="material-symbols-outlined">speed</span></div>
                <div class="pl-dash-kpi-info">
                    <span class="pl-dash-kpi-value pl-dash-kpi-value--fw"><?php echo $avg_score; ?><small>/100</small></span>
                    <span class="pl-dash-kpi-label">Score moyen</span>
                </div>
            </div>
        </div>

        <!-- ── Bottom zone : 3 columns ─────────────────────────── -->
        <div class="pl-dash-bottom-grid">

            <!-- Cours récents -->
            <section class="pl-dash-section-card">
                <h2 class="pl-dash-section-heading">
                    <span class="material-symbols-outlined">menu_book</span> Cours r&eacute;cents
                </h2>
                <?php if ( empty( $recent_courses ) ) : ?>
                    <div class="pl-dash-empty-mini">
                        <span class="material-symbols-outlined">school</span>
                        <p>Aucun cours pour l&rsquo;instant.</p>
                    </div>
                <?php else : ?>
                    <div class="pl-dash-mini-list">
                        <?php foreach ( $recent_courses as $course ) :
                            $date_str   = esc_html( wp_date( 'j M Y', strtotime( $course->post_date ) ) );
                            $course_url = esc_url( add_query_arg( 'course_id', $course->ID, $workbench_url ) );
                        ?>
                        <a href="<?php echo $course_url; ?>" class="pl-dash-mini-item">
                            <div class="pl-dash-mini-info">
                                <span class="pl-dash-mini-title"><?php echo esc_html( $course->post_title ); ?></span>
                                <span class="pl-dash-mini-date"><?php echo $date_str; ?></span>
                            </div>
                            <span class="material-symbols-outlined pl-dash-mini-arrow">chevron_right</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Ateliers récents -->
            <section class="pl-dash-section-card">
                <h2 class="pl-dash-section-heading">
                    <span class="material-symbols-outlined">science</span> Ateliers r&eacute;cents
                </h2>
                <?php if ( empty( $recent_projects ) ) : ?>
                    <div class="pl-dash-empty-mini">
                        <span class="material-symbols-outlined">biotech</span>
                        <p>Aucun atelier pour l&rsquo;instant.</p>
                    </div>
                <?php else : ?>
                    <div class="pl-dash-mini-list">
                        <?php foreach ( $recent_projects as $project ) :
                            $date_str    = esc_html( wp_date( 'j M Y', strtotime( $project->post_date ) ) );
                            $project_url = esc_url( add_query_arg( 'project_id', $project->ID, $workbench_url ) );
                        ?>
                        <a href="<?php echo $project_url; ?>" class="pl-dash-mini-item">
                            <div class="pl-dash-mini-info">
                                <span class="pl-dash-mini-title"><?php echo esc_html( $project->post_title ); ?></span>
                                <span class="pl-dash-mini-date"><?php echo $date_str; ?></span>
                            </div>
                            <span class="material-symbols-outlined pl-dash-mini-arrow">chevron_right</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Actions rapides -->
            <section class="pl-dash-section-card pl-dash-section-card--actions">
                <h2 class="pl-dash-section-heading">
                    <span class="material-symbols-outlined">bolt</span> Actions rapides
                </h2>
                <div class="pl-dash-actions-grid">
                    <a href="<?php echo $courses_url; ?>" class="pl-dash-action-btn">
                        <span class="material-symbols-outlined">add_circle</span>
                        <span>Nouveau cours</span>
                    </a>
                    <a href="<?php echo $workbench_url; ?>" class="pl-dash-action-btn">
                        <span class="material-symbols-outlined">upload_file</span>
                        <span>Importer PPTX</span>
                    </a>
                    <a href="<?php echo $history_url; ?>" class="pl-dash-action-btn">
                        <span class="material-symbols-outlined">history</span>
                        <span>Historique</span>
                    </a>
                    <a href="<?php echo $settings_url; ?>" class="pl-dash-action-btn">
                        <span class="material-symbols-outlined">settings</span>
                        <span>Param&egrave;tres</span>
                    </a>
                </div>
            </section>

        </div><!-- .pl-dash-bottom-grid -->

    </div><!-- .pl-dash-page -->
            <?php
            echo '</main>';
            echo '</div>';
            echo self::render_footer();
            return ob_get_clean();
        }



    // -------------------------------------------------------------------------
    // [pedagolens_student_dashboard] — Dashboard étudiant (Stitch)
    // -------------------------------------------------------------------------

    
        public static function shortcode_student_dashboard( array $atts ): string {
            $atts = shortcode_atts( [ 'course_id' => 0 ], $atts );

            if ( ! is_user_logged_in() ) {
                return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der au tableau de bord.' );
            }

            $user       = wp_get_current_user();
            $first_name = esc_html( $user->first_name ?: $user->display_name );
            $roles      = (array) $user->roles;
            $is_teacher = in_array( 'administrator', $roles, true ) || in_array( 'pedagolens_teacher', $roles, true );

            // Localize twin script
            wp_localize_script( 'pl-landing-front', 'plLea', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'pl_twin_ajax' ),
            ] );

            ob_start();

            // Teacher/Admin → Agent IA Léa interface
            if ( $is_teacher ) {
                echo self::render_header( 'Agent IA L&eacute;a' );
                echo '<div class="pl-app-layout">';
                echo self::render_sidebar( 'lea' );
                echo '<main class="pl-app-main">';

                // Get active profiles from core
                $profiles = [];
                if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
                    $raw = PedagoLens_Profile_Manager::get_all( true );
                    foreach ( $raw as $p ) {
                        $profiles[] = [
                            'slug'        => $p['slug'] ?? '',
                            'label'       => $p['name'] ?? $p['slug'] ?? 'Profil',
                            'description' => $p['description'] ?? '',
                            'icon'        => 'person',
                            'traits'      => [],
                        ];
                    }
                }
                if ( empty( $profiles ) ) {
                    $profiles = [
                        [ 'slug' => 'visuel-spatial',    'label' => 'Visuel-Spatial',    'description' => 'Apprend mieux avec des diagrammes, schémas et représentations visuelles.', 'icon' => 'visibility', 'traits' => ['Visuel', 'Spatial', 'Diagrammes'] ],
                        [ 'slug' => 'auditif-verbal',    'label' => 'Auditif-Verbal',    'description' => 'Retient mieux par l\'écoute, les discussions et les explications orales.', 'icon' => 'hearing', 'traits' => ['Auditif', 'Verbal', 'Discussion'] ],
                        [ 'slug' => 'kinesthesique',     'label' => 'Kinesthésique',     'description' => 'Apprend par la pratique, les exercices concrets et la manipulation.', 'icon' => 'touch_app', 'traits' => ['Pratique', 'Concret', 'Manipulation'] ],
                        [ 'slug' => 'tdah',              'label' => 'TDAH',              'description' => 'Besoin de contenu structuré, court et interactif pour maintenir l\'attention.', 'icon' => 'psychology', 'traits' => ['Attention', 'Structure', 'Interactif'] ],
                        [ 'slug' => 'allophone',         'label' => 'Allophone',         'description' => 'Français langue seconde — besoin de vocabulaire simplifié et de contexte culturel.', 'icon' => 'translate', 'traits' => ['Langue', 'Vocabulaire', 'Contexte'] ],
                        [ 'slug' => 'anxieux',           'label' => 'Anxieux',           'description' => 'Sensible au stress d\'évaluation, besoin de renforcement positif et de clarté.', 'icon' => 'favorite', 'traits' => ['Stress', 'Renforcement', 'Clarté'] ],
                    ];
                }
                ?>
    <div class="pl-lea-page">
        <!-- Profile Selector Side Panel -->
        <div class="pl-lea-profiles-panel" id="pl-lea-profiles-panel">
            <div class="pl-lea-profiles-header">
                <h3><span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle;margin-right:0.25rem;">group</span> Profils &eacute;tudiants</h3>
                <p>S&eacute;lectionnez un profil pour simuler les r&eacute;ponses de L&eacute;a.</p>
            </div>
            <div class="pl-lea-profiles-list">
                <?php foreach ( $profiles as $i => $profile ) :
                    $slug  = esc_attr( $profile['slug'] ?? $profile['label'] ?? 'profil-' . $i );
                    $label = esc_html( $profile['label'] ?? $profile['slug'] ?? 'Profil' );
                    $desc  = esc_html( $profile['description'] ?? '' );
                    $icon  = esc_attr( $profile['icon'] ?? 'person' );
                    $traits = $profile['traits'] ?? [];
                    $active_cls = $i === 0 ? ' active' : '';
                ?>
                <div class="pl-lea-profile-card<?php echo $active_cls; ?>" data-profile="<?php echo $slug; ?>" data-label="<?php echo $label; ?>">
                    <div class="pl-lea-profile-card-name">
                        <span class="material-symbols-outlined"><?php echo $icon; ?></span>
                        <?php echo $label; ?>
                    </div>
                    <?php if ( $desc ) : ?>
                    <div class="pl-lea-profile-card-desc"><?php echo $desc; ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $traits ) ) : ?>
                    <div class="pl-lea-profile-card-traits">
                        <?php foreach ( $traits as $trait ) : ?>
                        <span class="pl-lea-profile-trait"><?php echo esc_html( $trait ); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="pl-lea-chat-area">
            <div class="pl-lea-chat-header">
                <div class="pl-lea-chat-header-left">
                    <button class="pl-lea-toggle-panel" id="pl-lea-toggle-panel" title="Profils">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <div class="pl-lea-chat-avatar">
                        <span class="material-symbols-outlined">psychology</span>
                    </div>
                    <div class="pl-lea-chat-info">
                        <h3>Agent IA L&eacute;a</h3>
                        <p id="pl-lea-active-profile">Profil : <?php echo esc_html( $profiles[0]['label'] ?? 'Visuel-Spatial' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="pl-lea-chat-messages" id="pl-lea-messages">
                <div class="pl-lea-msg pl-lea-msg--bot">
                    Bonjour <?php echo $first_name; ?> ! Je suis L&eacute;a, votre jumeau num&eacute;rique &eacute;tudiant. Je simule les r&eacute;actions d'un &eacute;tudiant au profil <strong><?php echo esc_html( $profiles[0]['label'] ?? 'Visuel-Spatial' ); ?></strong>. Posez-moi une question sur votre cours ou testez une explication — je r&eacute;pondrai comme le ferait un &eacute;tudiant de ce profil.
                </div>
            </div>

            <div class="pl-lea-chat-input-area">
                <div class="pl-lea-chat-input-wrap">
                    <textarea class="pl-lea-chat-input" id="pl-lea-input" placeholder="Posez une question &agrave; L&eacute;a..." rows="1"></textarea>
                    <button class="pl-lea-chat-send" id="pl-lea-send" title="Envoyer">
                        <span class="material-symbols-outlined">send</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
                <?php
                echo '</main>';
                echo '</div>';
                echo self::render_footer();
                return ob_get_clean();
            }

            // Student view (original)
            $logout_url  = esc_url( wp_logout_url( home_url( '/' ) ) );
            $dash_url    = esc_url( self::page_url( 'dashboard-etudiant', '' ) );
            $courses_url = esc_url( self::page_url( 'cours-projets', 'pl-course-workbench' ) );
            $twin_url    = esc_url( self::page_url( 'dashboard-etudiant', '' ) );
            $account_url = esc_url( self::page_url( 'compte', '' ) );

            $nb_courses      = (int) ( wp_count_posts( 'pl_course' )->publish ?? 0 );
            $nb_interactions = (int) ( wp_count_posts( 'pl_interaction' )->publish ?? 0 );

            $recent_interactions = get_posts( [
                'post_type'      => 'pl_interaction',
                'posts_per_page' => 5,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
                'author'         => $user->ID,
            ] );

            echo self::render_header( 'Dashboard > &Eacute;tudiant' );
            echo '<div class="pl-app-layout">';
            echo self::render_sidebar( 'dashboard' );
            echo '<main class="pl-app-main">';
            ?>
    <div class="pl-stu-page">
            <div class="pl-stu-header">
                <div>
                    <h1 class="pl-stu-title">Bonjour, <?php echo $first_name; ?> &#128075;</h1>
                    <p class="pl-stu-subtitle">Votre espace d'apprentissage personnalis&eacute;.</p>
                </div>
            </div>
            <div class="pl-stu-stats-grid">
                <div class="pl-stu-stat-card">
                    <div class="pl-stu-stat-icon pl-stu-icon-blue"><span class="material-symbols-outlined">menu_book</span></div>
                    <div class="pl-stu-stat-info">
                        <span class="pl-stu-stat-value"><?php echo $nb_courses; ?></span>
                        <span class="pl-stu-stat-label">Cours disponibles</span>
                    </div>
                </div>
                <div class="pl-stu-stat-card">
                    <div class="pl-stu-stat-icon pl-stu-icon-violet"><span class="material-symbols-outlined">chat</span></div>
                    <div class="pl-stu-stat-info">
                        <span class="pl-stu-stat-value"><?php echo $nb_interactions; ?></span>
                        <span class="pl-stu-stat-label">Conversations</span>
                    </div>
                </div>
                <div class="pl-stu-stat-card">
                    <div class="pl-stu-stat-icon pl-stu-icon-green"><span class="material-symbols-outlined">smart_toy</span></div>
                    <div class="pl-stu-stat-info">
                        <a href="<?php echo $twin_url; ?>" class="pl-stu-stat-value pl-stu-stat-link">Acc&eacute;der</a>
                        <span class="pl-stu-stat-label">Assistant IA</span>
                    </div>
                </div>
            </div>
            <section class="pl-stu-activity">
                <h2 class="pl-stu-activity-title">
                    <span class="material-symbols-outlined">history</span>
                    Historique r&eacute;cent
                </h2>
                <?php if ( empty( $recent_interactions ) ) : ?>
                    <div class="pl-stu-empty-state">
                        <span class="material-symbols-outlined">inbox</span>
                        <p>Aucune activit&eacute; r&eacute;cente. Commencez par discuter avec votre assistant !</p>
                    </div>
                <?php else : ?>
                    <div class="pl-stu-activity-list">
                        <?php foreach ( $recent_interactions as $interaction ) :
                            $date_str = esc_html( wp_date( 'j M Y &agrave; H:i', strtotime( $interaction->post_date ) ) );
                        ?>
                            <div class="pl-stu-activity-item">
                                <div class="pl-stu-activity-icon"><span class="material-symbols-outlined">chat_bubble</span></div>
                                <div class="pl-stu-activity-info">
                                    <strong><?php echo esc_html( $interaction->post_title ?: 'Conversation' ); ?></strong>
                                    <span class="pl-stu-activity-date"><?php echo $date_str; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
    </div>
            <?php
            echo '</main>';
            echo '</div>';
            echo self::render_footer();
            return ob_get_clean();
        }



    // -------------------------------------------------------------------------
    // [pedagolens_courses] — Liste des cours et projets (front-end complet)
    // -------------------------------------------------------------------------

    public static function shortcode_courses( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; vos cours.' );
        }

        $nav_links = self::get_nav_links();

        $courses = get_posts( [
            'post_type'      => 'pl_course',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

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
        $project_type_options = [
            'magistral'      => 'Magistral (PowerPoint de cours)',
            'exercice'       => 'Exercice (PowerPoint + Word)',
            'travail_equipe' => 'Travail d\'équipe (documents collaboratifs)',
            'evaluation'     => 'Évaluation (examens, dissertations)',
        ];

        ob_start();
        echo self::render_header('Cours > Projets');
        echo '<div class="pl-app-layout">';
        echo self::render_sidebar('courses');
        echo '<main class="pl-app-main">';
        ?>
    <!-- CONTENT -->
    <div class="pl-courses-content">
        <div class="pl-section-inner">

            <div class="pl-courses-page-header pl-animate-in">
                <div>
                    <span class="pl-section-tag">📚 Mes cours</span>
                    <h1 class="pl-courses-main-title">Cours &amp; S&eacute;ances</h1>
                    <p class="pl-courses-subtitle">G&eacute;rez vos cours, cr&eacute;ez des s&eacute;ances et analysez-les avec l'IA.</p>
                </div>
                <button data-pl-modal-open="create-course" class="pl-btn pl-btn--primary pl-btn--icon" style="display:none;">
                    <span class="material-symbols-outlined">add</span>
                    Cr&eacute;er un cours
                </button>
            </div>

            <?php if ( empty( $courses ) ) : ?>
                <div class="pl-empty-cta pl-animate-in">
                    <div class="pl-empty-cta-icon"><span class="material-symbols-outlined">add</span></div>
                    <h3>Cr&eacute;ez votre premier cours</h3>
                    <p>Ajoutez un cours pour commencer &agrave; organiser vos s&eacute;ances et lancer des analyses IA.</p>
                    <button data-pl-modal-open="create-course" class="pl-btn pl-btn--primary pl-btn--icon">
                        <span class="material-symbols-outlined">add</span>
                        Cr&eacute;er un cours
                    </button>
                </div>
            <?php else : ?>
                <div class="pl-courses-grid">
                    <?php foreach ( $courses as $course ) :
                        $course_type = get_post_meta( $course->ID, '_pl_course_type', true ) ?: 'magistral';
                        $projects    = class_exists( 'PedagoLens_Teacher_Dashboard' )
                            ? PedagoLens_Teacher_Dashboard::get_projects( $course->ID )
                            : [];
                        $nb_projects = count( $projects );

                        // Build projects JSON for modal
                        $workbench_page = get_page_by_path( 'workbench' );
                        $projects_json = [];
                        foreach ( $projects as $p ) {
                            $wb_url = $workbench_page
                                ? get_permalink( $workbench_page ) . '?project_id=' . $p['id']
                                : admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $p['id'] );
                            $projects_json[] = [
                                'id'    => $p['id'],
                                'title' => $p['title'],
                                'type'  => $p['type'] ?? 'magistral',
                                'url'   => $wb_url,
                            ];
                        }

                        // Last analysis date
                        $last_analysis = '';
                        $analysis_posts = get_posts( [
                            'post_type'      => 'pl_analysis',
                            'posts_per_page' => 1,
                            'orderby'        => 'date',
                            'order'          => 'DESC',
                            'meta_query'     => [ [ 'key' => '_pl_course_id', 'value' => $course->ID, 'type' => 'NUMERIC' ] ],
                        ] );
                        if ( ! empty( $analysis_posts ) ) {
                            $last_analysis = wp_date( 'j M Y', strtotime( $analysis_posts[0]->post_date ) );
                        }
                        ?>
                        <div class="pl-course-card-front pl-animate-in pl-course-card-clickable"
                            data-course-id="<?php echo (int) $course->ID; ?>"
                            data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
                            data-course-type="<?php echo esc_attr( $course_type ); ?>"
                            data-course-projects="<?php echo esc_attr( wp_json_encode( $projects_json ) ); ?>">
                            <div class="pl-course-card-toolbar">
                                <button class="pl-course-card-edit-btn"
                                    data-course-id="<?php echo (int) $course->ID; ?>"
                                    data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
                                    data-course-code="<?php echo esc_attr( get_post_meta( $course->ID, '_pl_course_code', true ) ); ?>"
                                    data-course-session="<?php echo esc_attr( get_post_meta( $course->ID, '_pl_session', true ) ); ?>"
                                    data-course-desc="<?php echo esc_attr( $course->post_content ); ?>"
                                    data-course-type="<?php echo esc_attr( $course_type ); ?>"
                                    title="Modifier ce cours">
                                    <span class="material-symbols-outlined">edit</span>
                                </button>
                                <button class="pl-course-card-delete-btn"
                                    data-course-id="<?php echo (int) $course->ID; ?>"
                                    data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
                                    title="Supprimer ce cours">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </div>
                            <div class="pl-course-card-body">
                                <span class="pl-course-type-icon"><?php echo $type_icons[ $course_type ] ?? '📄'; ?></span>
                                <h3 class="pl-course-card-title"><?php echo esc_html( $course->post_title ); ?></h3>
                                <span class="pl-badge pl-type-<?php echo esc_attr( $course_type ); ?>">
                                    <?php echo esc_html( $type_labels[ $course_type ] ?? $course_type ); ?>
                                </span>
                                <div class="pl-course-card-meta">
                                    <span>📁 <?php echo $nb_projects; ?> s&eacute;ance(s)</span>
                                    <?php if ( $last_analysis ) : ?>
                                        <span>🔍 <?php echo esc_html( $last_analysis ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="pl-course-card-hint">Cliquer pour voir les s&eacute;ances</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <!-- Create course card at end of grid -->
                    <button class="pl-create-course-card pl-animate-in" data-pl-modal-open="create-course">
                        <div class="pl-create-course-card-icon"><span class="material-symbols-outlined">add</span></div>
                        <span class="pl-create-course-card-label">Cr&eacute;er un cours</span>
                        <span class="pl-create-course-card-sub">Ajouter un nouveau cours</span>
                    </button>
                </div>
            <?php endif; ?>

        </div>
    </div>

        <?php
        // ── Create Course Modal ──
        ?>
        <div class="pl-modal" data-pl-modal="create-course">
          <div class="pl-modal-backdrop"></div>
          <div class="pl-modal-content">
            <div class="pl-modal-header">
              <h2>Créer un nouveau cours</h2>
              <button data-pl-modal-close class="pl-modal-close-btn"><span class="material-symbols-outlined">close</span></button>
            </div>
            <form id="pl-create-course-form" class="pl-modal-form">
              <div class="pl-form-group">
                <label for="pl-course-title">Titre du cours *</label>
                <input type="text" id="pl-course-title" name="title" required placeholder="Ex: Français 103" class="pl-form-input" />
              </div>
              <div class="pl-form-row">
                <div class="pl-form-group pl-form-half">
                  <label for="pl-course-code">Code du cours</label>
                  <input type="text" id="pl-course-code" name="code" placeholder="Ex: FRA-103" class="pl-form-input" />
                </div>
                <div class="pl-form-group pl-form-half">
                  <label for="pl-course-session">Session</label>
                  <input type="text" id="pl-course-session" name="session" placeholder="Ex: H26" class="pl-form-input" />
                </div>
              </div>
              <div class="pl-form-group">
                <label for="pl-course-desc">Description</label>
                <textarea id="pl-course-desc" name="description" rows="3" placeholder="Description du cours..." class="pl-form-input"></textarea>
              </div>
              <div class="pl-form-group">
                <label for="pl-course-type">Type de cours</label>
                <select id="pl-course-type" name="course_type" class="pl-form-input">
                  <option value="magistral">Magistral</option>
                  <option value="exercice">Exercice</option>
                  <option value="travail_equipe">Travail d'équipe</option>
                  <option value="evaluation">Évaluation</option>
                </select>
              </div>
              <div class="pl-modal-footer">
                <button type="button" data-pl-modal-close class="pl-btn pl-btn--ghost">Annuler</button>
                <button type="submit" class="pl-btn pl-btn--primary pl-btn-submit">
                  <span class="pl-btn-text">Créer le cours</span>
                  <span class="pl-btn-loader" style="display:none;"><span class="material-symbols-outlined pl-spin">progress_activity</span></span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <?php
        // ── Edit Course Modal ──
        ?>
        <div class="pl-modal" data-pl-modal="edit-course">
          <div class="pl-modal-backdrop"></div>
          <div class="pl-modal-content">
            <div class="pl-modal-header">
              <h2>Modifier le cours</h2>
              <button data-pl-modal-close class="pl-modal-close-btn"><span class="material-symbols-outlined">close</span></button>
            </div>
            <form id="pl-edit-course-form" class="pl-modal-form">
              <input type="hidden" id="pl-edit-course-id" name="course_id" />
              <div class="pl-form-group">
                <label for="pl-edit-course-title">Titre du cours *</label>
                <input type="text" id="pl-edit-course-title" name="title" required placeholder="Ex: Français 103" class="pl-form-input" />
              </div>
              <div class="pl-form-row">
                <div class="pl-form-group pl-form-half">
                  <label for="pl-edit-course-code">Code du cours</label>
                  <input type="text" id="pl-edit-course-code" name="code" placeholder="Ex: FRA-103" class="pl-form-input" />
                </div>
                <div class="pl-form-group pl-form-half">
                  <label for="pl-edit-course-session">Session</label>
                  <input type="text" id="pl-edit-course-session" name="session" placeholder="Ex: H26" class="pl-form-input" />
                </div>
              </div>
              <div class="pl-form-group">
                <label for="pl-edit-course-desc">Description</label>
                <textarea id="pl-edit-course-desc" name="description" rows="3" placeholder="Description du cours..." class="pl-form-input"></textarea>
              </div>
              <div class="pl-form-group">
                <label for="pl-edit-course-type">Type de cours</label>
                <select id="pl-edit-course-type" name="course_type" class="pl-form-input">
                  <option value="magistral">Magistral</option>
                  <option value="exercice">Exercice</option>
                  <option value="travail_equipe">Travail d'équipe</option>
                  <option value="evaluation">Évaluation</option>
                </select>
              </div>
              <div class="pl-modal-footer">
                <button type="button" data-pl-modal-close class="pl-btn pl-btn--ghost">Annuler</button>
                <button type="submit" class="pl-btn pl-btn--primary pl-btn-submit">
                  <span class="pl-btn-text">Enregistrer</span>
                  <span class="pl-btn-loader" style="display:none;"><span class="material-symbols-outlined pl-spin">progress_activity</span></span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <?php
        // ── Create Project Modal (Task 17) ──
        ?>
        <div class="pl-modal" data-pl-modal="create-project">
          <div class="pl-modal-backdrop"></div>
          <div class="pl-modal-content">
            <div class="pl-modal-header">
              <h2>Cr&eacute;er une nouvelle s&eacute;ance</h2>
              <button data-pl-modal-close class="pl-modal-close-btn"><span class="material-symbols-outlined">close</span></button>
            </div>
            <form id="pl-create-project-form" class="pl-modal-form" enctype="multipart/form-data">
              <input type="hidden" id="pl-project-course-id" name="course_id" value="" />
              <div class="pl-project-course-info">
                📚 Cours : <span id="pl-project-course-label"></span>
              </div>
              <div class="pl-form-group">
                <label for="pl-project-title">Titre de la s&eacute;ance *</label>
                <input type="text" id="pl-project-title" name="title" required placeholder="Ex: Semaine 3 — Les fonctions" class="pl-form-input" />
              </div>
              <div class="pl-form-group">
                <label for="pl-project-type">Type de s&eacute;ance</label>
                <select id="pl-project-type" name="project_type" class="pl-form-input">
                  <?php foreach ( $project_type_options as $val => $lbl ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="pl-form-group">
                <label for="pl-project-desc">Description</label>
                <textarea id="pl-project-desc" name="description" rows="3" placeholder="Description de la s&eacute;ance..." class="pl-form-input"></textarea>
              </div>
              <div class="pl-form-group">
                <label>Fichier(s)</label>
                <div class="pl-file-upload-zone" id="pl-project-upload-zone">
                  <label class="pl-file-upload-label" for="pl-project-files">
                    <span class="material-symbols-outlined">upload_file</span>
                    <p>Cliquez ou glissez vos fichiers ici</p>
                    <p><small>.pptx, .pdf, .docx, .doc</small></p>
                  </label>
                  <input type="file" id="pl-project-files" name="project_files[]" multiple accept=".pptx,.pdf,.docx,.doc" />
                </div>
                <div id="pl-project-file-list" style="margin-top:0.5rem;font-size:0.85rem;color:#666;"></div>
              </div>
              <div class="pl-modal-footer">
                <button type="button" data-pl-modal-close class="pl-btn pl-btn--ghost">Annuler</button>
                <button type="submit" class="pl-btn pl-btn--primary pl-btn-submit">
                  <span class="pl-btn-text">Cr&eacute;er la s&eacute;ance</span>
                  <span class="pl-btn-loader" style="display:none;"><span class="material-symbols-outlined pl-spin">progress_activity</span></span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <?php
        // ── Séances Modal (Task 36) ──
        ?>
        <div class="pl-modal pl-seances-modal" data-pl-modal="view-seances">
          <div class="pl-modal-backdrop"></div>
          <div class="pl-modal-content pl-seances-modal-content">
            <div class="pl-modal-header pl-seances-modal-header">
              <div class="pl-seances-modal-title-wrap">
                <span class="pl-seances-modal-icon" id="pl-seances-modal-icon">📚</span>
                <div>
                  <h2 id="pl-seances-modal-title">Séances du cours</h2>
                  <span class="pl-seances-modal-badge" id="pl-seances-modal-badge"></span>
                </div>
              </div>
              <button data-pl-modal-close class="pl-modal-close-btn"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="pl-seances-modal-body" id="pl-seances-modal-body">
              <!-- Populated by JS -->
            </div>
            <div class="pl-seances-modal-footer">
              <button class="pl-btn pl-btn--primary pl-btn--icon pl-seances-modal-create-btn" id="pl-seances-modal-create-btn">
                <span class="material-symbols-outlined">add</span>
                Créer une séance
              </button>
            </div>
          </div>
        </div>

        <?php
        echo '</main>';
        echo '</div>';
        echo self::render_footer();
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // [pedagolens_workbench] — Atelier d'édition complet front-end
    // -------------------------------------------------------------------------

    public static function shortcode_workbench( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der au workbench.' );
        }

        $project_id = (int) ( $_GET['project_id'] ?? 0 );
        $nav_links  = self::get_nav_links();

        if ( ! $project_id ) {
            $courses_page = get_page_by_path( 'cours-projets' );
            $courses_url  = $courses_page ? get_permalink( $courses_page ) : home_url( '/' );
            $workbench_page = get_page_by_path( 'workbench' );

            // Fetch all courses + their projects for the selector
            $courses = get_posts( [
                'post_type'      => 'pl_course',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ] );

            $type_icons = [
                'magistral' => '🎓', 'exercice' => '📝',
                'travail_equipe' => '👥', 'evaluation' => '📋',
            ];
            $type_labels = [
                'magistral' => 'Magistral', 'exercice' => 'Exercice',
                'travail_equipe' => 'Travail d\'équipe', 'evaluation' => 'Évaluation',
            ];

            ob_start();
            echo self::render_header('Atelier');
            echo '<div class="pl-app-layout">';
            echo self::render_sidebar('workbench');
            echo '<main class="pl-app-main">';
            ?>
            <div class="pl-wb-selector">
                <div class="pl-wb-selector-header">
                    <span class="pl-section-tag">🔬 Atelier pédagogique</span>
                    <h1 class="pl-wb-selector-title">Choisissez une séance</h1>
                    <p class="pl-wb-selector-subtitle">Sélectionnez un cours puis une séance pour ouvrir l'atelier d'analyse IA.</p>
                </div>

                <?php if ( empty( $courses ) ) : ?>
                    <div class="pl-wb-selector-empty">
                        <div class="pl-wb-selector-empty-icon">📚</div>
                        <h3>Aucun cours</h3>
                        <p>Créez d'abord un cours sur la page <a href="<?php echo esc_url( $courses_url ); ?>">Cours &amp; Séances</a>.</p>
                    </div>
                <?php else : ?>
                    <div class="pl-wb-selector-grid">
                        <?php foreach ( $courses as $course ) :
                            $c_type = get_post_meta( $course->ID, '_pl_course_type', true ) ?: 'magistral';
                            $projects = class_exists( 'PedagoLens_Teacher_Dashboard' )
                                ? PedagoLens_Teacher_Dashboard::get_projects( $course->ID )
                                : [];
                            $c_icon = $type_icons[ $c_type ] ?? '📄';
                            ?>
                            <div class="pl-wb-selector-course" data-wb-course-id="<?php echo (int) $course->ID; ?>">
                                <div class="pl-wb-selector-course-header">
                                    <span class="pl-wb-selector-course-icon"><?php echo $c_icon; ?></span>
                                    <div>
                                        <h3><?php echo esc_html( $course->post_title ); ?></h3>
                                        <span class="pl-badge pl-type-<?php echo esc_attr( $c_type ); ?>"><?php echo esc_html( $type_labels[ $c_type ] ?? $c_type ); ?></span>
                                    </div>
                                    <span class="pl-wb-selector-chevron material-symbols-outlined">expand_more</span>
                                </div>
                                <div class="pl-wb-selector-seances" style="display:none;">
                                    <?php if ( empty( $projects ) ) : ?>
                                        <p class="pl-wb-selector-no-seance">Aucune séance. <a href="<?php echo esc_url( $courses_url ); ?>">Créer une séance</a></p>
                                    <?php else : ?>
                                        <?php foreach ( $projects as $p ) :
                                            $wb_url = $workbench_page
                                                ? get_permalink( $workbench_page ) . '?project_id=' . $p['id']
                                                : admin_url( 'admin.php?page=pl-course-workbench&project_id=' . $p['id'] );
                                            $p_type = $p['type'] ?? 'magistral';
                                            $p_icon = $type_icons[ $p_type ] ?? '📄';
                                            ?>
                                            <a href="<?php echo esc_url( $wb_url ); ?>" class="pl-wb-selector-seance-card">
                                                <span class="pl-wb-selector-seance-icon"><?php echo $p_icon; ?></span>
                                                <div>
                                                    <h4><?php echo esc_html( $p['title'] ); ?></h4>
                                                    <span class="pl-badge pl-type-<?php echo esc_attr( $p_type ); ?>"><?php echo esc_html( $type_labels[ $p_type ] ?? $p_type ); ?></span>
                                                </div>
                                                <span class="material-symbols-outlined pl-wb-selector-arrow">arrow_forward</span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            echo '</main>';
            echo '</div>';
            echo self::render_footer();
            return ob_get_clean();
        }

        if ( ! class_exists( 'PedagoLens_Workbench_Admin' ) ) {
            return '<div class="pl-notice pl-notice-error"><p>Le plugin Course Workbench n\'est pas activé.</p></div>';
        }

        // Get the workbench HTML from the admin class
        $workbench_html = PedagoLens_Workbench_Admin::render_front( $project_id );

        ob_start();
        echo self::render_header('Atelier');
        echo '<div class="pl-app-layout">';
        echo self::render_sidebar('workbench');
        echo '<main class="pl-app-main">';
        ?>
    <!-- WORKBENCH CONTENT -->
    <div class="pl-workbench-content">
        <?php echo $workbench_html; ?>
    </div>
        <?php
        echo '</main>';
        echo '</div>';
        echo self::render_footer();
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
        echo self::render_header('Mon Compte');
        echo '<div class="pl-app-layout">';
        echo self::render_sidebar('account');
        echo '<main class="pl-app-main">';
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
        echo '</main>';
        echo '</div>';
        echo self::render_footer();
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
    // Reusable Header & Footer
    // -------------------------------------------------------------------------

    public static function render_header( string $breadcrumb = '' ): string {
        $home_url    = esc_url( home_url( '/' ) );
        $is_logged   = is_user_logged_in();
        $user        = $is_logged ? wp_get_current_user() : null;
        $roles       = $user ? (array) $user->roles : [];
        $is_admin    = in_array( 'administrator', $roles, true );
        $is_teacher  = in_array( 'pedagolens_teacher', $roles, true );
        $is_student  = in_array( 'pedagolens_student', $roles, true );

        $login_page  = get_page_by_path( 'connexion' );
        $login_url   = $login_page ? get_permalink( $login_page ) : wp_login_url();
        $logout_url  = esc_url( wp_logout_url( $home_url ) );

        ob_start();

        // ── Variante 1 : Visiteur (non connecté) ──
        if ( ! $is_logged ) :
            $dash_teacher = esc_url( self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' ) );
        ?>
        <header class="pl-header-visitor" role="banner">
            <nav class="plx-nav" role="navigation" aria-label="Navigation principale">
                <div class="plx-nav-inner">
                    <a href="<?php echo $home_url; ?>" class="plx-nav-logo">
                        <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--header" />
                        P&eacute;dagoLens
                    </a>
                    <ul class="plx-nav-links">
                        <li><a href="<?php echo $home_url; ?>#features">Fonctionnalit&eacute;s</a></li>
                        <li><a href="<?php echo $home_url; ?>#how-it-works">Comment &ccedil;a marche</a></li>
                        <li><a href="<?php echo $home_url; ?>#testimonials">T&eacute;moignages</a></li>
                    </ul>
                    <div class="plx-nav-actions">
                        <a href="<?php echo esc_url( $login_url ); ?>" class="plx-btn plx-btn--ghost">Connexion</a>
                        <a href="<?php echo esc_url( $login_url ); ?>" class="plx-btn plx-btn--primary">Essai gratuit</a>
                    </div>
                </div>
            </nav>
        </header>
        <?php
        // ── Variante 2 & 3 : Utilisateur connecté (étudiant / enseignant / admin) ──
        else :
            $avatar_url = get_avatar_url( $user->ID, [ 'size' => 64 ] );
            $display    = esc_html( $user->display_name );

            // Build breadcrumb HTML
            $bc_html = '';
            if ( $breadcrumb ) {
                $parts   = array_map( 'trim', explode( '>', $breadcrumb ) );
                $last_i  = count( $parts ) - 1;
                $bc_html = '<div class="pl-breadcrumb" aria-label="Fil d\'Ariane">';
                foreach ( $parts as $i => $part ) {
                    if ( $i > 0 ) {
                        $bc_html .= '<span class="pl-breadcrumb-sep material-symbols-outlined">chevron_right</span>';
                    }
                    if ( $i === $last_i ) {
                        $bc_html .= '<span class="pl-breadcrumb-current">' . esc_html( $part ) . '</span>';
                    } else {
                        $bc_html .= '<span>' . esc_html( $part ) . '</span>';
                    }
                }
                $bc_html .= '</div>';
            }
        ?>
        <header class="pl-header-app" role="banner">
            <div class="pl-header-app-left">
                <a href="<?php echo $home_url; ?>" class="pl-header-app-logo-link">
                    <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--header-app" />
                    <span class="pl-header-app-brand">P&eacute;dagoLens AI</span>
                </a>
                <?php if ( $bc_html ) : ?>
                    <span class="pl-header-app-sep material-symbols-outlined">chevron_right</span>
                    <?php echo $bc_html; ?>
                <?php endif; ?>
            </div>
            
            <div class="pl-header-app-right">
                <div class="pl-header-user">
                    <img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="pl-header-avatar" />
                    <span class="pl-header-username"><?php echo $display; ?></span>
                    <a href="<?php echo esc_url( self::page_url( 'compte', '' ) ); ?>" class="pl-header-account-btn" title="Mon compte">
                        <span class="material-symbols-outlined">person</span>
                    </a>
                    <a href="<?php echo $logout_url; ?>" class="pl-header-logout-btn" title="Déconnexion">
                        <span class="material-symbols-outlined">logout</span>
                    </a>
                </div>
            </div>
        </header>
        <?php endif;

        return ob_get_clean();
    }

    public static function render_footer(): string {
        ob_start();
        ?>
        <footer class="pl-app-footer" role="contentinfo">
            <span class="pl-app-footer-logo">P&eacute;dagoLens AI</span>
            <span>&copy; 2026 P&eacute;dagoLens &mdash; Propuls&eacute; par AWS Bedrock</span>
            <nav class="pl-app-footer-links" aria-label="Liens du pied de page">
                <a href="<?php echo esc_url( home_url( '/aide' ) ); ?>">Aide</a>
                <a href="<?php echo esc_url( home_url( '/confidentialite' ) ); ?>">Confidentialit&eacute;</a>
                <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact</a>
            </nav>
        </footer>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Reusable Sidebar — contextual navigation by role
    // -------------------------------------------------------------------------

    public static function render_sidebar( string $active = '' ): string {
        $user       = wp_get_current_user();
        $roles      = (array) $user->roles;
        $is_admin   = in_array( 'administrator', $roles, true );
        $is_teacher = in_array( 'pedagolens_teacher', $roles, true );
        $is_student = in_array( 'pedagolens_student', $roles, true );
        $logout_url = esc_url( wp_logout_url( home_url( '/' ) ) );

        // URLs
        $url_dash_teacher  = esc_url( self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' ) );
        $url_dash_student  = esc_url( self::page_url( 'dashboard-etudiant', '' ) );
        $url_courses       = esc_url( self::page_url( 'cours-projets', 'pl-course-workbench' ) );
        $url_workbench     = esc_url( self::page_url( 'workbench', 'pl-course-workbench' ) );
        $url_history       = esc_url( self::page_url( 'historique', '' ) );
        $url_settings      = esc_url( self::page_url( 'parametres', '' ) );
        $url_institutional = esc_url( self::page_url( 'institutionnel', '' ) );
        $url_account       = esc_url( self::page_url( 'compte', '' ) );

        // Navigation items per role
        if ( $is_admin || $is_teacher ) {
            $nav = [
                [ 'slug' => 'dashboard',     'icon' => 'dashboard',        'label' => 'Dashboard',              'url' => $url_dash_teacher ],
                [ 'slug' => 'courses',        'icon' => 'school',           'label' => 'Analyses IA',            'url' => $url_courses ],
                [ 'slug' => 'workbench',      'icon' => 'build',            'label' => 'Atelier',                'url' => $url_workbench ],
                [ 'slug' => 'lea',            'icon' => 'psychology',       'label' => 'Agent IA L&eacute;a',    'url' => $url_dash_student ],
                [ 'slug' => 'history',        'icon' => 'history',          'label' => 'Historique',             'url' => $url_history ],
                [ 'slug' => 'settings',       'icon' => 'settings',         'label' => 'Param&egrave;tres',      'url' => $url_settings ],
                [ 'slug' => 'institutional',  'icon' => 'account_balance',  'label' => 'Lumi&egrave;re institutionnelle', 'url' => $url_institutional ],
                [ 'slug' => 'account',        'icon' => 'person',           'label' => 'Compte',                 'url' => $url_account ],
            ];
        } else {
            $nav = [
                [ 'slug' => 'dashboard', 'icon' => 'dashboard',   'label' => 'Dashboard',   'url' => $url_dash_student ],
                [ 'slug' => 'twin',      'icon' => 'psychology',  'label' => 'Jumeau IA',   'url' => $url_dash_student ],
                [ 'slug' => 'history',   'icon' => 'history',     'label' => 'Historique',  'url' => $url_history ],
                [ 'slug' => 'account',   'icon' => 'person',      'label' => 'Compte',      'url' => $url_account ],
            ];
        }

        ob_start();
        ?>
        <aside class="pl-app-sidebar" role="navigation" aria-label="Menu principal">
            <div class="pl-app-sidebar-logo">
                <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--sidebar" />
                <span class="pl-app-sidebar-brand">P&eacute;dagoLens AI</span>
            </div>
            <div class="pl-app-sidebar-sub">Portail &Eacute;ducatif</div>

            <?php if ( $is_admin || $is_teacher ) : ?>
            <a href="<?php echo $url_courses; ?>" class="pl-app-sidebar-cta">
                <span class="material-symbols-outlined">add_circle</span>
                Nouvelle Analyse
            </a>
            <?php endif; ?>

            <nav class="pl-app-sidebar-nav">
                <?php foreach ( $nav as $item ) :
                    $cls = 'pl-app-sidebar-link';
                    if ( $active === $item['slug'] ) {
                        $cls .= ' pl-app-sidebar-link--active';
                    }
                ?>
                <a href="<?php echo $item['url']; ?>" class="<?php echo $cls; ?>">
                    <span class="material-symbols-outlined"><?php echo $item['icon']; ?></span>
                    <?php echo $item['label']; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <div class="pl-app-sidebar-bottom">
                <a href="<?php echo esc_url( home_url( '/aide' ) ); ?>" class="pl-app-sidebar-link">
                    <span class="material-symbols-outlined">help</span>
                    Aide
                </a>
                <a href="<?php echo $logout_url; ?>" class="pl-app-sidebar-link">
                    <span class="material-symbols-outlined">logout</span>
                    D&eacute;connexion
                </a>
            </div>
        </aside>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // [pedagolens_login] — Page de connexion / inscription (Stitch)
    // -------------------------------------------------------------------------

    public static function shortcode_login( array $atts ): string {
        // Si déjà connecté, rediriger vers le dashboard approprié
        if ( is_user_logged_in() ) {
            $user  = wp_get_current_user();
            $roles = (array) $user->roles;
            if ( in_array( 'pedagolens_teacher', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
                $url = self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' );
            } else {
                $url = self::page_url( 'dashboard-etudiant', '' );
            }
            return '<script>window.location.href=' . wp_json_encode( $url ) . ';</script>'
                 . '<div class="pl-notice pl-notice-info"><p>Vous &ecirc;tes d&eacute;j&agrave; connect&eacute;. Redirection&hellip;</p></div>';
        }

        $login_nonce    = wp_create_nonce( 'pl_login_nonce' );
        $register_nonce = wp_create_nonce( 'pl_register_nonce' );
        $compte_url     = home_url( '/compte' );
        $lost_pw_url    = esc_url( wp_lostpassword_url() );

        ob_start();
        ?>
<div class="pl-st-login-page">
    <!-- ========== LOGIN HEADER ========== -->
    <header class="pl-login-header">
        <a href="<?php echo esc_url( home_url('/') ); ?>" class="pl-login-header-logo">
            <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--login-header" />
        </a>
        <nav class="pl-login-header-nav">
            <a href="<?php echo esc_url( home_url('/') ); ?>">Accueil</a>
            <a href="<?php echo esc_url( home_url('/#plx-features') ); ?>">Fonctionnalit&eacute;s</a>
            <a href="<?php echo esc_url( home_url('/#plx-how') ); ?>">Comment &ccedil;a marche</a>
        </nav>
    </header>

    <div class="pl-st-login-layout">

        <!-- ========== LEFT: BRANDING ========== -->
        <section class="pl-st-login-branding">
            <div class="pl-st-login-branding-content">
                <div class="pl-st-login-brand-logo">
                    <img src="http://pedagolens.34.199.149.247.nip.io/wp-content/uploads/2026/03/logo.png" alt="PédagoLens" class="pl-logo-img pl-logo-img--login-hero" />
                </div>
                <h1 class="pl-st-login-brand-title">
                    Transformez chaque <span class="pl-st-login-brand-accent">s&eacute;ance de cours</span> gr&acirc;ce &agrave; l'IA.
                </h1>
                <p class="pl-st-login-brand-desc">Analysez vos plans de cours, d&eacute;tectez les zones &agrave; risque et personnalisez l'apprentissage pour chaque &eacute;tudiant — en quelques clics.</p>
                <div class="pl-st-login-brand-widgets">
                    <div class="pl-st-login-widget">
                        <span class="material-symbols-outlined">auto_awesome</span>
                        <div><strong>Analyse intelligente</strong><span>Identifiez les lacunes p&eacute;dagogiques dans vos PowerPoint et PDF.</span></div>
                    </div>
                    <div class="pl-st-login-widget">
                        <span class="material-symbols-outlined">psychology</span>
                        <div><strong>Agent IA L&eacute;a</strong><span>Un jumeau num&eacute;rique qui simule la compr&eacute;hension de vos &eacute;tudiants.</span></div>
                    </div>
                    <div class="pl-st-login-widget">
                        <span class="material-symbols-outlined">trending_up</span>
                        <div><strong>Suivi en temps r&eacute;el</strong><span>Tableaux de bord et rapports d'engagement par s&eacute;ance.</span></div>
                    </div>
                </div>
            </div>
            <div class="pl-st-login-branding-blur pl-st-login-branding-blur--violet"></div>
            <div class="pl-st-login-branding-blur pl-st-login-branding-blur--teal"></div>
        </section>

        <!-- ========== RIGHT: FORM ========== -->
        <section class="pl-st-login-form-section">
            <div class="pl-st-login-form-wrap">

                <!-- Mobile branding -->
                <div class="pl-st-login-mobile-brand">
                    <span class="material-symbols-outlined">auto_awesome</span>
                    <span>P&eacute;dagoLens</span>
                </div>

                <!-- Tabs -->
                <div class="pl-st-login-tabs">
                    <button class="pl-st-login-tab pl-st-login-tab--active" data-tab="login">Connexion</button>
                    <button class="pl-st-login-tab" data-tab="register">Inscription</button>
                </div>

                <!-- ============ LOGIN PANEL ============ -->
                <div class="pl-st-login-panel pl-st-login-panel--active" id="pl-panel-login">
                    <div class="pl-st-login-panel-header">
                        <h2>Bon retour.</h2>
                        <p>Veuillez entrer vos identifiants pour acc&eacute;der au portail &eacute;ducatif.</p>
                    </div>
                    <div id="pl-login-msg" class="pl-st-login-msg" style="display:none;"></div>
                    <form id="pl-login-form" autocomplete="on" novalidate>
                        <input type="hidden" name="_wpnonce" value="<?php echo $login_nonce; ?>" />
                        <div class="pl-st-field">
                            <label for="pl-login-email">Email professionnel</label>
                            <div class="pl-st-input-wrap">
                                <input type="email" id="pl-login-email" name="email" placeholder="nom@etablissement.fr" required />
                                <span class="material-symbols-outlined">mail</span>
                            </div>
                        </div>
                        <div class="pl-st-field">
                            <div class="pl-st-field-header">
                                <label for="pl-login-password">Mot de passe</label>
                                <a href="<?php echo $lost_pw_url; ?>" class="pl-st-field-link">Oubli&eacute; ?</a>
                            </div>
                            <div class="pl-st-input-wrap">
                                <input type="password" id="pl-login-password" name="password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required />
                                <span class="material-symbols-outlined">lock</span>
                            </div>
                        </div>
                        <label class="pl-st-checkbox-row">
                            <input type="checkbox" name="remember" value="1" />
                            <span>Se souvenir de moi</span>
                        </label>
                        <button type="submit" class="pl-st-login-submit">
                            <span>Se connecter</span>
                            <span class="material-symbols-outlined">arrow_forward</span>
                        </button>
                    </form>
                    <p class="pl-st-login-switch">
                        Nouveau sur la plateforme ?
                        <button type="button" class="pl-st-login-switch-btn" data-switch-tab="register">Cr&eacute;er un compte</button>
                    </p>
                </div>

                <!-- ============ REGISTER PANEL ============ -->
                <div class="pl-st-login-panel" id="pl-panel-register">
                    <div class="pl-st-login-panel-header">
                        <h2>Cr&eacute;er un compte</h2>
                        <p>Rejoignez P&eacute;dagoLens en quelques secondes.</p>
                    </div>
                    <div id="pl-register-msg" class="pl-st-login-msg" style="display:none;"></div>

                    <!-- Step 1: Role -->
                    <div id="pl-register-step-role" class="pl-register-step pl-register-step--active">
                        <p class="pl-st-register-prompt">Je suis&hellip;</p>
                        <div class="pl-st-role-cards">
                            <button class="pl-st-role-card" data-role="teacher">
                                <span class="material-symbols-outlined">school</span>
                                <strong>Enseignant</strong>
                                <span>Analysez et am&eacute;liorez vos cours</span>
                            </button>
                            <button class="pl-st-role-card" data-role="student">
                                <span class="material-symbols-outlined">auto_stories</span>
                                <strong>&Eacute;tudiant</strong>
                                <span>Acc&eacute;dez &agrave; votre jumeau num&eacute;rique</span>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Form -->
                    <div id="pl-register-step-form" class="pl-register-step">
                        <button type="button" class="pl-register-back pl-st-back-btn">
                            <span class="material-symbols-outlined">chevron_left</span> Changer de r&ocirc;le
                        </button>
                        <form id="pl-register-form" autocomplete="off" novalidate>
                            <input type="hidden" name="_wpnonce" value="<?php echo $register_nonce; ?>" />
                            <input type="hidden" name="role" id="pl-register-role" value="" />
                            <div class="pl-st-field">
                                <label for="pl-reg-email">Courriel</label>
                                <div class="pl-st-input-wrap">
                                    <input type="email" id="pl-reg-email" name="email" placeholder="votre@courriel.ca" required />
                                    <span class="material-symbols-outlined">mail</span>
                                </div>
                            </div>
                            <div class="pl-st-field">
                                <label for="pl-reg-password">Mot de passe</label>
                                <div class="pl-st-input-wrap">
                                    <input type="password" id="pl-reg-password" name="password" placeholder="Min. 6 caract&egrave;res" required />
                                    <span class="material-symbols-outlined">lock</span>
                                </div>
                                <div class="pl-password-strength" id="pl-password-strength">
                                    <div class="pl-password-strength-bar"><div class="pl-password-strength-fill" id="pl-password-strength-fill"></div></div>
                                    <span class="pl-password-strength-text" id="pl-password-strength-text"></span>
                                </div>
                            </div>
                            <div class="pl-st-field">
                                <label for="pl-reg-password2">Confirmer le mot de passe</label>
                                <div class="pl-st-input-wrap">
                                    <input type="password" id="pl-reg-password2" name="password_confirm" placeholder="Retapez le mot de passe" required />
                                    <span class="material-symbols-outlined">shield</span>
                                </div>
                            </div>
                            <div class="pl-st-field pl-field-student" style="display:none;">
                                <label class="pl-st-checkbox-row">
                                    <input type="checkbox" id="pl-reg-difficulties-check" />
                                    <span>J'ai des difficult&eacute;s d'apprentissage</span>
                                </label>
                            </div>
                            <button type="submit" class="pl-st-login-submit">Cr&eacute;er mon compte</button>
                            <p class="pl-st-register-note">Vous pourrez compl&eacute;ter votre profil apr&egrave;s l'inscription.</p>
                        </form>
                    </div>

                    <p class="pl-st-login-switch">
                        D&eacute;j&agrave; un compte ?
                        <button type="button" class="pl-st-login-switch-btn" data-switch-tab="login">Se connecter</button>
                    </p>
                </div>

            </div>
        </section>

    </div>

    <!-- ============ MODAL DIFFICULTÉS ============ -->
    <div id="pl-difficulties-modal" class="pl-diff-modal" style="display:none;">
        <div class="pl-diff-modal-backdrop"></div>
        <div class="pl-diff-modal-content">
            <button type="button" class="pl-diff-modal-close">&times;</button>
            <h3>&#128203; Mes difficult&eacute;s d'apprentissage</h3>
            <p class="pl-diff-modal-desc">Ces informations aident vos enseignants &agrave; adapter leur p&eacute;dagogie. Tout est confidentiel.</p>
            <div class="pl-diff-options">
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="tdah" /><span>TDAH / Difficult&eacute;s de concentration</span></label>
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="surcharge" /><span>Surcharge cognitive</span></label>
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="allophone" /><span>Langue seconde / Allophone</span></label>
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="faible_autonomie" /><span>Faible autonomie</span></label>
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="anxiete" /><span>Anxi&eacute;t&eacute; face aux consignes</span></label>
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="trouble_apprentissage" /><span>Trouble d'apprentissage</span></label>
                <label class="pl-diff-option"><input type="checkbox" name="diff[]" value="autre" /><span>Autre</span></label>
                <div class="pl-diff-autre-field" style="display:none;">
                    <input type="text" id="pl-diff-autre-text" placeholder="Pr&eacute;cisez&hellip;" />
                </div>
            </div>
            <button type="button" class="pl-diff-modal-save pl-st-login-submit">Enregistrer</button>
        </div>
    </div>

    <script>window.plRegisterRedirect = <?php echo wp_json_encode( $compte_url ); ?>;</script>

    <!-- ========== LOGIN FOOTER ========== -->
    <footer class="pl-login-footer">
        <span>&copy; <?php echo esc_html( date( 'Y' ) ); ?> P&eacute;dagoLens AI</span>
        <div class="pl-login-footer-links">
            <a href="#">Confidentialit&eacute;</a>
            <a href="#">Conditions</a>
            <a href="#">Aide</a>
        </div>
    </footer>

</div><!-- .pl-st-login-page -->
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX — Login
    // -------------------------------------------------------------------------

    public static function ajax_login(): void {
        check_ajax_referer( 'pl_login_nonce' );

        $login    = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
        $password = $_POST['password'] ?? '';

        if ( ! $login || ! $password ) {
            wp_send_json_error( [ 'message' => 'Courriel et mot de passe requis.' ] );
        }

        // Trouver le user par email OU par username
        $user_obj = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
        if ( ! $user_obj ) {
            wp_send_json_error( [ 'message' => 'Identifiants invalides.' ] );
        }

        $creds = [
            'user_login'    => $user_obj->user_login,
            'user_password' => $password,
            'remember'      => true,
        ];

        $signon = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $signon ) ) {
            wp_send_json_error( [ 'message' => 'Identifiants invalides.' ] );
        }

        wp_set_current_user( $signon->ID );

        $roles = (array) $signon->roles;
        if ( in_array( 'pedagolens_teacher', $roles, true ) || in_array( 'administrator', $roles, true ) ) {
            $redirect = self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' );
        } else {
            $redirect = self::page_url( 'dashboard-etudiant', '' );
        }

        wp_send_json_success( [ 'redirect' => $redirect ] );
    }

    // -------------------------------------------------------------------------
    // AJAX — Register
    // -------------------------------------------------------------------------

    public static function ajax_register(): void {
        check_ajax_referer( 'pl_register_nonce' );

        $email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password     = $_POST['password'] ?? '';
        $password2    = $_POST['password_confirm'] ?? '';
        $role         = sanitize_key( $_POST['role'] ?? '' );
        $raw_diff     = isset( $_POST['difficulties'] ) ? sanitize_text_field( wp_unslash( $_POST['difficulties'] ) ) : '[]';

        // Validations
        if ( ! $email || ! $password ) {
            wp_send_json_error( [ 'message' => 'Courriel et mot de passe requis.' ] );
        }
        if ( ! in_array( $role, [ 'teacher', 'student' ], true ) ) {
            wp_send_json_error( [ 'message' => 'R&ocirc;le invalide.' ] );
        }
        if ( strlen( $password ) < 6 ) {
            wp_send_json_error( [ 'message' => 'Le mot de passe doit contenir au moins 6 caract&egrave;res.' ] );
        }
        if ( $password !== $password2 ) {
            wp_send_json_error( [ 'message' => 'Les mots de passe ne correspondent pas.' ] );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( [ 'message' => 'Ce courriel est d&eacute;j&agrave; utilis&eacute;.' ] );
        }
        if ( username_exists( $email ) ) {
            wp_send_json_error( [ 'message' => 'Ce courriel est d&eacute;j&agrave; utilis&eacute;.' ] );
        }

        // Créer le user (display_name = partie avant @ du courriel par défaut)
        $user_id = wp_create_user( $email, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( [ 'message' => $user_id->get_error_message() ] );
        }

        // Mettre à jour le rôle + display_name temporaire
        $wp_role      = $role === 'teacher' ? 'pedagolens_teacher' : 'pedagolens_student';
        $display_name = ucfirst( explode( '@', $email )[0] );
        wp_update_user( [
            'ID'           => $user_id,
            'display_name' => $display_name,
            'role'         => $wp_role,
        ] );

        // Marquer le profil comme incomplet pour forcer la complétion sur /compte
        update_user_meta( $user_id, 'pl_profile_incomplete', '1' );

        // Sauvegarder les difficultés (étudiant)
        if ( $role === 'student' ) {
            $decoded = json_decode( $raw_diff, true );
            if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                $clean = [];
                foreach ( $decoded as $item ) {
                    if ( is_string( $item ) ) {
                        $clean[] = sanitize_key( $item );
                    } elseif ( is_array( $item ) && isset( $item['key'] ) ) {
                        $clean[] = [
                            'key'     => sanitize_key( $item['key'] ),
                            'text'    => sanitize_text_field( $item['text'] ?? '' ),
                            'context' => sanitize_textarea_field( $item['context'] ?? '' ),
                        ];
                    }
                }
                update_user_meta( $user_id, 'pl_student_difficulties', $clean );
            }
        }

        // Connecter automatiquement
        wp_set_auth_cookie( $user_id, true );
        wp_set_current_user( $user_id );

        // Toujours rediriger vers /compte pour compléter le profil
        $redirect = home_url( '/compte' );

        wp_send_json_success( [ 'redirect' => $redirect ] );
    }

    // -------------------------------------------------------------------------
    // [pedagolens_settings] — Page paramètres front (Stitch)
    // -------------------------------------------------------------------------

    public static function shortcode_settings( array $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez être connecté pour accéder aux paramètres.' );
        }

        $user   = wp_get_current_user();
        $roles  = (array) $user->roles;
        $is_admin   = in_array( 'administrator', $roles, true );
        $is_teacher = in_array( 'pedagolens_teacher', $roles, true );

        if ( ! $is_admin && ! $is_teacher ) {
            return '<div class="pl-notice pl-notice-error"><p>Accès réservé aux enseignants.</p></div>';
        }

        $first_name  = esc_html( $user->first_name ?: $user->display_name );
        $email       = esc_html( $user->user_email );
        $avatar_url  = esc_url( get_avatar_url( $user->ID, [ 'size' => 120 ] ) );
        $logout_url  = esc_url( wp_logout_url( home_url( '/' ) ) );

        // URLs sidebar
        $dash_url      = esc_url( self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' ) );
        $courses_url   = esc_url( self::page_url( 'cours-projets', 'pl-course-workbench' ) );
        $workbench_url = esc_url( self::page_url( 'workbench', 'pl-course-workbench' ) );
        $twin_url      = esc_url( self::page_url( 'dashboard-etudiant', '' ) );
        $account_url   = esc_url( self::page_url( 'compte', '' ) );
        $settings_url  = esc_url( self::page_url( 'parametres', '' ) );

        // User settings
        $prefs = (array) get_user_meta( $user->ID, 'pl_teacher_prefs', true );
        $notif_progress  = ! empty( $prefs['notif_progress'] );
        $notif_weekly    = ! empty( $prefs['notif_weekly'] );
        $notif_sms       = ! empty( $prefs['notif_sms'] );
        $proactive_ai    = ! empty( $prefs['proactive_ai'] );
        $dark_mode       = ! empty( $prefs['dark_mode'] );
        $ai_model        = esc_attr( $prefs['ai_model'] ?? 'elite' );
        $ai_tone         = esc_attr( $prefs['ai_tone'] ?? 'academic' );
        $report_detail   = (int) ( $prefs['report_detail'] ?? 4 );
        $institution     = esc_attr( $prefs['institution'] ?? '' );
        $department      = esc_attr( $prefs['department'] ?? '' );
        $language        = esc_attr( $prefs['language'] ?? 'fr' );

        // Profiles (read-only for teachers)
        $profiles = [];
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $profiles = PedagoLens_Profile_Manager::get_all();
        }
        if ( empty( $profiles ) ) {
            $profiles = [
                [ 'slug' => 'tdah',      'name' => 'TDAH',        'icon' => 'neurology',     'desc' => 'Focus sur la gestion de l\'attention et l\'organisation.' ],
                [ 'slug' => 'allophone', 'name' => 'Allophone',   'icon' => 'translate',     'desc' => 'Adaptation linguistique et supports visuels accrus.' ],
                [ 'slug' => 'hpi',       'name' => 'HPI / Avancé', 'icon' => 'rocket_launch', 'desc' => 'Approfondissement critique et défis complexes.' ],
            ];
        }

        $settings_nonce = wp_create_nonce( 'pl_settings_nonce' );

        ob_start();
        echo self::render_header('Paramètres');
        echo '<div class="pl-app-layout">';
        echo self::render_sidebar('settings');
        echo '<main class="pl-app-main">';
        ?>
<div class="pl-st-settings-page">

    <!-- ========== MAIN CONTENT ========== -->
    <div class="pl-st-settings-main">

        <!-- Header -->
        <header class="pl-st-settings-header">
            <h2 class="pl-st-settings-title">Paramètres du Système</h2>
            <p class="pl-st-settings-subtitle">Gérez vos préférences pédagogiques et la configuration de votre intelligence artificielle.</p>
        </header>

        <div id="pl-settings-msg" class="pl-st-settings-msg" style="display:none;"></div>

        <form id="pl-settings-form" class="pl-st-settings-grid" autocomplete="off">
            <input type="hidden" name="_wpnonce" value="<?php echo $settings_nonce; ?>" />

            <!-- ============ LEFT COLUMN ============ -->
            <div class="pl-st-settings-col-left">

                <!-- Profil Enseignant -->
                <section class="pl-st-settings-card">
                    <div class="pl-st-settings-profile-center">
                        <div class="pl-st-settings-avatar-wrap">
                            <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="pl-st-settings-avatar" />
                        </div>
                        <h3 class="pl-st-settings-profile-name"><?php echo esc_html( $user->display_name ); ?></h3>
                        <p class="pl-st-settings-profile-email"><?php echo $email; ?></p>
                        <a href="<?php echo $account_url; ?>" class="pl-st-settings-btn-profile">Modifier le profil</a>
                    </div>
                </section>

                <!-- Institution -->
                <section class="pl-st-settings-card">
                    <div class="pl-st-settings-card-header">
                        <span class="material-symbols-outlined">account_balance</span>
                        <h3>Institution</h3>
                    </div>
                    <div class="pl-st-settings-fields">
                        <div class="pl-st-field-group">
                            <label class="pl-st-field-label">Université / Établissement</label>
                            <input type="text" name="institution" class="pl-st-field-input" value="<?php echo $institution; ?>" placeholder="Université de Paris-Sorbonne" />
                        </div>
                        <div class="pl-st-field-group">
                            <label class="pl-st-field-label">Département</label>
                            <input type="text" name="department" class="pl-st-field-input" value="<?php echo $department; ?>" placeholder="Sciences de l'Éducation" />
                        </div>
                    </div>
                </section>

            </div>

            <!-- ============ RIGHT COLUMN ============ -->
            <div class="pl-st-settings-col-right">

                <!-- Modèles de Profils Élèves (lecture seule) -->
                <section class="pl-st-settings-card pl-st-settings-card--profiles">
                    <div class="pl-st-settings-card-header-row">
                        <div>
                            <h3>Modèles de Profils Élèves</h3>
                            <p class="pl-st-settings-card-desc">Configurez les types d'analyses récurrents.</p>
                        </div>
                        <?php if ( $is_admin ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pl-profiles' ) ); ?>" class="pl-st-settings-link-add">
                                <span class="material-symbols-outlined">add_circle</span>
                                Nouveau modèle
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="pl-st-profiles-grid">
                        <?php foreach ( $profiles as $profile ) :
                            $p_name = esc_html( $profile['name'] ?? '' );
                            $p_icon = esc_attr( $profile['icon'] ?? 'school' );
                            $p_desc = esc_html( $profile['desc'] ?? '' );
                        ?>
                            <div class="pl-st-profile-card">
                                <div class="pl-st-profile-icon-wrap">
                                    <span class="material-symbols-outlined"><?php echo $p_icon; ?></span>
                                </div>
                                <h4><?php echo $p_name; ?></h4>
                                <p><?php echo $p_desc; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Préférences de l'IA -->
                <section class="pl-st-settings-card">
                    <div class="pl-st-settings-card-header">
                        <div class="pl-st-settings-icon-badge pl-st-icon-violet">
                            <span class="material-symbols-outlined">psychology_alt</span>
                        </div>
                        <h3>Préférences de l'IA</h3>
                    </div>
                    <div class="pl-st-settings-ai-grid">
                        <div class="pl-st-settings-ai-col">
                            <div class="pl-st-field-group">
                                <label class="pl-st-field-label">Modèle d'Analyse</label>
                                <select name="ai_model" class="pl-st-field-select">
                                    <option value="elite" <?php selected( $ai_model, 'elite' ); ?>>PédagoLens-4-Elite (Par défaut)</option>
                                    <option value="flash" <?php selected( $ai_model, 'flash' ); ?>>PédagoLens-Flash (Vitesse)</option>
                                    <option value="research" <?php selected( $ai_model, 'research' ); ?>>Modèle de Recherche Académique</option>
                                </select>
                            </div>
                            <div class="pl-st-field-group">
                                <label class="pl-st-field-label">Ton Épistémologique</label>
                                <div class="pl-st-tone-btns">
                                    <button type="button" class="pl-st-tone-btn <?php echo $ai_tone === 'academic' ? 'pl-st-tone-btn--active' : ''; ?>" data-tone="academic">Académique</button>
                                    <button type="button" class="pl-st-tone-btn <?php echo $ai_tone === 'pragmatic' ? 'pl-st-tone-btn--active' : ''; ?>" data-tone="pragmatic">Pragmatique</button>
                                    <button type="button" class="pl-st-tone-btn <?php echo $ai_tone === 'narrative' ? 'pl-st-tone-btn--active' : ''; ?>" data-tone="narrative">Narratif</button>
                                </div>
                                <input type="hidden" name="ai_tone" id="pl-ai-tone" value="<?php echo $ai_tone; ?>" />
                            </div>
                        </div>
                        <div class="pl-st-settings-ai-col">
                            <div class="pl-st-field-group">
                                <label class="pl-st-field-label">Niveau de Détail des Rapports</label>
                                <input type="range" name="report_detail" class="pl-st-field-range" min="1" max="5" value="<?php echo $report_detail; ?>" />
                                <div class="pl-st-range-labels">
                                    <span>SYNTHÉTIQUE</span>
                                    <span>EXHAUSTIF</span>
                                </div>
                            </div>
                            <div class="pl-st-toggle-card">
                                <div class="pl-st-toggle-info">
                                    <span class="pl-st-toggle-title">Suggestions Proactives</span>
                                    <span class="pl-st-toggle-desc">L'IA propose des ajustements en temps réel</span>
                                </div>
                                <label class="pl-st-switch">
                                    <input type="checkbox" name="proactive_ai" value="1" <?php checked( $proactive_ai ); ?> />
                                    <span class="pl-st-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Notifications & Affichage -->
                <div class="pl-st-settings-row-2">
                    <!-- Notifications -->
                    <section class="pl-st-settings-card">
                        <h3 class="pl-st-settings-card-title">Notifications</h3>
                        <div class="pl-st-notif-list">
                            <div class="pl-st-notif-row">
                                <span>Alertes de progression élève</span>
                                <label class="pl-st-switch">
                                    <input type="checkbox" name="notif_progress" value="1" <?php checked( $notif_progress ); ?> />
                                    <span class="pl-st-switch-slider"></span>
                                </label>
                            </div>
                            <div class="pl-st-notif-row">
                                <span>Rapports hebdomadaires</span>
                                <label class="pl-st-switch">
                                    <input type="checkbox" name="notif_weekly" value="1" <?php checked( $notif_weekly ); ?> />
                                    <span class="pl-st-switch-slider"></span>
                                </label>
                            </div>
                            <div class="pl-st-notif-row pl-st-notif-row--disabled">
                                <span>Alertes système par SMS</span>
                                <label class="pl-st-switch">
                                    <input type="checkbox" name="notif_sms" value="1" <?php checked( $notif_sms ); ?> disabled />
                                    <span class="pl-st-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Préférences d'affichage -->
                    <section class="pl-st-settings-card">
                        <h3 class="pl-st-settings-card-title">Affichage &amp; Langue</h3>
                        <div class="pl-st-settings-fields">
                            <div class="pl-st-field-group">
                                <label class="pl-st-field-label">Langue</label>
                                <select name="language" class="pl-st-field-select">
                                    <option value="fr" <?php selected( $language, 'fr' ); ?>>Français</option>
                                    <option value="en" <?php selected( $language, 'en' ); ?>>English</option>
                                </select>
                            </div>
                            <div class="pl-st-toggle-card">
                                <div class="pl-st-toggle-info">
                                    <span class="pl-st-toggle-title">Thème sombre</span>
                                    <span class="pl-st-toggle-desc">Interface en mode nuit</span>
                                </div>
                                <label class="pl-st-switch">
                                    <input type="checkbox" name="dark_mode" value="1" <?php checked( $dark_mode ); ?> />
                                    <span class="pl-st-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Action Footer -->
                <footer class="pl-st-settings-actions">
                    <button type="button" class="pl-st-settings-btn-cancel" id="pl-settings-cancel">Annuler les modifications</button>
                    <button type="submit" class="pl-st-settings-btn-save">Sauvegarder les paramètres</button>
                </footer>

            </div>

        </form>

    </div>

</div><!-- .pl-st-settings-page -->
        <?php
        echo '</main>';
        echo '</div>';
        echo self::render_footer();
        return ob_get_clean();
    }

    /**
     * Alias francophone pour [pedagolens_parametres].
     */
    public static function shortcode_parametres( array $atts = [] ): string {
        return self::shortcode_settings( $atts );
    }

    // -------------------------------------------------------------------------
    // AJAX — Sauvegarde paramètres front (enseignant)
    // -------------------------------------------------------------------------

    public static function ajax_save_settings(): void {
        check_ajax_referer( 'pl_settings_nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Non authentifié.' ] );
        }

        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if ( ! in_array( 'administrator', $roles, true ) && ! in_array( 'pedagolens_teacher', $roles, true ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ] );
        }

        $prefs = (array) get_user_meta( $user->ID, 'pl_teacher_prefs', true );

        // Checkboxes / toggles
        $prefs['notif_progress'] = ! empty( $_POST['notif_progress'] );
        $prefs['notif_weekly']   = ! empty( $_POST['notif_weekly'] );
        $prefs['notif_sms']      = ! empty( $_POST['notif_sms'] );
        $prefs['proactive_ai']   = ! empty( $_POST['proactive_ai'] );
        $prefs['dark_mode']      = ! empty( $_POST['dark_mode'] );

        // Text / select fields
        $allowed_models = [ 'elite', 'flash', 'research' ];
        $allowed_tones  = [ 'academic', 'pragmatic', 'narrative' ];
        $allowed_langs  = [ 'fr', 'en' ];

        $model = sanitize_key( $_POST['ai_model'] ?? 'elite' );
        $prefs['ai_model'] = in_array( $model, $allowed_models, true ) ? $model : 'elite';

        $tone = sanitize_key( $_POST['ai_tone'] ?? 'academic' );
        $prefs['ai_tone'] = in_array( $tone, $allowed_tones, true ) ? $tone : 'academic';

        $lang = sanitize_key( $_POST['language'] ?? 'fr' );
        $prefs['language'] = in_array( $lang, $allowed_langs, true ) ? $lang : 'fr';

        $prefs['report_detail'] = max( 1, min( 5, (int) ( $_POST['report_detail'] ?? 4 ) ) );
        $prefs['institution']   = sanitize_text_field( wp_unslash( $_POST['institution'] ?? '' ) );
        $prefs['department']    = sanitize_text_field( wp_unslash( $_POST['department'] ?? '' ) );

        update_user_meta( $user->ID, 'pl_teacher_prefs', $prefs );

        wp_send_json_success( [ 'message' => 'Paramètres enregistrés.' ] );
    }

    // -------------------------------------------------------------------------
    // Course CRUD — Front-end AJAX handlers (Task 16)
    // -------------------------------------------------------------------------

    public static function ajax_create_course_front(): void {
        check_ajax_referer( 'pl_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
        }

        $title   = sanitize_text_field( $_POST['title'] ?? '' );
        $code    = sanitize_text_field( $_POST['code'] ?? '' );
        $session = sanitize_text_field( $_POST['session'] ?? '' );
        $desc    = sanitize_textarea_field( $_POST['description'] ?? '' );
        $type    = sanitize_text_field( $_POST['course_type'] ?? 'magistral' );

        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => 'Le titre du cours est requis.' ] );
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'pl_course',
            'post_title'   => $title,
            'post_content' => $desc,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Erreur lors de la création du cours.' ] );
        }

        update_post_meta( $post_id, '_pl_course_code', $code );
        update_post_meta( $post_id, '_pl_session', $session );
        update_post_meta( $post_id, '_pl_course_type', $type );

        wp_send_json_success( [
            'message'   => 'Cours créé avec succès !',
            'course_id' => $post_id,
            'title'     => $title,
        ] );
    }

    public static function ajax_update_course_front(): void {
        check_ajax_referer( 'pl_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( ! $course_id || get_post_type( $course_id ) !== 'pl_course' ) {
            wp_send_json_error( [ 'message' => 'Cours introuvable.' ] );
        }

        $title   = sanitize_text_field( $_POST['title'] ?? '' );
        $code    = sanitize_text_field( $_POST['code'] ?? '' );
        $session = sanitize_text_field( $_POST['session'] ?? '' );
        $desc    = sanitize_textarea_field( $_POST['description'] ?? '' );
        $type    = sanitize_text_field( $_POST['course_type'] ?? 'magistral' );

        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => 'Le titre est requis.' ] );
        }

        wp_update_post( [
            'ID'           => $course_id,
            'post_title'   => $title,
            'post_content' => $desc,
        ] );

        update_post_meta( $course_id, '_pl_course_code', $code );
        update_post_meta( $course_id, '_pl_session', $session );
        update_post_meta( $course_id, '_pl_course_type', $type );

        wp_send_json_success( [ 'message' => 'Cours mis à jour !', 'course_id' => $course_id ] );
    }

    public static function ajax_delete_course_front(): void {
        check_ajax_referer( 'pl_nonce', 'nonce' );

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( ! $course_id || get_post_type( $course_id ) !== 'pl_course' ) {
            wp_send_json_error( [ 'message' => 'Cours introuvable.' ] );
        }

        // Delete associated projects
        $projects = get_posts( [
            'post_type'      => 'pl_project',
            'posts_per_page' => -1,
            'meta_query'     => [ [ 'key' => '_pl_course_id', 'value' => $course_id ] ],
        ] );
        foreach ( $projects as $p ) {
            wp_delete_post( $p->ID, true );
        }

        wp_delete_post( $course_id, true );

        wp_send_json_success( [ 'message' => 'Cours supprimé.' ] );
    }

    // ── Task 17: Create project from front-end ──────────────────────────

    public static function ajax_create_project_front(): void {
        check_ajax_referer( 'pl_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( ! $course_id || get_post_type( $course_id ) !== 'pl_course' ) {
            wp_send_json_error( [ 'message' => 'Cours introuvable.' ] );
        }

        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $type  = sanitize_text_field( $_POST['project_type'] ?? 'magistral' );
        $desc  = sanitize_textarea_field( $_POST['description'] ?? '' );

        if ( empty( $title ) ) {
            wp_send_json_error( [ 'message' => 'Le titre de la séance est requis.' ] );
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'pl_project',
            'post_title'   => $title,
            'post_content' => $desc,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Erreur lors de la création.' ] );
        }

        update_post_meta( $post_id, '_pl_course_id', $course_id );
        update_post_meta( $post_id, '_pl_project_type', $type );
        update_post_meta( $post_id, '_pl_created_at', current_time( 'mysql' ) );

        // Handle file upload
        $files_urls = [];
        if ( ! empty( $_FILES['project_files'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $file_count = is_array( $_FILES['project_files']['name'] )
                ? count( $_FILES['project_files']['name'] )
                : 1;

            for ( $i = 0; $i < $file_count; $i++ ) {
                $file = [
                    'name'     => is_array( $_FILES['project_files']['name'] ) ? $_FILES['project_files']['name'][ $i ] : $_FILES['project_files']['name'],
                    'type'     => is_array( $_FILES['project_files']['type'] ) ? $_FILES['project_files']['type'][ $i ] : $_FILES['project_files']['type'],
                    'tmp_name' => is_array( $_FILES['project_files']['tmp_name'] ) ? $_FILES['project_files']['tmp_name'][ $i ] : $_FILES['project_files']['tmp_name'],
                    'error'    => is_array( $_FILES['project_files']['error'] ) ? $_FILES['project_files']['error'][ $i ] : $_FILES['project_files']['error'],
                    'size'     => is_array( $_FILES['project_files']['size'] ) ? $_FILES['project_files']['size'][ $i ] : $_FILES['project_files']['size'],
                ];
                $_FILES['upload_file'] = $file;
                $upload = wp_handle_upload( $file, [ 'test_form' => false ] );
                if ( ! empty( $upload['url'] ) ) {
                    $files_urls[] = $upload['url'];
                }
            }
        }

        if ( ! empty( $files_urls ) ) {
            update_post_meta( $post_id, '_pl_files', wp_json_encode( $files_urls ) );
        }

        wp_send_json_success( [
            'message'    => 'Séance créée avec succès !',
            'project_id' => $post_id,
        ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // [pedagolens_history] — Historique des analyses & sessions (Stitch)
    // -------------------------------------------------------------------------

    public static function shortcode_history( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez être connecté pour accéder à l\'historique.' );
        }

        $user       = wp_get_current_user();
        $roles      = (array) $user->roles;
        $is_admin   = in_array( 'administrator', $roles, true );
        $is_teacher = in_array( 'pedagolens_teacher', $roles, true );
        $is_student = in_array( 'pedagolens_student', $roles, true );
        $first_name = esc_html( $user->first_name ?: $user->display_name );
        $logout_url = esc_url( wp_logout_url( home_url( '/' ) ) );

        // Navigation URLs
        $dash_url      = esc_url( self::page_url( 'dashboard-enseignant', 'pl-teacher-dashboard' ) );
        $courses_url   = esc_url( self::page_url( 'cours-projets', 'pl-course-workbench' ) );
        $workbench_url = esc_url( self::page_url( 'workbench', 'pl-course-workbench' ) );
        $twin_url      = esc_url( self::page_url( 'dashboard-etudiant', '' ) );
        $account_url   = esc_url( self::page_url( 'compte', '' ) );
        $history_url   = esc_url( self::page_url( 'historique', '' ) );

        // Pagination
        $per_page     = 10;
        $current_page = max( 1, (int) ( $_GET['pl_page'] ?? 1 ) );
        $offset       = ( $current_page - 1 ) * $per_page;

        // Filter params
        $filter_type  = sanitize_text_field( $_GET['pl_type'] ?? 'all' );
        $filter_sort  = sanitize_text_field( $_GET['pl_sort'] ?? 'newest' );

        // Course filter (from Cours & Projets page)
        $filter_course       = (int) ( $_GET['course_id'] ?? 0 );
        $filter_course_title = $filter_course ? get_the_title( $filter_course ) : '';

        // ── Build unified timeline ──────────────────────────────
        $timeline = [];

        // Analyses (pl_analysis)
        if ( $filter_type === 'all' || $filter_type === 'analysis' ) {
            $analysis_args = [
                'post_type'      => 'pl_analysis',
                'posts_per_page' => 100,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];
            // Teachers/admins see all; students see only their own
            if ( $is_student ) {
                $analysis_args['author'] = $user->ID;
            }
            if ( $filter_course ) {
                $analysis_args['meta_query'] = array_merge(
                    $analysis_args['meta_query'] ?? [],
                    [ [ 'key' => '_pl_course_id', 'value' => $filter_course, 'type' => 'NUMERIC' ] ]
                );
            }
            $analyses = get_posts( $analysis_args );
            foreach ( $analyses as $a ) {
                $scores  = get_post_meta( $a->ID, '_pl_profile_scores', true );
                $summary = get_post_meta( $a->ID, '_pl_summary', true );
                $course_id = (int) get_post_meta( $a->ID, '_pl_course_id', true );
                $course_title = $course_id ? get_the_title( $course_id ) : '';

                // Determine risk level from scores
                $risk = 'low';
                if ( is_array( $scores ) ) {
                    $avg = count( $scores ) > 0 ? array_sum( $scores ) / count( $scores ) : 100;
                    if ( $avg < 40 ) $risk = 'high';
                    elseif ( $avg < 70 ) $risk = 'medium';
                }

                $timeline[] = [
                    'id'        => $a->ID,
                    'type'      => 'analysis',
                    'title'     => esc_html( $a->post_title ),
                    'subtitle'  => $course_title ? esc_html( $course_title ) : '',
                    'date'      => $a->post_date,
                    'date_fmt'  => date_i18n( 'j M Y', strtotime( $a->post_date ) ),
                    'icon'      => 'analytics',
                    'badge'     => 'Analyse IA',
                    'risk'      => $risk,
                    'summary'   => $summary ? esc_html( wp_trim_words( $summary, 20 ) ) : '',
                ];
            }
        }

        // Sessions jumeau (pl_interaction)
        if ( $filter_type === 'all' || $filter_type === 'session' ) {
            $session_args = [
                'post_type'      => 'pl_interaction',
                'posts_per_page' => 100,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];
            if ( $is_student ) {
                $session_args['meta_query'] = [
                    [ 'key' => '_pl_student_id', 'value' => $user->ID ],
                ];
            }
            if ( $filter_course ) {
                $session_args['meta_query'] = array_merge(
                    $session_args['meta_query'] ?? [],
                    [ [ 'key' => '_pl_course_id', 'value' => $filter_course, 'type' => 'NUMERIC' ] ]
                );
            }
            $sessions = get_posts( $session_args );
            foreach ( $sessions as $s ) {
                $messages   = get_post_meta( $s->ID, '_pl_messages', true );
                $msg_count  = is_array( $messages ) ? count( $messages ) : 0;
                $started    = get_post_meta( $s->ID, '_pl_started_at', true );
                $ended      = get_post_meta( $s->ID, '_pl_ended_at', true );
                $course_id  = (int) get_post_meta( $s->ID, '_pl_course_id', true );
                $course_title = $course_id ? get_the_title( $course_id ) : '';

                $timeline[] = [
                    'id'        => $s->ID,
                    'type'      => 'session',
                    'title'     => esc_html( $s->post_title ),
                    'subtitle'  => $course_title ? esc_html( $course_title ) : '',
                    'date'      => $s->post_date,
                    'date_fmt'  => date_i18n( 'j M Y', strtotime( $s->post_date ) ),
                    'icon'      => 'psychology',
                    'badge'     => 'Session jumeau',
                    'risk'      => 'none',
                    'summary'   => $msg_count > 0
                        ? sprintf( '%d message%s échangé%s', $msg_count, $msg_count > 1 ? 's' : '', $msg_count > 1 ? 's' : '' )
                        : 'Session vide',
                ];
            }
        }

        // Sort
        usort( $timeline, function( $a, $b ) use ( $filter_sort ) {
            return $filter_sort === 'oldest'
                ? strtotime( $a['date'] ) - strtotime( $b['date'] )
                : strtotime( $b['date'] ) - strtotime( $a['date'] );
        } );

        $total       = count( $timeline );
        $total_pages = max( 1, (int) ceil( $total / $per_page ) );
        $items       = array_slice( $timeline, $offset, $per_page );

        // Risk badge helper
        $risk_html = function( string $risk ): string {
            return match( $risk ) {
                'high'   => '<div class="pl-hi-risk pl-hi-risk--high"><span class="pl-hi-risk-dot"></span>Élevé</div>',
                'medium' => '<div class="pl-hi-risk pl-hi-risk--medium"><span class="pl-hi-risk-dot"></span>Moyen</div>',
                'low'    => '<div class="pl-hi-risk pl-hi-risk--low"><span class="pl-hi-risk-dot"></span>Faible</div>',
                default  => '<div class="pl-hi-risk pl-hi-risk--none"><span class="pl-hi-risk-dot"></span>N/A</div>',
            };
        };

        // Type icon bg class
        $icon_class = function( string $type ): string {
            return $type === 'analysis' ? 'pl-hi-icon--analysis' : 'pl-hi-icon--session';
        };

        $base_url = remove_query_arg( [ 'pl_page', 'pl_type', 'pl_sort' ] );

        ob_start();
        echo self::render_header('Historique');
        echo '<div class="pl-app-layout">';
        echo self::render_sidebar('history');
        echo '<main class="pl-app-main">';
        ?>
<div class="pl-hi-page">

    <!-- ========== MAIN ========== -->
    <div class="pl-hi-main">

        <!-- Header -->
        <header class="pl-hi-header">
            <div class="pl-hi-header-text">
                <h1 class="pl-hi-title">Historique des analyses</h1>
                <p class="pl-hi-subtitle">Consultez, comparez et g&eacute;rez vos pr&eacute;c&eacute;dentes &eacute;valuations p&eacute;dagogiques assist&eacute;es par l'intelligence artificielle.</p>
            </div>
            <div class="pl-hi-search-wrap">
                <span class="material-symbols-outlined pl-hi-search-icon">search</span>
                <input type="text" class="pl-hi-search" placeholder="Rechercher une analyse..." id="pl-hi-search-input" />
            </div>
        </header>

        <?php if ( $filter_course && $filter_course_title ) : ?>
        <div class="pl-hi-course-filter-banner">
            <span class="material-symbols-outlined">filter_alt</span>
            Filtré par cours : <strong><?php echo esc_html( $filter_course_title ); ?></strong>
            <a href="<?php echo esc_url( remove_query_arg( 'course_id' ) ); ?>" class="pl-hi-clear-filter">
                <span class="material-symbols-outlined">close</span> Voir tout
            </a>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <section class="pl-hi-filters">
            <div class="pl-hi-filters-label">
                <span>Filtrer par :</span>
            </div>
            <form method="get" action="" class="pl-hi-filters-form">
                <?php if ( $filter_course ) : ?>
                    <input type="hidden" name="course_id" value="<?php echo $filter_course; ?>" />
                <?php endif; ?>
                <select name="pl_type" class="pl-hi-select" onchange="this.form.submit()">
                    <option value="all" <?php selected( $filter_type, 'all' ); ?>>Tous les types</option>
                    <option value="analysis" <?php selected( $filter_type, 'analysis' ); ?>>Analyses IA</option>
                    <option value="session" <?php selected( $filter_type, 'session' ); ?>>Sessions jumeau</option>
                </select>
                <select name="pl_sort" class="pl-hi-select" onchange="this.form.submit()">
                    <option value="newest" <?php selected( $filter_sort, 'newest' ); ?>>Date : Plus r&eacute;cent</option>
                    <option value="oldest" <?php selected( $filter_sort, 'oldest' ); ?>>Date : Plus ancien</option>
                </select>
                <button type="button" class="pl-hi-reset-btn" onclick="window.location.href='<?php echo esc_url( $base_url ); ?>'">
                    <span class="material-symbols-outlined">refresh</span> R&eacute;initialiser
                </button>
            </form>
        </section>

        <!-- Table header -->
        <div class="pl-hi-table-head">
            <div class="pl-hi-th pl-hi-th--details">D&eacute;tails</div>
            <div class="pl-hi-th pl-hi-th--date">Date</div>
            <div class="pl-hi-th pl-hi-th--type">Type</div>
            <div class="pl-hi-th pl-hi-th--risk">Risque IA</div>
            <div class="pl-hi-th pl-hi-th--actions">Actions</div>
        </div>

        <!-- Rows -->
        <div class="pl-hi-rows" id="pl-hi-rows">
        <?php if ( empty( $items ) ) : ?>
            <div class="pl-hi-empty">
                <span class="material-symbols-outlined">inbox</span>
                <p>Aucun &eacute;l&eacute;ment dans l'historique.</p>
            </div>
        <?php else : ?>
            <?php foreach ( $items as $item ) : ?>
            <div class="pl-hi-row" data-title="<?php echo esc_attr( strtolower( $item['title'] . ' ' . $item['subtitle'] ) ); ?>">
                <div class="pl-hi-cell pl-hi-cell--details">
                    <div class="pl-hi-icon-wrap <?php echo $icon_class( $item['type'] ); ?>">
                        <span class="material-symbols-outlined"><?php echo esc_html( $item['icon'] ); ?></span>
                    </div>
                    <div>
                        <h3 class="pl-hi-row-title"><?php echo $item['title']; ?></h3>
                        <p class="pl-hi-row-sub"><?php echo $item['subtitle'] ?: $item['summary']; ?></p>
                    </div>
                </div>
                <div class="pl-hi-cell pl-hi-cell--date"><?php echo $item['date_fmt']; ?></div>
                <div class="pl-hi-cell pl-hi-cell--type">
                    <span class="pl-hi-badge"><?php echo $item['badge']; ?></span>
                </div>
                <div class="pl-hi-cell pl-hi-cell--risk"><?php echo $risk_html( $item['risk'] ); ?></div>
                <div class="pl-hi-cell pl-hi-cell--actions">
                    <button class="pl-hi-action-btn" title="Voir le d&eacute;tail"><span class="material-symbols-outlined">visibility</span></button>
                    <button class="pl-hi-action-btn" title="Dupliquer"><span class="material-symbols-outlined">content_copy</span></button>
                    <button class="pl-hi-action-btn" title="Exporter"><span class="material-symbols-outlined">download</span></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
        <div class="pl-hi-pagination">
            <span class="pl-hi-pagination-info">
                Affichage de <?php echo $offset + 1; ?>-<?php echo min( $offset + $per_page, $total ); ?> sur <?php echo $total; ?> &eacute;l&eacute;ments
            </span>
            <div class="pl-hi-pagination-btns">
                <?php if ( $current_page > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'pl_page', $current_page - 1 ) ); ?>" class="pl-hi-page-btn">
                        <span class="material-symbols-outlined">chevron_left</span>
                    </a>
                <?php endif; ?>
                <?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'pl_page', $p ) ); ?>"
                       class="pl-hi-page-btn <?php echo $p === $current_page ? 'pl-hi-page-btn--active' : ''; ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
                <?php if ( $current_page < $total_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'pl_page', $current_page + 1 ) ); ?>" class="pl-hi-page-btn">
                        <span class="material-symbols-outlined">chevron_right</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
(function(){
    const input = document.getElementById('pl-hi-search-input');
    if (!input) return;
    input.addEventListener('input', function(){
        const q = this.value.toLowerCase();
        document.querySelectorAll('.pl-hi-row').forEach(function(row){
            row.style.display = row.dataset.title.includes(q) ? '' : 'none';
        });
    });
})();
</script>
        <?php
        echo '</main>';
        echo '</div>';
        echo self::render_footer();
        return ob_get_clean();
    }

    /** Alias francophone */
    public static function shortcode_historique( array $atts ): string {
        return self::shortcode_history( $atts );
    }

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

    // -------------------------------------------------------------------------
    // [pedagolens_institutional] — Lumière institutionnelle (Stitch)
    // -------------------------------------------------------------------------

    public static function shortcode_institutional( array $atts = [] ): string {
        if ( ! is_user_logged_in() ) {
            return self::render_login_notice( 'Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; la vue institutionnelle.' );
        }

        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if ( ! in_array( 'administrator', $roles, true ) && ! in_array( 'pedagolens_teacher', $roles, true ) ) {
            return '<div class="pl-notice pl-notice-warning"><p>Acc&egrave;s r&eacute;serv&eacute; aux administrateurs et enseignants.</p></div>';
        }

        // ── Aggregate data from pl_analysis CPT ─────────────────────────
        $all_analyses = get_posts( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );

        $total_analyses   = count( $all_analyses );
        $total_courses    = (int) ( wp_count_posts( 'pl_course' )->publish ?? 0 );
        $total_projects   = (int) ( wp_count_posts( 'pl_project' )->publish ?? 0 );

        // Profile scores aggregation
        $profile_labels = [
            'visuel'       => 'Visuel-Spatial',
            'auditif'      => 'Auditif-Verbal',
            'kinesthesique'=> 'Kinesthésique',
            'lecture'       => 'Lecture-Écriture',
            'social'       => 'Social',
            'solitaire'    => 'Solitaire',
            'logique'      => 'Logique-Math',
        ];
        $profile_keys   = array_keys( $profile_labels );
        $profile_totals = array_fill_keys( $profile_keys, 0 );
        $profile_counts = array_fill_keys( $profile_keys, 0 );

        // Monthly trend data (last 6 months)
        $monthly_scores = [];
        $now            = current_time( 'timestamp' );
        for ( $i = 5; $i >= 0; $i-- ) {
            $key = wp_date( 'Y-m', strtotime( "-{$i} months", $now ) );
            $monthly_scores[ $key ] = [ 'total' => 0, 'count' => 0 ];
        }

        // Recommendations aggregation
        $recommendations_count = [];

        foreach ( $all_analyses as $analysis ) {
            $scores = get_post_meta( $analysis->ID, '_pl_scores', true );
            if ( is_array( $scores ) ) {
                foreach ( $profile_keys as $pk ) {
                    if ( isset( $scores[ $pk ] ) && is_numeric( $scores[ $pk ] ) ) {
                        $profile_totals[ $pk ] += (float) $scores[ $pk ];
                        $profile_counts[ $pk ]++;
                    }
                }
            }

            // Single global score
            $global = get_post_meta( $analysis->ID, '_pl_global_score', true );
            if ( ! $global && is_array( $scores ) && ! empty( $scores ) ) {
                $numeric = array_filter( $scores, 'is_numeric' );
                $global  = $numeric ? round( array_sum( $numeric ) / count( $numeric ) ) : 0;
            }

            // Monthly trend
            $month_key = wp_date( 'Y-m', strtotime( $analysis->post_date ) );
            if ( isset( $monthly_scores[ $month_key ] ) ) {
                $monthly_scores[ $month_key ]['total'] += (float) $global;
                $monthly_scores[ $month_key ]['count']++;
            }

            // Recommendations
            $recs = get_post_meta( $analysis->ID, '_pl_recommendations', true );
            if ( is_array( $recs ) ) {
                foreach ( $recs as $rec ) {
                    $label = is_array( $rec ) ? ( $rec['text'] ?? $rec['label'] ?? '' ) : (string) $rec;
                    $label = wp_strip_all_tags( $label );
                    if ( $label ) {
                        $short = mb_substr( $label, 0, 80 );
                        $recommendations_count[ $short ] = ( $recommendations_count[ $short ] ?? 0 ) + 1;
                    }
                }
            }
        }

        // Compute averages
        $profile_averages = [];
        foreach ( $profile_keys as $pk ) {
            $profile_averages[ $pk ] = $profile_counts[ $pk ] > 0
                ? round( $profile_totals[ $pk ] / $profile_counts[ $pk ] )
                : 0;
        }
        $global_avg = $total_analyses > 0
            ? round( array_sum( $profile_totals ) / max( 1, array_sum( $profile_counts ) ) )
            : 0;

        // Monthly averages for chart
        $monthly_avgs = [];
        foreach ( $monthly_scores as $mk => $mv ) {
            $monthly_avgs[ $mk ] = $mv['count'] > 0 ? round( $mv['total'] / $mv['count'] ) : 0;
        }
        $chart_max = max( 1, max( $monthly_avgs ?: [ 1 ] ) );

        // Top recommendations
        arsort( $recommendations_count );
        $top_recs = array_slice( $recommendations_count, 0, 5, true );

        // Most impacted profiles (lowest scores)
        asort( $profile_averages );
        $impacted = array_slice( $profile_averages, 0, 3, true );

        // Profile colors for chart bars
        $profile_colors = [
            'visuel'        => 'var(--pl-secondary)',
            'auditif'       => 'var(--pl-primary)',
            'kinesthesique' => 'var(--pl-tertiary)',
            'lecture'        => 'var(--pl-primary-light)',
            'social'        => '#f59e0b',
            'solitaire'    => '#6366f1',
            'logique'      => '#06b6d4',
        ];

        $first_name = esc_html( $user->first_name ?: $user->display_name );

        ob_start();
        echo self::render_header('Lumière institutionnelle');
        echo '<div class="pl-app-layout">';
        echo self::render_sidebar('institutional');
        echo '<main class="pl-app-main">';
        ?>
<div class="pl-inst">
<div class="pl-inst-inner">

    <!-- ========== PAGE HEADER ========== -->
    <header class="pl-inst-header">
        <div class="pl-inst-header-title-row">
            <span class="material-symbols-outlined pl-inst-header-icon">account_balance</span>
            <h1 class="pl-inst-page-title">
                Lumi&egrave;re institutionnelle
            </h1>
        </div>
        <p class="pl-inst-page-subtitle">
            Vue d'ensemble agr&eacute;g&eacute;e des analyses p&eacute;dagogiques &mdash; <?php echo $first_name; ?>
        </p>
    </header>

    <!-- ========== KPI CARDS ========== -->
    <div class="pl-inst-kpi-grid">

        <?php
        $kpis = [
            [ 'icon' => 'menu_book',   'value' => $total_courses,  'label' => 'Cours analys&eacute;s',  'color' => 'var(--pl-primary)' ],
            [ 'icon' => 'analytics',   'value' => $total_analyses, 'label' => 'Analyses effectu&eacute;es', 'color' => 'var(--pl-secondary)' ],
            [ 'icon' => 'speed',       'value' => $global_avg . '%', 'label' => 'Score moyen global',    'color' => 'var(--pl-tertiary)' ],
            [ 'icon' => 'folder_open', 'value' => $total_projects, 'label' => 'Projets actifs',         'color' => '#f59e0b' ],
        ];
        foreach ( $kpis as $kpi ) : ?>
            <div class="pl-inst-kpi-card">
                <div class="pl-inst-kpi-icon" style="background:<?php echo $kpi['color']; ?>;">
                    <span class="material-symbols-outlined"><?php echo $kpi['icon']; ?></span>
                </div>
                <div class="pl-inst-kpi-info">
                    <span class="pl-inst-kpi-value"><?php echo $kpi['value']; ?></span>
                    <span class="pl-inst-kpi-label"><?php echo $kpi['label']; ?></span>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- ========== TWO-COLUMN: PROFILE SCORES + TREND ========== -->
    <div class="pl-inst-two-col">

        <!-- Profile Averages -->
        <div class="pl-inst-profiles">
            <h2 class="pl-inst-card-title">
                <span class="material-symbols-outlined">equalizer</span>
                Score moyen par profil
            </h2>
            <div class="pl-inst-profiles-list">
                <?php foreach ( $profile_keys as $pk ) :
                    $avg   = $profile_averages[ $pk ];
                    $color = $profile_colors[ $pk ] ?? 'var(--pl-primary)';
                    $label = esc_html( $profile_labels[ $pk ] );
                ?>
                    <div class="pl-inst-profile-row">
                        <div class="pl-inst-profile-meta">
                            <span class="pl-inst-profile-label"><?php echo $label; ?></span>
                            <span class="pl-inst-profile-value"><?php echo $avg; ?>%</span>
                        </div>
                        <div class="pl-inst-profile-bar-bg">
                            <div class="pl-inst-profile-bar-fill" style="width:<?php echo $avg; ?>%;background:<?php echo $color; ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <div class="pl-inst-trend">
            <h2 class="pl-inst-card-title">
                <span class="material-symbols-outlined">trending_up</span>
                Tendance des scores (6 mois)
            </h2>
            <div class="pl-inst-trend-chart">
                <?php foreach ( $monthly_avgs as $mk => $mv ) :
                    $pct       = $chart_max > 0 ? round( ( $mv / $chart_max ) * 100 ) : 0;
                    $month_lbl = wp_date( 'M', strtotime( $mk . '-01' ) );
                ?>
                    <div class="pl-inst-trend-col">
                        <span class="pl-inst-trend-value"><?php echo $mv; ?></span>
                        <div class="pl-inst-trend-bar-bg">
                            <div class="pl-inst-trend-bar-fill" style="height:<?php echo max( 4, $pct ); ?>%;"></div>
                        </div>
                        <span class="pl-inst-trend-month"><?php echo esc_html( $month_lbl ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ========== TWO-COLUMN: TOP RECS + IMPACTED PROFILES ========== -->
    <div class="pl-inst-two-col pl-inst-two-col--recs">

        <!-- Top Recommendations -->
        <div class="pl-inst-recs">
            <h2 class="pl-inst-card-title">
                <span class="material-symbols-outlined">auto_awesome</span>
                Recommandations r&eacute;currentes
            </h2>
            <?php if ( empty( $top_recs ) ) : ?>
                <div class="pl-inst-empty">
                    <span class="material-symbols-outlined">inbox</span>
                    <p>Aucune recommandation disponible.</p>
                </div>
            <?php else : ?>
                <div class="pl-inst-recs-list">
                    <?php $rank = 0; foreach ( $top_recs as $rec_text => $rec_count ) : $rank++; ?>
                        <div class="pl-inst-rec-item">
                            <span class="pl-inst-rec-rank"><?php echo $rank; ?>.</span>
                            <div class="pl-inst-rec-text">
                                <p><?php echo esc_html( $rec_text ); ?></p>
                            </div>
                            <span class="pl-inst-rec-count">&times;<?php echo $rec_count; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Most Impacted Profiles -->
        <div class="pl-inst-impacted">
            <h2 class="pl-inst-card-title">
                <span class="material-symbols-outlined">warning</span>
                Profils les plus impact&eacute;s
            </h2>
            <p class="pl-inst-impacted-desc">
                Profils avec les scores moyens les plus bas &mdash; priorit&eacute; d'am&eacute;lioration.
            </p>
            <?php if ( $total_analyses === 0 ) : ?>
                <div class="pl-inst-empty">
                    <span class="material-symbols-outlined">inbox</span>
                    <p>Aucune donn&eacute;e disponible.</p>
                </div>
            <?php else : ?>
                <div class="pl-inst-impacted-list">
                    <?php foreach ( $impacted as $pk => $avg ) :
                        $color = $profile_colors[ $pk ] ?? 'var(--pl-primary)';
                        $label = esc_html( $profile_labels[ $pk ] ?? $pk );
                    ?>
                        <div class="pl-inst-impacted-item">
                            <div class="pl-inst-impacted-icon" style="background:<?php echo $color; ?>;">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            <div class="pl-inst-impacted-info">
                                <span class="pl-inst-impacted-label"><?php echo $label; ?></span>
                                <div class="pl-inst-impacted-bar-bg">
                                    <div class="pl-inst-impacted-bar-fill" style="width:<?php echo $avg; ?>%;background:<?php echo $color; ?>;"></div>
                                </div>
                            </div>
                            <span class="pl-inst-impacted-score" style="color:<?php echo $color; ?>;"><?php echo $avg; ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>
</div>
        <?php
        echo '</main>';
        echo '</div>';
        echo self::render_footer();
        return ob_get_clean();
    }

    /** Alias francophone */
    public static function shortcode_institutionnel( array $atts = [] ): string {
        return self::shortcode_institutional( $atts );
    }
}
