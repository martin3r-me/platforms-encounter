<?php

use Platform\Encounter\Livewire\Dashboard;
use Platform\Encounter\Livewire\Cockpit\Show as CockpitShow;
use Platform\Encounter\Livewire\Appointment\Index as AppointmentIndex;
use Platform\Encounter\Livewire\Appointment\Show as AppointmentShow;
use Platform\Encounter\Livewire\Certificate\Index as CertificateIndex;
use Platform\Encounter\Livewire\Certificate\Show as CertificateShow;
use Platform\Encounter\Livewire\Settings\Index as SettingsIndex;
use Platform\Encounter\Livewire\Record\Show as RecordShow;
use Platform\Encounter\Livewire\Anamnesis\History as AnamnesisHistoryView;

/*
 * Encounter (Modul-Titel „Akte") — Web-Routes (Prefix 'encounter' aus config).
 * Der einzelne Termin ist ein Eintrag; die Akte/{patient} ist der Verlauf.
 */

Route::get('/', CockpitShow::class)->name('encounter.cockpit');
Route::get('/kalender', Dashboard::class)->name('encounter.dashboard');
Route::get('/akte/{patient}', RecordShow::class)->name('encounter.akte.show');
Route::get('/anamnese-verlauf/{patient}', AnamnesisHistoryView::class)->name('encounter.anamnesis.history');
Route::get('/appointments', AppointmentIndex::class)->name('encounter.appointments.index');
Route::get('/appointments/{appointment}', AppointmentShow::class)->name('encounter.appointments.show');
Route::get('/certificates', CertificateIndex::class)->name('encounter.certificates.index');
Route::get('/certificates/{certificate}', CertificateShow::class)->name('encounter.certificates.show');
Route::get('/settings', SettingsIndex::class)->name('encounter.settings');
