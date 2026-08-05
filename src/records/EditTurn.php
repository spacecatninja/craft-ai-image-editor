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
 * Active record for the turns table.
 *
 * @property int         $id
 * @property int         $sessionId
 * @property string      $prompt
 * @property string      $resultPath
 * @property string      $resolution
 * @property bool        $isFinal
 * @property string|null $providerMeta
 * @property string      $dateCreated
 * @property string      $dateUpdated
 * @property string      $uid
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditTurn extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TURNS;
    }
}
