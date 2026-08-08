<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\AnamnesisQuestion;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class UpdateAnamnesisQuestionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const TYPES = ['yes_no', 'text', 'scale', 'choice'];

    public function getName(): string
    {
        return 'encounter.anamnesis_questions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /encounter/anamnesis-questions - Updates an anamnesis question. REQUIRED: question_id. '
            . 'Optional: text, type, options, section, examiner_scope, occasion_id (null clears the ArbMedVV binding), position, active.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'question_id'    => ['type' => 'integer', 'description' => 'The question id (REQUIRED).'],
                'text'           => ['type' => 'string', 'description' => 'Optional: new text.'],
                'type'           => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'Optional: answer type.'],
                'options'        => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: options for choice/scale.'],
                'section'        => ['type' => 'string', 'description' => 'Optional: grouping section.'],
                'examiner_scope' => ['type' => 'string', 'description' => 'Optional: examiner group (null=all).'],
                'occasion_id'    => ['type' => 'integer', 'description' => 'Optional: ArbMedVV occasion binding (send null to clear).'],
                'position'       => ['type' => 'integer', 'description' => 'Optional: sort order.'],
                'active'         => ['type' => 'boolean', 'description' => 'Optional: active flag.'],
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

            $payload = [];

            if (array_key_exists('text', $arguments)) {
                $t = trim((string) $arguments['text']);
                if ($t === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'text must not be empty.');
                }
                $payload['text'] = $t;
            }
            if (array_key_exists('type', $arguments)) {
                if (!in_array($arguments['type'], self::TYPES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'type is invalid.');
                }
                $payload['type'] = $arguments['type'];
            }
            if (array_key_exists('options', $arguments)) {
                $payload['options'] = is_array($arguments['options']) ? array_values($arguments['options']) : null;
            }
            if (array_key_exists('section', $arguments)) {
                $payload['section'] = $arguments['section'] !== '' ? (string) $arguments['section'] : null;
            }
            if (array_key_exists('examiner_scope', $arguments)) {
                $payload['examiner_scope'] = $arguments['examiner_scope'] !== '' ? (string) $arguments['examiner_scope'] : null;
            }
            if (array_key_exists('occasion_id', $arguments)) {
                $occasionId = !empty($arguments['occasion_id']) ? (int) $arguments['occasion_id'] : null;
                if ($occasionId && class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
                    $exists = \Platform\Arbmedvv\Models\Occasion::query()
                        ->where('team_id', $teamId)->whereKey($occasionId)->exists();
                    if (!$exists) {
                        return ToolResult::error('VALIDATION_ERROR', 'occasion_id not found in the ArbMedVV catalog.');
                    }
                }
                $payload['catalog_type'] = $occasionId ? 'arbmedvv_occasion' : null;
                $payload['catalog_id']   = $occasionId;
            }
            if (array_key_exists('position', $arguments)) {
                $payload['position'] = (int) $arguments['position'];
            }
            if (array_key_exists('active', $arguments)) {
                $payload['active'] = (bool) $arguments['active'];
            }

            if (!empty($payload)) {
                $question->update($payload);
            }

            return ToolResult::success([
                'id'          => $question->id,
                'text'        => $question->text,
                'occasion_id' => $question->catalog_type === 'arbmedvv_occasion' ? (int) $question->catalog_id : null,
                'message'     => 'Anamnesis question updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error updating anamnesis question: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'anamnesis_questions', 'settings', 'update'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
