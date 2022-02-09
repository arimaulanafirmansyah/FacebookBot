<?php  

/**
* Menu
*/
class Menu
{

	protected $menu = [
	'1' => 'Auto Reaction'
	];
	
	protected function ListMenu() {
		echo "Bot Tersedia ===>".PHP_EOL;
		foreach ($this->menu as $key => $value) {
			echo "[{$key}] {$value}".PHP_EOL;
		}
		echo PHP_EOL;
	}

	protected function Choice($login) {
		echo "Mau Make Bot Apa : ";
		$select = trim(fgets(STDIN));
		switch ($select) {
			case '1':
			require "modules/BotReaction.php";
			echo PHP_EOL;
			$reaction = new Reaction();
			$reaction->Start($login);
			break;

			default:
			echo "Pilihan tidak diketahui, ulangi".PHP_EOL;
			echo PHP_EOL;
			$this->Choice($login);
			break;
		}
	}

	public function Start($login)
	{

		$this->ListMenu();

		$choice = $this->Choice($login);

		return $choice;
	}
}