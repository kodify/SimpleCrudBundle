<?php
namespace Kodify\SimpleCrudBundle\Repository\Parser;

class FilterParser
{
    public static function parseFilters($filters, $query)
    {
        if (!empty($filters)) {
            foreach ($filters as $key => $filter) {

                if (self::isValidItemToFilter($filter)) {
                    self::addFilter($filter, $query, $key);
                }
            }
        }

        return $query;
    }

    private static function addFilter($filter, $query, $key)
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
                $query->andWhere($defaultEntity . '.'.$key.' '.$defaultOperator.' (:value_' . $key . ')')
                    ->setParameter('value_' . $key, $filter);
                break;
            case 'left_like':
                $query->andWhere("$defaultEntity.$key LIKE '%$filter'");
                break;
            case 'right_like':
                $query->andWhere("$defaultEntity.$key LIKE '$filter%'");
                break;
            case 'full_like':
                $query->andWhere("$defaultEntity.$key LIKE '%$filter%'");
                break;
            default:
                $query->andWhere($defaultEntity . '.' . $key . ' ' . $defaultOperator . ' :value_' . $key)
                    ->setParameter('value_' . $key, $filter);
        }
    }

    private static function isValidItemToFilter($filter)
    {
        return ((!is_array($filter) && $filter != '') || (is_array($filter) && !empty($filter['value'])));
    }
}