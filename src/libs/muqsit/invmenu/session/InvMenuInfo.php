<?php

declare(strict_types=1);

namespace libs\muqsit\invmenu\session;

use libs\muqsit\invmenu\InvMenu;
use libs\muqsit\invmenu\type\graphic\InvMenuGraphic;

final class InvMenuInfo{

	public function __construct(
		readonly public InvMenu $menu,
		readonly public InvMenuGraphic $graphic,
		readonly public ?string $graphic_name
	){}
}