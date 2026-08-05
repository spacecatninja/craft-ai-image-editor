<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\models;

use Craft;
use craft\base\Model;
use craft\elements\Asset;
use craft\helpers\DateTimeHelper;

use DateTime;

use spacecatninja\aiimageeditor\enums\SessionStatus;
use spacecatninja\aiimageeditor\records\EditSession as EditSessionRecord;

/**
 * An edit session, i.e. one editor's chat-style editing of one source asset.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditSession extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var string The handle of the edit driver used by this session.
     */
    public string $driverHandle = '';

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string The model used by this session.
     */
    public string $model = '';

    /**
     * @var int|null The ID of the asset created when the session was finalized.
     */
    public ?int $resultAssetId = null;

    /**
     * @var int|null The ID of the source asset being edited.
     */
    public ?int $sourceAssetId = null;

    /**
     * @var SessionStatus
     */
    public SessionStatus $status = SessionStatus::Active;

    /**
     * @var int|null The ID of the folder that a generation session saves its
     * result into. Null for edit sessions, which save relative to the source asset.
     */
    public ?int $targetFolderId = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @var int|null The ID of the user who owns the session.
     */
    public ?int $userId = null;

    // Private Properties
    // =========================================================================

    /**
     * @var Asset|null
     * @see getSourceAsset()
     */
    private ?Asset $_sourceAsset = null;

    // Public Methods
    // =========================================================================

    /**
     * Creates a model from an active record.
     *
     * @param EditSessionRecord $record
     * @return self
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public static function fromRecord(EditSessionRecord $record): self
    {
        $model = new self();
        $model->id = $record->id;
        $model->sourceAssetId = $record->sourceAssetId;
        $model->targetFolderId = $record->targetFolderId;
        $model->userId = $record->userId;
        $model->driverHandle = $record->driverHandle;
        $model->model = $record->model;
        $model->status = SessionStatus::tryFrom($record->status) ?? SessionStatus::Active;
        $model->resultAssetId = $record->resultAssetId;
        $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated) ?: null;
        $model->dateUpdated = DateTimeHelper::toDateTime($record->dateUpdated) ?: null;
        $model->uid = $record->uid;

        return $model;
    }

    /**
     * Returns the source asset being edited in this session.
     *
     * @return Asset|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSourceAsset(): ?Asset
    {
        if ($this->_sourceAsset === null && $this->sourceAssetId !== null) {
            $this->_sourceAsset = Craft::$app->getAssets()->getAssetById($this->sourceAssetId);
        }

        return $this->_sourceAsset;
    }

    /**
     * Returns whether this is a generation session, i.e. one that creates a
     * brand new image rather than editing an existing asset.
     *
     * @return bool
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function isGeneration(): bool
    {
        return $this->sourceAssetId === null;
    }
}
