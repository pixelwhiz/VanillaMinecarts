<?php


namespace pixelwhiz\vanillaminecarts\entities;

use pixelwhiz\vanillaminecarts\entities\minecarts\Minecart;
use pixelwhiz\vanillaminecarts\items\MinecartChest;
use pixelwhiz\vanillaminecarts\items\MinecartHopper;
use pixelwhiz\vanillaminecarts\items\MinecartTNT;
use pixelwhiz\vanillaminecarts\handlers\Rails;
use pixelwhiz\vanillaminecarts\utils\Facing;
use pixelwhiz\vanillaminecarts\utils\MinecartState;
use pixelwhiz\vanillaminecarts\utils\RailTypes;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\DetectorRail;
use pocketmine\block\PoweredRail;
use pocketmine\block\Rail;
use pocketmine\math\AxisAlignedBB;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

class MinecartBase extends Entity {

    protected int $rollingAmplitude = 0;
    protected bool $rollingDirection = false;

    private int $state = MinecartState::STATE_INITIAL;
    private int $direction = -1;
    public array $moveVector = [];

    public float $moveSpeed = 0.2;

    protected function initEntity(CompoundTag $nbt): void
    {
        $this->networkPropertiesDirty = true;
        $this->moveVector[Facing::NORTH] = new Vector3(0, 0, -1);
        $this->moveVector[Facing::SOUTH] = new Vector3(0, 0, 1);
        $this->moveVector[Facing::WEST] = new Vector3(-1, 0, 0);
        $this->moveVector[Facing::EAST] = new Vector3(1, 0, 0);
        parent::initEntity($nbt);
    }

    protected function getInitialGravity(): float
    {
        return 0.1;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.7, 0.98);
    }

    public function getOffsetPosition(Vector3 $vector3): Vector3
    {
        return $vector3->add(0, 0.4, 0);
    }

    public function onUpdate(int $currentTick): bool
    {
        $this->motion->y -= $this->getGravity();
        $this->timings->startTiming();

        if($this->rollingAmplitude > 0){
            --$this->rollingAmplitude;
            $this->networkPropertiesDirty = true;
        }

        $pos = $this->getPosition()->add(0.5, 0, 0.5)->subtract(0, 0.1, 0);
        $currentBlock = $this->getWorld()->getBlock($pos);
        if ($currentBlock->isTransparent()) {
            $this->motion->y += $this->getGravity();
            $this->updateMovement();
        }

        if ($this->isAlive() and $this instanceof Minecart) {
            $rider = $this->rider;
            if ($rider instanceof Player) {
                switch ($this->state) {
                    case MinecartState::STATE_INITIAL:
                        $this->checkIfOnRail();
                        break;
                }
            }
        }

        foreach ($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(0.5, 0.5, 0.5), $this) as $entity) {
            if ($entity instanceof MinecartBase) {
                $this->onCollideWithMinecart($entity);
            }
        }

        $currentRail = $this->getCurrentRail();
        if ($currentRail !== null) {
            $rails = new Rails($currentRail, $this);
            $rails->handle();
            $rails->onUpdate();
        }

        $this->forwardOnRail($this->getHorizontalFacing());

        $this->timings->stopTiming();
        return parent::onUpdate($currentTick);
    }

    private function checkIfOnRail(){
        for ($y = -1; $y !== 2 and $this->state === MinecartState::STATE_INITIAL; $y++) {
            $positionToCheck = new Vector3($this->location->x, $this->location->y + $y, $this->location->z);
            $block = $this->getWorld()->getBlock($positionToCheck);
            if ($this->isRail($block)) {
                $minecartPosition = $positionToCheck->floor()->add(0.5, 0, 0.5);
                $this->setPosition($minecartPosition);    // Move minecart to center of rail
                $this->state = MinecartState::STATE_ON_RAIL;
            }
        }
        if ($this->state !== MinecartState::STATE_ON_RAIL) {
            $this->state = MinecartState::STATE_OFF_RAIL;
        }
    }

    public function getVariant(): int {
        return match ($this->getNetworkTypeId()) {
            EntityIds::MINECART => 0,
            EntityIds::CHEST_MINECART => 1,
            EntityIds::HOPPER_MINECART => 2,
            EntityIds::TNT_MINECART => 3,
        };
    }

    protected function syncNetworkData(EntityMetadataCollection $properties): void
    {
        $properties->setInt(EntityMetadataProperties::VARIANT, $this->getVariant());
        $properties->setInt(EntityMetadataProperties::HURT_TIME, $this->rollingAmplitude);
        $properties->setInt(EntityMetadataProperties::HURT_DIRECTION, $this->rollingDirection ? 1 : -1);

        $properties->setByte(EntityMetadataProperties::IS_BUOYANT, 1);
        $properties->setString(EntityMetadataProperties::BUOYANCY_DATA, "{\"apply_gravity\":true,\"base_buoyancy\":1.0,\"big_wave_probability\":0.03,\"big_wave_speed\":10.0,\"drag_down_on_buoyancy_removed\":0.0,\"liquid_blocks\":[\"minecraft:rail\",\"minecraft:powered_rail\",\"minecraft:detector_rail\",\"minecraft:activator_rail\"],\"simulate_waves\":true}");

        $properties->setInt(EntityMetadataProperties::HEALTH, (int) ($this->getMaxHealth() - $this->getHealth()));
        parent::syncNetworkData($properties);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.3;
    }

    public function getName(): string { return ""; }
    public static function getNetworkTypeId(): string { return ""; }

    public function getMinecartItem(): Item {
        return match ($this->getNetworkTypeId()) {
            EntityIds::MINECART => VanillaItems::MINECART(),
            EntityIds::CHEST_MINECART => new MinecartChest(),
            EntityIds::HOPPER_MINECART => new MinecartHopper(),
            EntityIds::TNT_MINECART => new MinecartTNT(),
        };
    }

    public function getBoundingBox(): AxisAlignedBB
    {
        return new AxisAlignedBB(
            $this->location->x - 0.35,
            $this->location->y,
            $this->location->z - 0.35,
            $this->location->x + 0.35,
            $this->location->y + 0.98,
            $this->location->z + 0.35
        );
    }

    public function attack(EntityDamageEvent $source): void
    {
        if($source instanceof EntityDamageByEntityEvent){
            $damager = $source->getDamager();
            if($damager instanceof Player){
                if ($damager->isCreative()) {
                    $source->setBaseDamage(1000);
                }

                if (!$source->isCancelled() and $this->isAlive()) {
                    $this->performHurtAnimation();
                }
            }
        }

        parent::attack($source);
    }

    public function onCollideWithMinecart(MinecartBase $minecart): void
    {
        $relativeMotion = $this->motion->subtract($minecart->motion->x, $minecart->motion->y, $minecart->motion->z);

        if ($relativeMotion->lengthSquared() == 0) {
            return;
        }

        $collisionVector = $this->getPosition()->subtract($minecart->location->x, $minecart->location->y, $minecart->location->z)->normalize();
        $impulse = $collisionVector->multiply(0.4);

        $this->motion->x += $impulse->x;
        $this->motion->z += $impulse->z;

        $minecart->motion->x -= $impulse->x;
        $minecart->motion->z -= $impulse->z;

        $this->updateMovement();
        $minecart->updateMovement();
    }
    
    public function forwardOnRail(int $candidateDirection): bool {

        $rail = $this->getCurrentRail();
        if ($rail !== null) {
            $railType = $rail->getShape();
            $nextDirection = $this->getDirectionToMove($railType, $candidateDirection);
            if ($nextDirection !== -1) {
                $this->direction = $nextDirection;
                $moved = $this->checkForVertical($railType, $nextDirection);
                if (!$moved) {
                    return $this->moveIfRail();
                } else {
                    return true;
                }
            } else {
                $this->direction = -1;
            }
        } else {
            $this->state = MinecartState::STATE_INITIAL;
        }

        return false;
    }

    private function getDirectionToMove(int $railTypes, int $candidateDirection) {
        switch ($railTypes) {
            case RailTypes::STRAIGHT_NORTH_SOUTH:
            case RailTypes::SLOPED_ASCENDING_NORTH:
            case RailTypes::SLOPED_DESCENDING_SOUTH:
                switch ($candidateDirection) {
                    case Facing::NORTH:
                        $this->location->yaw = 180;
                        return $candidateDirection;
                    case Facing::SOUTH:
                        $this->location->yaw = 0;
                        return $candidateDirection;
                }
                break;
            case RailTypes::STRAIGHT_EAST_WEST:
            case RailTypes::SLOPED_ASCENDING_EAST:
            case RailTypes::SLOPED_DESCENDING_WEST:
                switch ($candidateDirection) {
                    case Facing::WEST:
                        $this->location->yaw = 90;
                        return $candidateDirection;
                    case Facing::EAST:
                        $this->location->yaw = 270;
                        return $candidateDirection;
                }

                break;
            case RailTypes::CURVED_SOUTH_EAST:
                switch ($candidateDirection) {
                    case Facing::SOUTH:
                        $diff = $this->location->x - $this->getLocation()->getFloorX();
                        if ($diff !== 0 and $diff >= .5) {
                            $dx = ($this->getLocation()->getFloorX() + 1.25) - $this->location->x;
                            $this->move($dx, 0, 0.4);
                            $this->location->yaw = 270;
                            return Facing::EAST;
                        }
                        break;
                    case Facing::WEST:
                        $diff = $this->location->z - $this->getLocation()->getFloorZ();
                        if ($diff !== 0 and $diff <= .5) {
                            $dz = ($this->getLocation()->getFloorZ() - .5) - $this->location->z;
                            $this->move(-0.4, 0, $dz);
                            $this->location->yaw = 180;
                            return Facing::NORTH;
                        }
                        break;
                    case Facing::EAST:
                        $this->location->yaw = 270;
                        return $candidateDirection;
                    case Facing::NORTH:
                        $this->location->yaw = 180;
                        return $candidateDirection;
                }
                break;
            case RailTypes::CURVED_SOUTH_WEST:
                switch ($candidateDirection) {
                    case Facing::SOUTH:
                        $diff = $this->location->x - $this->getLocation()->getFloorX();
                        if ($diff !== 0 and $diff <= .5) {
                            $dx = ($this->getLocation()->getFloorX() - .5) - $this->location->x;
                            $this->move($dx, 0, 0.4);
                            $this->location->yaw = 90;
                            return Facing::WEST;
                        }
                        break;
                    case Facing::EAST:
                        $diff = $this->location->z - $this->getLocation()->getFloorZ();
                        if ($diff !== 0 and $diff <= .5) {
                            $dz = ($this->getLocation()->getFloorZ() - .5) - $this->location->z;
                            $this->move(0.4, 0, $dz);
                            $this->location->yaw = 180;
                            return Facing::NORTH;
                        }
                        break;
                    case Facing::WEST:
                        $this->location->yaw = 90;
                        return $candidateDirection;
                    case Facing::NORTH:
                        $this->location->yaw = 180;
                        return $candidateDirection;
                }
                break;
            case RailTypes::CURVED_NORTH_WEST:
                switch ($candidateDirection) {
                    case Facing::NORTH:
                        $diff = $this->location->x - $this->getLocation()->getFloorX();
                        if ($diff !== 0 and $diff <= .5) {
                            $dx = ($this->getLocation()->getFloorX() - .5) - $this->location->x;
                            $this->move($dx, 0, -0.4);
                            $this->location->yaw = 90;
                            return Facing::WEST;
                        }
                        break;
                    case Facing::EAST:
                        $diff = $this->location->z - $this->getLocation()->getFloorZ();
                        if ($diff !== 0 and $diff >= .5) {
                            $dz = ($this->getLocation()->getFloorZ() + 1.25) - $this->location->z;
                            $this->move(0.4, 0, $dz);
                            $this->location->yaw = 0;
                            return Facing::SOUTH;
                        }
                        break;
                    case Facing::WEST:
                        $this->location->yaw = 90;
                        return $candidateDirection;
                    case Facing::SOUTH:
                        $this->location->yaw = 0;
                        return $candidateDirection;

                }
                break;
            case RailTypes::CURVED_NORTH_EAST:
                switch ($candidateDirection) {
                    case Facing::NORTH:
                        $diff = $this->location->x - $this->getLocation()->getFloorX();
                        if ($diff !== 0 and $diff >= .5) {
                            $dx = ($this->getLocation()->getFloorX() + 1.25) - $this->location->x;
                            $this->move($dx, 0, -0.4);
                            $this->location->yaw = 270;
                            return Facing::EAST;
                        }
                        break;
                    case Facing::WEST:
                        $diff = $this->location->z - $this->getLocation()->getFloorZ();
                        if ($diff !== 0 and $diff >= .5) {
                            $dz = ($this->getLocation()->getFloorZ() + 1.25) - $this->location->z;
                            $this->move(-0.4, 0, $dz);
                            $this->location->yaw = 0;
                            return Facing::SOUTH;
                        }
                        break;
                    case Facing::SOUTH:
                        $this->location->yaw = 0;
                        return $candidateDirection;
                    case Facing::EAST:
                        $this->location->yaw = 270;
                        return $candidateDirection;
                }
                break;
        }
        return -1;
    }

    private function moveIfRail(): bool {
        $railBlocks = [
            BlockTypeIds::RAIL,
            BlockTypeIds::ACTIVATOR_RAIL,
            BlockTypeIds::DETECTOR_RAIL,
            BlockTypeIds::POWERED_RAIL
        ];

        $nextMoveVector = $this->moveVector[$this->direction];
        $nextMoveVector = $nextMoveVector->multiply($this->moveSpeed);
        $newVector = $this->getPosition()->add($nextMoveVector->x, $nextMoveVector->y, $nextMoveVector->z);
        $possibleRail = $this->getCurrentRail();
        if ($possibleRail !== null and in_array($possibleRail->getTypeId(), $railBlocks)) {
            $this->moveUsingVector($newVector);
            return true;
        }

        return false;
    }

    private function moveUsingVector(Vector3 $desiredPosition){
        $dx = $desiredPosition->x - $this->location->x;
        $dy = $desiredPosition->y - $this->location->y;
        $dz = $desiredPosition->z - $this->location->z;
        $this->move($dx, $dy, $dz);
    }

    private function checkForTurn(int $currentDirection, int $newDirection): int {
        $rail = $this->getCurrentRail();
        switch ($currentDirection) {
            case Facing::NORTH:
                $diff = $this->location->x - $this->getLocation()->getFloorX();
                if ($diff !== 0 and $diff <= .5) {
                    $dx = ($this->getLocation()->getFloorX() + .5) - $this->location->x;
                    $this->move($dx, 0, 0);
                    return $newDirection;
                }
                break;
            case Facing::SOUTH:
                $diff = $this->location->x - $this->getLocation()->getFloorX();
                if ($diff !== 0 and $diff >= .5) {
                    $dx = ($this->getLocation()->getFloorX() + .5) - $this->location->x;
                    $this->move($dx, 0, 0);
                    return $newDirection;
                }
                break;
            case Facing::EAST:
                $diff = $this->location->z - $this->getLocation()->getFloorZ();
                if ($diff !== 0 and $diff <= .5) {
                    $dz = ($this->getLocation()->getFloorZ() + .5) - $this->location->z;
                    $this->move(0, 0, $dz);
                    return $newDirection;
                }
                break;
            case Facing::WEST:
                $diff = $this->location->z - $this->getLocation()->getFloorZ();
                if ($diff !== 0 and $diff >= .5) {
                    $dz = $dz = ($this->getLocation()->getFloorZ() + .5) - $this->location->z;
                    $this->move(0, 0, $dz);
                    return $newDirection;
                }
                break;
        }

        return $currentDirection;
    }

    private function isRail(Block $rail): bool {
        $railBlocks = [
            BlockTypeIds::RAIL,
            BlockTypeIds::ACTIVATOR_RAIL,
            BlockTypeIds::POWERED_RAIL,
            BlockTypeIds::DETECTOR_RAIL
        ];

        if (in_array($rail->getTypeId(), $railBlocks)) {
            return true;
        }

        return false;
    }

    public function getCurrentRail(): ?Block {
        $pos = $this->getPosition()->floor();
        $blockPos = $this->getWorld()->getBlock($pos);
        if ($this->isRail($blockPos)) {
            return $blockPos;
        }

        $down = $this->getLocation()->subtract(0, 1, 0);
        $blockDown = $this->getWorld()->getBlock($down);
        if ($this->isRail($blockDown)) {
            return $blockDown;
        }

        return null;
    }

    private function checkForVertical(int $railType, int $currentDirection) : bool{
        switch ($railType) {
            case RailTypes::SLOPED_ASCENDING_NORTH:
                switch ($currentDirection) {
                    case Facing::NORTH:
                        // Headed north up
                        $diff = $this->location->z - $this->getLocation()->floor()->z;
                        if ($diff !== 0 and $diff <= .5) {
                            $dz = ($this->location->floor()->z - .1) - $this->location->z;
                            $this->move(0, 1, $dz);
                            return true;
                        }
                        break;
                    case Facing::SOUTH:
                        $diff = $this->location->z - $this->location->floor()->z;
                        if ($diff !== 0 and $diff >= .5) {
                            $dz = ($this->location->floor()->z + 1) - $this->location->z;
                            $this->move(0, -1, $dz);
                            return true;
                        }
                        break;
                }
                break;
            case RailTypes::SLOPED_DESCENDING_SOUTH:
                switch ($currentDirection) {
                    case Facing::SOUTH:
                        // Headed south up
                        $diff = $this->location->z - $this->location->floor()->z;
                        if ($diff !== 0 and $diff >= .5) {
                            $dz = ($this->location->floor()->z + 1) - $this->location->z;
                            $this->move(0, 1, $dz);
                            return true;
                        }
                        break;
                    case Facing::NORTH:
                        // Headed north down
                        $diff = $this->location->z - $this->location->floor()->z;
                        if ($diff !== 0 and $diff <= .5) {
                            $dz = ($this->location->floor()->z - .1) - $this->location->z;
                            $this->move(0, -1, $dz);
                            return true;
                        }
                        break;
                }
                break;
            case RailTypes::SLOPED_ASCENDING_EAST:
                switch ($currentDirection) {
                    case Facing::EAST:
                        // Headed east up
                        $diff = $this->location->x - $this->location->floor()->x;
                        if ($diff !== 0 and $diff >= .5) {
                            $dx = ($this->location->floor()->x + 1) - $this->location->x;
                            $this->move($dx, 1, 0);
                            return true;
                        }
                        break;
                    case Facing::WEST:
                        // Headed west down
                        $diff = $this->location->x - $this->location->floor()->x;
                        if ($diff !== 0 and $diff <= .5) {
                            $dx = ($this->location->floor()->x - .1) - $this->location->x;
                            $this->move($dx, -1, 0);
                            return true;
                        }
                        break;
                }
                break;
            case RailTypes::SLOPED_DESCENDING_WEST:
                switch ($currentDirection) {
                    case Facing::WEST:
                        $diff = $this->location->x - $this->location->floor()->x;
                        if ($diff !== 0 and $diff <= .5) {
                            $dx = ($this->getLocation()->getFloorx() - .1) - $this->location->x;;
                            $this->move($dx, 1, 0);
                            return true;
                        }
                        break;
                    case Facing::EAST:
                        // Headed east down
                        $diff = $this->location->x - $this->location->floor()->x;
                        if ($diff !== 0 and $diff >= .5) {
                            $dx = ($this->getLocation()->getFloorx() + 1) - $this->location->x;;
                            $this->move($dx, -1, 0);
                            return true;
                        }
                        break;
                }
                break;
        }

        return false;
    }

    public function performHurtAnimation() : void{
        $this->rollingAmplitude = 9;
        $this->rollingDirection = !$this->rollingDirection;
        $this->networkPropertiesDirty = true;
    }

    protected function onDeath(): void
    {
        $drop = true;
        if($this->lastDamageCause instanceof EntityDamageByEntityEvent){
            $damager = $this->lastDamageCause->getDamager();
            if($damager instanceof Player && $damager->isCreative()){
                $drop = false;
            }
        }

        if($drop){
            $this->getWorld()->dropItem($this->location, $this->getMinecartItem());
        }

        $rail = $this->getCurrentRail();
        if ($rail instanceof DetectorRail) {
            $rail->setActivated(false);
            $this->getWorld()->setBlock($rail->getPosition(), $rail);
        }

        $this->flagForDespawn();
        parent::onDeath();
    }

}