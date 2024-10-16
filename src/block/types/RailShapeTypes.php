<?php

namespace pixelwhiz\vanillaminecarts\block\types;


/**
 * Check using $block->getShape() or $rail->getShape() $block or $rail must be pocketmine\block\Rail
 * after that u can check getShape() function
 * @method Rail getShape()
 *
 * @var int STRAIGHT_NORTH_SOUTH
 * @var int SLOPED_ASCENDING_NORTH
 * @var int SLOPED_DESCENDING_SOUTH
 * @var int STRAIGHT_EAST_WEST
 * @var int SLOPED_ASCENDING_EAST
 * @var int SLOPED_DESCENDING_WEST
 * @var int CURVED_SOUTH_EAST
 * @var int CURVED_SOUTH_WEST
 * @var int CURVED_NORTH_EAST
 * @var int CURVED_NORTH_WEST
 *
 */

class RailShapeTypes {

    public const STRAIGHT_NORTH_SOUTH = 0;
    public const SLOPED_ASCENDING_NORTH = 4;
    public const SLOPED_DESCENDING_SOUTH = 5;


    public const STRAIGHT_EAST_WEST = 1;
    public const SLOPED_ASCENDING_EAST = 2;
    public const SLOPED_DESCENDING_WEST = 3;


    public const CURVED_SOUTH_EAST = 9;
    public const CURVED_SOUTH_WEST = 8;


    public const CURVED_NORTH_EAST = 6;
    public const CURVED_NORTH_WEST = 7;

}