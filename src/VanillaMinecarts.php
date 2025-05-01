<?php

namespace pixelwhiz\vanillaminecarts;

use pixelwhiz\vanillaminecarts\blocks\BlockLoader;
use pixelwhiz\vanillaminecarts\blocks\mechanism\BlockPoweredRail;
use pixelwhiz\vanillaminecarts\blocks\power\BlockRedstoneTorch;
use pixelwhiz\vanillaminecarts\entities\MinecartBase;
use pixelwhiz\vanillaminecarts\items\MinecartChest;
use pixelwhiz\vanillaminecarts\items\MinecartHopper;
use pixelwhiz\vanillaminecarts\items\MinecartTNT;

use pixelwhiz\vanillaminecarts\entities\minecarts\Minecart as MinecartEntity;
use pixelwhiz\vanillaminecarts\entities\minecarts\MinecartChest as MinecartChestEntity;
use pixelwhiz\vanillaminecarts\entities\minecarts\MinecartHopper as MinecartHopperEntity;
use pixelwhiz\vanillaminecarts\entities\minecarts\MinecartTNT as MinecartTNTEntity;

use pixelwhiz\vanillaminecarts\handlers\EventHandler;

use pocketmine\block\Block;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\World;

class VanillaMinecarts extends PluginBase {

    public static self $instance;

    public Config $data;

    public static array $inMinecart = [];

    public array $blockLoader = [];

    protected function onLoad(): void
    {
        $this->registerBlocks();
        $this->registerEntities();
        $this->registerItems();
    }

    protected function onEnable(): void
    {
        self::$instance = $this;
        Server::getInstance()->getPluginManager()->registerEvents(new EventHandler(), $this);
        $this->data = new Config($this->getDataFolder() . "data.json", Config::JSON);
        $this->load();
    }

    private function overrideBlock(string $name, Block $oldBlock, int $id, \Closure $callback, ?string $class = null): void {
        $this->blockLoader[] = BlockLoader::createBlock($name, $oldBlock, $id, $callback, $class);
    }

    private function load(): void {
        for ($i = 0; $i < count($this->blockLoader); $i++) {
            $loader = $this->blockLoader[$i];
            $loader->load();
        }
    }

    private function registerBlocks(): void {
        $this->overrideBlock("rail", VanillaBlocks::POWERED_RAIL(), BlockTypeIds::newId(), fn($bid, $name, $info) => new BlockPoweredRail($bid, $name, $info));
        $this->overrideBlock("redstone_torch", VanillaBlocks::REDSTONE_TORCH(), BlockTypeIds::newId(), fn($bid, $name, $info) => new BlockRedstoneTorch());
    }

    private function registerItems(): void {
        $itemDeserializer = GlobalItemDataHandlers::getDeserializer();
        $itemSerializer = GlobalItemDataHandlers::getSerializer();
        $creativeInventory = CreativeInventory::getInstance();
        $stringToItemParser = StringToItemParser::getInstance();

        $minecartChest = new MinecartChest();
        $minecartHopper = new MinecartHopper();
        $minecartTNT = new MinecartTNT();

        $itemDeserializer->map(ItemTypeNames::CHEST_MINECART, static fn() => clone $minecartChest);
        $itemSerializer->map($minecartChest, static fn() => new SavedItemData(ItemTypeNames::CHEST_MINECART));
        $creativeInventory->add($minecartChest);
        $stringToItemParser->register('minecart_with_chest', static fn() => clone $minecartChest);

        $itemDeserializer->map(ItemTypeNames::HOPPER_MINECART, static fn() => clone $minecartHopper);
        $itemSerializer->map($minecartHopper, static fn() => new SavedItemData(ItemTypeNames::HOPPER_MINECART));
        $creativeInventory->add($minecartHopper);
        $stringToItemParser->register('minecart_with_hopper', static fn() => clone $minecartHopper);

        $itemDeserializer->map(ItemTypeNames::TNT_MINECART, static fn() => clone $minecartTNT);
        $itemSerializer->map($minecartTNT, static fn() => new SavedItemData(ItemTypeNames::TNT_MINECART));
        $creativeInventory->add($minecartTNT);
        $stringToItemParser->register('minecart_with_tnt', static fn() => clone $minecartTNT);
    }

    public static function getInstance(): self {
        return self::$instance;
    }

    private function registerEntities(): void {
        EntityFactory::getInstance()->register(MinecartEntity::class, function (World $world, CompoundTag $nbt): MinecartEntity {
            return new MinecartEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [MinecartEntity::TAG_NAME]);

        EntityFactory::getInstance()->register(MinecartChestEntity::class, function (World $world, CompoundTag $nbt): MinecartChestEntity {
            return new MinecartChestEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [MinecartChestEntity::TAG_NAME]);

        EntityFactory::getInstance()->register(MinecartHopperEntity::class, function (World $world, CompoundTag $nbt): MinecartHopperEntity {
            return new MinecartHopperEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [MinecartHopperEntity::TAG_NAME]);

        EntityFactory::getInstance()->register(MinecartTNTEntity::class, function (World $world, CompoundTag $nbt): MinecartTNTEntity {
            return new MinecartTNTEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, [MinecartTNTEntity::TAG_NAME]);
    }

    public function isMinecartItem(Item $item): bool {
        $minecartChest = new MinecartChest();
        $minecartHopper = new MinecartHopper();
        $minecartTNT = new MinecartTNT();

        $minecart = [
            VanillaItems::MINECART()->getTypeId(),
            $minecartChest->getTypeId(),
            $minecartHopper->getTypeId(),
            $minecartTNT->getTypeId()
        ];

        if (in_array($item->getTypeId(), $minecart)) {
            return true;
        }
        return false;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "checkdir":
                $player = $sender;
                if ($player instanceof Player) {
                    $yaw = $player->getLocation()->getYaw();
                    if (($yaw >= -45 && $yaw < 45) || ($yaw >= 315 && $yaw < 360) || ($yaw >= -360 && $yaw < -315)) {
                        $direction = "SOUTH";
                    } elseif ($yaw >= 45 && $yaw < 135) {
                        $direction = "WEST";
                    } elseif ($yaw >= 135 && $yaw < 225) {
                        $direction = "NORTH";
                    } elseif ($yaw >= 225 && $yaw < 315) {
                        $direction = "EAST";
                    }
                }
                $player->sendMessage("Direction: ". $direction . " / ". $player->getHorizontalFacing());
                $player->sendMessage("Yaw: ". (int)$player->getLocation()->getYaw());
                if (isset(self::$inMinecart[$player->getName()])) {
                    $minecart = self::$inMinecart[$player->getName()];
                    if ($minecart instanceof MinecartBase) {
                        $player->sendMessage("Minecart: ". $minecart->getHorizontalFacing() . "");
                        $player->sendMessage("Minecart Yaw: ". $minecart->getLocation()->getYaw() . "");
                    }
                }
                break;
        }
        return true;
    }

}