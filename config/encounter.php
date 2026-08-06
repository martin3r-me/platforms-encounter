<?php

/**
 * Encounter — klinischer Kern (fachneutral).
 *
 * Die FK-dichte Kette: Termin → erbrachte Leistung (→ Katalog per morphMap)
 * → Anamnese/Befund → Bescheinigung + Textbaustein/Audience + Zustellung
 * → Recall/Fristen. Hängt an [patient]; Fachmodule (health, …) sitzen darauf.
 *
 * Konvention: englische Identifier, deutsche Anzeige-Labels.
 */

return [
    'routing' => [
        'mode'   => env('ENCOUNTER_MODE', 'path'),
        'prefix' => 'encounter',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'encounter.dashboard',
        'icon'  => 'heroicon-o-calendar-days',
        'order' => 35,
    ],

    'sidebar' => [
        [
            'group' => 'Encounter',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'encounter.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
                [
                    'label' => 'Termine',
                    'route' => 'encounter.appointments.index',
                    'icon'  => 'heroicon-o-calendar-days',
                ],
                [
                    'label' => 'Bescheinigungen',
                    'route' => 'encounter.certificates.index',
                    'icon'  => 'heroicon-o-document-check',
                ],
            ],
        ],
    ],
];
