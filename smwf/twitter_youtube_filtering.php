<?php

//run composer require abraham/twitteroauth and composer require google/apiclient:^2.0
require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
}

use Abraham\TwitterOAuth\TwitterOAuth;

function init_twitter_connection() {
	$api_secrets = get_object_from_file('api_secrets');
	$CONSUMER_KEY = $api_secrets->CONSUMER_KEY;
	$CONSUMER_SECRET = $api_secrets->CONSUMER_SECRET;
	$access_token = $api_secrets->access_token;
	$access_token_secret = $api_secrets->access_token_secret;
	
	$connection = new TwitterOAuth($CONSUMER_KEY, $CONSUMER_SECRET, $access_token, $access_token_secret);
	$content = $connection->get("account/verify_credentials");
	$connection->setApiVersion('2');
	
	return $connection;
}

function init_youtube_connection() {
	$api_secrets = get_object_from_file('api_secrets');
	$client = new Google_Client();
	$developer_key = $api_secrets->developer_key;

	$client->setApplicationName('STFC Interactive Site Maps');
	$client->setDeveloperKey($developer_key);

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
	$twitter_usernames = get_usernames('twitter');

	foreach ($twitter_usernames as $user_name => $user_id) {
		$tweets_from_file = get_object_from_file("$user_name" . "_tweets");
		
		//Use the data loaded from file if it exists
		if(!is_null($tweets_from_file)) {
			$latest_id = $tweets_from_file[0]->id;
			
			try {
				$new_tweets = get_new_tweets($user_id, $latest_id);
				$aggregated_tweets = array_merge($new_tweets, $tweets_from_file);
				write_object_to_file($aggregated_tweets, "$user_name" . "_tweets");
			} catch(Exception $e) {
				echo 'Message: ' .$e->getMessage();
			}
		//Clean initialisation
		} else {
			try {
				$collected_tweets = collect_tweets($user_id);
			
				if(count($collected_tweets) > 0) {
					write_object_to_file($collected_tweets, "$user_name" . "_tweets");
				}
			} catch(Exception $e) {
				echo 'Message: ' .$e->getMessage();
			}
		}
	}
}

//Initialises or updates the variables and files for all channels's youtube videos
function update_youtube_videos() {
	$youtube_usernames = get_usernames('youtube');
	
	foreach ($youtube_usernames as $user_name => &$channel_id) {
		$videos_from_file = get_object_from_file("$user_name" . "_videos");
		
		//Use the data loaded from file if it exists
		if(!is_null($videos_from_file)) {
			$video_list = $videos_from_file;
		//Clean initialisation
		} else {
			$video_list = null;
		}
		
		try {
			write_object_to_file(get_channel_videos($channel_id, $video_list), "$user_name" . "_videos");
		} catch(Exception $e) {
			echo 'Message: ' .$e->getMessage();
		}
	}
}

//Called by cron for updating/initilising social medias
function update_social_medias() {
	update_tweets();
	update_youtube_videos();
}

function get_name_from_tags($social_media) {
	$id = get_the_ID();
	$tags = get_the_tags($id);
	
	$usernames = array();
	
	if(strcmp($social_media, 'twitter') == 0) {
		$usernames = get_usernames('twitter');
	}
	
	if(strcmp($social_media, 'youtube') == 0) {
		$usernames = get_usernames('twitter');
	}
	//assuming there is one type of social media account for each post
	return array_intersect(array_keys($usernames), $tags)[0];
}

function get_keywords_from_tags() {
	$names = array(get_name_from_tags('twitter'), get_name_from_tags('youtube'));
	
	$id = get_the_ID();
	$tags = get_the_tags($id);
	
	return array_diff($tags, $names);
}


function filter_tweets() {
	$name = get_name_from_tags('twitter');
	$tweets_from_file = get_object_from_file("$name" . "_tweets");
	
	if(!is_null($tweets_from_file)) {
		return array_filter($tweets_from_file, 'tweet_contains_keyword');
	}
	
	return array();
}

//To be called externally for filtering
function filter_videos() {
	$name = get_name_from_tags('youtube');
	$videos_from_file = get_object_from_file("$name" . "_videos");
	
	if(!is_null($videos_from_file)) {
		return array_filter($videos_from_file->items, 'video_contains_keyword');
	}
	
	return array();
}

//TO BE CALLED BY OTHER STUFF

//TWITTER STUFF

//Fetches as many of a user's tweets as the API allows
function collect_tweets($user_id) {
	$connection = init_twitter_connection();
	$twitter_max_results = '20';
	
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
			break;
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
	$connection = init_twitter_connection();
	$twitter_max_results = '20';
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
	$connection = init_twitter_connection();
	
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
	$youtube_max_results = 20;
	$service = init_youtube_connection();
	
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