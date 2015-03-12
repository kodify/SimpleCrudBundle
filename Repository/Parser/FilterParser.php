<?php
namespace Kodify\SimpleCrudBundle\Repository\Parser;

class FilterParser
{
    public static function parseFilters($filters, $query)
    {
        if (!empty($filters)) {
            foreach ($filters as $key => $filter) {



                if (self::isValidItemToFilter($filter)) {
                    if (is_array($filter) && !isset($filter['value'])) {
                        foreach ($filter as $subFilter) {
                            self::addFilter($subFilter, $query, $key);
                        }
                    } else {
                        self::addFilter($filter, $query, $key);

                        if (isset($filter['add_filter'])) {
                            self::orFilter($filter, $query, $filter['add_filter']);
                        }
                    }
                }
            }
        }

        return $query;
    }

    protected static function addFilter($filter, $query, $key)
    {
        $defaultOperator = '=';
        $defaultEntity = 'p';

        if (is_array($filter)) {
            $defaultOperator = $filter['operator'];
            $filter = $filter['value'];
        }

        $defaultOperator = strtolower($defaultOperator);

        if (strpos($key, '.') > 0) {
            $tmp = explode('.', $key);
            $defaultEntity = $tmp[0];
            $key = $tmp[1];
        }

        switch ($defaultOperator) {
            case 'in':
            case 'not in':
                if (!is_array($filter)) {
                    $filter = array_map('trim', explode(',', $filter));
                }

                $query->andWhere($defaultEntity . '.'.$key.' '.$defaultOperator.' (:value_' . $key . ')')
                    ->setParameter('value_' . $key, $filter);
                break;
            case 'left_like':
                $query->andWhere("$defaultEntity.$key LIKE :term_{$defaultEntity}_$key")->setParameter("term_{$defaultEntity}_$key", '%'.$filter);
                break;
            case 'right_like':
                $query->andWhere("$defaultEntity.$key LIKE :term_{$defaultEntity}_$key")->setParameter("term_{$defaultEntity}_$key", $filter . '%');
                break;
            case 'full_like':
                $query->andWhere("$defaultEntity.$key LIKE :term_{$defaultEntity}_$key")->setParameter("term_{$defaultEntity}_$key", '%' . $filter . '%');
                break;
            case 'is null':
                $query->andWhere($query->expr()->isNull("$defaultEntity.$key"));
                break;
            case 'is not null':
                $query->andWhere($query->expr()->isNotNull("$defaultEntity.$key"));
                break;
            case 'is null field':
                $query->andWhere($query->expr()->isNull($defaultEntity . '.' . $key));
                break;
            case 'is not null field':
                $query->andWhere($query->expr()->isNotNull($defaultEntity . '.' . $key));
                break;
            case 'having_like':
                $query->having('GROUP_CONCAT(' . $defaultEntity . '.' . $key . ') LIKE :term_' . $defaultEntity . '_' . $key)->setParameter("term_{$defaultEntity}_$key", '%'.$filter.'%');
                break;
            default:
                $query->andWhere($defaultEntity . '.' . $key . ' ' . $defaultOperator . ' :value_' . $defaultEntity . $key . md5($defaultOperator))
                    ->setParameter('value_' . $defaultEntity . $key  . md5($defaultOperator), $filter);
        }
    }

    protected static function orFilter($filter, $query, $key)
    {
        $defaultOperator = '=';
        $defaultEntity = 'p';

        if (is_array($filter)) {
            $defaultOperator = $filter['operator'];
            $filter = $filter['value'];
        }

        $defaultOperator = strtolower($defaultOperator);

        if (strpos($key, '.') > 0) {
            $tmp = explode('.', $key);
            $defaultEntity = $tmp[0];
            $key = $tmp[1];
        }

        switch ($defaultOperator) {
            case 'in':
            case 'not in':
                if (!is_array($filter)) {
                    $filter = array_map('trim', explode(',', $filter));
                }

                $query->orWhere($defaultEntity . '.'.$key.' '.$defaultOperator.' (:value_' . $key . ')')
                    ->setParameter('value_' . $key, $filter);
                break;
            case 'left_like':
                $query->orWhere("$defaultEntity.$key LIKE '%$filter'");
                break;
            case 'right_like':
                $query->orWhere("$defaultEntity.$key LIKE '$filter%'");
                break;
            case 'full_like':
                $query->orWhere("$defaultEntity.$key LIKE '%$filter%'");
                break;
            case 'is null':
                $query->orWhere($query->expr()->isNull('VideoWebsite.scheduledFor'));
                break;
            case 'is not null':
                $query->orWhere($query->expr()->isNotNull('VideoWebsite.scheduledFor'));
                break;
            case 'is null field':
                $query->andWhere($query->expr()->isNull($defaultEntity . '.' . $key));
                break;
            case 'is not null field':
                $query->andWhere($query->expr()->isNotNull($defaultEntity . '.' . $key));
                break;
            case 'having_like':
                $query->having('GROUP_CONCAT(' . $defaultEntity . '.' . $key . ') LIKE :term_'.$key)->setParameter("term_$key", '%'.$filter.'%');
                break;
            default:
                $query->orWhere($defaultEntity . '.' . $key . ' ' . $defaultOperator . ' :value_' . $key . md5($defaultOperator))
                    ->setParameter('value_' . $key  . md5($defaultOperator), $filter);
        }
    }

    protected static function isValidItemToFilter($filter)
    {
        if (is_array($filter) && empty($filter['value'])) {
            foreach ($filter as $subFilter) {

                return self::isValidItemToFilter($subFilter);
            }
        }

        return ((!is_array($filter) && $filter != '') || (is_array($filter) && !empty($filter['value'])));
    }
}
