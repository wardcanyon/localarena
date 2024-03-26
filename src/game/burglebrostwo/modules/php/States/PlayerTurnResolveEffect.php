<?php

namespace BurgleBrosTwo\States;

use \BurgleBrosTwo\Models\Position;
use \BurgleBrosTwo\Models\EffectContext;

// XXX: This is becoming just "ResolveEffect", I think.
trait ResolveEffect {
    function stResolveEffect() {
        $this->resolveEffects();
    }

    // XXX: does not go here
    function revealTile($tile) {
        $pos = Position::fromRow($tile);
        self::DbQuery('UPDATE `tile` SET state = "VISIBLE" WHERE ' . $this->buildExprWherePos($pos));
        $this->notifyTileUpdates($this->rawGetTile($tile['id']), /*msg=*/'');
    }

    // XXX: does not go here
    function revealChip(\BurgleBrosTwo\Models\Chip $chip) {
        self::DbQuery('UPDATE `entity` SET state = "VISIBLE" WHERE `id`='.$chip->id);
        // XXX: this part is "pre-OOP":
        $this->notifyEntityUpdates($this->rawGetEntity($chip->id), /*msg=*/'A chip is revealed.');
    }
}
