<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

/**
 * Tests for the default-disabled side of the undo-savepoint contract.
 *
 * UndoTest covers the opt-in path: it overrides defaultTableParams() to set $enable_undo_savepoints = true, so every
 * test there exercises a real mysqldump/restore roundtrip.  This file is the mirror: it deliberately leaves the
 * default in place (savepoints disabled) and pins the no-op + loud-throw contract that downstream test suites rely
 * on -- the whole reason the opt-in mechanism exists is that the typical test no longer pays the dump cost, and that
 * "no cost" needs an explicit regression test.
 *
 * Also covers the runtime setUndoSavepointsEnabled() setter, which is the path a test takes when it can't (or
 * doesn't want to) opt in at TableParams construction time.
 */
class UndoDisabledTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    // Note: NO defaultTableParams() override here -- the whole point is to test the default.

    private function undoFilePath(): string
    {
        return '/tmp/undo_' . $this->table()->localarena_table_id . '.sql';
    }

    private function deleteUndoFileIfExists(): void
    {
        $path = $this->undoFilePath();
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function getPlayerScore(string $playerId): int
    {
        return (int) $this->table()->getUniqueValueFromDB(
            "SELECT player_score FROM player WHERE player_id = " . $playerId
        );
    }

    /**
     * With savepoints disabled (the default), undoSavepoint() must return without writing the dump file -- this is
     * the optimization the opt-in flip exists for.  Verified by deleting any leftover dump from a prior test and
     * confirming undoSavepoint() does not recreate it.
     */
    public function testUndoSavepointIsNoopByDefault(): void
    {
        $this->deleteUndoFileIfExists();

        $this->table()->undoSavepoint();

        $this->assertFileDoesNotExist(
            $this->undoFilePath(),
            'undoSavepoint() must not write the dump file when savepoints are disabled (the default).'
        );
    }

    /**
     * With savepoints disabled, undoRestorePoint() must throw rather than silently no-op.  A silent no-op would let
     * a test that thinks it's exercising undo green-pass against a stripped harness, producing results inconsistent
     * with production where the restore would actually happen.
     */
    public function testUndoRestorePointThrowsByDefault(): void
    {
        $this->expectException(\BgaVisibleSystemException::class);
        $this->expectExceptionMessage('savepoints are disabled');
        $this->table()->undoRestorePoint();
    }

    /**
     * setUndoSavepointsEnabled(true) is the runtime opt-in, used by tests that can't override defaultTableParams()
     * (e.g. when they need to flip behavior partway through).  Calling it must flip both halves of the contract:
     * undoSavepoint() actually dumps, undoRestorePoint() actually restores.  Verified end-to-end through a player
     * score mutation.
     */
    public function testRuntimeOptInEnablesRoundtrip(): void
    {
        $this->deleteUndoFileIfExists();
        $this->table()->setUndoSavepointsEnabled(true);

        $player = $this->playerByIndex(0);
        $initialScore = $this->getPlayerScore($player->id());

        $this->table()->undoSavepoint();
        $this->assertFileExists(
            $this->undoFilePath(),
            'undoSavepoint() must write the dump file after setUndoSavepointsEnabled(true).'
        );

        $newScore = $initialScore + 17;
        $this->table()->DbQuery(
            "UPDATE player SET player_score = {$newScore} WHERE player_id = " . $player->id()
        );
        $this->assertEquals(
            $newScore,
            $this->getPlayerScore($player->id()),
            'Sanity check: DbQuery should have updated the score before the restore call.'
        );

        $this->table()->undoRestorePoint();
        $this->assertEquals(
            $initialScore,
            $this->getPlayerScore($player->id()),
            'undoRestorePoint() must roll the score back to the savepoint after the runtime opt-in.'
        );
    }

    /**
     * The runtime setter is symmetric: toggling back to false must re-apply the disabled contract (no-op +
     * loud-throw).  This guards against the implementation accidentally becoming write-only -- e.g. caching the
     * "enabled" decision somewhere that ignores subsequent setter calls.
     */
    public function testRuntimeOptInIsReversible(): void
    {
        $this->table()->setUndoSavepointsEnabled(true);
        $this->table()->setUndoSavepointsEnabled(false);

        $this->deleteUndoFileIfExists();
        $this->table()->undoSavepoint();
        $this->assertFileDoesNotExist(
            $this->undoFilePath(),
            'undoSavepoint() must return to no-op behavior after setUndoSavepointsEnabled(false).'
        );

        $this->expectException(\BgaVisibleSystemException::class);
        $this->expectExceptionMessage('savepoints are disabled');
        $this->table()->undoRestorePoint();
    }
}
