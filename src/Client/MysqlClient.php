<?php

namespace Jezzis\MysqlSyncer\Client;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jezzis\MysqlSyncer\CommandMessage;

class MysqlClient implements ClientInterface
{
    /**
     * @var CommandMessage
     */
    protected $msg;

    public function __construct()
    {
        $this->msg = new CommandMessage();
    }

    public function getDbDefiner()
    {
        static $definer;
        if (empty($definer)) {
            $host = DB::connection()->getConfig('host');
            $user = DB::connection()->getConfig('username');
            $definer = "DEFINER=`{$user}`@`{$host}`";
        }
        return $definer;
    }

    public function getDefFromDB($type, $subject)
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

    public function dbHasTable($name)
    {
        $sql = 'select * from information_schema.tables where table_schema = ? and table_name = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $name]);
        return !empty($ret);
    }

    public function dbHasFunction($name)
    {
        $sql = 'select * from information_schema.routines where routine_schema = ? and routine_name = ? and routine_type = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $name, 'FUNCTION']);
        return !empty($ret);
    }

    public function dbHasProcedure($name)
    {
        $sql = 'select * from information_schema.routines where routine_schema = ? and routine_name = ? and routine_type = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $name, 'PROCEDURE']); return !empty($ret);
        return !empty($ret);
    }

    public function dbHasView($name)
    {
        $sql = 'select * from information_schema.views where table_schema = ? and table_name = ?';
        $ret = DB::select($sql, [Schema::getConnection()->getDatabaseName(), $name]);
        return !empty($ret);
    }

    public function execSqlList($sqlList)
    {
        foreach ($sqlList as $sql) {
            DB::connection()->getPdo()->exec($sql);
        }
    }
}
