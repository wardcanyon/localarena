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

    // The "legacy scope" that the table's legacy data (the
    // `$this->bga->legacy` API) lives under.  Legacy data is shared
    // exactly by the tables of a game that share a scope.
    //
    // Null (the default) means the empty scope: the game's one shared
    // pool, which gives BGA's semantics (all tables of a game share
    // their legacy data).  The test harness instead assigns each test
    // case its own unique scope (see
    // IntegrationTestCase::legacyScope()), so that tests are isolated
    // by default; a test that creates several tables whose legacy data
    // should flow from one to the next gives them all the same scope.
    public ?string $legacy_scope = null;

    // The limit, in bytes, on the total size of the notifications that
    // one request -- an action plus all of the state transitions that
    // follow it -- may generate; a request that exceeds it fails and is
    // rolled back, as on BGA.
    //
    // Null (the default) means BGA's own limit, 128 KiB
    // (`LocalArenaNotifBudget::BGA_LIMIT_BYTES`), which is what a game
    // should normally be tested against.  A test that wants to exercise
    // the limit cheaply can set a small value here instead of
    // generating 128 KiB of notifications, and one that deliberately
    // generates enormous notifications can turn the check off with
    // `LocalArenaNotifBudget::NO_LIMIT` (0).  See also
    // `Table::localarenaSetNotifSizeLimit()`.
    public ?int $notif_size_limit = null;
}
