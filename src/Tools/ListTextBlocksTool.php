<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Encounter\Models\TextBlock;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class ListTextBlocksTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.text_blocks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /encounter/text-blocks - Lists reusable text blocks (settings). Params: team_id (optional), audience (patient|employer|internal|private), sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'audience' => ['type' => 'string', 'enum' => ['patient', 'employer', 'internal', 'private'], 'description' => 'Optional: filter by audience.'],
            ],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $query = TextBlock::query()->forTeam($teamId);
            if (isset($arguments['audience'])) {
                $query->where('audience', $arguments['audience']);
            }

            $this->applyStandardFilters($query, $arguments, ['audience', 'active', 'created_at']);
            $this->applyStandardSort($query, $arguments, ['position', 'audience', 'title', 'created_at'], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (TextBlock $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'content' => $b->content,
                'audience' => $b->audience?->value,
                'position' => $b->position,
                'active' => (bool) $b->active,
                'team_id' => $b->team_id,
            ])->values()->toArray();

            return ToolResult::success(['data' => $data, 'pagination' => $result['pagination'] ?? null, 'team_id' => $teamId]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error loading text blocks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true, 'category' => 'read', 'tags' => ['encounter', 'text_blocks', 'settings', 'list'],
            'risk_level' => 'safe', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
