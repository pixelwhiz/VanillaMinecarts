<?php


namespace pixelwhiz\vanillaminecarts\blocks;

use Closure;
use pocketmine\block\Block;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\CreativeInventory;

class BlockLoader extends Loader {

    public Block $block;
    public bool $addCreative;

    public function __construct(string $name, Block $block, bool $addCreative = false) {
        parent::__construct($name);

        $this->block = $block;
        $this->addCreative = $addCreative;
    }

    public static function createBlock(string $name, Block $oldBlock, int $id, Closure $callback, ?string $class = null): self {
        $bid = $oldBlock->getIdInfo();
        if ($class !== null) {
            $bid = new BlockIdentifier($id);
        }

        $block = $callback($bid, $oldBlock->getName(), $oldBlock->getBreakInfo());

        return new self($name, $block);
    }

    public function load(): void {
        RuntimeBlockStateRegistry::getInstance()->register($this->block);
        if ($this->addCreative) CreativeInventory::getInstance()->add($this->block->asItem());
    }

}