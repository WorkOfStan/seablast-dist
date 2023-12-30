<?php

declare(strict_types=1);

namespace Seablast\Distribution\Models;

use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use Tracy\Debugger;
use Tracy\ILogger;
use stdClass;
use Webmozart\Assert\Assert;

/**
 * Demo API
 */
class ApiMirrorModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    /** @var Superglobals */
    private $superglobals;
    /** @var string */
    private $toBeMirrored;

    /**
     *
     * @param SeablastConfiguration $configuration
     * @param Superglobals $superglobals
     */
    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->superglobals = $superglobals;
        unset($configuration); // just to do something
        Debugger::log('POST=' . print_r($this->superglobals->post, true), ILogger::DEBUG);
        $str = file_get_contents("php://input");
        Assert::string($str, 'php://input');
        Debugger::log('php://input=' . $str, ILogger::DEBUG);
        $data = json_decode($str);
        //Debugger::barDump($data, 'Decoded JSON');
        Assert::object($data); // TODO recoverable error
        Debugger::log(
            'Server REQUEST METHOD (should be POST): ' . $this->superglobals->server['REQUEST_METHOD'],
            ILogger::DEBUG
        );
        if (!isset($data->mirror)) {
            throw new \Exception('data->mirror expected');
        }
        $this->toBeMirrored = $data->mirror;
    }

    public function knowledge(): stdClass
    {
        $result = new stdClass();
        // array variant
        //$rest->rest = ['mirrorField' => 'Mirrored ' . $this->toBeMirrored];
        // object variant
        $result->rest = new stdClass();
        $result->rest->mirrorField = 'Mirrored ' . $this->toBeMirrored;
        return $result;
    }
}
