<?php

namespace pixelwhiz\vanillaminecarts;

use pixelwhiz\vanillaminecarts\block\types\RailShapeTypes;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Rail;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
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

    public const SOUTH = 0;
    public const NORTH = 1;
    public const WEST = 2;
    public const EAST = 3;


    public const STATE_INITIAL = 0;
    public const STATE_ON_RAIL = 1;
    public const STATE_OFF_RAIL = 2;


    public bool $isMoving = false;

    private Vector3 $moveVector;
    private float $direction = -1;
    private float $moveSpeed = 0.4;

    private int $state = self::STATE_INITIAL;

    public function getName(): string
    {
        return "Minecart";
    }

    protected function initEntity(CompoundTag $nbt): void
    {

//        $this->moveVector[self::NORTH] = new Vector3(0, 0, 1);
//        $this->moveVector[self::EAST] = new Vector3(-1, 0, 0);
//        $this->moveVector[self::SOUTH] = new Vector3(0, 0, -1);
//        $this->moveVector[self::WEST] = new Vector3(1, 0, 0);

        parent::initEntity($nbt);
    }

    public function getRider(): ?Player {
        $rider = $this->getTargetEntity();
        if ($rider instanceof Player) {
            return $rider;
        }
        return null;
    }

    public function onUpdate(int $currentTick): bool
    {
        if ($this->getRider() !== null and $this->isAlive()) {
            switch ($this->state) {
                case self::STATE_INITIAL:
                    $this->checkIfOnRail();
                    break;
                case self::STATE_ON_RAIL:
                    $this->forwardOnRail($this->getRider());
                    $this->updateMovement();
                    break;
                case self::STATE_OFF_RAIL:
                    break;
            }
        }

        return parent::onUpdate($currentTick);
    }

    private function checkIfOnRail(){
        for($y = -1; $y !== 2 and $this->state === self::STATE_INITIAL; $y++){
            $positionToCheck = $this->getLocation()->add(0, $y, 0);
            $block = $this->getWorld()->getBlock($positionToCheck);
            if($this->isRail($block)){
                $minecartPosition = $positionToCheck->floor()->add(0.5, 0, 0.5);
                $this->setPosition($minecartPosition);
                $this->state = self::STATE_ON_RAIL;
            }
        }
        if($this->state !== self::STATE_ON_RAIL){
            $this->state = self::STATE_OFF_RAIL;
        }
    }

    public function forwardOnRail(Player $player): void {
        if ($this->direction === -1) {
            $this->direction = $this->getPlayerDirection();
        }

        $rail = $this->getCurrentRail();

    }

    public function getDirectionToMove(Rail $rail, Vector3 $direction): Vector3 {
        switch ($rail->getShape()) {
            case RailShapeTypes::STRAIGHT_NORTH_SOUTH:
            case RailShapeTypes::SLOPED_ASCENDING_NORTH:
            case RailShapeTypes::SLOPED_DESCENDING_SOUTH:
                switch ($this->getDirectionVector()) {
                    case self::NORTH:
                    case self::SOUTH:
                        return $direction;
                        break;
                }
                break;

            case RailShapeTypes::STRAIGHT_EAST_WEST:
            case RailShapeTypes::SLOPED_ASCENDING_EAST:
            case RailShapeTypes::SLOPED_DESCENDING_WEST:
                switch ($this->getDirectionVector()) {
                    case self::EAST:
                    case self::WEST:
                        return $direction;
                        break;
                }
                break;

            case RailShapeTypes::CURVED_SOUTH_EAST:
                break;

        }

    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.7, 0.5);
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
            $this->flagForDespawn();
        }
        $source->cancel();
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $player->setTargetEntity($this);
        $this->setTargetEntity($player);
        $link = new SetActorLinkPacket();
        $link->link = new EntityLink($this->getId(), $player->getId(), EntityLink::TYPE_RIDER, true, true, 1.0);
        $player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, 1.25, 0.25));
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
        $direction = -1;
        $player = $this->getRider();
        if ($player instanceof Player) {
            $yaw = $player->getLocation()->getYaw();
            if (($yaw >= -45 && $yaw < 45) || ($yaw >= 315 && $yaw < 360) || ($yaw >= -360 && $yaw < -315)) {
                $direction = self::SOUTH;
            } elseif ($yaw >= 45 && $yaw < 135) {
                $direction = self::WEST;
            } elseif ($yaw >= 135 && $yaw < 225) {
                $direction = self::NORTH;
            } elseif ($yaw >= 225 && $yaw < 315) {
                $direction = self::EAST;
            }
        }
        return $direction;
    }

    public function isRail(Block $rail): bool {
        if (in_array($rail->getTypeId(), [BlockTypeIds::RAIL, BlockTypeIds::ACTIVATOR_RAIL, BlockTypeIds::POWERED_RAIL, BlockTypeIds::DETECTOR_RAIL])) {
            return true;
        }
        return false;
    }

    public function getCurrentRail(): ?Block {
        $pos = $this->getPosition()->floor();
        $block = $this->getWorld()->getBlock($pos);
        if ($this->isRail($block)) {
            return $block;
        }

        $down = $this->getLocation()->subtract(0, 1, 0);
        $block = $this->getWorld()->getBlock($down);
        if ($this->isRail($block)) {
            return $block;
        }

        return null;
    }

    public function walk(): void {
        $player = $this->getRider();
        if ($player !== null) {
            $pos = $this->getPosition()->floor();
            $world = $this->getWorld();
            $direction = $player->getDirectionVector();
            switch ($this->getPlayerDirection()) {
                case self::NORTH:
                    if ($world->getBlock($pos->add(0, 0, -1.0)) instanceof Rail) {
                        $this->move(0, 0, -0.5);
                    }

                    // TODO: Vertical movement
                    if ($world->getBlock($pos->add(0, 1, -1.0)) instanceof Rail) {
                        $this->move(0, 1, -0.5);
                    }

                    if ($world->getBlock($pos->add(0, -1, -1.0)) instanceof Rail || $world->getBlock($pos->add(0, -1, 0)) instanceof Rail) {
                        $this->move(0, -1, -0.5);
                    }

                    break;
                case self::SOUTH:
                    if ($world->getBlock($pos->add(0, 0, 1.0)) instanceof Rail) {
                        $this->move(0, 0, 0.5);
                    }

                    // TODO: Vertical movement
                    if ($world->getBlock($pos->add(0, 1, 1.0)) instanceof Rail) {
                        $this->move(0, 1, 0.5);
                    }

                    if ($world->getBlock($pos->add(0, -1, 1.0)) instanceof Rail || $world->getBlock($pos->add(0, -1, 0)) instanceof Rail) {
                        $this->move(0, -1, 0.5);
                    }

                    break;
                case self::EAST:
                    if ($world->getBlock($pos->add(1.0, 0, 0)) instanceof Rail) {
                        $this->move(0.5, 0, 0);
                    }

                    
                    // TODO: Vertical movement
                    if ($world->getBlock($pos->add(1.0, 1, 0)) instanceof Rail) {
                        $this->move(0.5, 1, 0);
                    }

                    if ($world->getBlock($pos->add(1.0, -1, 0)) instanceof Rail || $world->getBlock($pos->add(0, -1, 0)) instanceof Rail) {
                        $this->move(0.5, -1, 0);
                    }
                    break;
                case self::WEST:
                    if ($world->getBlock($pos->add(-1.0, 0, 0)) instanceof Rail) {
                        $this->move(-0.5, 0, 0);
                    }

                    // TODO: Vertical movement
                    if ($world->getBlock($pos->add(-1.0, 1, 0)) instanceof Rail) {
                        $this->move(-0.5, 1, 0);
                    }

                    if ($world->getBlock($pos->add(-1.0, -1, 0)) instanceof Rail || $world->getBlock($pos->add(0, -1, 0)) instanceof Rail) {
                        $this->move(-0.5, -1, 0);
                    }
                    break;
            }
        }
    }

    private function moveIfRail(){
        $nextMoveVector = $this->moveVector[$this->direction];
        $nextMoveVector = $nextMoveVector->multiply($this->moveSpeed);
        $newVector = $this->getDirectionVector()->add($nextMoveVector->x, $nextMoveVector->y, $nextMoveVector->z);
        $possibleRail = $this->getCurrentRail();
        if($possibleRail){
            $this->moveUsingVector($newVector);
            return true;
        }

        return false;
    }

    private function moveUsingVector(Vector3 $desiredPosition){
        $dx = $desiredPosition->x - $this->getLocation()->x;
        $dy = $desiredPosition->y - $this->getLocation()->y;
        $dz = $desiredPosition->z - $this->getLocation()->z;
        $this->move($dx, $dy, $dz);
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