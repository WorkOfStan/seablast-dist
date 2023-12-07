<?php

namespace Seablast\Distribution\Models;

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
     * TODO: change to object as parameters for Latte render
     * @return array<mixed>
     */
    public function getParameters(): array
    {
        return ['num' => rand(), 'title' => 'Home sweet home'];
    }
}
