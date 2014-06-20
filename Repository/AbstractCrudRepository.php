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

            foreach ($this->selectInnerJoin as $join) {
                $query->innerJoin($join['field'], $join['alias']);
            }

            foreach ($this->selectLeftJoin as $join) {
                $query->leftJoin($join['field'], $join['alias']);
            }

            Parser\FilterParser::parseFilters($filters, $query);
        } else if (is_array($this->selectInnerJoin)) {
            $query->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            foreach ($this->selectInnerJoin as $join) {
                $query->innerJoin($join['field'], $join['alias']);
            }

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

        if (is_array($this->selectLeftJoin)) {
            foreach ($this->selectLeftJoin as $join) {
                $query->leftJoin($join['field'], $join['alias']);
            }
        }

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
            $countQuery = $this->cloneQuery($query->getQuery());
            $countQuery->setHint(Query::HINT_CUSTOM_TREE_WALKERS, array('Doctrine\ORM\Tools\Pagination\CountWalker'));

            try {
                $data =  $countQuery->getScalarResult();
                $data = array_map('current', $data);
                $count = array_sum($data);
            } catch (NoResultException $e) {
                $count = 0;
            }

            return $count;
        }
    }

    public function getQuery($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null, $fields = null)
    {
        $query = $this->createQueryBuilder('p');
        if ($fields != null && $this->useFieldsToSelect) {
            $query->select(implode(',', $fields));
        } else {
            $query->select($this->selectEntities);
        }

        if (is_array($this->selectLeftJoin)) {
            $this->getQueryForSelectLeftJoin($filters, $pageSize, $currentPage, $sort, $defaultSort, $query);
            Parser\FilterParser::parseFilters($filters, $query);
        } else {
            if (is_array($this->selectInnerJoin)) {
                $this->getQueryForSelectInnerJoin($filters, $pageSize, $currentPage, $sort, $defaultSort, $query);
            }
            $query->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            Parser\FilterParser::parseFilters($filters, $query);
        }

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
    protected function getQueryForSelectInnerJoin($filters, $pageSize, $currentPage, $sort, $defaultSort, $query)
    {
        foreach ($this->selectInnerJoin as $join) {
            $query->innerJoin($join['field'], $join['alias']);
        }
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getQueryForSelectLeftJoin($filters, $pageSize, $currentPage, $sort, $defaultSort, $query)
    {
        $identifiers = ($this->getClassMetadata()->getIdentifier());
        $queryToRetrieveIds = $this->createQueryBuilder('p')
            ->select('p.' . $identifiers[0])
            ->setMaxResults($pageSize)
            ->setFirstResult($currentPage * $pageSize);

        foreach ($this->selectInnerJoin as $join) {
            $query->innerJoin($join['field'], $join['alias']);
            $queryToRetrieveIds->innerJoin($join['field'], $join['alias']);
        }

        foreach ($this->selectLeftJoin as $join) {
            $query->leftJoin($join['field'], $join['alias']);
            $queryToRetrieveIds->leftJoin($join['field'], $join['alias']);
        }

        Parser\FilterParser::parseFilters($filters, $queryToRetrieveIds);
        Parser\SortParser::parseSort($sort, $defaultSort, $queryToRetrieveIds);

        $queryToRetrieveIds->groupBy('p.' . $identifiers[0]);

        $selectedEntities = $queryToRetrieveIds->getQuery()->expireQueryCache(true)->getArrayResult();
        $ids = array();
        foreach ($selectedEntities as $entity) {
            $ids[] = $entity[$identifiers[0]];
        }

        if (empty($ids)) {
            $query->andWhere('1 != 1');
        } else {
            $query->andWhere('p.' . $identifiers[0] . ' IN (:ids_list)')
                ->setParameter('ids_list', $ids);
        }
    }
}
