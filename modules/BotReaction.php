<?php  

/**
* Reaction
*/
class Reaction
{
	protected $userdata;	
	protected $userconfig;

	protected $filelog = "./storage/reaction-%s.json";		

	protected $sleep_noactivity = 600; // 10 minutes	
	protected $sleep_bot = 120; // 2 minutes	
	protected $delay_bot = 30; // delay per-process
	protected $delay_bot_default = 30; // delay default
	protected $delay_bot_count = 0; // dont change

	protected function FetchFeed($classBot)
	{

		echo "[*] Membaca Feed {$this->userconfig['target']}".PHP_EOL;

		$deep = false;
		$count = 0;
		$limit = 1;
		$all_data = array();

		do {

			if ($this->userconfig['target'] == 'Timeline') {
				$article = $classBot->FeedTimeLine($deep);
			}elseif ($this->userconfig['target'] == 'Group') {
				$article = $classBot->FeedGroup($this->userconfig['targetid'],$deep);
			}

			if (!$article['status']) {
				echo "[!] Error ".$article['response']." Break..".PHP_EOL;
				break;
			}

			$all_data = array_merge($all_data,$article['response']);

			if ($article['deep'] !== null) {
				$deep = $article['deep'];
			}else{
				$deep = false;
			}

			$count = $count+1;
		} while ($deep !== false AND $count < $limit);		

		if (is_array($all_data) and count($all_data) > 0) 
		{
			echo "[*] Sukses Mendapatkan Feed".PHP_EOL;
		}else{
			echo "[!] Gagal Mendapatkan Feed".PHP_EOL;
		}

		return $all_data;
	}

	protected function SendReaction($classBot,$postid,$is_comment = false){

		$posttype = ($is_comment == true) ? "Komentar" : "Post";

		$posturl = "https://www.facebook.com/{$postid}";

		/* sync react post with log file */
		$sync = self::SyncReact($postid);
		if ($sync) 
		{
			echo "[SKIP] React {$posttype} {$posturl} Sudah Diproses.".PHP_EOL;
			return false;
		}

		$reationrandom = ['LIKE', 'LOVE', 'CARE', 'WOW'];
		$type = ($this->userconfig['reaction'] == 'RANDOM') ? $reationrandom[array_rand($reationrandom)] : $this->userconfig['reaction'];

		echo "[*] Proses React {$type} {$postid}".PHP_EOL;

		$results = $classBot->SendReaction($postid,$type);

		if ($results['status'] != false) 
		{
			echo "[*] Berhasil React {$posttype} waktu [".date('d-m-Y H:i:s')."]".PHP_EOL;
			echo "{$posturl}".PHP_EOL;
			self::SaveLog($postid);
			return true;
		}else{

			if ($results['response'] == 'fail_get_url') 
			{
				echo "[!] Gagal Mendapatkan URL React".PHP_EOL;

			}elseif ($results['response'] == 'unreact') 
			{
				echo "[SKIP] React {$posttype} {$posturl} Sudah Diproses.".PHP_EOL;	
				self::SaveLog($postid);	
			}else{
				echo "[!] Gagal React {$posttype} Pada url {$posturl}".PHP_EOL;
			}
		}

		return false;
	}	

	public function Start($classBot)
	{

		echo "Menjalankan Bot Reaction".PHP_EOL;

		// configuration
		$this->userdata = $classBot->login;
		$setconfig = new ReactionConfiguration();	
		$this->userconfig = $setconfig->BuildConfiguration($classBot);	

		while (true) 
		{

			$FeedList = self::FetchFeed($classBot);

			echo PHP_EOL;

			$no_activity = true;
			foreach ($FeedList as $data) {

				$process_post = self::SendReaction($classBot,$data['postid']);

				echo PHP_EOL;

				if ($process_post) 
				{
					/* delay bot */
					self::DelayBot();

					$no_activity = false; /* activty detected */
				}

				/**
				 * COMMENT PROCESS
				 */
				if ($this->userconfig['comment'] == 'y') 
				{
					echo "[*] Membaca Komentar pada post {$data['postid']}".PHP_EOL;

					$comments = self::ReadComment($classBot,$data['postid']);
					if (!$comments) 
					{
						echo "[*] Tidak ada komentar pada post {$data['postid']}".PHP_EOL;
						continue;
					}

					foreach ($comments as $comment) 
					{

						$process_comment = self::SendReaction($classBot,$comment['commentid'],true);

						if ($process_comment) 
						{
							/* delay bot */
							self::DelayBot();

							$no_activity = false;  /* activty detected  */
						}

						/* process react reply comment if exist */
						if ($comment['reply']) {

							foreach ($comment['reply'] as $commentreply) {

								$process_comment = self::SendReaction($classBot,$commentreply['commentid'],true);

								if ($process_comment) 
								{
									/* delay bot */
									self::DelayBot();

									$no_activity = false; /* activty detected */
								}
							}

						}

					}
				}
			}

			if ($no_activity) 
			{
				echo "[*] Tidak ditemukan Post, Coba lagi setelah {$this->sleep_noactivity} detik".PHP_EOL;
				sleep($this->sleep_noactivity);
				continue;
			}else{
				echo "[*] Delay Selama {$this->sleep_bot} detik, menghindari aktivitas mencurigakan".PHP_EOL;
				sleep($this->sleep_bot);
				continue;				
			}

		}	
	}

	/**
	 * Read Comment
	 */
	public function ReadComment($classBot,$postid)
	{

		$deep = false;
		$count = 0;
		$limit = 1;
		$all_data = array();
		do {

			$data = $classBot->ReadComment($postid,$deep);

			if (!$data['status']) {
				echo "Error ".$data['response']." Reprocess Again...";
				break;
			}

			$all_data = array_merge($all_data,$data['response']);

			if ($data['deep'] !== null) {
				$deep = $data['deep'];
			}else{
				$deep = false;
			}

			$count = $count+1;
		} while ($deep !== false AND $count < $limit);

		$comment_data = array();
		foreach ($all_data as $comment) {

			if ($comment['reply_url']) {

				$deep = false;
				$count = 0;
				$limit = 1;
				do {

					$data = $classBot->ReadCommentReply($comment['reply_url'],$deep);

					if (!$data['status']) {
						echo "Error ".$data['response']." Reprocess Again...";
						break;
					}

					$comment_data[] = [
					'username' => $comment['username'],
					'commentid' => $comment['commentid'],
					'reply' => $data['response']
					];

					if ($data['deep'] !== null) {
						$deep = $data['deep'];
					}else{
						$deep = false;
					}

					$count = $count+1;
				} while ($deep !== false AND $count < $limit);

			}else{
				$comment_data[] = [
				'username' => $comment['username'],
				'commentid' => $comment['commentid'],
				'reply' => false
				];
			}

		}		

		if (count($comment_data) < 1) {
			return false;
		}

		return $comment_data;
	}	

	/**
	 * Delay
	 */

	public function DelayBot()
	{

		/* reset sleep value to default */
		if ($this->delay_bot_count >= 5) {
			$this->delay_bot = $this->delay_bot_default;
			$this->delay_bot_count = 0;
		}	

		echo "[*] Delay {$this->delay_bot}".PHP_EOL;
		sleep($this->delay_bot);
		$this->delay_bot = $this->delay_bot+5;
		$this->delay_bot_count++;
	}

	/**
	 * Sync 
	 */
	protected function SyncReact($postid)
	{

		$ReadLog = self::ReadLog();

		if (is_array($ReadLog) AND in_array($postid, $ReadLog)) 
		{
			return true;
		}

		return false;
	}

	protected function LogFileName()
	{
		if (in_array($this->userconfig['target'], ['Group'])) {
			$filetargetreaction = $this->userconfig['target']."-".$this->userconfig['targetid'];
		}else{
			$filetargetreaction = $this->userconfig['target'];
		}

		$filelog = "{$filetargetreaction}-{$this->userdata['username']} ({$this->userdata['userid']})";

		return sprintf($this->filelog,$filelog);
	}

	protected function ReadLog()
	{		

		$logfilename = $this->LogFileName();
		$log_id = array();
		if (file_exists($logfilename)) 
		{
			$log_id = file_get_contents($logfilename);
			$log_id  = explode(PHP_EOL, $log_id);
		}

		return $log_id;
	}

	protected function SaveLog($data)
	{

		$logfilename = $this->LogFileName();
		return file_put_contents($logfilename, $data.PHP_EOL, FILE_APPEND);
	}			
}

class ReactionConfiguration
{

	protected $userdata;
	protected $userconfig;

	public $fileconfig = "./storage/reaction.json";	

	public function InputTargetReaction($reinput = false) 
	{

		if (!$reinput) {
			echo PHP_EOL."[?] Target Reaction : ".PHP_EOL;
			echo "[1] Feed Timeline".PHP_EOL;
			echo "[2] Feed Group".PHP_EOL;	
		}

		echo PHP_EOL;

		echo "[?] Pilihan anda(Angka) : ";

		$input = trim(fgets(STDIN));

		if (!in_array(strtolower($input),['1','2'])) 
		{
			echo "Pilihan tidak diketahui, ulangi".PHP_EOL;
			return self::InputTargetReaction(true);
		}

		if ($input == '1') {
			$input = 'Timeline';
		}elseif($input == '2'){
			$input = 'Group';
		}

		return $input;
	}

	public function InputGroupName() {

		echo "[?] Cari Nama Group (karakter): ";

		$input = trim(fgets(STDIN));

		if(!$input) {
			echo "Nama Group Masih Kosong, ulangi".PHP_EOL;
			self::InputGroupName();
		}

		return $input;
	}	

	public function InputChoiceGroup() {

		echo "[?] Masukan Group yang dipilih (angka): ";

		$input = trim(fgets(STDIN));

		if (strval($input) !== strval(intval($input))) {
			echo "Salah memasukan format, ulangi".PHP_EOL;
			self::InputChoiceGroup();
		}

		return $input;
	}	

	public function ChoiceGroup($classBot)
	{
		echo "[*] Mendapatkan List Group".PHP_EOL;

		$results = $classBot->ListGroupJoined();
		if (!$results['status']) {die($results['response']);}

		echo "[*] Ditemukan ".count($results['response'])." Group".PHP_EOL;

		$search = self::InputGroupName();

		$search_results = array();
		foreach ($results['response'] as $key => $group) {
			if (preg_match("/{$search}/i", $group['name'])) {
				$search_results[] = "[{$key}] ".$group['name'].PHP_EOL;
			}
		}

		if (!$search_results) {
			echo "[*] Group tidak ditemukan, ulangi".PHP_EOL;
			self::ChoiceGroup($classBot);
		}else{
			echo PHP_EOL."[*] Daftar Group yang ditemukan : ".PHP_EOL;			
			echo implode('', $search_results);
		}

		$choice = self::InputChoiceGroup();
		$choice =  $results['response'][$choice]['url'];

		// get groupid
		$groupid =  $classBot->GetGroupID($choice);
		if (!$groupid['status']) {die($groupid['response']);}

		return $groupid['response'];
	}	

	public function InputReactType() 
	{
		echo "[?] Daftar React yang ada [LIKE, LOVE, CARE, HAHA, WOW, SAD, ANGRY, RANDOM]".PHP_EOL;

		echo "[?] Pilihan anda : ";

		$input = strtoupper(trim(fgets(STDIN)));

		$react = ['LIKE', 'LOVE', 'CARE', 'HAHA', 'WOW', 'SAD', 'ANGRY', 'RANDOM'];

		if (!in_array($input,$react)) 
		{
			echo "Pilihan tidak diketahui, ulangi".PHP_EOL;
			self::InputReactType();
		}

		return (!$input) ? die('Reaction Masih Kosong'.PHP_EOL) : $input;
	}

	public function InputReactComment() 
	{

		echo "[?] React Comment Juga ? (y/n): ";

		$input = trim(fgets(STDIN));

		if (!in_array(strtolower($input),['y','n'])) 
		{
			echo "Pilihan tidak diketahui, ulangi".PHP_EOL;
			self::InputReactComment();
		}

		return (!$input) ? die('Pilihan masih Kosong'.PHP_EOL) : $input;
	}	

	public function SaveConfiguration($data){

		$filename = $this->fileconfig;

		if (file_exists($filename)) {
			$read = file_get_contents($filename);
			$read = json_decode($read,true);
			$dataexist = false;
			foreach ($read as $key => $logdata) {
				if ($logdata['userid'] == $data['userid']) {
					$inputdata[] = $data;
					$dataexist = true;
				}else{
					$inputdata[] = $logdata;
				}
			}

			if (!$dataexist) {
				$inputdata[] = $data;
			}
		}else{
			$inputdata[] = $data;
		}

		return file_put_contents($filename, json_encode($inputdata,JSON_PRETTY_PRINT));
	}

	public function ReadConfiguration($userid)
	{

		$filename = $this->fileconfig;

		if (file_exists($filename)) {

			$inputdata = false;
			$read = file_get_contents($filename);
			$read = json_decode($read,TRUE);
			foreach ($read as $key => $logdata) {
				if ($logdata['userid'] == $userid) {
					$inputdata = $logdata;
				}
			}

			return $inputdata;
		}else{
			return false;
		}
	}		

	public function ReloadConfiguration($data)
	{
		$this->userconfig['target'] = $data['target'];
		$this->userconfig['targetid'] = $data['targetid'];		
		$this->userconfig['reaction'] = $data['reaction'];
		$this->userconfig['comment'] = $data['comment'];
	}

	public function CreateConfiguration($classBot)
	{

		$this->userconfig['target'] = self::InputTargetReaction();

		if($this->userconfig['target'] == 'Group'){
			$this->userconfig['targetid'] = self::ChoiceGroup($classBot);
		}else {
			$this->userconfig['targetid'] = false;
		}

		$this->userconfig['reaction'] = self::InputReactType();
		$this->userconfig['comment'] = self::InputReactComment();

		$save_config = [
		'userid' => $this->userdata['userid'],
		'username' => $this->userdata['username'],
		'target' => $this->userconfig['target'],
		'targetid' => $this->userconfig['targetid'],		
		'reaction' => $this->userconfig['reaction'],
		'comment' => $this->userconfig['comment'],		
		];

		/* save new config data */
		self::SaveConfiguration($save_config);
	}			

	public function BuildConfiguration($classBot)
	{

		$this->userdata = $classBot->login;

		if ($check = self::ReadConfiguration($this->userdata['userid'])){

			echo "[?] Anda Memiliki konfigurasi yang tersimpan, gunakan kembali (y/n) : ";

			$reuse = trim(fgets(STDIN));

			if (!in_array(strtolower($reuse),['y','n'])) {
				echo "Pilihan tidak diketahui, ulangi".PHP_EOL;
				echo PHP_EOL;
				return self::BuildConfiguration($classBot);
			}

			if ($reuse == 'y') {
				self::ReloadConfiguration($check);
			}else{
				self::CreateConfiguration($classBot);
			}
		}else{
			self::CreateConfiguration($classBot);
		}

		return $this->userconfig;
	}
}