<?php

declare(strict_types=1);

namespace Seablast\Distribution\Models;

use stdClass;

class RedirModel // implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /**
     * @return stdClass
     */
    public function knowledge(): stdClass
    {
        // typecasting array to stdClass works since PHP4
        $result = (object) ['httpCode' => 302, 'redirectionUrl' => //(object) [
            //'url' => // SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL
            './kontakt'
            //]
            ];
        return $result;
    }
}
