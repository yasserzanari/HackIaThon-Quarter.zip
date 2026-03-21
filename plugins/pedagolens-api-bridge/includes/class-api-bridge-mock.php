<?php
/**
 * PedagoLens_API_Bridge_Mock
 *
 * Retourne des réponses de démonstration crédibles pour chaque prompt_key,
 * sans aucun appel HTTP externe.
 *
 * Les profils actifs sont lus dynamiquement via PedagoLens_Profile_Manager
 * (si disponible) — aucune liste hardcodée de slugs.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_API_Bridge_Mock {

    /**
     * Scores mock indicatifs par slug (utilisés si le profil est actif).
     * Valeurs réalistes entre 45 et 92 pour la démo HackIAthon.
     */
    private const MOCK_SCORES = [
        'concentration_tdah'  => 54,
        'surcharge_cognitive' => 63,
        'langue_seconde'      => 61,
        'faible_autonomie'    => 67,
        'anxieux_consignes'   => 58,
        'avance_rapide'       => 88,
        'usage_passif_ia'     => 72,
    ];

    public static function invoke( string $prompt_key, array $params ): array {
        return match ( $prompt_key ) {
            'course_analysis'           => self::mock_course_analysis( $params ),
            'workbench_suggestions'     => self::mock_workbench_suggestions( $params ),
            'student_twin_response'     => self::mock_student_twin_response( $params ),
            'student_guardrail_check'   => self::mock_guardrail_check( $params ),
            'dashboard_insight_summary' => self::mock_dashboard_summary( $params ),
            default                     => PedagoLens_API_Bridge::error( 'pl_prompt_not_found', "Clé inconnue : {$prompt_key}" ),
        };
    }

    // -------------------------------------------------------------------------
    // Réponses mock par prompt_key
    // -------------------------------------------------------------------------

    private static function mock_course_analysis( array $params ): array {
        $active_profiles = self::get_active_profile_slugs();
        $profile_scores  = [];

        foreach ( $active_profiles as $slug ) {
            // Score mock : valeur connue ou aléatoire réaliste entre 45 et 92
            $profile_scores[ $slug ] = self::MOCK_SCORES[ $slug ] ?? rand( 45, 92 );
        }

        $course_title = $params['course_title'] ?? 'Français 101 — Analyse littéraire';

        return [
            'success'        => true,
            'profile_scores' => $profile_scores,
            'recommendations' => [
                [
                    'section'        => 'Introduction',
                    'text'           => 'Reformuler la consigne d\'analyse en étapes numérotées pour réduire la charge cognitive.',
                    'priority'       => 1,
                    'profile_target' => 'concentration_tdah',
                ],
                [
                    'section'        => 'Développement',
                    'text'           => 'Ajouter un exemple de paragraphe d\'analyse complété pour les étudiants allophones.',
                    'priority'       => 2,
                    'profile_target' => 'langue_seconde',
                ],
                [
                    'section'        => 'Conclusion',
                    'text'           => 'Clarifier les critères d\'évaluation avec une grille explicite.',
                    'priority'       => 3,
                    'profile_target' => 'anxieux_consignes',
                ],
            ],
            'impact_estimates' => [
                'suggestion_1' => [
                    'concentration_tdah'  => 8,
                    'surcharge_cognitive' => 6,
                    'langue_seconde'      => 3,
                ],
                'suggestion_2' => [
                    'langue_seconde'  => 9,
                    'faible_autonomie' => 5,
                    'avance_rapide'   => -1,
                ],
            ],
            'summary' => sprintf(
                'Le cours "%s" est bien structuré pour les étudiants avancés (88/100) mais présente des difficultés ' .
                'pour les étudiants avec TDAH (54/100) et allophones (61/100). ' .
                'Trois améliorations prioritaires ont été identifiées.',
                esc_html( $course_title )
            ),
        ];
    }

    private static function mock_workbench_suggestions( array $params ): array {
        $section   = $params['section'] ?? 'Introduction';
        $slide_num = $params['slide_num'] ?? 1;

        // Generate unique IDs per section to avoid cache collisions
        $uid = substr( md5( $section . microtime() ), 0, 8 );

        $profile_slugs = self::get_active_profile_slugs();
        $profile_scores = [];
        foreach ( $profile_slugs as $slug ) {
            $profile_scores[ $slug ] = rand( 45, 92 );
        }

        return [
            'success'        => true,
            'profile_scores' => $profile_scores,
            'suggestions'    => [
                [
                    'id'                => "sug_{$uid}_1",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'reformulation',
                    'impact_score'      => 82,
                    'original'          => 'Rédigez une analyse littéraire du texte suivant en tenant compte des procédés stylistiques.',
                    'proposed'          => "Rédigez une analyse littéraire en suivant ces 3 étapes :\n1. Identifiez 2 procédés stylistiques (ex. métaphore, anaphore).\n2. Expliquez l'effet de chaque procédé sur le lecteur.\n3. Reliez votre analyse au thème principal du texte.",
                    'rationale'         => 'Le découpage en étapes numérotées réduit la charge cognitive et clarifie les attentes.',
                    'profile_target'    => 'concentration_tdah',
                    'impact_delta'      => [
                        'concentration_tdah'  => 12,
                        'surcharge_cognitive' => 8,
                        'faible_autonomie'    => 5,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_2",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'restructuration',
                    'impact_score'      => 74,
                    'original'          => 'Analysez l\'argumentation du texte philosophique.',
                    'proposed'          => "Analysez l'argumentation en répondant à ces questions :\n• Quelle est la thèse principale de l'auteur ?\n• Quels arguments soutiennent cette thèse ?\n• Y a-t-il des contre-arguments ? Comment sont-ils réfutés ?",
                    'rationale'         => 'Les questions guidées aident les étudiants à faible autonomie à structurer leur réflexion.',
                    'profile_target'    => 'faible_autonomie',
                    'impact_delta'      => [
                        'faible_autonomie'    => 15,
                        'concentration_tdah'  => 6,
                        'anxieux_consignes'   => 4,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_3",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'reformulation',
                    'impact_score'      => 91,
                    'original'          => 'Critères d\'évaluation : qualité de l\'analyse, pertinence des exemples.',
                    'proposed'          => "Critères d'évaluation (sur 20 points) :\n• Identification des procédés (6 pts) : au moins 2 procédés nommés correctement.\n• Analyse de l'effet (8 pts) : explication claire du lien procédé → effet.\n• Cohérence (6 pts) : lien avec le thème, structure du paragraphe.",
                    'rationale'         => 'Des critères explicites et chiffrés réduisent l\'anxiété face aux consignes ambiguës.',
                    'profile_target'    => 'anxieux_consignes',
                    'impact_delta'      => [
                        'anxieux_consignes'   => 18,
                        'surcharge_cognitive' => 7,
                        'langue_seconde'      => 3,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_4",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'ajout',
                    'impact_score'      => 67,
                    'original'          => '',
                    'proposed'          => "Glossaire contextuel :\n• Procédé stylistique : technique d'écriture utilisée pour produire un effet.\n• Métaphore : comparaison implicite (sans « comme »).\n• Anaphore : répétition d'un mot en début de phrase.",
                    'rationale'         => 'L\'ajout d\'un glossaire intégré à la diapositive aide les étudiants allophones à comprendre le vocabulaire technique sans interrompre leur lecture.',
                    'profile_target'    => 'langue_seconde',
                    'impact_delta'      => [
                        'langue_seconde'      => 20,
                        'surcharge_cognitive' => 5,
                        'faible_autonomie'    => 3,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_5",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'suppression',
                    'impact_score'      => 53,
                    'original'          => 'Note : ce travail sera noté sévèrement. Toute erreur de méthode entraînera une pénalité importante. Assurez-vous de bien relire avant de soumettre.',
                    'proposed'          => '',
                    'rationale'         => 'Le ton menaçant de cet avertissement génère de l\'anxiété inutile sans apporter de valeur pédagogique. Le retirer et intégrer les attentes dans la grille de critères est plus efficace.',
                    'profile_target'    => 'anxieux_consignes',
                    'impact_delta'      => [
                        'anxieux_consignes'   => 10,
                        'concentration_tdah'  => 3,
                    ],
                ],
            ],
        ];
    }


    private static function mock_student_twin_response( array $params ): array {
        $message = $params['message'] ?? '';

        return [
            'success'             => true,
            'reply'               => 'Je comprends ta question ! Pour analyser un texte littéraire, commence par identifier le procédé stylistique utilisé — par exemple, une métaphore compare deux éléments sans "comme". Ensuite, demande-toi : quel effet cela crée-t-il sur le lecteur ? Essaie d\'abord avec le premier paragraphe, puis on pourra revoir ensemble.',
            'guardrail_triggered' => false,
            'guardrail_reason'    => null,
            'follow_up_questions' => [
                'Quel procédé as-tu identifié dans le premier paragraphe ?',
                'Comment ce procédé contribue-t-il au thème du texte ?',
                'As-tu besoin d\'un exemple de métaphore pour t\'aider ?',
            ],
        ];
    }

    private static function mock_guardrail_check( array $params ): array {
        return [
            'success'             => true,
            'guardrail_triggered' => false,
            'reason'              => '',
        ];
    }

    private static function mock_dashboard_summary( array $params ): array {
        return [
            'success' => true,
            'summary' => 'L\'analyse révèle un cours bien adapté aux étudiants avancés, mais nécessitant des ajustements ' .
                         'pour les profils TDAH et allophones. Les recommandations prioritaires portent sur la clarification ' .
                         'des consignes et l\'ajout d\'exemples concrets. L\'impact estimé des modifications suggérées ' .
                         'pourrait améliorer l\'accessibilité globale de 12 à 18 points.',
        ];
    }

    // -------------------------------------------------------------------------
    // Helper : profils actifs
    // -------------------------------------------------------------------------

    /**
     * Retourne les slugs des profils actifs via Profile_Manager si disponible,
     * sinon retourne les 7 slugs par défaut.
     */
    private static function get_active_profile_slugs(): array {
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $profiles = PedagoLens_Profile_Manager::get_all( active_only: true );
            return array_column( $profiles, 'slug' );
        }

        // Fallback si pedagolens-core n'est pas encore chargé
        return array_keys( self::MOCK_SCORES );
    }
}
