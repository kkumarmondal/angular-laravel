<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Config;
use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Ring\Exception\RingException;
use GuzzleHttp\Exception\RequestException;
use DB;
use Twitter;

class SocialfeedTwitter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'socialfeedtwitter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corn job for twitter feed';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {

      $userId = Config::get('services.twitter.page_id');    
      $tweets = Twitter::getUserTimeline([ 'screen_name' => $userId, 'count' => 200, 'format' => 'array', "tweet_mode" => 'extended', 'include_entities' => true]);
       if ( !empty($tweets)) {
          foreach ($tweets as $key => $value) {
            $this->saveSocialData($value);
          }
        }
        


    }

    /*
    * save data in social table.
    */
    private function saveSocialData($data) {
      if (!empty($data) && isset($data['full_text'])) {
        $postId = $data['id'];
        $this->comment('Process feed with id - ' . $postId);
       $hashtag = $this->getHashtag($data['full_text']);
       if (!empty($hashtag)) {
          $tagIds = DB::table('event_hash')->whereIn('hash', $hashtag)->groupBy('event_id')->orderBy('id')->get();
          if (!empty($tagIds)) {
            $fields1 = $this->getFields($data);
            foreach ($tagIds as $key => $value) {

              $fields = $fields1;
              $sfId = DB::table('social_data')->where('event_hast_id', $value->id)->where('post_id', $postId)->value('id');
              if (!is_null($sfId)) {
                unset($fields['status']);
                $updateData = DB::table('social_data')->where('id', '=', $sfId)->update($fields);
              }
              else {
                $fields['event_hast_id'] =  $value->id; 
                $insertData = DB::table('social_data')->insert($fields);
              }
            }

          }
       }
       
      }
    }

   /**
   * Get post hashtag.
   */
    private function getHashtag($tweet) {
       preg_match_all("/(#\w+)/", $tweet, $matches);
       return isset($matches[0]) ? $matches[0] : array();
    }


    /*
    * Get table fields for feed data.
    */
    private function getFields ($data) {
      $fields = [
        //'event_hast_id',
        'Title' => '',
        'description' => $data['full_text'],
        'handle' => 'twitter',
        'updated_At' => date('Y-m-d h:i:s'),
        'status' => 'Active', 
        'image' => '', 
        'url' => 'https://twitter.com/' . $data['user']['screen_name']. '/status/' . $data['id'], 
        'author' => $data['user']['name'], 
        'auther_id' => '@'. $data['user']['screen_name'], 
        'auther_image' => $data['user']['profile_image_url'], 
        'post_id' => $data['id'],
        'data' => ''
       ];
       $extra = [
         'retweet_count' => $data['retweet_count'],
         'favorite_count' => $data['favorite_count'],
         'is_quote_status' => $data['is_quote_status'],
         'lang' => $data['lang'],
        ];
       if (isset($data['extended_entities']['media']) && !empty($data['extended_entities']['media'])) {
        if (isset($data['extended_entities']['media'][0]) && isset($data['extended_entities']['media'][0]['media_url'])) {
          $fields['image'] = $data['extended_entities']['media'][0]['media_url'];
        }
       }
       $fields['data'] = json_encode($extra);
       return $fields;
    }
}

