<?php
namespace Kodify\SimpleCrudBundle\Repository\Parser;

class FilterParser
{
    public static function parseFilters($filters, $query)
    {
        if (!empty($filters)) {
            foreach ($filters as $key => $filter) {

                if (self::isValidItemToFilter($filter)) {
                    $defaultOperator = '=';
                    $defaultEntity = 'p';

                    if (is_array($filter)) {
                        $defaultOperator = $filter['operator'];
                        $filter = $filter['value'];
                    }

                    $defaultOperator = strtolower($defaultOperator);

                    if (strpos($key, '.') > 0) {
                        $tmp = explode(".", $key);
                        $key = str_replace('.', '___', $key);

                        $defaultEntity = $tmp[0];
                        $key = $tmp[1];
                    }

                    switch ($defaultOperator) {
                        case 'in':
                        case 'not in':
                            $query = $query
                                ->andWhere($defaultEntity . '.'.$key.' '.$defaultOperator.' (:value_' . $key . ')')
                                ->setParameter('value_' . $key, $filter);
                            break;
                        case 'left_like':
                            $query = $query
                                ->andWhere("$defaultEntity.$key LIKE '%$filter'");
                            break;
                        case 'right_like':
                            $query = $query
                                ->andWhere("$defaultEntity.$key LIKE '$filter%'");
                            break;
                        case 'full_like':
                            $query = $query
                                ->andWhere("$defaultEntity.$key LIKE '%$filter%'");
                            break;
                        default:
                            $query = $query
                                ->andWhere($defaultEntity . '.'.$key.' '.$defaultOperator.' :value_' . $key)
                                ->setParameter('value_' . $key, $filter);
                    }
                }
            }
        }

        return $query;
    }

    private function isValidItemToFilter($filter)
    {
        return ((!is_array($filter) && $filter != '') || (is_array($filter) && !empty($filter['value'])));
    }
}