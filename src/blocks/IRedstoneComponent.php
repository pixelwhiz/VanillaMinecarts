<?php


namespace pixelwhiz\vanillaminecarts\blocks;

interface IRedstoneComponent {

    public function getStrongPower(int $face): int;

    public function getWeakPower(int $face): int;

    public function isPowerSource(): bool;

    public function onRedstoneUpdate(): void;

}