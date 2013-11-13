<?php

namespace Kodify\SimpleCrudBundle\Tests\Repository\Parser;

use Kodify\SimpleCrudBundle\Repository\Parser\SortParser;
use Kodify\SimpleCrudBundle\Tests\TestBaseClass;
use \Mockery as M;

/**
 * @group crud
 */
class SortParserTest extends TestBaseClass
{
    public function testWithSort()
    {
        $sort = array(
            array('field' => 'table.field', 'direction' => 'asc'),
            array('field' => 'table.field2', 'direction' => 'desc'),
            array('field' => 'field3', 'direction' => 'asc')
        );

        $defaultSort = array(
            'fieldOne' => 'dirOne',
            'fieldTwo' => 'dirTwo',
        );

        $queryMock = M::mock();
        $queryMock->shouldReceive('addOrderBy')->with('table.field', 'asc')->once();
        $queryMock->shouldReceive('addOrderBy')->with('table.field2', 'desc')->once();
        $queryMock->shouldReceive('addOrderBy')->with('p.field3', 'asc')->once();

        $queryMock->shouldReceive('addOrderBy')->with('p.fieldOne', 'dirOne')->once();
        $queryMock->shouldReceive('addOrderBy')->with('p.fieldTwo', 'dirTwo')->once();

        SortParser::parseSort($sort, $defaultSort, $queryMock);
    }
}