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
                    'shouldReceive' => array('p.key = :value_key'),
                    'parameters' => array(array('value_key', 'filter'))
                ),
                array('key' => 'filter')
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey = :value_tableKey'),
                    'parameters' => array(array('value_tableKey', 'filter'))
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
                    'shouldReceive' => array('table.tableKey LIKE \'%filterValue\''),
                    'parameters' => array()
                ),
                array('table.tableKey' => array('operator' => 'left_like', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey LIKE \'filterValue%\''),
                    'parameters' => array()
                ),
                array('table.tableKey' => array('operator' => 'right_like', 'value' => 'filterValue'))
            ),
            array(
                array(
                    'shouldReceive' => array('table.tableKey LIKE \'%filterValue%\''),
                    'parameters' => array()
                ),
                array('table.tableKey' => array('operator' => 'full_like', 'value' => 'filterValue'))
            )
        );
    }

    /**
     * @dataProvider testDataProvider
     */
    public function testParseFilters($expected, $input)
    {
        $queryMock = M::mock();
        foreach ($expected['shouldReceive'] as $should) {
            $queryMock->shouldReceive('andWhere')->with($should)->once()->andReturn($queryMock);
        }
        foreach ($expected['parameters'] as $parameter) {
            $queryMock->shouldReceive('setParameter')->with($parameter[0], $parameter[1])->once();
        }

        FilterParser::parseFilters($input, $queryMock);
    }
}