<?php

class action_localarenanoop extends APP_GameAction
{
    // Constructor: please do not modify
    public function __default()
    {
        if (self::isArg("notifwindow")) {
            $this->view = "common_notifwindow";
            $this->viewArgs["table"] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "localarenanoop_localarenanoop";
            self::trace("Complete reinitialization of board game");
        }
    }

    // LocalArena test-support action: drive an arbitrary named
    // transition from the current state, within a real request (so the
    // request-boundary semantics of the current-state global are
    // exercised).  Framework tests use this to trigger an in-request
    // state cascade; real games never expose anything like it.
    public function actTestTransition()
    {
        self::setAjaxMode();
        $transition = self::getArg("transition", AT_alphanum_dash, /*required=*/ true);
        $this->game->gamestate->nextState($transition);
        self::ajaxResponse();
    }
}
