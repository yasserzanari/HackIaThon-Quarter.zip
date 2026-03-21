<?php
/**
 * PedagoLens_Core
 *
 * Classe principale du noyau : constantes, options, CPT, rôles, hooks, log.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_Core {

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init(): void {
        add_action( 'init', [ self::class, 'register_cpts' ] );
        add_action( 'init', [ self::class, 'register_roles' ] );

        PedagoLens_Admin_Profiles::register();
        PedagoLens_Core_Settings::register();
    }

    public static function activate(): void {
        self::register_cpts();
        self::register_roles();
        PedagoLens_Profile_Manager::seed_defaults();
        flush_rewrite_rules();
    }

    // -------------------------------------------------------------------------
    // Options API
    // -------------------------------------------------------------------------

    public static function get_option( string $key, mixed $default = null ): mixed {
        return get_option( $key, $default );
    }

    public static function update_option( string $key, mixed $value ): bool {
        return update_option( $key, $value );
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    public static function log( string $level, string $message, array $context = [] ): void {
        if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
            return;
        }

        $prefix  = strtoupper( $level );
        $context_str = empty( $context ) ? '' : ' ' . wp_json_encode( $context );
        error_log( "[PédagoLens][{$prefix}] {$message}{$context_str}" );
    }

    // -------------------------------------------------------------------------
    // Rôles
    // -------------------------------------------------------------------------

    public static function register_roles(): void {
        if ( ! get_role( 'pedagolens_teacher' ) ) {
            add_role( 'pedagolens_teacher', __( 'Enseignant PédagoLens', 'pedagolens-core' ), [
                'read'               => true,
                'edit_pl_courses'    => true,
                'publish_pl_courses' => true,
                'read_pl_analyses'   => true,
                'manage_pl_workbench' => true,
            ] );
        }

        if ( ! get_role( 'pedagolens_student' ) ) {
            add_role( 'pedagolens_student', __( 'Étudiant PédagoLens', 'pedagolens-core' ), [
                'read' => true,
            ] );
        }
    }

    public static function get_user_role( int $user_id ): string {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return 'student';
        }

        if ( in_array( 'administrator', $user->roles, true ) ) {
            return 'admin';
        }
        if ( in_array( 'pedagolens_teacher', $user->roles, true ) ) {
            return 'teacher';
        }
        return 'student';
    }

    // -------------------------------------------------------------------------
    // CPT
    // -------------------------------------------------------------------------

    public static function register_cpts(): void {
        self::register_cpt_analysis();
        self::register_cpt_course();
        self::register_cpt_interaction();
        self::register_cpt_project();

        // Hooks inter-plugins déclarés ici pour documentation
        // pedagolens_before_analysis, pedagolens_after_analysis,
        // pedagolens_before_ai_invoke, pedagolens_after_ai_invoke,
        // pedagolens_guardrail_triggered, pedagolens_workbench_suggestion_applied
    }

    public static function register_cpt( string $post_type, array $args ): void {
        register_post_type( $post_type, $args );
    }

    private static function register_cpt_analysis(): void {
        register_post_type( 'pl_analysis', [
            'label'               => __( 'Analyses', 'pedagolens-core' ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'supports'            => [ 'title' ],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ] );

        // Méta enregistrées pour REST / block editor si besoin futur
        foreach ( [
            '_pl_course_id', '_pl_profile_scores', '_pl_recommendations',
            '_pl_raw_response', '_pl_analyzed_at', '_pl_summary', '_pl_impact_estimates',
        ] as $meta_key ) {
            register_post_meta( 'pl_analysis', $meta_key, [ 'single' => true, 'show_in_rest' => false ] );
        }
    }

    private static function register_cpt_course(): void {
        register_post_type( 'pl_course', [
            'label'           => __( 'Cours', 'pedagolens-core' ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'editor' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );

        foreach ( [ '_pl_sections', '_pl_versions', '_pl_last_workbench_at', '_pl_course_type', '_pl_course_code', '_pl_session' ] as $meta_key ) {
            register_post_meta( 'pl_course', $meta_key, [ 'single' => true, 'show_in_rest' => false ] );
        }
    }

    private static function register_cpt_interaction(): void {
        register_post_type( 'pl_interaction', [
            'label'           => __( 'Sessions jumeau', 'pedagolens-core' ),
            'public'          => false,
            'show_ui'         => false,
            'supports'        => [ 'title' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );

        foreach ( [
            '_pl_student_id', '_pl_course_id', '_pl_session_id', '_pl_messages',
            '_pl_started_at', '_pl_ended_at', '_pl_guardrails_applied',
        ] as $meta_key ) {
            register_post_meta( 'pl_interaction', $meta_key, [ 'single' => true, 'show_in_rest' => false ] );
        }
    }

    private static function register_cpt_project(): void {
        register_post_type( 'pl_project', [
            'label'           => __( 'Projets', 'pedagolens-core' ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'supports'        => [ 'title' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );

        foreach ( [
            '_pl_course_id', '_pl_project_type', '_pl_content_sections',
            '_pl_profile_scores', '_pl_recommendations', '_pl_impact_estimates',
            '_pl_versions', '_pl_created_at', '_pl_updated_at', '_pl_files',
        ] as $meta_key ) {
            register_post_meta( 'pl_project', $meta_key, [ 'single' => true, 'show_in_rest' => false ] );
        }
    }
}
