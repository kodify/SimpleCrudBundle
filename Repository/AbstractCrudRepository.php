<?php
namespace Kodify\SimpleCrudBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

abstract class AbstractCrudRepository extends EntityRepository
{
    protected $selectEntities = 'p';
    protected $selectLeftJoin = null;

    public function getRows($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null)
    {
        $query = $this->getQuery($filters, $pageSize, $currentPage, $sort, $defaultSort);

        return $query->getQuery()->getArrayResult();
    }

    public function getTotalRows($filters = array(), $pageSize = 25, $currentPage = 0)
    {
        $identifiers = ($this->getClassMetadata()->getIdentifier());

        if (is_array($this->selectLeftJoin)) {
            $query = $this->createQueryBuilder('p')
                ->select('p')
                ->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            Parser\FilterParser::parseFilters($filters, $query);
        } else {
            $query = $this->getQuery($filters, $pageSize, $currentPage);
        }

        return count(new Paginator($query));
    }

    public function getQuery($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null)
    {
        $query = $this->createQueryBuilder('p')
            ->select($this->selectEntities);

        if (is_array($this->selectLeftJoin)) {
            foreach ($this->selectLeftJoin as $join) {
                $query->leftJoin($join['field'], $join['alias']);
            }

            $identifiers = ($this->getClassMetadata()->getIdentifier());

            $queryToRetrieveIds = $this->createQueryBuilder('p')
                ->select('p.' . $identifiers[0])
                ->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            Parser\FilterParser::parseFilters($filters, $queryToRetrieveIds);
            Parser\SortParser::parseSort($sort, $defaultSort, $queryToRetrieveIds);

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
        } else {
            $query->setMaxResults($pageSize)
                ->setFirstResult($currentPage * $pageSize);

            Parser\FilterParser::parseFilters($filters, $query);
        }

        Parser\SortParser::parseSort($sort, $defaultSort, $query);

        return $query;
    }
}
