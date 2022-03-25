<?php

/**
 * Fired during plugin activation
 *
 * @link       https://twitter.com/HughMan98241429
 * @since      1.0.0
 *
 * @package    Smwf
 * @subpackage Smwf/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Smwf
 * @subpackage Smwf/includes
 * @author     Interactive Map Project <hugh.mann.bee.ing@gmail.com>
 */
class Smwf_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
           wp_register_style('smwf', plugins_url('style.css',__FILE__ ));
	   wp_enqueue_style('smwf');
	}

}
