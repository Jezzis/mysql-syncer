<?php

/**
 * Created by PhpStorm.
 * User: szj
 * Date: 16/11/30
 * Time: 20:57
 */

use Mockery as m;

class ColumnTest extends BaseTest
{
    /**
     * 测试新增字段
     */
    function testHandle_newColumn_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = "ALTER TABLE `post` ADD `content` varchar(20) NOT NULL COMMENT '内容' AFTER `id`";
        $this->assertEquals([$sql], $actualSql);
    }

    /**
     * 测试删除字段
     */
    function testHandle_missColumn_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL COMMENT '内容',
  `to_be_delete` varchar(20) NOT NULL COMMENT '待删除',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = "ALTER TABLE `post` DROP `to_be_delete`";
        $this->assertEquals([$sql], $actualSql);
    }

    /**
     * 测试更新字段 - 名称
     */
    function testHandle_diffColumnOfName_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content_new` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` ADD `content` varchar(20) NOT NULL COMMENT '内容' AFTER `id`";
        $sqlList[] = "ALTER TABLE `post` DROP `content_new`";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 类型
     */
    function testHandle_diffColumnOfType_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` char(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 长度
     */
    function testHandle_diffColumnOfLength_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(30) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 默认值
     */
    function testHandle_diffColumnOfDefaultValue_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `created_at` `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp COMMENT '创建时间'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 允许Null值
     */
    function testHandle_diffColumnOfAllowNull_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 有无符号(相关整型)
     */
    function testHandle_diffColumnOfUnsigned_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 允许0填充
     */
    function testHandle_diffColumnOfZerofill_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL ZEROFILL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 编码(相关字符型)
     */
    function testHandle_diffColumnOfCharacter_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL CHARACTER SET LATIN1 COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 字符集(相关字符型)
     */
    function testHandle_diffColumnOfCollate_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL COLLATE utf8_unicode_ci COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试更新字段 - 注释
     */
    function testHandle_diffColumnOfComment_shouldGenerateAlterScript()
    {
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(20) NOT NULL COMMENT '旧注释',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `content` `content` VARCHAR(20) CHARACTER SET UTF8 COLLATE UTF8_GENERAL_CI NOT NULL COMMENT '内容'";
        $this->assertEquals($sqlList, $actualSql);
    }
}