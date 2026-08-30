<?php declare(strict_types=1);

namespace LocalArena;

class TableParams
{
    public string $game;

    // The number of players to seat at this table.
    //
    // Null (the default) means the count configured for interactive
    // play, `LOCALARENA_PLAYER_COUNT` in
    // "localarena_config.inc.php".  Tests that exercise
    // player-count-dependent behavior -- turn order, multiactive sets
    // -- set it explicitly in their `defaultTableParams()` override;
    // see `tests/PlayerOrderTest.php`.
    public ?int $playerCount = null;

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

    // Game-option values for this table, as a map of option id =>
    // value.  These are applied on top of the defaults from the game's
    // "gameoptions.json" during game setup, before `setupNewGame()`
    // runs -- the same point at which a real BGA table has the options
    // its creator chose -- so setup code sees them.
    //
    // Naming an option id that the game does not define is an error.
    public array $game_options = [];

    // Option ids whose `$game_options` value is allowed not to appear
    // among that option's published `values`.
    //
    // A game may deliberately define an option value that players
    // cannot select: content that is implemented but not yet released
    // is typically commented out of "gameoptions.inc.php" so the BGA
    // lobby never offers it, even though the engine honors the value
    // if it is set.  Tests need to reach exactly those values, so
    // supplying one is allowed -- but only deliberately.  Listing the
    // option id here is that declaration; without it, an unpublished
    // value throws rather than quietly configuring a table that no
    // player could create.
    public array $allow_unpublished_option_values = [];

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
}
