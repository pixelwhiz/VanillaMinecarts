<?php

namespace pixelwhiz\vanillaminecarts;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\world\World;

class VanillaMinecarts extends PluginBase {

    protected function onEnable(): void
    {
        Server::getInstance()->getPluginManager()->registerEvents(new EventHandler(), $this);

        EntityFactory::getInstance()->register(MinecartEntity::class, function (World $world, CompoundTag $nbt): MinecartEntity {
            return new MinecartEntity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["Minecart"]);
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "checkdir":
                $player = $sender;
                if ($player instanceof Player) {
                    $yaw = $player->getLocation()->getYaw();
                    if (($yaw >= -45 && $yaw < 45) || ($yaw >= 315 && $yaw < 360) || ($yaw >= -360 && $yaw < -315)) {
                        $direction = 0;
                    } elseif ($yaw >= 45 && $yaw < 135) {
                        $direction = 2;
                    } elseif ($yaw >= 135 && $yaw < 225) {
                        $direction = 1;
                    } elseif ($yaw >= 225 && $yaw < 315) {
                        $direction = 3;
                    }
                }
                $player->sendMessage("Direction: ". $direction);
                break;
        }
        return true;
    }

}