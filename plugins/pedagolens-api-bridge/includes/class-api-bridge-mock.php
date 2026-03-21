<?php
/**
 * PedagoLens_API_Bridge_Mock
 *
 * Retourne des réponses de démonstration crédibles et contextuelles pour chaque prompt_key,
 * sans aucun appel HTTP externe. Simule des délais réalistes de traitement IA.
 *
 * Thème de démonstration : cours de sciences — Réalité Augmentée / Réalité Virtuelle (RA/RV)
 * appliquée à l'enseignement des sciences au collégial.
 *
 * Les profils actifs sont lus dynamiquement via PedagoLens_Profile_Manager.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_API_Bridge_Mock {

    /** Scores mock réalistes par profil pour le cours RA/RV en sciences. */
    private const MOCK_SCORES = [
        'concentration_tdah'  => 71,
        'surcharge_cognitive' => 58,
        'langue_seconde'      => 55,
        'faible_autonomie'    => 62,
        'anxieux_consignes'   => 49,
        'avance_rapide'       => 85,
        'usage_passif_ia'     => 68,
    ];

    /** Banque de réponses contextuelles pour le chat Léa. */
    private const CHAT_RESPONSES = [
        'default' => [
            'reply' => "Super question ! 🔬 En sciences, la réalité augmentée (RA) permet de superposer des modèles 3D sur le monde réel — imagine pointer ton téléphone vers ta table de labo et voir apparaître une molécule d'ADN en 3D que tu peux faire tourner !\n\nLa réalité virtuelle (RV), elle, t'immerge complètement dans un environnement simulé. Par exemple, tu pourrais \"entrer\" dans une cellule humaine et observer les organites de l'intérieur.\n\nPour bien comprendre la différence, pose-toi cette question : est-ce que la technologie ajoute quelque chose au monde réel (RA) ou est-ce qu'elle le remplace complètement (RV) ?",
            'follow_up' => [
                "Peux-tu me donner un exemple concret où la RA serait plus utile que la RV en laboratoire ?",
                "Quels sont les avantages pédagogiques de manipuler un modèle 3D par rapport à un schéma 2D dans ton manuel ?",
                "Comment la RA pourrait-elle aider un étudiant qui a du mal à visualiser les structures moléculaires ?"
            ],
        ],
        'molecule' => [
            'reply' => "Bonne réflexion ! 🧬 La structure moléculaire en 3D est effectivement plus facile à comprendre avec la RA. Quand tu regardes un schéma 2D d'une molécule d'eau (H₂O), tu vois juste des cercles et des lignes. Mais en RA, tu peux voir l'angle de 104.5° entre les liaisons O-H, et comprendre pourquoi la molécule est polaire.\n\nEssaie de réfléchir : pourquoi est-ce que cet angle est important pour les propriétés de l'eau ? Pense à ce que tu sais sur les liaisons hydrogène...",
            'follow_up' => [
                "Quel lien fais-tu entre la géométrie de la molécule d'eau et ses propriétés comme solvant ?",
                "Si tu pouvais manipuler le modèle 3D, que changerais-tu pour observer l'effet sur la polarité ?",
                "Comment expliquerais-tu la différence entre liaison covalente et liaison hydrogène à un camarade ?"
            ],
        ],
        'labo' => [
            'reply' => "Excellente question sur les labos virtuels ! 🧪 Un laboratoire en RV te permet de faire des expériences qui seraient trop dangereuses, trop coûteuses ou trop longues en vrai labo. Par exemple, tu pourrais simuler une réaction exothermique violente sans risque, ou observer la croissance d'une culture bactérienne en accéléré.\n\nMais attention — la RV ne remplace pas complètement le vrai labo ! La manipulation physique (pipeter, peser, observer au microscope) développe des compétences motrices essentielles.\n\nÀ ton avis, dans quels cas le labo virtuel est-il un complément utile, et dans quels cas est-il insuffisant ?",
            'follow_up' => [
                "Quelles compétences de laboratoire ne peuvent pas être développées en RV selon toi ?",
                "Comment un labo virtuel pourrait-il aider un étudiant anxieux à se préparer avant un vrai TP ?",
                "Peux-tu identifier 3 avantages et 3 limites des labos virtuels ?"
            ],
        ],
        'evaluation' => [
            'reply' => "C'est une question pertinente sur l'évaluation ! 📝 Évaluer les apprentissages en RA/RV demande de repenser nos méthodes. On ne peut pas juste faire un examen écrit traditionnel quand l'étudiant a appris en manipulant des objets 3D.\n\nPlusieurs approches sont possibles :\n• L'évaluation par portfolio numérique (captures d'écran annotées de tes manipulations 3D)\n• L'évaluation par les pairs en environnement collaboratif VR\n• Les quiz intégrés directement dans l'expérience RA\n\nRéfléchis : quelle méthode d'évaluation te semblerait la plus juste pour mesurer ta compréhension d'une structure moléculaire ?",
            'follow_up' => [
                "Quelle différence fais-tu entre évaluer la mémorisation et évaluer la compréhension en 3D ?",
                "Comment un portfolio numérique pourrait-il montrer ta progression dans le cours ?",
                "Est-ce que l'évaluation par les pairs en RV te semble plus ou moins stressante qu'un examen écrit ?"
            ],
        ],
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
    // 1. Analyse de cours (délai : 15 secondes)
    // -------------------------------------------------------------------------

    private static function mock_course_analysis( array $params ): array {
        sleep( 15 );

        $active_profiles = self::get_active_profile_slugs();
        $profile_scores  = [];
        foreach ( $active_profiles as $slug ) {
            $profile_scores[ $slug ] = self::MOCK_SCORES[ $slug ] ?? rand( 50, 85 );
        }

        $course_title = $params['course_title'] ?? 'RA/RV appliquée à l\'enseignement des sciences';

        return [
            'success'        => true,
            'profile_scores' => $profile_scores,
            'recommendations' => [
                [
                    'section'        => 'Diapositive 1 — Introduction à la RA/RV',
                    'text'           => 'Ajouter un schéma comparatif RA vs RV avec des icônes visuelles claires. Le texte actuel est trop dense pour une première diapositive — fragmenter en 3 points clés maximum.',
                    'priority'       => 1,
                    'profile_target' => 'surcharge_cognitive',
                ],
                [
                    'section'        => 'Diapositive 2 — Applications en laboratoire',
                    'text'           => 'Reformuler les consignes de manipulation virtuelle en étapes numérotées (1, 2, 3) avec des verbes d\'action. Ajouter une capture d\'écran annotée de l\'interface RA pour chaque étape.',
                    'priority'       => 1,
                    'profile_target' => 'concentration_tdah',
                ],
                [
                    'section'        => 'Diapositive 3 — Modélisation moléculaire 3D',
                    'text'           => 'Intégrer un glossaire visuel des termes techniques (liaison covalente, polarité, géométrie moléculaire) avec traductions anglaises entre parenthèses pour les étudiants allophones.',
                    'priority'       => 2,
                    'profile_target' => 'langue_seconde',
                ],
                [
                    'section'        => 'Diapositive 4 — Évaluation et portfolio',
                    'text'           => 'Clarifier les critères d\'évaluation du portfolio numérique avec une grille détaillée (points par critère). Le libellé actuel « sera évalué selon la qualité » est trop vague et anxiogène.',
                    'priority'       => 1,
                    'profile_target' => 'anxieux_consignes',
                ],
                [
                    'section'        => 'Diapositive 2 — Applications en laboratoire',
                    'text'           => 'Ajouter des questions de réflexion intermédiaires pour éviter que les étudiants avancés ne survolent le contenu. Proposer un défi optionnel de modélisation avancée.',
                    'priority'       => 3,
                    'profile_target' => 'avance_rapide',
                ],
                [
                    'section'        => 'Diapositive 3 — Modélisation moléculaire 3D',
                    'text'           => 'Ajouter un guide pas-à-pas pour la première manipulation 3D avec des points de contrôle (« As-tu réussi à faire tourner la molécule ? Oui/Non »). Les étudiants à faible autonomie ont besoin de validation intermédiaire.',
                    'priority'       => 2,
                    'profile_target' => 'faible_autonomie',
                ],
            ],
            'impact_estimates' => [
                'diapo_1_fragmentation' => [
                    'surcharge_cognitive' => 14,
                    'concentration_tdah'  => 8,
                    'langue_seconde'      => 6,
                ],
                'diapo_2_etapes_numerotees' => [
                    'concentration_tdah'  => 16,
                    'faible_autonomie'    => 10,
                    'surcharge_cognitive' => 7,
                ],
                'diapo_3_glossaire' => [
                    'langue_seconde'      => 18,
                    'surcharge_cognitive' => 5,
                    'faible_autonomie'    => 4,
                ],
                'diapo_4_grille_evaluation' => [
                    'anxieux_consignes'   => 22,
                    'surcharge_cognitive' => 6,
                    'faible_autonomie'    => 8,
                ],
            ],
            'summary' => sprintf(
                'Le cours « %s » obtient un score d\'accessibilité global de 64/100. '
                . 'Les étudiants avancés (85/100) sont bien servis par le contenu technique riche, '
                . 'mais les profils anxieux face aux consignes (49/100) et allophones (55/100) rencontrent des obstacles significatifs. '
                . 'La surcharge cognitive (58/100) est préoccupante sur les diapositives 1 et 3 qui contiennent trop d\'information dense. '
                . 'Six améliorations prioritaires ont été identifiées, avec un impact estimé de +14 à +22 points sur les profils ciblés. '
                . 'Les modifications les plus urgentes concernent la clarification des critères d\'évaluation (diapo 4) '
                . 'et la fragmentation du contenu introductif (diapo 1).',
                esc_html( $course_title )
            ),
        ];
    }


    // -------------------------------------------------------------------------
    // 2. Suggestions workbench (délai : 15 secondes)
    // -------------------------------------------------------------------------

    private static function mock_workbench_suggestions( array $params ): array {
        sleep( 15 );

        $section   = $params['section'] ?? 'Introduction à la RA/RV';
        $slide_num = $params['slide_num'] ?? 1;
        $uid       = substr( md5( $section . microtime() ), 0, 8 );

        $profile_slugs  = self::get_active_profile_slugs();
        $profile_scores = [];
        foreach ( $profile_slugs as $slug ) {
            $profile_scores[ $slug ] = self::MOCK_SCORES[ $slug ] ?? rand( 50, 85 );
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
                    'impact_score'      => 88,
                    'original'          => 'La réalité augmentée (RA) superpose des éléments virtuels au monde réel tandis que la réalité virtuelle (RV) immerge l\'utilisateur dans un environnement entièrement simulé. Ces technologies offrent des possibilités pédagogiques considérables pour l\'enseignement des sciences, notamment en permettant la visualisation de phénomènes invisibles à l\'œil nu et la manipulation d\'objets dangereux ou inaccessibles.',
                    'proposed'          => "**Réalité Augmentée (RA)** 📱\n→ Ajoute des éléments virtuels au monde réel\n→ Exemple : pointer son téléphone vers un bécher et voir la réaction chimique en 3D\n\n**Réalité Virtuelle (RV)** 🥽\n→ Immersion complète dans un monde simulé\n→ Exemple : « entrer » dans une cellule humaine pour observer les organites\n\n**Pourquoi en sciences ?**\n✓ Voir l'invisible (molécules, ondes, cellules)\n✓ Manipuler sans danger (réactions exothermiques)\n✓ Répéter sans coût (réactifs virtuels illimités)",
                    'rationale'         => 'Le paragraphe dense est remplacé par une structure visuelle avec icônes, exemples concrets et points clés. Réduit la surcharge cognitive de 40% selon les principes de Mayer (2009) sur le multimédia pédagogique.',
                    'profile_target'    => 'surcharge_cognitive',
                    'impact_delta'      => [
                        'surcharge_cognitive' => 18,
                        'concentration_tdah'  => 12,
                        'langue_seconde'      => 8,
                        'faible_autonomie'    => 5,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_2",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'ajout',
                    'impact_score'      => 82,
                    'original'          => '',
                    'proposed'          => "📖 **Glossaire — Termes clés de cette diapositive**\n\n| Terme | Définition | English |\n|-------|-----------|--------|\n| Réalité augmentée | Technologie qui superpose des images virtuelles sur le monde réel | Augmented Reality (AR) |\n| Réalité virtuelle | Technologie qui crée un environnement 3D immersif | Virtual Reality (VR) |\n| Modélisation 3D | Représentation numérique en trois dimensions d'un objet | 3D Modeling |\n| Organite | Structure spécialisée à l'intérieur d'une cellule | Organelle |",
                    'rationale'         => 'Un glossaire bilingue intégré directement dans la diapositive évite aux étudiants allophones de devoir chercher les termes ailleurs, réduisant la charge cognitive et le temps de compréhension.',
                    'profile_target'    => 'langue_seconde',
                    'impact_delta'      => [
                        'langue_seconde'      => 22,
                        'surcharge_cognitive' => 6,
                        'faible_autonomie'    => 4,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_3",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'restructuration',
                    'impact_score'      => 79,
                    'original'          => 'Manipulez le modèle moléculaire 3D pour identifier les liaisons et la géométrie de la molécule assignée.',
                    'proposed'          => "🔬 **Activité guidée — Manipulation moléculaire 3D**\n\n**Étape 1** : Ouvre l'application RA sur ta tablette (icône 🧬)\n**Étape 2** : Pointe la caméra vers le marqueur sur ta table\n**Étape 3** : Touche la molécule pour la faire tourner — identifie les atomes (sphères colorées)\n**Étape 4** : Pince pour zoomer — observe les liaisons (bâtonnets entre les sphères)\n**Étape 5** : Capture d'écran → annote avec le nom de chaque atome\n\n✅ **Point de contrôle** : As-tu identifié au moins 2 types d'atomes différents ? Si oui, passe à l'étape suivante !",
                    'rationale'         => 'La consigne unique et vague est transformée en 5 micro-étapes avec verbes d\'action et un point de contrôle. Essentiel pour les étudiants TDAH (structure) et à faible autonomie (validation intermédiaire).',
                    'profile_target'    => 'concentration_tdah',
                    'impact_delta'      => [
                        'concentration_tdah'  => 16,
                        'faible_autonomie'    => 14,
                        'anxieux_consignes'   => 8,
                        'surcharge_cognitive' => 5,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_4",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'reformulation',
                    'impact_score'      => 91,
                    'original'          => 'L\'évaluation portera sur la qualité de votre portfolio numérique et votre participation aux activités de laboratoire virtuel.',
                    'proposed'          => "📋 **Grille d'évaluation du portfolio numérique** (total : 30 points)\n\n| Critère | Points | Détail |\n|---------|--------|--------|\n| Captures annotées des manipulations 3D | 10 pts | Au moins 3 captures avec annotations claires |\n| Réflexion écrite sur l'apport de la RA/RV | 10 pts | 200-300 mots, lien avec les objectifs du cours |\n| Participation aux labos virtuels | 5 pts | Présence + complétion des 3 activités |\n| Qualité de la présentation | 5 pts | Organisation, clarté, orthographe |\n\n📅 **Date de remise** : Semaine 12 — Dépôt sur Moodle avant 23h59",
                    'rationale'         => 'Les critères vagues (« qualité », « participation ») sont remplacés par une grille chiffrée avec des attentes explicites. Réduit drastiquement l\'anxiété liée à l\'ambiguïté des consignes d\'évaluation.',
                    'profile_target'    => 'anxieux_consignes',
                    'impact_delta'      => [
                        'anxieux_consignes'   => 24,
                        'faible_autonomie'    => 10,
                        'surcharge_cognitive' => 4,
                    ],
                ],
                [
                    'id'                => "sug_{$uid}_5",
                    'section'           => $section,
                    'slide_num'         => (int) $slide_num,
                    'modification_type' => 'ajout',
                    'impact_score'      => 65,
                    'original'          => '',
                    'proposed'          => "🚀 **Défi avancé (optionnel)**\n\nTu maîtrises déjà les bases de la modélisation 3D ? Essaie ceci :\n1. Construis un modèle moléculaire de la caféine (C₈H₁₀N₄O₂) dans l'application RA\n2. Compare sa structure avec celle de l'adénosine — pourquoi la caféine bloque-t-elle les récepteurs ?\n3. Rédige une courte explication (150 mots) avec capture d'écran annotée\n\n💡 Ce défi peut remplacer l'activité 3 du portfolio pour les étudiants qui souhaitent approfondir.",
                    'rationale'         => 'Les étudiants avancés risquent de s\'ennuyer avec les activités de base. Un défi optionnel stimulant maintient leur engagement sans pénaliser les autres profils.',
                    'profile_target'    => 'avance_rapide',
                    'impact_delta'      => [
                        'avance_rapide'       => 12,
                        'usage_passif_ia'     => 5,
                    ],
                ],
            ],
        ];
    }


    // -------------------------------------------------------------------------
    // 3. Réponse chat Léa — Jumeau IA étudiant (délai : 4 secondes)
    // -------------------------------------------------------------------------

    private static function mock_student_twin_response( array $params ): array {
        sleep( 4 );

        $message = strtolower( $params['message'] ?? '' );

        // Détection du guardrail : demande de travail direct
        if ( self::is_guardrail_triggered( $message ) ) {
            return [
                'success'             => true,
                'reply'               => "Je comprends que tu veuilles avancer vite, mais je ne peux pas faire ton travail à ta place — ce ne serait pas te rendre service ! 😊\n\nPar contre, je peux t'aider à comprendre les concepts et à structurer ta réflexion. Dis-moi précisément ce qui te bloque et on va travailler ensemble étape par étape.\n\nPar exemple, est-ce que c'est la différence entre RA et RV qui n'est pas claire, ou plutôt comment les appliquer dans ton contexte de labo ?",
                'guardrail_triggered' => true,
                'follow_up_questions' => [
                    "Quel aspect du cours te semble le plus difficile en ce moment ?",
                    "As-tu déjà commencé à travailler sur ton portfolio ? Où en es-tu ?",
                    "Veux-tu qu'on revoie ensemble les concepts de base avant d'attaquer l'exercice ?",
                ],
            ];
        }

        // Sélection contextuelle de la réponse
        $response_key = 'default';
        if ( preg_match( '/mol[eé]cul|liaison|atome|3d|polari|chimiq/u', $message ) ) {
            $response_key = 'molecule';
        } elseif ( preg_match( '/labo|exp[eé]rience|manipul|danger|s[eé]curit/u', $message ) ) {
            $response_key = 'labo';
        } elseif ( preg_match( '/[eé]valu|note|portfolio|crit[eè]re|examen|grille/u', $message ) ) {
            $response_key = 'evaluation';
        }

        $data = self::CHAT_RESPONSES[ $response_key ];

        return [
            'success'             => true,
            'reply'               => $data['reply'],
            'guardrail_triggered' => false,
            'follow_up_questions' => $data['follow_up'],
        ];
    }

    /**
     * Détecte si le message étudiant demande de faire le travail à sa place.
     */
    private static function is_guardrail_triggered( string $message ): bool {
        $patterns = [
            '/fais.*(mon|le|ce).*(devoir|travail|exercice|portfolio|rapport)/u',
            '/[eé]cris.*(mon|le|ce).*(texte|essai|analyse|r[eé]ponse|rapport)/u',
            '/donne.*(moi|nous).*(la|les).*(r[eé]ponse|solution)/u',
            '/r[eé]dige.*(pour moi|[àa] ma place)/u',
            '/compl[eè]te.*(mon|le|ce).*(devoir|travail)/u',
            '/r[eé]sous.*(pour moi|[àa] ma place)/u',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $message ) ) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // 4. Vérification guardrail (pas de délai)
    // -------------------------------------------------------------------------

    private static function mock_guardrail_check( array $params ): array {
        $message = strtolower( $params['message'] ?? '' );
        $triggered = self::is_guardrail_triggered( $message );

        return [
            'success'             => true,
            'guardrail_triggered' => $triggered,
            'reason'              => $triggered
                ? 'L\'étudiant demande que l\'IA fasse son travail académique à sa place.'
                : '',
        ];
    }

    // -------------------------------------------------------------------------
    // 5. Résumé dashboard enseignant (délai : 4 secondes)
    // -------------------------------------------------------------------------

    private static function mock_dashboard_summary( array $params ): array {
        sleep( 4 );

        return [
            'success' => true,
            'summary' => 'L\'analyse du cours « RA/RV appliquée à l\'enseignement des sciences » révèle un contenu '
                . 'techniquement solide mais présentant des défis d\'accessibilité significatifs. '
                . 'Le score global d\'accessibilité est de 64/100, avec des écarts importants entre les profils : '
                . 'les étudiants avancés (85/100) bénéficient pleinement du contenu riche, tandis que les profils '
                . 'anxieux face aux consignes (49/100) et allophones (55/100) rencontrent des obstacles majeurs. '
                . "\n\n"
                . '**Points forts identifiés :**' . "\n"
                . '• Contenu scientifique rigoureux et à jour sur les technologies RA/RV' . "\n"
                . '• Activités pratiques de manipulation 3D engageantes' . "\n"
                . '• Bonne progression thématique (théorie → pratique → évaluation)' . "\n\n"
                . '**Améliorations prioritaires :**' . "\n"
                . '• 🔴 Clarifier les critères d\'évaluation du portfolio (impact : +22 pts profil anxieux)' . "\n"
                . '• 🔴 Fragmenter la diapositive d\'introduction trop dense (impact : +14 pts surcharge cognitive)' . "\n"
                . '• 🟡 Ajouter un glossaire bilingue pour les termes techniques (impact : +18 pts allophones)' . "\n"
                . '• 🟡 Transformer les consignes en micro-étapes numérotées (impact : +16 pts TDAH)' . "\n\n"
                . 'L\'application des 6 suggestions proposées pourrait faire passer le score global de 64 à environ 78/100, '
                . 'soit une amélioration de 22% de l\'accessibilité pédagogique.',
        ];
    }

    // -------------------------------------------------------------------------
    // Helper : profils actifs
    // -------------------------------------------------------------------------

    private static function get_active_profile_slugs(): array {
        if ( class_exists( 'PedagoLens_Profile_Manager' ) ) {
            $profiles = PedagoLens_Profile_Manager::get_all( active_only: true );
            return array_column( $profiles, 'slug' );
        }
        return array_keys( self::MOCK_SCORES );
    }
}
