<?php

namespace Platform\Encounter\Contracts;

/**
 * CertificateLifecycleListener — ein Fachmodul (z.B. occupational) reagiert darauf, dass
 * encounter eine Bescheinigung AUSGESTELLT hat, und schreibt seinen eigenen Zustand fort
 * (z.B. die Vorsorge-Kartei: last_done_at + next_due_at). encounter bleibt fachneutral und
 * kennt weder Provision noch Kartei — es meldet nur das Ereignis. Gegenstück (Push) zum
 * CertificateContextProvider (Pull).
 *
 * Event-Payload:
 *  [
 *    'certificate_id' => 42,
 *    'patient_id'     => 7,
 *    'team_id'        => 3,
 *    'occasion_type'  => 'arbmedvv_occasion'|null,   // Vorsorgeanlass der Anamnese
 *    'occasion_id'    => 51|null,
 *    'examined_on'    => '2026-08-06'|null,           // Tag der Vorsorge (Termindatum)
 *    'audience'       => 'patient'|'employer',
 *  ]
 */
interface CertificateLifecycleListener
{
    public function certificateIssued(array $event): void;
}
