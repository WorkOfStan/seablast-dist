<?php

declare(strict_types=1);

namespace Seablast\Distribution\Models;

use Seablast\BriefApiClient\BriefApiClient;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;

/**
 * Demo API
 */
class UseMirrorModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var BriefApiClient */
    private $api;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        unset($superglobals); // just to do something
        $this->api = new BriefApiClient(
            $configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL) . '/api/mirror'
        );
    }

    public function knowledge(): stdClass
    {
        $arr = ['mirror' => 'xyz'];
        // It is a server-side API call, so there's no way to display Tracy for the API
        $response = $this->api->getArrayArray($arr);
        $result = new stdClass();
        // Latte expects $mirrored
        $result->mirrored = json_encode($response);
        return $result;
    }
}
