<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Database\Migrations;

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Database\Table as CraftTable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use SpaceCatNinja\AiImageEditor\Database\Table;

/**
 * Creates the edit session tables on install, and drops them on uninstall.
 *
 * The tables are skipped when they already exist, so a site upgrading from the
 * Craft 5 version keeps its sessions.
 */
class Install extends Migration
{
    public function up(): void
    {
        $this->createTables();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        Schema::dropIfExists(Table::TURNS);
        Schema::dropIfExists(Table::SESSIONS);
    }

    private function createTables(): void
    {
        if (!Schema::hasTable(Table::SESSIONS)) {
            Schema::create(Table::SESSIONS, function(Blueprint $table): void {
                // Signed int ids, to match the Craft tables the foreign keys point at
                $table->integer('id', true);
                $table->integer('sourceAssetId')->nullable();
                $table->integer('targetFolderId')->nullable();
                $table->integer('userId');
                $table->string('driverHandle', 64);
                $table->string('model');
                $table->string('status', 32)->default('active');
                $table->integer('resultAssetId')->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');

                $table->index(['sourceAssetId']);
                $table->index(['targetFolderId']);
                $table->index(['userId']);
                $table->index(['status', 'dateUpdated']);
            });
        }

        if (!Schema::hasTable(Table::TURNS)) {
            Schema::create(Table::TURNS, function(Blueprint $table): void {
                $table->integer('id', true);
                $table->integer('sessionId');
                $table->text('prompt');
                $table->string('resultPath');
                $table->string('resolution', 16);
                $table->boolean('isFinal')->default(false);
                $table->text('providerMeta')->nullable();
                $table->dateTime('dateCreated');
                $table->dateTime('dateUpdated');
                $table->char('uid', 36)->default('0');

                $table->index(['sessionId']);
            });
        }
    }

    private function addForeignKeys(): void
    {
        if (Schema::getForeignKeys(Table::SESSIONS) === []) {
            Schema::table(Table::SESSIONS, function(Blueprint $table): void {
                $table->foreign('sourceAssetId')->references('id')->on(CraftTable::ASSETS)->cascadeOnDelete();
                $table->foreign('targetFolderId')->references('id')->on(CraftTable::VOLUMEFOLDERS)->nullOnDelete();
                $table->foreign('userId')->references('id')->on(CraftTable::USERS)->cascadeOnDelete();
                $table->foreign('resultAssetId')->references('id')->on(CraftTable::ASSETS)->nullOnDelete();
            });
        }

        if (Schema::getForeignKeys(Table::TURNS) === []) {
            Schema::table(Table::TURNS, function(Blueprint $table): void {
                $table->foreign('sessionId')->references('id')->on(Table::SESSIONS)->cascadeOnDelete();
            });
        }
    }
}
