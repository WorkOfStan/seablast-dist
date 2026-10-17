<?php

declare(strict_types=1);

$migrations = ['%%PHINX_CONFIG_DIR%%/db/migrations']; // the default path
// Note: if phinx create selection gets stuck, it may help to temporarily hide the other migration paths
foreach (
    [
        'seablast/auth/conf/db/migrations',
        'seablast/i18n/conf/db/migrations',
    ] as $path
) {
    if (is_dir(__DIR__ . '/../vendor/' . $path)) {
        $migrations[] = '%%PHINX_CONFIG_DIR%%/../vendor/' . $path;
    }
}

return
[
    'paths' => [
        'migrations' => $migrations,
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'production' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'production_db',
            'user' => 'root',
            'pass' => '',
            'port' => '3306',
            'charset' => 'utf8',
        ],
        'development' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'seablast_dist',
            'user' => 'root',
            'pass' => '',
            'port' => '3306',
            'charset' => 'utf8',
            'table_prefix' => 'seablast_dist_',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => 'localhost',
            'name' => 'testing_db',
            'user' => 'root',
            'pass' => 'root', // so that it works in GitHub automation
            'port' => '3306',
            'charset' => 'utf8',
            'table_prefix' => 'seablast_dist_testing_',
        ]
    ],
    'version_order' => 'creation'
];
