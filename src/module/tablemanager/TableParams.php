<?php declare(strict_types=1);

namespace LocalArena;

class TableParams
{
    public string $game;
    public int $playerCount;

    // Iff true, the "dbmodel.sql" file will be loaded at table
    // creation.  Setting this false is sometimes useful in test
    // situations.
    public bool $load_schema_file = true;

    // Iff set, these schema changes will be applied after the schema file(s) are applied.
    public string $schema_changes = '';

    // Iff set, LocalArena will instantiate the table using
    // $table_class rather than by reading files from disk based on
    // the $game name.
    //
    // This mechanism is intended only for PHPUnit tests.
    public $table_class = null;

    // Iff true, undoSavepoint() / undoRestorePoint() are active on the table from creation.  Defaults to false
    // so the typical test (which transits through state hooks that take savepoints but never exercises undo
    // itself) pays no mysqldump cost.  Tests that exercise undo opt in by setting this to true in their
    // defaultTableParams() override -- see UndoTest for an example.
    public bool $enable_undo_savepoints = false;
}
