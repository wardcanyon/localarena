<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * thecrew implementation : © Nicolas Gocel <nicolas.gocel@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * thecrew.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 **/

class THCCheck41 extends THCCheck
{
    function __construct($cards)
    {
        parent::__construct($cards);
    }

    function specialCheck()
    {
        $specialId = $this->thecrew->getGameStateValue("special_id");
        $lastwinner = $this->thecrew->getGameStateValue("last_winner");
        $trickCOunt = $this->thecrew->getGameStateValue("trick_count");
        $rockets = thecrew::getUniqueValueFromDB(
            "select count(*) from card where card_type ='5' and card_location = 'trick1' and card_location_arg = " .
                $specialId
        ); // NOI18N
        $rocketslast = thecrew::getUniqueValueFromDB(
            "select count(*) from card where card_type ='5' and card_location = 'trick" .
                $trickCOunt .
                "' and card_location_arg = " .
                $specialId
        ); // NOI18N

        if (
            ($lastwinner != $specialId && $trickCOunt == 1) ||
            $rockets > 0 ||
            ($this->isLastTrick() &&
                ($lastwinner != $specialId || $rocketslast > 0))
        ) {
            $this->missionFailed();
        } elseif ($this->isLastTrick()) {
            $this->missionSuccess();
        } else {
            //otherwise we continue
            $this->thecrew->setGameStateValue("mission_finished", 0);
        }

        return true;
    }
}
