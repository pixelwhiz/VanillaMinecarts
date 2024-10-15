<?php

namespace pixelwhiz\vanillaminecarts;

use pocketmine\block\Block;
use pocketmine\block\Rail;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

class MinecartEntity extends Living {

    public const NORTH = 0;
    public const SOUTH = 1;
    public const EAST = 2;
    public const WEST = 3;
    public const UNKNOWN = 4;


    public bool $isMoving = false;

    public function getName(): string
    {
        return "Minecart";
    }

    public function getRider(): ?Player {
        $rider = $this->getTargetEntity();
        if ($rider instanceof Player) {
            return $rider;
        }
        return null;
    }

    protected function entityBaseTick(int $tickDiff = 20): bool
    {
        return parent::entityBaseTick($tickDiff);
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.7, 0.98);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.1;
    }

    public function getOffsetPosition(Vector3 $vector3): Vector3
    {
        return $this->getPosition()->add(0, 0.25, 0);
    }

    protected function getInitialGravity(): float
    {
        return 0.5;
    }

    protected function syncNetworkData(EntityMetadataCollection $properties): void
    {
        $properties->setByte(EntityMetadataProperties::IS_BUOYANT, 1);
        $properties->setString(EntityMetadataProperties::BUOYANCY_DATA, "{\"apply_gravity\":true,\"base_buoyancy\":1.0,\"big_wave_probability\":0.03,\"big_wave_speed\":10.0,\"drag_down_on_buoyancy_removed\":0.0,\"liquid_blocks\":[\"minecraft:rail\",\"minecraft:powered_rail\",\"minecraft:detector_rail\",\"minecraft:activator_rail\"],\"simulate_waves\":true}");
        parent::syncNetworkData($properties);
    }

    public function attack(EntityDamageEvent $source): void
    {
        $item = VanillaItems::MINECART();
        if ($source instanceof EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player and $damager->getGamemode() === GameMode::SURVIVAL) {
                $this->getWorld()->dropItem($this->getLocation()->asVector3(), $item);
            }
        }

        $this->close();
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $player->setTargetEntity($this);
        $this->setTargetEntity($player);
        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($this->getId(), $player->getId(), EntityLink::TYPE_RIDER, true, true, 1.0);
        $player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, 1.25, 0));
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);
        $player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
        NetworkBroadcastUtils::broadcastPackets($player->getWorld()->getPlayers(), [$link]);
        return parent::onInteract($player, $clickPos);
    }

    public function dismount(): void {
        $player = $this->getRider();
        $playerProps = $player->getNetworkProperties();
        $playerProps->setGenericFlag(EntityMetadataFlags::RIDING, false);

        NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [SetActorLinkPacket::create(
            new EntityLink($this->id, $player->id, EntityLink::TYPE_REMOVE, true, true, 0.1)
        )]);

        $this->setTargetEntity(null);
        $player->setTargetEntity(null);
    }

    public function getPlayerDirection(): int {
        $direction = self::UNKNOWN;
        $player = $this->getRider();
        if ($player instanceof Player) {
            $yaw = $player->getLocation()->getYaw();
            if (($yaw >= -45 && $yaw < 45) || ($yaw >= 315 && $yaw < 360) || ($yaw >= -360 && $yaw < -315)) {
                $direction = self::NORTH;
            } elseif ($yaw >= 45 && $yaw < 135) {
                $direction = self::EAST;
            } elseif ($yaw >= 135 && $yaw < 225) {
                $direction = self::SOUTH;
            } elseif ($yaw >= 225 && $yaw < 315) {
                $direction = self::WEST;
            }
        }
        return $direction;
    }

    public function walk(): void {
        $player = $this->getRider();
        if ($player !== null) {
            $pos = $this->getPosition()->floor();
            $world = $this->getWorld();
            $direction = $player->getDirectionVector();
            switch ($this->getPlayerDirection()) {
                case self::NORTH:
                    $block = $world->getBlock($pos->add(0, 0, 1.0));
                    if ($block instanceof Rail) {
                        $this->move(0, 0, $direction->getZ() + 0.1);
                    }
                    break;
                case self::SOUTH:
                    if ($world->getBlock($pos->subtract(0, 0, 1.0)) instanceof Rail) {
                        $this->move(0, 0, $direction->getZ() - 0.1);
                    }
                    break;
                case self::EAST:
                    if ($world->getBlock($pos->subtract(1.0, 0, 0)) instanceof Rail) {
                        $this->move($direction->getX() - 0.1, 0, 0);
                    }
                    break;
                case self::WEST:
                    if ($world->getBlock($pos->add(1.0, 0, 0)) instanceof Rail) {
                        $this->move($direction->getX() + 0.1, 0, 0);
                    }
                    break;
            }
        }
    }

    public function getSpeed(): float {
        $speed = 0.0;
        $world = $this->getWorld();
        $block = $world->getBlock($this->getDirectionVector()->floor());

        switch ($block) {
            case VanillaBlocks::RAIL():
                $speed = 0.4;
                break;
        }

        return $speed;
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::MINECART;
    }

}