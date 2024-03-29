<?php

//run composer require abraham/twitteroauth and composer require google/apiclient:^2.0
require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
}

use Abraham\TwitterOAuth\TwitterOAuth;

//Creates a new twitter connection with credentials from the secrets file
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

//Creates a new youtube connection with credentials from the secrets file
function init_youtube_connection() {
	$api_secrets = get_object_from_file('api_secrets');
	$client = new Google_Client();
	$developer_key = $api_secrets->developer_key;

	$client->setApplicationName('STFC Interactive Site Maps');
	$client->setDeveloperKey($developer_key);

	return new Google_Service_YouTube($client);
}

//TO BE CALLED BY OTHER STUFF

//Gets social media ('twitter' or 'youtube') usernames from wordpress' media sources variable
function get_usernames($social_media) {
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
        $usernames = array();
        foreach ($media_sources as &$media_source) {
                if($media_source->social == $social_media) {
                        //echo var_dump($media_source);
                        $usernames[$media_source->tag] = $media_source->id;
                }
        }
	return $usernames;
}

//Creates or updates the files containing tweets for each twitter user specified in media sources
function update_tweets() {
	$twitter_usernames = get_usernames('twitter');
	
	//For each twitter user
	foreach ($twitter_usernames as $user_name => $user_id) {
		$tweets_from_file = get_object_from_file("$user_name" . "_tweets");
		
		//Use the tweets loaded from file if it exists
		if(!is_null($tweets_from_file)) {
			$latest_id = $tweets_from_file[0]->id;
			
			try {
				$new_tweets = get_new_tweets($user_id, $latest_id);
				$aggregated_tweets = array_merge($new_tweets, $tweets_from_file);
				
				//If the number of tweets currently stored is greater than the max allowed, cull the oldest posts
				if(count($aggregated_tweets) > 200) {
					$num_elems_to_remove = count($aggregated_tweets) - 200;
					array_splice($aggregated_tweets, count($aggregated_tweets) - $num_elems_to_remove, $num_elems_to_remove);
				}
				
				write_object_to_file($aggregated_tweets, "$user_name" . "_tweets");
			} catch(Exception $e) {
				echo 'Message: ' .$e->getMessage();
			}
		//There are no existing tweets so start from scratch
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

//Creates or updates the files containing youtube videos for each youtube user specified in media sources
function update_youtube_videos() {
	$youtube_usernames = get_usernames('youtube');
	
	//For each youtube user
	foreach ($youtube_usernames as $user_name => &$channel_id) {
		$videos_from_file = get_object_from_file("$user_name" . "_videos");
		
		//Use the videos loaded from file if it exists
		if(!is_null($videos_from_file)) {
			$video_list = $videos_from_file;
		//There are no existing videos so start from scratch
		} else {
			$video_list = null;
		}
		
		try {
			$aggregated_videos = get_channel_videos($channel_id, $video_list);
			
			//If the number of videos currently stored is greater than the max allowed, cull the oldest posts
			if(count($aggregated_videos) > 200) {
					$num_elems_to_remove = count($aggregated_videos) - 200;
					array_splice($aggregated_videos, count($aggregated_videos) - $num_elems_to_remove, $num_elems_to_remove);
			}
			
			write_object_to_file($aggregated_videos, "$user_name" . "_videos");
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


//Gets the twitter or youtube username from a post's tags
function get_name_from_tags($social_media) {
	$id = get_the_ID();
	$tags = get_the_tags($id);
	$t2 = array();
        $t2 = array_map(function($x) {return $x->name;}, $tags);
        $tags = $t2;
	
	$usernames = array();
	
	if(strcmp($social_media, 'twitter') == 0) {
		$usernames = get_usernames('twitter');
	}
	
	if(strcmp($social_media, 'youtube') == 0) {
		$usernames = get_usernames('twitter');
	}
	
	//remove the general STFC account if others are available
	$names_arr=array_values(array_intersect(array_keys($usernames), $tags));
        if (count($names_arr)>1){
                $names_arr=array_values(array_diff(array_intersect(array_keys($usernames), $tags),['STFC']));
        }
	
	//assuming there is one type (twitter or youtube) of social media account to get posts from for each post
	return $names_arr[0];
}


//Gets a post's filter keywords from its tags
function get_keywords_from_tags() {
	$names = array(get_name_from_tags('twitter'), get_name_from_tags('youtube'));
	
	$id = get_the_ID();
	$tags = get_the_tags($id);

	$t2 = array();
	$t2 = array_map(function($x) {return $x->name;}, $tags);
        $tags = $t2;

	//everything that isn't a social media username is a filter keyword
	return array_diff($tags, $names);
}

//To be called when a post needs tweets to display, filtered by its keywords
function filter_tweets() {
	$name = get_name_from_tags('twitter');
	$tweets_from_file = get_object_from_file("$name" . "_tweets");
	
	if(!is_null($tweets_from_file)) {
                 $filtered=array_filter($tweets_from_file, 'tweet_contains_keyword');
                 // failsafe for no results when filtered
                 if(!empty($filtered)){
                      return $filtered;
                 }
                 else{
                      return $tweets_from_file;
		 }
        }
	return array();
}

//To be called when a post needs videos to display, filtered by its keywords
function filter_videos() {
	$name = get_name_from_tags('youtube');
	$videos_from_file = get_object_from_file("$name" . "_videos");
	
	if(!is_null($videos_from_file)) {
		$filtered=array_filter($videos_from_file->items, 'video_contains_keyword');
	        if(!empty($filtered)){
                         return $filtered;
                 }
		// return unfiltered videos from SM account
                else{
                        return $videos_from_file->items;
                }
        }
	// return STFC youtube videos filtered by keyword
        else{
          $videos_from_file = get_object_from_file("STFC" . "_videos");
          $filtered=array_filter($videos_from_file->items, 'video_contains_keyword_stfc');
                if(!empty($filtered)){
                         return $filtered;
                 }
        }
}

//TO BE CALLED BY OTHER STUFF

//TWITTER STUFF

//Fetches as many of a user's tweets as the API allows
function collect_tweets($user_id) {
	$connection = init_twitter_connection();
	$twitter_max_results = '20';
	$twitter_max_results_int = (int)$twitter_max_results;
	$tweet_collection_limit = 200;
	
	$tweets = array();
	$result = $connection->get("users/$user_id/tweets", ['max_results' => $twitter_max_results]);
	
	if(is_null($result) || property_exists($result, 'errors') || !property_exists($result, 'data')) {
		return array();
	}
	
	$tweets = array_merge($tweets, $result->data);
	$tweet_collection_limit = $tweet_collection_limit - $twitter_max_results_int;

	//while there are still tweets to get and the limit of tweets has not been reached, fetch them and add them to the list
	while(property_exists($result->meta, 'next_token') && $tweet_collection_limit > 0) {
		$result = $connection->get("users/$user_id/tweets", ['max_results' => $twitter_max_results, 'pagination_token' => $result->meta->next_token]);
		
		if(is_null($result) || property_exists($result, 'errors') || !property_exists($result, 'data')) {
			break;
		}

		$tweets = array_merge($tweets, $result->data);
		$tweet_collection_limit = $tweet_collection_limit - $twitter_max_results_int;
	}
	
	return $tweets;
}

//Returns true if a tweet contains a keyword, otherwise false
//Use array_filter() e.g. array_filter($tweets, 'tweet_contains_keyword')
function tweet_contains_keyword($tweet) {
	$keywords = get_keywords_from_tags();
	//return all tweets for general pages (no keywords specified)
        if(empty($keywords)) {
                return true;
        }
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
		
		if(is_null($result) || property_exists($result, 'errors') || !property_exists($result, 'data')) {
			break;
		}

		$tweets = array_merge($tweets, $result->data);
	}
	
	return $tweets;
}

//Gets a list of Twitter IDs from a list of user names
//Provide user names as a comma-separated string with no spaces e.g. 'john,sarah'
function get_twitter_user_ids($user_names) {
	$connection = init_twitter_connection();
	
	$user_ids = array();
	$user_id_response_array = $connection->get('users/by', ['usernames' => $user_names]);
	
	if(is_null($user_id_response_array) || property_exists($user_id_response_array, 'errors')) {
		echo 'Message: ' .$e->getMessage();
		
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
//The number of new videos to fetch is calculated by the new total number of videos - old total number of videos
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
	//return all videos for general pages (no keywords specified)
        if(empty($keywords)) {
                 return true;
        }
	foreach ($keywords as &$keyword) {
		if(stripos($video->snippet->title, $keyword) !== false || stripos($video->snippet->description, $keyword) !== false) {
			return true;
		}
	}
	
	return false;
}

//keyword filter including account keywords for general STFC youtube account
function video_contains_keyword_stfc($video) {
        $tags = get_the_tags($id);
        $t2 = array();
        $t2 = array_map(function($x) {return $x->name;}, $tags);
        $keywords = $t2;
        //return all tweets for general pages (no keywords specified)
                if(empty($keywords)) {
                            return true;
                                    }
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
	$fp = fopen( plugin_dir_path(__FILE__) . $file_name, 'w');
	fwrite($fp, serialize($object));
	fclose($fp);
}


//Reads a file and returns an object if possible
//Returns null if an error is thrown while reading the file
function get_object_from_file($fileNanme) {
	try {
		$file_contents = file_get_contents( plugin_dir_path(__FILE__) . $fileNanme);
	} catch(Exception $e) {
		return null;
	}
	
	return !$file_contents ? null : unserialize($file_contents);
}
