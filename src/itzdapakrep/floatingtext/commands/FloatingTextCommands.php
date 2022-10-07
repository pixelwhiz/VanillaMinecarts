<?php

namespace itzdapakrep\floatingtext\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use itzdapakrep\floatingtext\FloatingText;
use pocketmine\command\SimpleCommandMap;
use pocketmine\command\ConsoleCommandSender;

class FloatingTextCommands extends Command {
	
	public function __construct(FloatingText $plugin){
		parent::__construct("floatingtext", "FloatingText commands", "§cUsage: /ft <create:delete:id>", ["ft"]);
		$this->plugin = $plugin;
	}
	
	
	public function execute(CommandSender $sender, string $label, array $args) {
		
		if(!$sender->hasPermission("ft.cmd")){
			$sender->sendMessage($this->plugin->getMessage("ft-no-permission"));
			return true;
		}
		
		if(!isset($args[0])) {
			$sender->sendMessage($this->getUsage());
			return true;
		}
		
		if(isset($args[0])){
			switch(strtolower($args[0])){
				case "create":
					$sender->sendMessage($this->plugin->getMessage("ft-create-success"));
				break;
				case "remove":
					$sender->sendMessage($this->plugin->getMessage("ft-remove-success"));
				break;
				case "id":
					
				break;
				default:
					$sender->sendMessage($this->getUsage());
				break;
			}
			return true;
		}
	}
}			
