<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Contracts\CertificateLifecycleListener;

/**
 * CertificateLifecycleRegistry — sammelt die Fachmodul-Listener und benachrichtigt sie, wenn
 * eine Bescheinigung ausgestellt wurde. Singleton; Fachmodule rufen ->register(...) in ihrem
 * boot(). Ein defekter Listener darf die Ausstellung NICHT brechen (try/catch je Listener).
 */
class CertificateLifecycleRegistry
{
    /** @var array<int,CertificateLifecycleListener> */
    protected array $listeners = [];

    public function register(CertificateLifecycleListener $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Meldet allen Listenern, dass eine Bescheinigung ausgestellt wurde.
     */
    public function notifyIssued(array $event): void
    {
        foreach ($this->listeners as $listener) {
            try {
                $listener->certificateIssued($event);
            } catch (\Throwable $e) {
                // Fortschreibung eines Fachmoduls darf die Bescheinigung nicht gefährden.
            }
        }
    }
}
