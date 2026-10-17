<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 * `app.conf.dist.php` is a template for `app.conf.local.php`
 */

declare(strict_types=1);

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

//use Seablast\Auth\AuthConstant;
//use Seablast\Distribution\Models\AppConstant;
//use Seablast\I18n\I18nConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        //->deactivate(SeablastConstant::FLAG_WEB_RUNNING) // DEBUG_IP_LIST clients bypass maintenance
        //->activate(SeablastConstant::FLAG_DEBUG_JSON) // JSON would be displayed directly with Tracy
        ->activate(SeablastConstant::ADMIN_MAIL_ENABLED) // live on server
        ->activate(SeablastConstant::USER_MAIL_ENABLED)  // live on server
        //->deactivate(I18nConstant::FLAG_SHOW_LANGUAGE_SELECTOR)
        ;
    $SBConfig
        // Enable Tracy and maintenance bypass for direct local development only.
        // Behind a shared proxy, configure SB_TRUSTED_PROXIES and allow actual developer client IPs instead.
        ->setArrayString(SeablastConstant::DEBUG_IP_LIST, [
            //'127.0.0.1', // localhost IPv4
            //'::1', // localhost IPv6
            //'9.9.9.9', // dev office
        ])
        //->setString(SeablastConstant::ADMIN_MAIL_ADDRESS, 'admin@test.xy') // fill-in your admin email
        //->setString(SeablastConstant::FROM_MAIL_ADDRESS, 'no-reply@server.cz') // live on server
        //->setInt(
        //    SeablastConstant::SB_WEB_FORCE_ASSET_VERSION,
        //    153 + ($SBConfig->getInt(SeablastConstant::SB_WEB_FORCE_ASSET_VERSION))
        //)
        //->setString(AuthConstant::FACEBOOK_APP_SECRET, '')
    ;
};
