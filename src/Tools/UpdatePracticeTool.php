<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\Practice;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class UpdatePracticeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const FIELDS = ['name', 'street', 'postal_code', 'city', 'phone', 'physician', 'physician_suffix'];

    public function getName(): string
    {
        return 'encounter.practice.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /encounter/practice - Upserts the team\'s practice profile / letterhead (settings). All fields optional (empty string clears). One record per team.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'name' => ['type' => 'string', 'description' => 'Practice name.'],
                'street' => ['type' => 'string', 'description' => 'Street.'],
                'postal_code' => ['type' => 'string', 'description' => 'Postal code.'],
                'city' => ['type' => 'string', 'description' => 'City.'],
                'phone' => ['type' => 'string', 'description' => 'Phone.'],
                'physician' => ['type' => 'string', 'description' => 'Responsible physician.'],
                'physician_suffix' => ['type' => 'string', 'description' => 'Physician suffix / qualification.'],
            ],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'No user in context.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $payload = [];
            foreach (self::FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $payload[$f] = ($arguments[$f] === '' || $arguments[$f] === null) ? null : (string) $arguments[$f];
                }
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'No fields provided.');
            }

            $practice = Practice::query()->updateOrCreate(['team_id' => $teamId], $payload);

            return ToolResult::success([
                'team_id' => $teamId,
                'name' => $practice->name,
                'physician' => $practice->physician,
                'message' => 'Practice profile saved.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error saving practice: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'practice', 'settings', 'update'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
