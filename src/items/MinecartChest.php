<?php


namespace pixelwhiz\vanillaminecarts\items;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class MinecartChest extends Item {

    public function __construct()
    {
        parent::__construct(new ItemIdentifier(342), "Minecart with Chest", []);
    }

    public function getMaxStackSize(): int
    {
        return 1;
    }

}