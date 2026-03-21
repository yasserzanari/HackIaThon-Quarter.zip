<?php
/**
 * Plugin Name: PédagoLens Student Twin
 * Plugin URI:  https://pedagolens.ca
 * Description: Jumeau numérique étudiant — interface de conversation IA avec garde-fous pédagogiques.
 * Version:     1.4.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      PédagoLens
 * Text Domain: pedagolens-student-twin
 */

defined( 'ABSPATH' ) || exit;

define( 'PL_TWIN_VERSION',    '1.4.0' );
define( 'PL_TWIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PL_TWIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader simple
spl_autoload_register( function ( string $class ): void {
    $map = [
        'PedagoLens_Student_Twin'       => PL_TWIN_PLUGIN_DIR . 'includes/class-student-twin.php',
        'PedagoLens_Twin_Admin'         => PL_TWIN_PLUGIN_DIR . 'admin/class-twin-admin.php',
    ];
    if ( isset( $map[ $class ] ) && file_exists( $map[ $class ] ) ) {
        require_once $map[ $class ];
    }
} );

add_action( 'plugins_loaded', function (): void {
    if ( ! class_exists( 'PedagoLens_Core' ) ) {
        add_action( 'admin_notices', function (): void {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'PédagoLens Student Twin requiert le plugin PédagoLens Core.', 'pedagolens-student-twin' )
                . '</p></div>';
        } );
        return;
    }

    PedagoLens_Student_Twin::init();
} );
