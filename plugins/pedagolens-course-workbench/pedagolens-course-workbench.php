<?php
/**
 * Plugin Name: PédagoLens Course Workbench
 * Plugin URI:  https://pedagolens.local
 * Description: Atelier de cours — suggestions IA par section, apply/reject, versionnage, delta d'impact par profil.
 * Version:     4.5.1
 * Author:      PédagoLens Team
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: pedagolens-course-workbench
 */

defined( 'ABSPATH' ) || exit;

define( 'PL_WORKBENCH_VERSION',    '4.5.1' );
define( 'PL_WORKBENCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PL_WORKBENCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( function ( string $class ): void {
    $map = [
        'PedagoLens_Course_Workbench'       => 'includes/class-course-workbench.php',
        'PedagoLens_Workbench_Admin'        => 'admin/class-workbench-admin.php',
    ];
    if ( isset( $map[ $class ] ) ) {
        require_once PL_WORKBENCH_PLUGIN_DIR . $map[ $class ];
    }
} );

add_action( 'plugins_loaded', [ 'PedagoLens_Course_Workbench', 'init' ] );
