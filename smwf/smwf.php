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
// SETTINGS TEXTBOX
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
include plugin_dir_path( __FILE__ ) . 'twitter_youtube_filtering.php';
wp_register_style('your_namespace', plugins_url('style.css',__FILE__ ));
wp_enqueue_style('your_namespace');

function twitter_filter_content( $content )
{
    global $post ;
    if ( is_singular() && in_the_loop() && is_main_query() && $post->post_type == "post" )
    {    // load and parse variables
         $postid = $post->ID ;
         $smwf_tags = get_the_tags($postid);
         $options = get_option( 'smwf_options' );
         global $media_sources;
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
         // TAG FILTERING
         $facebook_link = "";
         $instagram_link = "";
         foreach ($smwf_tags as $lc_tag) {
                 foreach($media_sources as $ms){
                         if (strcasecmp($lc_tag->name , $ms->tag) == 0 && strcasecmp( $ms->social , 'facebook') == 0){
                                 $facebook_link .= "<p><a href='" . $ms->url . "' class='fblink'>" . ucwords(str_replace("_", " ", $ms->tag)) . " Facebook</a></p><br>";
                         }
                         if (strcasecmp($lc_tag->name , $ms->tag) == 0 && strcasecmp( $ms->social , 'instagram') == 0){
                                 $instagram_link .= "<p><a href='" . $ms->url . "' class='instagram'>" . ucwords(str_replace("_", " ", $ms->tag)) . " Instagram</a></p>";
                         }
                 }
                 //DEBUG return saved tweets
                 //global ${$lc_tag}_twitter;
                 //if isset(${$lc_tag}_twitter){
                //       echo ${$lc_tag}_twitter;
                // }
                // else{
        //               echo ${$lc_tag}_twitter;
        //       }
         }
         
         $tweets = array_values(filter_tweets());
         $ytube = array_values(filter_videos());


         //render
         $renderable = "<style>" . file_get_contents( __DIR__ . "/styles.css") . "</style><div class='smlinks'><div class='fbblock' >" . $facebook_link . "</div> <div class='instablock'> " . $instagram_link . "</div></div><div class='smwf-postlist'><div class='twitterblock'>";
         for ($postn = 0; $postn < min(count($tweets),6); $postn++ ){
                  // V1 CUSTOM TWEET DISPLAY
                 //      $renderable .= "<div class='smwf-post'><a href='https://twitter.com/twitter/status/" .  $tweets[$postn]->id . "'><h3>" . $tweets[$postn]->title  . "</h3><br/><div class='smwf-content'>"  . $tweets[$postn]->text  . "</div>" ;
                 //      $renderable .= "</a></div>";
               // TWITTER DEFAULT TWEET RENDERING
               $renderable .="<div class='twittercon' id=\"container" . $postn . "\"></div><script>twttr.widgets.createTweet(
  '" . $tweets[$postn]->id . "',
  document.getElementById('container" . $postn . "'),
  {

  }
    );
         </script>";
         }
         if (!empty(get_id_from_tags('twitter'))){
                 $renderable .= "</div><a style='background-color:lightblue;padding:5px;margin-bottom:15px' href='https://twitter.com/intent/user?user_id=" . get_id_from_tags('twitter') . "'>More Tweets</a>";
         }
         if(!empty($ytube)){
                 $renderable .="<p>To navigate between videos in this playlist, use the 'Next Video' and 'Previous Video' buttons, or click the Youtube button to watch on Youtube.com.</p>
                                <div id=\"player\"></div>";
         }

         // RENDER YOUTUBE VIDEOS
         $renderable .="</div>";
//       $renderable .= "<ul class=\"list-unstyled video-list-thumbs row\">";
         $vodid = array();
         foreach ( $ytube as $video ) {

        # $renderable .= "<li class=\"col-lg-3 col-sm-4 col-xs-6\"><a href=\"http://www.youtube.com/watch?v=" . $video->getId()->getVideoId() . "])\" title=\" " .  $video['snippet']['title']  . " \" target=\"_blank\"><img src=\" " . $video['snippet']['thumbnails']['medium']['url'] . " \" alt=\" " .  $video['snippet']['title'] . " \" class=\"img-responsive\" height=\"130px\" /><h2 class=\"truncate\"> " . $video['snippet']['title'] . " </h2><span class=\"glyphicon glyphicon-play-circle\"></span></a></li>";
         array_push($vodid,$video->getId()->getVideoId());
         }
            
         $vodid=json_encode($vodid);
         $vodid=str_replace('"','',$vodid);
         $vodid=str_replace('[','"',$vodid);
         $vodid=str_replace(']','"',$vodid);
         //echo $ytube;
//       $renderable .="</ul>";
         $renderable .= "<script>
      var tag = document.createElement('script');
      tag.src = \"https://www.youtube.com/iframe_api\";
      var firstScriptTag = document.getElementsByTagName('script')[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
      var player;
      function onYouTubeIframeAPIReady() {
        player = new YT.Player('player', {
          height: '390',
          width: '640',
          videoId: 'dwM78wnqYK0',
          playerVars: {
          'playsinline': 1,
                  'controls': 1,
                  'playlist': $vodid
          },
          events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange
          }
        });
      }

      function onPlayerReady(event) {
        //player.queVideoByID('9Nr6lohmbhY');
        event.target.playVideo();
      }

      var done = false;
      function onPlayerStateChange(event) {
        if (event.data == YT.PlayerState.PLAYING && !done) {
          setTimeout(stopVideo, 6000);
          done = true;
        }
      }
      function stopVideo() {
        player.stopVideo();
      }

    </script>";

         return $content . $renderable;
    }
    else {
        return $content;
    }
}
// CRON UPDATE TWEETS (called each hour)
add_action( 'update_media_action', 'update_social_medias', 10, 0 );
# cron job for API calls - first check whether it is already scheduled, and if not, schedule the cron twicedaily.
if ( ! wp_next_scheduled( 'update_media_action' ) ) {
 wp_schedule_event( time(), 'hourly', 'update_media_action' );
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
