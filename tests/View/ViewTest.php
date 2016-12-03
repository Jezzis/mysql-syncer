<?php

class ViewTest extends BaseTest
{
    function loadSql()
    {}

    protected function getSingleViewSql()
    {
        return <<<EOD
CREATE ALGORITHM=UNDEFINED DEFINER=`test`@`localhost` SQL SECURITY DEFINER VIEW `view_post` AS select `post`.*, `user`.name as user_name from `post` inner join `user` on `post`.user_id = `user`.id ;
EOD;
    }

    function testHandle_newTable_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleViewSql();

        $this->client->shouldReceive('dbHasView')->andReturnFalse();

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = "CREATE VIEW `view_post` AS select `post`.*, `user`.name as user_name from `post` inner join `user` on `post`.user_id = `user`.id;";
        $this->assertEquals([$sql], $actualSql);
    }

    function testHandle_alterTable_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleViewSql();

        $this->client->shouldReceive('dbHasView')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->andReturn('CREATE ALGORITHM=UNDEFINED DEFINER=`test`@`localhost` SQL SECURITY DEFINER VIEW `view_post` AS select `post`.*, `user`.name as user_name, `user`.pic as user_pic from `post` inner join `user` on `post`.user_id = `user`.id');

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
//        dd($parser->getMsgs());
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "DROP VIEW `view_post`;";
        $sqlList[] = "CREATE VIEW `view_post` AS select `post`.*, `user`.name as user_name from `post` inner join `user` on `post`.user_id = `user`.id;";
        $this->assertEquals($sqlList, $actualSql);
    }
}