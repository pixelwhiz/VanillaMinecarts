<?php

declare(strict_types=1);

namespace libs\muqsit\invmenu\type;

use libs\muqsit\invmenu\InvMenu;
use libs\muqsit\invmenu\type\graphic\InvMenuGraphic;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

interface InvMenuType{

	public function createGraphic(InvMenu $menu, Player $player) : ?InvMenuGraphic;

	public function createInventory() : Inventory;
}