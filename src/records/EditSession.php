<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\records;

use craft\db\ActiveRecord;

use spacecatninja\aiimageeditor\db\Table;

/**
 * Active record for the sessions table.
 *
 * @property int         $id
 * @property int|null    $sourceAssetId
 * @property int|null    $targetFolderId
 * @property int         $userId
 * @property string      $driverHandle
 * @property string      $model
 * @property string      $status
 * @property int|null    $resultAssetId
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditSession extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::SESSIONS;
    }
}
