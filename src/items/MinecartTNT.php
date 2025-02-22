<?php


namespace pixelwhiz\vanillaminecarts\items;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class MinecartTNT extends Item {

    public function __construct()
    {
        parent::__construct(new ItemIdentifier(407), "Minecart with TNT", []);
    }

    public function getMaxStackSize(): int
    {
        return 1;
    }

}