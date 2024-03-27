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
class LocalArenaContext {
    public int $table_id;

    public static function get(): LocalArenaContext {
        global $localarena_context;
        if (is_null($localarena_context)) {
            $localarena_context = new LocalArenaContext();
            $localarena_context->table_id = -1;
        }
        return $localarena_context;
    }
}
