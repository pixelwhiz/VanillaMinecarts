<?php

namespace pixelwhiz\vanillaminecarts\entities\minecarts;

use pixelwhiz\vanillaminecarts\entities\MinecartBase;
use pixelwhiz\vanillaminecarts\VanillaMinecarts;
use pocketmine\block\ActivatorRail;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

class Minecart extends MinecartBase {

    public const TAG_NAME = "Minecart";

    public ?Player $rider = null;

    private int $hurtsTick = 15;

    public static function getNetworkTypeId(): string
    {
        return EntityIds::MINECART;
    }

    public function onUpdate(int $currentTick): bool
    {
        $this->hurtsTick--;
        if ($this->hurtsTick === 0) {
            $rail = $this->getCurrentRail();
            if ($rail instanceof ActivatorRail and $rail->isPowered()) {
                $this->hurtsTick = 15;
                $this->performHurtAnimation();
                $this->dismount();
            }
        }
        return parent::onUpdate($currentTick);
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $this->setRider($player);
        return parent::onInteract($player, $clickPos);
    }

    public function onCollideWithEntity(): void {
        $entity = $this->getWorld()->getNearestEntity($this->getPosition(), 5);
        if ($entity instanceof Living && !$entity instanceof Player) {
            if ($entity->getPosition()->equals($this->getPosition())) {
                $this->setRider($entity);
            }
        }
    }

    public function setRider(Player $entity): bool {
        if (isset(VanillaMinecarts::$inMinecart[$entity->getName()])) {
            return false;
        }

        $pk = new SetActorLinkPacket();
        $pk->link = new EntityLink($this->getId(), $entity->getId(), EntityLink::TYPE_RIDER, true, true, 1.0);

        $entity->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, 1, 0));
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);

        $this->rider = $entity;
        VanillaMinecarts::$inMinecart[$entity->getName()] = $this;
        NetworkBroadcastUtils::broadcastPackets($entity->getWorld()->getPlayers(), [$pk]);
        return true;
    }

    public function dismount(): bool {
        if ($this->rider === null) {
            return false;
        }

        $entity = $this->rider;
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, false);
        $entity->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, false);
        $entity->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, null);

        $this->rider = null;
        unset(VanillaMinecarts::$inMinecart[$entity->getName()]);
        return true;
    }

    public function getName(): string
    {
        return self::TAG_NAME;
    }
}
