<?php

namespace AppBundle\Service\DataTables;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface DataTableInterface
 * @package AppBundle\Service\DataTables
 */
interface DataTableInterface
{
    /**
     * @return mixed
     */
    public static function factory(): DataTableBuilder;

    /**
     * @return array
     */
    public function getData();

    /**
     * @return QueryBuilder
     */
    public function getFilteredQuery();

    /**
     * @return mixed
     */
    public function getRecordsFiltered();

    /**
     * @return bool|int
     */
    public function getRecordsTotal();

    /**
     * @return array
     */
    public function getResponse();

    /**
     * @return Request
     */
    public function getRequest(): Request;

    /**
     * @param Request $request
     * @return mixed
     */
    public function setRequest(Request &$request);

    /**
     * @return QueryBuilder|ORMQueryBuilder
     */
    public function getQueryBuilder();

    /**
     * @param QueryBuilder|ORMQueryBuilder $queryBuilder
     * @return mixed
     */
    public function setQueryBuilder($queryBuilder);

    /**
     * @return mixed
     */
    public function getIndexColumn();

    /**
     * @param $indexColumn
     * @param bool $clearName
     * @return mixed
     */
    public function setIndexColumn($indexColumn, $clearName = false);

    /**
     * @return mixed
     */
    public function getColumnField();

    /**
     * @param $columnField
     * @return mixed
     */
    public function setColumnField($columnField);

    /**
     * @return mixed
     */
    public function getColumnAliases();

    /**
     * @param $columnAliases
     * @return mixed
     */
    public function setColumnAliases($columnAliases);
}
