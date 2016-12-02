<?php

class Procedure extends BaseTest
{
    function loadSql()
    {}

    protected function getSingleProcedureSql()
    {
        return <<<EOD
DROP PROCEDURE IF EXISTS `SPLIT_STR`;
DELIMITER ;;
CREATE DEFINER=`test`@`localhost` PROCEDURE `SPLIT_STR`(
  x VARCHAR(500),
  delim VARCHAR(12),
  pos INT
) RETURNS varchar(500) CHARSET utf8
BEGIN
	RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
       delim, '');
END ;;
DELIMITER ;
EOD;
    }

    protected function getDiffSingleProcedureSql()
    {
        return <<<EOD
DROP PROCEDURE IF EXISTS `SPLIT_STR`;
DELIMITER ;;
CREATE DEFINER=`test`@`localhost` PROCEDURE `SPLIT_STR`(
  x VARCHAR(500),
  delim VARCHAR(15),
  pos INT
) RETURNS varchar(520) CHARSET utf8
BEGIN
	RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
       delim, '');
END ;;
DELIMITER ;
EOD;
    }

    function testHandle_newProcedure_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleProcedureSql();

        $this->client->shouldReceive('dbHasProcedure')->andReturnFalse();

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = <<<EOD
CREATE DEFINER=`test`@`localhost` PROCEDURE `SPLIT_STR`(
  x VARCHAR(500),
  delim VARCHAR(12),
  pos INT
) RETURNS varchar(500) CHARSET utf8
BEGIN
	RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
       delim, '');
END
EOD;

        $this->assertEquals([$sql], $actualSql);
    }

    function testHandle_diffProcedure_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleProcedureSql();
        $procedureDef = <<<EOD
CREATE DEFINER=`test`@`localhost` PROCEDURE `SPLIT_STR`(
  x VARCHAR(500),
  delim VARCHAR(15),
  pos INT
) RETURNS varchar(520) CHARSET utf8
BEGIN
	RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
       delim, '');
END
EOD;

        $this->client->shouldReceive('dbHasProcedure')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->andReturn($procedureDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "DROP PROCEDURE `SPLIT_STR`;";
        $sqlList[] = "CREATE DEFINER=`test`@`localhost` PROCEDURE `SPLIT_STR`(
  x VARCHAR(500),
  delim VARCHAR(12),
  pos INT
) RETURNS varchar(500) CHARSET utf8
BEGIN
	RETURN REPLACE(SUBSTRING(SUBSTRING_INDEX(x, delim, pos),
       LENGTH(SUBSTRING_INDEX(x, delim, pos -1)) + 1),
       delim, '');
END";

        $this->assertEquals($sqlList, $actualSql);
    }
}