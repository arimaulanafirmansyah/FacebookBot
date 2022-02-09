<?php 

/**
* Auth
*/
Class Auth 
{

	public $filesavedata = './storage/user.json';

	public function isJson($string) {
		json_decode($string);
		return json_last_error() === JSON_ERROR_NONE;
	}

	public function InputNewCookie() 
	{

		echo "[?] Masuk menggunakan Cookie (text/json) : ".PHP_EOL;

		$input = trim(fgets(STDIN));

		// convert cookie json to cookie format
		if ($this->isJson($input)) 
		{			

			$jsoncookie = json_decode($input,TRUE);

			$cookies = '';
			foreach ($jsoncookie as $read) {
				$cookies .= "{$read['name']}={$read['value']};";
			}

			return $cookies;
		} 

		if (empty($input)) {
			echo "[!] Cookie masih kosong, ulangi".PHP_EOL;
			return $this->InputNewCookie();
		}

		return $input;
	}	

	public function InputChoicePreviousCookie($data_cookie) 
	{

		echo PHP_EOL."[?] Pilihan Anda (angka/x): ";

		$input = strtolower(trim(fgets(STDIN)));

		if (!array_key_exists($input, $data_cookie) AND strtolower($input) != 'x') 
		{
			echo "[!] Pilihan tidak diketahui, ulangi".PHP_EOL;
			return $this->InputChoicePreviousCookie($data_cookie);
		}

		return $input;
	}		

	public function SaveUser($data){

		$filename = $this->filesavedata;

		if (file_exists($filename)) 
		{
			$read = file_get_contents($filename);
			$read = json_decode($read,true);
			$dataexist = false;
			foreach ($read as $key => $logdata) 
			{
				if ($logdata['userid'] == $data['userid']) 
				{
					$inputdata[] = $data;
					$dataexist = true;
				}else{
					$inputdata[] = $logdata;
				}
			}

			if (!$dataexist) 
			{
				$inputdata[] = $data;
			}
		}else{
			$inputdata[] = $data;
		}

		return file_put_contents($filename, json_encode($inputdata,JSON_PRETTY_PRINT));
	}

	public function ReadPreviousUser()
	{

		$filename = $this->filesavedata;

		if (file_exists($filename)) 
		{
			$read = file_get_contents($filename);
			$read = json_decode($read,TRUE);
			foreach ($read as $key => $logdata) 
			{
				$inputdata[] = $logdata;
			}

			return $inputdata;
		}else{
			return false;
		}
	}

	public function ReadUser($data)
	{

		$filename = $this->filesavedata;

		$read = file_get_contents($filename);
		$read = json_decode($read,TRUE);
		foreach ($read as $key => $logdata) 
		{
			if ($key == $data) 
			{
				$inputdata = $logdata;
				break;
			}
		}

		return $inputdata;
	}	

	public function Login($key = "")
	{

		if ($key or $key == "0") {
			$results = $this->ReadUser($key);
			$cookie = $results['cookie'];
		}else{
			$cookie = $this->InputNewCookie();		
		}

		echo PHP_EOL;

		$bot = new FacebookBot([
			'cookie' => $cookie
		]);

		echo PHP_EOL;

		echo "[*] Menyimpan Data Login".PHP_EOL;

		echo PHP_EOL;

		$save = array_merge($bot->login,['cookie' => $cookie,'last_login' => date('d-m-Y H:i:s')]);
		$this->SaveUser($save);

		return $bot;
	}

	public function Start($reauth = false)
	{	

		if ($check = $this->ReadPreviousUser() AND !$reauth) {
			echo PHP_EOL."[?] Anda Memiliki Cookie yang tersimpan pilih angkanya dan gunakan kembali : ".PHP_EOL;

			foreach ($check as $key => $cookie) 
			{
				echo "[{$key}] ".$cookie['username'].PHP_EOL;
				$data_cookie[] = $key;
			}

			echo "[x] Masuk menggunakan akun baru".PHP_EOL;

			$input = $this->InputChoicePreviousCookie($data_cookie);

			if (strtolower((string)$input) == 'x') {
				return $this->Login();
			}elseif (array_key_exists($input, $data_cookie) AND strtolower($input) != 'x') { 
				return $this->Login($input);
			}

		}else{
			return $this->Login();
		}
	}
}