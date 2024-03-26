<?php

abstract class Finale
{
    // Called when the finale is activated (i.e. when the safe is cracked).
    abstract public function onActivated(World $world);

    abstract public function onPcEntersTile(World $world, EffectContext $ctx);

    public function onNpcEntersTile(World $world, EffectContext $ctx)
    {
    }

    // Called when a PC tries to exit the casino; returns a boolean.
    // If the return value is false, the attempt to exit is canceled.
    abstract public function onPcEscaping(World $world, EffectContext $ctx);

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        return [];
    }

    public function onSpecialAction(World $world, $actionParams)
    {
        throw new \feException(
            "No special actions are defined for this finale!"
        );
    }
}

// This is a special "no-op" finale class that is used before the
// finale is activated.
class InactiveFinale extends Finale
{
    public function onActivated(World $world)
    {
        throw new \feException(
            "This should never be called for `InactiveFinale`."
        );
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        // This is a no-op.
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Player characters can't escape from the casino before the
        // finale is activated.
        return false;
    }
}

// Heist #1 (does not work with the Casing the Joint variant)
class BodyguardFinale extends Finale
{
    // Chips still activate normally. Players may peek at an adjacent
    // chip even if its tile is revealed.

    // If a player with a Prima Donna chip is ever on the same
    // tile as a bouncer, the players lose.
    //
    // XXX: Do we want to do this with a "pcMeetsNpc" hook, or with
    // "pcEntersTile" and "npcEntersTile" hooks?

    public function onActivated(World $world)
    {
        // Fake Orders: Take 2 random discarded patrol cards and put
        // them at the bottom of either patrol deck.
        //
        // Setup: Gather all the chips used in this game, then shuffle
        // them facedown. Take a random discarded patrol card for each
        // floor and place chips facedown as the card shows. If a chip
        // would be placed on a tile with a player on it, choose any
        // other tile with no player or chip.
        //
        // In a two-player game, choose 6 chips to discard after
        // placing them.  In a three-player game, choose 3 chips to
        // discard after placing them.
        //
        // If a Prima Donna is discarded in the step above, put it
        // back on the tile facedown instead.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        // When a player enters a tile with a Prima Donna chip, they
        // take it and put it on their character card.

        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Win: Once 2 Prima Donna chips are on character cards,
        // players can exit through any tile with an entrance
        // token. Once all the players have exited the casino, they
        // win!
        throw new \feException("no impl");
    }
}

// Heist #2
class RescuingHerRideFinale extends Finale
{
    // Car rules
    //
    // A player on the tile with the car can spend an action to get in
    // the car, putting their figure on it.
    //
    // A player in the car can spend an action to move the car to an
    // adjacent tile, even through walls.  If there is more than one
    // tile in that direction, skip over the first tile instead and
    // jump to the second one.
    //
    // Remove any walls the car moves through or skips over.
    //
    // In a two-player game, the car cannot move through walls.
    //
    // The car cannot move down to the first floor.
    //
    // While in the car, only the current player interacts with tiles,
    // such as When You Enter effects.
    //
    // All players in the car follow the car’s movement and can gain
    // heat from the bouncer, but ignore all chips.
    //
    // Win: The car, with all players inside, must exit through any
    // edge tile on the second floor. (Through the window!)

    public function onActivated(World $world)
    {
        // Place the car figure on the Safe tile.

        // Disguise Change: All players immediately lose 1 heat.

        // The second-floor bouncer is hunting for the rest of the
        // game, but always sets the car as his destination.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }
}

// Heist #3
class LaundryDayFinale extends Finale
{
    public function onActivated(World $world)
    {
        // Fake Orders: Take 2 random discarded patrol cards and put them at the bottom of
        //     either patrol deck.
        // Setup: Each player takes a safe die and turns it to the 1 face.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        // When a player enters a tile with a number exactly 1 higher
        // than the number shown on their die, they may turn their die
        // to match the number on that tile.  (Example: The Raven has
        // a die on the 1 face. She enters a room showing the number
        // 2, so she turns her die to its 2 face.)
        //
        // A die cannot be turned to its 6 face—it stops at 5.
        //
        // XXX: Implement this as five statuses.  They don't do
        // anything on their own (other than showing the player a die
        // face); they're used by this finale.

        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Win: Once a player has a die on its 5 face, that player can
        // exit the casino through a tile with an entrance token. Once
        // all the players have exited the casino, they win!

        throw new \feException("no impl");
    }
}

// Heist #4
class CaughtRedHandedFinale extends Finale
{
    // XXX: Need to figure out how to implement this:
    //
    // Win: All players must reach 6+ heat during the exact same
    // bouncer move—not simply the same bouncer turn. Yes, the players
    // win instead of losing!

    // Lose: If any player reaches 6+ heat before anyone else, the
    // players lose.  (kelleyk@: This is just the normal lose
    // condition, I think.)

    public function onActivated(World $world)
    {
        // Fake Orders: Take 2 random discarded patrol cards and put
        // them at the bottom of either patrol deck.
        //
        // Setup: Randomly distribute fingerprint tokens to all the
        // players, one each, as follows.  Two Players: Distribute the
        // tokens numbered 1 and 4 randomly.  Three Players:
        // Distribute the tokens numbered 1, 2, and 4 randomly.  Four
        // Players: Distribute the tokens numbered 1, 2, 3, and 4
        // randomly.
        //
        // Each player sets their heat to the number shown on their
        // fingerprint token.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }
}

// Heist #5
class GravityBootsFinale extends Finale
{
    // Gravity Boots movement rules:
    //
    // Whenever the player with the gravity boots moves, they keep
    // moving in the same direction until they reach a wall, an edge
    // tile, or a tile that has another player on it. This costs only
    // one action!
    //
    // That player ignores the effects of all the chips and tiles they
    // move through, except the tile they stop on.
    //
    // That player cannot be moved by gear or chips, and they cannot
    // use the Monorail.
    //
    // Whenever that player moves, the bouncer on their floor is moved
    // in the same direction as the player, until he hits a wall or an
    // edge tile. (The bouncer still takes his turn as normal
    // afterwards.)
    //
    // If a bouncer is moved through any player, that player still
    // gains 2 heat. The bouncer does not stop.

    public function onActivated(World $world)
    {
        // Disguise Change: All players immediately lose 1 heat.
        //
        // Setup: The player who cracked the Safe takes the gravity
        // boots figure. That player, and any other players on the
        // Safe, fall to the tile on the first floor in the same row
        // and column, ignoring any When You Enter effects.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Win: The player with the gravity boots must reach the
        // Safe. Then, all players must exit through the Safe.

        throw new \feException("no impl");
    }
}

// Heist #6
class CatchATigerFinale extends Finale
{
    // Tiger NPC behavior:
    //
    // After you end your turn, the tiger activates if it is on the
    // same floor. It moves to an adjacent tile with a steak token,
    // removing the steak. If there are no such tiles with steak
    // tokens, the tiger does not move.
    //
    // After the tiger activates, the bouncer on the same floor takes
    // his turn as normal.
    //
    // The tiger does not give players heat.

    // XXX: Implement this as part of the Tiger NPC type?
    //
    // Lose: If the tiger is ever on the same tile as a bouncer, the
    // players lose.

    public function onActivated(World $world)
    {
        // Fake Orders: Take 2 random discarded patrol cards and put them
        // at the bottom of either patrol deck.
        //
        // Setup: Place the tiger figure on the same tile as any player,
        // and distribute 4 steak tokens evenly among the players. With 3
        // players, give the extra token to the player who cracked the
        // Safe.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Win: The tiger must eat 4 steaks. Once it has, put the tiger
        // figure on any character card, representing that you have the
        // jeweled necklace. Then, all players must exit through any tile
        // with an entrance token to win.
        //
        // (Basically, don't allow exiting until the tiger has eaten
        // four steaks.  XXX: Show number of steaks as a table status?)

        throw new \feException("no impl");
    }

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        // A player can spend an action to place their steak token on
        // their tile.
        //
        // Steak tokens must be placed on a tile that is adjacent to the
        // tiger and has never had a steak token on it.

        return [];
    }

    public function onSpecialAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

// Heist #7
class HailMaryFinale extends Finale
{
    public function onActivated(World $world)
    {
        // Disguise Change: All players immediately lose 1 heat.
        //
        // Place the football figure on the Safe tile.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Win: Once the football is on a tile with an entrance token,
        // players can exit through any tile with an entrance
        // token. Once all the players have exited the casino, they
        // win!

        throw new \feException("no impl");
    }

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        // A player on the tile with the football can throw it by spending
        // an action.
        //
        // The football must be thrown to another player up to 3 tiles
        // away in a straight line, not diagonally or through walls.
        //
        // In a two-player game, players may throw the football through walls.
        //
        // The football must be thrown down the second-floor Escalator to
        // a player who is on a first-floor tile in the same row and
        // column.
        //
        // The football can only be moved by throwing it.

        return [];
    }

    public function onSpecialAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

// Heist #8
class TheRaidFinale extends Finale
{
    // SWAT token behavior:
    //
    // When a player enters a tile with a SWAT token, that player
    // gains 2 heat.  (Players do not gain heat when a token is placed
    // on their tile during setup.)
    //
    // Bouncers still take their turn as normal. (They ignore SWAT
    // tokens.)

    // XXX:
    //
    // Win: The players must remove the 6 crack tokens. No exit is
    // required because they covered their tracks.

    public function onActivated(World $world)
    {
        // Disguise Change: All players immediately lose 1 heat.
        //
        // Setup: Leave the 6 crack tokens on their tiles. On the second
        // floor, place 1 SWAT token on each even-numbered tile and each
        // facedown tile.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        // When a player enters a tile with a crack token, remove that
        // token.
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        // A player on the first floor can spend an action to remove a
        // SWAT token on the second floor in the same row and column as
        // the player’s tile.

        return [];
    }

    public function onSpecialAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

// Heist #9
class BringingDownTheHouseFinale extends Finale
{
    public function onActivated(World $world)
    {
        // Fake Orders: Take 2 random discarded patrol cards and put them
        // at the bottom of either patrol deck.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Once a player has destroyed this number of walls, that player
        // can exit through any tile with an entrance token. Once all the
        // players have exited the casino, they win!

        throw new \feException("no impl");
    }

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        // A player may spend an action to destroy a wall bordering their
        // tile, which makes a commotion on their tile. They put the
        // destroyed wall near their character card to keep count.
        //
        // A player may destroy multiple walls bordering the same tile,
        // but they will make a new commotion each time, moving the
        // bouncer closer.
        //
        // Win: Each player must destroy a certain number of walls. A
        // player cannot destroy more walls than needed.  Two Players:
        // Each player must destroy 5 walls.  Three Players: Each player
        // must destroy 4 walls.  Four Players: Each player must destroy 3
        // walls.

        return [];
    }

    public function onSpecialAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}

// Heist #10 (only for the Casing the Joint variant)
class BachelorPartyFinale extends Finale
{
    public function onActivated(World $world)
    {
        // Remove both entrance tokens from their tiles.
        //
        // Take 1 random discarded patrol card from the first floor
        // and put an entrance token on the tile indicated.  Then put
        // that card on the bottom of either patrol deck.

        throw new \feException("no impl");
    }

    public function onPcEntersTile(World $world, EffectContext $ctx)
    {
        throw new \feException("no impl");
    }

    public function onPcEscaping(World $world, EffectContext $ctx)
    {
        // Win: All Drunk chips must be on the first floor. One Drunk
        // chip per player must be on the tile with an entrance
        // token. Then players can exit through the tile with an
        // entrance token. Once all players exit the casino, they win!

        throw new \feException("no impl");
    }

    public function getSpecialActions(World $world, PlayerCharacter $pc)
    {
        // Rules: All chips still function normally, except the Drunk
        // chips can now be pushed from the second floor Escalator to the
        // tile in the same row and column on the first floor. This costs
        // 1 action, just like pushing them to an adjacent tile.

        return [];
    }

    public function onSpecialAction(World $world, $actionParams)
    {
        throw new \feException("no impl");
    }
}
