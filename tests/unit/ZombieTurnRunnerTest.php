<?php declare(strict_types=1);

namespace LocalArena\Test\Unit;

require_once __DIR__ . '/UnitTestCase.php';

require_once localarenaFrameworkPath('module/table/feException.php');
require_once localarenaFrameworkPath('module/table/LocalArenaZombieTurnRunner.php');

/**
 * Tests for `LocalArenaZombieTurnRunner`: the control flow behind
 * playing for players who have abandoned a table.
 *
 * BGA marks a player who quits (or times out) a "zombie" and plays for
 * them by calling the game's own `zombieTurn()` whenever the table
 * comes to rest on them.  `Table` supplies the parts of that with a
 * database behind them -- who is waiting, and what handing a turn to
 * the game means; this class supplies the loop, which is where the
 * interesting decisions are, and which is why it can be tested here
 * rather than against a real table (see `UnitTestCase`).
 *
 * The decisions under test:
 *
 * - Who is waiting is re-asked on every pass, because a zombie's turn
 *   transitions and so changes the answer.
 * - Exactly one turn is run per answer, so that a turn which changes
 *   who else is waiting is accounted for before the next one.
 * - Runs nest, because running a turn transitions and a transition
 *   checks for zombies.
 * - Non-progressing zombie handling -- the game bug this has to
 *   survive -- is capped and reported rather than left to hang.
 */
class ZombieTurnRunnerTest extends UnitTestCase
{
    /**
     * The players each successive "who is waiting?" call should
     * answer with; consumed from the front.
     *
     * @var array<int, array<int, int>>
     */
    private array $waiting_ = [];

    /** The player ids turns were run for, in order. @var array<int, int> */
    private array $turns_ = [];

    /** How many times "who is waiting?" was asked. */
    private int $asked_ = 0;

    private string $state_name_ = 'stSomeState';

    /**
     * Builds a runner over a scripted sequence of answers to "who is
     * waiting?".  $on_turn, if given, is called for each turn run --
     * the place where a test can simulate what a `zombieTurn()` does
     * (transition, re-enter, or nothing at all).
     */
    private function runner(
        array $waiting,
        ?callable $on_turn = null,
        int $max_depth = \LocalArenaZombieTurnRunner::DEFAULT_MAX_DEPTH,
        int $max_turns_per_pass = \LocalArenaZombieTurnRunner::DEFAULT_MAX_TURNS_PER_PASS
    ): \LocalArenaZombieTurnRunner {
        $this->waiting_ = $waiting;

        return new \LocalArenaZombieTurnRunner(
            function (): array {
                $this->asked_++;
                // Once the script runs out, nobody is waiting: the
                // ordinary way a pass finishes.
                return array_shift($this->waiting_) ?? [];
            },
            function (int $player_id) use ($on_turn): void {
                $this->turns_[] = $player_id;
                if ($on_turn !== null) {
                    $on_turn($player_id);
                }
            },
            fn(): string => $this->state_name_,
            $max_depth,
            $max_turns_per_pass
        );
    }

    /**
     * The common case by far: nobody has abandoned the table, so
     * nothing happens.  (Every state entry in every game runs this
     * code, so "does nothing, cheaply" is the behavior that keeps the
     * feature invisible to games that never see a zombie.)
     */
    public function testDoesNothingWhenNobodyIsWaiting(): void
    {
        $runner = $this->runner([[]]);

        $runner->run('a state entry');

        $this->assertEquals([], $this->turns_);
    }

    /**
     * One zombie, whose turn resolves the situation: one turn is run,
     * and the runner asks again before believing it is done.
     */
    public function testRunsOneTurnForOneWaitingZombie(): void
    {
        $runner = $this->runner([[7], []]);

        $runner->run('a state entry');

        $this->assertEquals([7], $this->turns_);
        $this->assertEquals(2, $this->asked_, 'The runner must confirm that nobody is left waiting.');
    }

    /**
     * Several zombies waiting at once -- a "multipleactiveplayer"
     * state -- are played for one at a time, in the order given.
     */
    public function testRunsATurnForEachWaitingZombieInTurn(): void
    {
        $runner = $this->runner([[7, 8, 9], [8, 9], [9], []]);

        $runner->run('a state entry');

        $this->assertEquals([7, 8, 9], $this->turns_);
    }

    /**
     * The property that keeps the runner honest: it takes only the
     * FIRST player from each answer and then asks again, rather than
     * working through the list it was given.  A real `zombieTurn()`
     * can change who else is waiting -- deactivating them, eliminating
     * them, or transitioning to a state that waits on somebody else
     * entirely -- so a list read once and iterated would play for
     * players the game is no longer waiting on.
     */
    public function testRereadsWhoIsWaitingRatherThanIteratingAStaleList(): void
    {
        // The first answer names three players, but by the time the
        // first one's turn is over only one of them is still waiting.
        $runner = $this->runner([[7, 8, 9], [9], []]);

        $runner->run('a state entry');

        $this->assertEquals([7, 9], $this->turns_, 'Player 8 must not be played for once they stop waiting.');
    }

    /**
     * Runs nest: a zombie's turn transitions, the transition checks
     * for zombies, and so a run starts inside a run.  That is how a
     * zombie's turn leading into a state where another zombie is
     * waiting gets both of them played for within the original call.
     */
    public function testNestedRunsArePermitted(): void
    {
        $runner = null;
        $nested = false;

        $runner = $this->runner([[7], []], function () use (&$runner, &$nested): void {
            if ($nested) {
                return;
            }
            $nested = true;
            // What a transition inside `zombieTurn()` amounts to.
            $this->waiting_ = [[8], []];
            $runner->run('a state entry');
        });

        $runner->run('a state entry');

        $this->assertEquals([7, 8], $this->turns_);
    }

    // ==================== Runaway guards ====================

    /**
     * The recursive shape of a broken `zombieTurn()`: it transitions
     * back into the state it was called from, with the same zombie
     * still waiting.  Left alone that recurses until the request dies
     * -- in a test suite, a hang with nothing to look at.
     */
    public function testFailsLoudlyWhenTurnsNestWithoutEnd(): void
    {
        $runner = null;
        $runner = $this->runner(
            [],
            function () use (&$runner): void {
                // Always somebody waiting, always one level deeper.
                $this->waiting_ = [[7]];
                $runner->run('a state entry');
            },
            /*max_depth=*/ 4
        );
        $this->waiting_ = [[7]];

        try {
            $runner->run('a state entry');
            $this->fail('Expected endlessly nested zombie turns to throw.');
        } catch (\feException $exc) {
            $this->assertStringContainsString('Runaway zombie turns', $exc->getMessage());
            $this->assertStringContainsString('nested', $exc->getMessage());
            $this->assertStringContainsString($this->state_name_, $exc->getMessage());
        }

        $this->assertCount(4, $this->turns_, 'The cap should stop the recursion, not notice it afterwards.');
    }

    /**
     * The flat shape of the same bug: a `zombieTurn()` that returns
     * having made no progress at all, leaving its player waiting in
     * the state it was called for.  That spins in one pass rather than
     * recursing, so it needs a bound of its own.
     */
    public function testFailsLoudlyWhenATurnLeavesItsPlayerWaiting(): void
    {
        $runner = $this->runner(
            [],
            function (): void {
                // Nothing changes, however many turns are run.
                $this->waiting_ = [[7]];
            },
            /*max_depth=*/ 4,
            /*max_turns_per_pass=*/ 3
        );
        $this->waiting_ = [[7]];

        try {
            $runner->run('checkStuckedZombiePlayers()');
            $this->fail('Expected a non-progressing zombie turn to throw.');
        } catch (\feException $exc) {
            $this->assertStringContainsString('Runaway zombie turns', $exc->getMessage());
            $this->assertStringContainsString('checkStuckedZombiePlayers()', $exc->getMessage());
            $this->assertStringContainsString('player 7', $exc->getMessage());
            $this->assertStringContainsString($this->state_name_, $exc->getMessage());
        }

        $this->assertCount(3, $this->turns_);
    }

    /**
     * Hitting a cap is a report, not a corruption: the nesting count
     * is unwound on the way out, so a later run (the next request,
     * say, or the next test using the same table) starts from zero
     * rather than inheriting a poisoned depth.
     */
    public function testDepthIsUnwoundWhenACapIsHit(): void
    {
        $runner = null;
        $recurse = true;
        $runner = $this->runner(
            [],
            function () use (&$runner, &$recurse): void {
                if (!$recurse) {
                    return;
                }
                $this->waiting_ = [[7]];
                $runner->run('a state entry');
            },
            /*max_depth=*/ 3
        );
        $this->waiting_ = [[7]];

        try {
            $runner->run('a state entry');
            $this->fail('Expected endlessly nested zombie turns to throw.');
        } catch (\feException $exc) {
            // Expected.
        }

        // A fresh, well-behaved run over the same runner works.
        $recurse = false;
        $this->turns_ = [];
        $this->waiting_ = [[11], []];
        $runner->run('a state entry');

        $this->assertEquals([11], $this->turns_);
    }
}
