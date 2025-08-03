<?php

declare(strict_types=1);

namespace Seablast\Distribution\Models;

use Seablast\I18n\Models\FetchLocalisedItemsModel;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\Superglobals;

/**
 * Retrieve items from database
 */
class BlogModel extends FetchLocalisedItemsModel
{
    use \Nette\SmartObject;

    /**
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->itemTypeId = 1; // Blog type ID // TODO create actually blog item_type
        $this->titlePrefix = "Seablast Distribution - ";
        $this->titleSuffix = "Blog";
        $configuration->mysqli(); // dbms prefix set up even if Seablast\Auth is not present and thus it's not
        //already set up in SeablastController: `$this->identity = new $identityManager($this->configuration->mysqli());
        // TODO check if there will be user set as in other pages?
        parent::__construct($configuration, $superglobals);
    }
}
