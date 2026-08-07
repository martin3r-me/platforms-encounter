<?php

namespace Platform\Encounter\Contracts;

/**
 * CertificateContextProvider — ein Fachmodul (z.B. occupational) liefert den fachlichen
 * Kontext einer Bescheinigung graph-nativ bei: Arbeitgeber (Firma) + Vorsorge-Pflichten
 * (Anlass/Art/Frist). encounter bleibt fachneutral und friert die Referenz in die
 * ausgestellte Bescheinigung ein (AMR 6.3, Datenübernahme als Referenz).
 *
 * Rückgabe (alles optional, null wenn nichts beitragbar):
 *  [
 *    'employer'   => ['name'=>'…', 'entity_id'=>12, 'address'=>'…'|null]|null,
 *    'provisions' => [
 *      ['occasion_id'=>51, 'occasion_title'=>'Lärm …', 'care_type'=>'Pflichtvorsorge', 'next_due'=>'2027-08-06'],
 *      …
 *    ],
 *  ]
 */
interface CertificateContextProvider
{
    public function contextFor(int $patientId, int $teamId): ?array;
}
