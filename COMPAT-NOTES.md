- When we enter a "multipleactiveplayer" state, we see two events, not one:

```

Event: gameStateChange
args:
	action: "stCharacterSelection"​​
	active_player: "2393965"​​
	args: Object { cards: (7) […], characterCount: 2, currentPlayerCharacterCount: 1 }​​
	description: "Other players must choose a character."​​
	descriptionmyturn: "${you} must choose a character."​​
	id: 3​​
	multiactive: Array []​​
	name: "stCharacterSelection"​​
	possibleactions: Array(3) [ "actChangeGameFlowSettings", "actPlayCard", "actPass" ]​​
	reflexion: Object { total: {…} }​​
	transitions: Object { tRoundContinues: 3, tRoundDone: 4 }​​
	type: "multipleactiveplayer"

Args are the state descriptor, plus:
  - active_player as a PlayerIdString
  - multiactive as an array of PlayerIdString
    - always empty when the message is being sent in response to a state
      transition (if the state is multiactive; absent otherwise);
      there will be a follow-up "gameStateMultipleActiveUpdate"
      message with actual values
  - "reflexion"
  - args is the result of calling the args function rather than its name
  - id (the key that the state has in the states.inc.php array) is added

Event: gameStateMultipleActiveUpdate
  args: PlayerIdString[]

```

gameStateChange
	- when entering a single-player state, active player is PlayerIdString and multi active is absent

gameStateChange
	- when entering a multiactive state, active_player is still set, and multiactive==[]


XXX: Do we get a gameStateChange message (or anything else) when the page first loads?

XXX: When the page reloads in a multiactive state, how are the set of active players indicated?
