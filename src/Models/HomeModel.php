<?php

declare(strict_types=1);

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
        // typecasting array to stdClass works since PHP4
        return (object) ['num' => rand(), 'title' => 'Home sweet home'];
    }
}
