<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Encounter\Models\AnamnesisQuestion;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class ListAnamnesisQuestionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.anamnesis_questions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /encounter/anamnesis-questions - Lists the anamnesis question catalog (settings). '
            . 'Questions are the team-customizable layer bound (optionally) to an ArbMedVV occasion via occasion_id. '
            . 'Params: team_id (optional), occasion_id (filter by ArbMedVV occasion), sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'occasion_id' => ['type' => 'integer', 'description' => 'Optional: filter to questions bound to this ArbMedVV occasion.'],
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

            $query = AnamnesisQuestion::query()->forTeam($teamId);

            if (!empty($arguments['occasion_id'])) {
                $query->where('catalog_type', 'arbmedvv_occasion')->where('catalog_id', (int) $arguments['occasion_id']);
            }

            $this->applyStandardFilters($query, $arguments, ['section', 'examiner_scope', 'active', 'created_at']);
            $this->applyStandardSort($query, $arguments, ['position', 'section', 'created_at'], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (AnamnesisQuestion $q) => [
                'id'             => $q->id,
                'text'           => $q->text,
                'type'           => $q->type instanceof \Platform\Encounter\Enums\QuestionType ? $q->type->value : $q->type,
                'options'        => $q->options,
                'section'        => $q->section,
                'examiner_scope' => $q->examiner_scope,
                'occasion_id'    => $q->catalog_type === 'arbmedvv_occasion' ? (int) $q->catalog_id : null,
                'position'       => $q->position,
                'active'         => (bool) $q->active,
                'team_id'        => $q->team_id,
            ])->values()->toArray();

            return ToolResult::success(['data' => $data, 'pagination' => $result['pagination'] ?? null, 'team_id' => $teamId]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error loading anamnesis questions: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true, 'category' => 'read', 'tags' => ['encounter', 'anamnesis_questions', 'settings', 'list'],
            'risk_level' => 'safe', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
