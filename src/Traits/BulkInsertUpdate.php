<?php
/**
 * User: Warlof Tutsimo <loic.leuilliot@gmail.com>
 * Date: 01/05/2018
 * Time: 10:37
 */

namespace Seat\Eveapi\Traits;


use Illuminate\Database\Grammar;

trait BulkInsertUpdate
{

    /**
     * @param array $values
     * @param array|null $updateColumns
     * @return bool|int
     */
    public static function insertOnDuplicateKey(array $values, array $updateColumns = null)
    {
        if (empty($values))
            return true;

        if (! is_array(reset($values)))
            $values = [$values];
        else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $model = self::getModel();

        $sql = static::compileInsertOnDuplicate($model->getConnection()->getQueryGrammar(), $values, $updateColumns);

        $values = static::inLineArray($values);

        return $model->getConnection()->affectingStatement($sql, $values);
    }

    /**
     * @param array $values
     * @return bool|int
     */
    public static function insertIgnore(array $values)
    {
        if (empty($values))
            return true;

        if (! is_array(reset($values)))
            $values = [$values];
        else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $model = self::getModel();

        $sql = static::compileInsertIgnore($model->getConnection()->getQueryGrammar(), $values);

        $values = static::inLineArray($values);

        return $model->getConnection()->affectingStatement($sql, $values);
    }

    /**
     * @param array $values
     * @return bool|int
     */
    public static function replace(array $values)
    {
        if (empty($values))
            return true;

        if (! is_array(reset($values)))
            $values = [$values];
        else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $model = self::getModel();

        $sql = static::compileReplace($model->getConnection()->getQueryGrammar(), $values);

        $values = static::inLineArray($values);

        return $model->getConnection()->affectingStatement($sql, $values);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public static function getModel()
    {
        $model = get_called_class();
        return new $model;
    }

    /**
     * Convert a multi-dimensional array into a simple array
     *
     * @param array $records
     * @return array
     */
    protected static function inLineArray(array $records)
    {
        $values = [];

        foreach ($records as $record) {
            $values = array_merge($values, array_values($record));
        }

        return $values;
    }

    /**
     * Provide the model table name and prefix
     *
     * @return string
     */
    private static function getTableName()
    {
        $model = self::getModel();
        return $model->getConnection()->getTablePrefix() . $model->getTable();
    }

    /**
     * Produce an insert on duplicate sql statement
     *
     * @author Yada Khov
     * @param array $values
     * @param array|null $updateColumns
     * @return string
     */
    private static function compileInsertOnDuplicate(Grammar $grammar, array $values, array $updateColumns = null)
    {
        $table = static::getTableName();

        $columns = $grammar->columnize(array_keys(reset($values)));

        $parameters = collect($values)->map(function ($record) use ($grammar) {
            return '(' . $grammar->parameterize($record) . ')';
        })->implode(', ');

        if (empty($updateColumns))
            $updateColumns = array_keys(reset($values));

        $updateColumns = collect($updateColumns)->map(function($column) {
            return sprintf('`%s` = VALUES(`%s`)', $column, $column);
        })->implode(', ');

        $sql = "INSERT INTO `$table` ($columns) VALUES $parameters ON DUPLICATE KEY UPDATE $updateColumns";

        return $sql;
    }

    /**
     * Produce an insert ignore sql statement
     *
     * @author Yada Khov
     * @param array $values
     * @return string
     */
    private static function compileInsertIgnore(Grammar $grammar, array $values)
    {
        $table = static::getTableName();

        $columns = $grammar->columnize(array_keys(reset($values)));

        $parameters = collect($values)->map(function($record) use ($grammar) {
            return '(' . $grammar->parameterize($record) . ')';
        })->implode(', ');

        $sql = "INSERT IGNORE INTO `$table` ($columns) VALUES $parameters";

        return $sql;
    }

    /**
     * Produce a replace sql statement
     *
     * @author Yada Khov
     * @param array $values
     * @return string
     */
    private static function compileReplace(Grammar $grammar, array $values)
    {
        $table = static::getTableName();

        $columns = $grammar->columnize(array_keys(reset($values)));

        $parameters = collect($values)->map(function($record) use ($grammar) {
            return '(' . $grammar->parameterize($record) . ')';
        })->implode(', ');

        $sql = "REPLACE INTO `$table` ($columns) VALUES $parameters";

        return $sql;
    }

}
