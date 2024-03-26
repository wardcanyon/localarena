<?php

namespace BurgleBrosTwo;

trait GameOptions
{
    function optionMultiHanded()
    {
        if (self::getPlayersNumber() == 1) {
            // N.B.: The multi-handed play option is hidden in solo
            // play; because the game requires two characters, a
            // single player must play multi-handed.
            return true;
        }
        return $this->getGameStateValue("optionMultihanded") == OPTVAL_ENABLED;
    }

    function optionSuspicion()
    {
        $value = $this->getGameStateValue("optionSuspicion");
        assert(is_int($value) && 1 <= $value && $value <= 10);
        return $value;
    }

    function optionVariantDeadDrops()
    {
        return $this->getGameStateValue("optionVariantDeadDrops") ==
            OPTVAL_ENABLED;
    }

    function optionVariantCasingTheJoint()
    {
        return $this->getGameStateValue("optionVariantCasingTheJoint") ==
            OPTVAL_ENABLED;
    }
}
