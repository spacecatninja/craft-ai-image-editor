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
use Illuminate\Database\Eloquent\Relations\HasMany;
use SpaceCatNinja\AiImageEditor\Database\Table;

/**
 * An editing session.
 *
 * @property int $id
 * @property int|null $sourceAssetId
 * @property int|null $targetFolderId
 * @property int $userId
 * @property string $driverHandle
 * @property string $model
 * @property string $status
 * @property int|null $resultAssetId
 * @property \Carbon\Carbon $dateCreated
 * @property \Carbon\Carbon $dateUpdated
 * @property string $uid
 */
class EditSession extends BaseModel
{
    use HasUid;

    protected $table = Table::SESSIONS;

    protected $casts = [
        'sourceAssetId' => 'integer',
        'targetFolderId' => 'integer',
        'userId' => 'integer',
        'resultAssetId' => 'integer',
        'dateCreated' => 'datetime',
        'dateUpdated' => 'datetime',
    ];

    /**
     * @return HasMany<EditTurn, $this>
     */
    public function turns(): HasMany
    {
        return $this->hasMany(EditTurn::class, 'sessionId');
    }
}
