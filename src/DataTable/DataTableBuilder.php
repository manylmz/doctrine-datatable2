<?php

namespace AppBundle\Service\DataTables;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Builder
 * @package AppBundle\Service\DataTables
 */
class DataTableBuilder implements DataTableInterface
{
    /**
     * @var string
     */
    protected $columnField = 'data';

    /**
     * @var array
     */
    protected $columnAliases = [];

    /**
     * @var string
     */
    protected $indexColumn = '*';

    /**
     * @var QueryBuilder|ORMQueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var int
     */
    private $defaultLimit = 20;

    /**
     * @return DataTableBuilder
     */
    public static function factory(): DataTableBuilder
    {
        return new self();
    }

    /**
     * @return QueryBuilder|ORMQueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @param QueryBuilder|ORMQueryBuilder $queryBuilder
     * @return $this|mixed
     */
    public function setQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest() :Request
    {
        return $this->request;
    }

    /**
     * @param Request $request
     * @return $this|mixed
     */
    public function setRequest(Request &$request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getIndexColumn()
    {
        return $this->indexColumn;
    }

    /**
     * @param $indexColumn
     * @param bool $clearName
     * @return $this|mixed
     */
    public function setIndexColumn($indexColumn, $clearName = false)
    {
        $this->indexColumn = $clearName
            ? $this->clearIndexColumnName($indexColumn)
            : $indexColumn;

        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getColumnField()
    {
        return $this->columnField;
    }

    /**
     * @param $columnField
     * @return $this|mixed
     */
    public function setColumnField($columnField)
    {
        $this->columnField = $columnField;

        return $this;
    }

    /**
     * @return array|mixed
     */
    public function getColumnAliases()
    {
        return $this->columnAliases;
    }

    /**
     * @param $columnAliases
     * @return $this|mixed
     */
    public function setColumnAliases($columnAliases)
    {
        $this->columnAliases = $columnAliases;

        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        /** @var QueryBuilder|ORMQueryBuilder $queryBuilder */
        $queryBuilder = $this->getFilteredQuery();
        $columns = $this->getRequest()->get("columns");

        /** @var Request $order */
        if ($order = $this->getRequest()->get("order") and isset($order)) {
            foreach ($order as $key => $sort) {
                $column = &$columns[intval($sort['column'])];

                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }

                // Attention Please!
                // "SELECT COUNT(DISTINCT comment_count) as comment_count FROM table"
                // instead of
                // "SELECT COUNT(DISTINCT commentCount) as commentCount FROM table" -- so i love camelCase
                $queryBuilder->addOrderBy(
                    $this->converterUnderlineToDot($column[$this->columnField]),
                    $sort['dir']
                );
            }
        }

        if ($start = intval($this->getRequest()->get("start")) and $start > 0) {
            $queryBuilder->setFirstResult($start);
        }

        if ($length = intval($this->getRequest()->get("length")) and $length > 0) {
            $queryBuilder->setMaxResults($length);
        } else {
            $queryBuilder->setMaxResults($this->defaultLimit);
        }

        return $queryBuilder instanceof ORMQueryBuilder
            ? $queryBuilder->getQuery()->getScalarResult() // getArrayResult
            : $queryBuilder->execute()->fetchAll();
    }

    /**
     * @return QueryBuilder|ORMQueryBuilder
     */
    public function getFilteredQuery()
    {
        /** @var QueryBuilder|ORMQueryBuilder $queryBuilder */
        $queryBuilder = (clone $this->queryBuilder);

        $search  = $this->getRequest()->get('search');
        $columns = $this->getRequest()->get('columns');
        $dataColumns = $this->getRequest()->get('dataColumns');
        $columnCount = $columns ? count($columns) : 0;

        if (
            $columns !== null and $dataColumns !== null and
            $search !== null and $searchValue = trim($search['value'])
        ) {
            /** @var $dataColumns */
            $dataColumns = json_decode($dataColumns, true);
//            $orX = $queryBuilder->expr()->orX();
//            for ($i = 0; $i < $columnCount; $i++) {
//                $column = &$columns[$i];
//                $dataColumn = &$dataColumns[$i];
//
//                if ($column['searchable'] == 'true') {
//                    $orX->add($queryBuilder->expr()
//                        ->like(
//                            $this->findColumnType($dataColumn, $column[$this->columnField]),
//                            ':search'
//                        )
//                    );
//                }
//            }
//            if ($orX->count() >= 1) {
//                $queryBuilder->andWhere($orX)->setParameter('search', "%{$value}%");
//            }

            for ($i = 0; $i < $columnCount; $i++) {
                $column = &$columns[$i];
                $dataColumn = &$dataColumns[$i];

                /** @var $andX */
                $andX = $queryBuilder->expr()->andX();

                if (($column['searchable'] == 'true') and ($value = trim($column['search']['value']))) {
                    // searches the relevant column
                    if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                        $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                    }

                    $andX = $this->searchTerm($queryBuilder, $andX, $dataColumn, $column, $value, $i);

                    $queryBuilder->setParameter("filter_{$i}", "LOWER({$value})");
                } else if (($column['searchable'] == 'true') and strlen($searchValue) > 0) {
                    // searches all columns
                    $andX = $this->searchTerm($queryBuilder, $andX, $dataColumn, $column, $searchValue, $i);

                    $queryBuilder->setParameter("filter_{$i}", "LOWER({$searchValue})");
                }

                if ($andX->count() >= 1) {
                    $queryBuilder->andWhere($andX);
                }
            }
        }

        return $queryBuilder;
    }

    /**
     * @return bool|mixed|string
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRecordsFiltered()
    {
        $query = $this->getFilteredQuery();

        if ($query instanceof ORMQueryBuilder) {
            return $query->resetDQLParts(['select', 'groupBy', 'join'])
                ->select("COUNT({$this->indexColumn})")
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return $query->resetQueryParts(['select', 'groupBy', 'join'])
                ->select("COUNT({$this->indexColumn})")
                ->execute()
                ->fetchColumn(0);
        }
    }

    /**
     * @return int
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRecordsTotal(): int
    {
        $query = (clone $this->queryBuilder);

        if ($query instanceof ORMQueryBuilder) {
            return $query->resetDQLParts(['select', 'groupBy', 'join'])
                ->select("COUNT({$this->indexColumn})")
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return $query->resetQueryParts(['select', 'groupBy', 'join'])
                ->select("COUNT({$this->indexColumn})")
                ->execute()
                ->fetchColumn(0);
        }
    }

    /**
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getResponse()
    {
        return array(
            'data' => $this->getData(),
            'draw' => $this->getRequest()->get("draw"),
            'recordsFiltered' => $this->getRecordsFiltered(),
            'recordsTotal' => $this->getRecordsTotal()
        );
    }

    /**
     * @param QueryBuilder|ORMQueryBuilder $queryBuilder
     * @param $andX
     * @param $dataColumn
     * @param $column
     * @param $value
     * @param $i
     * @return QueryBuilder|ORMQueryBuilder
     */
    private function searchTerm($queryBuilder, $andX, $dataColumn, $column, $value, $i)
    {
        $operator = preg_match('~^\[(?<operator>[=!%<>]+)\].*$~', $value, $matches)
            ? $matches['operator']
            : '=';

        $castColumn = $this->findColumnType($dataColumn, $column[$this->columnField]);

        switch ($operator) {
            case '!=': // Not equals; usage: [!=]search_term
                $andX->add($queryBuilder->expr()->neq($castColumn, ":filter_{$i}"));
                break;
            case '%': // Like; usage: [%]search_term
                $andX->add($queryBuilder->expr()->like($castColumn, ":filter_{$i}"));
                $value = "%{$value}%";
                break;
            case '<': // Less than; usage: [>]search_term
                $andX->add($queryBuilder->expr()->lt($castColumn, ":filter_{$i}"));
                break;
            case '>': // Greater than; usage: [<]search_term
                $andX->add($queryBuilder->expr()->gt($castColumn, ":filter_{$i}"));
                break;
            case '=': // Equals (default); usage: [=]search_term
            default:
                $andX->add($queryBuilder->expr()->eq($castColumn, ":filter_{$i}"));
                break;
        }

        return $andX;
    }

    /**
     * @param array $dataColumn
     * @param string $columnName
     * @return string
     */
    private function findColumnType(array &$dataColumn, string $columnName)
    {
        $columnName = $this->converterUnderlineToDot($columnName);

        if (isset($dataColumn)) {
            switch ($dataColumn["columnType"]) {
                case 'int':
                case 'integer':
                    $columnName = "CAST({$columnName} as int)";
                    break;
                case 'double':
                    $columnName = "CAST({$columnName} as double)";
                    break;
                case 'datetime':
                    $columnName = "DATE_FORMAT({$columnName}, '%Y-%m-%d')";
                    break;
                default:
                    $columnName = "CAST(LOWER({$columnName}) as text)";
                    break;
            }
        }

        return $columnName;
    }

    /**
     * @param string $string
     * @return mixed
     */
    private function converterUnderlineToDot(string $string)
    {
        return str_replace("_", ".", $string);
    }

    /**
     * @param string $name
     * @return string
     */
    private function clearIndexColumnName(string $name)
    {
        return trim(strtolower(str_replace("AppBundle\\Entity\\", "", $name)));
    }
}
