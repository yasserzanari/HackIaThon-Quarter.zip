<?php
/**
 * Plugin Name: PédagoLens API Bridge
 * Plugin URI:  https://pedagolens.local
 * Description: Couche IA Bedrock pour PédagoLens — gestion des appels AWS Bedrock (Claude), prompt templates, validation JSON, mode mock.
 * Version:     1.3.1
 * Author:      PédagoLens Team
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: pedagolens-api-bridge
 */

defined( 'ABSPATH' ) || exit;

define( 'PL_BRIDGE_VERSION',    '1.3.1' );
define( 'PL_BRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PL_BRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload des classes du plugin
spl_autoload_register( function ( string $class ): void {
    $map = [
        'PedagoLens_API_Bridge'          => 'includes/class-api-bridge.php',
        'PedagoLens_API_Bridge_Settings' => 'includes/class-api-bridge-settings.php',
        'PedagoLens_API_Bridge_Mock'     => 'includes/class-api-bridge-mock.php',
    ];
    if ( isset( $map[ $class ] ) ) {
        require_once PL_BRIDGE_PLUGIN_DIR . $map[ $class ];
    }
} );

add_action( 'plugins_loaded', [ 'PedagoLens_API_Bridge', 'init' ] );
