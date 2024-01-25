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
        //$result = new stdClass();
        // typecasting array to stdClass works since PHP4
        $result = (object) ['httpCode' => 302, 'redirection' => (object) ['url' => './kontakt']];
        //$result->redirection = (object) ['url' => './kontakt', 'httpCode' => 302];
        return $result;
    }
}
