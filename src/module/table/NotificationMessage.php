<?php

// `Bga\GameFramework\NotificationMessage`: the message (and extra
// arguments) that a framework component sends along with the
// notification it produces.
//
// Components that update the front-end -- currently the counters (see
// `LocalArenaCounters.php`) -- take one of these wherever BGA's API
// documents a `?\Bga\GameFramework\NotificationMessage $message`
// parameter:
//
//   - A `NotificationMessage` with a non-empty message: the
//     notification is sent, and the message appears in the game log.
//   - A `NotificationMessage` with an empty message (the default):
//     the notification is sent, but nothing appears in the game log.
//   - `null`: no notification is sent at all, so the front end is not
//     updated.
//
// The component supplies the arguments that describe what it did (for
// counters: `name`, `value`, `oldValue`, `inc`, `absInc`, and, for
// player counters, `playerId` and `player_name`), so `$args` only
// needs to carry the arguments that are specific to the message.

namespace Bga\GameFramework;

class NotificationMessage
{
  public function __construct(
    // The game-log message.  Should be wrapped in `clienttranslate()`
    // (as ever, at the point where the literal appears).  An empty
    // message means "update the front end, but log nothing".
    public string $message = '',

    // Substitution arguments for `$message`, beyond those that the
    // sending component supplies itself.
    public array $args = []
  ) {
  }
}
