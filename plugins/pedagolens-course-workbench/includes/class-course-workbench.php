<?php
/**
 * PedagoLens_Course_Workbench
 *
 * Logique métier : suggestions IA par section, apply/reject, versionnage.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Course_Workbench {

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init(): void {
        PedagoLens_Workbench_Admin::register();
    }

    // -------------------------------------------------------------------------
    // Suggestions
    // -------------------------------------------------------------------------

    /**
     * Récupère les suggestions IA pour une section d'un projet.
     */
    public static function get_suggestions( int $project_id, string $section ): array {
        $project = get_post( $project_id );
        if ( ! $project || $project->post_type !== 'pl_project' ) {
            return self::error( 'pl_section_not_found', "Projet introuvable : {$project_id}" );
        }

        $sections = self::get_content_sections( $project_id );
        if ( ! self::section_exists( $sections, $section ) ) {
            return self::error( 'pl_section_not_found', "Section introuvable : {$section}" );
        }

        $course_id   = (int) get_post_meta( $project_id, '_pl_course_id',    true );
        $project_type = get_post_meta( $project_id, '_pl_project_type', true ) ?: 'magistral';
        $course_type  = $course_id ? ( get_post_meta( $course_id, '_pl_course_type', true ) ?: 'magistral' ) : $project_type;

        $section_content = self::get_section_content( $sections, $section );

        // Get slide_num from section data
        $slide_num = 0;
        foreach ( $sections as $idx => $s ) {
            if ( ( $s['id'] ?? '' ) === $section ) {
                $slide_num = (int) ( $s['slide_num'] ?? ( $idx + 1 ) );
                break;
            }
        }

        // Get active profile slugs
        $active_profiles = '';
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $profiles = PedagoLens_Profile_Manager::get_all( active_only: true );
            $active_profiles = implode( ', ', array_column( $profiles, 'slug' ) );
        }
        if ( ! $active_profiles ) {
            $active_profiles = 'concentration_tdah, anxieux_consignes, avance_rapide, faible_autonomie, langue_seconde, surcharge_cognitive, usage_passif_ia';
        }

        $params = [
            'project_id'      => $project_id,
            'course_id'       => $course_id,
            'section'         => $section,
            'content'         => $section_content,
            'course_type'     => $course_type,
            'project_type'    => $project_type,
            'slide_num'       => $slide_num,
            'active_profiles' => $active_profiles,
        ];

        $result = PedagoLens_API_Bridge::invoke( 'workbench_suggestions', $params );

        if ( empty( $result['success'] ) ) {
            return $result;
        }

        // Valider la structure de chaque suggestion
        $suggestions = $result['suggestions'] ?? [];
        foreach ( $suggestions as $sug ) {
            if ( ! self::validate_suggestion( $sug ) ) {
                return self::error( 'pl_bedrock_invalid_response', 'Structure de suggestion invalide.' );
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Apply / Reject
    // -------------------------------------------------------------------------

    /**
     * Applique une suggestion : remplace le contenu de la section par `proposed`.
     */
    public static function apply_suggestion( int $project_id, string $section, string $suggestion_id ): bool {
        $suggestions_cache = self::get_cached_suggestions( $project_id, $section );
        $suggestion        = self::find_suggestion( $suggestions_cache, (string) $suggestion_id );

        if ( ! $suggestion ) {
            // Fallback: search ALL sections in the cache (suggestion may have been stored under a different section key)
            $raw_all = get_post_meta( $project_id, '_pl_last_suggestions', true );
            $all_cache = is_string( $raw_all ) ? (array) json_decode( $raw_all, true ) : [];
            foreach ( $all_cache as $sec_id => $sec_suggestions ) {
                if ( ! is_array( $sec_suggestions ) ) continue;
                $suggestion = self::find_suggestion( $sec_suggestions, (string) $suggestion_id );
                if ( $suggestion ) break;
            }
        }

        if ( ! $suggestion ) {
            PedagoLens_Core::log( 'warning', "apply_suggestion — suggestion introuvable : {$suggestion_id} (section: {$section}, project: {$project_id})" );
            return false;
        }

        $sections = self::get_content_sections( $project_id );
        $updated  = self::update_section_content( $sections, $section, $suggestion['proposed'] );

        if ( ! $updated ) {
            return false;
        }

        // Sauvegarder une version avant modification
        self::save_version( $project_id, $section, self::get_section_content( $sections, $section ) );

        update_post_meta( $project_id, '_pl_content_sections', wp_json_encode( $updated ) );
        update_post_meta( $project_id, '_pl_updated_at', gmdate( 'c' ) );

        do_action( 'pedagolens_workbench_suggestion_applied', $project_id, $section, $suggestion_id );

        return true;
    }

    /**
     * Rejette une suggestion : ne modifie pas le contenu.
     */
    public static function reject_suggestion( int $project_id, string $section, string $suggestion_id ): bool {
        // Aucune modification du contenu — on log juste le rejet
        PedagoLens_Core::log( 'info', "reject_suggestion — {$suggestion_id} rejeté pour section {$section}" );
        return true;
    }

    // -------------------------------------------------------------------------
    // Versionnage
    // -------------------------------------------------------------------------

    /**
     * Sauvegarde une version du contenu d'une section.
     *
     * @return int  Nombre de versions après sauvegarde.
     */
    public static function save_version( int $project_id, string $section, string $content ): int {
        $raw      = get_post_meta( $project_id, '_pl_versions', true );
        $versions = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];

        if ( ! isset( $versions[ $section ] ) ) {
            $versions[ $section ] = [];
        }

        $versions[ $section ][] = [
            'content'    => $content,
            'saved_at'   => gmdate( 'c' ),
            'version_no' => count( $versions[ $section ] ) + 1,
        ];

        update_post_meta( $project_id, '_pl_versions', wp_json_encode( $versions ) );
        update_post_meta( $project_id, '_pl_updated_at', gmdate( 'c' ) );

        return count( $versions[ $section ] );
    }

    /**
     * Retourne les versions d'une section en ordre chronologique.
     */
    public static function compare_versions( int $project_id, string $section ): array {
        $raw      = get_post_meta( $project_id, '_pl_versions', true );
        $versions = is_string( $raw ) ? (array) json_decode( $raw, true ) : [];

        return $versions[ $section ] ?? [];
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

    public static function get_content_sections( int $project_id ): array {
        $raw = get_post_meta( $project_id, '_pl_content_sections', true );
        return is_string( $raw ) ? (array) json_decode( $raw, true ) : [];
    }

    public static function save_content_sections( int $project_id, array $sections ): void {
        update_post_meta( $project_id, '_pl_content_sections', wp_json_encode( $sections ) );
        update_post_meta( $project_id, '_pl_updated_at', gmdate( 'c' ) );
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private static function section_exists( array $sections, string $section_id ): bool {
        foreach ( $sections as $s ) {
            if ( ( $s['id'] ?? '' ) === $section_id ) {
                return true;
            }
        }
        return false;
    }

    private static function get_section_content( array $sections, string $section_id ): string {
        foreach ( $sections as $s ) {
            if ( ( $s['id'] ?? '' ) === $section_id ) {
                return $s['content'] ?? '';
            }
        }
        return '';
    }

    private static function update_section_content( array $sections, string $section_id, string $new_content ): ?array {
        foreach ( $sections as &$s ) {
            if ( ( $s['id'] ?? '' ) === $section_id ) {
                $s['content'] = $new_content;
                return $sections;
            }
        }
        return null;
    }

    private static function validate_suggestion( array $sug ): bool {
        // id, section, rationale sont toujours requis
        foreach ( [ 'id', 'section', 'rationale' ] as $field ) {
            if ( empty( $sug[ $field ] ) ) {
                return false;
            }
        }
        // Pour un ajout, original peut être vide ; pour une suppression, proposed peut être vide
        if ( ! isset( $sug['original'] ) || ! isset( $sug['proposed'] ) ) {
            return false;
        }
        // Au moins l'un des deux doit être non-vide
        if ( $sug['original'] === '' && $sug['proposed'] === '' ) {
            return false;
        }
        return true;
    }

    /**
     * Cache en mémoire des suggestions pour la requête courante.
     * En production, utiliser un transient ou une meta.
     */
    private static array $suggestions_cache = [];

    private static function get_cached_suggestions( int $project_id, string $section ): array {
        $key = "{$project_id}:{$section}";
        if ( isset( self::$suggestions_cache[ $key ] ) ) {
            return self::$suggestions_cache[ $key ];
        }

        // Lire depuis la meta si disponible
        $raw = get_post_meta( $project_id, '_pl_last_suggestions', true );
        if ( $raw ) {
            $data = json_decode( $raw, true );
            if ( isset( $data[ $section ] ) ) {
                self::$suggestions_cache[ $key ] = $data[ $section ];
                return $data[ $section ];
            }
        }

        return [];
    }

    private static function find_suggestion( array $suggestions, string $id ): ?array {
        foreach ( $suggestions as $sug ) {
            // Compare as strings to handle jQuery numeric coercion (e.g. "1" vs 1)
            if ( (string) ( $sug['id'] ?? '' ) === (string) $id ) {
                return $sug;
            }
        }
        return null;
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
