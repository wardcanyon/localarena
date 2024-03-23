<?php

namespace BurgleBrosTwo\States;

trait CharacterSelectionRoundEnd
{
    // Transition to either ST_PLACE_ENTRANCE_TOKENS (if selection is
    // complete) or ST_CHOOSE_CHARACTER (if it is not).
    function stCharacterSelectionRoundEnd()
    {
        // If four characters have been selected, we're done; if we
        // don't yet have two, we need to keep picking more.
        $character_count = count(
            self::getCollectionFromDB(
                "SELECT * FROM character_player WHERE TRUE"
            )
        );
        if ($character_count < MIN_CHARACTERS) {
            $this->gamestate->nextState("tAnotherRound");
            return;
        }
        if ($character_count >= MAX_CHARACTERS) {
            $this->gamestate->nextState("tDone");
            return;
        }

        // If multi-handed play is disabled, we're done.
        if (!self::optionMultiHanded()) {
            $this->gamestate->nextState("tDone");
            return;
        }

        // If all players have passed, we're done.
        $players_not_passed = self::getCollectionFromDB(
            "SELECT * FROM player WHERE player_selection_passed IS FALSE"
        );
        if (count($players_not_passed) == 0) {
            $this->gamestate->nextState("tDone");
            return;
        }

        // Otherwise, another round!
        $this->gamestate->nextState("tAnotherRound");
    }
}
