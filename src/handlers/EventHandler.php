<?php

namespace pixelwhiz\vanillaminecarts\handlers;

use pixelwhiz\vanillaminecarts\items\MinecartChest;
use pixelwhiz\vanillaminecarts\items\MinecartHopper;
use pixelwhiz\vanillaminecarts\items\MinecartTNT;

use pixelwhiz\vanillaminecarts\entities\minecarts\Minecart as MinecartEntity;
use pixelwhiz\vanillaminecarts\entities\minecarts\MinecartChest as MinecartChestEntity;
use pixelwhiz\vanillaminecarts\entities\minecarts\MinecartHopper as MinecartHopperEntity;
use pixelwhiz\vanillaminecarts\entities\minecarts\MinecartTNT as MinecartTNTEntity;

use pixelwhiz\vanillaminecarts\VanillaMinecarts;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Rail;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Stick;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\player\Player;

class EventHandler implements Listener {

    public function onInteract(PlayerInteractEvent $event): bool {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        if ($event->getAction() === $event::LEFT_CLICK_BLOCK) {
            return false;
        }

        if ($item instanceof Stick and $block instanceof Rail) {
            $player->sendTip("ShapeID: ". $block->getShape());
        }

        $railBlocks = [
            BlockTypeIds::RAIL,
            BlockTypeIds::POWERED_RAIL,
            BlockTypeIds::ACTIVATOR_RAIL,
            BlockTypeIds::DETECTOR_RAIL,
        ];

        if (in_array($block->getTypeId(), $railBlocks) and VanillaMinecarts::getInstance()->isMinecartItem($item)) {

            $minecart = VanillaItems::MINECART();
            $minecartChest = new MinecartChest();
            $minecartHopper = new MinecartHopper();
            $minecartTNT = new MinecartTNT();

            $pos = $block->getPosition()->add(0.5, 1, 0.5);
            $world = $block->getPosition()->getWorld();
            $yaw = fmod($player->getLocation()->yaw - 90, 360);
            $location = Location::fromObject($pos, $world, $yaw, 0);

            switch ($item->getTypeId()) {
                case $minecart->getTypeId():
                    $entity = new MinecartEntity($location);
                    break;
                case $minecartChest->getTypeId():
                    $entity = new MinecartChestEntity($location);
                    $entity->saveNBT()->setString("Data", $entity->write());
                    break;
                case $minecartHopper->getTypeId():
                    $entity = new MinecartHopperEntity($location);
                    break;
                case $minecartTNT->getTypeId():
                    $entity = new MinecartTNTEntity($location);
                    break;
                default:
            }

            if ($item->hasCustomName()) {
                $entity->setNameTag($item->getCustomName());
            }

            if ($player->isSurvival()) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }

            $entity->spawnToAll();
            $event->cancel();
        }
        return true;
    }

    public function onTeleport(EntityTeleportEvent $event) {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            if (isset(VanillaMinecarts::$inMinecart[$entity->getName()])) {
                $entity = VanillaMinecarts::$inMinecart[$entity->getName()];
                if ($entity instanceof MinecartEntity) {
                    unset(VanillaMinecarts::$inMinecart[$entity->getName()]);
                }
            }
        }
    }

    public function onReceive(DataPacketReceiveEvent $event) {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        if ($packet instanceof PlayerAuthInputPacket) {

            $moveVecZ = $packet->getMoveVecZ();
            $isWPressed = $moveVecZ > 0.0;
            if (isset(VanillaMinecarts::$inMinecart[$player->getName()]) and $isWPressed) {
                $entity = VanillaMinecarts::$inMinecart[$player->getName()];
                if ($entity instanceof MinecartEntity) {
                    $entity->forwardOnRail($player->getHorizontalFacing());
                }
            }

        }

        if ($packet instanceof InteractPacket) {
            if ($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
                if (isset(VanillaMinecarts::$inMinecart[$player->getName()])) {
                    $entity = VanillaMinecarts::$inMinecart[$player->getName()];
                    if ($entity instanceof MinecartEntity) {
                        $entity->dismount();
                    }
                }
            }

        }

        return true;
    }

}