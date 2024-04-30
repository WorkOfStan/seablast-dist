<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 * `app.conf.dist.php` is a template for `app.conf.local.php`
 */

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

//use Seablast\Distribution\Models\AppConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        //->deactivate(SeablastConstant::FLAG_WEB_RUNNING)
        //->activate(SeablastConstant::FLAG_DEBUG_JSON) // JSON would be displayed directly with Tracy
        ->activate(SeablastConstant::ADMIN_MAIL_ENABLED) // live on server
        ->activate(SeablastConstant::USER_MAIL_ENABLED)  // live on server
        ;
    $SBConfig
        ->setArrayString(SeablastConstant::DEBUG_IP_LIST, [
            //'9.9.9.9', // dev office
        ])
        //->setString(SeablastConstant::FROM_MAIL_ADDRESS, 'no-reply@server.cz') // live on server
        //->setInt(
        //    SeablastConstant::SB_WEB_FORCE_ASSET_VERSION,
        //    153 + ($SBConfig->getInt(SeablastConstant::SB_WEB_FORCE_ASSET_VERSION))
        //)
    ;
};
