<?php

defined( 'ABSPATH' ) || exit;

class PedagoLens_Git_Preconfig {

    private const PAGE_SLUG = 'pl-git-preconfig';
    private const OPT_KEY   = 'pl_git_preconfig_settings';

    private const ACT_SAVE  = 'pl_git_preconfig_save';
    private const ACT_SYNC  = 'pl_git_preconfig_sync';
    private const ACT_SETUP = 'pl_git_preconfig_setup';

    public static function init(): void {
        add_action( 'admin_menu', [ self::class, 'add_menu' ] );
        add_action( 'admin_post_' . self::ACT_SAVE, [ self::class, 'handle_save' ] );
        add_action( 'admin_post_' . self::ACT_SYNC, [ self::class, 'handle_sync' ] );
        add_action( 'admin_post_' . self::ACT_SETUP, [ self::class, 'handle_setup' ] );
    }

    public static function add_menu(): void {
        add_submenu_page(
            'tools.php',
            __( 'PedagoLens Git Preconfig', 'pedagolens-git-preconfig' ),
            __( 'PedagoLens Git', 'pedagolens-git-preconfig' ),
            'manage_options',
            self::PAGE_SLUG,
            [ self::class, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acces refuse.', 'pedagolens-git-preconfig' ) );
        }

        $s = self::settings();
        $report = get_transient( 'pl_git_preconfig_report' );
        if ( $report ) {
            delete_transient( 'pl_git_preconfig_report' );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'PedagoLens Git Preconfig', 'pedagolens-git-preconfig' ); ?></h1>
            <p><?php esc_html_e( 'Configure Git deployment and run one-click setup for PedagoLens.', 'pedagolens-git-preconfig' ); ?></p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( self::ACT_SAVE, '_pl_nonce' ); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACT_SAVE ); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="repo_url">Repo URL</label></th>
                        <td><input type="url" class="regular-text" id="repo_url" name="repo_url" value="<?php echo esc_attr( $s['repo_url'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="branch">Branch</label></th>
                        <td><input type="text" class="regular-text" id="branch" name="branch" value="<?php echo esc_attr( $s['branch'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deploy_path">Deploy path</label></th>
                        <td><input type="text" class="regular-text" id="deploy_path" name="deploy_path" value="<?php echo esc_attr( $s['deploy_path'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="git_binary">Git binary path</label></th>
                        <td>
                            <input type="text" class="regular-text" id="git_binary" name="git_binary" value="<?php echo esc_attr( $s['git_binary'] ); ?>" placeholder="/usr/bin/git">
                            <p class="description">Example: <code>/usr/bin/git</code>. Leave default for auto-detection.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="n8n_webhook_url">n8n webhook</label></th>
                        <td><input type="url" class="regular-text" id="n8n_webhook_url" name="n8n_webhook_url" value="<?php echo esc_attr( $s['n8n_webhook_url'] ); ?>"></td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Settings', 'pedagolens-git-preconfig' ), 'secondary' ); ?>
            </form>

            <hr>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:12px;">
                <?php wp_nonce_field( self::ACT_SYNC, '_pl_nonce' ); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACT_SYNC ); ?>">
                <?php submit_button( __( 'Run Git Sync', 'pedagolens-git-preconfig' ), 'primary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('Apply full PedagoLens setup now?');">
                <?php wp_nonce_field( self::ACT_SETUP, '_pl_nonce' ); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACT_SETUP ); ?>">
                <?php submit_button( __( 'Run Full Setup', 'pedagolens-git-preconfig' ), 'primary', 'submit', false ); ?>
            </form>

            <?php if ( is_array( $report ) ) : ?>
                <h2><?php esc_html_e( 'Last Report', 'pedagolens-git-preconfig' ); ?></h2>
                <pre><?php echo esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_save(): void {
        self::assert_admin();
        check_admin_referer( self::ACT_SAVE, '_pl_nonce' );

        $s = self::settings();
        $s['repo_url'] = esc_url_raw( $_POST['repo_url'] ?? $s['repo_url'] );
        $s['branch'] = sanitize_text_field( $_POST['branch'] ?? $s['branch'] );
        $s['deploy_path'] = sanitize_text_field( $_POST['deploy_path'] ?? $s['deploy_path'] );
        $s['git_binary'] = sanitize_text_field( $_POST['git_binary'] ?? $s['git_binary'] );
        $s['n8n_webhook_url'] = esc_url_raw( $_POST['n8n_webhook_url'] ?? $s['n8n_webhook_url'] );

        update_option( self::OPT_KEY, wp_json_encode( $s ) );
        self::redirect();
    }

    public static function handle_sync(): void {
        self::assert_admin();
        check_admin_referer( self::ACT_SYNC, '_pl_nonce' );

        $s = self::settings();
        $report = [
            'action' => 'git_sync',
            'started_at' => gmdate( 'c' ),
            'commands' => [],
            'success' => true,
        ];

        $sync_root = self::canonical_sync_path();
        if ( rtrim( $s['deploy_path'], '/\\' ) !== rtrim( $sync_root, '/\\' ) ) {
            $report['notice'] = 'Deploy path overridden to WordPress content root for reliable plugin updates.';
            $report['configured_deploy_path'] = $s['deploy_path'];
        }
        $report['sync_root'] = $sync_root;

        $git = self::find_git_binary( $s['git_binary'] );
        if ( $git === '' ) {
            $report['success'] = false;
            $report['error'] = 'Git binary not found in container.';
            $report['hint'] = [
                'docker exec -u root -it <wordpress_container> sh -lc "apt-get update && apt-get install -y git"',
                'Then set Git binary path to /usr/bin/git in this plugin settings.',
            ];
            self::save_report_and_redirect( $report );
        }

        $resolved = self::resolve_repo_path( $sync_root );
        if ( ! $resolved['ok'] ) {
            $bootstrap = self::bootstrap_repo_in_path( $git, $sync_root, $s['repo_url'], $s['branch'] );
            $report['bootstrap'] = $bootstrap;

            if ( ! $bootstrap['ok'] ) {
                $report['success'] = false;
                $report['error'] = 'Git repository not found from sync root: ' . $sync_root;
                $report['hint'] = [
                    'The plugin now syncs WordPress content root only.',
                    'Ensure this folder is writable: ' . $sync_root,
                ];
                $report['diagnostic'] = $resolved;
                self::save_report_and_redirect( $report );
            }

            $resolved = self::resolve_repo_path( $sync_root );
            if ( ! $resolved['ok'] ) {
                $report['success'] = false;
                $report['error'] = 'Repository bootstrap completed but repo path is still unresolved.';
                $report['diagnostic'] = $resolved;
                self::save_report_and_redirect( $report );
            }
        }

        $deploy_path = $resolved['path'];
        $report['resolved_deploy_path'] = $deploy_path;

        $cmds = [
            self::git_cmd( $git, $deploy_path, [ 'rev-parse', '--is-inside-work-tree' ] ),
            self::git_cmd( $git, $deploy_path, [ 'remote', 'set-url', 'origin', $s['repo_url'] ] ),
            self::git_cmd( $git, $deploy_path, [ 'fetch', '--all', '--prune' ] ),
            self::git_cmd( $git, $deploy_path, [ 'checkout', $s['branch'] ] ),
            self::git_cmd( $git, $deploy_path, [ 'pull', 'origin', $s['branch'] ] ),
        ];

        foreach ( $cmds as $cmd ) {
            $r = self::run_cmd( $cmd );
            $report['commands'][] = $r;
            if ( $r['exit_code'] !== 0 ) {
                $report['success'] = false;
                break;
            }
        }

        if ( $report['success'] ) {
            $deploy_report = self::deploy_repo_plugins_to_wp( $deploy_path );
            $report['deploy_plugins'] = $deploy_report;
            if ( empty( $deploy_report['ok'] ) ) {
                $report['success'] = false;
            }
        }

        $report['finished_at'] = gmdate( 'c' );
        self::save_report_and_redirect( $report );
    }

    public static function handle_setup(): void {
        self::assert_admin();
        check_admin_referer( self::ACT_SETUP, '_pl_nonce' );

        $s = self::settings();
        $report = [
            'action' => 'full_setup',
            'started_at' => gmdate( 'c' ),
            'steps' => [],
            'success' => true,
        ];

        $report['steps'][] = [ 'activate_plugins' => self::activate_required_plugins() ];

        update_option( 'pl_ai_mode', 'n8n' );
        update_option( 'pl_n8n_webhook_url', $s['n8n_webhook_url'] );
        update_option( 'pl_n8n_timeout', 30 );
        $report['steps'][] = [ 'n8n_mode' => 'configured' ];

        $created_pages = self::ensure_pages();
        self::apply_wp_settings( $created_pages );
        $report['steps'][] = [ 'pages' => $created_pages ];

        $theme_used = self::ensure_blank_theme();
        $report['steps'][] = [ 'theme' => $theme_used ];

        $report['finished_at'] = gmdate( 'c' );
        self::save_report_and_redirect( $report );
    }

    private static function settings(): array {
        $raw = get_option( self::OPT_KEY, [] );
        if ( is_string( $raw ) ) {
            $raw = json_decode( $raw, true ) ?? [];
        }

        $default_path = self::canonical_sync_path();

        return wp_parse_args( $raw, [
            'repo_url' => 'https://github.com/yasserzanari/HackIaThon-Quarter.zip.git',
            'branch' => 'main',
            'deploy_path' => $default_path,
            'git_binary' => '/usr/bin/git',
            'n8n_webhook_url' => home_url( '/webhook/pedagolens-ai' ),
        ] );
    }

    private static function canonical_sync_path(): string {
        if ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) {
            return rtrim( WP_CONTENT_DIR, '/\\' );
        }

        return rtrim( ABSPATH, '/\\' );
    }

    private static function find_git_binary( string $preferred ): string {
        $preferred = trim( $preferred );
        if ( $preferred !== '' && is_file( $preferred ) && is_executable( $preferred ) ) {
            return $preferred;
        }

        foreach ( [ '/usr/bin/git', '/bin/git', 'git' ] as $candidate ) {
            if ( $candidate === 'git' ) {
                $path = @shell_exec( 'command -v git 2>/dev/null' );
                $path = is_string( $path ) ? trim( $path ) : '';
                if ( $path !== '' && is_executable( $path ) ) {
                    return $path;
                }
                continue;
            }
            if ( is_file( $candidate ) && is_executable( $candidate ) ) {
                return $candidate;
            }
        }

        return '';
    }

    private static function resolve_repo_path( string $configured_path ): array {
        $configured_path = trim( $configured_path );
        $candidates = [];

        if ( $configured_path !== '' ) {
            $candidates[] = $configured_path;
        }

        foreach ( [ self::canonical_sync_path(), '/var/www/html/wp-content', '/opt/pedagolens', ABSPATH, '/var/www/html' ] as $candidate ) {
            if ( ! in_array( $candidate, $candidates, true ) ) {
                $candidates[] = $candidate;
            }
        }

        foreach ( $candidates as $path ) {
            if ( self::is_git_repo_path( $path ) ) {
                return [
                    'ok' => true,
                    'path' => $path,
                    'candidates' => $candidates,
                ];
            }
        }

        return [
            'ok' => false,
            'path' => '',
            'candidates' => $candidates,
        ];
    }

    private static function is_git_repo_path( string $path ): bool {
        $path = rtrim( $path, '/\\' );
        if ( $path === '' || ! is_dir( $path ) ) {
            return false;
        }

        return file_exists( $path . '/.git' );
    }

    private static function deploy_repo_plugins_to_wp( string $repo_root ): array {
        $result = [
            'ok' => true,
            'repo_plugins_path' => rtrim( $repo_root, '/\\' ) . '/plugins',
            'wp_plugins_path' => WP_PLUGIN_DIR,
            'copied' => [],
            'errors' => [],
        ];

        $repo_plugins = $result['repo_plugins_path'];
        if ( ! is_dir( $repo_plugins ) ) {
            $result['ok'] = false;
            $result['errors'][] = 'Repo plugins folder not found: ' . $repo_plugins;
            return $result;
        }

        if ( ! is_dir( WP_PLUGIN_DIR ) ) {
            $result['ok'] = false;
            $result['errors'][] = 'WP plugin directory not found: ' . WP_PLUGIN_DIR;
            return $result;
        }

        $repo_plugins_real = realpath( $repo_plugins );
        $wp_plugins_real = realpath( WP_PLUGIN_DIR );
        if ( $repo_plugins_real && $wp_plugins_real && $repo_plugins_real === $wp_plugins_real ) {
            $result['note'] = 'Repo plugins path already matches active WP plugin directory.';
            return $result;
        }

        $entries = @scandir( $repo_plugins );
        if ( ! is_array( $entries ) ) {
            $result['ok'] = false;
            $result['errors'][] = 'Unable to scan repo plugins folder.';
            return $result;
        }

        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }

            $source = $repo_plugins . '/' . $entry;
            if ( ! is_dir( $source ) || strpos( $entry, 'pedagolens-' ) !== 0 ) {
                continue;
            }

            $target = rtrim( WP_PLUGIN_DIR, '/\\' ) . '/' . $entry;
            $ok = self::copy_directory_recursive( $source, $target );
            if ( $ok ) {
                $result['copied'][] = $entry;
            } else {
                $result['ok'] = false;
                $result['errors'][] = 'Failed to copy plugin: ' . $entry;
            }
        }

        if ( empty( $result['copied'] ) ) {
            $result['ok'] = false;
            $result['errors'][] = 'No pedagolens-* plugin folder copied from repo.';
        }

        return $result;
    }

    private static function copy_directory_recursive( string $source, string $target ): bool {
        if ( ! is_dir( $source ) ) {
            return false;
        }

        if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
            return false;
        }

        $entries = @scandir( $source );
        if ( ! is_array( $entries ) ) {
            return false;
        }

        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }

            $src = $source . '/' . $entry;
            $dst = $target . '/' . $entry;

            if ( is_dir( $src ) ) {
                if ( ! self::copy_directory_recursive( $src, $dst ) ) {
                    return false;
                }
                continue;
            }

            if ( realpath( $src ) === realpath( $dst ) ) {
                continue;
            }

            if ( ! @copy( $src, $dst ) ) {
                return false;
            }
        }

        return true;
    }

    private static function bootstrap_repo_in_path( string $git, string $deploy_path, string $repo_url, string $branch ): array {
        $out = [
            'ok' => false,
            'path' => $deploy_path,
            'commands' => [],
        ];

        $deploy_path = trim( $deploy_path );
        if ( $deploy_path === '' || ! is_dir( $deploy_path ) ) {
            $out['error'] = 'Deploy path does not exist.';
            return $out;
        }

        if ( self::is_git_repo_path( $deploy_path ) ) {
            $out['ok'] = true;
            $out['note'] = 'Already a git repository.';
            return $out;
        }

        $cmds = [
            self::git_cmd( $git, $deploy_path, [ 'init' ] ),
            self::git_cmd( $git, $deploy_path, [ 'remote', 'remove', 'origin' ] ),
            self::git_cmd( $git, $deploy_path, [ 'remote', 'add', 'origin', $repo_url ] ),
            self::git_cmd( $git, $deploy_path, [ 'fetch', 'origin', $branch, '--depth=1' ] ),
            self::git_cmd( $git, $deploy_path, [ 'checkout', '-B', $branch, '--track', 'origin/' . $branch ] ),
        ];

        foreach ( $cmds as $idx => $cmd ) {
            $r = self::run_cmd( $cmd );
            $out['commands'][] = $r;

            // remote remove origin is best-effort.
            if ( $idx === 1 ) {
                continue;
            }

            if ( $r['exit_code'] !== 0 ) {
                $out['error'] = 'Bootstrap command failed.';
                return $out;
            }
        }

        $out['ok'] = self::is_git_repo_path( $deploy_path );
        if ( ! $out['ok'] ) {
            $out['error'] = 'Bootstrap commands ran but .git was not found.';
        }

        return $out;
    }

    private static function assert_admin(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acces refuse.', 'pedagolens-git-preconfig' ) );
        }
    }

    private static function redirect(): void {
        wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
        exit;
    }

    private static function save_report_and_redirect( array $report ): void {
        set_transient( 'pl_git_preconfig_report', $report, MINUTE_IN_SECONDS * 15 );
        self::redirect();
    }

    private static function run_cmd( array $parts ): array {
        $command = implode( ' ', array_map( 'escapeshellarg', $parts ) );
        $descriptors = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ],
        ];

        $proc = @proc_open( $command, $descriptors, $pipes );
        if ( ! is_resource( $proc ) ) {
            return [
                'command' => $command,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'proc_open failed',
            ];
        }

        fclose( $pipes[0] );
        $stdout = stream_get_contents( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $code = proc_close( $proc );

        return [
            'command' => $command,
            'exit_code' => (int) $code,
            'stdout' => trim( (string) $stdout ),
            'stderr' => trim( (string) $stderr ),
        ];
    }

    private static function git_cmd( string $git, string $repo_path, array $args ): array {
        $repo_path = rtrim( $repo_path, '/\\' );
        if ( $repo_path === '' ) {
            $repo_path = '.';
        }

        // Avoid "detected dubious ownership" in shared Docker volumes.
        return array_merge( [ $git, '-c', 'safe.directory=' . $repo_path, '-C', $repo_path ], $args );
    }

    private static function activate_required_plugins(): array {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $required = [
            'pedagolens-core/pedagolens-core.php',
            'pedagolens-api-bridge/pedagolens-api-bridge.php',
            'pedagolens-landing/pedagolens-landing.php',
            'pedagolens-teacher-dashboard/pedagolens-teacher-dashboard.php',
            'pedagolens-course-workbench/pedagolens-course-workbench.php',
            'pedagolens-student-twin/pedagolens-student-twin.php',
            'pedagolens-migration/pedagolens-migration.php',
        ];

        $out = [];
        foreach ( $required as $plugin_file ) {
            if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) && ! is_plugin_active( $plugin_file ) ) {
                $res = activate_plugin( $plugin_file );
                $out[ $plugin_file ] = is_wp_error( $res ) ? $res->get_error_message() : 'activated';
            } else {
                $out[ $plugin_file ] = is_plugin_active( $plugin_file ) ? 'already_active' : 'missing';
            }
        }

        return $out;
    }

    private static function ensure_pages(): array {
        $pages = [
            [ 'title' => 'Accueil', 'slug' => 'accueil', 'shortcode' => '[pedagolens_landing]' ],
            [ 'title' => 'Connexion', 'slug' => 'connexion', 'shortcode' => '[pedagolens_login]' ],
            [ 'title' => 'Dashboard Enseignant', 'slug' => 'dashboard-enseignant', 'shortcode' => '[pedagolens_teacher_dashboard]' ],
            [ 'title' => 'Dashboard Etudiant', 'slug' => 'dashboard-etudiant', 'shortcode' => '[pedagolens_student_dashboard]' ],
            [ 'title' => 'Cours Projets', 'slug' => 'cours-projets', 'shortcode' => '[pedagolens_courses]' ],
            [ 'title' => 'Atelier Pedagogique', 'slug' => 'workbench', 'shortcode' => '[pedagolens_workbench]' ],
            [ 'title' => 'Mon Compte', 'slug' => 'compte', 'shortcode' => '[pedagolens_account]' ],
            [ 'title' => 'Historique', 'slug' => 'historique', 'shortcode' => '[pedagolens_history]' ],
            [ 'title' => 'Parametres', 'slug' => 'parametres', 'shortcode' => '[pedagolens_settings]' ],
            [ 'title' => 'Vue Institutionnelle', 'slug' => 'institutionnel', 'shortcode' => '[pedagolens_institutional]' ],
            [ 'title' => 'Jumeau IA', 'slug' => 'jumeau-ia', 'shortcode' => '[pedagolens_jumeau_ia]' ],
        ];

        $created = [];
        foreach ( $pages as $p ) {
            $existing = get_page_by_path( $p['slug'] );
            if ( $existing ) {
                wp_update_post( [
                    'ID' => $existing->ID,
                    'post_title' => $p['title'],
                    'post_content' => $p['shortcode'],
                    'post_status' => 'publish',
                ] );
                $created[ $p['slug'] ] = (int) $existing->ID;
                continue;
            }

            $id = wp_insert_post( [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $p['title'],
                'post_name' => $p['slug'],
                'post_content' => $p['shortcode'],
            ] );
            if ( ! is_wp_error( $id ) ) {
                $created[ $p['slug'] ] = (int) $id;
            }
        }

        return $created;
    }

    private static function apply_wp_settings( array $pages ): void {
        if ( isset( $pages['accueil'] ) ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', (int) $pages['accueil'] );
        }

        global $wp_rewrite;
        if ( is_object( $wp_rewrite ) ) {
            $wp_rewrite->set_permalink_structure( '/%postname%/' );
        }
        flush_rewrite_rules( false );
    }

    private static function ensure_blank_theme(): string {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $candidates = [ 'blank-canvas', 'blank', 'twentytwentyfour' ];

        foreach ( $candidates as $slug ) {
            $theme = wp_get_theme( $slug );
            if ( $theme->exists() ) {
                switch_theme( $slug );
                return $slug;
            }
        }

        foreach ( $candidates as $slug ) {
            $zip = "https://downloads.wordpress.org/theme/{$slug}.latest-stable.zip";
            $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
            $result = $upgrader->install( $zip );
            if ( $result === true ) {
                $theme = wp_get_theme( $slug );
                if ( $theme->exists() ) {
                    switch_theme( $slug );
                    return $slug;
                }
            }
        }

        $current = wp_get_theme();
        return $current->get_stylesheet();
    }
}
