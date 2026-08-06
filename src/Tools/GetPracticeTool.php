<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Encounter\Models\Practice;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class GetPracticeTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.practice.GET';
    }

    public function getDescription(): string
    {
        return 'GET /encounter/practice - Shows the team\'s practice profile / letterhead (settings). One record per team.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $practice = Practice::query()->forTeam($teamId)->first();

            return ToolResult::success([
                'team_id' => $teamId,
                'exists' => (bool) $practice,
                'name' => $practice?->name,
                'street' => $practice?->street,
                'postal_code' => $practice?->postal_code,
                'city' => $practice?->city,
                'phone' => $practice?->phone,
                'physician' => $practice?->physician,
                'physician_suffix' => $practice?->physician_suffix,
                'has_signature' => (bool) $practice?->signature,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error loading practice: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true, 'category' => 'read', 'tags' => ['encounter', 'practice', 'settings', 'get'],
            'risk_level' => 'safe', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
