<?php


namespace pixelwhiz\vanillaminecarts\handlers;

use pixelwhiz\vanillaminecarts\entities\MinecartBase;
use pixelwhiz\vanillaminecarts\utils\Facing;
use pocketmine\block\ActivatorRail;
use pocketmine\block\Block;
use pocketmine\block\DetectorRail;
use pocketmine\block\PoweredRail;
use pocketmine\block\Rail;
use pocketmine\block\VanillaBlocks;
use pocketmine\Server;

class Rails {

    private Block $rail;
    private MinecartBase $minecart;

    public function __construct(Block $rail, MinecartBase $minecart) {
        $this->rail = $rail;
        $this->minecart = $minecart;
    }

    public function onUpdate(): void {
        $minecart = $this->minecart;

        $rail = $minecart->getCurrentRail();
        if ($rail instanceof DetectorRail) {
            switch ($minecart->getHorizontalFacing()) {
                case Facing::NORTH:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->add(0, 0, $i);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract(0, 1, 0)->add(0, 0, $i);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }

                    }
                    break;
                case Facing::SOUTH:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->subtract(0, 0, $i);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract(0, 1, $i);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }
                        
                    }
                    break;
                case Facing::EAST:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->subtract($i, 0, 0);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract($i, 1, 0);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }

                    }
                    break;
                case Facing::WEST:
                    for ($i = 1; $i < 5; $i++) {
                        $pos = $minecart->getPosition()->floor()->add($i, 0, 0);
                        $blockPos = $minecart->getWorld()->getBlock($pos);

                        $down = $minecart->getLocation()->subtract(0, 1, 0)->add($i, 0, 0);
                        $blockDown = $minecart->getWorld()->getBlock($down);

                        if ($blockPos instanceof DetectorRail) {
                            $blockPos->setActivated(false);
                            $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                        }

                        if ($blockDown instanceof DetectorRail) {
                            $blockDown->setActivated(false);
                            $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
                        }

                    }
                    break;
            }

            if ($minecart->isAlive() === true and $minecart->isClosed() === true) {
                $blockPos->setActivated(false);
                $blockDown->setActivated(false);

                $minecart->getWorld()->setBlock($blockPos->getPosition(), $blockPos);
                $minecart->getWorld()->setBlock($blockDown->getPosition(), $blockDown);
            }
        }
    }


    public function handle(): void {
        $rail = $this->rail;
        $minecart = $this->minecart;

        if ($rail instanceof PoweredRail) {
            if ($rail->isPowered()) {
                $minecart->moveSpeed = 0.4;
            } else {
                $minecart->moveSpeed = 0.2;
            }
        }

        if ($rail instanceof DetectorRail) {
            $rail->setActivated(true);
            $minecart->getWorld()->setBlock($rail->getPosition(), $rail);
        }
    }

}