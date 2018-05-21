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
use Artesaos\LinkedIn\LinkedinServiceProvider;
use Artesaos\LinkedIn\Facades\LinkedIn;

class SocialfeedLinkedin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'socialfeedlinkedin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corn job for Linkedin feed';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
      
      $apiKey = env('LINKEDIN_KEY','');
      $apiSecret = env('LINKEDIN_SECRET','');
      $pageId = env('LINKEDIN_PAGEID','');
      $api = New \Artesaos\LinkedIn\LinkedInLaravel($apiKey, $apiSecret);
      $api->setAccessToken('AQVDCZ2gm8sfvqt7JL9jf9TNqET-5Yey2pLUR92FY7rVRPwgYzaqu1gVjr-jgCBzyTuDFTY4dId8d-nj1dpuLJWC2S7nU_CTHJ6VsA0mR4-coDcPAACnaXIGeUDkiSQvAV36qMgwH_mUmgC2ONVHCp_vFUFVfZvZJOkI28gWH8mXwk3XeFoOvLUCTs-R5DPyfYv4DlN9xxVOx1gIvy1ZNUoAtEwugQg_rPSOmYvrqYgMvZd_NqLuZiZNZU4WnWojs8JEquVXHk553d2S5s0GY_RmjxJNX7ndrVtUBIPnQkVCoFA3fWtwP9xkKtgfMzTLiLTxl069YcHACJPmxAGjY1ENypJx9w');
      $data = $api->get('v1/companies/' .$pageId. '/updates?start=0&count=10&format=json&event-type=status-update');
      
       if ( isset($data['values']) && !empty( $data['values'])) {
          foreach ($data['values'] as $key => $value) {
           $this->saveSocialData($value);
          }
        }
        
       

    }

    /*
    * save data in social table.
    */
    private function saveSocialData($data1) {
      if (!empty($data1) && isset($data1['updateContent']['companyStatusUpdate']['share'])) {
        $data = $data1['updateContent']['companyStatusUpdate']['share'];
        $postId = $data['id'];
        $this->comment('Process feed with id - ' . $postId);
       $hashtag = $this->getHashtag($data['comment']);
       if (!empty($hashtag)) {
          $tagIds = DB::table('event_hash')->whereIn('hash', $hashtag)->groupBy('event_id')->orderBy('id')->get();
          if (!empty($tagIds)) {
            $fields1 = $this->getFields($data, $data1);
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
    private function getFields ($data, $data1) {

    //  6351791160503177216

      $ids = explode('-', $data1['updateKey']);
      $fields = [
        //'event_hast_id',
        'Title' => '',
        'description' => $data['comment'],
        'handle' => 'linkedin',
        'updated_At' => date('Y-m-d h:i:s'),
        'status' => 'Active', 
        'image' => '', 
        'url' => 'https://www.linkedin.com/feed/update/urn:li:activity:' . $ids[2], 
        'author' => $data1['updateContent']['company']['name'], 
        'auther_id' => $data1['updateContent']['company']['id'], 
        'auther_image' => '', 
        'post_id' => $data['id'],
        'data' => ''
       ];
       $extra = [
         'lik_count' => $data1['numLikes'],
         'isCommentable' => $data1['isCommentable'],
         'comment_coount' => $data1['updateComments']['_total'],
         //'lang' => $data['lang'],1
        ];
       if (isset($data['content']) && !empty(['content'])) {
        if (isset($data['content']['eyebrowUrl'])) {
          $fields['image'] = $data['content']['eyebrowUrl'];
        }
       }
       $fields['data'] = json_encode($extra);
       return $fields;
    }
}

