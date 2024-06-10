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
}
