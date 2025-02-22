<?php

namespace pixelwhiz\vanillaminecarts\entities\minecarts;

use pixelwhiz\vanillaminecarts\entities\MinecartBase;
use pocketmine\entity\Explosive;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\item\FlintSteel;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\sound\FizzSound;

class MinecartTNT extends MinecartBase implements Explosive {

    public const TAG_NAME = "Minecart with TNT";
    public const TAG_FUSE = "Fuse";
    public const TAG_IGNITED = "Ignited";
    public int $fuse;
    public int $ignited;

    public static function getNetworkTypeId(): string
    {
        return EntityIds::TNT_MINECART;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        $this->fuse = $nbt->getShort(self::TAG_FUSE, 80);
        $this->ignited = $nbt->getShort(self::TAG_IGNITED, 0);
        parent::initEntity($nbt);
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setShort(self::TAG_FUSE, $this->fuse);
        $nbt->setShort(self::TAG_FUSE, $this->ignited);
        return $nbt;
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $item = $player->getInventory()->getItemInHand();

        if ($item instanceof FlintSteel) {
            $this->ignite();

            if ($player->isSurvival()) {
                $maxDamage = $item->getMaxDurability();
                $newDamage = min($maxDamage, $item->getDamage() + 1);

                $item->setDamage($newDamage);
                $player->getInventory()->setItemInHand($item);
            }
        }

        return parent::onInteract($player, $clickPos);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->closed) {
            return false;
        }

        $hasUpdate = parent::entityBaseTick($tickDiff);

        if(!$this->isFlaggedForDespawn() and $this->ignited === 1){
            $this->fuse -= $tickDiff;
            $this->networkPropertiesDirty = true;

            if($this->fuse <= 0){
                $this->flagForDespawn();
                $this->explode();
            }
        }

        return $hasUpdate || $this->fuse >= 0;
    }

    public function attack(EntityDamageEvent $source): void
    {
        switch ($source->getCause()) {
            case $source::CAUSE_ENTITY_EXPLOSION:
            case $source::CAUSE_BLOCK_EXPLOSION:
                if ($this->ignited === 0) {
                    $this->ignite(24);
                }

                $source->cancel();
                break;
            case $source::CAUSE_FIRE:
            case $source::CAUSE_LAVA:
            case $source::CAUSE_FIRE_TICK:
                if ($this->ignited === 0) {
                    $this->ignite();
                }

                $source->cancel();
                break;
        }
        parent::attack($source);
    }

    protected function ignite(int $fuse = 80): void {
        $this->ignited = 1;
        $this->fuse = $fuse;
        $pos = $this->getPosition();

        foreach ($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(10, 10, 10)) as $entity) {
            if ($entity instanceof Player) {
                $pk = new PlaySoundPacket();
                $pk->soundName = "random.fuse";
                $pk->x = $pos->getX();
                $pk->y = $pos->getY();
                $pk->z = $pos->getZ();
                $pk->volume = 1.0;
                $pk->pitch = 1.0;
                $entity->getNetworkSession()->sendDataPacket($pk);
            }
        }

        $this->networkPropertiesDirty = true;
    }

    protected function syncNetworkData(EntityMetadataCollection $properties): void
    {
        if ($this->ignited === 1) {
            $properties->setGenericFlag(EntityMetadataFlags::IGNITED, true);
            $properties->setInt(EntityMetadataProperties::VARIANT, 1);
            $properties->setInt(EntityMetadataProperties::FUSE_LENGTH, $this->fuse);
        }

        parent::syncNetworkData($properties);
    }

    public function explode(): void
    {
        $ev = new EntityPreExplodeEvent($this, 4);
        $ev->call();
        if(!$ev->isCancelled()){
            //TODO: deal with underwater TNT (underwater TNT treats water as if it has a blast resistance of 0)
            $explosion = new Explosion(Position::fromObject($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getWorld()), $ev->getRadius(), $this);
            if($ev->isBlockBreaking()){
                $explosion->explodeA();
            }
            $explosion->explodeB();
        }
    }

}