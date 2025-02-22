<?php


namespace pixelwhiz\vanillaminecarts\blocks;

trait RedstoneComponentTrait {

    public function getStrongPower(int $face): int {
        return 0;
    }

    public function getWeakPower(int $face): int {
        return 0;
    }

    public function isPowerSource(): bool {
        return false;
    }

    public function onRedstoneUpdate(): void {
    }

}