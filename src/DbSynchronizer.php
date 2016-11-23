<?php

namespace Jezzis\Mysqlsyncer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbSynchronizer extends Command
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

    protected $permitDrop = false;

    protected $sqlPath = './', $debug = false;

    protected $definer, $host, $user, $charset = 'utf8', $collate = 'utf8_general_ci';

    protected $delimiter, $msgList, $excsqlList;

    public function __construct()
    {
        parent::__construct();

        $this->host = DB::connection()->getConfig('host');
        $this->user = DB::connection()->getConfig('username');
        $this->definer = "DEFINER=`{$this->user}`@`{$this->host}`";
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $file = $this->argument('file');
        $this->permitDrop = $this->option('drop');

        $file = $this->sqlPath . $file . '.sql';
        while (!file_exists($file)) {
            $file = $this->ask('cannot find file [' . basename($file) . '], please retype filename');
            $file = $this->sqlPath . $file . '.sql';
        }

        $this->executeSQL($file);
        $this->info('done');
    }

    function executeSQL($file)
    {

        // 处理字符串，去掉一些注释的代码
        $sql = file_get_contents($file);
        // 去除如/***/的注释
        $sql = preg_replace("[(/\*)+.+(\*/;\s*)]", '', $sql);
        // 去除如--类的注释
        $sql = preg_replace("(--.*?\n)", '', $sql);

        $this->syncTable($sql);

        $this->syncView($sql);

        $this->syncFunc($sql);

        $this->syncProc($sql);

        // 输出信息并存入
        if (empty($this->excsqlList)) {
            $this->info('nothing to do.');
            return;
        }

        $this->info("execute sqls: \n" . implode("\n\n", $this->excsqlList));
        $confirm = $this->ask('continue: y|n');
        if (strtolower($confirm) == 'y') {
            foreach ($this->excsqlList as $sql) {
                DB::connection()->getPdo()->exec($sql);
            }
        }
    }

    function syncTable($sql)
    {
        preg_match_all("/CREATE\s+TABLE\s+(IF NOT EXISTS.+?)?`?(.+?)`?\s*\((.+?)\)\s*(ENGINE|TYPE)\s*\=(.+?;)/is", $sql, $matches);
        $newTabs = empty($matches[2]) ? array() : $matches[2];
        $newSqls = empty($matches[0]) ? array() : $matches[0];

        $totalNum = count($newTabs);
        for ($num = 0; $num < $totalNum; $num++) {
            $newTab = $newTabs[$num];
            $newCols = $this->getColumn($newSqls[$num]);
            $oldTab = $newTab;

            if (!$this->hasTable($newTab)) {
                $this->excsqlList[] = str_replace($oldTab, $newTab, $newSqls[$num]);
            } else {
                $query = (array)DB::selectOne("SHOW CREATE TABLE `{$newTab}`");

                $oldCols = $this->getColumn($query['Create Table']);
//                r(['tabname' => $newTab, 'cols' => $oldCols]);
                $updates = [];
                $allfileds = array_keys($newCols);
                foreach ($newCols as $key => $newCol) {
                    if ($key == 'PRIMARY') {
                        if (empty($oldCols[$key]) || $newCol != $oldCols[$key]) {
                            if (!empty($oldCols[$key])) {
                                $sql = "RENAME TABLE " . $newTab . " TO " . $newTab . '_bak';
                                $this->excsqlList[] = $sql;
                            }
                            $updates[] = "ADD PRIMARY KEY $newCol";
                        }
                    } elseif ($key == 'KEY') {
                        foreach ($newCol as $subkey => $subValue) {
                            if (!empty($oldCols['KEY'][$subkey])) {
                                if ($subValue != $oldCols['KEY'][$subkey]) {
                                    $updates[] = "ADD INDEX `$subkey` $subValue";
                                }
                            } else {
                                $updates[] = "ADD INDEX `$subkey` $subValue";
                            }
                        }
                    } elseif ($key == 'UNIQUE') {
                        foreach ($newCol as $subkey => $subValue) {
                            if (!empty($oldCols['UNIQUE'][$subkey])) {
                                if ($subValue != $oldCols['UNIQUE'][$subkey]) {
                                    $updates[] = "ADD UNIQUE INDEX `$subkey` $subValue";
                                }
                            } else {
//                                $sql = "ALTER TABLE  " . $newTab . " DROP INDEX `$subkey`";
//                                $this->excsqlList[] = $sql;
                                $updates[] = "ADD UNIQUE INDEX `$subkey` $subValue";
                            }
                        }
                    } else {
                        if (!empty($oldCols[$key])) {
                            $oldCol = $oldCols[$key];
//                            if ($newTab == 'option_log') {
//                                r('col name: ' . $key);
//                                r('new col def: ' . $newCol);
//                                r('old col def: ' . $oldCols[$key]);
//                                $this->debug = true;
//                            } else {
//                                $this->debug = false;
//                            }
                            if (!$this->compareColumnDefinition($oldCol, $newCol)) {
                                $updates[] = "CHANGE `$key` `$key` $newCol";
                            }
                        } else {
                            $i = array_search($key, $allfileds);
                            $fieldposition = $i > 0 ? 'AFTER `' . $allfileds[$i - 1] . '`' : 'FIRST';
                            $updates[] = "ADD `$key` $newCol $fieldposition";
                        }
                    }
                }
                if ($updates) {
                    $sql = "ALTER TABLE `" . $newTab . "` " . implode(",\n  ", $updates);
                    $this->excsqlList[] = $sql;
                } else {
// 				    checkColumnDiff($execSqlInfo, $newCols, $oldCols); // 字段顺序校验
                }

                // del
                $deletes = array();
                foreach ($oldCols as $colname => $definition) {
                    if (in_array($colname, array('UNIQUE', 'KEY'))) { // drop index
                        if (empty($newCols[$colname])) {
                            foreach ($definition as $indexName => $indexColName) {
                                $deletes[] = "DROP INDEX `{$indexName}`";
                            }
                        }

                        if (!empty($newCols[$colname])) {
                            $diffArr = array_diff(array_keys($definition), array_keys($newCols[$colname]));
                            foreach ($diffArr as $indexName => $indexColName) {
                                $deletes[] = "DROP INDEX `{$indexName}`";
                            }
                        }
                    } elseif ($colname == 'PRIMARY') {
                        continue;
                    } else { // drop column
                        if (empty($newCols[$colname])) {
                            $deletes[] = "DROP `{$colname}`";
                        }
                    }
                }

                if ($deletes && $this->permitDrop) {
                    $sql = "ALTER TABLE `{$newTab}` " . implode(",\n  ", $deletes);
                    $this->excsqlList[] = $sql;
                }
            }
        }
    }

    protected function compareColumnDefinition(&$def1, &$def2)
    {
        $def1 = $this->standardColumnDefinion($def1);
        $def2 = $this->standardColumnDefinion($def2);
        $result = $def1 == $def2;
        if (!$result || $this->debug) {
            r([$def1, $def2]);
        }
        return $result;
    }

    protected function standardColumnDefinion($def)
    {
        $def = preg_replace('/DEFAULT\s+([0-9]+)/i', "DEFAULT '\$1'", $def); // 整型默认值加引号
        $defInfo = $this->parseColumnDefinition($def);

        $metaType = strtoupper(preg_replace('/\([^\)]+\)/', '', $defInfo['type']));
        if (in_array($metaType, ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'LONGTEXT', 'ENUM', 'SET'])) {
            empty($defInfo['character']) && $defInfo['character'] = 'CHARACTER SET ' . $this->charset;
            empty($defInfo['collate']) && $defInfo['collate'] = 'COLLATE ' . $this->collate;
        }

        $stdColDefStr = strtoupper($defInfo['type']) . ' ';
        !empty($defInfo['unsigned'])    && $stdColDefStr .= strtoupper($defInfo['unsigned']) . ' ';
        !empty($defInfo['zerofill'])    && $stdColDefStr .= strtoupper($defInfo['zerofill']) . ' ';
        !empty($defInfo['character'])   && $stdColDefStr .= strtoupper($defInfo['character']) . ' ';
        !empty($defInfo['collate'])     && $stdColDefStr .= strtoupper($defInfo['collate']) . ' ';
        !empty($defInfo['nullable'])    && $stdColDefStr .= strtoupper($defInfo['nullable']) . ' ';
        !empty($defInfo['default'])     && $stdColDefStr .= $defInfo['default'] . ' ';
        !empty($defInfo['comment'])     && $stdColDefStr .= $defInfo['comment'] . ' ';
        return $stdColDefStr;
    }

    protected function parseColumnDefinition($def)
    {
        $pattern = "/(\w+(?:\([^\)]+\))?)\s*(?:(BINARY)\s+)?(?:(UNSIGNED)\s+)?(?:(ZEROFILL)\s+)?(?:(CHARACTER\s+SET\s+\w+)\s+)?(?:(COLLATE\s+\w+)\s+)?(?:((?:NOT\s+)?NULL)\s*)?(?:(DEFAULT\s+\w+)\s*)?(?:(COMMENT\s+'[^\']+'))?/is";
        preg_match($pattern, $def, $matches);

        return [
            'type'      => $matches[1],
            'binary'    => !empty($matches[2]) ? $matches[2] : false,
            'unsigned'  => !empty($matches[3]) ? $matches[3] : false,
            'zerofill'  => !empty($matches[4]) ? $matches[4] : false,
            'character' => !empty($matches[5]) ? $matches[5] : false,
            'collate'   => !empty($matches[6]) ? $matches[6] : false,
            'nullable'  => !empty($matches[7]) ? $matches[7] : false,
            'default'   => !empty($matches[8]) ? $matches[8] : false,
            'comment'   => !empty($matches[9]) ? $matches[9] : false,
        ];
    }

    function syncView($sql)
    {
        $pattern = '/CREATE\s+(ALGORITHM=UNDEFINED)?\s+(DEFINER=`.+?`@`.+?`)?\s+(SQL SECURITY DEFINER)?\s+VIEW\s+(IF NOT EXISTS)?\s*`?(.+?)`\s+AS\s+(SELECT\s+.+?)\s*;/is';
        preg_match_all($pattern, $sql, $matches);

        $newViews = empty($matches[6]) ? [] : $matches[6];
        $newViewNames = empty($matches[5]) ? [] : $matches[5];
        foreach ($newViewNames as $idx => $newViewName) {

            if ($this->hasView($newViewName)) {
                $query = (array) DB::selectOne("SHOW CREATE VIEW `{$newViewName}`");
                preg_match_all($pattern, $query['Create View'] . ';', $matches);

                $oldDef = $matches[6][0];
                if ($oldDef != $newViews[$idx]) {
                    $sql = "DROP VIEW `{$newViewName}`;";
                    $this->excsqlList[] = $sql;
                    $this->excsqlList[] = $newViews[$idx];
                }
            } else {
                $sql = "CREATE VIEW `{$newViewName}` AS {$newViews[$idx]};";
                $this->excsqlList[] = $sql;
            }
        }
    }

    protected function prepareDelimiter($sql)
    {
        $pattern = '/DELIMITER\s+(.+?)\r?\n\r?/is';
        preg_match($pattern, $sql, $matches);
        $this->delimiter = $matches[1];
        if (empty($this->delimiter)) {
            $this->delimiter = ';;';
        }
    }

    function syncFunc($sql)
    {
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
            $newDef = str_replace($definers[$idx], $this->definer, $newFuncs[$idx]);
            if ($this->hasFunction($newFuncName)) {
                $query = (array) DB::selectOne("SHOW CREATE FUNCTION `{$newFuncName}`");
                $oldDef = $query['Create Function'];
                if ($this->stripSpaces($oldDef) != $this->stripSpaces($newDef)) {
                    r($this->stripSpaces($oldDef));
                    r($this->stripSpaces($newDef));
                    $this->excsqlList[] = "DROP FUNCTION `{$newFuncName}`;";
                    $this->excsqlList[] = $newDef;
                }
            } else {
                $this->excsqlList[] = $newDef;
            }
        }
    }

    function syncProc($sql)
    {
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
            $newDef = str_replace($definers[$idx], $this->definer, $newProcs[$idx]);
            if ($this->hasProcedure($newProcName)) {
                $query = (array) DB::selectOne("SHOW CREATE PROCEDURE `{$newProcName}`");
                $oldDef = $query['Create Procedure'];
                if ($this->stripSpaces($oldDef) != $this->stripSpaces($newDef)) {
                    $this->excsqlList[] = "DROP PROCEDURE `{$newProcName}`;";
                    $this->excsqlList[] = $newDef;
                }
            } else {
                $this->excsqlList[] = $newDef;
            }
        }
    }


    /**
     * 检索两个数组的键值顺序是否一致，若不一致列出具体的信息
     * @param $execSqlInfo
     * @param $newCols
     * @param $oldCols
     * @return bool
     */
    function checkColumnDiff($execSqlInfo, $newCols, $oldCols)
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


    function remakeSql($value)
    {
        $value = trim(preg_replace("/\s+/u", ' ', $value));
        $value = str_replace(array('`', ', ', ' ,', '( ', ' )', 'mediumtext'), array('', ',', ',', '(', ')', 'text'), $value);
        return $value;
    }

    function getColumn($createSql)
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

    protected function hasTable($tableName)
    {
        $sql = 'select * from information_schema.tables where table_schema = ? and table_name = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $tableName]);
        return !empty($ret);
    }

    protected function hasFunction($funcName)
    {
        $sql = 'select * from information_schema.routines where routine_schema = ? and routine_name = ? and routine_type = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $funcName, 'FUNCTION']);
        return !empty($ret);
    }

    protected function hasProcedure($procName)
    {
        $sql = 'select * from information_schema.routines where routine_schema = ? and routine_name = ? and routine_type = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $procName, 'PROCEDURE']); return !empty($ret);
        return !empty($ret);
    }

    protected function hasView($viewName)
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
