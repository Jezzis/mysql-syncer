<?php
/**
 * Created by PhpStorm.
 * User: szj
 * Date: 16/12/1
 * Time: 11:37
 */

namespace Jezzis\MysqlSyncer\Client;


interface ClientInterface
{
    /**
     * get current db definer
     *
     * @return string
     */
    public function getDbDefiner();

    /**
     * get definition from current db, when failure return false
     *
     * @param string $type table|view|function|procedure
     * @param $subject object name
     * @return string|bool
     */
    public function getDefFromDB($type, $subject);


    /**
     * is the table exists in current db
     *
     * @param string $name table name
     * @return bool
     */
    public function dbHasTable($name);

    /**
     * is the function exists in current db
     * @param string $name function name
     * @return bool
     */
    public function dbHasFunction($name);

    /**
     * is the procedure exists in current db
     * @param string $name procedure name
     * @return bool
     */
    public function dbHasProcedure($name);

    /**
     * is the view exists in current db
     *
     * @param string $name view name
     * @return bool
     */
    public function dbHasView($name);

    /**
     * execute sql list
     *
     * @param array $sqlList sql string list
     * @return mixed
     */
    public function execSqlList($sqlList);

}