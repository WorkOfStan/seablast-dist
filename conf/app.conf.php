<?php

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

// TODO Description: use AppConstants ... A ty budou nastaveny kde?
// ... Slouží jako nápověda - každá hodnota je akceptovaná

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        ->activate(SeablastConstant::FLAG_WEB_RUNNING)
    ;
};
