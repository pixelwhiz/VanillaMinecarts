<?php

namespace pixelwhiz\vanillaminecarts;

use pixelwhiz\vanillaminecarts\block\types\RailShapeTypes;
use pocketmine\block\Rail;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Minecart;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class EventHandler implements Listener {

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        if ($block instanceof Rail and $item instanceof Minecart) {
            $player->getInventory()->setItemInHand($item->setCount($item->getCount() - 1));
            $entity = new MinecartEntity(Location::fromObject($block->getPosition()->asVector3(), $block->getPosition()->getWorld()));
            $entity->spawnToAll();
        }
    }

    public function onReceive(DataPacketReceiveEvent $event) {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        if ($packet instanceof PlayerAuthInputPacket) {

            $moveVecX = $packet->getMoveVecX();
            $moveVecZ = $packet->getMoveVecZ();

            $isWPressed = $moveVecZ > 0.0;

            $entity = $player->getTargetEntity();
            if ($entity instanceof MinecartEntity) {
                if ($isWPressed) {
                    $entity->walk();
                }
            }
        }

        if ($packet instanceof InteractPacket) {
            $entity = $player->getTargetEntity();
            if ($entity instanceof MinecartEntity) {
                if ($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
                    $entity->dismount();
                }
            }


        }

        return true;
    }



}