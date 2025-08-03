<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FirstBlogPosts extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $itemTypes = [
            ['id' => 1, 'name' => 'blogpost'],
        ];

        //$itemTypesTable =
        $this->table('localised_item_types')->insert($itemTypes)->saveData();

        // phpcs:disable Generic.Files.LineLength
        $blogPosts = [
            ['item_id' => 1, 'language' => 'cs', 'title' => 'Co je Seablast for PHP?',
                'content' => 'Tento minimalistický MVC framework přidaný kompozitorem vám pomůže vytvořit komplexní, ale snadno udržovatelnou webovou aplikaci POUZE pomocí konfigurace:
a) nakonfigurujete trasy pro řadič,
b) přidáte modely pro funkčnost aplikace,
c) volitelně upravíte šablony zobrazení.
Framework se postará o logy, databázi, více jazyků, uživatelsky přívětivé chyby HTTP a přátelské URL.',
                'item_type_id' => 1,],
            ['item_id' => 1, 'language' => 'en', 'title' => 'What is Seablast for PHP?',
                'content' => '
            This minimalist MVC framework added by composer helps you to create a complex, yet easy to maintain, web application by configuration ONLY:
a) you configure routes for controller,
b) add models for the app business functionality,
c) optionally modify view templates.
The framework takes care of logs, database, multiple languages, user friendly HTTP errors, friendly URL.
            ',
                'item_type_id' => 1,],
            ['item_id' => 2, 'language' => 'cs', 'title' => 'Lorem 2',
                'content' => 'Ipsum 2',
                'item_type_id' => 1,],
            ['item_id' => 3, 'language' => 'cs', 'title' => 'Lorem 3',
                'content' => 'Ipsum 3',
                'item_type_id' => 1,],
            ['item_id' => 4, 'language' => 'cs', 'title' => 'Lorem 4', 'content' => 'Ipsum 4', 'item_type_id' => 1, 'active' => 0],
            ['item_id' => 5, 'language' => 'cs', 'title' => 'Lorem 5', 'content' => 'Ipsum 5', 'item_type_id' => 1, 'active' => 0],
            ['item_id' => 6, 'language' => 'cs', 'title' => 'Lorem 6', 'content' => 'Ipsum 6', 'item_type_id' => 1, 'active' => 0],
            ['item_id' => 7, 'language' => 'cs', 'title' => 'Lorem 7', 'content' => 'Ipsum 7', 'item_type_id' => 1, 'active' => 0],
            ['item_id' => 8, 'language' => 'cs', 'title' => 'Lorem 8', 'content' => 'Ipsum 8', 'item_type_id' => 1, 'active' => 0],
            ['item_id' => 9, 'language' => 'cs', 'title' => 'Lorem 9', 'content' => 'Ipsum 9', 'item_type_id' => 1, 'active' => 0],
            ['item_id' => 10, 'language' => 'cs', 'title' => 'Lorem 10', 'content' => 'Ipsum 10', 'item_type_id' => 1, 'active' => 0],
        ];
        // phpcs:enable
        $this->table('localised_items')->insert($blogPosts)->saveData();
    }
}
