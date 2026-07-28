<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We extend the bundled "localarenanoop" harness game, so load its class.  When a test supplies a `table_class`,
// TableManager::getTable() instantiates that class directly and does NOT require the game's .game.php file, so we
// must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for choosing a table's game options at table creation (`TableParams::$game_options`).
 *
 * On BGA, a table's options are fixed by its creator before any game code runs, so setup code can read them.  These
 * tests pin that ordering -- an option value supplied at table creation is visible to `setupNewGame()`, not merely
 * settable afterwards -- because a game whose setup branches on an option (e.g. which cards to create) cannot be
 * tested at all if the harness only offers to change options once setup is over.
 *
 * They also pin the guard rail on the feature: a value the game does not publish is reachable only when the table
 * names that option id in `$allow_unpublished_option_values`.  Tests legitimately need unpublished values (that is
 * how a game hides finished-but-unreleased content from the lobby), but silently accepting any value would let a
 * typo, or a stale copy of a game's option definitions, configure a table no player could ever create.
 *
 * The no-op game's options are defined in src/game/localarenanoop/gameoptions.inc.php.
 */
class GameOptionsTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // The no-op game's test option: values 1 and 2 are published, 3 is commented out, and 1 is the default.
    // (Public because OptionReadingTestGame, below, reads the same option during setup.)
    public const OPTION_ID = 100;
    private const VALUE_DEFAULT = 1;
    private const VALUE_PUBLISHED = 2;
    private const VALUE_UNPUBLISHED = 3;

    // An option id the no-op game does not define at all.
    private const UNDEFINED_OPTION_ID = 999;

    // Whether this test got as far as a usable table; see tearDown().
    private bool $table_created_ = false;

    protected function tearDown(): void
    {
        if ($this->table_created_) {
            parent::tearDown();
            return;
        }

        // Table creation threw partway through, so there is no table object to close the process-wide database
        // connection through -- and the inherited tearDown() would reach for one, silently creating a whole extra
        // table just to close it.  Close the connection directly instead, so the next test's table connects to its
        // own database rather than inheriting the abandoned one.
        $conn = \APP_DbObject::conn();
        if ($conn !== null) {
            $conn->close();
            \APP_DbObject::$static_conn_ = null;
        }
    }

    private function initTableWithOptions(
        array $game_options,
        array $allow_unpublished_option_values = []
    ): void {
        $this->initTable($this->tableParams($game_options, $allow_unpublished_option_values));
        $this->table_created_ = true;
    }

    private function tableParams(
        array $game_options,
        array $allow_unpublished_option_values = []
    ): \LocalArena\TableParams {
        $params = new \LocalArena\TableParams();
        $params->game = self::LOCALARENA_GAME_NAME;
        $params->playerCount = 2;
        // Reuse all of localarenanoop's files, but instantiate our setup-observing subclass instead of the plain
        // game class.
        $params->table_class = OptionReadingTestGame::class;
        $params->game_options = $game_options;
        $params->allow_unpublished_option_values = $allow_unpublished_option_values;
        return $params;
    }

    // Reads an option's value straight out of the globals table, where option values live under their option id.
    // (Reading it through `getGameStateValue()` instead would require the game to have registered a label for the
    // option, which the no-op game does not do.)
    private function optionValue(int $option_id): int
    {
        return (int) $this->table()->getUniqueValueFromDB(
            'SELECT global_value FROM global WHERE global_id = ' . $option_id
        );
    }

    /**
     * A table created without any options gets the defaults from the game's option definitions -- the behavior every
     * existing test depends on, unchanged by the ability to override.
     */
    public function testDefaultsApplyWhenNoOptionsAreGiven(): void
    {
        $this->initTableWithOptions([]);

        $this->assertSame(
            self::VALUE_DEFAULT,
            $this->optionValue(self::OPTION_ID),
            'A table created with no options should take each option\'s declared default.'
        );
    }

    /**
     * A published value needs no opt-in: this is the ordinary case, a table created the way a player would create it.
     */
    public function testPublishedValueIsApplied(): void
    {
        $this->initTableWithOptions([self::OPTION_ID => self::VALUE_PUBLISHED]);

        $this->assertSame(
            self::VALUE_PUBLISHED,
            $this->optionValue(self::OPTION_ID),
            'A published option value supplied at table creation should replace the default.'
        );
    }

    /**
     * The point of applying options at table creation: they must already be in place when the game's own setup runs,
     * since that is when a game decides what to create.  Verified by reading the value during `setupNewGame()`.
     */
    public function testOptionIsVisibleDuringGameSetup(): void
    {
        $this->initTableWithOptions([self::OPTION_ID => self::VALUE_PUBLISHED]);

        $this->assertSame(
            self::VALUE_PUBLISHED,
            $this->table()->setup_option_read,
            'The chosen option value must be readable by setupNewGame(), not just after setup has finished.'
        );
    }

    /**
     * An unpublished value is what a test reaching unreleased content actually needs, and it works -- but only with
     * the explicit declaration.
     */
    public function testUnpublishedValueIsAppliedWhenDeclared(): void
    {
        $this->initTableWithOptions([self::OPTION_ID => self::VALUE_UNPUBLISHED], [self::OPTION_ID]);

        $this->assertSame(
            self::VALUE_UNPUBLISHED,
            $this->optionValue(self::OPTION_ID),
            'An unpublished value should be applied when its option id is declared in' .
                ' $allow_unpublished_option_values.'
        );
    }

    /**
     * Without the declaration, the same value throws.  This is the guard rail: it fires on the exact input the
     * declaration exists to authorize, so a table cannot reach unpublished content by accident.
     */
    public function testUnpublishedValueThrowsWhenNotDeclared(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->expectExceptionMessage('which that option does not list');

        $this->initTable($this->tableParams([self::OPTION_ID => self::VALUE_UNPUBLISHED]));
    }

    /**
     * The declaration is per option id, not a blanket "anything goes" switch: declaring one option must not quietly
     * authorize unpublished values for another.
     */
    public function testDeclarationDoesNotCoverOtherOptions(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->expectExceptionMessage('which that option does not list');

        $this->initTable(
            $this->tableParams([self::OPTION_ID => self::VALUE_UNPUBLISHED], [self::UNDEFINED_OPTION_ID])
        );
    }

    /**
     * An option id the game does not define is always an error -- there is no opt-in for it, because there is no
     * legitimate reason to set an option that does not exist.  Declaring the id must not rescue it either.
     */
    public function testUndefinedOptionIdThrowsEvenWhenDeclared(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->expectExceptionMessage('which this game does not define');

        $this->initTable(
            $this->tableParams([self::UNDEFINED_OPTION_ID => 1], [self::UNDEFINED_OPTION_ID])
        );
    }
}

// The no-op game, plus a record of what the test option read as while `setupNewGame()` was running.  Options are
// stored under their option id, and the no-op game registers no state labels, so setup reads the global directly --
// the same value `getGameStateValue()` would return for a game that had labeled the option.
class OptionReadingTestGame extends \localarenanoop
{
    /** @var ?int The test option's value as seen during setup, or null if setup has not run. */
    public $setup_option_read = null;

    protected function setupNewGame($players, $options = [])
    {
        $this->setup_option_read = (int) $this->getUniqueValueFromDB(
            'SELECT global_value FROM global WHERE global_id = ' . GameOptionsTest::OPTION_ID
        );

        parent::setupNewGame($players, $options);
    }
}
