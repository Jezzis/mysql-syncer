<?php

use Mockery as m;

abstract class BaseTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\Mock
     */
    protected $command;

    public static function setupBeforeClass()
    {
    }

    protected function setUp()
    {
        $this->command = m::mock(new \Jezzis\MysqlSyncer\MysqlSyncerCommand())
            ->shouldAllowMockingProtectedMethods()
            ->shouldDeferMissing();

        // source sql
        $sourceSql = <<<EOD
-- --------------------------------------------------------

--
-- Table structure for table `post`
--

DROP TABLE IF EXISTS `post`;
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->command->shouldReceive('prepareSql')->andReturn($sourceSql);

        // definer
        $this->command->shouldReceive('getDefiner')->andReturn('DEFINER=`test`@`localhost`');
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
