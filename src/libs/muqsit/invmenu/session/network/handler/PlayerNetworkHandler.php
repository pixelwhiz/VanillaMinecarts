<?php

declare(strict_types=1);

namespace libs\muqsit\invmenu\session\network\handler;

use Closure;
use libs\muqsit\invmenu\session\network\NetworkStackLatencyEntry;

interface PlayerNetworkHandler{

	public function createNetworkStackLatencyEntry(Closure $then) : NetworkStackLatencyEntry;
}