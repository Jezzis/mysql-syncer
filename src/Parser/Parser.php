<?php
/**
 * Created by PhpStorm.
 * User: szj
 * Date: 16/12/1
 * Time: 11:33
 */

namespace Jezzis\MysqlSyncer\Parser;


use Jezzis\MysqlSyncer\Client\ClientInterface;
use Jezzis\MysqlSyncer\CommandMessage;

class Parser
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var CommandMessage
     */
    protected $msg;

    protected $charset = 'utf8', $collate = 'utf8_general_ci';

    protected $permitDrop = false, $execSqlList;

    public function __construct($client, $permitDrop = false)
    {
        $this->permitDrop = $permitDrop;

        $this->client = $client;

        $this->msg = new CommandMessage;
    }

    /**
     * @return bool
     */
    public function permitDrop()
    {
        return $this->permitDrop;
    }

    /**
     * parse entrance
     *
     * @param $sql
     * @return mixed
     */
    public function parse($sql)
    {
        $sql = $this->prepareSql($sql);

        $this->syncTable($sql);

        $this->syncView($sql);

        $this->syncFunction($sql);

        $this->syncProcedure($sql);
    }



    protected function prepareSql($sql)
    {
        // 去除如/***/的注释
        $sql = preg_replace("[(/\*)+.+(\*/;\s*)]", '', $sql);
        // 去除如--类的注释
        $sql = preg_replace("(--.*?\n)", '', $sql);

        return $sql;
    }

    protected function addExecSql($execSql)
    {
        $this->execSqlList[] = trim($execSql);
    }

    public function getExecSqlList()
    {
        return $this->execSqlList;
    }

    protected function syncTable($sql)
    {
        $this->msg->vverbose("\n- sync table");

        preg_match_all("/CREATE\s+TABLE\s+(IF NOT EXISTS.+?)?`?(.+?)`?\s*\((.+?)\)\s*(ENGINE|TYPE)\s*\=(.+?;)/is", $sql, $matches);
        $newTabs = empty($matches[2]) ? array() : $matches[2];
        $newSqls = empty($matches[0]) ? array() : $matches[0];

        $totalNum = count($newTabs);
        for ($num = 0; $num < $totalNum; $num++) {
            $newTab = $newTabs[$num];
            $this->msg->vverbose("checking table: `$newTab`...");
            $newCols = $this->getColDefListByTableDef($newSqls[$num]);
            $oldTab = $newTab;

            if (!$this->client->dbHasTable($newTab)) {
                $this->addExecSql(str_replace($oldTab, $newTab, $newSqls[$num]));
            } else {
                $oldCols = $this->getColDefListByTableDef($this->client->getDefFromDB('table', $newTab));
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
                            if (strpos($oldColName, ',') === false && empty($newCols[$oldColName])) { // 原单主键字段已不存在,须删除
                                $updateSqlList[] = "DROP `{$oldColName}`";
                            }

                            $updateSqlList[] = "DROP PRIMARY KEY";
                            $updateSqlList[] = "ADD PRIMARY KEY {$newCol}";
                        }
                    } elseif ($key == 'KEY') {
                        foreach ($newCol as $keyName => $keyDefinition) {
                            if (!empty($oldCols['KEY'][$keyName])) {
                                if ($keyDefinition != $oldCols['KEY'][$keyName]) {
                                    $updateSqlList[] = "DROP INDEX `$keyName`";
                                    $updateSqlList[] = "ADD INDEX `$keyName` $keyDefinition";
                                }
                            } else {
                                $updateSqlList[] = "ADD INDEX `$keyName` $keyDefinition";
                            }
                        }
                    } elseif ($key == 'UNIQUE') {
                        foreach ($newCol as $keyName => $keyDefinition) {
                            if (!empty($oldCols['UNIQUE'][$keyName])) {
                                if ($keyDefinition != $oldCols['UNIQUE'][$keyName]) {
                                    $updateSqlList[] = "DROP INDEX `$keyName`";
                                    $updateSqlList[] = "ADD UNIQUE INDEX `$keyName` $keyDefinition";
                                }
                            } else {
                                $updateSqlList[] = "ADD UNIQUE INDEX `$keyName` $keyDefinition";
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
                            foreach ($definition as $indexName => $indexDefinition) {
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

            $this->msg->vverbose("done\n");
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
        $def1 = $this->standardColumnDefinion($def1, $table, $column);
        $def2 = $this->standardColumnDefinion($def2, $table, $column);
        $result = $def1 == $def2;
        if (!$result) {
            $this->msg->verbose("\na different column definition was detected in `$table`.$column:");
            $diff = $this->msg->highlightDiff($def1, $def2);
            $this->msg->verbose("old: $def1", CommandMessage::MSG_STYLE_NONE);
            $this->msg->verbose("new: $diff", CommandMessage::MSG_STYLE_NONE);
        }
        return $result;
    }

    /**
     * 补全列定义
     * @param $def
     * @param $table
     * @param $column
     * @return string
     */
    protected function standardColumnDefinion($def, $table, $column)
    {
        static $cache = [];

        $uniqKey = md5($def);
        if (empty($cache[$uniqKey])) {
            $def = preg_replace('/DEFAULT\s+([0-9]+)/i', "DEFAULT '\$1'", $def); // 整型默认值加引号
            $defInfo = $this->parseColumnDefinition($def, $table, $column);

            $metaType = strtoupper(preg_replace('/\([^\)]+\)/', '', $defInfo['type']));
            if (in_array($metaType, ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'])) {
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
            !empty($defInfo['autoinc']) && $stdColDefStr .= $defInfo['autoinc'] . ' ';
            !empty($defInfo['comment']) && $stdColDefStr .= $defInfo['comment'] . ' ';

            $cache[$uniqKey] = trim($stdColDefStr);
        }
        return $cache[$uniqKey];
    }

    /**
     * 解析列定义
     * @param $def
     * @param $table
     * @param $column
     * @return mixed
     */
    protected function parseColumnDefinition($def, $table, $column)
    {
        static $cache = [];

        $uniqKey = md5($def);
        if (empty($cache[$table][$column][$uniqKey])) {
            $pattern = "/(\w+(?:\([^\)]+\))?)\s*";
            $pattern .= "(?:(BINARY)\s+)?";
            $pattern .= "(?:(UNSIGNED)\s+)?";
            $pattern .= "(?:(ZEROFILL)\s+)?";
            $pattern .= "(?:(CHARACTER\s+SET\s+\w+)\s+)?";
            $pattern .= "(?:(COLLATE\s+\w+)\s+)?";
            $pattern .= "(?:((?:NOT\s+)?NULL)\s*)?";
            $pattern .= "(?:(DEFAULT\s+(?:'[^\']*'|\w+))\s*)?";
            $pattern .= "(?:(AUTO_INCREMENT)\s*)?";
            $pattern .= "(?:(COMMENT\s+'[^\']+'))?/is";
            preg_match($pattern, $def, $matches);

            $definition = [
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

            // 默认行为修正
            if (empty($definition['nullable']) && empty($definition['default'])) {
                $definition['default'] = 'DEFAULT NULL';
            }

            if (empty($definition['nullable'])) {
                $definition['nullable'] = 'NULL';
            }

            empty($cache[$table]) && $cache[$table] = [];
            empty($cache[$table][$column]) && $cache[$table][$column] = [];
            $cache[$table][$column][$uniqKey] = $definition;
        }
        return $cache[$table][$column][$uniqKey];
    }

    /**
     * 处理视图
     * @param $sql
     */
    protected function syncView($sql)
    {
        $this->msg->vverbose("\n- sync view");

        $pattern = '/CREATE\s+(ALGORITHM=UNDEFINED)?\s+(DEFINER=`.+?`@`.+?`)?\s+(SQL SECURITY DEFINER)?\s+VIEW\s+(IF NOT EXISTS)?\s*`?(.+?)`\s+AS\s+(SELECT\s+.+?)\s*;/is';
        preg_match_all($pattern, $sql, $matches);

        $newViews = empty($matches[6]) ? [] : $matches[6];
        $newViewNames = empty($matches[5]) ? [] : $matches[5];
        foreach ($newViewNames as $idx => $newViewName) {
            $this->msg->vverbose("view $newViewName");
            if ($this->client->dbHasView($newViewName)) {
                $def = $this->client->getDefFromDB('view', $newViewName);
                preg_match_all($pattern, $def . ';', $matches);

                $oldDef = $matches[6][0];
                if ($oldDef != $newViews[$idx]) {
                    $this->msg->verbose("\na different view definition was detected in `$newViewName`:");
                    $this->msg->verbose("old def: $oldDef", CommandMessage::MSG_STYLE_NONE);
                    $this->msg->verbose("new def: $newViews[$idx]", CommandMessage::MSG_STYLE_NONE);
                    $this->addExecSql("DROP VIEW `{$newViewName}`;");
                    $this->addExecSql("CREATE VIEW `$newViewName` AS $newViews[$idx];");
                }
            } else {
                $this->addExecSql("CREATE VIEW `{$newViewName}` AS {$newViews[$idx]};");
            }
        }
    }

    /**
     * 处理分隔符
     * @param string $sql
     * @return string
     */
    protected function getDelimiter($sql)
    {
        static $delimiter;
        if (empty($delimiter)) {
            $pattern = '/DELIMITER\s+(.+?)\r?\n\r?/is';
            preg_match($pattern, $sql, $matches);
            if (empty($matches[1])) {
                $delimiter = ';;';
            } else {
                $delimiter = $matches[1];
            }
        }
        return $delimiter;
    }

    protected function getDelimiterToken($sql)
    {
        $delimiter = $this->getDelimiter($sql);
        $token = '';
        for ($i = 0; $i < strlen($delimiter); $i ++) {
            $token .= "\\" . substr($delimiter, $i, 1);
        }
        return $token;
    }

    /**
     * 处理函数
     * @param $sql
     */
    protected function syncFunction($sql)
    {
        $this->msg->vverbose("\n- sync function");

        $token = $this->getDelimiterToken($sql);
        $pattern = "/(CREATE\s+(DEFINER=`[^`]+?`@`[^`]+?`)?\s+FUNCTION\s+(IF NOT EXISTS)?\s*`?([^`]+?)`\s*.+?END)\s*{$token}/is";
        preg_match_all($pattern, $sql, $matches);

        $newFuncs = empty($matches[1]) ? [] : $matches[1];
        $newFuncNames = empty($matches[4]) ? [] : $matches[4];
        $definers = empty($matches[2]) ? [] : $matches[2];

        foreach ($newFuncNames as $idx => $newFuncName) {
            $this->msg->vverbose("function: $newFuncName");
            $newDef = str_replace($definers[$idx], $this->client->getDbDefiner(), $newFuncs[$idx]);
            if ($this->client->dbHasFunction($newFuncName)) {
                $oldDef = $this->client->getDefFromDB('function', $newFuncName);
                if ($this->stripSpaces($oldDef) != $this->stripSpaces($newDef)) {
                    $diff = $this->msg->highlightDiff($oldDef, $newDef);
                    $this->msg->verbose("\na different function definition was detected in `$newFuncName`:");
                    $this->msg->vverbose("old def: $oldDef", CommandMessage::MSG_STYLE_NONE);
                    $this->msg->verbose("new def: $diff", CommandMessage::MSG_STYLE_NONE);

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
        $this->msg->vverbose("\n- sync procedure");

        $token = $this->getDelimiterToken($sql);
        $matches = null;
        $pattern = "/(CREATE\s+(DEFINER=`[^`]+?`@`[^`]+?`)?\s+PROCEDURE\s+(IF NOT EXISTS)?\s*`?([^`]+?)`\s*.+?END)\s*{$token}/is";
        preg_match_all($pattern, $sql, $matches);

        $newProcs = empty($matches[1]) ? [] : $matches[1];
        $newProcNames = empty($matches[4]) ? [] : $matches[4];
        $definers = empty($matches[2]) ? [] : $matches[2];

        foreach ($newProcNames as $idx => $newProcName) {
            $this->msg->vverbose("procedure: $newProcName");
            $newDef = str_replace($definers[$idx], $this->client->getDbDefiner(), $newProcs[$idx]);
            if ($this->client->dbHasProcedure($newProcName)) {
                $oldDef = $this->client->getDefFromDB('procedure', $newProcName);
                if ($this->stripSpaces($oldDef) != $this->stripSpaces($newDef)) {
                    $diff = $this->msg->highlightDiff($oldDef, $newDef);
                    $this->msg->verbose("\na different procedure definition was detected in `$newProcName`:");
                    $this->msg->vverbose("old def: " . $this->stripSpaces($oldDef), CommandMessage::MSG_STYLE_NONE);
                    $this->msg->verbose("new def: " . $this->stripSpaces($newDef), CommandMessage::MSG_STYLE_NONE);

                    $this->addExecSql("DROP PROCEDURE `{$newProcName}`;");
                    $this->addExecSql($newDef);
                }
            } else {
                $this->addExecSql($newDef);
            }
        }
    }

    protected function remakeSql($value)
    {
        $value = trim(preg_replace("/\s+/u", ' ', $value));
        $value = str_replace(array('`', ', ', ' ,', '( ', ' )'), array('', ',', ',', '(', ')', 'text'), $value);
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

        $cols = empty($matches[1]) ? [] : explode("\n", $matches[1]);
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

    protected function stripSpaces($str)
    {
        return strtoupper(preg_replace("/\s*[\r?\n|\t]+\s*/", ' ', $str));
    }

    public function getMsgs()
    {
        return $this->msg->get();
    }

}