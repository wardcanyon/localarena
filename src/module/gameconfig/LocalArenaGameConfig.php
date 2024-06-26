<?php declare(strict_types=1);

class LocalArenaGameConfig {
    protected $validators_alldatas_ = [];
    protected $validators_args_ = [];
    protected $validators_notif_ = [];

    public function validateAllDatas($alldatas): void {
        // echo '*** XXX: validating alldatas (' . count($this->validators_alldatas_) . ' hooks)...' . "\n";
        foreach ($this->validators_alldatas_ as $cb) {
            $cb($alldatas);
        }
    }

    // If $player_id is set, the $args were rendered for that player
    // (and will not include any private info that that player should
    // not receive).  If it is null, then private information has not
    // been rendered yet.
    public function validateArgs($state, $args, ?string $player_id): void {
        foreach ($this->validators_args_ as $cb) {
            $cb($state, $args, $player_id);
        }
    }

    // If $player_id is set, the $args were sent via `notifyPlayer()`
    // to that individual player.  If it is null, the $args were sent
    // via `notifyAllPlayers()` (to all players).
    public function validateNotif($notif_name, $args, ?string $player_id): void {
        foreach ($this->validators_notif_ as $cb) {
            $cb($notif_name, $args, $player_id);
        }
    }

    public function registerAllDatasValidator($cb): void {
        // echo '*** XXX: registering validator...' . "\n";
        $this->validators_alldatas_[] = $cb;
        // echo '  - there are now ' . count($this->validators_alldatas_) . ' hooks...' . "\n";
    }

    public function registerArgsValidator($cb): void {
        $this->validators_args_[] = $cb;
    }

    public function registerNotifValidator($cb): void {
        $this->validators_notif_[] = $cb;
    }
}
