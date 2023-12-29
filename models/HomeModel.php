<?php

namespace Seablast\Distribution\Models;

use stdClass;
//use Tracy\Debugger;
//use Webmozart\Assert\Assert;

class HomeModel
{
    use \Nette\SmartObject;

    public function __construct()
    {
        //
    }

    /**
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        return ['num' => rand(), 'title' => 'Home sweet home'];
    }
}
