<?php
/**
 * Classe principale PedagoLens_API_Bridge
 *
 * Point d'entrée public du plugin. Expose les méthodes statiques utilisées
 * par les autres plugins PédagoLens pour invoquer l'IA et lire la configuration.
 */

defined( 'ABSPATH' ) || exit;

class PedagoLens_API_Bridge {

    /** Clés de prompt gérées par le bridge. */
    public const PROMPT_KEYS = [
        'course_analysis',
        'workbench_suggestions',
        'student_twin_response',
        'student_guardrail_check',
        'dashboard_insight_summary',
    ];

    /** Schémas de validation JSON par prompt_key. */
    private const RESPONSE_SCHEMAS = [
        'course_analysis' => [
            'profile_scores'   => 'array',
            'recommendations'  => 'array',
            'impact_estimates' => 'array',
            'summary'          => 'string',
        ],
        'workbench_suggestions' => [
            'suggestions' => 'array',
        ],
        'student_twin_response' => [
            'reply'               => 'string',
            'guardrail_triggered' => 'bool',
            'follow_up_questions' => 'array',
        ],
        'student_guardrail_check' => [
            'guardrail_triggered' => 'bool',
            'reason'              => 'string',
        ],
        'dashboard_insight_summary' => [
            'summary' => 'string',
        ],
    ];

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init(): void {
        PedagoLens_API_Bridge_Settings::register();
    }

    // -------------------------------------------------------------------------
    // Interface publique principale
    // -------------------------------------------------------------------------

    /**
     * Point d'entrée unique pour tous les appels IA.
     *
     * @param string $prompt_key  Une des 5 clés définies dans PROMPT_KEYS.
     * @param array  $params      Paramètres contextuels passés au prompt.
     * @return array              Résultat structuré ou tableau d'erreur.
     */
    public static function invoke( string $prompt_key, array $params ): array {
        if ( ! in_array( $prompt_key, self::PROMPT_KEYS, true ) ) {
            return self::error( 'pl_prompt_not_found', "Clé de prompt inconnue : {$prompt_key}" );
        }

        $mode = self::get_ai_mode();

        if ( $mode === 'mock' ) {
            return PedagoLens_API_Bridge_Mock::invoke( $prompt_key, $params );
        }

        return self::invoke_bedrock( $prompt_key, $params );
    }

    /**
     * Lit le template d'un prompt depuis les options WP.
     */
    public static function get_prompt_template( string $key ): string {
        $stored = get_option( "pl_prompt_{$key}", '' );
        if ( $stored !== '' ) {
            return $stored;
        }

        $defaults = self::default_prompt_templates();
        return $defaults[ $key ] ?? '';
    }

    /**
     * Valide la structure JSON d'une réponse selon le schéma du prompt_key.
     */
    public static function validate_response( array $response, string $schema_key ): bool {
        $schema = self::RESPONSE_SCHEMAS[ $schema_key ] ?? null;
        if ( $schema === null ) {
            return false;
        }

        foreach ( $schema as $field => $type ) {
            if ( ! array_key_exists( $field, $response ) ) {
                return false;
            }
            if ( ! self::check_type( $response[ $field ], $type ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retourne les modèles Bedrock disponibles (liste statique pour la démo).
     */
    public static function get_available_models(): array {
        return [
            'anthropic.claude-sonnet-4-20250514-v2:0',
            'anthropic.claude-3-5-sonnet-20241022-v2:0',
            'anthropic.claude-3-haiku-20240307-v1:0',
        ];
    }

    // -------------------------------------------------------------------------
    // Lecture de la configuration (utilisée par les autres plugins)
    // -------------------------------------------------------------------------

    /**
     * Retourne la configuration Bedrock (sans credentials).
     */
    public static function get_bedrock_config(): array {
        return [
            'region'      => get_option( 'pl_bedrock_region',      'us-east-1' ),
            'model_id'    => get_option( 'pl_bedrock_model_id',    'anthropic.claude-sonnet-4-20250514-v2:0' ),
            'max_tokens'  => (int) get_option( 'pl_bedrock_max_tokens',  1500 ),
            'temperature' => (float) get_option( 'pl_bedrock_temperature', 0.3 ),
            'timeout'     => (int) get_option( 'pl_bedrock_timeout',     30 ),
        ];
    }

    /**
     * Retourne les credentials AWS.
     *
     * Priorité : constantes wp-config.php → variables d'environnement → options WP (fallback hackathon).
     * En production, définir AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_SESSION_TOKEN
     * dans wp-config.php ou en variables d'environnement — jamais dans les options WP.
     */
    public static function get_aws_credentials(): array {
        return [
            'access_key_id'     => defined( 'AWS_ACCESS_KEY_ID' )     ? AWS_ACCESS_KEY_ID     : ( getenv( 'AWS_ACCESS_KEY_ID' )     ?: get_option( 'pl_aws_access_key_id',     '' ) ),
            'secret_access_key' => defined( 'AWS_SECRET_ACCESS_KEY' ) ? AWS_SECRET_ACCESS_KEY : ( getenv( 'AWS_SECRET_ACCESS_KEY' ) ?: get_option( 'pl_aws_secret_access_key', '' ) ),
            'session_token'     => defined( 'AWS_SESSION_TOKEN' )     ? AWS_SESSION_TOKEN     : ( getenv( 'AWS_SESSION_TOKEN' )     ?: get_option( 'pl_aws_session_token',      '' ) ),
        ];
    }

    public static function get_ai_mode(): string {
        return get_option( 'pl_ai_mode', 'mock' );
    }

    // -------------------------------------------------------------------------
    // Appel Bedrock réel
    // -------------------------------------------------------------------------

    private static function invoke_bedrock( string $prompt_key, array $params ): array {
        $credentials = self::get_aws_credentials();

        if ( empty( $credentials['access_key_id'] ) || empty( $credentials['secret_access_key'] ) ) {
            return self::error( 'pl_bedrock_auth_error', 'Credentials AWS manquants.' );
        }

        $config  = self::get_bedrock_config();
        $template = self::get_prompt_template( $prompt_key );

        if ( $template === '' ) {
            return self::error( 'pl_prompt_not_found', "Template absent pour la clé : {$prompt_key}" );
        }

        do_action( 'pedagolens_before_ai_invoke', $prompt_key, $params );

        $payload  = self::build_bedrock_payload( $template, $params, $config );
        $endpoint = "https://bedrock-runtime.{$config['region']}.amazonaws.com/model/{$config['model_id']}/invoke";

        $response = self::http_post_bedrock( $endpoint, $payload, $credentials, $config );

        if ( is_wp_error( $response ) ) {
            return self::error( 'pl_bedrock_timeout', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 429 ) {
            return self::error( 'pl_bedrock_rate_limit', 'Quota Bedrock dépassé.' );
        }

        if ( $code !== 200 ) {
            return self::error( 'pl_bedrock_invalid_response', "HTTP {$code} reçu de Bedrock." );
        }

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return self::error( 'pl_bedrock_invalid_response', 'Réponse Bedrock non JSON.' );
        }

        // Extraire le contenu texte de la réponse Claude
        $text = $decoded['content'][0]['text'] ?? '';
        $result = json_decode( $text, true );

        if ( ! is_array( $result ) ) {
            return self::error( 'pl_bedrock_invalid_response', 'Contenu Claude non JSON.' );
        }

        if ( ! self::validate_response( $result, $prompt_key ) ) {
            return self::error( 'pl_bedrock_invalid_response', 'Structure de réponse non conforme au schéma.' );
        }

        do_action( 'pedagolens_after_ai_invoke', $prompt_key, $result );

        return array_merge( [ 'success' => true ], $result );
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private static function build_bedrock_payload( string $template, array $params, array $config ): array {
        // Interpolation simple des paramètres dans le template
        $prompt = $template;
        foreach ( $params as $key => $value ) {
            if ( is_string( $value ) || is_numeric( $value ) ) {
                $prompt = str_replace( "{{$key}}", (string) $value, $prompt );
            }
        }

        return [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens'        => $config['max_tokens'],
            'temperature'       => $config['temperature'],
            'messages'          => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ];
    }

    private static function http_post_bedrock(
        string $endpoint,
        array $payload,
        array $credentials,
        array $config
    ): array|WP_Error {
        $body    = wp_json_encode( $payload );
        $date    = gmdate( 'Ymd\THis\Z' );
        $headers = self::sign_request( $endpoint, $body, $date, $credentials, $config['region'] );

        return wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => $config['timeout'],
        ] );
    }

    /**
     * Signature AWS Signature V4 minimale pour Bedrock.
     * Suffisant pour la démo hackathon.
     */
    private static function sign_request(
        string $endpoint,
        string $body,
        string $date,
        array $credentials,
        string $region
    ): array {
        $service      = 'bedrock';
        $short_date   = substr( $date, 0, 8 );
        $host         = parse_url( $endpoint, PHP_URL_HOST );
        $path         = parse_url( $endpoint, PHP_URL_PATH );
        $content_type = 'application/json';

        $canonical_headers = "content-type:{$content_type}\nhost:{$host}\nx-amz-date:{$date}\n";
        if ( ! empty( $credentials['session_token'] ) ) {
            $canonical_headers .= "x-amz-security-token:{$credentials['session_token']}\n";
        }

        $signed_headers = 'content-type;host;x-amz-date';
        if ( ! empty( $credentials['session_token'] ) ) {
            $signed_headers .= ';x-amz-security-token';
        }

        $payload_hash      = hash( 'sha256', $body );
        $canonical_request = implode( "\n", [
            'POST',
            $path,
            '',
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ] );

        $credential_scope = "{$short_date}/{$region}/{$service}/aws4_request";
        $string_to_sign   = implode( "\n", [
            'AWS4-HMAC-SHA256',
            $date,
            $credential_scope,
            hash( 'sha256', $canonical_request ),
        ] );

        $signing_key = self::derive_signing_key(
            $credentials['secret_access_key'],
            $short_date,
            $region,
            $service
        );

        $signature = bin2hex( hash_hmac( 'sha256', $string_to_sign, $signing_key, true ) );

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $credentials['access_key_id'],
            $credential_scope,
            $signed_headers,
            $signature
        );

        $headers = [
            'Content-Type'  => $content_type,
            'X-Amz-Date'    => $date,
            'Authorization' => $authorization,
        ];

        if ( ! empty( $credentials['session_token'] ) ) {
            $headers['X-Amz-Security-Token'] = $credentials['session_token'];
        }

        return $headers;
    }

    private static function derive_signing_key(
        string $secret,
        string $date,
        string $region,
        string $service
    ): string {
        $k_date    = hash_hmac( 'sha256', $date,              'AWS4' . $secret, true );
        $k_region  = hash_hmac( 'sha256', $region,            $k_date,          true );
        $k_service = hash_hmac( 'sha256', $service,           $k_region,        true );
        return       hash_hmac( 'sha256', 'aws4_request',     $k_service,       true );
    }

    private static function check_type( mixed $value, string $type ): bool {
        return match ( $type ) {
            'string' => is_string( $value ),
            'array'  => is_array( $value ),
            'bool'   => is_bool( $value ),
            'int'    => is_int( $value ),
            'float'  => is_float( $value ) || is_int( $value ),
            default  => true,
        };
    }

    /**
     * Construit un tableau d'erreur structuré uniforme.
     */
    public static function error( string $code, string $message, array $context = [] ): array {
        return [
            'success'       => false,
            'error_code'    => $code,
            'error_message' => $message,
            'context'       => $context,
        ];
    }

    /**
     * Masque un secret en n'affichant que les 4 derniers caractères.
     * Utiliser dans les logs et messages de debug — jamais la valeur brute.
     */
    public static function mask_secret( string $value ): string {
        if ( strlen( $value ) <= 4 ) {
            return str_repeat( '*', strlen( $value ) );
        }
        return str_repeat( '*', strlen( $value ) - 4 ) . substr( $value, -4 );
    }

    // -------------------------------------------------------------------------
    // Prompt templates par défaut
    // -------------------------------------------------------------------------

    private static function default_prompt_templates(): array {
        return [
            'course_analysis' =>
                "Analyse ce contenu de cours selon les profils d'apprenants fournis.\n" .
                "Retourne un JSON avec : profile_scores (objet slug→score /100), " .
                "recommendations (array), impact_estimates (objet), summary (string).\n\n" .
                "Contenu : {content}\nProfils : {profiles}",

            'workbench_suggestions' =>
                "Propose des suggestions d'amélioration pédagogique pour cette section de cours.\n\n" .
                "Contexte :\n" .
                "- Numéro de slide : {slide_num}\n" .
                "- Type de cours : {course_type} (magistral ou exercice)\n" .
                "- Profils pédagogiques actifs : {active_profiles}\n\n" .
                "Pour chaque suggestion, retourne un objet JSON contenant :\n" .
                "- id (string) — identifiant unique de la suggestion\n" .
                "- section (string) — nom de la section concernée\n" .
                "- slide_num (int) — numéro de la diapositive concernée\n" .
                "- modification_type (string) — \"reformulation\", \"ajout\", \"suppression\" ou \"restructuration\"\n" .
                "- impact_score (int 0-100) — score d'impact estimé de la modification\n" .
                "- original (string) — texte original\n" .
                "- proposed (string) — texte proposé\n" .
                "- rationale (string) — justification pédagogique\n" .
                "- profile_target (string) — slug du profil pédagogique ciblé\n\n" .
                "Retourne un JSON avec : suggestions (array des objets ci-dessus).\n\n" .
                "Section : {section}\nContenu : {content}",

            'student_twin_response' =>
                "Tu es un jumeau numérique pédagogique. Réponds à l'étudiant de façon guidée.\n" .
                "Retourne un JSON avec : reply (string), guardrail_triggered (bool), " .
                "guardrail_reason (string|null), follow_up_questions (array).\n\n" .
                "Message : {message}\nContexte cours : {course_context}",

            'student_guardrail_check' =>
                "Vérifie si ce message étudiant contient une demande de travail académique direct.\n" .
                "Retourne un JSON avec : guardrail_triggered (bool), reason (string).\n\n" .
                "Message : {message}\nSujets interdits : {forbidden_topics}",

            'dashboard_insight_summary' =>
                "Génère un résumé pédagogique narratif de cette analyse de cours.\n" .
                "Retourne un JSON avec : summary (string).\n\n" .
                "Scores : {scores}\nRecommandations : {recommendations}",
        ];
    }
}
