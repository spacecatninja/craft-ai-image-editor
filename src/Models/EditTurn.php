<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use SpaceCatNinja\AiImageEditor\Database\Table;

/**
 * A turn in an editing session: one prompt, and the image it produced.
 *
 * @property int $id
 * @property int $sessionId
 * @property string $prompt
 * @property string $resultPath
 * @property string $resolution
 * @property bool $isFinal
 * @property string|null $providerMeta
 * @property \Carbon\Carbon $dateCreated
 * @property \Carbon\Carbon $dateUpdated
 * @property string $uid
 */
class EditTurn extends BaseModel
{
    use HasUid;

    protected $table = Table::TURNS;

    protected $casts = [
        'sessionId' => 'integer',
        'isFinal' => 'boolean',
        'dateCreated' => 'datetime',
        'dateUpdated' => 'datetime',
    ];
}
