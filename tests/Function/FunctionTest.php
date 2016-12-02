<?php

class FunctionTest extends BaseTest
{
    function loadSql()
    {}

    protected function getSingleFunctionSql()
    {
        return <<<EOD
DROP FUNCTION IF EXISTS `SPLIT_STR`;
DELIMITER ;;
CREATE DEFINER=`test`@`localhost` FUNCTION `SPLIT_STR`(
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

    protected function getDiffSingleFunctionSql()
    {
        return <<<EOD
DROP FUNCTION IF EXISTS `SPLIT_STR`;
DELIMITER ;;
CREATE DEFINER=`test`@`localhost` FUNCTION `SPLIT_STR`(
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

    function testHandle_newFunction_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleFunctionSql();

        $this->client->shouldReceive('dbHasFunction')->andReturnFalse();

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = <<<EOD
CREATE DEFINER=`test`@`localhost` FUNCTION `SPLIT_STR`(
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

    function testHandle_diffFunction_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleFunctionSql();
        $procedureDef = <<<EOD
CREATE DEFINER=`test`@`localhost` FUNCTION `SPLIT_STR`(
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

        $this->client->shouldReceive('dbHasFunction')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->andReturn($procedureDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "DROP FUNCTION `SPLIT_STR`;";
        $sqlList[] = "CREATE DEFINER=`test`@`localhost` FUNCTION `SPLIT_STR`(
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