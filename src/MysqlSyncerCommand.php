<?php

namespace Jezzis\MysqlSyncer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\OutputInterface;

class MysqlSyncerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:sync
                             {file : The filename of the sql file, without .sql extension}
                             {--drop : allow drop redundant columns}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize database structure';

    protected $file, $permitDrop = false;

    protected $sqlPath = './';

    protected $definer, $host, $user, $charset = 'utf8', $collate = 'utf8_general_ci';

    protected $delimiter, $msgList, $execSqlList;

    protected function initEnv()
    {
        $this->host = DB::connection()->getConfig('host');
        $this->user = DB::connection()->getConfig('username');
        $this->definer = "DEFINER=`{$this->user}`@`{$this->host}`";

        $this->sqlPath = Config::get('msyncer.sql_path', './');

        $this->file = $this->argument('file');
        $this->permitDrop = $this->option('drop');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    function handle()
    {
        $this->initEnv();

        $this->resolveFile();

        $this->start();
    }

    protected function resolveFile()
    {
        $file = rtrim($this->sqlPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->file . '.sql';
        while (!file_exists($file)) {
            $file = $this->ask('cannot find file [' . $file . '], please retype');
            $file = rtrim($this->sqlPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file . '.sql';
        }
        $this->file = $file;
    }

    protected function start()
    {
        $sql = $this->prepareSql();

        $this->syncTable($sql);

        $this->syncView($sql);

        $this->syncFunction($sql);

        $this->syncProcedure($sql);

        $this->execSql();
    }

    protected function prepareSql()
    {
        // 处理字符串，去掉一些注释的代码
        $sql = file_get_contents($this->file);
        // 去除如/***/的注释
        $sql = preg_replace("[(/\*)+.+(\*/;\s*)]", '', $sql);
        // 去除如--类的注释
        $sql = preg_replace("(--.*?\n)", '', $sql);

        return $sql;
    }

    protected function addExecSql($execSql)
    {
        $this->execSqlList[] = $execSql;
    }

    public function getExecSqlList()
    {
        return $this->execSqlList;
    }

    protected function execSql()
    {
        if (empty($this->execSqlList)) {
            $this->info("\nnothing to do.");
            return;
        }

        $this->info("\n- execute sqls: \n" . implode("\n\n", $this->execSqlList));

        $warning = 'continue: y|n ? ';
        if ($this->permitDrop) {
            $warning .= '(with drop option enabled, please be careful!)';
        }
        $confirm = $this->ask($warning);
        if (strtolower($confirm) == 'y') {
            foreach ($this->execSqlList as $sql) {
                DB::connection()->getPdo()->exec($sql);
            }
        } else {
            $this->info('do nothing.');
        }
    }

    protected function syncTable($sql)
    {
        $this->comment("\n- sync table", OutputInterface::VERBOSITY_VERY_VERBOSE);
        preg_match_all("/CREATE\s+TABLE\s+(IF NOT EXISTS.+?)?`?(.+?)`?\s*\((.+?)\)\s*(ENGINE|TYPE)\s*\=(.+?;)/is", $sql, $matches);
        $newTabs = empty($matches[2]) ? array() : $matches[2];
        $newSqls = empty($matches[0]) ? array() : $matches[0];

        $totalNum = count($newTabs);
        for ($num = 0; $num < $totalNum; $num++) {
            $newTab = $newTabs[$num];
            $this->comment("checking table: `$newTab`...", OutputInterface::VERBOSITY_VERY_VERBOSE);
            $newCols = $this->getColDefListByTableDef($newSqls[$num]);
            $oldTab = $newTab;

            if (!$this->dbHasTable($newTab)) {
                $this->execSqlList[] = str_replace($oldTab, $newTab, $newSqls[$num]);
            } else {
                $oldCols = $this->getColDefListByTableDef($this->getDefFromDB('table', $newTab));
                $updateSqlList = $deleteSqlList = [];
                $allNewColNames = array_keys($newCols);
                foreach ($newCols as $key => $newCol) {
                    $oldCol = empty($oldCols[$key]) ? null : $oldCols[$key];
                    if (!in_array($key, ['PRIMARY', 'KEY', 'UNIQUE'])) { // 优先处理字段变更
                        if (!empty($oldCol)) { // 字段更新
                            if (!$this->compareColumnDefinition($newTab, $key, $oldCol, $newCol)) {
                                $updateSqlList[] = "CHANGE `$key` `$key` $newCol";
                            }
                        } else { // 字段添加
                            $i = array_search($key, $allNewColNames);
                            $fieldPosition = $i > 0 ? 'AFTER `' . $allNewColNames[$i - 1] . '`' : 'FIRST';
                            $updateSqlList[] = "ADD `$key` $newCol $fieldPosition";
                        }
                    } elseif ($key == 'PRIMARY') {
                        if (empty($oldCol)) { // 原主键不存在,新增主键
                            $updateSqlList[] = "ADD PRIMARY KEY {$newCol}";
                        } elseif ($newCol != $oldCol) { // 主键替换
                            $oldColName = str_replace(['(', ')'], '', $oldCol);
                            if (empty($newCols[$oldColName])) { // 原主键字段已不存在,须删除
                                $updateSqlList[] = "DROP `{$oldColName}`";
                            }
                            $updateSqlList[] = "DROP PRIMARY KEY";
                            $updateSqlList[] = "ADD PRIMARY KEY {$newCol}";
                        }
                    } elseif ($key == 'KEY') {
                        foreach ($newCol as $subKey => $subValue) {
                            if (!empty($oldCols['KEY'][$subKey])) {
                                if ($subValue != $oldCols['KEY'][$subKey]) {
                                    $updateSqlList[] = "ADD INDEX `$subKey` $subValue";
                                }
                            } else {
                                $updateSqlList[] = "ADD INDEX `$subKey` $subValue";
                            }
                        }
                    } elseif ($key == 'UNIQUE') {
                        foreach ($newCol as $subKey => $subValue) {
                            if (!empty($oldCols['UNIQUE'][$subKey])) {
                                if ($subValue != $oldCols['UNIQUE'][$subKey]) {
                                    $updateSqlList[] = "DROP INDEX `$subKey`";
                                    $updateSqlList[] = "ADD UNIQUE INDEX `$subKey` $subValue";
                                }
                            } else {
                                $updateSqlList[] = "ADD UNIQUE INDEX `$subKey` $subValue";
                            }
                        }
                    }
                }
                if (!empty($updateSqlList)) {
                    $this->addExecSql("ALTER TABLE `{$newTab}` " . implode(",\n  ", $updateSqlList));
                } else {
// 				    checkColumnDiff($execSqlInfo, $newCols, $oldCols); // 字段顺序校验
                }

                // del
                foreach ($oldCols as $colName => $definition) {
                    if (in_array($colName, array('UNIQUE', 'KEY'))) { // drop index
                        if (empty($newCols[$colName])) {
                            foreach ($definition as $indexName => $indexColName) {
                                $deleteSqlList[] = "DROP INDEX `{$indexName}`";
                            }
                        }

                        if (!empty($newCols[$colName])) {
                            $diffArr = array_diff(array_keys($definition), array_keys($newCols[$colName]));
                            foreach ($diffArr as $indexName => $indexColName) {
                                $deleteSqlList[] = "DROP INDEX `{$indexName}`";
                            }
                        }
                    } elseif ($colName == 'PRIMARY') {
                        if (empty($newCols[$colName])) {
                            $deleteSqlList[] = "DROP PRIMARY KEY";
                        }
                    } else { // drop column
                        if (empty($newCols[$colName])) {
                            $sql = "DROP `{$colName}`";
                            !in_array($sql, $updateSqlList) && $deleteSqlList[] = $sql;
                        }
                    }
                }

                if ($deleteSqlList && $this->permitDrop) {
                    $this->addExecSql("ALTER TABLE `{$newTab}` " . implode(",\n  ", $deleteSqlList));
                }
            }

            $this->comment("done\n", OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
    }

    /**
     * 比较两个列定义是否相同
     * @param $table
     * @param $column
     * @param $def1
     * @param $def2
     * @return bool
     */
    protected function compareColumnDefinition($table, $column, &$def1, &$def2)
    {
        $def1 = $this->standardColumnDefinion($def1);
        $def2 = $this->standardColumnDefinion($def2);
        $result = $def1 == $def2;
        if (!$result) {
            $this->comment("\na different column definition was detected in `$table`.$column:", OutputInterface::VERBOSITY_VERBOSE);
            $this->comment("old: $def1", OutputInterface::VERBOSITY_VERBOSE);
            $this->comment("new: $def2", OutputInterface::VERBOSITY_VERBOSE);
        }
        return $result;
    }

    /**
     * 补全列定义
     * @param $def
     * @return string
     */
    protected function standardColumnDefinion($def)
    {
        static $cache = [];

        $uniqKey = md5($def);
        if (empty($cache[$uniqKey])) {
            $def = preg_replace('/DEFAULT\s+([0-9]+)/i', "DEFAULT '\$1'", $def); // 整型默认值加引号
            $defInfo = $this->parseColumnDefinition($def);

            $metaType = strtoupper(preg_replace('/\([^\)]+\)/', '', $defInfo['type']));
            if (in_array($metaType, ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'LONGTEXT', 'ENUM', 'SET'])) {
                empty($defInfo['character']) && $defInfo['character'] = 'CHARACTER SET ' . $this->charset;
                empty($defInfo['collate']) && $defInfo['collate'] = 'COLLATE ' . $this->collate;
            }

            $stdColDefStr = strtoupper($defInfo['type']) . ' ';
            !empty($defInfo['unsigned']) && $stdColDefStr .= strtoupper($defInfo['unsigned']) . ' ';
            !empty($defInfo['zerofill']) && $stdColDefStr .= strtoupper($defInfo['zerofill']) . ' ';
            !empty($defInfo['character']) && $stdColDefStr .= strtoupper($defInfo['character']) . ' ';
            !empty($defInfo['collate']) && $stdColDefStr .= strtoupper($defInfo['collate']) . ' ';
            !empty($defInfo['nullable']) && $stdColDefStr .= strtoupper($defInfo['nullable']) . ' ';
            !empty($defInfo['default']) && $stdColDefStr .= $defInfo['default'] . ' ';
            !empty($defInfo['comment']) && $stdColDefStr .= $defInfo['comment'] . ' ';

            $cache[$uniqKey] = $stdColDefStr;
            return $stdColDefStr;
        }
        return $cache[$uniqKey];
    }

    /**
     * 解析列定义
     * @param $def
     * @return mixed
     */
    protected function parseColumnDefinition($def)
    {
        static $cache = [];

        $uniqKey = md5($def);
        if (empty($cache[$uniqKey])) {
            $pattern = "/(\w+(?:\([^\)]+\))?)\s*";
            $pattern .= "(?:(BINARY)\s+)?";
            $pattern .= "(?:(UNSIGNED)\s+)?";
            $pattern .= "(?:(ZEROFILL)\s+)?";
            $pattern .= "(?:(CHARACTER\s+SET\s+\w+)\s+)?";
            $pattern .= "(?:(COLLATE\s+\w+)\s+)?";
            $pattern .= "(?:((?:NOT\s+)?NULL)\s*)?";
            $pattern .= "(?:(DEFAULT\s+(?:'[^\']+'|\w+))\s*)?";
            $pattern .= "(?:(AUTO_INCREMENT\s+)\s*)?";
            $pattern .= "(?:(COMMENT\s+'[^\']+'))?/is";
            preg_match($pattern, $def, $matches);

            $cache[$uniqKey] = [
                'type' => $matches[1],
                'binary' => !empty($matches[2]) ? $matches[2] : false,
                'unsigned' => !empty($matches[3]) ? $matches[3] : false,
                'zerofill' => !empty($matches[4]) ? $matches[4] : false,
                'character' => !empty($matches[5]) ? $matches[5] : false,
                'collate' => !empty($matches[6]) ? $matches[6] : false,
                'nullable' => !empty($matches[7]) ? $matches[7] : false,
                'default' => !empty($matches[8]) ? $matches[8] : false,
                'autoinc' => !empty($matches[9]) ? $matches[9] : false,
                'comment' => !empty($matches[10]) ? $matches[10] : false,
            ];
        }
        return $cache[$uniqKey];
    }

    /**
     * 处理视图
     * @param $sql
     */
    protected function syncView($sql)
    {
        $this->comment("\n- sync view", OutputInterface::VERBOSITY_VERY_VERBOSE);

        $pattern = '/CREATE\s+(ALGORITHM=UNDEFINED)?\s+(DEFINER=`.+?`@`.+?`)?\s+(SQL SECURITY DEFINER)?\s+VIEW\s+(IF NOT EXISTS)?\s*`?(.+?)`\s+AS\s+(SELECT\s+.+?)\s*;/is';
        preg_match_all($pattern, $sql, $matches);

        $newViews = empty($matches[6]) ? [] : $matches[6];
        $newViewNames = empty($matches[5]) ? [] : $matches[5];
        foreach ($newViewNames as $idx => $newViewName) {
            $this->comment("view $newViewName", OutputInterface::VERBOSITY_VERY_VERBOSE);
            if ($this->dbHasView($newViewName)) {
                $def = $this->getDefFromDB('view', $newViewName);
                preg_match_all($pattern, $def . ';', $matches);

                $oldDef = $matches[6][0];
                if ($oldDef != $newViews[$idx]) {
                    $this->comment("\na different view definition was detected in `$newViewName`:", OutputInterface::VERBOSITY_VERBOSE);
                    $this->comment("old def: $oldDef", OutputInterface::VERBOSITY_VERY_VERBOSE);
                    $this->comment("new def: $newViews[$idx]", OutputInterface::VERBOSITY_VERY_VERBOSE);
                    $this->addExecSql("DROP VIEW `{$newViewName}`;");
                    $this->addExecSql($newViews[$idx]);
                }
            } else {
                $this->addExecSql("CREATE VIEW `{$newViewName}` AS {$newViews[$idx]};");
            }
        }
    }

    /**
     * 处理分隔符
     * @param $sql
     */
    protected function prepareDelimiter($sql)
    {
        $pattern = '/DELIMITER\s+(.+?)\r?\n\r?/is';
        preg_match($pattern, $sql, $matches);
        $this->delimiter = $matches[1];
        if (empty($this->delimiter)) {
            $this->delimiter = ';;';
        }
    }

    /**
     * 处理函数
     * @param $sql
     */
    protected function syncFunction($sql)
    {
        $this->comment("\n- sync function", OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->prepareDelimiter($sql);

        $token = '';
        for ($i = 0; $i < strlen($this->delimiter); $i ++) {
            $token .= "\\" . substr($this->delimiter, $i, 1);
        }

        $pattern = "/(CREATE\s+(DEFINER=`[^`]+?`@`[^`]+?`)?\s+FUNCTION\s+(IF NOT EXISTS)?\s*`?([^`]+?)`\s*.+?END)\s*{$token}/is";
        preg_match_all($pattern, $sql, $matches);

        $newFuncs = empty($matches[1]) ? [] : $matches[1];
        $newFuncNames = empty($matches[4]) ? [] : $matches[4];
        $definers = empty($matches[2]) ? [] : $matches[2];

        foreach ($newFuncNames as $idx => $newFuncName) {
            $this->comment("function: $newFuncName", OutputInterface::VERBOSITY_VERY_VERBOSE);
            $newDef = str_replace($definers[$idx], $this->definer, $newFuncs[$idx]);
            if ($this->dbHasFunction($newFuncName)) {
                $oldDef = $this->getDefFromDB('function', $newFuncName);
                if ($this->stripSpaces($oldDef) != $this->stripSpaces($newDef)) {
                    $this->comment("\na different function definition was detected in `$newFuncName`:", OutputInterface::VERBOSITY_VERBOSE);
                    $this->comment("old def: $oldDef", OutputInterface::VERBOSITY_VERBOSE);
                    $this->comment("new def: $newDef", OutputInterface::VERBOSITY_VERBOSE);

                    $this->addExecSql("DROP FUNCTION `{$newFuncName}`;");
                    $this->addExecSql($newDef);
                }
            } else {
                $this->addExecSql($newDef);
            }
        }
    }

    /**
     * 处理存储过程
     * @param $sql
     */
    protected function syncProcedure($sql)
    {
        $this->comment("\n- sync procedure", OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->prepareDelimiter($sql);

        $token = '';
        for ($i = 0; $i < strlen($this->delimiter); $i ++) {
            $token .= "\\" . substr($this->delimiter, $i, 1);
        }

        $matches = null;
        $pattern = "/(CREATE\s+(DEFINER=`[^`]+?`@`[^`]+?`)?\s+PROCEDURE\s+(IF NOT EXISTS)?\s*`?([^`]+?)`\s*.+?END)\s*{$token}/is";
        preg_match_all($pattern, $sql, $matches);

        $newProcs = empty($matches[1]) ? [] : $matches[1];
        $newProcNames = empty($matches[4]) ? [] : $matches[4];
        $definers = empty($matches[2]) ? [] : $matches[2];

        foreach ($newProcNames as $idx => $newProcName) {
            $this->comment("procedure: $newProcName", OutputInterface::VERBOSITY_VERY_VERBOSE);
            $newDef = str_replace($definers[$idx], $this->definer, $newProcs[$idx]);
            if ($this->dbHasProcedure($newProcName)) {
                $oldDef = $this->getDefFromDB('procedure', $newProcName);
                if ($this->stripSpaces($oldDef) != $this->stripSpaces($newDef)) {
                    $this->comment("\na different procedure definition was detected in `$newProcName`:", OutputInterface::VERBOSITY_VERBOSE);
                    $this->comment("old def: " . $this->stripSpaces($oldDef), OutputInterface::VERBOSITY_VERBOSE);
                    $this->comment("new def: " . $this->stripSpaces($newDef), OutputInterface::VERBOSITY_VERBOSE);

                    $this->addExecSql("DROP PROCEDURE `{$newProcName}`;");
                    $this->addExecSql($newDef);
                }
            } else {
                $this->addExecSql($newDef);
            }
        }
    }


    /**
     * 检索两个数组的键值顺序是否一致，若不一致列出具体的信息
     * @param $execSqlInfo
     * @param $newCols
     * @param $oldCols
     * @return boolean
     */
    protected function checkColumnDiff($execSqlInfo, $newCols, $oldCols)
    {

        if (array_keys($newCols) == array_keys($oldCols)) {
            return false;
        }
        if (count($newCols) != count($oldCols)) {
            return false;
        }
        $size = count($newCols);

        for ($i = 0; $i < $size; $i++) {
            $newCol = key($newCols);
            $oldCol = key($oldCols);

            if (!empty($newCol) && !in_array($newCol, array('KEY', 'INDEX', 'UNIQUE', 'PRIMARY'))
                && $newCol != $oldCol
            ) {
                $execSqlInfo->setMsg("字段顺序不正确: 第" . ($i + 1) . "个字段 sql中字段为 {$newCol} 数据库中字段为 {$oldCol}");
            }
            next($newCols);
            next($oldCols);
        }

    }


    protected function remakeSql($value)
    {
        $value = trim(preg_replace("/\s+/u", ' ', $value));
        $value = str_replace(array('`', ', ', ' ,', '( ', ' )', 'mediumtext'), array('', ',', ',', '(', ')', 'text'), $value);
        return $value;
    }

    /**
     * 由表定义获取列定义数组
     * @param $createSql
     * @return array
     */
    protected function getColDefListByTableDef($createSql)
    {

        preg_match("/\((.+)\)\s*(ENGINE|TYPE)\s*\=/is", $createSql, $matches);

        $cols = explode("\n", $matches[1]);
        $newCols = [];
        foreach ($cols as $value) {
            $value = trim($value);
            if (empty($value))
                continue;
            $value = $this->remakeSql($value);
            if (substr($value, -1) == ',') $value = substr($value, 0, -1);

            $vs = explode(' ', $value);
            $colName = $vs[0];

            if ($colName == 'KEY' || $colName == 'INDEX' || $colName == 'UNIQUE') {
                $name_length = strlen($colName);
                if ($colName == 'UNIQUE') $name_length = $name_length + 4;

                $subValue = trim(substr($value, $name_length));
                $subVs = explode(' ', $subValue);
                $subColName = $subVs[0];
                $newCols[$colName][$subColName] = trim(substr($value, ($name_length + 2 + strlen($subColName))));
            } elseif ($colName == 'PRIMARY') {
                $newCols[$colName] = trim(substr($value, 11));
            } else {
                $newCol = trim(substr($value, strlen($colName)));
                $newCols[$colName] = $newCol;
            }
        }
        return $newCols;
    }

    /**
     * @param string $type table|view|function|procedure
     * @param $subject object name
     * @return string|boolean
     */
    protected function getDefFromDB($type, $subject)
    {
        $type = strtolower($type);
        $def = false;
        if ($type == 'table') {
            $query = (array)DB::selectOne("SHOW CREATE TABLE `{$subject}`");
            $def = (string) $query['Create Table'];
        } elseif ($type == 'view') {
            $query = (array) DB::selectOne("SHOW CREATE VIEW `{$subject}`");
            $def = (string) $query['Create View'];
        } elseif ($type == 'function') {
            $query = (array) DB::selectOne("SHOW CREATE FUNCTION `{$subject}`");
            $def = (string) $query['Create Function'];
        } elseif ($type == 'procedure') {
            $query = (array)DB::selectOne("SHOW CREATE PROCEDURE `{$subject}`");
            $def = (string) $query['Create Procedure'];
        }
        return $def;
    }


    /**
     * 当前库是否存在表
     * @param $tableName
     * @return bool
     */
    protected function dbHasTable($tableName)
    {
        $sql = 'select * from information_schema.tables where table_schema = ? and table_name = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $tableName]);
        return !empty($ret);
    }

    /**
     * 当前库是否存在函数
     * @param $funcName
     * @return bool
     */
    protected function dbHasFunction($funcName)
    {
        $sql = 'select * from information_schema.routines where routine_schema = ? and routine_name = ? and routine_type = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $funcName, 'FUNCTION']);
        return !empty($ret);
    }

    /**
     * 当前库是否存在存储过程
     * @param $procName
     * @return bool
     */
    protected function dbHasProcedure($procName)
    {
        $sql = 'select * from information_schema.routines where routine_schema = ? and routine_name = ? and routine_type = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $procName, 'PROCEDURE']); return !empty($ret);
        return !empty($ret);
    }

    /**
     * 当前库是否存在视图
     * @param $viewName
     * @return bool
     */
    protected function dbHasView($viewName)
    {
        $sql = 'select * from information_schema.views where table_schema = ? and table_name = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $viewName]);
        return !empty($ret);
    }

    protected function stripSpaces($str)
    {
        return preg_replace("/\s*[\r?\n|\t]\s*/", ' ', $str);
    }
}
