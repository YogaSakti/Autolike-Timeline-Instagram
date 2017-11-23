<?php

/* 
 * MUHAMMAD FADHIIL RACHMAN © 2016 Made This
 *
 * fadhiilrachman@gmail.com - fadilus.com
 *
 */

require_once 'config.php';

class Instagram
{
  protected $username;
  protected $password;

  protected $uuid;
  protected $device_id;
  protected $username_id;
  protected $token;
  protected $isLoggedIn = false;
  protected $rank_token;
  public $IGDataPath;

  public function __construct()
  {
      global $FADILUS;
      $this->username = $FADILUS['ig'][0];
      $this->password = $FADILUS['ig'][1];
      $this->uuid = $this->generateUUID(true);
      $this->device_id = $this->generateDeviceId(md5($FADILUS['ig'][0].$FADILUS['ig'][1]));
      if (!file_exists( 'cache' )) {
        mkdir('cache/' , 0777);
      }
      $this->IGDataPath = 'cache/';
      if ((file_exists($this->IGDataPath."$this->username-cookies.log")) && (file_exists($this->IGDataPath."$this->username-userId.log"))
    && (file_exists($this->IGDataPath."$this->username-token.log"))) {
          $this->isLoggedIn = true;
          $this->username_id = trim(file_get_contents($this->IGDataPath."$this->username-userId.log"));
          $this->rank_token = $this->username_id.'_'.$this->uuid;
          $this->token = trim(file_get_contents($this->IGDataPath."$this->username-token.log"));
      }
  }

  public function login()
  {
      if (!$this->isLoggedIn) {
          $fetch = $this->request('si/fetch_headers/?challenge_type=signup&guid='.$this->generateUUID(false), null, true);
          preg_match('#Set-Cookie: csrftoken=([^;]+)#', $fetch[0], $token);

          $data = [
          'device_id'           => $this->device_id,
          'guid'                => $this->uuid,
          'phone_id'            => $this->generateUUID(true),
          'username'            => $this->username,
          'password'            => $this->password,
          'login_attempt_count' => '0',
           ];

          $login = $this->request('accounts/login/', $this->generateSignature(json_encode($data)), true);
          $this->isLoggedIn = true;
          $this->username_id = $login[1]['logged_in_user']['pk'];
          file_put_contents($this->IGDataPath.$this->username.'-userId.log', $this->username_id);
          $this->rank_token = $this->username_id.'_'.$this->uuid;
          preg_match('#Set-Cookie: csrftoken=([^;]+)#', $login[0], $match);
          $this->token = $match[1];
          file_put_contents($this->IGDataPath.$this->username.'-token.log', $this->token);

          return $login[1];
      }
  }
  
  public function timelineFeed()
  {
        return $this->request('feed/timeline/')[1];
  }
  
  public function getRecentActivity()
  {
      $activity = $this->request('news/inbox/?')[1];
      return $activity;
  }

  public function getUserId()
  {
      return $this->username_id;
  }
  
  public function getUserFeed($usernameId)
  {
      $userFeed = $this->request("feed/user/$usernameId/?rank_token=$this->rank_token&ranked_content=true&")[1];

      return $userFeed;
  }

  public function like($mediaId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        '_csrftoken' => $this->token,
        'media_id'   => $mediaId,
    ]);

      return $this->request("media/$mediaId/like/", $this->generateSignature($data))[1];
  }
  
  public function comment($mediaId, $commentText)
  {
      $data = json_encode([
        '_uuid'          => $this->uuid,
        '_uid'           => $this->username_id,
        '_csrftoken'     => $this->token,
        'comment_text'   => $commentText,
    ]);

      return $this->request("media/$mediaId/comment/", $this->generateSignature($data))[1];
  }

  public function follow($userId)
  {
      $data = json_encode([
        '_uuid'      => $this->uuid,
        '_uid'       => $this->username_id,
        'user_id'    => $userId,
        '_csrftoken' => $this->token,
    ]);

      return $this->request("friendships/create/$userId/", $this->generateSignature($data))[1];
  }

    public function generateSignature($data)
    {
        global $FADILUS;
        $hash = hash_hmac('sha256', $data, $FADILUS['ig'][4]);

        return 'ig_sig_key_version=4&signed_body='.$hash.'.'.urlencode($data);
    }

    public function generateDeviceId($seed)
    {
        $volatile_seed = filemtime(__DIR__);
        return 'android-'.substr(md5($seed.$volatile_seed), 16);
    }

    public function generateUUID($type)
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

        return $type ? $uuid : str_replace('-', '', $uuid);
    }

    protected function buildBody($bodies, $boundary)
    {
        $body = '';
        foreach ($bodies as $b) {
            $body .= '--'.$boundary."\r\n";
            $body .= 'Content-Disposition: '.$b['type'].'; name="'.$b['name'].'"';
            if (isset($b['filename'])) {
                $ext = pathinfo($b['filename'], PATHINFO_EXTENSION);
                $body .= '; filename="'.'pending_media_'.number_format(round(microtime(true) * 1000), 0, '', '').'.'.$ext.'"';
            }
            if (isset($b['headers']) && is_array($b['headers'])) {
                foreach ($b['headers'] as $header) {
                    $body .= "\r\n".$header;
                }
            }

            $body .= "\r\n\r\n".$b['data']."\r\n";
        }
        $body .= '--'.$boundary.'--';

        return $body;
    }

    protected function request($endpoint, $post = null, $login = false)
    {
        global $FADILUS;
        $headers = [
        'Connection: close',
        'Accept: */*',
        'Content-type: application/x-www-form-urlencoded; charset=UTF-8',
        'Cookie2: $Version=1',
        'Accept-Language: en-US',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $FADILUS['ig'][2].$endpoint);
        curl_setopt($ch, CURLOPT_USERAGENT, $FADILUS['ig'][3]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->IGDataPath."$this->username-cookies.log");
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->IGDataPath."$this->username-cookies.log");

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $resp = curl_exec($ch);
        $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($resp, 0, $header_len);
        $body = substr($resp, $header_len);

        curl_close($ch);

        return [$header, json_decode($body, true)];
    }
}
