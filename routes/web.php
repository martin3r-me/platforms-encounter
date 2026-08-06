<?php

use Platform\Encounter\Livewire\Dashboard;
use Platform\Encounter\Livewire\Appointment\Index as AppointmentIndex;
use Platform\Encounter\Livewire\Appointment\Show as AppointmentShow;
use Platform\Encounter\Livewire\Certificate\Index as CertificateIndex;
use Platform\Encounter\Livewire\Certificate\Show as CertificateShow;
use Platform\Encounter\Livewire\Settings\Index as SettingsIndex;
use Platform\Encounter\Livewire\Record\Show as RecordShow;

/*
 * Encounter (Modul-Titel „Akte") — Web-Routes (Prefix 'encounter' aus config).
 * Der einzelne Termin ist ein Eintrag; die Akte/{patient} ist der Verlauf.
 */

Route::get('/', Dashboard::class)->name('encounter.dashboard');
Route::get('/akte/{patient}', RecordShow::class)->name('encounter.akte.show');
Route::get('/appointments', AppointmentIndex::class)->name('encounter.appointments.index');
Route::get('/appointments/{appointment}', AppointmentShow::class)->name('encounter.appointments.show');
Route::get('/certificates', CertificateIndex::class)->name('encounter.certificates.index');
Route::get('/certificates/{certificate}', CertificateShow::class)->name('encounter.certificates.show');
Route::get('/settings', SettingsIndex::class)->name('encounter.settings');
