<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://twitter.com/HughMan98241429
 * @since             1.0.0
 * @package           Smwf
 *
 * @wordpress-plugin
 * Plugin Name:       Social-media-with-filter
 * Plugin URI:        https://github.com/Jo-stfc/Public-engagement-app
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Interactive Map Project
 * Author URI:        https://twitter.com/HughMan98241429
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       smwf
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SMWF_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-smwf-activator.php
 */
function activate_smwf() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-smwf-activator.php';
	Smwf_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-smwf-deactivator.php
 */
function deactivate_smwf() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-smwf-deactivator.php';
	Smwf_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_smwf' );
register_deactivation_hook( __FILE__, 'deactivate_smwf' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-smwf.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_smwf() {

	$plugin = new Smwf();
	$plugin->run();

}
run_smwf();
