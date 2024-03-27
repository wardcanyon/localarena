<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Hearts implementation fixes: © ufm <tel2tale@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * hearts.action.php
 *
 * Hearts main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/emptygame/emptygame/myAction.html", ...)
 *
 */

/**
 * Note: this code is modified to add suggestions from BGA players and popular variants.
 * Please visit here to read the basic code used in the BGA Studio tutorial: https://github.com/elaskavaia/
 */

class action_hearts extends APP_GameAction {
    public function __default() {
        if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "hearts_hearts";
            self::trace("Complete reinitialization of board game");
        }
    }

    public function playCard() {
        self::setAjaxMode();
        $card_id = self::getArg("card_id", AT_posint, true);
        $this->game->playCard($card_id);
        self::ajaxResponse();
    }

    public function giveCards() {
        self::setAjaxMode();
        $card_ids_raw = self::getArg("card_ids", AT_numberlist, true);

        // Removing last ';' if exists
        if (substr($card_ids_raw, -1) == ';')
            $card_ids_raw = substr($card_ids_raw, 0, -1);
        if ($card_ids_raw == '') $card_ids = [];
        else $card_ids = explode(';', $card_ids_raw);

        $this->game->giveCards($card_ids);
        self::ajaxResponse();
    }
}