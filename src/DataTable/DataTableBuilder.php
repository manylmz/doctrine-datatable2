<?php

namespace AppBundle\Service\DataTables;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\QueryBuilder as ORMQueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DataTableBuilder
 * @package AppBundle\Service
 */
class DataTableBuilder implements DataTableInterface
{
    /**
     * @var array
     */
    protected $columnAliases = array();

    /**
     * @var string
     */
    protected $columnField = 'data';

    /**
     * @var string
     */
    protected $indexColumn = '*';

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var array
     */
    protected $request;

    /**
     * @return DataTableBuilder
     */
    public static function factory()
    {
        return new self();
    }

    /**
     * @return array
     */
    public function getData()
    {
        $queryBuilder = $this->getFilteredQuery();
        $columns = &$this->request['columns'];

        // Order
        if (array_key_exists('order', $this->request)) {
            $order = &$this->request['order'];
            foreach ($order as $sort) {
                $column = &$columns[intval($sort['column'])];

                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }

                $queryBuilder->addOrderBy($column[$this->columnField], $sort['dir']);
            }
        }

        // Offset
        if (array_key_exists('start', $this->request)) {
            $queryBuilder->setFirstResult(intval($this->request['start']));
        }

        // Limit
        if (array_key_exists('length', $this->request)) {
            $length = intval($this->request['length']);

            if ($length > 0) {
                $queryBuilder->setMaxResults($length);
            }
        }

        // Fetch
        return $queryBuilder instanceof ORMQueryBuilder
            ? $queryBuilder->getQuery()->getScalarResult()
            : $queryBuilder->execute()->fetchAll();
    }

    /**
     * @return QueryBuilder
     */
    public function getFilteredQuery()
    {
        $queryBuilder = (clone $this->queryBuilder);

        $columns = &$this->request['columns'];
        $columnCount = count($columns);

        // Search
        if (array_key_exists('search', $this->request)) {
            if ($value = trim($this->request['search']['value'])) {
                $orX = $queryBuilder->expr()->orX();

                for ($i = 0; $i < $columnCount; $i++) {
                    $column = &$columns[$i];

                    if ($column['searchable'] == 'true') {
                        if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                            $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                        }

                        $orX->add($queryBuilder->expr()->like($column[$this->columnField], ':search'));
                    }
                }

                if ($orX->count() >= 1) {
                    $queryBuilder->andWhere($orX)
                        ->setParameter('search', "%{$value}%");
                }
            }
        }

        // Filter
        for ($i = 0; $i < $columnCount; $i++) {
            $column = &$columns[$i];
            $andX = $queryBuilder->expr()->andX();

            if (($column['searchable'] == 'true') && ($value = trim($column['search']['value']))) {
                if (array_key_exists($column[$this->columnField], $this->columnAliases)) {
                    $column[$this->columnField] = $this->columnAliases[$column[$this->columnField]];
                }

                $operator = preg_match('~^\[(?<operator>[=!%<>]+)\].*$~', $value, $matches) ? $matches['operator'] : '=';

                switch ($operator) {
                    case '!=': // Not equals; usage: [!=]search_term
                        $andX->add($queryBuilder->expr()->neq($column[$this->columnField], ":filter_{$i}"));
                        break;
                    case '%': // Like; usage: [%]search_term
                        $andX->add($queryBuilder->expr()->like($column[$this->columnField], ":filter_{$i}"));
                        $value = "%{$value}%";
                        break;
                    case '<': // Less than; usage: [>]search_term
                        $andX->add($queryBuilder->expr()->lt($column[$this->columnField], ":filter_{$i}"));
                        break;
                    case '>': // Greater than; usage: [<]search_term
                        $andX->add($queryBuilder->expr()->gt($column[$this->columnField], ":filter_{$i}"));
                        break;
                    case '=': // Equals (default); usage: [=]search_term
                    default:
                        $andX->add($queryBuilder->expr()->eq($column[$this->columnField], ":filter_{$i}"));
                        break;
                }

                $queryBuilder->setParameter("filter_{$i}", $value);
            }

            if ($andX->count() >= 1) {
                $queryBuilder->andWhere($andX);
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
            return $query->resetDQLParts(['select', 'groupBy'])
                ->select("COUNT({$this->indexColumn})")
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return $query->resetQueryParts(['select', 'groupBy'])
                ->select("COUNT({$this->indexColumn})")
                ->execute()
                ->fetchColumn(0);
        }
    }

    /**
     * @return bool|int|mixed|string
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRecordsTotal()
    {
        $query = clone $this->queryBuilder;

        if ($query instanceof ORMQueryBuilder) {
            return $query->resetDQLParts(['select', 'groupBy'])
                ->select("COUNT({$this->indexColumn})")
                ->getQuery()
                ->getSingleScalarResult();
        } else {
            return $query->resetQueryParts(['select', 'groupBy'])
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
            'draw' => $this->request['draw'],
            'recordsFiltered' => $this->getRecordsFiltered(),
            'recordsTotal' => $this->getRecordsTotal(),
        );
    }

    /**
     * @param $indexColumn
     * @return $this
     */
    public function withIndexColumn($indexColumn)
    {
        $this->indexColumn = $indexColumn;

        return $this;
    }

    /**
     * @param $columnAliases
     * @return $this
     */
    public function withColumnAliases($columnAliases)
    {
        $this->columnAliases = $columnAliases;

        return $this;
    }

    /**
     * @param $columnField
     * @return $this
     */
    public function withColumnField($columnField)
    {
        $this->columnField = $columnField;

        return $this;
    }

    /**
     * @param QueryBuilder|ORMQueryBuilder $queryBuilder
     * @return $this
     */
    public function withQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * @param Request $request
     * @return $this|mixed
     */
    public function withRequest(Request $request)
    {
        $this->request = $request->query->all();

        return $this;
    }
}
