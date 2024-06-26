<?php declare(strict_types=1);

class LocalArenaGameConfig {
    protected $validators_alldatas_ = [];
    protected $validators_args_ = [];
    protected $validators_notif_ = [];

    public function validateAllDatas($alldatas): void {
        echo '*** XXX: validating alldatas (' . count($this->validators_alldatas_) . ' hooks)...' . "\n";
        foreach ($this->validators_alldatas_ as $cb) {
            $cb($alldatas);
        }
    }

    public function registerAllDatasValidator($cb): void {
        echo '*** XXX: registering validator...' . "\n";
        $this->validators_alldatas_[] = $cb;
        echo '  - there are now ' . count($this->validators_alldatas_) . ' hooks...' . "\n";
    }

    public function registerArgsValidator($cb): void {
        $this->validators_args_[] = $cb;
    }

    public function registerNotifValidator($cb): void {
        $this->validators_notif_[] = $cb;
    }
}
