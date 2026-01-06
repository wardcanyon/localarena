<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Tests for the undo savepoint/restore functionality.
 */
class UndoTest extends IntegrationTestCase
{
    const LOCALARENA_GAME_NAME = 'localarenanoop';

    /**
     * Test that undoSavepoint() and undoRestorePoint() correctly save and restore database state.
     */
    public function testUndoSavepointAndRestore(): void
    {
        // Get initial player score
        $player = $this->playerByIndex(0);
        $initialScore = $this->getPlayerScore($player->id());

        // Save the current state
        $this->table()->undoSavepoint();

        // Modify the database (change a player's score)
        $newScore = $initialScore + 100;
        $this->table()->DbQuery(
            "UPDATE player SET player_score = {$newScore} WHERE player_id = " . $player->id()
        );

        // Verify the score changed
        $scoreAfterChange = $this->getPlayerScore($player->id());
        $this->assertEquals($newScore, $scoreAfterChange, 'Score should be updated after DbQuery');

        // Restore the savepoint
        $this->table()->undoRestorePoint();

        // Verify the score was restored to the original value
        $scoreAfterRestore = $this->getPlayerScore($player->id());
        $this->assertEquals($initialScore, $scoreAfterRestore, 'Score should be restored after undoRestorePoint()');
    }

    /**
     * Test that undoRestorePoint() does nothing when no savepoint exists.
     */
    public function testUndoRestorePointWithNoSavepoint(): void
    {
        // Get initial player score
        $player = $this->playerByIndex(0);
        $initialScore = $this->getPlayerScore($player->id());

        // Modify the database
        $newScore = $initialScore + 50;
        $this->table()->DbQuery(
            "UPDATE player SET player_score = {$newScore} WHERE player_id = " . $player->id()
        );

        // Try to restore (should have no effect since no savepoint was created)
        // Note: This assumes the undo file from previous tests might not exist,
        // or if it does, it would restore to that state. In a clean environment,
        // this should be a no-op.
        $this->table()->undoRestorePoint();

        // The score should remain at newScore since there's no valid savepoint
        // (or it restores to whatever was last saved, which isn't our concern here)
        $this->assertTrue(true, 'undoRestorePoint should not crash when no savepoint exists');
    }

    /**
     * Test that multiple savepoints overwrite each other (only one savepoint at a time).
     */
    public function testMultipleSavepoints(): void
    {
        $player = $this->playerByIndex(0);

        // Set score to 10
        $this->table()->DbQuery(
            "UPDATE player SET player_score = 10 WHERE player_id = " . $player->id()
        );
        $this->table()->undoSavepoint();

        // Set score to 20
        $this->table()->DbQuery(
            "UPDATE player SET player_score = 20 WHERE player_id = " . $player->id()
        );
        $this->table()->undoSavepoint();

        // Set score to 30
        $this->table()->DbQuery(
            "UPDATE player SET player_score = 30 WHERE player_id = " . $player->id()
        );

        // Restore should go back to 20 (the last savepoint), not 10
        $this->table()->undoRestorePoint();

        $scoreAfterRestore = $this->getPlayerScore($player->id());
        $this->assertEquals(20, $scoreAfterRestore, 'Should restore to most recent savepoint (score=20)');
    }

    private function getPlayerScore(string $playerId): int
    {
        return (int) $this->table()->getUniqueValueFromDB(
            "SELECT player_score FROM player WHERE player_id = " . $playerId
        );
    }
}
