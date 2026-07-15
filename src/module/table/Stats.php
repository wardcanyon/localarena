<?php

// Game-statistics support: loading of the game's statistics
// description (`stats.json`, `stats.jsonc`, or the legacy
// `stats.inc.php`) and the object-based statistics APIs
// (`$this->tableStats` / `$this->playerStats`).
//
// See https://en.doc.boardgamearena.com/Game_statistics:_stats.json

require_once APP_GAMEMODULE_PATH . 'module/table/feException.php';

// Strips "//" and "/* ... */" comments from JSON text (so that we can
// accept "jsonc" input, which BGA permits for `stats.json`).
// Comment markers inside string literals are preserved.
function localarenaStripJsonComments(string $text): string
{
  $result = '';
  $len = strlen($text);
  $in_string = false;
  for ($i = 0; $i < $len; $i++) {
    $c = $text[$i];
    if ($in_string) {
      $result .= $c;
      if ($c === '\\' && $i + 1 < $len) {
        // Copy the escaped character so that an escaped quote (\")
        // doesn't end the string.
        $i++;
        $result .= $text[$i];
      } elseif ($c === '"') {
        $in_string = false;
      }
      continue;
    }
    if ($c === '"') {
      $in_string = true;
      $result .= $c;
      continue;
    }
    if ($c === '/' && $i + 1 < $len && $text[$i + 1] === '/') {
      while ($i < $len && $text[$i] !== "\n") {
        $i++;
      }
      // Keep the newline (if any) so that line numbers in
      // `json_decode()` error messages stay meaningful.
      if ($i < $len) {
        $result .= "\n";
      }
      continue;
    }
    if ($c === '/' && $i + 1 < $len && $text[$i + 1] === '*') {
      $i += 2;
      while ($i + 1 < $len && !($text[$i] === '*' && $text[$i + 1] === '/')) {
        $i++;
      }
      // Skip the closing "*/" (or the final character, for an
      // unterminated comment).
      $i++;
      continue;
    }
    $result .= $c;
  }
  return $result;
}

// Loads a game's statistics description from `$game_dir` and returns
// it as an associative array with (at least) "table" and "player"
// sections.
//
// Modern games describe their statistics in `stats.json` (which BGA
// permits to contain jsonc-style comments) or `stats.jsonc`; older
// games use a `stats.inc.php` that defines `$stats_type`.  The JSON
// files may also carry keys that only matter for BGA's end-of-game
// display (such as "value_labels" and per-stat "display" properties);
// these are preserved in the returned array but otherwise ignored by
// LocalArena.
function localarenaLoadStatsDescription(string $game_dir): array
{
  foreach (['stats.json', 'stats.jsonc'] as $filename) {
    $path = $game_dir . '/' . $filename;
    if (file_exists($path)) {
      $stats_type = json_decode(localarenaStripJsonComments(file_get_contents($path)), /*associative=*/ true);
      if (!is_array($stats_type)) {
        throw new \feException('Could not parse ' . $path . ': ' . json_last_error_msg());
      }
      $stats_type += ['table' => [], 'player' => []];
      return $stats_type;
    }
  }

  $path = $game_dir . '/stats.inc.php';
  if (!file_exists($path)) {
    throw new \feException(
      'Game has no statistics description (expected stats.json, stats.jsonc, or stats.inc.php in ' . $game_dir . ').'
    );
  }
  include $path;
  if (!isset($stats_type) || !is_array($stats_type)) {
    throw new \feException($path . ' did not define $stats_type.');
  }
  return $stats_type;
}

// Casts a raw stat value (as read from the database) to the type
// declared for the stat in the game's statistics description.
function localarenaCastStatValue(?string $declared_type, $raw_value)
{
  switch ($declared_type) {
    case 'float':
      return floatval($raw_value);
    case 'bool':
      return intval($raw_value) !== 0;
    default:
      // "int", or no declared type.
      return intval($raw_value);
  }
}

// The table-statistics API object, available to game code as
// `$this->tableStats`.  Each table statistic has a single value for
// the whole game.
class TableStats
{
  private $table_;

  public function __construct($table)
  {
    $this->table_ = $table;
  }

  // Creates the statistic entry (or entries; `$nameOrNames` may be a
  // single name or an array of names) with the given starting value.
  // Like `initStat()`, this should be called from `setupNewGame()`.
  public function init($nameOrNames, $value): void
  {
    foreach ((array) $nameOrNames as $name) {
      $this->table_->initStat('table', $name, $value);
    }
  }

  public function set(string $name, $value): void
  {
    $this->table_->setStat($value, $name);
  }

  public function inc(string $name, $delta = 1): void
  {
    $this->table_->incStat($delta, $name);
  }

  // Returns the statistic's current value, cast to its declared type
  // ("int", "float", or "bool").
  public function get(string $name)
  {
    $raw_value = $this->table_->getStat($name);
    return localarenaCastStatValue($this->table_->stats_type['table'][$name]['type'] ?? null, $raw_value);
  }
}

// The player-statistics API object, available to game code as
// `$this->playerStats`.  Each player statistic has one value per
// player.
class PlayerStats
{
  private $table_;

  public function __construct($table)
  {
    $this->table_ = $table;
  }

  // Creates the statistic entry (or entries; `$nameOrNames` may be a
  // single name or an array of names) for every player, with the
  // given starting value.  With `$updateTableStat`, the table
  // statistic with the same name is initialized as well.  Like
  // `initStat()`, this should be called from `setupNewGame()`.
  public function init($nameOrNames, $value, bool $updateTableStat = false): void
  {
    foreach ((array) $nameOrNames as $name) {
      $this->table_->initStat('player', $name, $value);
      if ($updateTableStat) {
        $this->table_->initStat('table', $name, $value);
      }
    }
  }

  public function set(string $name, $value, int $player_id): void
  {
    $this->table_->setStat($value, $name, $player_id);
  }

  // Increments (or, with a negative `$delta`, decrements) the
  // statistic for the given player.  With `$updateTableStat`, the
  // table statistic with the same name is incremented as well.
  public function inc(string $name, $delta, int $player_id, bool $updateTableStat = false): void
  {
    $this->table_->incStat($delta, $name, $player_id);
    if ($updateTableStat) {
      $this->table_->incStat($delta, $name);
    }
  }

  // Returns the statistic's current value for the given player, cast
  // to its declared type ("int", "float", or "bool").
  public function get(string $name, int $player_id)
  {
    $raw_value = $this->table_->getStat($name, $player_id);
    return localarenaCastStatValue($this->table_->stats_type['player'][$name]['type'] ?? null, $raw_value);
  }
}
