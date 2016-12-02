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
        // client
        $this->client = m::mock(new \Jezzis\MysqlSyncer\Client\MysqlClient())
            ->shouldDeferMissing();

        // definer
        $this->client->shouldReceive('getDbDefiner')->andReturn('DEFINER=`test`@`localhost`');

        // source sql
        $this->loadSql();
    }

    abstract function loadSql();

    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public static function tearDownAfterClass()
    {
    }
}
