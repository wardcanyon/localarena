<?php

namespace BurgleBrosTwo\States;

trait ActionWindow
{
    function stActionWindow()
    {
        // XXX: for now, just print a message and continue as though
        // the players all passed
        $this->notifyDebug("tmpActionWindow", "Action window!");
        $this->gamestate->nextState("tContinue");
    }

    function argActionWindow()
    {
        return [];
    }
}
