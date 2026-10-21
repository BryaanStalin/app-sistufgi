<?php

return [
    'admision_poll_interval_ms' => max(10000, (int) env('OFERTA_FLEXIBLE_POLL_INTERVAL_MS', 120000)),
];
