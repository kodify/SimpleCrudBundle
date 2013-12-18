<?php
namespace Kodify\SimpleCrudBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Mapping\ClassMetadata;

abstract class AbstractCrudRepository extends EntityRepository
{
    protected $selectEntities = 'p';
    protected $selectLeftJoin = null;

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

    public function getRows($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null)
    {
        $query = $this->getQuery($filters, $pageSize, $currentPage, $sort, $defaultSort);

        return $query->getQuery()->getArrayResult();
    }

    public function getTotalRows($filters = array(), $pageSize = 25, $currentPage = 0)
    {
        if (is_array($this->selectLeftJoin)) {

            $query = $this->createQueryBuilder('p')
                ->select('p')
                ->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            foreach ($this->selectLeftJoin as $join) {
                $query->leftJoin($join['field'], $join['alias']);
            }

            Parser\FilterParser::parseFilters($filters, $query);
        } else {
            $query = $this->getQuery($filters, $pageSize, $currentPage);
        }

        return $this->countQuery($query);
    }

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
        return count(new Paginator($query));
    }

    public function getQuery($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null)
    {

        $query = $this->createQueryBuilder('p')
            ->select($this->selectEntities);

        if (is_array($this->selectLeftJoin)) {
            $this->getQueryForSelectLeftJoin($filters, $pageSize, $currentPage, $sort, $defaultSort, $query);
        } else {
            $query->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            Parser\FilterParser::parseFilters($filters, $query);
        }

        Parser\SortParser::parseSort($sort, $defaultSort, $query);

        return $query;
    }

    /**
     * @codeCoverageIgnore
     * @param $filters
     * @param $pageSize
     * @param $currentPage
     * @param $sort
     * @param $defaultSort
     * @param $query
     */
    protected function getQueryForSelectLeftJoin($filters, $pageSize, $currentPage, $sort, $defaultSort, $query)
    {
        $identifiers = ($this->getClassMetadata()->getIdentifier());
        $queryToRetrieveIds = $this->createQueryBuilder('p')
            ->select('p.' . $identifiers[0])
            ->setMaxResults($pageSize)
            ->setFirstResult($currentPage * $pageSize);

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
