<?php

// Legacy-games ("campaign") data support: the modern object-based API
// (`$this->bga->legacy`) and the storage layer behind it and behind
// the deprecated function-based aliases on `Table`
// (`storeLegacyData()` et al.).
//
// See https://en.doc.boardgamearena.com/Main_game_logic:_yourgamename.game.php#Legacy_games_API
//
// In the opposite of all other game data, legacy data persists after
// the end of a table and can be re-used by a future table of the same
// game.  LocalArena therefore stores it OUTSIDE of the per-table
// `table_N` databases, in the shared `localarena` metadata database
// (see `LocalArenaLegacyStore`).  Keeping it outside the table
// database also means that undoSavepoint()/undoRestorePoint() -- which
// snapshot and restore the table database -- never roll back legacy
// data.
//
// In addition to the game name, legacy data is keyed by a table's
// "legacy scope" (see `Table::localarenaLegacyScope()`).  In normal
// use the scope is empty, giving BGA's semantics: every table of a
// game shares one pool of legacy data.  But the scope is what makes
// tests ISOLATED BY DEFAULT: the test harness gives each test case its
// own unique scope, so a test sees only the legacy data it seeded or
// stored itself -- nothing leaks in from other tests, from previous
// runs, or from interactive play against the same database, and
// nothing a test writes can disturb those -- while tables created
// within one test can still share data by sharing the scope.

require_once APP_GAMEMODULE_PATH . 'module/table/feException.php';
require_once APP_GAMEMODULE_PATH . 'module/table/BgaVisibleSystemException.php';
require_once APP_GAMEMODULE_PATH . 'module/tablemanager/metadata_db.php';

// If you go over 64k of legacy data (serialized as JSON) per player
// per game, storing legacy data FAILS with a `feException` carrying
// this code.  (This matches the constant BGA documents for the same
// purpose.)
if (!defined('FEX_legacy_size_exceeded')) {
  define('FEX_legacy_size_exceeded', 805);
}

// Returns the canonical "team signature" identifying an exact set of
// players.  Team legacy data (`setTeam()` / `getTeam()`) is shared by
// future tables whose players are (exactly) the same set, so the
// signature must not depend on player order.
function localarenaLegacyTeamSignature(array $player_ids): string
{
  $ids = array_map('intval', $player_ids);
  sort($ids);
  return implode(',', $ids);
}

// The storage layer for legacy data.
//
// Rows live in the shared `localarena` database (NOT the per-table
// database); the schema is defined in
// `module/tablemanager/schema.sql`.  Per-player state and team state
// have different keys, so they live in separate tables:
//
//   - `legacy_player_data`, keyed by (scope, game, player, key).
//     Player id 0 holds data global to the game.
//   - `legacy_team_data`, keyed by (scope, game, team), where "team"
//     is a team signature (see `localarenaLegacyTeamSignature()`).
//     Team data is keyless in the BGA API -- one value per team -- so
//     there is no key column.
//
// Values are stored as JSON text; encoding/decoding is the caller's
// business so that the 64k size limit is enforced against exactly the
// serialized bytes.
//
// N.B.: This class uses its own connection to the `localarena`
// database, separate from the per-table connection managed by
// `APP_DbObject`.  In particular, legacy writes are NOT part of the
// per-action transaction on the table database; if an action stores
// legacy data and then fails, the legacy write is not rolled back.
// (On BGA, legacy data may only be stored by the last game action, so
// this divergence should not matter in practice.)
//
// This class is part of the LocalArena implementation and its test
// harness; game code should go through `$this->bga->legacy`.
class LocalArenaLegacyStore
{
  // The maximum total size, in bytes of JSON, of the legacy data for
  // one (scope, game, player) -- or one (scope, game, team) -- bucket.
  const MAX_TOTAL_BYTES = 65536;

  // The maximum (and default) time-to-live, in days.
  const MAX_TTL_DAYS = 365;

  private static $conn_ = null;

  private string $game_name_;
  private string $scope_;

  public function __construct(string $game_name, string $scope)
  {
    $this->game_name_ = $game_name;
    $this->scope_ = $scope;
  }

  private static function conn()
  {
    if (self::$conn_ === null) {
      // Opens the shared metadata database, initializing it from
      // schema.sql (which defines the legacy tables) if it is still
      // empty -- legacy data may be seeded by a test before anything
      // has constructed a TableManager.
      self::$conn_ = localarenaOpenMetadataDb();
    }
    return self::$conn_;
  }

  // Allocates a fresh integer for building a unique legacy scope.
  // Uniqueness is guaranteed by the database (an AUTO_INCREMENT
  // allocation), not by randomness, so callers need not worry about
  // collisions between processes, runs, or machines sharing the
  // database.  Used by the test harness's per-test-case scopes.
  public static function allocateScopeId(): int
  {
    if (!self::conn()->query('INSERT INTO `legacy_scope_alloc` (created) VALUES (NOW())')) {
      throw new \feException('Could not allocate a legacy scope id: ' . self::conn()->error);
    }
    return intval(self::conn()->insert_id);
  }

  private function query(string $sql)
  {
    $result = self::conn()->query($sql);
    if ($result === false) {
      throw new \feException('Legacy-data query failed: ' . self::conn()->error);
    }
    return $result;
  }

  private function esc(string $s): string
  {
    return self::conn()->real_escape_string($s);
  }

  // The WHERE predicate selecting this store's (scope, game) slice of
  // a legacy table.
  private function scopePredicate(): string
  {
    return "scope = '" . $this->esc($this->scope_) . "' AND game_name = '" . $this->esc($this->game_name_) . "'";
  }

  private function playerPredicate(int $player_id): string
  {
    return $this->scopePredicate() . ' AND player_id = ' . $player_id;
  }

  private function teamPredicate(string $team): string
  {
    return $this->scopePredicate() . " AND team = '" . $this->esc($team) . "'";
  }

  // Validates a key for storage: BGA only permits letters and numbers
  // (notably, underscore is NOT allowed).
  public static function validateKey(string $key): void
  {
    if (!preg_match('/^[A-Za-z0-9]+$/', $key)) {
      throw new \BgaVisibleSystemException(
        'Invalid legacy-data key "' . $key . '": keys may only contain letters and numbers.'
      );
    }
  }

  // Validates a key for retrieval, where '%' may additionally be used
  // as a pattern wildcard.
  public static function validateKeyPattern(string $key): void
  {
    if (!preg_match('/^[A-Za-z0-9%]+$/', $key)) {
      throw new \BgaVisibleSystemException(
        'Invalid legacy-data key pattern "' . $key . '": keys may only contain letters, numbers, and "%".'
      );
    }
  }

  // Opportunistically drops expired rows in this (scope, game) slice
  // of `$table`, so that they neither count against the size limit nor
  // collide with re-inserts.
  private function purgeExpired(string $table): void
  {
    $this->query('DELETE FROM `' . $table . '` WHERE ' . $this->scopePredicate() . ' AND expiration <= NOW()');
  }

  // Throws the documented size-limit failure if a bucket would exceed
  // 64k of JSON.
  private function requireWithinSizeLimit(int $total_bytes, string $description): void
  {
    if ($total_bytes > self::MAX_TOTAL_BYTES) {
      throw new \feException(
        'Legacy data size limit exceeded: ' .
          $description .
          ' would bring the total to ' .
          $total_bytes .
          ' bytes (limit ' .
          self::MAX_TOTAL_BYTES .
          ' bytes of JSON per player per game).',
        FEX_legacy_size_exceeded
      );
    }
  }

  // ---- Per-player data (also, with player id 0, game-global data) ----

  // Stores one JSON value, replacing any previous value under the same
  // key and refreshing its expiration.  $ttl_days may be negative or
  // zero (producing an already-expired row); the test harness uses
  // this to exercise TTL behavior.  Enforces the 64k limit against the
  // player's total across all keys.
  public function setPlayerData(int $player_id, string $key, string $json_value, int $ttl_days): void
  {
    $this->purgeExpired('legacy_player_data');

    $other_bytes = intval(
      $this->query(
        'SELECT COALESCE(SUM(LENGTH(data_value)), 0) FROM `legacy_player_data` WHERE ' .
          $this->playerPredicate($player_id) .
          " AND data_key != '" .
          $this->esc($key) .
          "'"
      )->fetch_row()[0]
    );
    $this->requireWithinSizeLimit(
      $other_bytes + strlen($json_value),
      'storing ' . strlen($json_value) . ' bytes under key "' . $key . '"'
    );

    $this->query(
      'INSERT INTO `legacy_player_data` (scope, game_name, player_id, data_key, data_value, expiration) VALUES (' .
        "'" .
        $this->esc($this->scope_) .
        "','" .
        $this->esc($this->game_name_) .
        "'," .
        $player_id .
        ",'" .
        $this->esc($key) .
        "','" .
        $this->esc($json_value) .
        "',DATE_ADD(NOW(), INTERVAL " .
        $ttl_days .
        ' DAY)) ON DUPLICATE KEY UPDATE data_value = VALUES(data_value), expiration = VALUES(expiration)'
    );
  }

  // Returns the non-expired values matching $key_or_pattern for the
  // given player, as an array mapping key => JSON text.  '%' acts as a
  // SQL LIKE wildcard.
  public function getPlayerData(int $player_id, string $key_or_pattern): array
  {
    $key_pred = strpos($key_or_pattern, '%') === false ? "data_key = '" : "data_key LIKE '";
    $result = $this->query(
      'SELECT data_key, data_value FROM `legacy_player_data` WHERE ' .
        $this->playerPredicate($player_id) .
        ' AND ' .
        $key_pred .
        $this->esc($key_or_pattern) .
        "' AND expiration > NOW() ORDER BY data_key ASC"
    );

    $ret = [];
    foreach (mysqli_fetch_all($result, MYSQLI_ASSOC) as $row) {
      $ret[$row['data_key']] = $row['data_value'];
    }
    return $ret;
  }

  public function deletePlayerData(int $player_id, string $key): void
  {
    $this->query(
      'DELETE FROM `legacy_player_data` WHERE ' .
        $this->playerPredicate($player_id) .
        " AND data_key = '" .
        $this->esc($key) .
        "'"
    );
  }

  // ---- Team data (keyless in the BGA API: one value per team) ----

  public function setTeamData(string $team, string $json_value, int $ttl_days): void
  {
    $this->purgeExpired('legacy_team_data');

    // The team's bucket is its single row, so the 64k limit is simply
    // a bound on this value.
    $this->requireWithinSizeLimit(strlen($json_value), 'storing ' . strlen($json_value) . ' bytes of team data');

    $this->query(
      'INSERT INTO `legacy_team_data` (scope, game_name, team, data_value, expiration) VALUES (' .
        "'" .
        $this->esc($this->scope_) .
        "','" .
        $this->esc($this->game_name_) .
        "','" .
        $this->esc($team) .
        "','" .
        $this->esc($json_value) .
        "',DATE_ADD(NOW(), INTERVAL " .
        $ttl_days .
        ' DAY)) ON DUPLICATE KEY UPDATE data_value = VALUES(data_value), expiration = VALUES(expiration)'
    );
  }

  // Returns the team's non-expired JSON text, or null if there is
  // none.
  public function getTeamData(string $team): ?string
  {
    $result = $this->query(
      'SELECT data_value FROM `legacy_team_data` WHERE ' . $this->teamPredicate($team) . ' AND expiration > NOW()'
    );
    $row = $result->fetch_row();
    return $row === null ? null : $row[0];
  }

  public function deleteTeamData(string $team): void
  {
    $this->query('DELETE FROM `legacy_team_data` WHERE ' . $this->teamPredicate($team));
  }
}

// The legacy-games API object, available to game code as
// `$this->bga->legacy`.
class LocalArenaLegacy
{
  private $table_;

  public function __construct($table)
  {
    $this->table_ = $table;
  }

  // The table's legacy scope is assigned by `TableManager` after the
  // `Table` constructor has run, so resolve it per call rather than
  // binding it at construction time.
  private function store(): LocalArenaLegacyStore
  {
    return new LocalArenaLegacyStore($this->table_->localarenaGetGameName(), $this->table_->localarenaLegacyScope());
  }

  // Clamps/validates a caller-supplied TTL: the maximum (and default)
  // is 365 days.
  private function normalizeTtl(int $ttl): int
  {
    if ($ttl < 1) {
      throw new \BgaVisibleSystemException('Legacy-data TTL must be at least 1 day (got ' . $ttl . ').');
    }
    return min($ttl, LocalArenaLegacyStore::MAX_TTL_DAYS);
  }

  // BGA throws when legacy data is stored during game setup (storing
  // is only legal once the game is over); reproduce that so games
  // catch the bug locally.
  private function requireNotInGameSetup(string $caller): void
  {
    if ($this->table_->localarenaInGameSetup()) {
      throw new \BgaVisibleSystemException(
        $caller . '() cannot be called during game setup; legacy data may only be stored when the game is over.'
      );
    }
  }

  private function encode($value): string
  {
    $json = json_encode($value);
    if ($json === false) {
      throw new \BgaVisibleSystemException('Could not serialize legacy data as JSON: ' . json_last_error_msg());
    }
    return $json;
  }

  // Store some data associated with $key for the given player and the
  // current game.  This data persists after the end of this table and
  // can be re-used by a future table of the same game.  Player id 0
  // (which no real player uses) stores game-global data.
  //
  // IMPORTANT: On BGA the only legal place to call this is when the
  // game is over at your table (the last game action); it throws
  // during game setup, and LocalArena reproduces that.
  public function set(string $key, int $playerId, $value, int $ttl = 365): void
  {
    $this->requireNotInGameSetup('legacy->set');
    LocalArenaLegacyStore::validateKey($key);
    $this->store()->setPlayerData($playerId, $key, $this->encode($value), $this->normalizeTtl($ttl));
  }

  // Get the data associated with $key for the given player and the
  // current game, or $defaultValue if there is none (or it has
  // expired).
  //
  // A '%' in $key acts as a wildcard; a pattern get instead returns an
  // array mapping each matching key to its value (empty if nothing
  // matches; $defaultValue is not used).
  public function get(string $key, int $playerId, $defaultValue = null)
  {
    LocalArenaLegacyStore::validateKeyPattern($key);
    $rows = $this->store()->getPlayerData($playerId, $key);

    if (strpos($key, '%') !== false) {
      return array_map(function ($json) {
        return json_decode($json, /*associative=*/ true);
      }, $rows);
    }

    if (!array_key_exists($key, $rows)) {
      return $defaultValue;
    }
    return json_decode($rows[$key], /*associative=*/ true);
  }

  // Remove the legacy data with the given key (useful to free some
  // space to avoid going over the 64k limit).
  public function delete(string $key, int $playerId): void
  {
    LocalArenaLegacyStore::validateKey($key);
    $this->store()->deletePlayerData($playerId, $key);
  }

  // Same as set(), except that the data is stored for the whole team
  // at the current table -- the exact set of players -- and does not
  // use a key.  A future table with (exactly) the same players sees it
  // via getTeam().
  public function setTeam($value, int $ttl = 365): void
  {
    $this->requireNotInGameSetup('legacy->setTeam');
    $this->store()->setTeamData(
      $this->table_->localarenaLegacyTeamSignature(),
      $this->encode($value),
      $this->normalizeTtl($ttl)
    );
  }

  // Get the data previously stored (by setTeam()) for the exact set of
  // players at the current table, or $defaultValue if there is none.
  public function getTeam($defaultValue = null)
  {
    $json = $this->store()->getTeamData($this->table_->localarenaLegacyTeamSignature());
    if ($json === null) {
      return $defaultValue;
    }
    return json_decode($json, /*associative=*/ true);
  }

  public function deleteTeam(): void
  {
    $this->store()->deleteTeamData($this->table_->localarenaLegacyTeamSignature());
  }

  // Returns the raw (JSON text) values matching $key for the given
  // player, as key => JSON.  This is part of the LocalArena API, not
  // the BGA API; it backs the deprecated function-based aliases on
  // `Table`, which returned JSON-encoded values.
  public function localarenaGetRaw(string $key, int $playerId): array
  {
    LocalArenaLegacyStore::validateKeyPattern($key);
    return $this->store()->getPlayerData($playerId, $key);
  }

  // As localarenaGetRaw(), for the current table's team data; returns
  // the JSON text or null.
  public function localarenaGetTeamRaw(): ?string
  {
    return $this->store()->getTeamData($this->table_->localarenaLegacyTeamSignature());
  }
}
