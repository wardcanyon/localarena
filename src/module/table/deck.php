<?php

class deck
{
    public string $tableName;
    public bool $autoreshuffle = false;
    public $autoreshuffle_trigger = null;
    public $autoreshuffle_custom = ['deck' => 'discard'];

    // N.B.: This shouldn't be public, but the LocalArena code at
    // table.game.php:430 makes that tricky.
    public $game;

    private string $column_prefix_ = 'card';

  /*
   * Initializing
   */

  function init(string $tableName)
  {
    $this->tableName = $tableName;
  }

  function createCards($cards, $location = 'deck', $location_arg = null)
  {
    $sql = "INSERT INTO {$this->tableName} ({$this->column_prefix_}_type, {$this->column_prefix_}_type_arg, {$this->column_prefix_}_location, {$this->column_prefix_}_location_arg) VALUES ";
    $values = [];
    if (is_null($location_arg)) {
      $loc_arg = 1;
    } else {
      $loc_arg = $location_arg;
    }

    foreach ($cards as $card) {
      for ($i = 0; $i < $card['nbr']; $i++) {
        $values[] = "('{$card['type']}',{$card['type_arg']},'{$location}',{$loc_arg})";
        if (is_null($location_arg)) {
          $loc_arg++;
        }
      }
    }
    $sql .= implode(',', $values);
    $this->game->DbQuery($sql);
  }

  /*
   * Picking cards
   */

  function pickCard($location, $player_id)
  {
    return $this->pickCardForLocation($location, 'hand', $player_id);
  }

  function pickCards($nbr, $location, $player_id)
  {
    return $this->pickCardsForLocation($nbr, $location, 'hand', $player_id);
  }

  function pickCardForLocation($from_location, $to_location, $location_arg = 0)
  {
    $card = $this->getCardOnTop($from_location);
    if ($card == null && $this->autoreshuffle && array_key_first($this->autoreshuffle_custom) == $from_location) {
      $this->autoReshuffle();
      $card = $this->getCardOnTop($from_location);
    }

    if ($card != null) {
      $this->moveCard($card['id'], $to_location, $location_arg);
      $card['location'] = $to_location;
      $card['location_arg'] = $location_arg;
    }

    return $card;
  }

  function pickCardsForLocation($nbr, $from_location, $to_location, $location_arg = 0, $no_deck_reform = false)
  {
    $cards = $this->getCardsOnTop($nbr, $from_location);
    $this->moveCards($cards, $to_location, $location_arg);

    if (
      count($cards) < $nbr &&
      $this->autoreshuffle &&
      array_key_first($this->autoreshuffle_custom) == $from_location &&
      !$no_deck_reform
    ) {
      $this->autoReshuffle();
      $nbr = $nbr - count($cards);
      $cards = array_merge($cards, $this->getCardsOnTop($nbr, $from_location));
      $this->moveCards($cards, $to_location, $location_arg);
    }
    return $this->getCards($cards);
  }

  /*
   * Moving cards
   */
  function moveCard($card_id, $location, $location_arg = 0)
  {
    $sql = "update {$this->tableName} set {$this->column_prefix_}_location = '{$location}', {$this->column_prefix_}_location_arg = {$location_arg}  where {$this->column_prefix_}_id = {$card_id}";
    $this->game->DbQuery($sql);
  }

  function moveCards($cards, $location, $location_arg = 0)
  {
    if (count($cards) > 0) {
      $cardIds = implode(',', $cards);
      $sql = "update {$this->tableName} set {$this->column_prefix_}_location = '{$location}', {$this->column_prefix_}_location_arg = {$location_arg}  where {$this->column_prefix_}_id in ({$cardIds})";
      $this->game->DbQuery($sql);
    }
  }

  function insertCard($card_id, $location, $location_arg)
  {
    if (
      $this->game->DbQuery(
        "select count(*) from {$this->tableName} where {$this->column_prefix_}_location = '{$location}' and {$this->column_prefix_}_location_arg={$location_arg}"
      ) == 0
    ) {
      $sql = "update {$this->tableName} set {$this->column_prefix_}_location_arg = {$this->column_prefix_}_location_arg + 1  where {$this->column_prefix_}_location  = '{$location}' and {$this->column_prefix_}_location_arg>={$location_arg}";
      $this->game->DbQuery($sql);
    }
    $this->moveCard($card_id, $location, $location_arg);
  }

  function insertCardOnExtremePosition($card_id, $location, $bOnTop)
  {
    $location_arg = 1;
    if ($bOnTop) {
      $location_arg = $this->game->getUniqueValueFromDB(
        "select max({$this->column_prefix_}_location_arg)+1 from {$this->tableName} where {$this->column_prefix_}_location = '{$location}'"
      );
    }
    $this->insertCard($card_id, $location, $location_arg);
  }

  function moveAllCardsInLocation($from_location, $to_location, $from_location_arg = null, $to_location_arg = 0)
  {
    $sql = "update {$this->tableName} set {$this->column_prefix_}_location = '{$to_location}', {$this->column_prefix_}_location_arg = {$to_location_arg} where {$this->column_prefix_}_location = '{$from_location}'";
    if (!is_null($from_location_arg)) {
      $sql .= " and {$this->column_prefix_}_location_arg= {$from_location_arg}";
    }
    $this->game->DbQuery($sql);
  }

  function moveAllCardsInLocationKeepOrder($from_location, $to_location)
  {
    $sql = "update {$this->tableName} set {$this->column_prefix_}_location = '{$to_location}' where {$this->column_prefix_}_location = '{$from_location}'";
    $this->game->DbQuery($sql);
  }

  function playCard($card_id)
  {
    $this->insertCardOnExtremePosition($card_id, 'discard', true);
  }

  /*
   * Get cards information
   */
  function getCard($card_id)
  {
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where {$this->column_prefix_}_id = {$card_id}";
    return $this->game->getObjectFromDB($sql);
  }

  function getCards($cards_array)
  {
    $cardIds = implode(',', $cards_array);
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where {$this->column_prefix_}_id in ({$cardIds})";
    return $this->game->getCollectionFromDB($sql);
  }

  function getCardsInLocation($location, $location_arg = null, $order_by = null)
  {
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where {$this->column_prefix_}_location = '{$location}'";
    if (!is_null($location_arg)) {
      $sql .= " and {$this->column_prefix_}_location_arg = {$location_arg}";
    }
    if (!is_null($order_by)) {
      $sql .= " order by {$order_by}";
    }
    return $this->game->getCollectionFromDB($sql);
  }

  function countCardInLocation($location, $location_arg = null)
  {
    $sql = "select count(*) from {$this->tableName} where {$this->column_prefix_}_location = '{$location}'";
    if (!is_null($location_arg)) {
      $sql .= " and {$this->column_prefix_}_location_arg = {$location_arg}";
    }
    return $this->game->getUniqueValueFromDB($sql);
  }

  function countCardsInLocations()
  {
    $sql = "select {$this->column_prefix_}_location location, count(*) nb from {$this->tableName} group by {$this->column_prefix_}_location";
    $list = $this->game->getObjectListFromDB($sql);
    $ret = [];
    foreach ($list as $row) {
      $ret[$row['location']] = intval($row['nb']);
    }
    return $ret;
  }

  function countCardsByLocationArgs($location)
  {
    $sql = "select {$this->column_prefix_}_location_arg location, count(*) nb from {$this->tableName} where {$this->column_prefix_}_location={$location} group by {$this->column_prefix_}_location_arg";
    $list = $this->game->getObjectListFromDB($sql);
    $ret = [];
    foreach ($list as $row) {
      $ret[$row['location']] = intval($row['nb']);
    }
    return $ret;
  }

  function getPlayerHand($player_id)
  {
    return $this->getCardsInLocation('hand', $player_id);
  }

  function getCardOnTop($location)
  {
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where {$this->column_prefix_}_location = '{$location}' order by {$this->column_prefix_}_location_arg desc limit 1";
    $card = $this->game->getObjectFromDB($sql);
    return $card;
  }

  function getCardsOnTop($nbr, $location)
  {
    $sql =
      "select {$this->column_prefix_}_id from {$this->tableName} where {$this->column_prefix_}_location = '{$location}' order by {$this->column_prefix_}_location_arg desc limit " .
      $nbr;
    $cards = $this->game->getObjectListFromDB($sql, true);
    return $cards;
  }

  function getExtremePosition($bGetMax, $location)
  {
    $order = $bGetMax ? 'desc' : 'asc';
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where {$this->column_prefix_}_location = '{$location}' order by {$this->column_prefix_}_location_arg {$order} limit 1";
    $card = $this->game->getObjectFromDB($sql);
    return $card;
  }

  function getCardsOfType($type, $type_arg = null)
  {
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where {$this->column_prefix_}_type = '{$type}'";
    if (!is_null($type_arg)) {
      $sql .= " and {$this->column_prefix_}_type_arg = {$type_arg}";
    }
    return $this->game->getCollectionFromDB($sql);
  }

  function getCardsOfTypeInLocation($type, $type_arg, $location, $location_arg = null)
  {
    $sql = "select {$this->column_prefix_}_id id, {$this->column_prefix_}_type type, {$this->column_prefix_}_type_arg type_arg, {$this->column_prefix_}_location location, {$this->column_prefix_}_location_arg location_arg from {$this->tableName} where  {$this->column_prefix_}_type = '{$type}' and {$this->column_prefix_}_location = '{$location}'";
    if (!is_null($location_arg)) {
      $sql .= " and {$this->column_prefix_}_location_arg = {$location_arg}";
    }
    if (!is_null($type_arg)) {
      $sql .= " and {$this->column_prefix_}_type_arg = {$type_arg}";
    }
    return $this->game->getCollectionFromDB($sql);
  }

  /**
   * Shuffling
   */

  function autoReshuffle()
  {
    $to = array_key_first($this->autoreshuffle_custom);
    $from = $this->autoreshuffle_custom[$to];

    $this->moveAllCardsInLocation($from, $to);
    $this->shuffle($to);

    if ($this->autoreshuffle_trigger != null) {
      $meth = $this->autoreshuffle_trigger['method'];
      $this->autoreshuffle_trigger['obj']->$meth();
    }
  }

  function shuffle($location)
  {
    $cards = $this->getCardsInLocation($location);
    $nargs = [];
    for ($i = 1; $i <= count($cards); $i++) {
      $nargs[] = $i;
    }
    shuffle($nargs);
    $cnt = count($cards);
    for ($i = 1; $i <= $cnt; $i++) {
      $card = array_shift($cards);
      $narg = array_shift($nargs);
      $sql = "update {$this->tableName} set {$this->column_prefix_}_location_arg={$narg} where {$this->column_prefix_}_id={$card['id']}";
      $this->game->DbQuery($sql);
    }
  }
}
