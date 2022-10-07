<?php

declare(strict_types=1);

namespace itzdapakrep\floatingtext;

use pocketmine\plugin\PluginBase;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\utils\Config;

use itzdapakrep\floatingtext\EventListener;
use itzdapakrep\floatingtext\TaskScheduler;
use itzdapakrep\floatingtext\commands\FloatingTextCommands;

class FloatingText extends PluginBase {
	
	public function onEnable(){
		$this->getServer()->getCommandMap()->register("floatingtext", new FloatingTextCommands);
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		$this->data = new Config($this->getDataFolder() . "data.json", Config::JSON);
	}
	
	public function onLoading(){
		$this->saveResource("data.json");
	}
	
	public function getMessage(bool $message = ''){
		switch($message){
			case ft-no-permission:
				$message = "§cYou don't have permission to use this commands";
			break;
			case ft-create-success:
				$message = "§aFloatingText created";
			break;
			case ft-remove-success:
				$message = "§aFloatingText removed";
			break;
			default:
			break;
		}
	}
	
	public function updateFloatingText() : void {
		
	}
	
	public function addFloatingText($player){
		
	}
	
	public function removeFloatingText($player){
		
	}

}
