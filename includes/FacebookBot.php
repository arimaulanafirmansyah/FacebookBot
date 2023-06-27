<?php 

/**
* FacebookBot Class
* 9 Februari 2022
* Made by FaanTeyki
*/
class FacebookBot
{

	protected $base = "https://mbasic.facebook.com/";
	protected $apibase = "https://graph.facebook.com/";
	protected $debug = true;

	protected $headers = [
		'Authority: mbasic.facebook.com',
		'Cache-Control: max-age=0',
		'Sec-Ch-Ua: ?0',
		'Sec-Ch-Ua-Mobile: ?0',
		'Sec-Ch-Ua-Platform: Windows',
		'Upgrade-Insecure-Requests: 1',
		'User-Agent: Mozilla/5.0 (Windows NT 6.1, Win64, x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.99 Safari/537.36',
		'Sec-Fetch-Site: none',
		'Sec-Fetch-Mode: navigate',
		'Sec-Fetch-User: ?1',
		'Sec-Fetch-Dest: document',
		'Accept-Language: en-GB,en-US,q=0.9,en,q=0.8,id,q=0.7'
	];

	public $login = [];

	protected $proxy = false;

	function __construct($data = []) 
	{
		if (array_key_exists('cookie', $data)) {
			$this->headers = array_merge($this->headers, ['Cookie: '.$data['cookie']]);
		}else {
			die("cookie not set, how you to login ?");
		}

		if (array_key_exists('proxy', $data)) {
			$this->proxy = $data['proxy'];
		}

		if (!array_key_exists('bypass', $data)) {
			$this->Auth();
		}
	}
	
	/**
	 * Helper
	 */
	protected function Fetch($url, $postdata = 0, $header = 0, $cookie = 0, $useragent = 0, $proxy = array(), $followlocation = 0) 
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followlocation);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, false);
		curl_setopt($ch, CURLOPT_HEADER, 1);

		// for facebook url & api
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

		if($header) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		}

		if($postdata) {
			curl_setopt($ch, CURLOPT_POST, 1);
			if ($postdata != 'empty') {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
			}
		}

		if($cookie) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		}

		if ($useragent) {
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		}

		if (!empty($proxy['proxy']['ip'])){
			curl_setopt($ch, CURLOPT_PROXY, $proxy['proxy']['ip']);
		}

		if (!empty($proxy['proxy']['userpwd'])){
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy['proxy']['userpwd']);
		}

		if (!empty($proxy['proxy']['socks5'])){
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		}

		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch);

		if (curl_errno($ch)) {
			return [
				'status' => false,
				'response' => 'Connection 404'
			];
		}		

		if(!$httpcode) 
		{
			curl_close($ch);	
			
			return [
				'status' => false,
				'response' => 'HttpCode 404'
			];
		}
		else
		{
			$header = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
			$body = substr($response, curl_getinfo($ch, CURLINFO_HEADER_SIZE));

			curl_close($ch);

			if (!$httpcode['http_code']) {
				return [
					'status' => false,
					'response' => 'HttpCode 404'
				];
			}elseif (strstr($header, 'login.php') or $httpcode['http_code'] == '404') {
				return [
					'status' => false,
					'response' => 'Has Kick to Lobby'
				];
			}

			return [
				'status' => true,
				'header' => $header,
				'body' => $body,
				'code' => $httpcode['http_code']
			];
		}
	}	

	protected function FindStringOnArray($arr, $string) 
	{
		return array_filter($arr, function($value) use ($string) {
			return strstr($value, $string) !== false;
		});
	}

	protected function GetStringBetween($string,$start,$end)
	{
		$str = explode($start,$string);
		if (empty($str[1])) return false;
		$str = explode($end,$str[1]);
		return $str[0];
	}

	protected function innerHTML($node)
	{
		$doc = new \DOMDocument();
		foreach ($node->childNodes as $child) 
		{
			$doc->appendChild($doc->importNode($child, true));
		}
		return $doc->saveHTML();
	}	

	protected function GetDom($html)
	{

		$previous_value = libxml_use_internal_errors(TRUE);
		$dom = new \DOMDocument;
		$dom->loadHTML($html);
		libxml_clear_errors();
		libxml_use_internal_errors($previous_value);

		return $dom;
	}

	protected function GetXpath($dom)
	{

		$xpath = new \DOMXPath($dom);
		return $xpath;
	}

	protected function BoundaryBuilder($delimiter, $postFields, $fileFields = array())
	{
	    // form field separator
		$eol = "\r\n";
		$data = '';
	    // populate normal fields first (simpler)
		foreach ($postFields as $name => $content) {
			$data .= "--$delimiter" . $eol;
			$data .= 'Content-Disposition: form-data; name="' . $name . '"';
	        $data .= $eol.$eol; // note: double endline
	        $data .= $content;
	        $data .= $eol;
	    }
	    // populate file fields
	    foreach ($fileFields as $name => $file) {
	    	$data .= "--$delimiter" . $eol;
	        // fallback on var name for filename
	    	if (!array_key_exists('filename', $file))
	    	{
	    		$file['filename'] = $name;
	    	}
	        // "filename" attribute is not essential; server-side scripts may use it
	    	$data .= 'Content-Disposition: form-data; name="' . $name . '";' .
	    	' filename="' . $file['filename'] . '"' . $eol;
	        // this is, again, informative only; good practice to include though
	    	$data .= 'Content-Type: ' . $file['type'] . $eol;
	        // this endline must be here to indicate end of headers
	    	$data .= $eol;
	        // the file itself (note: there's no encoding of any kind)
	    	if (is_resource($file['content'])){
	            // rewind pointer
	    		rewind($file['content']);
	            // read all data from pointer
	    		while(!feof($file['content'])) {
	    			$data .= fgets($file['content']);
	    		}
	    		$data .= $eol;
			// check if we are loading a file from full path	    		
	    	}elseif (strpos($file['content'], '@') === 0){
	    		$file_path = substr($file['content'], 1);
	    		$fh = fopen(realpath($file_path), 'rb');
	    		if ($fh) {
	    			while (!feof($fh)) {
	    				$data .= fgets($fh);
	    			}
	    			$data .= $eol;
	    			fclose($fh);
	    		}

	    	// check if a url read data 
	    	}elseif (filter_var($file['content'], FILTER_VALIDATE_URL)){
	    		$file = fopen($file['content'], 'rb');
	    		$bytefile = stream_get_contents($file);
	    		fclose($file);
	    		$data .= $bytefile . $eol;
	    	}else {
            	// use data as provided
	    		$data .= $file['content'] . $eol;
	    	}
	    }
	    // last delimiter
	    $data .= "--" . $delimiter . "--$eol";
	    return $data;
	}	

	protected function ReadCookie($response) 
	{

		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $results);
		$cookies = '';
		for($o = 0; $o < count($results[0]); $o++){
			$cookies.=$results[1][$o].";";
		}

		if (!$cookies) return false;

		return $cookies;
	}	

	protected function GetInputValue($response) 
	{

		$pattern_input = '/<input.*?name="(.*?)".*?value="(.*?)".*?>/';
		preg_match_all($pattern_input, $response, $matches);

		if (empty($matches)) {
			return [
				'status' => false,
				'response' => 'not_found_required'
			];
		}

		$params = array();
		foreach ($matches[1] as $index => $key) {
			$params[$key] = $matches[2][$index];
		}

		if (count($params) > 0) {
			return [
				'status' => true,
				'response' =>  $params,
				'matches' => $matches
			];
		}

		return [
			'status' => false,
			'response' => 'params_nothing'
		];
	}	


	/**
	 * Auth
	 */

	protected function Auth()
	{

		if ($this->debug) echo "Validate Cookie".PHP_EOL;

		$connect = $this->Fetch($this->base, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {
			die($connect['response']);
		}

		if (!strstr($connect['body'], 'mbasic_logout_button')) {
			die("Cookie Invalid");	
		}

		if ($this->debug) echo "Cookie Valid".PHP_EOL;	

		$this->GetAccessToken();
		$this->GetUserInfoUseToken();
	}

	protected function GetAccessToken()
	{

		if ($this->debug) echo "Search AccessToken".PHP_EOL;

		$url = "https://business.facebook.com/business_locations/?nav_source=flyout_menu";
		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {
			die($connect['response']);
		}
		$response = $connect['body'];
		$accesstoken = null;

		if (preg_match('/"EAAG[^"]+"/', $response, $matches)) {
    	$accesstoken = trim($matches[0], '"');
		}

		if ($accesstoken) {
   		echo "Access Token Found\n";
		} else {
    	die("Error, Get Ulang Cookie!'");
		}
		
		// $accesstoken = $this->GetStringBetween($connect['body'],'"userAccessToken":"','","rightsManagerVersion"');
		// if (!$accesstoken) {
		// 	die("Can't Get AccessToken");
		// }

		// if ($this->debug) echo "AccessToken Found".PHP_EOL;

		$this->login = [
			'accesstoken' => $accesstoken
		];
	}	

	protected function GetUserInfoUseToken()
	{

		if ($this->debug) echo "Get User Information".PHP_EOL;

		$url = $this->apibase."me?fields=name,picture.type(large)&access_token=".$this->login['accesstoken'];

		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {
			die($connect['response']);
		}

		$response = json_decode($connect['body'],true);

		if (array_key_exists('error', $response)) {

			if ($this->debug) {
				echo "Fail Get User Information".PHP_EOL;
			}

			die($response['error']['message']);

		}else{

			if ($this->debug) echo "Success Get User Information".PHP_EOL;

			$this->login = array_merge($this->login, [
				'userid' => $response['id'],
				'username' => $response['name'],
				'photo' => $response['picture']['data']['url']
			]);

			if ($this->debug) echo "Welcome {$response['name']}".PHP_EOL;
		}
	}

	/**
	 * Feed
	 */

	public function FeedTimeLine($deep = false)
	{

		if ($deep) {
			$url = $this->base.$deep;
		}else{
			$url = $this->base."stories.php";
		}

		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}

		$dom = $this->GetDom($connect['body']);
		$xpath = $this->GetXpath($dom);		

		$GetDeepURL = $xpath->query('//*[@id="root"]/div/a/@href');

		$deep = false;
		if ($GetDeepURL->length > 0) {
			$deep = $GetDeepURL[0]->value;
		}

		return [
			'status' => true,
			'response' => $this->ExtractFeed($xpath->query('//*[@id="root"]/div/section/article')),
			'deep' => $deep
		];
	}

	public function FeedGroup($groupid, $deep = false)
	{

		if ($deep) {
			$url = $this->base."groups/".$deep;
		}else{
			$url = $this->base."groups/".$groupid;
		}

		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}

		$dom = $this->GetDom($connect['body']);
		$xpath = $this->GetXpath($dom);		

		$GetDeepURL = $xpath->query('//*[@id="m_group_stories_container"]/div/a/@href');

		$deep = false;
		if ($GetDeepURL->length > 0) {
			$deep = $GetDeepURL[0]->value;
		}

		return [
			'status' => true,
			'response' => $this->ExtractFeed($xpath->query('//div[@id="m_group_stories_container"]/section/article')),
			'deep' => $deep
		];
	}

	protected function ExtractFeed($ArticleList)
	{

		$extract = array();
		if($ArticleList->length > 0) 
		{
			foreach ($ArticleList as $node) 
			{

				$jsonattr = $node->getAttribute('data-ft');
				$readjson = json_decode($jsonattr);
				
				if(empty($readjson->top_level_post_id) OR empty($readjson->content_owner_id_new)) {
					continue;
				}

				$postid = $readjson->top_level_post_id;
				$userid = $readjson->content_owner_id_new;

				$extract[] = [
					'userid' => $userid,
					'postid' => $postid
				];
			}
		}

		return $extract;
	}


	/**
	 * Reaction
	 */

	protected function GetReactionURL($postid,$reaction) {

		$url = $this->base."reactions/picker/?is_permalink=1&ft_id=".$postid;

		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}

		$response = $connect['body'];

		$dom = $this->GetDom($response);
		$xpath = $this->GetXpath($dom);

		$XpathReactlionlist = $xpath->query('//li/table/tbody/tr/td/a/@href');

		if($XpathReactlionlist->length > 0) 
		{
			$reaction_data = array();
			foreach ($XpathReactlionlist as $node) 
			{
				$url = $this->InnerHTML($node);
				$url = "https://mbasic.facebook.com".$url;

				if (!strstr($url, '/story.php')) 
				{
					$type = self::ConvertReact($url);
					$reaction_data[$type] = html_entity_decode(trim($url));
				}
			}

			if ((!empty($reaction_data[$reaction]))) 
			{
				return [
					'status' => true,
					'response' => $reaction_data[$reaction]
				];
			}else{
				return [
					'status' => false,
					'response' => 'unreact'
				];
			}
		}

		return [
			'status' => false,
			'response' => 'fail_get_xpath'
		];
	}

	public function SendReaction($postid,$reaction) 
	{

		$url = $this->GetReactionURL($postid,$reaction);
		if (!$url['status']) {return $url;}

		$connect = $this->Fetch($url['response'], false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}

		if (strstr($connect['header'], '200'))
		{
			return [
				'status' => true,
				'response' => 'success_react'
			];
		}else{
			return [
				'status' => false,
				'response' => 'failed_react'
			];
		}

	}

	public function ConvertReact($url)
	{

		$type = false;
		if (strstr($url, 'reaction_type=1&')) 
		{
			$type = 'LIKE';
		}elseif (strstr($url, 'reaction_type=2&')) 
		{
			$type = 'LOVE';
		}elseif (strstr($url, 'reaction_type=16&')) 
		{
			$type = 'CARE';
		}elseif (strstr($url, 'reaction_type=4&')) 
		{
			$type = 'HAHA';
		}elseif (strstr($url, 'reaction_type=3&')) 
		{
			$type = 'WOW';
		}elseif (strstr($url, 'reaction_type=7&')) 
		{
			$type = 'SAD';
		}elseif (strstr($url, 'reaction_type=8&')) 
		{
			$type = 'ANGRY';
		}elseif (strstr($url, 'reaction_type=0&')) 
		{
			$type = 'UNREACT';
		}

		return $type;
	}

	/**
	 * List Group
	 */

	public function ListGroupJoined() {

		$url = $this->base."groups/?seemore&refid=27";

		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}	

		$dom = $this->GetDom($connect['body']);
		$xpath = $this->GetXpath($dom);

		$GroupList = $xpath->query('//ul/li/table/tbody/tr/td[1]/a');

		// echo $GroupList->length.PHP_EOL;

		$extract = array();
		if($GroupList->length > 0) 
		{
			foreach ($GroupList as $link) 
			{
				$href = $link->getAttribute('href');
				$id = $this->GetStringBetween($href,'/groups/','/?refid=27');
				$name = $link->nodeValue;

				$extract[] = [
					'id' => $id,
					'name' => $name,
					'url' => $href
				];
			}
		}else{
			return [
				'status' => false,
				'response' => 'groups_not_found'
			];
		}

		if (count($extract) > 0) {
			return [
				'status' => true,
				'response' => $extract
			];
		}

		return [
			'status' => false,
			'response' => 'group_not_found'
		];
	}

	public function GetGroupID($url) 
	{

		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}	

		$id = $this->GetStringBetween($connect['body'],'group_id=','&');

		if (is_numeric($id)) {
			return [
				'status' => true,
				'response' => $id
			];			
		}

		return [
			'status' => false,
			'response' => 'fail_get_groupid'
		];
	}

	/**
	 * Comment
	 */
	public function ReadComment($postid,$deep = false) 
	{
		if ($deep) {
			$url = $this->base.$deep;
		}else{
			$url = $this->base.$postid;
		}
		
		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy, true);
		if (!$connect['status']) {return $connect;}		

		$dom = $this->GetDom($connect['body']);
		$xpath = $this->GetXpath($dom);

		$GetDeepURL = $xpath->query('//div[@id="ufi_'.$postid.'"]/div/div[4]/div[contains(@id,"see_prev")]/a/@href');

		$deep = false;
		if ($GetDeepURL->length > 0) {
			$deep = $GetDeepURL[0]->value;
		}

		/**
		 * Extract
		 */
		$XpathCommentList = $xpath->query('//div[@id="ufi_'.$postid.'"]/div/div[4]/div');
		$extract = array();
		if($XpathCommentList->length > 0) 
		{

			foreach ($XpathCommentList as $key => $node) 
			{

				$commentid = $node->getAttribute('id');

				if (is_numeric($commentid)) {

					$build_commentid = "{$postid}_{$commentid}";
					$profilexpath = $xpath->query('//div[@id="'.$commentid.'"]/div/h3/a',$node)[0];
					$username = $profilexpath->nodeValue;

					$reactionxpath = $xpath->query('//div[@id="'.$commentid.'"]/div/div[3]/span[1]/span/a[2]',$node)[0];

					// if ($reactionxpath->length > 0) {
					// 	$userid = $this->GetStringBetween($reactionxpath->getAttribute('href'),'av=','&');
					// }else{
					// 	$userid = false;
					// }

					$CheckReplyTag = $xpath->query('//div[contains(@id,"'.$build_commentid.'")]/div/a',$node);

					$reply = false;
					if ($CheckReplyTag->length > 0) {
						$reply = $CheckReplyTag[0]->getAttribute('href');
					}

					$extract[] = [
						// 'userid' => $userid,
						'username' => $username,
						'commentid' => $build_commentid,
						'reply_url' => $reply
					];

				}

			}
		}

		return [
			'status' => true,
			'response' => $extract,
			'deep' => $deep
		];
	}

	public function ReadCommentReply($reply_url,$deep = false) 
	{
		if ($deep) {
			$url = $this->base.$deep;
		}else{
			$url = $this->base.$reply_url;
		}
		
		$connect = $this->Fetch($url, false , $this->headers , false, false, $this->proxy);
		if (!$connect['status']) {return $connect;}		

		$dom = $this->GetDom($connect['body']);
		$xpath = $this->GetXpath($dom);

		$GetDeepURL = $xpath->query('/html/body/div/div/div[2]/div/div[1]/div[2]/div[1]/a/@href');

		$deep = false;
		if ($GetDeepURL->length > 0) {
			$deep = $GetDeepURL[0]->value;
		}

		/**
		 * Extract
		 */
		$XpathCommentList = $xpath->query('//div[@id="objects_container"]/div/div[1]/div[2]/div');
		$extract = array();
		if($XpathCommentList->length > 0) 
		{

			foreach ($XpathCommentList as $ked => $node) 
			{

				$commentid = $node->getAttribute('id');

				if (is_numeric($commentid)) {
					$build_commentid = "{$commentid}";
					$profilexpath = $xpath->query('//div[@id="'.$commentid.'"]/div/h3/a',$node)[0];
					$username = $profilexpath->nodeValue;

					$reactionxpath = $xpath->query('//div[@id="'.$commentid.'"]/div/div[3]/span[1]/span/a[2]',$node)[0];

					// if ($reactionxpath->length > 0) {
					// 	$userid = $this->GetStringBetween($reactionxpath->getAttribute('href'),'av=','&');
					// }else{
					// 	$userid = false;
					// }

					$extract[] = [
						// 'userid' => $userid,					
						'username' => $username,
						'commentid' => $build_commentid
					];
				}
			}
		}

		return [
			'status' => true,
			'response' => $extract,
			'deep' => $deep
		];

	}
}