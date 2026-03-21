<?php
/**
 * Plugin Name: PédagoLens Core
 * Plugin URI:  https://pedagolens.local
 * Description: Noyau partagé PédagoLens — CPT, rôles, helpers, constantes, Profile_Manager.
 * Version:     1.0.2
 * Author:      PédagoLens Team
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: pedagolens-core
 */

defined( 'ABSPATH' ) || exit;

define( 'PEDAGOLENS_VERSION',    '1.0.2' );
define( 'PEDAGOLENS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PEDAGOLENS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( string $class ): void {
    $map = [
        'PedagoLens_Core'            => 'includes/class-core.php',
        'PedagoLens_Profile_Manager' => 'includes/class-profile-manager.php',
        'PedagoLens_Admin_Profiles'  => 'admin/class-admin-profiles.php',
        'PedagoLens_Core_Settings'   => 'admin/class-core-settings.php',
    ];
    if ( isset( $map[ $class ] ) ) {
        require_once PEDAGOLENS_PLUGIN_DIR . $map[ $class ];
    }
} );

register_activation_hook( __FILE__, [ 'PedagoLens_Core', 'activate' ] );

add_action( 'plugins_loaded', [ 'PedagoLens_Core', 'init' ] );
