<?php

//run composer require abraham/twitteroauth and composer require google/apiclient:^2.0
require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
  throw new Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
}



use Abraham\TwitterOAuth\TwitterOAuth;

//TWITTER SETUP
$CONSUMER_KEY = '';
$CONSUMER_SECRET = '';
$access_token = '';
$access_token_secret = '';
$connection = new TwitterOAuth($CONSUMER_KEY, $CONSUMER_SECRET, $access_token, $access_token_secret);
$content = $connection->get("account/verify_credentials");
$connection->setApiVersion('2');
$userIdResponse = $connection->get('users/by', ['usernames' => 'STFC_Matters']);
$userIdLiteral = $userIdResponse->data[0]->id;
$userNames = readJsonFile('twitter_usernames.json');
$twitterKeywords = array('update', 'learn');
$twitterMaxResults = '20';
//TWITTER SETUP

//YOUTUBE SETUP
$developerKey = '';
$client = new Google_Client();
$client->setApplicationName('API code samples');
$client->setDeveloperKey($developerKey);

// Define service object for making API requests.
$service = new Google_Service_YouTube($client);

$youtubeKeywords = ['matter', 'daresbury'];
$channelIds = readJsonFile('youtube_channel_ids.json');
$youtubeMaxResults = 10;
//YOUTUBE SETUP

//TWITTER STUFF

//limit is here just for testing purposes
//if this function returns an empty array, don't update the tweets lists
function collect_tweets($userId) {
	global $connection, $twitterMaxResults;
	
	$tweets = array();
	$limit = 0;
	
	$result = $connection->get("users/$userId/tweets", ['max_results' => $twitterMaxResults]);
	
	if(is_null($result) || property_exists($result, 'errors')) {
		return array();
	}
	
	$tweets = array_merge($tweets, $result->data);
	
	while(property_exists($result->meta, 'next_token')) {
		$result = $connection->get("users/$userId/tweets", ['max_results' => $twitterMaxResults, 'pagination_token' => $result->meta->next_token]);
		
		if(is_null($result) || property_exists($result, 'errors')) {
			return array();
		}
	
		$tweets = array_merge($tweets, $result->data);
		
		if ($limit == 0) {
			break;
		}
		
		$limit++;
	}
	
	return $tweets;
}

// use array_filter(collect_tweets(), 'tweetContainsKeyword')
function tweetContainsKeyword($tweet) {
	global $twitterKeywords;
	foreach ($keywords as &$keyword) {
		if(stripos($tweet->text, $keyword) !== false) {
			return true;
		}
	}
	
	return false;
}

//if this function returns an empty array, don't update the tweets lists
function getNewTweets($userId, $lastTweetId) {
	global $connection, $twitterMaxResults;
	
	$result = $connection->get("users/$userId/tweets", ['max_results' => $twitterMaxResults, 'since_id' => $lastTweetId]);
	
	if(is_null($result) || property_exists($result, 'errors')) {
		return array();
	}
	
	return $result->data;
}

//if this fails, use a file as backup
function getTwitterUserIds() {
	global $connection, $userNames;
	
	$userIds = array();
	$userIdResponseArray = $connection->get('users/by', ['usernames' => $userNames]);
	
	if(is_null($userIdResponseArray) || property_exists($userIdResponseArray, 'errors')) {
		return array();
	}
	
	foreach ($userIdResponseArray->data as &$userIdResponse) {
		$userIds[$userIdResponse->username] = $userIdResponse->id;
	}
	
	return $userIds;
}

print_r(array_filter(collect_tweets(getTwitterUserIds()['STFC_Matters']), 'tweetContainsKeyword'));

//TWITTER STUFF

//YOUTUBE_STUFF

//functions that make a failed youtube api call will throw an exception
function getChannelVideos($channelId, $currentVideoList = null) {	
	global $youtubeMaxResults, $service;
	
	$previousTotalVideos = is_null($currentVideoList) ? 0 : $currentVideoList->pageInfo->totalResults;
	
	$queryParams = [
		'channelId' => $channelId,
		'maxResults' => $youtubeMaxResults,
		'order' => 'date',
		'type' => 'video',
	];
	
	$response = $service->search->listSearch('snippet', $queryParams);
	
	$newVideoList = is_null($currentVideoList) ? $response : $currentVideoList;
	$newTotalVideos = $response->pageInfo->totalResults;
	$remainingVideosToFetch = $newTotalVideos - $previousTotalVideos;
	$remainingVideosToFetch = 2;
	$newVideos = array();
	
	while(property_exists($response, 'nextPageToken') && $remainingVideosToFetch > 0) {
		$queryParams['pageToken'] = $response->nextPageToken;
		$response = $service->search->listSearch('snippet', $queryParams);
		
		$newVideoList->items = $remainingVideosToFetch < $youtubeMaxResults ? array_merge($newVideoList->items, array_slice($response->items, 0, $remainingVideosToFetch, true)) : array_merge($newVideoList->items, $response->items);
		
		$remainingVideosToFetch = $remainingVideosToFetch - $youtubeMaxResults;
	}
	
	return $newVideoList;
}

function videoContainsKeyword($video) {
	global $keywords;
	
	foreach ($keywords as &$keyword) {
		if(stripos($video->snippet->title, $keyword) !== false || stripos($video->snippet->description, $keyword) !== false) {
			return true;
		}
	}
	
	return false;
}

print_r(array_filter(getChannelVideos($channelIds[0])->items, 'videoContainsKeyword'));

//YOUTUBE STUFF

function writeJsonFile($object, $fileName) {
	$fp = fopen($fileName, 'w');
	fwrite($fp, json_encode($object));
	fclose($fp);
}

//could add .json extension here instead of having to specify it in arg
function readJsonFile($fileNanme) {
	$tweetFileContents = file_get_contents($fileNanme);
	
	return json_decode($tweetFileContents, true);
}