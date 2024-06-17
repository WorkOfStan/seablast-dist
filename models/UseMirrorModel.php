<?php

declare(strict_types=1);

namespace Seablast\Distribution\Models;

use GuzzleHttp\Client;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastConstant;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Demo API
 */
class UseMirrorModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var Client */
    private $client;
    /** @var SeablastConfiguration */
    private $configuration;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        unset($superglobals); // just to do something
        $this->configuration = $configuration;
        $this->client = new Client();
    }

    /**
     * @return stdClass
     * @throws \Exception
     */
    public function knowledge(): stdClass
    {
        $arr = ['mirror' => 'xyz'];
        // It is a server-side API call, so there's no way to display Tracy for the API
        $apiUrl = $this->configuration->getString(SeablastConstant::SB_APP_ROOT_ABSOLUTE_URL) . '/api/mirror';

        try {
            $response = $this->client->request('POST', $apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $arr,
            ]);

            $body = $response->getBody();
            Debugger::barDump($body, 'API body response');
            $content = $body->getContents();
            Debugger::barDump($content, 'API content response - JSON');
            $responseArr = json_decode($content, true); // JSON to associative array
            Debugger::barDump($responseArr, 'API content response - arr');
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            Debugger::log('Request failed: ' . $e->getMessage(), ILogger::EXCEPTION);
            throw new \Exception('Request failed: ' . $e->getMessage());
        }

        $result = new stdClass();
        // Latte expects $mirrored
        $result->mirrored = json_encode($responseArr);
        return $result;
    }
}
