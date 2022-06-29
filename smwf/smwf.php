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

add_action('admin_menu', 'test_plugin_setup_menu');

function test_plugin_setup_menu(){
            add_menu_page( 'Test Plugin Page', 'Social Media With Filter Settings', 'manage_options', 'test-plugin', 'test_init' );
}
function smwf_plugin_section_text() {
            echo '<p>Here you can set all the options for using the API</p>';
}

function smwf_setting_media_sources() {
            $options = get_option( 'smwf_options' );
            echo "<textarea id='smwf_source_media' name='smwf_options[media_sources]' rows=7 cols=100 type='textarea'>{$options['media_sources']}</textarea>";
//          echo "<input style='width:80%; min-height:400px;' type='text' id='smwf_source_media' name='smwf_options[media_sources]' value='" . esc_attr( $options['media_sources'] ) . "' />";
}

function smwf_register_settings() {
    register_setting( 'smwf_options', 'smwf_options', 'smwf_options_validate' );
    add_settings_section( 'api_settings', 'API Settings', 'smwf_plugin_section_text', 'smwf_plugin' );

    add_settings_field( 'smwf_setting_media_sources', 'Social media sources (new line for each source)', 'smwf_setting_media_sources', 'smwf_plugin', 'api_settings' );
}
add_action( 'admin_init', 'smwf_register_settings' );

function test_init(){
    ?>
    <h2>Example Plugin Settings</h2>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'smwf_options' );
        do_settings_sections( 'smwf_plugin' ); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
    </form>
    <?php
/*<?php esc_attr_e( 'Save' ); ?>*/

}

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

wp_register_style('your_namespace', plugins_url('style.css',__FILE__ ));
wp_enqueue_style('your_namespace');

function twitter_filter_content( $content )
{
    global $post ;
    if ( is_singular() && in_the_loop() && is_main_query() && $post->post_type == "post" )
    {
         $postid = $post->ID ;
         $smwf_tags = get_the_tags($postid);
         $options = get_option( 'smwf_options' );
         $media_sources = preg_split("/\r\n|\n|\r/", $options['media_sources']);
         foreach($media_sources as $key => $source){
                 $split_text = preg_split("/,/", $source);
                 $source_ob = new StdClass();
                 $source_ob->social = $split_text[0];
                 $source_ob->url = $split_text[1];
                 $source_ob->tag = $split_text[2];
                 $source_ob->id = $split_text[3];
                 $media_sources[$key] = clone $source_ob;
         }
         // tag filtering stuff goes here
         foreach ($smwf_tags as &$lc_tag) {
                 echo $lc_tag->name;
                 echo "----------------";
         }
         foreach ($media_sources as &$md_src) {
                 echo $md_src;
                 echo "----------------";
         }
         // get list of renderables
         $s1 = new StdClass() ;
         $s1->title = "test post 1" ;
         $s1->content = "somethingsomething that or other" ;
         $s1->link = "https://www.google.com" ;
         $s1->image = "https://asia.olympus-imaging.com/content/000107507.jpg" ;
         $s2 = new StdClass() ;
         $s2->title = "test post 2" ;
         $s2->content = "somethingsomething that or other" ;

         $sample = array($s1,$s2);

         //render
         $renderable = "<style>" . file_get_contents( __DIR__ . "/styles.css") . "</style><div class='smwf-postlist'>";
         for ($postn = 0; $postn < count($sample) ; $postn++ ){
                $renderable .= "<div class='smwf-post'><a href='" .  $sample[$postn]->link . "'><h3>" . $sample[$postn]->title  . "</h3><br/><div class='smwf-content'>"  . $sample[$postn]->content  . "</div>" ;
                if (! empty($sample[$postn]->image)){
                        $renderable .= "<img src='" . $sample[$postn]->image  . "'>";
                }
                $renderable .= "</a></div>";
         }
         $renderable .= "</div>";
         return $content . $renderable;
    }
    else {
        return $content;
    }
}

add_filter( 'the_content', 'twitter_filter_content');

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
