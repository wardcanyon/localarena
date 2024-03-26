<?php

namespace BurgleBrosTwo;

trait GameFlow
{
    function onActChangeGameFlowSettings($stepping)
    {
        $steppingSettings = self::getGameStateJson(GAMESTATE_JSON_NPC_STEPPING);
        $steppingSettings[self::getCurrentPlayerId()] = $stepping;
        self::setGameStateJson(GAMESTATE_JSON_NPC_STEPPING, $steppingSettings);

        // N.B.: We just need to send the player who updated their
        // settings *something*, or the client will hang at "Move
        // recorded, waiting for update...".
        self::notifyPlayer(self::getCurrentPlayerId(), "ack", "", []);
    }
}
