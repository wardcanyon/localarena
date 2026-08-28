<?php

// This is here to let us sneak some context information to object
// constructors that may be invoked by arbitrary game code.
//
// In particular, the `APP_DbObject` needs to understand which table
// database to connect to.  We could inject a database connection into
// the game class after instantiation, but games may instantiate other
// subclasses.  We can't simply add a constructor parameter because
// that would require game code to plumb through those parameters.
//
// The implementation of this class will need to become more
// sophisticated if we ever use threading, because then there might be
// a different active context per thread.
class LocalArenaContext
{
  public int $table_id;

  // The game the table being instantiated is running, as the registry
  // recorded it (`table`.`table_game`).
  //
  // This is here for the same reason `$table_id` is: `Table`'s own
  // constructor needs it -- it loads gameinfos, material, states, the
  // stats description and the action file from that directory -- so it
  // cannot be injected after construction.  `TableManager` has the name
  // in hand at that moment, having just used it to locate the game's
  // class; before this it had to ask the constructed game to name
  // itself back, which is what `Table::getGameName()` was for.
  public ?string $game_name;

  public static function get(): LocalArenaContext
  {
    global $localarena_context;
    if (is_null($localarena_context)) {
      $localarena_context = new LocalArenaContext();
      $localarena_context->table_id = -1;
      $localarena_context->game_name = null;
    }
    return $localarena_context;
  }
}
