<?php


namespace pixelwhiz\vanillaminecarts\blocks\power;

use pixelwhiz\vanillaminecarts\blocks\BlockPowerHelper;
use pixelwhiz\vanillaminecarts\blocks\BlockUpdateHelper;
use pixelwhiz\vanillaminecarts\blocks\ILinkRedstoneWire;
use pixelwhiz\vanillaminecarts\blocks\IRedstoneComponent;
use pixelwhiz\vanillaminecarts\blocks\LinkRedstoneWireTrait;
use pixelwhiz\vanillaminecarts\events\BlockRedstoneUpdatePowerEvent;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\player\Player;

class BlockRedstoneTorch extends RedstoneTorch implements IRedstoneComponent, ILinkRedstoneWire {

    use LinkRedstoneWireTrait;

    public function __construct()
    {
        $oldRedstoneTorch = VanillaBlocks::REDSTONE_TORCH();
        parent::__construct(
            new BlockIdentifier(BlockTypeIds::newId()),
            $oldRedstoneTorch->getName(),
            new BlockTypeInfo($oldRedstoneTorch->getBreakInfo(), $oldRedstoneTorch->getTypeTags())
        );
    }


    public function onPostPlace(): void {
        $this->onRedstoneUpdate();
        BlockUpdateHelper::updateAroundDirectionRedstone($this, Facing::UP);
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool
    {
        BlockUpdateHelper::updateAroundDirectionRedstone($this, Facing::UP);
        return parent::onBreak($item, $player, $returnedItems);
    }

    public function onScheduledUpdate(): void {
        $lit = !$this->isLit();
        $event = new BlockRedstoneUpdatePowerEvent($this, $lit, $lit);
        $event->call();
        $lit = $event->getNewPowered();
        $this->setLit($lit);
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        BlockUpdateHelper::updateAroundDirectionRedstone($this, Facing::UP);
    }

    public function getStrongPower(int $face): int {
        return $this->isLit() && $face === Facing::DOWN ? 15 : 0;
    }

    public function getWeakPower(int $face): int {
        if  (!$this->isLit()) return 0;
        if ($face === Facing::DOWN) return $this->getFacing() !== Facing::DOWN ? 15 : 0;
        return $face !== $this->getFacing() ? 15 : 0;
    }

    public function isPowerSource(): bool {
        return $this->isLit();
    }

    public function onRedstoneUpdate(): void {
        if (BlockPowerHelper::isSidePowered($this, Facing::opposite($this->getFacing())) !== $this->isLit()) return;
        $this->getPosition()->getWorld()->scheduleDelayedBlockUpdate($this->getPosition(), 2);
    }
}