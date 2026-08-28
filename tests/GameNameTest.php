<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

// We subclass the bundled "localarenanoop" harness game below, so load
// its class.  When a test supplies a `table_class`, TableManager::getTable()
// instantiates that class directly and does NOT require the game's
// .game.php file, so we must require it ourselves before subclassing.
require_once LOCALARENA_GAME_PATH . 'localarenanoop/localarenanoop.game.php';

/**
 * Tests for where LocalArena gets the name of the game a table is
 * running.
 *
 * It is the registry row that says: `TableManager` writes
 * `table`.`table_game` when the table is created, reads it back to
 * find the game's class, and hands it to `Table`'s constructor through
 * `LocalArenaContext`.  Every file the constructor loads -- gameinfos,
 * material, states, the stats description, the action class, the
 * schema -- is found under that name, so "the table exists and is in
 * its entry state" is itself most of the assertion here.
 *
 * It used to be the game class that said, through a `getGameName()`
 * override.  BGA's framework no longer calls that method -- its studio
 * linter reports it as obsolete and tells games to delete it -- and a
 * game that took the advice booted against `Table`'s 'noname' default
 * and hunted for its files in a directory that does not exist.  Hence
 * the first test: a game class that names nothing has to work.  And
 * hence the second: the override, where a game still carries one, must
 * not be able to contradict the registry.
 */
class GameNameTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // The harness game's entry state, which a table that loaded
    // "localarenanoop/states.inc.php" -- and only such a table -- comes
    // to rest in.
    const ST_NOOP = 2;

    /**
     * `localarenanoop` itself defines no `getGameName()`, which is what
     * this asserts: the table boots, from the right directory, without
     * the game having named itself.
     */
    public function testBootsAGameClassThatNamesNothing(): void
    {
        $this->initTable($this->defaultTableParams());

        $this->assertFalse(
            method_exists(\localarenanoop::class, 'getGameName'),
            'The premise of this test is that the harness game defines no getGameName().'
        );

        $this->assertGameState(self::ST_NOOP);
        $this->assertEquals(self::LOCALARENA_GAME_NAME, $this->table()->localarenaGetGameName());
    }

    /**
     * A game that still carries the obsolete override gets no say.  The
     * class here answers with a game that does not exist, and the table
     * boots from the registry's directory anyway.
     */
    public function testTheRegistryOutranksAGameClassOverride(): void
    {
        $params = $this->defaultTableParams();
        $params->table_class = GameNameTestGame::class;
        $this->initTable($params);

        $this->assertGameState(self::ST_NOOP);
        $this->assertEquals(self::LOCALARENA_GAME_NAME, $this->table()->localarenaGetGameName());
    }
}

// A game class carrying the obsolete override, answering with a name
// no directory under LOCALARENA_GAME_PATH has.  Note that its own class
// name is not the game's either, which is the other thing the framework
// must not read the name off of: TableManager maps a game name to a
// class name (`ucfirst()`), never the reverse.
class GameNameTestGame extends \localarenanoop
{
    protected function getGameName()
    {
        return 'a-game-that-does-not-exist';
    }
}
