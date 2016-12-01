<?php

use Mockery as m;

abstract class BaseTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\Mock
     */
    protected $client;

    protected $sourceSql;

    public static function setupBeforeClass()
    {
        chdir(__DIR__);
    }

    protected function setUp()
    {
        // source sql
        $this->sourceSql = file_get_contents('source.sql');

        $this->client = m::mock(new \Jezzis\MysqlSyncer\Client\MysqlClient())
            ->shouldDeferMissing();

        // definer
        $this->client->shouldReceive('getDefiner')->andReturn('DEFINER=`test`@`localhost`');
    }

    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public static function tearDownAfterClass()
    {
    }
}
