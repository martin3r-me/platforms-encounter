<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\TextBlock;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class UpdateTextBlockTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const AUDIENCES = ['patient', 'employer', 'internal', 'private'];

    public function getName(): string
    {
        return 'encounter.text_blocks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /encounter/text-blocks - Updates a text block. REQUIRED: text_block_id. Optional: title, audience, content, position, active (content empty string clears).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'text_block_id' => ['type' => 'integer', 'description' => 'Id of the text block (REQUIRED).'],
                'title' => ['type' => 'string', 'description' => 'Optional.'],
                'audience' => ['type' => 'string', 'enum' => self::AUDIENCES, 'description' => 'Optional.'],
                'content' => ['type' => 'string', 'description' => 'Optional (empty string clears).'],
                'position' => ['type' => 'integer', 'description' => 'Optional.'],
                'active' => ['type' => 'boolean', 'description' => 'Optional.'],
            ],
            'required' => ['text_block_id'],
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

            $id = (int) ($arguments['text_block_id'] ?? 0);
            if ($id <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'text_block_id is required.');
            }

            $block = TextBlock::query()->forTeam($teamId)->find($id);
            if (!$block) {
                return ToolResult::error('NOT_FOUND', 'Text block not found (or no access).');
            }

            $payload = [];
            if (array_key_exists('title', $arguments) && $arguments['title'] !== null) {
                $title = trim((string) $arguments['title']);
                if ($title === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'title must not be empty.');
                }
                $payload['title'] = $title;
            }
            if (array_key_exists('audience', $arguments) && $arguments['audience'] !== null) {
                if (!in_array($arguments['audience'], self::AUDIENCES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'audience is invalid.');
                }
                $payload['audience'] = $arguments['audience'];
            }
            if (array_key_exists('content', $arguments)) {
                $payload['content'] = ($arguments['content'] === '' || $arguments['content'] === null) ? null : (string) $arguments['content'];
            }
            if (array_key_exists('position', $arguments) && $arguments['position'] !== null) {
                $payload['position'] = (int) $arguments['position'];
            }
            if (array_key_exists('active', $arguments) && $arguments['active'] !== null) {
                $payload['active'] = (bool) $arguments['active'];
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'No changes provided.');
            }

            $block->update($payload);

            return ToolResult::success([
                'id' => $block->id,
                'title' => $block->title,
                'audience' => $block->audience?->value,
                'team_id' => $block->team_id,
                'message' => "Text block '{$block->title}' updated successfully.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error updating text block: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'text_blocks', 'settings', 'update'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
