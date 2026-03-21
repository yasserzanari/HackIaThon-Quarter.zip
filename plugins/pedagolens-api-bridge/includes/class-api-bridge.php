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
            'suggestions'    => 'array',
            'profile_scores' => 'array?',
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
            $optional = str_ends_with( $type, '?' );
            $clean_type = rtrim( $type, '?' );
            if ( ! array_key_exists( $field, $response ) ) {
                if ( $optional ) {
                    continue;
                }
                return false;
            }
            if ( ! self::check_type( $response[ $field ], $clean_type ) ) {
                if ( $optional ) {
                    continue;
                }
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
            'model_id'    => get_option( 'pl_bedrock_model_id',    'us.anthropic.claude-sonnet-4-20250514-v1:0' ),
            'max_tokens'  => (int) get_option( 'pl_bedrock_max_tokens',  4096 ),
            'temperature' => (float) get_option( 'pl_bedrock_temperature', 0.3 ),
            'timeout'     => (int) get_option( 'pl_bedrock_timeout',     30 ),
        ];
    }

    /**
     * Retourne les credentials AWS.
     *
     * Priorité :
     *   1. Constantes wp-config.php
     *   2. Variables d'environnement
     *   3. Options WP (fallback hackathon)
     *   4. EC2 Instance Profile via IMDS v2 (IAM role)
     */
    public static function get_aws_credentials(): array {
        $creds = [
            'access_key_id'     => defined( 'AWS_ACCESS_KEY_ID' )     ? AWS_ACCESS_KEY_ID     : ( getenv( 'AWS_ACCESS_KEY_ID' )     ?: get_option( 'pl_aws_access_key_id',     '' ) ),
            'secret_access_key' => defined( 'AWS_SECRET_ACCESS_KEY' ) ? AWS_SECRET_ACCESS_KEY : ( getenv( 'AWS_SECRET_ACCESS_KEY' ) ?: get_option( 'pl_aws_secret_access_key', '' ) ),
            'session_token'     => defined( 'AWS_SESSION_TOKEN' )     ? AWS_SESSION_TOKEN     : ( getenv( 'AWS_SESSION_TOKEN' )     ?: get_option( 'pl_aws_session_token',      '' ) ),
        ];

        // If we already have valid static credentials, return them.
        if ( ! empty( $creds['access_key_id'] ) && ! empty( $creds['secret_access_key'] ) ) {
            return $creds;
        }

        // Fallback: EC2 Instance Profile via IMDS v2.
        return self::get_imds_credentials();
    }

    /**
     * Fetch temporary credentials from EC2 Instance Metadata Service (IMDS v2).
     *
     * Uses a WP transient to cache credentials until ~5 min before expiration.
     *
     * @return array Credentials array (may have empty values on failure).
     */
    private static function get_imds_credentials(): array {
        $empty = [ 'access_key_id' => '', 'secret_access_key' => '', 'session_token' => '' ];

        // Check transient cache first.
        $cached = get_transient( 'pl_imds_credentials' );
        if ( is_array( $cached ) && ! empty( $cached['access_key_id'] ) ) {
            return $cached;
        }

        // Step 1: Get IMDS v2 token (required for IMDSv2).
        $token_response = wp_remote_request( 'http://169.254.169.254/latest/api/token', [
            'method'  => 'PUT',
            'headers' => [ 'X-aws-ec2-metadata-token-ttl-seconds' => '21600' ],
            'timeout' => 2,
        ] );

        if ( is_wp_error( $token_response ) || wp_remote_retrieve_response_code( $token_response ) !== 200 ) {
            if ( class_exists( 'PedagoLens_Core' ) ) {
                $err = is_wp_error( $token_response ) ? $token_response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $token_response );
                PedagoLens_Core::log( 'error', "IMDS v2 token request failed: {$err}" );
            }
            return $empty;
        }

        $token = wp_remote_retrieve_body( $token_response );

        // Step 2: Get the IAM role name attached to this instance.
        $role_response = wp_remote_get( 'http://169.254.169.254/latest/meta-data/iam/security-credentials/', [
            'headers' => [ 'X-aws-ec2-metadata-token' => $token ],
            'timeout' => 2,
        ] );

        if ( is_wp_error( $role_response ) || wp_remote_retrieve_response_code( $role_response ) !== 200 ) {
            if ( class_exists( 'PedagoLens_Core' ) ) {
                PedagoLens_Core::log( 'error', 'IMDS: unable to retrieve IAM role name.' );
            }
            return $empty;
        }

        $role_name = trim( wp_remote_retrieve_body( $role_response ) );
        if ( empty( $role_name ) ) {
            if ( class_exists( 'PedagoLens_Core' ) ) {
                PedagoLens_Core::log( 'error', 'IMDS: IAM role name is empty — no role attached?' );
            }
            return $empty;
        }

        // Step 3: Fetch the temporary credentials for that role.
        $creds_response = wp_remote_get(
            "http://169.254.169.254/latest/meta-data/iam/security-credentials/{$role_name}",
            [
                'headers' => [ 'X-aws-ec2-metadata-token' => $token ],
                'timeout' => 2,
            ]
        );

        if ( is_wp_error( $creds_response ) || wp_remote_retrieve_response_code( $creds_response ) !== 200 ) {
            if ( class_exists( 'PedagoLens_Core' ) ) {
                PedagoLens_Core::log( 'error', "IMDS: unable to retrieve credentials for role {$role_name}." );
            }
            return $empty;
        }

        $data = json_decode( wp_remote_retrieve_body( $creds_response ), true );
        if ( ! is_array( $data ) || empty( $data['AccessKeyId'] ) ) {
            if ( class_exists( 'PedagoLens_Core' ) ) {
                PedagoLens_Core::log( 'error', 'IMDS: credential response is invalid or missing AccessKeyId.' );
            }
            return $empty;
        }

        $creds = [
            'access_key_id'     => $data['AccessKeyId'],
            'secret_access_key' => $data['SecretAccessKey'],
            'session_token'     => $data['Token'] ?? '',
        ];

        // Cache in a transient. Credentials typically last 6 hours;
        // we cache for (expiration - 5 minutes) or 50 minutes as a safe default.
        $ttl = 50 * MINUTE_IN_SECONDS;
        if ( ! empty( $data['Expiration'] ) ) {
            $expires_at = strtotime( $data['Expiration'] );
            if ( $expires_at ) {
                $ttl = max( 60, $expires_at - time() - 5 * MINUTE_IN_SECONDS );
            }
        }
        set_transient( 'pl_imds_credentials', $creds, $ttl );

        if ( class_exists( 'PedagoLens_Core' ) ) {
            PedagoLens_Core::log( 'info', "IMDS: credentials fetched for role {$role_name}, cached for {$ttl}s." );
        }

        return $creds;
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
            self::log_error( 'pl_bedrock_auth_error', 'Credentials AWS manquants (constantes, env, options WP et IMDS tous vides).', [ 'prompt_key' => $prompt_key ] );
            return self::error( 'pl_bedrock_auth_error', 'Credentials AWS manquants.' );
        }

        $config  = self::get_bedrock_config();
        $template = self::get_prompt_template( $prompt_key );

        if ( $template === '' ) {
            self::log_error( 'pl_prompt_not_found', "Template absent pour la clé : {$prompt_key}" );
            return self::error( 'pl_prompt_not_found', "Template absent pour la clé : {$prompt_key}" );
        }

        do_action( 'pedagolens_before_ai_invoke', $prompt_key, $params );

        $payload  = self::build_bedrock_payload( $template, $params, $config );
        $endpoint = "https://bedrock-runtime.{$config['region']}.amazonaws.com/model/{$config['model_id']}/invoke";

        $response = self::http_post_bedrock( $endpoint, $payload, $credentials, $config );

        if ( is_wp_error( $response ) ) {
            self::log_error( 'pl_bedrock_timeout', $response->get_error_message(), [ 'prompt_key' => $prompt_key, 'model' => $config['model_id'] ] );
            return self::error( 'pl_bedrock_timeout', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 429 ) {
            self::log_error( 'pl_bedrock_rate_limit', 'Quota Bedrock dépassé.', [ 'prompt_key' => $prompt_key ] );
            return self::error( 'pl_bedrock_rate_limit', 'Quota Bedrock dépassé.' );
        }

        if ( $code !== 200 ) {
            self::log_error( 'pl_bedrock_invalid_response', "HTTP {$code} reçu de Bedrock.", [
                'prompt_key' => $prompt_key,
                'model'      => $config['model_id'],
                'body'       => mb_substr( $body, 0, 500 ),
            ] );
            return self::error( 'pl_bedrock_invalid_response', "HTTP {$code} reçu de Bedrock." );
        }

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            self::log_error( 'pl_bedrock_invalid_response', 'Réponse Bedrock non JSON.', [ 'body_preview' => mb_substr( $body, 0, 300 ) ] );
            return self::error( 'pl_bedrock_invalid_response', 'Réponse Bedrock non JSON.' );
        }

        // Extraire le contenu texte de la réponse Claude
        $text = $decoded['content'][0]['text'] ?? '';

        // Claude may wrap JSON in markdown code fences — strip them.
        // Also handle truncated responses where closing ``` is missing.
        $clean_text = trim( $text );

        // Case 1: Complete fences ```json ... ```
        if ( preg_match( '/^```(?:json)?\s*\n?(.*?)\n?\s*```$/s', $clean_text, $m ) ) {
            $clean_text = trim( $m[1] );
        }
        // Case 2: Opening fence but no closing (truncated response)
        elseif ( preg_match( '/^```(?:json)?\s*\n?(.*)/s', $clean_text, $m ) ) {
            $clean_text = trim( $m[1] );
            // Remove trailing incomplete ``` if partially present
            $clean_text = rtrim( $clean_text, '`' );
        }

        $result = json_decode( $clean_text, true );

        // If JSON is truncated (incomplete), try to repair it
        if ( ! is_array( $result ) && str_starts_with( $clean_text, '{' ) ) {
            $repaired = self::repair_truncated_json( $clean_text );
            if ( $repaired ) {
                $result = json_decode( $repaired, true );
            }
        }

        if ( ! is_array( $result ) ) {
            self::log_error( 'pl_bedrock_invalid_response', 'Contenu Claude non JSON.', [ 'text_preview' => mb_substr( $text, 0, 300 ) ] );
            return self::error( 'pl_bedrock_invalid_response', 'Contenu Claude non JSON.' );
        }

        if ( ! self::validate_response( $result, $prompt_key ) ) {
            self::log_error( 'pl_bedrock_invalid_response', 'Structure de réponse non conforme au schéma.', [
                'prompt_key' => $prompt_key,
                'keys'       => array_keys( $result ),
            ] );
            return self::error( 'pl_bedrock_invalid_response', 'Structure de réponse non conforme au schéma.' );
        }

        do_action( 'pedagolens_after_ai_invoke', $prompt_key, $result );

        return array_merge( [ 'success' => true ], $result );
    }

    /**
     * Log an error via PedagoLens_Core if available.
     */
    private static function log_error( string $code, string $message, array $context = [] ): void {
        if ( class_exists( 'PedagoLens_Core' ) ) {
            PedagoLens_Core::log( 'error', "[API Bridge] {$code}: {$message}", $context );
        }
    }

    /**
     * Attempt to repair truncated JSON from Claude (max_tokens hit).
     * Closes open strings, arrays, and objects to make it parseable.
     */
    private static function repair_truncated_json( string $json ): ?string {
        // Track nesting
        $in_string = false;
        $escape    = false;
        $stack     = [];

        for ( $i = 0, $len = strlen( $json ); $i < $len; $i++ ) {
            $ch = $json[ $i ];

            if ( $escape ) {
                $escape = false;
                continue;
            }

            if ( $ch === '\\' && $in_string ) {
                $escape = true;
                continue;
            }

            if ( $ch === '"' ) {
                $in_string = ! $in_string;
                continue;
            }

            if ( $in_string ) {
                continue;
            }

            if ( $ch === '{' ) {
                $stack[] = '}';
            } elseif ( $ch === '[' ) {
                $stack[] = ']';
            } elseif ( $ch === '}' || $ch === ']' ) {
                array_pop( $stack );
            }
        }

        // If we're inside a string, close it
        $suffix = '';
        if ( $in_string ) {
            // Truncate back to last complete key-value or array element
            // Find last comma or opening bracket before the unclosed string
            $last_good = strrpos( $json, ',' );
            $last_open_brace = strrpos( $json, '{' );
            $last_open_bracket = strrpos( $json, '[' );
            $cutpoint = max( $last_good ?: 0, $last_open_brace ?: 0, $last_open_bracket ?: 0 );

            if ( $cutpoint > 0 ) {
                $json = substr( $json, 0, $cutpoint + 1 );
                // Recount stack after truncation
                $in_string = false;
                $escape    = false;
                $stack     = [];
                for ( $i = 0, $len = strlen( $json ); $i < $len; $i++ ) {
                    $ch = $json[ $i ];
                    if ( $escape ) { $escape = false; continue; }
                    if ( $ch === '\\' && $in_string ) { $escape = true; continue; }
                    if ( $ch === '"' ) { $in_string = ! $in_string; continue; }
                    if ( $in_string ) continue;
                    if ( $ch === '{' ) $stack[] = '}';
                    elseif ( $ch === '[' ) $stack[] = ']';
                    elseif ( $ch === '}' || $ch === ']' ) array_pop( $stack );
                }
            } else {
                $suffix .= '"';
            }
        }

        // Close all open brackets/braces
        $suffix .= implode( '', array_reverse( $stack ) );

        $repaired = $json . $suffix;
        $test = json_decode( $repaired, true );

        return is_array( $test ) ? $repaired : null;
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
            'system'            => 'Tu es un assistant pédagogique. Tu réponds TOUJOURS en JSON valide uniquement, sans markdown, sans code fence, sans texte explicatif.',
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
        $raw_path     = parse_url( $endpoint, PHP_URL_PATH );
        // SigV4: URI-encode each path segment (e.g. ':' → '%3A') to match what the HTTP client sends.
        $path         = '/' . implode( '/', array_map( 'rawurlencode', array_filter( explode( '/', $raw_path ), 'strlen' ) ) );
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
                "Tu es un expert en pédagogie inclusive. Propose des suggestions d'amélioration pour cette section de cours.\n\n" .
                "Contexte :\n" .
                "- Section ID : {section}\n" .
                "- Numéro de slide : {slide_num}\n" .
                "- Type de cours : {course_type}\n" .
                "- Profils pédagogiques actifs : {active_profiles}\n\n" .
                "Pour chaque suggestion, fournis :\n" .
                "- id (string unique, format: sug_SECTIONID_NNN)\n" .
                "- section (string, le section ID)\n" .
                "- slide_num (int)\n" .
                "- modification_type: \"reformulation\", \"ajout\", \"suppression\" ou \"restructuration\"\n" .
                "- impact_score (int 0-100)\n" .
                "- original (string, texte original — vide pour ajout)\n" .
                "- proposed (string, texte proposé — vide pour suppression)\n" .
                "- rationale (string, justification pédagogique)\n" .
                "- profile_target (string, slug du profil ciblé)\n" .
                "- impact_delta (objet slug→int, delta de score estimé par profil, ex: {\"concentration_tdah\": 12, \"langue_seconde\": 5})\n\n" .
                "Fournis aussi profile_scores: un objet slug→score(0-100) pour chaque profil actif.\n\n" .
                "IMPORTANT: Réponds UNIQUEMENT avec un objet JSON valide, sans markdown, sans code fence, sans texte avant ou après.\n" .
                "Format: {\"suggestions\": [...], \"profile_scores\": {...}}\n\n" .
                "Contenu de la section :\n{content}",

            'student_twin_response' =>
                "Tu es Léa, une tutrice étudiante virtuelle bienveillante et pédagogue. " .
                "Tu t'adresses à l'étudiant(e) {student_name} de façon chaleureuse, en le/la tutoyant. " .
                "Tu guides l'étudiant(e) vers la compréhension sans jamais donner la réponse directement. " .
                "Tu poses des questions de relance pour stimuler la réflexion. " .
                "Tu réponds TOUJOURS en français.\n\n" .
                "Contexte du cours : {course_context}\n" .
                "Message de l'étudiant(e) : {message}\n\n" .
                "Réponds UNIQUEMENT avec un objet JSON valide (sans markdown, sans code fence) contenant :\n" .
                "- \"reply\" (string) : ta réponse à l'étudiant(e), en français, chaleureuse et pédagogique\n" .
                "- \"guardrail_triggered\" (bool) : true si l'étudiant demande de faire son travail à sa place\n" .
                "- \"follow_up_questions\" (array de strings) : 2-3 questions de relance pour approfondir la réflexion",

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
