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

class THCCheck26 extends THCCheck
{
    function __construct($cards )
    {
        parent::__construct($cards);
    }
    
    function specialCheck()
    {
        //Check last trick
        $trickCOunt =  $this->thecrew->getGameStateValue( 'trick_count');
        $cards_on_table = $this->thecrew->cards->getCardsInLocation('trick'.$trickCOunt);
        $best_value = 0;
        $best_value_player_id = null;
        $winningColor = $this->thecrew->getGameStateValue('trick_color'); // The color needed to win the trick color unless a trump (5) was played
        
        // Who wins ?
        foreach ($cards_on_table as $card) {
            // Note: type = card color
            // Note: type_arg = value of the card
            // Note: location_arg = player who played this card on table
            if ($card['type'] == 5 && $winningColor != 5) { // A trump has been played: this is the first one
                $winningColor = 5; // Now trumps are needed to win the trick
                $best_value_player_id = $card['location_arg'];
                $best_value = $card['type_arg'];
            }
            else if($card['type'] == $winningColor) { // This card is the right color to win the trick
                if($card['type_arg'] > $best_value) {
                    $best_value_player_id = $card['location_arg'];
                    $best_value = $card['type_arg'];
                }
            }
        }
        
        if($best_value == 1 && $winningColor < 5)
        {
            $this->thecrew->setGameStateValue( 'check_count', $this->thecrew->getGameStateValue('check_count') + 1 );
        }
        
        $nbLeft =  thecrew::getUniqueValueFromDB("select count(*) from card where card_type_arg = 1 and card_type <> '5' and (card_location = 'hand' or card_location='comm')");// NOI18N
        
        
        if($this->thecrew->getGameStateValue('check_count') >= 2)
        {
            $this->missionSuccess();
        }
        else if($this->isLastTrick() || $nbLeft + $this->thecrew->getGameStateValue('check_count') < 2 )
        {
            $this->missionFailed();
        }
        else
        {
            //otherwise we continue
            $this->thecrew->setGameStateValue( 'mission_finished', 0 );
        }
        
        return true;
    }
}