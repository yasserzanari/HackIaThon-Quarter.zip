<?php
/**
 * PedagoLens_Profile_Manager
 *
 * CRUD des profils pédagogiques stockés en options WP.
 * Chaque profil → option `pl_profile_{slug}` (JSON).
 * Index des slugs → option `pl_profile_index` (array).
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Profile_Manager {

    private const INDEX_KEY   = 'pl_profile_index';
    private const OPTION_PREFIX = 'pl_profile_';

    // -------------------------------------------------------------------------
    // Lecture
    // -------------------------------------------------------------------------

    /**
     * Retourne tous les profils (actifs seulement par défaut), triés par sort_order.
     */
    public static function get_all( bool $active_only = true ): array {
        $slugs    = self::get_index();
        $profiles = [];

        foreach ( $slugs as $slug ) {
            $profile = self::get( $slug );
            if ( $profile === null ) {
                continue;
            }
            if ( $active_only && ! $profile['is_active'] ) {
                continue;
            }
            $profiles[] = $profile;
        }

        usort( $profiles, fn( $a, $b ) => $a['sort_order'] <=> $b['sort_order'] );

        return $profiles;
    }

    /**
     * Retourne un profil par slug, ou null si absent.
     */
    public static function get( string $slug ): ?array {
        $data = get_option( self::OPTION_PREFIX . $slug, null );
        if ( ! is_array( $data ) ) {
            return null;
        }
        return $data;
    }

    /**
     * Retourne le tableau de slugs depuis l'index.
     */
    public static function get_index(): array {
        $index = get_option( self::INDEX_KEY, [] );
        return is_array( $index ) ? $index : [];
    }

    // -------------------------------------------------------------------------
    // Écriture
    // -------------------------------------------------------------------------

    /**
     * Sauvegarde un profil. Crée ou met à jour.
     * Retourne false si le slug est invalide ou en conflit (nouveau profil).
     */
    public static function save( array $profile_data ): bool {
        $slug = sanitize_text_field( $profile_data['slug'] ?? '' );

        if ( ! self::is_valid_slug( $slug ) ) {
            PedagoLens_Core::log( 'warning', "Profile_Manager::save — slug invalide : {$slug}" );
            return false;
        }

        $index      = self::get_index();
        $is_new     = ! in_array( $slug, $index, true );
        $existing   = self::get( $slug );

        // Conflit : slug déjà pris pour un nouveau profil
        if ( $is_new && $existing !== null ) {
            PedagoLens_Core::log( 'warning', "Profile_Manager::save — conflit de slug : {$slug}" );
            return false;
        }

        $now = gmdate( 'c' );

        // Map system_prompt_template → system_prompt si system_prompt est vide/absent
        $system_prompt = $profile_data['system_prompt'] ?? '';
        if ( empty( $system_prompt ) && ! empty( $profile_data['system_prompt_template'] ) ) {
            $system_prompt = $profile_data['system_prompt_template'];
        }

        // Build references text from array if present and resources is empty
        $resources = $profile_data['resources'] ?? '';
        if ( empty( $resources ) && ! empty( $profile_data['references'] ) && is_array( $profile_data['references'] ) ) {
            $resources = implode( "\n", $profile_data['references'] );
        }

        $profile = [
            'slug'             => $slug,
            'name'             => sanitize_text_field( $profile_data['name'] ?? $slug ),
            'description'      => wp_kses_post( $profile_data['description'] ?? '' ),
            'is_active'        => (bool) ( $profile_data['is_active'] ?? true ),
            'sort_order'       => (int) ( $profile_data['sort_order'] ?? count( $index ) + 1 ),
            'system_prompt'    => sanitize_textarea_field( $system_prompt ),
            'resources'        => sanitize_textarea_field( $resources ),
            'scoring_grid'     => self::sanitize_scoring_grid( $profile_data['scoring_grid'] ?? self::default_scoring_grid() ),
            'inject_resources' => (bool) ( $profile_data['inject_resources'] ?? true ),
            'inject_scoring'   => (bool) ( $profile_data['inject_scoring'] ?? true ),
            'created_at'       => $existing['created_at'] ?? $now,
            'updated_at'       => $now,
        ];

        // Store rich/extended fields as-is (read-only scientific data)
        $extended_keys = [
            'scientific_basis',
            'cognitive_characteristics',
            'learning_challenges',
            'pedagogical_recommendations',
            'risk_indicators',
            'ai_analysis_criteria',
            'scoring_weights',
            'references',
            'system_prompt_template',
        ];

        foreach ( $extended_keys as $key ) {
            if ( isset( $profile_data[ $key ] ) ) {
                $profile[ $key ] = $profile_data[ $key ];
            }
        }

        update_option( self::OPTION_PREFIX . $slug, $profile );

        if ( $is_new ) {
            $index[] = $slug;
            update_option( self::INDEX_KEY, $index );
        }

        return true;
    }

    /**
     * Soft delete : passe is_active à false.
     * Bloqué si le slug est référencé dans des pl_analysis existants.
     */
    public static function delete( string $slug ): bool {
        if ( self::is_slug_used_in_analyses( $slug ) ) {
            PedagoLens_Core::log( 'warning', "Profile_Manager::delete — slug utilisé dans pl_analysis : {$slug}" );
            return false;
        }

        $profile = self::get( $slug );
        if ( $profile === null ) {
            return false;
        }

        $profile['is_active']  = false;
        $profile['updated_at'] = gmdate( 'c' );
        update_option( self::OPTION_PREFIX . $slug, $profile );

        return true;
    }

    /**
     * Duplique un profil sous un nouveau slug.
     */
    public static function duplicate( string $slug, string $new_slug ): bool {
        $source = self::get( $slug );
        if ( $source === null ) {
            return false;
        }

        $copy             = $source;
        $copy['slug']     = $new_slug;
        $copy['name']     = $source['name'] . ' (copie)';
        $copy['is_active'] = false; // désactivé par défaut pour éviter les doublons actifs

        return self::save( $copy );
    }

    /**
     * Met à jour le sort_order de chaque profil selon l'ordre du tableau fourni.
     */
    public static function reorder( array $slugs ): bool {
        foreach ( $slugs as $order => $slug ) {
            $profile = self::get( $slug );
            if ( $profile === null ) {
                continue;
            }
            $profile['sort_order'] = $order + 1;
            $profile['updated_at'] = gmdate( 'c' );
            update_option( self::OPTION_PREFIX . $slug, $profile );
        }

        // Mettre à jour l'index dans le même ordre
        update_option( self::INDEX_KEY, array_values( $slugs ) );

        return true;
    }

    // -------------------------------------------------------------------------
    // Seed des profils par défaut
    // -------------------------------------------------------------------------

    public static function seed_defaults(): void {
        if ( ! empty( self::get_index() ) ) {
            return; // Déjà seedé
        }

        // Try to import from rich JSON files first
        $imported = self::import_from_json_files();
        if ( $imported > 0 ) {
            PedagoLens_Core::log( 'info', "Profile_Manager::seed_defaults — {$imported} profils importés depuis JSON." );
            return;
        }

        // Fallback to minimal hardcoded defaults
        $defaults = [
            [ 'slug' => 'concentration_tdah',  'name' => 'TDAH / Concentration',       'description' => 'Étudiant avec TDAH ou difficultés de concentration',                    'sort_order' => 1 ],
            [ 'slug' => 'surcharge_cognitive',  'name' => 'Surcharge cognitive',         'description' => 'Étudiant en surcharge cognitive',                                       'sort_order' => 2 ],
            [ 'slug' => 'langue_seconde',        'name' => 'Langue seconde',              'description' => 'Étudiant allophone ou en contexte de langue seconde',                   'sort_order' => 3 ],
            [ 'slug' => 'faible_autonomie',      'name' => 'Faible autonomie',            'description' => 'Étudiant avec besoin de guidage structuré',                             'sort_order' => 4 ],
            [ 'slug' => 'anxieux_consignes',     'name' => 'Anxieux / Consignes',         'description' => 'Étudiant anxieux face aux consignes floues ou ambiguës',               'sort_order' => 5 ],
            [ 'slug' => 'avance_rapide',         'name' => 'Avancé / Rapide',             'description' => 'Étudiant avancé et autonome (profil de référence fort)',               'sort_order' => 6 ],
            [ 'slug' => 'usage_passif_ia',       'name' => 'Usage passif IA',             'description' => 'Étudiant qui utilise l\'IA pour éviter de comprendre (profil à risque)', 'sort_order' => 7 ],
        ];

        foreach ( $defaults as $data ) {
            $data['system_prompt'] = '';
            $data['resources']     = '';
            $data['is_active']     = true;
            self::save( $data );
        }
    }

    /**
     * Import profiles from JSON files in the profiles/ directory.
     * Returns the number of profiles successfully imported.
     */
    public static function import_from_json_files(): int {
        $profiles_dir = PEDAGOLENS_PLUGIN_DIR . 'profiles/';
        $index_file   = $profiles_dir . 'index.json';

        if ( ! file_exists( $index_file ) ) {
            return 0;
        }

        $index_json = file_get_contents( $index_file );
        $index_data = json_decode( $index_json, true );

        if ( ! is_array( $index_data ) || empty( $index_data['profiles'] ) ) {
            return 0;
        }

        $imported = 0;
        $order    = 1;

        foreach ( $index_data['profiles'] as $slug ) {
            $file = $profiles_dir . $slug . '.json';
            if ( ! file_exists( $file ) ) {
                PedagoLens_Core::log( 'warning', "Profile_Manager::import_from_json_files — fichier manquant : {$file}" );
                continue;
            }

            $json = file_get_contents( $file );
            $data = json_decode( $json, true );

            if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
                PedagoLens_Core::log( 'warning', "Profile_Manager::import_from_json_files — JSON invalide : {$file}" );
                continue;
            }

            // Set sort_order from index position if not present
            if ( ! isset( $data['sort_order'] ) ) {
                $data['sort_order'] = $order;
            }

            // Ensure is_active defaults to true
            if ( ! isset( $data['is_active'] ) ) {
                $data['is_active'] = true;
            }

            // Skip if already exists (don't overwrite user edits)
            $existing = self::get( $data['slug'] );
            if ( $existing !== null ) {
                $order++;
                continue;
            }

            if ( self::save( $data ) ) {
                $imported++;
            }

            $order++;
        }

        return $imported;
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private static function is_valid_slug( string $slug ): bool {
        return (bool) preg_match( '/^[a-z][a-z0-9_\-]*$/', $slug );
    }

    private static function is_slug_used_in_analyses( string $slug ): bool {
        $query = new WP_Query( [
            'post_type'      => 'pl_analysis',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [ [
                'key'     => '_pl_profile_scores',
                'value'   => $slug,
                'compare' => 'LIKE',
            ] ],
        ] );

        return $query->found_posts > 0;
    }

    private static function sanitize_scoring_grid( array $grid ): array {
        return array_map( fn( $row ) => [
            'min'   => (int) ( $row['min'] ?? 0 ),
            'max'   => (int) ( $row['max'] ?? 100 ),
            'label' => sanitize_text_field( $row['label'] ?? '' ),
            'color' => sanitize_text_field( $row['color'] ?? 'grey' ),
        ], $grid );
    }

    private static function default_scoring_grid(): array {
        return [
            [ 'min' => 90, 'max' => 100, 'label' => 'Très accessible', 'color' => 'green' ],
            [ 'min' => 70, 'max' => 89,  'label' => 'Accessible',       'color' => 'blue' ],
            [ 'min' => 50, 'max' => 69,  'label' => 'Difficile',         'color' => 'yellow' ],
            [ 'min' => 30, 'max' => 49,  'label' => 'Très difficile',    'color' => 'orange' ],
            [ 'min' => 0,  'max' => 29,  'label' => 'Inaccessible',      'color' => 'red' ],
        ];
    }
}
