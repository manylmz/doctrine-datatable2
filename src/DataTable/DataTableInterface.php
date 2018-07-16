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
     * @param $indexColumn
     * @return mixed
     */
    public function withIndexColumn($indexColumn);

    /**
     * @param $columnAliases
     * @return mixed
     */
    public function withColumnAliases($columnAliases);

    /**
     * @param $columnField
     * @return mixed
     */
    public function withColumnField($columnField);

    /**
     * @param QueryBuilder|ORMQueryBuilder $queryBuilder
     * @return mixed
     */
    public function withQueryBuilder($queryBuilder);

    /**
     * @param Request $request
     * @return mixed
     */
    public function withRequest(Request $request);
}
