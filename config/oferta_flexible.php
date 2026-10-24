<?php

return [
    'admision_poll_interval_ms' => max(10000, (int) env('OFERTA_FLEXIBLE_POLL_INTERVAL_MS', 120000)),
    'laboratorio' => [
        'stock_intervalo_segundos' => 120,
        'eess' => [
            ['renipress' => '5772', 'nombre' => 'CMI Santa Luzmila II'],
            ['renipress' => '5755', 'nombre' => 'CMI Laura Rodríguez'],
            ['renipress' => '5816', 'nombre' => 'CMI Dr. Martin Altuna'],
            ['renipress' => '5791', 'nombre' => 'CMI Tahuantinsuyo Bajo'],
            ['renipress' => '5821', 'nombre' => 'CMI Los Sureños'],
            ['renipress' => '5814', 'nombre' => 'CMI Ancón'],
            ['renipress' => '5766', 'nombre' => 'CS Santa Luzmila I'],
            ['renipress' => '5820', 'nombre' => 'CS Ensenada'],
            ['renipress' => '5792', 'nombre' => 'CS Tahuantinsuyo Alto'],
        ],
    ],
];
