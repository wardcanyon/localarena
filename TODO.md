### Misc

- Right now, the websock server will drop clients if an exception is
  thrown.  We should instead send the client-side framework code a
  message about the error.

- Don't allow game to start with an invalid number of players.

- "reversi": don't allow a client to play when it's someone else's
  turn!

- Make player-board DOM elements more realistic, since some (many?)
  games manipulate them.  We've done enough here to make "reversi"
  happy, but other games (e.g. "burglebrostwo") may not be.

- Silence the ServerName warnings that Apache prints on startup; they
  might confuse people.

- Dockerize the `grunt` build process.

- Make `getUniqueValueFromDB()`, `DbQuery()` static again.

### For testing

- Add test fixtures for notifs.

- A missing validation check in "burglebrostwo" made the game's state
  machine run in a tight loop.  Should we have a limit on the number
  of state transitions that can happen in response to one action
  (e.g. 100), to avoid tests running in circles for so long before
  dying?
