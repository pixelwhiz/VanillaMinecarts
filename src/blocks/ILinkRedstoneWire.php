<?php


namespace pixelwhiz\vanillaminecarts\blocks;

interface ILinkRedstoneWire {

    public function isConnect(int $face): bool;

}