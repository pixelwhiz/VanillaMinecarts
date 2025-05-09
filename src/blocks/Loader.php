<?php


namespace pixelwhiz\vanillaminecarts\blocks;


abstract class Loader {

    protected string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    abstract public function load(): void;

}