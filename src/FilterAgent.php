<?php

namespace Freshbitsweb\Laratables;

class FilterAgent
{
    private static $model;

    /**
     * Applies where conditions to the query according to search value
     *
     * @param \Illuminate\Database\Query\Builder Query object
     * @param array Columns to be searched
     * @param string Search value
     * @return \Illuminate\Database\Query\Builder Query object
     */
    public static function applyFiltersTo($query, $searchColumns, $searchValue)
    {
        // We may receive custom model or Illuminate\Database\Query\Builder object
        // use newQuery() to make it Builder object and then get custom model using getModel() method
        self::$model = get_class($query->newQuery()->getModel());

        return $query->where(function ($query) use ($searchColumns, $searchValue) {
            foreach ($searchColumns as $columnName) {
                $query = self::applyFilter($query, $columnName, $searchValue);
            }
        });
    }

    /**
     * Applies filter condition for the table column
     *
     * @param \Freshbitsweb\Laratables\QueryHandler Query object
     * @param string Column name
     * @param string Search string
     * @return \Freshbitsweb\Laratables\QueryHandler Query object
     */
    protected static function applyFilter($query, $column, $searchValue)
    {
        if ($methodName = self::hasCustomSearch($column)) {
            return self::$model::$methodName($query, $searchValue);
        }

        if (isRelationColumn($column)) {
            return self::applyRelationFilter($query, $column, $searchValue);
        }

        $searchValue = '%'.$searchValue.'%';
        return $query->orWhere($column, 'like', "$searchValue");
    }

    /**
     * Decides whether column has custom search method defined in the model and returns method name if yes
     *
     * @param string Name of the column
     * @return boolean|string
     */
    protected static function hasCustomSearch($columnName)
    {
        $methodName = camel_case('laratables_search_' . $columnName);

        if (method_exists(self::$model, $methodName)) {
            return $methodName;
        }

        return false;
    }

    /**
     * Applies filter condition for the relation column
     *
     * @param \Freshbitsweb\Laratables\QueryHandler Query object
     * @param string Column name
     * @param string Search string
     * @return \Freshbitsweb\Laratables\QueryHandler Query object
     */
    protected static function applyRelationFilter($query, $column, $searchValue)
    {
        if ($methodName = self::hasCustomSearch(str_replace('.', '_', $column))) {
            return self::$model::$methodName($query, $searchValue);
        }

        list($relationName, $relationColumnName) = getRelationDetails($column);
        $searchValue = '%'.$searchValue.'%';

        return $query->orWhereHas($relationName, function ($query) use ($relationColumnName, $searchValue) {
            $query->where($relationColumnName, 'like', "$searchValue");
        });
    }
}