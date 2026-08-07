<?php

namespace Platform\Encounter;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Models\Service;
use Platform\Encounter\Models\Certificate;
use Platform\Encounter\Models\TextBlock;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EncounterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/encounter.php', 'encounter');

        // Verlauf-Registry: Fachmodule (encounter, occupational, später lab) liefern Akte-Einträge.
        $this->app->singleton(\Platform\Encounter\Services\JournalRegistry::class);

        // Briefkopf-Registry: practice-Modul (Standort + Arzt) liefert, encounter-Fallback als Basis.
        $this->app->singleton(\Platform\Encounter\Services\LetterheadRegistry::class);
    }

    public function boot(): void
    {
        // Eigene Modelle über stabile Aliase morphbar machen (Cross-Modul-Referenzen).
        Relation::morphMap([
            'encounter_appointment' => Appointment::class,
            'encounter_service'     => Service::class,
            'encounter_certificate' => Certificate::class,
            'encounter_text_block'  => TextBlock::class,
        ]);

        if (
            config()->has('encounter.routing') &&
            config()->has('encounter.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'encounter',
                'title'      => 'Sprechstunde',
                'routing'    => config('encounter.routing'),
                'guard'      => config('encounter.guard'),
                'navigation' => config('encounter.navigation'),
                'sidebar'    => config('encounter.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('encounter')) {
            ModuleRouter::group('encounter', function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/encounter.php' => config_path('encounter.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'encounter');

        $this->registerLivewireComponents();

        $this->registerTools();

        $this->registerPatientPanel();

        $this->registerJournalProvider();

        $this->registerLetterheadProvider();
    }

    /**
     * Registriert den encounter-internen Briefkopf-Fallback (Priorität 0).
     */
    protected function registerLetterheadProvider(): void
    {
        try {
            resolve(\Platform\Encounter\Services\LetterheadRegistry::class)
                ->register(new \Platform\Encounter\Letterhead\EncounterPracticeLetterheadProvider());
        } catch (\Throwable $e) {
            // ignorieren
        }
    }

    /**
     * Registriert den eigenen Verlauf-Provider (Termine → Akte-Einträge).
     */
    protected function registerJournalProvider(): void
    {
        try {
            resolve(\Platform\Encounter\Services\JournalRegistry::class)
                ->register(new \Platform\Encounter\Journal\EncounterJournalProvider());
        } catch (\Throwable $e) {
            // ignorieren
        }
    }

    /**
     * Dockt das „Termine"-Panel an die Patienten-Akte an (wenn patient-Modul da ist).
     */
    protected function registerPatientPanel(): void
    {
        try {
            resolve(\Platform\Patient\Services\PatientPanelRegistry::class)
                ->register(new \Platform\Encounter\Patient\EncounterPatientPanel());
        } catch (\Throwable $e) {
            // patient-Modul nicht verfügbar — ignorieren.
        }
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            $registry->register(new \Platform\Encounter\Tools\ListAppointmentsTool());
            $registry->register(new \Platform\Encounter\Tools\GetAppointmentTool());
            $registry->register(new \Platform\Encounter\Tools\CreateAppointmentTool());
            $registry->register(new \Platform\Encounter\Tools\UpdateAppointmentTool());
            $registry->register(new \Platform\Encounter\Tools\DeleteAppointmentTool());

            // Settings: Textbausteine
            $registry->register(new \Platform\Encounter\Tools\ListTextBlocksTool());
            $registry->register(new \Platform\Encounter\Tools\CreateTextBlockTool());
            $registry->register(new \Platform\Encounter\Tools\UpdateTextBlockTool());
            $registry->register(new \Platform\Encounter\Tools\DeleteTextBlockTool());

            // Settings: Feld-Definitionen
            $registry->register(new \Platform\Encounter\Tools\ListFieldDefinitionsTool());
            $registry->register(new \Platform\Encounter\Tools\CreateFieldDefinitionTool());
            $registry->register(new \Platform\Encounter\Tools\UpdateFieldDefinitionTool());
            $registry->register(new \Platform\Encounter\Tools\DeleteFieldDefinitionTool());

            // Settings: Praxis-Profil
            $registry->register(new \Platform\Encounter\Tools\GetPracticeTool());
            $registry->register(new \Platform\Encounter\Tools\UpdatePracticeTool());
        } catch (\Throwable $e) {
            // ToolRegistry nicht verfügbar — ignorieren.
        }
    }

    /**
     * Registriert alle Livewire-Komponenten unter src/Livewire/ rekursiv.
     * Datei src/Livewire/Appointment/Index.php → Alias encounter.appointment.index
     */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Encounter\\Livewire';
        $prefix = 'encounter';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
