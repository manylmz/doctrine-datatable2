<?php

namespace AppBundle\Service\DataTables;

use AppBundle\Entity\Product;
use AppBundle\Service\PredisService;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package AppBundle\Service\DataTables
 * @author Osman Yılmaz <osman@hayalmahsulleri.com.tr>
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
     * @var array
     */
    private $additionalData = [];

    /**
     * @var
     */
    private $redisData = [];

    /**
     * @var PredisService
     */
    private $redisService;

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
    public function getRequest(): Request
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
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * @param array $additionalData
     * @return $this
     */
    public function setAdditionalData(array $additionalData)
    {
        $this->additionalData = $additionalData;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRedisData()
    {
        return $this->redisData;
    }

    /**
     * @param mixed ...$redisData
     * @return $this
     */
    public function setRedisData(...$redisData)
    {
        $this->redisData = $redisData;

        return $this;
    }

    /**
     * @return PredisService
     */
    public function getRedisService()
    {
        return $this->redisService;
    }

    /**
     * @param PredisService $redisService
     */
    public function setRedisService(PredisService $redisService)
    {
        $this->redisService = $redisService;
    }

    /**
     * @return array
     * @throws \Exception
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
                // Attention Please!
                // "SELECT COUNT(DISTINCT comment_count) as comment_count FROM table"
                // instead of
                // "SELECT COUNT(DISTINCT commentCount) as commentCount FROM table" -- so i love camelCase
                $queryBuilder->addOrderBy(
                    $this->converterBottomLine($column[$this->columnField]),
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

        // if you have a query problem, yeah man pls -.-  dump SQL
        return $queryBuilder instanceof ORMQueryBuilder
            ? $queryBuilder->getQuery()->getScalarResult()
            : $queryBuilder->execute()->fetchAll();
    }

    /**
     * @return QueryBuilder|ORMQueryBuilder
     * @throws \Exception
     */
    public function getFilteredQuery()
    {
        /** @var QueryBuilder|ORMQueryBuilder $queryBuilder */
        $queryBuilder = (clone $this->queryBuilder);
        /** @var $search */
        $search = $this->getRequest()->get('search');
        /** @var $columns */
        $columns = $this->getRequest()->get('columns');
        /** @var $dataColumns */
        $dataColumns = $this->getRequest()->get('dataColumns');

        if (
            $columns !== null and $dataColumns !== null and
            $search !== null and $searchValue = trim($search['value'])
        ) {
            /** @var $dataColumns */
            $dataColumns = json_decode($dataColumns, true);

            /** @var $orX */
            $orX = $queryBuilder->expr()->orX();

            foreach ($columns as $key => $column) {
                /** @var $nativeColumn */
                $nativeColumn = &$dataColumns[$key];

                if (
                    ($column['searchable'] == 'true') and
                    (trim($column['search']['value']) || strlen(trim($searchValue)) > 0)
                ) {
                    $searchText  = (strlen($searchValue) > 0) ? $searchValue : trim($column['search']['value']);
                    $searchText  = str_replace(["%", "'", '"'], ["", "''", '""'], $searchText);
                    $columnType  = isset($nativeColumn['columnType']) ? $nativeColumn['columnType'] : null;
                    $columnRegex = isset($nativeColumn["columnRegex"]) ? $nativeColumn["columnRegex"] : "=";

                    /** @var $operator */
                    $operator = preg_match('~^\[(?<operator>[=!%<>]+)\].*$~', $searchText, $matches)
                        ? $matches['operator']
                        : (strlen($columnRegex) > 0 ? $columnRegex : '=');

                    /** @var $fetchColumn */
                    $fetchColumn = $this->converterColumnType(
                        $nativeColumn,
                        $column[$this->columnField],
                        $searchText
                    );

                    if (in_array($columnType, ['int', 'integer'])) {
                        // Equals (default); usage: [=]search_term, there is for just integer column
                        if (is_numeric($searchText)) {
                            $orX->add($queryBuilder->expr()->eq($fetchColumn, intval($searchText)));
                        }
                    } elseif (in_array($columnType, ['date', 'datetime'])) {
                        if (\DateTime::createFromFormat('Y-m-d', $searchText) !== false) {
                            $orX->add($queryBuilder
                                ->expr()
                                ->eq("DATE_FORMAT({$fetchColumn}, '%Y-%m-%d')", "'{$searchText}'")
                            );
                        } else {
                            $orX->add($queryBuilder->expr()->like($fetchColumn, "LOWER('%{$searchText}%')"));
                        }
                    } elseif ($columnType === 'price') {
                        if (is_numeric($searchText)) {
                            $orX->add($queryBuilder->expr()->eq($fetchColumn, intval($searchText)));
                        }
                    } elseif ($operator === '!=') {
                        // Not equals; usage: [!=]search_term
                        $orX->add($queryBuilder->expr()->neq($fetchColumn, $searchText));
                    } elseif ($operator === '%') {
                        // Like; usage: [%]search_term
                        $orX->add($queryBuilder->expr()->like($fetchColumn, "LOWER('%{$searchText}%')"));
                    } elseif ($operator === '<') {
                        // Less than; usage: [>]search_term
                        $orX->add($queryBuilder->expr()->lt($fetchColumn, $searchText));
                    } elseif ($operator === '>') {
                        // Greater than; usage: [<]search_term
                        $orX->add($queryBuilder->expr()->gt($fetchColumn, $searchText));
                    } else {
                        // Equals (default); usage: [=]search_term
                        $orX->add($queryBuilder->expr()->eq($fetchColumn, "'{$searchText}'"));
                    }
                }
            }

            $queryBuilder->andWhere($orX);
        }

        return $queryBuilder;
    }

    /**
     * @return bool|mixed|string
     * @throws \Doctrine\ORM\NonUniqueResultException|\Exception
     */
    public function getRecordsFiltered()
    {
        $query = clone $this->getFilteredQuery();

        if ($query instanceof ORMQueryBuilder) {
            return $query->resetDQLParts(['select', 'groupBy'])
                ->select("COUNT(DISTINCT {$this->indexColumn})")
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return $query->resetQueryParts(['select', 'groupBy'])
                ->select("COUNT(DISTINCT {$this->indexColumn})")
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
            return $query->resetDQLParts(['select', 'groupBy'])
                ->select("COUNT(DISTINCT {$this->indexColumn})")
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return $query->resetQueryParts(['select', 'groupBy'])
                ->select("COUNT(DISTINCT {$this->indexColumn})")
                ->execute()
                ->fetchColumn(0);
        }
    }

    /**
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException|\Exception
     */
    public function getResponse()
    {
        $data = array(
            'data' => $this->getData(),
            'draw' => $this->getRequest()->get("draw"),
            'recordsFiltered' => $this->getRecordsFiltered(),
            'recordsTotal' => $this->getRecordsTotal()
        );

        if (!empty($this->getAdditionalData()) and sizeof($this->getAdditionalData()) > 0) {
            $data = array_merge(['additional' => $this->getAdditionalData()], $data);
        }

        if (!empty($this->getRedisData()) and sizeof($this->getRedisData()) > 0) {
            $data = array_merge(['redis' => $this->fetchAllRedisData()], $data);
        }

        return $data;
    }

    /**
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getJsonResponse()
    {
        return new Response(json_encode($this->getResponse()));
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function fetchAllRedisData()
    {
        $redisData = [];
        $redisService = $this->getRedisService();
        $data = $this->getRedisData();

        foreach ($data as $key => $value) {
            if ($this->getRedisService()->exists($value)) {
                $redisData[$value] = (clone $redisService)->hGetAll($value);
            }
        }

        return $redisData;
    }

    /**
     * @param array $column
     * @param string $columnName
     * @param string $value
     * @return mixed|string
     */
    private function converterColumnType(array &$column, string $columnName, string $value)
    {
        $columnName = $this->converterBottomLine($columnName);

        if (!empty($column) and isset($column["columnType"])) {
            switch ($column["columnType"]) {
                case 'int':
                case 'integer':
                    $columnName = "CAST({$columnName} as INTEGER)";
                    break;
                case 'float':
                case 'double':
                    $columnName = "CAST({$columnName} as INTEGER)";
                    break;
                case 'price':
                    $columnName = "DECIMAL({$columnName})";
                    break;
                case 'bool':
                case 'boolean':
                    $isTrue = $value === Product::ACTIVE ? "TRUE" : "FALSE";
                    $columnName = "COALESCE({$columnName}, FALSE) = {$isTrue}";
                    break;
                case 'datetime':
                    if (!\DateTime::createFromFormat('Y-m-d', $value)) {
                        $columnName = "DATE_FORMAT({$columnName}, '%Y-%m-%d')";
                    } else {
                        continue;
                    }
                    break;
                default:
                    $columnName = !is_numeric($columnName) ? "LOWER({$columnName})" : $columnName;
                    $columnName = "CAST({$columnName} as TEXT)";
                    break;
            }
        }

        return $columnName;
    }

    /**
     * @param string $string
     * @return mixed
     */
    private function converterBottomLine(string $string)
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
