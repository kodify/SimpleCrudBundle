<?php
namespace Kodify\SimpleCrudBundle\Repository\Parser;

class SortParser
{
    public static function parseSort($sort, $defaultSort, $query)
    {
        if (!empty($sort)) {
            foreach ($sort as $key => $field) {
                if (!empty($field) && !empty($field['field'])) {
                    if (strpos($field['field'], '.') > 0) {
                        $tmp = explode(".", $field['field']);
                        $query = $query->addOrderBy($tmp[0].'.'.$tmp[1], $field['direction']);
                    } else {
                        $query = $query->addOrderBy('p.'.$field['field'], $field['direction']);
                    }
                }
            }
        }

        if (!empty($defaultSort)) {
            foreach ($defaultSort as $key => $dir) {
                $query = $query->addOrderBy('p.'.$key, $dir);
            }
        }

        return $query;
    }
}