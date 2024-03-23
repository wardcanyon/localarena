<?php

namespace BurgleBrosTwo;

trait Bouncer
{
    function posFromPatrolCard($card)
    {
        // XXX: need to handle "distracted" cards here
        if (
            !preg_match('/^patrol_(\d+)_(\d+)_(\d+)$/', $card["card_type"], $m)
        ) {
            throw new \feException(
                "Unable to parse patrol card_type: " . $card["card_type"]
            );
        }
        return [intval($m[1]), intval($m[2]), intval($m[3])];
    }

    // XXX: move to data-layer file?
    function getBouncer($npcId)
    {
    }

    function drawBouncerDestination($npcId)
    {
        $card = $patrol_deck->drawAndDiscard();
        if (is_null($card)) {
            throw new \feException(
                "XXX: TODO: patrol deck is empty; have not implemented hunting mode yet"
            );
        }
        $destination = $this->posFromPatrolCard($card);

        // XXX: get bouncer; get destination entity; update its position; send notif to client
    }
}
