<?php
/**
 * Plugin Name: PédagoLens Teacher Dashboard
 * Plugin URI:  https://pedagolens.local
 * Description: Tableau de bord enseignant — analyse de cours, scores par profil, recommandations, gestion des projets.
 * Version:     1.2.0
 * Author:      PédagoLens Team
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: pedagolens-teacher-dashboard
 */

defined( 'ABSPATH' ) || exit;

define( 'PL_DASHBOARD_VERSION',    '1.2.0' );
define( 'PL_DASHBOARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PL_DASHBOARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( string $class ): void {
    $map = [
        'PedagoLens_Teacher_Dashboard'       => 'includes/class-teacher-dashboard.php',
        'PedagoLens_Dashboard_Admin'         => 'admin/class-dashboard-admin.php',
    ];
    if ( isset( $map[ $class ] ) ) {
        require_once PL_DASHBOARD_PLUGIN_DIR . $map[ $class ];
    }
} );

add_action( 'plugins_loaded', [ 'PedagoLens_Teacher_Dashboard', 'init' ] );
