<?php


namespace pixelwhiz\vanillaminecarts\items;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class MinecartHopper extends Item {

    public function __construct()
    {
        parent::__construct(new ItemIdentifier(408), "Minecart with Hopper", []);
    }

    public function getMaxStackSize(): int
    {
        return 1;
    }

}