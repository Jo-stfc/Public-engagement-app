<?php

//run composer require abraham/twitteroauth and composer require google/apiclient:^2.0
require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
}

use Abraham\TwitterOAuth\TwitterOAuth;

function get_cached_var($var_name) {
	$value = wp_cache_get($var_name);
	
	if($value !== false) {
		return $value;
	}
	
	switch ($var_name) {
    case 'connection':
        $value = init_twitter_connection();
        break;
    case 'twitter_usernames':
        $value = get_usernames('twitter');
        break;
    case 'twitter_max_results':
        $value = '20';
        break;
	case 'service':
        $value = init_youtube_connection();
        break;
	case 'youtube_usernames':
        $value = get_usernames('youtube');
        break;
	case 'youtube_max_results':
        $value = 20;
        break;
	 default:
       $value = null;
	}
	
	wp_cache_set($var_name, $value);
	
	return $value;
	
}

function init_twitter_connection() {
	//if the value is cached (not false) use it, otherwise intitialise it
	$api_secrets = wp_cache_get('api_secrets') ? wp_cache_get('api_secrets') : get_object_from_file('api_secrets');
	$CONSUMER_KEY = $api_secrets->CONSUMER_KEY;
	$CONSUMER_SECRET = $api_secrets->CONSUMER_SECRET;
	$access_token = $api_secrets->access_token;
	$access_token_secret = $api_secrets->access_token_secret;
	
	wp_cache_set('api_secrets', $api_secrets);
	
	$connection = new TwitterOAuth($CONSUMER_KEY, $CONSUMER_SECRET, $access_token, $access_token_secret);
	$content = $connection->get("account/verify_credentials");
	$connection->setApiVersion('2');
	
	return $connection;
}

function init_youtube_connection() {
	//if the value is cached (not false) use it, otherwise intitialise it
	$api_secrets = wp_cache_get('api_secrets') ? wp_cache_get('api_secrets') : get_object_from_file('api_secrets');
	$client = wp_cache_get('client') ? wp_cache_get('client') : new Google_Client();
	$developer_key = $api_secrets->developer_key;

	$client->setApplicationName('STFC Interactive Site Maps');
	$client->setDeveloperKey($developer_key);
	
	wp_cache_set('api_secrets', $api_secrets);
	wp_cache_set('client', $client);

	return new Google_Service_YouTube($client);
}

//TO BE CALLED BY OTHER STUFF
function get_usernames($social_media) {
	$options = get_option( 'smwf_options' );
    $media_sources = preg_split("/\r\n|\n|\r/", $options['media_sources']);
	
	$usernames = array();
	
	foreach ($media_sources as &$media_source) {
		if($media_source->social == $social_media) {
			$usernames[$media_source->tag] = $media_source->id;
		}
	}
	
	return $usernames;
}

//Initialises or updates the variables and files for all user's tweets
function update_tweets() {
	$twitter_usernames = get_cached_var('twitter_usernames');

	foreach ($twitter_usernames as $user_name => $user_id) {
		${$user_name . '_tweets'} = get_cached_var("$user_name" . "_tweets");
		$tweets_from_file = get_object_from_file("$user_name" . "_tweets");
		
		//If the tweets are in memory, use that
		if(isset(${$user_name . '_tweets'})) {
			$latest_id = ${$user_name . '_tweets'}[0]->id;
			
			try {
				$new_tweets = get_new_tweets($user_id, $latest_id);
				${$user_name . '_tweets'} = array_merge($new_tweets, ${$user_name . '_tweets'});
				write_object_to_file(${$user_name . '_tweets'}, "$user_name" . "_tweets");
			} catch(Exception $e) {
				echo 'Message: ' .$e->getMessage();
			}
		//Else use the data loaded from file if it exists
		} elseif(!is_null($tweets_from_file)) {
			$latest_id = $tweets_from_file[0]->id;
			
			try {
				$new_tweets = get_new_tweets($user_id, $latest_id);
				${$user_name . '_tweets'} = array_merge($new_tweets, $tweets_from_file);
				write_object_to_file(${$user_name . '_tweets'}, "$user_name" . "_tweets");
			} catch(Exception $e) {
				echo 'Message: ' .$e->getMessage();
			}
		//Clean initialisation
		} else {
			try {
				$collected_tweets = collect_tweets($user_id);
			
				if(count($collected_tweets) > 0) {
					${$user_name . '_tweets'} = $collected_tweets;
					write_object_to_file(${$user_name . '_tweets'}, "$user_name" . "_tweets");
				}
			} catch(Exception $e) {
				echo 'Message: ' .$e->getMessage();
			}
		}
	}
	
	wp_cache_set("$user_name" . "_tweets", ${$user_name . '_tweets'});
}

//Initialises or updates the variables and files for all channels's youtube videos
function update_youtube_videos() {
	$youtube_usernames = get_cached_var('youtube_usernames');
	
	foreach ($youtube_usernames as $user_name => &$channel_id) {
		${$user_name . '_videos'} = get_cached_var("$user_name" . "_videos");
		$videos_from_file = get_object_from_file("$user_name" . "_videos");
		
		//If the videos are in memory, use that
		if(isset(${$user_name . '_videos'})) {
			$video_list = ${$user_name . '_videos'};
		//Else use the data loaded from file if it exists
		} elseif(!is_null($videos_from_file)) {
			$video_list = $videos_from_file;
		//Clean initialisation
		} else {
			$video_list = null;
		}
		
		try {
			${$user_name . '_videos'} = get_channel_videos($channel_id, $video_list);
			write_object_to_file(${$user_name . '_videos'}, "$user_name" . "_videos");
		} catch(Exception $e) {
			echo 'Message: ' .$e->getMessage();
		}
	}
	
	wp_cache_set("$user_name" . "_videos", ${$user_name . '_videos'});
}

//Called by cron for updating/initilising social medias
function update_social_medias() {
	update_tweets();
	update_youtube_videos();
}

function get_name_from_tags($social_media) {
	$id = get_the_ID();
	$tags = get_the_tags($id);
	
	//assuming there is one type of social media account for each post
	return array_intersect(get_usernames($social_media), $tags)[0];
}

function get_keywords_from_tags() {
	$twitter_usernames = get_cached_var('twitter_usernames');
	$youtube_usernames = get_cached_var('youtube_usernames');
	
	$id = get_the_ID();
	$tags = get_the_tags($id);
	$all_socials = array_merge($twitter_usernames, $youtube_usernames);
	
	return array_diff($all_socials, $tags);
}


function filter_tweets() {
	$user_name = get_name_from_tags('twitter');
	
	if(isset(${$user_name . '_tweets'})) {
		return array_filter(${$user_name . '_tweets'}, 'tweet_contains_keyword');
	}
	
	$tweets_from_file = get_object_from_file("$user_name" . "_tweets");
	
	if(!is_null($tweets_from_file)) {
		${$user_name . '_tweets'} = $tweets_from_file;
		wp_cache_set("$user_name" . "_tweets", ${$user_name . '_tweets'});
		
		return array_filter(${$user_name . '_tweets'}, 'tweet_contains_keyword');
	}
	
	return array();
}

//To be called externally for filtering, uses a file as a fallback
function filter_videos() {
	$user_name = get_name_from_tags('youtube');
	
	if(isset(${$user_name . '_videos'})) {
		return array_filter(${$user_name . '_videos'}->items, 'video_contains_keyword');
	}
	
	$videos_from_file = get_object_from_file("$user_name" . "_videos");
	
	if(!is_null($videos_from_file)) {
		${$user_name . '_videos'} = $videos_from_file;
		wp_cache_set("$user_name" . "_videos", ${$user_name . '_videos'});
		
		return array_filter(${$user_name . '_videos'}->items, 'video_contains_keyword');
	}
	
	return array();
}

//TO BE CALLED BY OTHER STUFF

//TWITTER STUFF

//Fetches as many of a user's tweets as the API allows
function collect_tweets($user_id) {
	$connection = get_cached_var('connection');
	$twitter_max_results = get_cached_var('twitter_max_results');
	
	$tweets = array();
	$result = $connection->get("users/$user_id/tweets", ['max_results' => $twitter_max_results]);
	
	if(is_null($result) || property_exists($result, 'errors')) {
		return array();
	}
	
	$tweets = array_merge($tweets, $result->data);

	//while there are still tweets to get, fetch them and add them to the list
	while(property_exists($result->meta, 'next_token')) {
		$result = $connection->get("users/$user_id/tweets", ['max_results' => $twitter_max_results, 'pagination_token' => $result->meta->next_token]);
		
		if(is_null($result) || property_exists($result, 'errors')) {
			return break;
		}
	
		$tweets = array_merge($tweets, $result->data);
	}
	
	return $tweets;
}

//Returns true if a tweet contains a keyword, otherwise false
//Use array_filter() e.g. array_filter($tweets, 'tweet_contains_keyword')
function tweet_contains_keyword($tweet) {
	$keywords = get_keywords_from_tags();
	
	foreach ($keywords as &$keyword) {
		if(stripos($tweet->text, $keyword) !== false) {
			return true;
		}
	}
	
	return false;
}

//Gets a user's new tweets published since the ID of the tweet provided
function get_new_tweets($user_id, $last_tweet_id) {
	$connection = get_cached_var('connection');
	$twitter_max_results = get_cached_var('twitter_max_results');
	
	$tweets = array();
	$result = $connection->get("users/$user_id/tweets", ['max_results' => $twitter_max_results, 'since_id' => $last_tweet_id]);
	
	if(is_null($result) || property_exists($result, 'errors') || !property_exists($result, 'data')) {
		return array();
	}
	
	$tweets = array_merge($tweets, $result->data);
	
	//while there are still tweets to get, fetch them and add them to the list
	while(property_exists($result->meta, 'next_token')) {
		$result = $connection->get("users/$user_id/tweets", ['max_results' => $twitter_max_results, 'since_id' => $last_tweet_id, 'pagination_token' => $result->meta->next_token]);
		
		if(is_null($result) || property_exists($result, 'errors')) {
			break;
		}
	
		$tweets = array_merge($tweets, $result->data);
	}
	
	return $tweets;
}

//Gets a list of Twitter IDs from a list of user names
function get_twitter_user_ids($user_names) {
	$connection = get_cached_var('connection');
	
	$user_ids = array();
	$user_id_response_array = $connection->get('users/by', ['usernames' => $user_names]);
	
	if(is_null($user_id_response_array) || property_exists($user_id_response_array, 'errors')) {
		return array();
	}
	
	foreach ($user_id_response_array->data as &$user_id_response) {
		$user_ids[$user_id_response->username] = $user_id_response->id;
	}
	
	return $user_ids;
}

//TWITTER STUFF

//YOUTUBE_STUFF

//Gets either all of a YouTube channel's videos or just the new ones not already saved
//The number of new videos to fetch is calculated by the new total number of ideos - old total number of videos
function get_channel_videos($channel_id, $current_video_list = null) {
	$youtube_max_results = get_cached_var('youtube_max_results');
	$service = get_cached_var('service');
	
	$videos = array();
	$previous_total_videos = is_null($current_video_list) ? 0 : $current_video_list->pageInfo->totalResults;
	
	$query_params = [
		'channelId' => $channel_id,
		'maxResults' => $youtube_max_results,
		'order' => 'date',
		'type' => 'video',
	];
	
	$response = $service->search->listSearch('snippet', $query_params);
	
	$new_video_list = is_null($current_video_list) ? $response : $current_video_list;
	$new_total_videos = $response->pageInfo->totalResults;
	$remaining_videos_to_fetch = $new_total_videos - $previous_total_videos;

	$new_video_list->pageInfo->totalResults = $new_total_videos;
	$videos = $remaining_videos_to_fetch < $youtube_max_results ? array_merge($videos, array_slice($response->items, 0, $remaining_videos_to_fetch, true)) : array_merge($videos, $response->items);
	$remaining_videos_to_fetch = $remaining_videos_to_fetch - $youtube_max_results;
	//while there are more videos to fetch
	while(property_exists($response, 'nextPageToken') && $remaining_videos_to_fetch > 0) {
		$query_params['pageToken'] = $response->nextPageToken;
		$response = $service->search->listSearch('snippet', $query_params);

		//if we've reached the last page of results we need, only add the remaining number of videos we need, otherwise add the whole page
		$videos = $remaining_videos_to_fetch < $youtube_max_results ? array_merge($videos, array_slice($response->items, 0, $remaining_videos_to_fetch, true)) : array_merge($videos, $response->items);
		
		$remaining_videos_to_fetch = $remaining_videos_to_fetch - $youtube_max_results;
	}

	//if we're fetching a channel's videos for the first time they're already in the correct order 
	//if we're getting new ones, preprend them to the list of existing ones
	$new_video_list->items = is_null($current_video_list) ? $videos : array_merge($videos, $new_video_list->items);
	
	return $new_video_list;
}


//Returns true if a video's title or description contains a keyword, false otherwise
//Is case insensitive
//Use with array_filter() e.g. array_filter(get_channel_videos(channel_id, 'video_contains_keyword');
function video_contains_keyword($video) {
	$keywords = get_keywords_from_tags();
	
	foreach ($keywords as &$keyword) {
		if(stripos($video->snippet->title, $keyword) !== false || stripos($video->snippet->description, $keyword) !== false) {
			return true;
		}
	}
	
	return false;
}

//YOUTUBE STUFF

//Serialises an object and writes it to a file
//If the file already exists it is overwritten
function write_object_to_file($object, $file_name) {
	$fp = fopen($file_name, 'w');
	fwrite($fp, serialize($object));
	fclose($fp);
}


//Reads a file and returns an object if possible
//Returns null if an error is thrown while reading the file
function get_object_from_file($fileNanme) {
	try {
		$file_contents = file_get_contents($fileNanme);
	} catch(Exception $e) {
		return null;
	}
	
	return !$file_contents ? null : unserialize($file_contents);
}