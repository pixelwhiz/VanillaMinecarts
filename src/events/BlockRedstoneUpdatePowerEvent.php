<?php


namespace pixelwhiz\vanillaminecarts\events;

use pocketmine\block\Block;
use pocketmine\event\block\BlockEvent;

class BlockRedstoneUpdatePowerEvent extends BlockEvent {

    private bool $newPowered;
    private bool $powered;

    public function __construct(Block $block, bool $newPower, bool $powered) {
        parent::__construct($block);

        $this->newPowered = $newPower;
        $this->powered = $powered;
    }

    public function getNewPowered(): bool {
        return $this->newPowered;
    }

    public function setNewPowered(bool $powered): void {
        $this->newPowered = $powered;
    }

    public function getPowered(): bool {
        return $this->powered;
    }

}