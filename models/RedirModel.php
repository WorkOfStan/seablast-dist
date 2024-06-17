<?php

namespace Seablast\Distribution\Models;

use stdClass;

//use Tracy\Debugger;
//use Webmozart\Assert\Assert;

class RedirModel
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
        $result = (object) ['httpCode' => 302, 'redirection' => (object) ['url' => './kontakt']];
        return $result;
    }
}
