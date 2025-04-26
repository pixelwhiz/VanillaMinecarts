<?php

declare(strict_types=1);

namespace libs\muqsit\invmenu\type\graphic\network;

use libs\muqsit\invmenu\session\InvMenuInfo;
use libs\muqsit\invmenu\session\PlayerSession;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;

interface InvMenuGraphicNetworkTranslator{

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void;
}