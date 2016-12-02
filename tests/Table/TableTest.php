<?php

class TableTest extends BaseTest
{
    function loadSql()
    {}

    protected function getSingleTableSql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    function testHandle_newTable_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleTableSql();

        $this->client->shouldReceive('dbHasTable')->andReturnFalse();

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = $this->getSingleTableSql();
        $this->assertEquals([$sql], $actualSql);
    }
}