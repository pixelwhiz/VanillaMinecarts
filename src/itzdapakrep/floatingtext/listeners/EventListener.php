<?php

declare(strict_types=1);

namespace itzdapakrep\floatingtext;

use pocketmine\plugin\PluginBase;

use pocketmine\Player;
use pocketmine\Server;

## MY LIBS ##
use itzdapakrep\floatingtext\FloatingText;
## MY LIBS ##

use pocketmine\event\player\PlayerJoinEvent;

class EventListener implements Listener {
	
	public function __construct(FloatingText $plugin){
		$this->plugin = $plugin;
	}
	
	public function onJoin(PlayerJoinEvent $event){
		if(count($this->getServer()->getOnlinePlayers() <= 0)) $this->plugin->getLogger()->info("All floating text schedulers will be stopped to eliminate lag on the server.");
	}

}
