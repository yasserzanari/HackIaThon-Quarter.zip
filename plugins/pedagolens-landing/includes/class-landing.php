<?php
/**
 * PedagoLens_Landing
 *
 * Enregistre les shortcodes de la landing page.
 * Toutes les données sont lues depuis l'option `pl_landing_settings`.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Landing {

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init(): void {
        PedagoLens_Landing_Admin::register();

        add_shortcode( 'pedagolens_hero',         [ self::class, 'shortcode_hero' ] );
        add_shortcode( 'pedagolens_features',     [ self::class, 'shortcode_features' ] );
        add_shortcode( 'pedagolens_pricing',      [ self::class, 'shortcode_pricing' ] );
        add_shortcode( 'pedagolens_testimonials', [ self::class, 'shortcode_testimonials' ] );

        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_front_assets' ] );
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
    }

    // -------------------------------------------------------------------------
    // Shortcodes
    // -------------------------------------------------------------------------

    public static function shortcode_hero( array $atts ): string {
        $s = self::get_settings();

        if ( empty( $s['sections']['hero'] ) ) {
            return '';
        }

        $title    = esc_html( $s['hero_title']    ?? 'PédagoLens' );
        $subtitle = esc_html( $s['hero_subtitle'] ?? 'L\'IA pédagogique pour les enseignants du CÉGEP.' );
        $cta_text = esc_html( $s['cta_text']      ?? 'Demander une démo' );
        $cta_url  = esc_url(  $s['cta_url']       ?? '#' );
        $color    = esc_attr( $s['primary_color'] ?? '#2271b1' );

        // Badges de profils pour la démo visuelle
        $profiles_html = '';
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $profiles = PedagoLens_Profile_Manager::get_all( active_only: true );
            foreach ( $profiles as $p ) {
                $name = esc_html( $p['name'] ?? $p['slug'] );
                $profiles_html .= "<span class=\"pl-hero-profile-badge\">{$name}</span>";
            }
        }

        $profiles_section = $profiles_html
            ? "<div class=\"pl-hero-profiles\"><span class=\"pl-hero-profiles-label\">7 profils d'apprenants :</span>{$profiles_html}</div>"
            : '';

        return <<<HTML
        <section class="pl-hero" style="--pl-primary:{$color};">
            <div class="pl-hero-inner">
                <div class="pl-hero-badge">✦ Propulsé par AWS Bedrock</div>
                <h1 class="pl-hero-title">{$title}</h1>
                <p class="pl-hero-subtitle">{$subtitle}</p>
                {$profiles_section}
                <div class="pl-hero-cta-group">
                    <a href="{$cta_url}" class="pl-btn-cta">{$cta_text}</a>
                    <span class="pl-hero-note">Mode démo disponible — aucun compte requis</span>
                </div>
            </div>
        </section>
        HTML;
    }

    public static function shortcode_features( array $atts ): string {
        $s = self::get_settings();

        if ( empty( $s['sections']['features'] ) ) {
            return '';
        }

        $features = $s['features'] ?? self::default_features();
        $color    = esc_attr( $s['primary_color'] ?? '#2271b1' );
        $title    = esc_html( $s['features_title'] ?? 'Tout ce dont vous avez besoin' );

        $items = '';
        foreach ( $features as $f ) {
            $icon  = esc_html( $f['icon']  ?? '✦' );
            $ftitle = esc_html( $f['title'] ?? '' );
            $desc  = esc_html( $f['desc']  ?? '' );
            $items .= "<div class=\"pl-feature-card\"><span class=\"pl-feature-icon\">{$icon}</span><h3>{$ftitle}</h3><p>{$desc}</p></div>";
        }

        return <<<HTML
        <section class="pl-features" style="--pl-primary:{$color};">
            <div class="pl-section-header-text"><h2>{$title}</h2></div>
            <div class="pl-features-grid">{$items}</div>
        </section>
        HTML;
    }

    public static function shortcode_pricing( array $atts ): string {
        $s = self::get_settings();

        if ( empty( $s['sections']['pricing'] ) ) {
            return '';
        }

        $plans = $s['pricing'] ?? self::default_pricing();
        $color = esc_attr( $s['primary_color'] ?? '#2271b1' );
        $title = esc_html( $s['pricing_title'] ?? 'Tarifs simples et transparents' );

        $cards = '';
        foreach ( $plans as $p ) {
            $name     = esc_html( $p['name']  ?? '' );
            $price    = esc_html( $p['price'] ?? '' );
            $desc     = esc_html( $p['desc']  ?? '' );
            $featured = ! empty( $p['featured'] ) ? ' pl-plan-featured' : '';
            $badge    = ! empty( $p['featured'] ) ? '<span class="pl-plan-badge">Recommandé</span>' : '';
            $cards   .= "<div class=\"pl-plan-card{$featured}\">{$badge}<h3>{$name}</h3><div class=\"pl-plan-price\">{$price}</div><p>{$desc}</p></div>";
        }

        return <<<HTML
        <section class="pl-pricing" style="--pl-primary:{$color};">
            <div class="pl-section-header-text"><h2>{$title}</h2></div>
            <div class="pl-pricing-grid">{$cards}</div>
        </section>
        HTML;
    }

    public static function shortcode_testimonials( array $atts ): string {
        $s = self::get_settings();

        if ( empty( $s['sections']['testimonials'] ) ) {
            return '';
        }

        $testimonials = $s['testimonials'] ?? self::default_testimonials();
        $color        = esc_attr( $s['primary_color'] ?? '#2271b1' );
        $title        = esc_html( $s['testimonials_title'] ?? 'Ce que disent les enseignants' );

        $items = '';
        foreach ( $testimonials as $t ) {
            $quote  = esc_html( $t['quote']  ?? '' );
            $author = esc_html( $t['author'] ?? '' );
            $role   = esc_html( $t['role']   ?? '' );
            $items .= "<blockquote class=\"pl-testimonial\"><p>&ldquo;{$quote}&rdquo;</p><footer><strong>{$author}</strong><span>{$role}</span></footer></blockquote>";
        }

        return <<<HTML
        <section class="pl-testimonials" style="--pl-primary:{$color};">
            <div class="pl-section-header-text"><h2>{$title}</h2></div>
            <div class="pl-testimonials-grid">{$items}</div>
        </section>
        HTML;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function get_settings(): array {
        $raw = get_option( 'pl_landing_settings', [] );
        if ( is_string( $raw ) ) {
            $raw = json_decode( $raw, true ) ?? [];
        }

        return wp_parse_args( (array) $raw, [
            'hero_title'    => 'PédagoLens',
            'hero_subtitle' => 'L\'IA pédagogique pour les enseignants du CÉGEP.',
            'cta_text'      => 'Demander une démo',
            'cta_url'       => '#',
            'primary_color' => '#2271b1',
            'sections'      => [
                'hero'         => true,
                'features'     => true,
                'pricing'      => true,
                'testimonials' => true,
            ],
            'features'     => self::default_features(),
            'pricing'      => self::default_pricing(),
            'testimonials' => self::default_testimonials(),
        ] );
    }

    private static function default_features(): array {
        return [
            [ 'icon' => '🔍', 'title' => 'Analyse pédagogique IA',    'desc' => 'Analysez vos cours selon 7 profils d\'apprenants en quelques secondes.' ],
            [ 'icon' => '✏️', 'title' => 'Atelier de cours',           'desc' => 'Recevez des suggestions concrètes pour améliorer l\'accessibilité de vos contenus.' ],
            [ 'icon' => '🤖', 'title' => 'Jumeau numérique étudiant', 'desc' => 'Simulez l\'expérience d\'un étudiant avec des garde-fous pédagogiques intégrés.' ],
            [ 'icon' => '📊', 'title' => 'Tableau de bord',           'desc' => 'Visualisez les scores par profil et suivez l\'évolution de vos cours.' ],
        ];
    }

    private static function default_pricing(): array {
        return [
            [ 'name' => 'Démo',        'price' => 'Gratuit',   'desc' => 'Accès complet en mode démo pour le hackathon.',  'featured' => false ],
            [ 'name' => 'Enseignant',  'price' => '29 $/mois', 'desc' => 'Pour un enseignant avec jusqu\'à 5 cours actifs.', 'featured' => true ],
            [ 'name' => 'Institution', 'price' => 'Sur devis', 'desc' => 'Déploiement institutionnel multi-enseignants.',   'featured' => false ],
        ];
    }

    private static function default_testimonials(): array {
        return [
            [
                'quote'  => 'PédagoLens m\'a permis d\'identifier en 2 minutes des problèmes d\'accessibilité que je n\'avais pas vus en 3 ans.',
                'author' => 'Marie-Ève Tremblay',
                'role'   => 'Enseignante, Cégep de Montréal',
            ],
            [
                'quote'  => 'Les suggestions pour les étudiants TDAH ont transformé ma façon de rédiger mes consignes.',
                'author' => 'Jean-François Côté',
                'role'   => 'Professeur de philosophie, Cégep Garneau',
            ],
        ];
    }
}
