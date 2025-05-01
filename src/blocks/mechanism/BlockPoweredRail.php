<?php


namespace pixelwhiz\vanillaminecarts\blocks\mechanism;

use pixelwhiz\vanillaminecarts\blocks\BlockPowerHelper;
use pixelwhiz\vanillaminecarts\blocks\IRedstoneComponent;
use pixelwhiz\vanillaminecarts\blocks\RedstoneComponentTrait;
use pixelwhiz\vanillaminecarts\events\BlockRedstoneUpdatePowerEvent;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\PoweredRail;
use pocketmine\block\utils\RailConnectionInfo;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\player\Player;

class BlockPoweredRail extends PoweredRail implements IRedstoneComponent {

    use RedstoneComponentTrait;

    public function __construct()
    {
        $oldPoweredRail = VanillaBlocks::POWERED_RAIL();
        parent::__construct(
            new BlockIdentifier(BlockTypeIds::newId()),
            $oldPoweredRail->getName(),
            new BlockTypeInfo($oldPoweredRail->getBreakInfo(), $oldPoweredRail->getTypeTags())
        );
    }

    public function onPostPlace(): void
    {
        $this->updatePower($this);
        parent::onPostPlace();
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool
    {
        parent::onBreak($item, $player, $returnedItems);
        $this->updateConnectedRails();
        return true;
    }

    public function onRedstoneUpdate(): void
    {
        $this->updatePower($this);
        $this->updateConnectedRails();
    }

    protected function updateConnectedRails(): void {
        $connections = $this->getCurrentShapeConnections();
        for ($i = 0; $i < count($connections); $i++) {
            $face = $connections[$i];
            $up = false;
            if (($face & RailConnectionInfo::FLAG_ASCEND) > 0) {
                $face = $face ^ RailConnectionInfo::FLAG_ASCEND;
                $up = true;
            }

            $side = $this;
            for ($j = 0; $j < 8; $j++) {
                $side = $side->getSide($face);
                if ($up) $side = $side->getSide(Facing::UP);
                if (!$side instanceof PoweredRail) {
                    $side = $side->getSide(Facing::DOWN);
                    if (!$side instanceof PoweredRail) break;
                }

                $faces = $side->getCurrentShapeConnections();
                if (in_array($face, $faces, true)) {
                    $this->updatePower($side);
                    $up = false;
                    continue;
                }

                if (in_array($face | RailConnectionInfo::FLAG_ASCEND, $faces, true)) {
                    $this->updatePower($side);
                    $up = true;
                    continue;
                }
                break;
            }
        }
    }

    protected function updatePower(PoweredRail $block): void {
        if (BlockPowerHelper::isPowered($block)) {
            $this->updatePowered($block, true);
            return;
        }

        $connections = $block->getCurrentShapeConnections();
        for ($i = 0; $i < count($connections); $i++) {
            $face = $connections[$i];
            $up = false;
            if (($face & RailConnectionInfo::FLAG_ASCEND) > 0) {
                $face = $face ^ RailConnectionInfo::FLAG_ASCEND;
                $up = true;
            }

            $side = $block;
            for ($j = 0; $j < 8; $j++) {
                $side = $side->getSide($face);
                if ($up) $side = $side->getSide(Facing::UP);
                if (!$side instanceof PoweredRail) {
                    $side = $side->getSide(Facing::DOWN);
                    if (!$side instanceof PoweredRail) break;
                }

                $faces = $side->getCurrentShapeConnections();
                if (in_array($face, $faces, true)) {
                    if (BlockPowerHelper::isPowered($side)) {
                        $this->updatePowered($block, true);
                        return;
                    }
                    $up = false;
                    continue;
                }

                if (in_array($face | RailConnectionInfo::FLAG_ASCEND, $faces, true)) {
                    if (BlockPowerHelper::isPowered($side)) {
                        $this->updatePowered($block, true);
                        return;
                    }
                    $up = true;
                    continue;
                }
                break;
            }
        }

        $this->updatePowered($block, false);
    }

    protected function updatePowered(PoweredRail $block, bool $powered): void {
        $oldPowered = $block->isPowered();
        if ($oldPowered === $powered) return;

        $event = new BlockRedstoneUpdatePowerEvent($this, $powered, $oldPowered);
        $event->call();
        $powered = $event->getNewPowered();
        if ($oldPowered === $powered) return;

        $block->setPowered($powered);
        $block->getPosition()->getWorld()->setBlock($block->getPosition(), $block);
    }

}