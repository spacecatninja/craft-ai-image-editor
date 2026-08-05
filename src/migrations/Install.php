<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\migrations;

use craft\db\Migration;
use craft\db\Table as CraftTable;

use spacecatninja\aiimageeditor\db\Table;

/**
 * Creates the plugin's database tables on install, and removes them on uninstall.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Table::SESSIONS)) {
            $this->createTable(Table::SESSIONS, [
                'id' => $this->primaryKey(),
                'sourceAssetId' => $this->integer(),
                'targetFolderId' => $this->integer(),
                'userId' => $this->integer()->notNull(),
                'driverHandle' => $this->string(64)->notNull(),
                'model' => $this->string()->notNull(),
                'status' => $this->string(32)->notNull()->defaultValue('active'),
                'resultAssetId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Table::SESSIONS, ['sourceAssetId']);
            $this->createIndex(null, Table::SESSIONS, ['targetFolderId']);
            $this->createIndex(null, Table::SESSIONS, ['userId']);
            $this->createIndex(null, Table::SESSIONS, ['status', 'dateUpdated']);

            $this->addForeignKey(null, Table::SESSIONS, ['sourceAssetId'], CraftTable::ASSETS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, Table::SESSIONS, ['targetFolderId'], CraftTable::VOLUMEFOLDERS, ['id'], 'SET NULL', null);
            $this->addForeignKey(null, Table::SESSIONS, ['userId'], CraftTable::USERS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, Table::SESSIONS, ['resultAssetId'], CraftTable::ASSETS, ['id'], 'SET NULL', null);
        }

        if (!$this->db->tableExists(Table::TURNS)) {
            $this->createTable(Table::TURNS, [
                'id' => $this->primaryKey(),
                'sessionId' => $this->integer()->notNull(),
                'prompt' => $this->text()->notNull(),
                'resultPath' => $this->string()->notNull(),
                'resolution' => $this->string(16)->notNull(),
                'isFinal' => $this->boolean()->notNull()->defaultValue(false),
                'providerMeta' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, Table::TURNS, ['sessionId']);

            $this->addForeignKey(null, Table::TURNS, ['sessionId'], Table::SESSIONS, ['id'], 'CASCADE', null);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists(Table::TURNS);
        $this->dropTableIfExists(Table::SESSIONS);

        return true;
    }
}
