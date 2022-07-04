# Post Fetching & Filtering

# Notes

The code assumes that for each post there is just one account for each social media platform to fetch from e.g. there is just one Twitter account to pull tweets from for a particular building/team page, one YouTube account, etc.

If an error is encountered when collecting the paginated posts for a social media account from an API, collection is stopped and the posts collected so far are returned.

The secrets required to make API calls to Twitter and YouTube are stored in the `api_secrets` file on the server. These secrets should *not* be made publicly viewable as that would compromise the security of the application.

Data is written and loaded from file every time as data is not persisted accross page loads in Wordpress.

Social media user IDs are stored in the `media_sources` variable in Wordpress' `options`.

The social media user IDs associated with a post are retrieved by matching the post's tags with the names of social media account stored in `media_sources` and getting the corresponding social media user IDs from `media_sources`.

The filter keywords for a post are fetched by getting the post's tags that don't match any social media account names in `media_sources`.

Data is written to and read from disk in a way that preserves its structure so there shouldn't be any issue directly using the writing and reading functions provided in the plugin with objects.

# Pagination

When the number of results from an API query is greater than the number of items that can fit in a single response, the caller receives a token that can be used to rerieve the next set of results in the response returned by the API. If this token is passed in a request, the next 'page' of results is returned, with another token for the next page if there is one. This process repeats until the last page of results is reached. Twitter's API [docs](https://developer.twitter.com/en/docs/twitter-api/pagination) detail this.

This is what's happening in the `collect_tweets`, `get_new_tweets` and `get_channel_videos` functions. They get the first API response, check if there are more pages and if there are, collect the results from subsequent pages until there are none.