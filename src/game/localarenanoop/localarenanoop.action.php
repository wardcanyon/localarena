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

    // LocalArena test-support action: jump straight to a state by its
    // key, within a real request.  The companion of
    // `actTestTransition()` for `jumpToState()`; likewise, real games
    // never expose anything like it.
    public function actTestJumpToState()
    {
        self::setAjaxMode();
        $state_id = self::getArg("state_id", AT_posint, /*required=*/ true);
        $with_actions = self::getArg("with_actions", AT_bool, /*required=*/ true);
        $this->game->gamestate->jumpToState($state_id, $with_actions);
        self::ajaxResponse();
    }

    // LocalArena test-support action: run the framework's
    // `checkAction()` within a real request, as whichever player the
    // request was submitted by.  Framework tests use this to exercise
    // the turn-order gate end to end; real games call `checkAction()`
    // at the top of their own action handlers instead.
    public function actTestCheckAction()
    {
        self::setAjaxMode();
        $action_name = self::getArg("action_name", AT_alphanum_dash, /*required=*/ true);
        $this->game->checkAction($action_name);
        self::ajaxResponse();
    }

    // LocalArena test-support action: announce the given
    // notifications from inside a real request, so that they pass
    // through the same transaction, gamelog, and post-commit delivery
    // that a game's own notifications do.
    //
    // "notifs" is a list of objects, each naming a "type" and
    // optionally carrying a "log", "args", and "player".  Supplying a
    // "player" announces that one with `notifyPlayer()` rather than
    // `notifyAllPlayers()`.
    //
    // Passing "fail" throws after the notifications have been
    // announced but before the request commits -- which is how a test
    // observes that a failed action leaves none of them behind.
    public function actTestNotify()
    {
        self::setAjaxMode();
        $notifs = self::getArg("notifs", AT_json, /*required=*/ true);
        $fail = self::getArg("fail", AT_bool, /*required=*/ false, /*default=*/ false);

        foreach ($notifs as $notif) {
            $type = $notif["type"];
            $log = $notif["log"] ?? "";
            $args = $notif["args"] ?? [];

            if (isset($notif["player"])) {
                $this->game->notifyPlayer($notif["player"], $type, $log, $args);
            } else {
                $this->game->notifyAllPlayers($type, $log, $args);
            }
        }

        if ($fail) {
            throw new \BgaUserException(
                "actTestNotify(): failing deliberately, as the test asked."
            );
        }

        self::ajaxResponse();
    }
}
