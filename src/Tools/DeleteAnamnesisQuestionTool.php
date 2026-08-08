<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\AnamnesisQuestion;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class DeleteAnamnesisQuestionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.anamnesis_questions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /encounter/anamnesis-questions - Deletes an anamnesis question. REQUIRED: question_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'question_id' => ['type' => 'integer', 'description' => 'The question id (REQUIRED).'],
            ],
            'required' => ['question_id'],
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

            $id = (int) ($arguments['question_id'] ?? 0);
            $question = AnamnesisQuestion::query()->forTeam($teamId)->find($id);
            if (!$question) {
                return ToolResult::error('NOT_FOUND', 'Anamnesis question not found.');
            }

            $question->delete();

            return ToolResult::success([
                'id'      => $id,
                'message' => 'Anamnesis question deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error deleting anamnesis question: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'anamnesis_questions', 'settings', 'delete'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
