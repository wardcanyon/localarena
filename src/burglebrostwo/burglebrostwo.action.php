<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Reversi implementation : © Gregory Isabelli <gisabelli@boardgamearena.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * burglebrostwo.action.php
 *
 * Reversi main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/emptygame/emptygame/myAction.html", ...)
 *
 */

use BurgleBrosTwo\Models\Position;

class action_burglebrostwo extends APP_GameAction
{
    public function __default()
    {
        if (self::isArg("notifwindow")) {
            $this->view = "common_notifwindow";
            $this->viewArgs["table"] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "burglebrostwo_burglebrostwo";
            self::trace("Complete reinitialization of board game");
        }
    }

    // XXX: Do we need to validate that this came from the right player?
    public function actMove()
    {
        self::setAjaxMode();
        $pos = self::parseIntList("pos", /*mandatory=*/ true);
        self::trace("actMove: pos: " . print_r($pos, /*return=*/ true));
        $result = $this->game->onActMove(Position::fromArray($pos));
        self::ajaxResponse();
    }

    public function actPeek()
    {
        self::setAjaxMode();
        $pos = self::parseIntList("pos", /*mandatory=*/ true);
        self::trace("actPeek: pos: " . print_r($pos, /*return=*/ true));
        $result = $this->game->onActPeek(Position::fromArray($pos));
        self::ajaxResponse();
    }

    public function actPass()
    {
        self::setAjaxMode();
        $this->game->onActPass();
        self::ajaxResponse();
    }

    public function actPlayCard()
    {
        self::setAjaxMode();
        $cardId = self::getArg("cardId", AT_posint, /*mandatory=*/ true);
        $this->game->onActPlayCard($cardId);
        self::ajaxResponse();
    }

    private function parseIntList($name, $mandatory = true)
    {
        // N.B.: To parse generic AT_numberlist params (which can use
        // either ; or , as a separator), do...
        //
        // return array_map('intval', preg_split('/[;,]$/', $rawVal));
        return array_map(
            "intval",
            explode(",", self::getArg($name, AT_numberlist, $mandatory))
        );
    }

    public function actSelectTile()
    {
        self::setAjaxMode();

        $pos = self::parseIntList("pos", /*mandatory=*/ true);
        self::trace("actSelectTile: pos: " . print_r($pos, /*return=*/ true));
        if (count($pos) != 3) {
            throw new feException(
                "Parameter `pos` does not contain the appropriate number of elements."
            );
        }

        $this->game->onActSelectTile(Position::fromArray($pos));
        self::ajaxResponse();
    }

    // N.B.: This is unlike other actions in that it can happen at
    // any point during the game, even when it's not the player's
    // turn.
    public function actChangeGameFlowSettings()
    {
        self::setAjaxMode();
        $stepping = self::getArg("stepping", AT_bool, /*mandatory=*/ true);
        $this->game->onActChangeGameFlowSettings($stepping);
        self::ajaxResponse();
    }
}
