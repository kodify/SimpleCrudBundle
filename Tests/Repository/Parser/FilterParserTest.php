<?php

namespace Kodify\SimpleCrudBundle\Tests\Repository\Parser;

use Kodify\SimpleCrudBundle\Repository\Parser\FilterParser;
use Kodify\SimpleCrudBundle\Tests\TestBaseClass;
use \Mockery as M;

/**
 * @group crud
 */
class FilterParserTest extends TestBaseClass
{
    public function testDataProvider()
    {
        return array(
            array(
                array(
                    'shouldReceive' => array('p.key = :value_pkey' . md5('=')),
                    'parameters' => array(array('value_pkey' . md5('='), 'filter'))
                ),
                array('key' => 'filter')
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey = :value_tabletableKey' . md5('=')),
                    'parameters' => array(array('value_tabletableKey' . md5('='), 'filter'))
                ),
                array('table.tableKey' => 'filter')
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey in (:value_tableKey)'),
                    'parameters' => array(array('value_tableKey', array('filterValue')))
                ),
                array('table.tableKey' => array('operator' => 'in', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey LIKE :term_table_tableKey'),
                    'parameters' => array(['term_table_tableKey', '%filterValue'])
                ),
                array('table.tableKey' => array('operator' => 'left_like', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey LIKE :term_table_tableKey'),
                    'parameters' => array(['term_table_tableKey', 'filterValue%'])
                ),
                array('table.tableKey' => array('operator' => 'right_like', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey LIKE :term_table_tableKey'),
                    'parameters' => array(['term_table_tableKey', '%filterValue%'])
                ),
                array('table.tableKey' => array('operator' => 'full_like', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'shouldReceive' => array(
                        'p.key = :value_pkey' . md5('='),
                        'p.key != :value_pkey' . md5('!='),
                    ),
                    'parameters' => array(
                        array('value_pkey' . md5('='), '1'),
                        array('value_pkey' . md5('!='), '2')
                    )
                ),
                array('key' => array(array('value' => '1', 'operator' => '='), array('value' => '2', 'operator' => '!=')))
            ),
            array(
                array(),
                array(array())
            )
        );
    }

    /**
     * @dataProvider testDataProvider
     */
    public function testParseFilters($expected, $input)
    {
        $queryMock = M::mock();
        if (isset($expected['shouldReceive'])) {
            foreach ($expected['shouldReceive'] as $should) {
                $queryMock->shouldReceive('andWhere')->with($should)->once()->andReturn($queryMock);
            }
        }
        if (isset($expected['parameters'])) {
            foreach ($expected['parameters'] as $parameter) {
              $queryMock->shouldReceive('setParameter')->with($parameter[0], $parameter[1])->once();
            }
        }

        FilterParser::parseFilters($input, $queryMock);
    }


    public function testDataProviderIsNull()
    {
        return array(
            array(
                array(
                    'parameter' => 'filterValue',
                    'operator'  => 'isNull'
                ),
                array('table.tableKey' => array('operator' => 'is null', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'parameter' => 'filterValue',
                    'operator'  => 'isNotNull'
                ),
                array('table.tableKey' => array('operator' => 'is not null', 'value' => 'filterValue'))
            ),
        );
    }
    /**
     * @dataProvider testDataProviderIsNull
     */
    public function testParseFiltersIsNULL($expected, $input)
    {
        $queryMock = M::mock();

        $exprMock = M::mock();
        $exprMock->shouldReceive($expected['operator'])->with()->once()->andReturn($exprMock);

        $queryMock->shouldReceive('expr')->once()->andReturn($exprMock);
        $queryMock->shouldReceive('andWhere')->with($exprMock)->once();


        FilterParser::parseFilters($input, $queryMock);
    }
}
