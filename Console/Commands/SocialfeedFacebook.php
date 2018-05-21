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


class SocialfeedFacebook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'socialfeedfacebook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corn job for facebook feed';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    //private $appId;
    //private $appSecert;
    public function handle()
    {

      $appId =  Config::get('services.facebook.client_id');
      $appSecert =  Config::get('services.facebook.client_secret');
      $pageId = Config::get('services.facebook.page_id');
            
      //$fb1 = new \Facebook\FacebookApp($appId, $appSecert);
      //$appToken = $fb1->getAccessToken()->getValue();
      $appToken = 'EAAHnkOYyYC4BADcT1apECwn5MA37I7ZCoo1dktIyfKWs2bFHQk3vDcpGL0i4vjfMpTkDAoZAtukSbqPhOEYAkrUJnRg6rSGanadE1VcnbgPZAJqSdmO3cZBxGYI9tia6t7RK1MycUCtdTw2P8B1mahJCZCinRC78LvUcsPu4GHQZDZD';
     $fb = new \Facebook\Facebook([
        'app_id' => $appId,
        'app_secret' => $appSecert,
        'default_graph_version' => 'v2.11',
        //'default_access_token' => '{access-token}', // optional
      ]);


     
      
      // $this->comment(print_r($this->getAccessToken(),1));

      try {
        // Get the \Facebook\GraphNodes\GraphUser object for the current user.
        // If you provided a 'default_access_token', the '{access-token}' is optional.
       $response = $fb->get($pageId. '/feed?fields=message,reactions.summary(true),id,created_time,attachments,likes.summary(true),comments.summary(true),from{id,name,picture{url}},permalink_url,type&limit=50', $appToken);
     
       $data = (array) json_decode($response->getBody());
       if (isset($data['data']) && !empty($data['data'])) {
          foreach ($data['data'] as $key => $value) {
            $this->saveSocialData((array) $value);
          }
        

       }

        
      } catch(\Facebook\Exceptions\FacebookResponseException $e) {
        // When Graph returns an error
        echo 'Graph returned an error: ' . $e->getMessage();
        exit;
      } catch(\Facebook\Exceptions\FacebookSDKException $e) {
        // When validation fails or other local issues
        echo 'Facebook SDK returned an error: ' . $e->getMessage();
        exit;
      }
    }


    private function getAccessToken() {
      $appId =  Config::get('services.facebook.client_id');
      $appSecert =  Config::get('services.facebook.client_secret');
      $options = [];
      $url =  'https://www.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id=' . $appId. '&client_secret=' . $appSecert . '&fb_exchange_token={short-lived-token}';
      $guzzleClient = new Client();

     

        try {
            $request = $guzzleClient->request('GET', $url, $options);
        } catch (RequestException $e) {
            echo $e->getResponse();
        }

        
         $rawBody = $request->getBody();


    } 

    /*
    * save data in social table.
    */
    private function saveSocialData($data) {
      if (!empty($data) && isset($data['message'])) {
        $postId = $data['id'];
        $this->comment('Process feed with id - ' . $postId);
       $hashtag = $this->getHashtag($data['message']);
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
        'description' => $data['message'],
        'handle' => 'facebook',
        //'created_at',
        'updated_At' => date('Y-m-d h:i:s'),
        'status' => 'Active', 
        'image' => '', 
        'url' => $data['permalink_url'], 
        'author' => $data['from']->name, 
        'auther_id' => $data['from']->id, 
        'auther_image' => '', 
        'post_id' => $data['id'],
        'data' => '[]'
       ];
       $extra = array();
       if (isset($data['from']->picture->data) && !empty($data['from']->picture->data)) {
        if (isset($data['from']->picture->data->url)) {
          $fields['auther_image'] = $data['from']->picture->data->url;
        }
       }
       //print_r($data['attachments']);
       if (isset($data['attachments']->data) && !empty($data['attachments']->data)) {
        if (isset($data['attachments']->data[0]) && isset($data['attachments']->data[0]->media->image)) {
          $fields['image'] = $data['attachments']->data[0]->media->image->src;
        }

       }

       if (isset($data['comments']->summary)) {
        $extra['comments_count'] =  $data['comments']->summary->total_count;
        $extra['data'] =  $data['comments']->data;

       }
       if (isset($data['reactions']->summary)) {
        $extra['likes_count'] =  $data['reactions']->summary->total_count;
       }
       $fields['data'] = json_encode($extra);
       return $fields;
    }
}

