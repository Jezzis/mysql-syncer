<?php

class KeyTest extends BaseTest
{
    function loadSql()
    {}

    protected function getNoPrimarySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间'
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    protected function getSinglePrimarySql()
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

    protected function getMultiplePrimarySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`, `user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    /**
     * 测试主键 - 新增
     */
    function testHandle_newPrimaryKey_shouldGenerateAlterScript()
    {
        $dbTabDef = $this->getNoPrimarySql();
        $this->sourceSql = $this->getSinglePrimarySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sql = "ALTER TABLE `post` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  ADD PRIMARY KEY (id)";
        $this->assertEquals([$sql], $actualSql);
    }

    /**
     * 测试主键 - 删除
     */
    function testHandle_missPrimaryKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getNoPrimarySql();
        $dbTabDef = $this->getSinglePrimarySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL";
        $sqlList[] = "ALTER TABLE `post` DROP PRIMARY KEY";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试主键 - 更换字段
     */
    function testHandle_changePrimaryKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSinglePrimarySql();
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;


        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (id)";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试主键 - 更换单双主键
     */
    function testHandle_addPrimaryKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSinglePrimarySql();
        $dbTabDef = $this->getMultiplePrimarySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (id)";
        $this->assertEquals($sqlList, $actualSql);
    }

    protected function getNoKeySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    protected function getSingleKeySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
  KEY `doctor_user_id_index` (`user_id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    protected function getMultiKeySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `doctor_user_id_index` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    /**
     * 测试索引 - 新增
     */
    function testHandle_newKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleKeySql();
        $dbTabDef = $this->getNoKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` ADD INDEX `doctor_user_id_index` (user_id)";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试索引 - 删除
     */
    function testHandle_missKey_shouldGenerateAlterScript()
    {
        $dbTabDef = $this->getSingleKeySql();
        $this->sourceSql = $this->getNoKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `doctor_user_id_index`";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试索引 - 更新 - 更换字段
     */
    function testHandle_changeKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleKeySql();
        $dbTabDef = <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
  KEY `doctor_user_id_index` (`created_at`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `doctor_user_id_index`,
  ADD INDEX `doctor_user_id_index` (user_id)";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试索引 - 更新 - 增加字段(组合索引)
     */
    function testHandle_changeKeyAddColumn_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getMultiKeySql();
        $dbTabDef = $this->getSingleKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `doctor_user_id_index`,
  ADD INDEX `doctor_user_id_index` (user_id,created_at)";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试索引 - 更新 - 删除字段(组合索引)
     */
    function testHandle_changeKeyDelColumn_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleKeySql();
        $dbTabDef = $this->getMultiKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `doctor_user_id_index`,
  ADD INDEX `doctor_user_id_index` (user_id)";
        $this->assertEquals($sqlList, $actualSql);
    }

    protected function getNoUniqueKeySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    protected function getSingleUniqueKeySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    protected function getMultiUniqueKeySql()
    {
        return <<<EOD
CREATE TABLE IF NOT EXISTS `post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户id',
  `content` varchar(20) NOT NULL COMMENT '内容',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOD;
    }

    /**
     * 测试唯一索引 - 新增
     */
    function testHandle_newUniqueKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleUniqueKeySql();
        $dbTabDef = $this->getNoUniqueKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` ADD UNIQUE INDEX `user_id` (user_id)";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试唯一索引 - 删除
     */
    function testHandle_missUniqueKey_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getNoUniqueKeySql();
        $dbTabDef = $this->getSingleUniqueKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `user_id`";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试唯一索引 - 更新 - 增加字段
     */
    function testHandle_changeUniqueKeyAddColumn_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getMultiUniqueKeySql();
        $dbTabDef = $this->getSingleUniqueKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `user_id`,
  ADD UNIQUE INDEX `user_id` (user_id,created_at)";
        $this->assertEquals($sqlList, $actualSql);
    }

    /**
     * 测试唯一索引 - 更新 - 删除字段
     */
    function testHandle_changeUniqueKeyDelColumn_shouldGenerateAlterScript()
    {
        $this->sourceSql = $this->getSingleUniqueKeySql();
        $dbTabDef = $this->getMultiUniqueKeySql();

        $this->client->shouldReceive('dbHasTable')->andReturnTrue();
        $this->client->shouldReceive('getDefFromDB')->withArgs(['table', 'post'])->andReturn($dbTabDef);

        $parser = new \Jezzis\MysqlSyncer\Parser\Parser($this->client, true);
        $parser->parse($this->sourceSql);
        $actualSql = $parser->getExecSqlList();

        $sqlList = [];
        $sqlList[] = "ALTER TABLE `post` DROP INDEX `user_id`,
  ADD UNIQUE INDEX `user_id` (user_id)";
        $this->assertEquals($sqlList, $actualSql);
    }
}