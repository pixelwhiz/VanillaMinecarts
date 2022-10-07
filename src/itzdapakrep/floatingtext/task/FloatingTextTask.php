<?php

declare(strict_types=1);

namespace itzdapakrep\floatingtext;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\scheduler\Task;

class FloatingTextTask extends Task {
	
	public function __construct(FloatingText $plugin){
		$this->plugin = $plugin;
	}
	
	public function onRun($tick){
		$this->plugin->updateFloatingText();
	}

}
