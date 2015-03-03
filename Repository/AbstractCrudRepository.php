<?php
namespace Kodify\SimpleCrudBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder,
    Doctrine\ORM\Query,
    Doctrine\ORM\Query\ResultSetMapping,
    Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\CountWalker;

use DoctrineExtensions\Paginate\Paginate;

abstract class AbstractCrudRepository extends EntityRepository
{
    protected $selectEntities       = 'p';
    protected $selectLeftJoin       = null;
    protected $selectInnerJoin      = null;
    protected $useFieldsToSelect    = false;
    protected $useCustomCounter     = false;

    public function __construct($em, ClassMetadata $class, $selectEntities = null, $selectLeftJoin = null)
    {
        $this->_entityName = $class->name;
        $this->_em         = $em;
        $this->_class      = $class;

        if ($selectEntities != null) {
            $this->selectEntities = $selectEntities;
        }
        if ($selectLeftJoin != null) {
            $this->selectLeftJoin = $selectLeftJoin;
        }
    }

    public function getRows($filters = array(), $pageSize = 3, $currentPage = 0, $sort = null, $defaultSort = null, $fields = null)
    {
        $query = $this->getQuery($filters, $pageSize, $currentPage, $sort, $defaultSort, $fields);

        return $query->getQuery()->getArrayResult();
    }

    public function getTotalRows($filters = array(), $pageSize = 25, $currentPage = 0, $fields = null)
    {
        $query = $this->createQueryBuilder('p');
        if ($fields != null && $this->useFieldsToSelect) {
            $query->select('p, ' . implode(',', $fields));
        } else {
            $query->select($this->selectEntities);
        }

        if (is_array($this->selectLeftJoin)) {
            $query->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            $this->getQueryForSelectInnerJoin($query);
            $this->getQueryForSelectLeftJoin($query);

            Parser\FilterParser::parseFilters($filters, $query);
        } else if (is_array($this->selectInnerJoin)) {
            $query->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            $this->getQueryForSelectInnerJoin($query);

            Parser\FilterParser::parseFilters($filters, $query);
        } else {
            $query = $this->getQuery($filters, $pageSize, $currentPage);
        }

        return $this->countQuery($query);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getAllRowsId($filters = array())
    {
        $query = $this->createQueryBuilder('p')
            ->select('partial p.{id}');

        $this->getQueryForSelectLeftJoin($query);
        Parser\FilterParser::parseFilters($filters, $query);

        return $query->getQuery()->getArrayResult();
    }

    /**
     * @codeCoverageIgnore
     */
    public function countQuery($query)
    {
        if (!$this->useCustomCounter) {

            return count(new Paginator($query));
        } else {
            $count = Paginate::getTotalQueryResults($query->getQuery());

            return $count;
        }
    }

    public function getQuery($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null, $fields = null)
    {
        $query = $this->createQueryBuilder('p');
        if ($fields != null && $this->useFieldsToSelect) {
            $query->select('DISTINCT ' . implode(',', $fields));
        } else {
            $query->select($this->selectEntities);
        }

        $this->getQueryForSelectInnerJoin($query);
        $this->getQueryForSelectLeftJoin($query);
        $this->getQueryForGroupBy($query);

        $query->setMaxResults($pageSize)
            ->setFirstResult($currentPage * $pageSize);
        Parser\FilterParser::parseFilters($filters, $query);

        Parser\SortParser::parseSort($sort, $defaultSort, $query);

        return $query;
    }

    /**
     * @codeCoverageIgnore
     */
    private function cloneQuery(Query $query)
    {
        $cloneQuery = clone $query;
        $cloneQuery->setParameters(clone $query->getParameters());

        foreach ($query->getHints() as $name => $value) {
            $cloneQuery->setHint($name, $value);
        }

        return $cloneQuery;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getQueryForSelectInnerJoin($query)
    {
        if (is_array($this->selectInnerJoin)) {
            foreach ($this->selectInnerJoin as $join) {
                $query->innerJoin($join['field'], $join['alias']);
            }
        }

    }

    /**
     * @codeCoverageIgnore
     */
    protected function getQueryForSelectLeftJoin($query)
    {
        if (is_array($this->selectLeftJoin)) {
            foreach ($this->selectLeftJoin as $join) {
                $query->leftJoin($join['field'], $join['alias']);
            }
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function setSelectEntities($selectEntities = 'p')
    {
        $this->selectEntities = $selectEntities;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setUseFieldsToSelect($use = true)
    {
        $this->useFieldsToSelect = (bool) $use;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setUseCustomCounter($use = true)
    {
        $this->useCustomCounter = (bool) $use;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setSelectInnerJoin($join)
    {
        $this->selectInnerJoin = $join;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setSelectLeftJoin($join)
    {
        $this->selectLeftJoin = $join;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getQueryForGroupBy($query)
    {
        if (isset($this->groupBy)) {
            $query->groupBy($this->groupBy);
        }
    }
    /**
     * @codeCoverageIgnore
     */
    public function setGroupBy($groupBy)
    {
        $this->groupBy = $groupBy;
    }
}
