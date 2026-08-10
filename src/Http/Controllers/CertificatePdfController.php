<?php

namespace Platform\Encounter\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Certificate;

/**
 * Rendert eine Bescheinigung als PDF (DomPDF) aus dem EINGEFRORENEN content — also
 * audience-korrekt (beim Arbeitgeber ohne Befunde). Team-scoped. Öffnet inline (stream),
 * damit der Arzt drucken/speichern kann.
 */
class CertificatePdfController extends Controller
{
    public function __invoke(Certificate $certificate)
    {
        abort_unless(
            Auth::check() && $certificate->team_id === Auth::user()->currentTeam?->id,
            403,
            'Zugriff verweigert'
        );

        abort_unless(
            class_exists(\Barryvdh\DomPDF\Facade\Pdf::class),
            500,
            'PDF-Erzeugung ist auf dieser Instanz nicht verfügbar.'
        );

        $html = view('encounter::pdf.certificate', ['certificate' => $certificate])->render();

        $filename = str(($certificate->title ?: 'bescheinigung') . '-' . $certificate->uuid)
            ->slug('-')->append('.pdf')->toString();

        return Pdf::loadHTML($html)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)   // Briefkopf/Logo/Unterschrift ggf. als URL
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }
}
