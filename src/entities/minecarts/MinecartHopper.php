<?php

namespace pixelwhiz\vanillaminecarts\entities\minecarts;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;

use pixelwhiz\vanillaminecarts\entities\MinecartBase;

use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

class MinecartHopper extends MinecartBase {

    public const TAG_INVENTORY = "Inventory";
    public const TAG_NAME = "Minecart with Hopper";
    public const TAG_INVENTORY_TYPE = InvMenu::TYPE_HOPPER;

    private static BigEndianNbtSerializer $nbtSerializer;

    public InvMenu $menu;

    public CompoundTag $nbt;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->menu = InvMenu::create(self::TAG_INVENTORY_TYPE);
        self::$nbtSerializer = new BigEndianNbtSerializer();
        $this->read($nbt->getString("Data", $this->write()));
    }

    public function onUpdate(int $currentTick): bool
    {
        return parent::onUpdate($currentTick);
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("Data", $this->write());
        return $nbt;
    }

    public function read(string $data): void {
        $contents = [];
        $inventoryTag = self::$nbtSerializer->read(zlib_decode($data))->mustGetCompoundTag()->getListTag(self::TAG_INVENTORY);

        foreach ($inventoryTag as $tag) {
            $contents[$tag->getByte("Slot")] = Item::nbtDeserialize($tag);
        }

        $inventory = $this->menu->getInventory();
        $inventory->setContents($contents);
    }

    public function write() : string{
        $contents = [];
        foreach($this->menu->getInventory()->getContents() as $slot => $item){
            $contents[] = $item->nbtSerialize($slot);
        }

        return zlib_encode(self::$nbtSerializer->write(new TreeRoot(CompoundTag::create()
            ->setTag(self::TAG_INVENTORY, new ListTag($contents, NBT::TAG_Compound))
        )), ZLIB_ENCODING_GZIP);
    }

    protected function onDeath(): void
    {
        foreach ($this->menu->getInventory()->getContents() as $slot => $item) {
            $pos = $this->getPosition();
            $this->getWorld()->dropItem($pos, $item);
        }
        parent::onDeath();
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::HOPPER_MINECART;
    }

    public function onInteract(Player $player, Vector3 $clickPos): bool
    {
        $menu = $this->menu;
        $menu->setName(self::TAG_NAME);

        $menu->setListener(function (InvMenuTransaction $transaction) : InvMenuTransactionResult {
            return $transaction->continue();
        });

        $menu->setInventoryCloseListener(function (Player $viewer, Inventory $inventory) {
            $this->saveNBT()->setString("Data", $this->write());
        });

        $menu->send($player);
        return parent::onInteract($player, $clickPos);
    }

    public function getName(): string
    {
        return self::TAG_NAME;
    }

}