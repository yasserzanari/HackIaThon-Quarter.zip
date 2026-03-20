<?php
/**
 * Plugin Name: PédagoLens Landing
 * Plugin URI:  https://pedagolens.ca
 * Description: Landing page marketing de PédagoLens — shortcodes configurables depuis l'admin.
 * Version:     1.6.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      PédagoLens
 * Text Domain: pedagolens-landing
 */

defined( 'ABSPATH' ) || exit;

define( 'PL_LANDING_VERSION',    '1.6.0' );
define( 'PL_LANDING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PL_LANDING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( string $class ): void {
    $map = [
        'PedagoLens_Landing'       => PL_LANDING_PLUGIN_DIR . 'includes/class-landing.php',
        'PedagoLens_Landing_Admin' => PL_LANDING_PLUGIN_DIR . 'admin/class-landing-admin.php',
    ];
    if ( isset( $map[ $class ] ) && file_exists( $map[ $class ] ) ) {
        require_once $map[ $class ];
    }
} );

add_action( 'plugins_loaded', function (): void {
    PedagoLens_Landing::init();
} );
