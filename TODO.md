- Right now, the websock server will drop clients if an exception is
  thrown.  We should instead send the client-side framework code a
  message about the error.

- Don't allow game to start with an invalid number of players.

- "reversi": don't allow a client to play when it's someone else's
  turn!

- Fix hardwired player name in index.php; fix page title.

- "hearts": no support for private state.

- Make player-board DOM elements more realistic, since some (many?)
  games manipulate them.  We've done enough here to make "reversi"
  happy, but other games (e.g. "burglebrostwo") may not be.

- Silence the ServerName warnings that Apache prints on startup; they
  might confuse people.

- Dockerize the `grunt` build process.
