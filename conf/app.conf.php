<?php

/**
 * SeablastConfiguration structure accepts all values, however only the expected ones are processed.
 * The usage of constants defined in the SeablastConstant class is encouraged for the sake of hinting within IDE.
 */

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;

return static function (SeablastConfiguration $SBConfig): void {
    $SBConfig->flag
        ->activate(SeablastConstant::FLAG_WEB_RUNNING)
    ;
    $SBConfig
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/', // page slug, i.e. URL representation
            [
                'template' => 'home', // template used by the View component
                //'id' => 'id', // OPTIONAL number GET parameter required for routing (otherwise 404)
                //'code' => 'code', // OPTIONAL string GET parameter required for routing (otherwise 404)
                //'tableName' => 'content', // OPTIONAL table where the data are stored
                //'filterType' => 'article', // OPTIONAL value of type field used for this collection
                'model' => 'Seablast\Distribution\Models\HomeModel',
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/article', // page slug, i.e. URL representation
            [
                'template' => 'article', // template used by the View component
                'id' => 'id', // OPTIONAL number GET parameter required for routing (otherwise 404)
                'code' => 'code', // OPTIONAL string GET parameter required for routing (otherwise 404)
                'tableName' => 'content', // OPTIONAL table where the data are stored
                'filterType' => 'article', // OPTIONAL value of type field used for this collection
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/item', // page slug, i.e. URL representation
            [
                'template' => 'item', // template used by the View component
                'id' => 'id', // OPTIONAL number GET parameter required for routing
                'code' => 'code', // OPTIONAL string GET parameter required for routing
                'tableName' => 'item', // OPTIONAL table where the data are stored
            //'filterType' => 'article', // OPTIONAL value of type field used for this collection
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/api/mirror',
            [
                'model' => '\Seablast\Distribution\Models\ApiMirrorModel',
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/use-mirror',
            [
                'template' => 'mirror', // template used by the View component
                'model' => '\Seablast\Distribution\Models\UseMirrorModel',
            ]
        )
        ->setArrayArrayString(
            SeablastConstant::APP_MAPPING,
            '/redir', // page slug, i.e. URL representation
            [
                'model' => '\Seablast\Distribution\Models\RedirModel',
            ]
        )
    ;
};
