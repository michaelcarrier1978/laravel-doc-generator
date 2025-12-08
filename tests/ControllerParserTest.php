<?php

use PHPUnit\Framework\TestCase;
use Michaelcarrier\LaravelDocGenerator\Parsers\ControllerParser;

class ControllerParserTest extends TestCase
{
    public function testParseController()
    {
        $parser = new ControllerParser();
        $result = $parser->parse(__DIR__ . '/fixtures/UserController.php');
        
        $this->assertEquals('UserController', $result['className']);
        $this->assertCount(2, $result['methods']);
        $this->assertEquals('store', $result['methods'][0]['name']);
    }
}