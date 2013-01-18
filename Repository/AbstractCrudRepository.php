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
        $paginator = new Paginator($this->getQuery($filters, $pageSize, $currentPage));
        return count($paginator);
    }

    public function getQuery($filters = array(), $pageSize = 25, $currentPage = 0, $sort = null, $defaultSort = null)
    {
        $query = $this->createQueryBuilder('p')
            ->select($this->selectEntities)
            ->setMaxResults($pageSize)
            ->setFirstResult($currentPage * $pageSize);

        if (is_array($this->selectLeftJoin)) {
            foreach ($this->selectLeftJoin as $join) {
                $query->leftJoin($join['field'], $join['alias']);
            }
        }

        Parser\FilterParser::parseFilters($filters, $query);
        Parser\SortParser::parseSort($sort, $defaultSort, $query);

        return $query;
    }
}
