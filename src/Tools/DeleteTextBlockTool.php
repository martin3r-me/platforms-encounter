<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\TextBlock;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class DeleteTextBlockTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.text_blocks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /encounter/text-blocks - Deletes a text block. REQUIRED: text_block_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'text_block_id' => ['type' => 'integer', 'description' => 'Id of the text block (REQUIRED).'],
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

            $title = $block->title;
            $block->delete();

            return ToolResult::success(['id' => $id, 'message' => "Text block '{$title}' deleted."]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error deleting text block: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'text_blocks', 'settings', 'delete'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
