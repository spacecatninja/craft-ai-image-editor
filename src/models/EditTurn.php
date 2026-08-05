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

use craft\base\Model;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;

use DateTime;

use spacecatninja\aiimageeditor\records\EditTurn as EditTurnRecord;

/**
 * One turn in an edit session: a prompt and the resulting image.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditTurn extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var DateTime|null
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var bool Whether this turn is a finalize candidate, i.e. the high
     * resolution regeneration created when the user accepts the result.
     */
    public bool $isFinal = false;

    /**
     * @var string The user's natural-language instruction for this turn.
     */
    public string $prompt = '';

    /**
     * @var array Raw provider response metadata for this turn.
     */
    public array $providerMeta = [];

    /**
     * @var string The resolution tier that was used for this turn.
     */
    public string $resolution = '';

    /**
     * @var string The result image filename, relative to the session's temp directory.
     */
    public string $resultPath = '';

    /**
     * @var int|null
     */
    public ?int $sessionId = null;

    // Public Methods
    // =========================================================================

    /**
     * Creates a model from an active record.
     *
     * @param EditTurnRecord $record
     * @return self
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public static function fromRecord(EditTurnRecord $record): self
    {
        $model = new self();
        $model->id = $record->id;
        $model->sessionId = $record->sessionId;
        $model->prompt = $record->prompt;
        $model->resultPath = $record->resultPath;
        $model->resolution = $record->resolution;
        $model->isFinal = (bool)$record->isFinal;
        $model->providerMeta = $record->providerMeta !== null ? (Json::decodeIfJson($record->providerMeta) ?: []) : [];
        $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated) ?: null;

        return $model;
    }
}
