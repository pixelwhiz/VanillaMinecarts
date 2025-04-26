<?php

declare(strict_types=1);

namespace libs\muqsit\invmenu\type\util\builder;

use libs\muqsit\invmenu\type\InvMenuType;

interface InvMenuTypeBuilder{

	public function build() : InvMenuType;
}