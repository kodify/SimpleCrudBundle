<?php

namespace Kodify\SimpleCrudBundle\Tests\Repository;

use \Mockery as M;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\DependencyInjection\Container;

use Kodify\SimpleCrudBundle\Tests\TestBaseClass;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @group crud
 */
class CrudRepository extends TestBaseClass
{
    private function callControllerMethod($methodName, $params = array(), $changeProtectedAttributes = array())
    {
        $classMetadata =  new ClassMetadata('Test');
        $classMetadata->setIdentifier(array('id'));

        $foo = self::getMethod('Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository', $methodName);
        $obj = $this->getMockForAbstractClass(
            'Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository',
            array(
                $this->em,
                $classMetadata
            )
        );

        if (is_array($changeProtectedAttributes) && !empty($changeProtectedAttributes)) {
            $refl = new \ReflectionObject($obj);
            foreach ($changeProtectedAttributes as $attr => $value) {
                $message = $refl->getProperty($attr);
                $message->setAccessible(true);
                $message->setValue($obj, $value);
            }
        }

        return $foo->invokeArgs($obj, $params);
    }

    public function testGetRows()
    {
        $expectedResponse = array('hello' => 'world');

        $mockQueryR = M::mock();
        $mockQueryR->shouldReceive('getArrayResult')->once()->andReturn($expectedResponse);

        $mockQuery = M::mock();
        $mockQuery->shouldReceive('getQuery')->once()->andReturn($mockQueryR);

        $repo = M::mock('Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository[getQuery]');
        $repo->shouldReceive('getQuery')->once()->andReturn($mockQuery);

        $response = $repo->getRows();
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGetQuery()
    {
        $result = $this->callControllerMethod(
            'getQuery',
            array(
                array(
                    'f1', 'f2', 'f3', 'f4', 'f5'
                ),
                55,
                2,
                array(
                    array(
                        'field' => 'f1',
                        'direction' => 'ASC'
                    )
                ),
                array(
                    'f2' => 'DESC',
                    'f3' => 'ASC',
                    'f4' => 'ASC'
                )
            )
        );


        $orderBy = $result->getDQLPart('orderBy');

        $this->assertTrue($result instanceof \Doctrine\ORM\QueryBuilder);
        $this->assertEquals(5, count($result->getDQLPart('where')->getParts()));
        $this->assertEquals(4, count($orderBy));

        $orderPos0 = $orderBy[0]->getParts();
        $this->assertEquals('p.f1 ASC', $orderPos0[0]);
        $orderPos1 = $orderBy[1]->getParts();
        $this->assertEquals('p.f2 DESC', $orderPos1[0]);

        $this->assertEquals(55 * 2, $result->getFirstResult());
        $this->assertEquals(55, $result->getMaxResults());
    }

    public function testGetQueryWithOperator()
    {
        $result = $this->callControllerMethod(
            'getQuery',
            array(
                array(
                    'id' => array(
                        'operator' => '!=',
                        'value' => '10',
                    )
                )
            )
        );

        $where = $result->getDQLPart('where')->getParts();
        $this->assertCount(1, $where);
        $this->assertEquals('p.id != :value_pid' . md5('!='), $where[0]);

        $params = $result->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('value_pid' . md5('!='), $params[0]->getName());
        $this->assertEquals('10', $params[0]->getValue());
    }


    public function operationsDataProvider()
    {
        return array(
            array(
                array('id' => array('operator' => 'in', 'value' => '10')),
                array(
                    'qty' => 1,
                    'where' => array(
                        'p.id in (:value_id)'
                    ),
                    'params_qty' => 1,
                    'params' => array(
                        array(
                            'name' => 'value_id',
                            'value' => array('10'),
                        )
                    )
                )
            ),
            array(
                array('id' => array('operator' => 'left_like', 'value' => 'test')),
                array(
                    'qty' => 1,
                    'where' => array(
                        "p.id LIKE :term_p_id"
                    ),
                    'params_qty' => 1,
                    'params' => array(
                        array(
                            'name' => 'term_p_id',
                            'value' => "%test",
                        )
                    )
                )
            ),
            array(
                array('id' => array('operator' => 'right_like', 'value' => 'test')),
                array(
                    'qty' => 1,
                    'where' => array(
                        "p.id LIKE :term_p_id"
                    ),
                    'params_qty' => 1,
                    'params' => array(
                        array(
                            'name' => 'term_p_id',
                            'value' => "test%",
                        )
                    )
                )
            ),
            array(
                array('id' => array('operator' => 'full_like', 'value' => 'test')),
                array(
                    'qty' => 1,
                    'where' => array(
                        "p.id LIKE :term_p_id"
                    ),
                    'params_qty' => 1,
                    'params' => array(
                        array(
                            'name' => 'term_p_id',
                            'value' => "%test%",
                        )
                    )
                )
            ),
            array(
                array(
                    'id1' => array('operator' => 'left_like', 'value' => 'test1'),
                    'id2' => array('operator' => 'right_like', 'value' => 'test2'),
                    'id3' => array('operator' => 'full_like', 'value' => 'test3'),
                ),
                array(
                    'qty' => 3,
                    'where' => array(
                        "p.id1 LIKE :term_p_id1",
                        "p.id2 LIKE :term_p_id2",
                        "p.id3 LIKE :term_p_id3",
                    ),
                    'params_qty' => 3,
                    'params' => array(
                        array(
                            'name' => 'term_p_id1',
                            'value' => "%test1",
                        ),
                        array(
                            'name' => 'term_id2',
                            'value' => "test2%",
                        ),
                        array(
                            'name' => 'term_id3',
                            'value' => "%test3%",
                        ),
                    )
                )
            ),
        );
    }

    /**
     * @dataProvider operationsDataProvider
     */
    public function testGetQueryWithOperations($input, $expected)
    {
        $result = $this->callControllerMethod('getQuery', array($input));

        $where = $result->getDQLPart('where')->getParts();
        $this->assertCount($expected['qty'], $where);
        $this->assertEquals($expected['where'][0], $where[0]);

        $params = $result->getParameters();
        $this->assertCount($expected['params_qty'], $params);

        if ($expected['params_qty'] > 0) {
            $this->assertEquals($expected['params'][0]['name'], $params[0]->getName());
            $this->assertEquals($expected['params'][0]['value'], $params[0]->getValue());
        }
    }

    public function testGetTotalRows()
    {
        $classMetadata =  new ClassMetadata('Test');
        $classMetadata->setIdentifier(array('id'));

        $repo = M::mock(
            'Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository[countQuery,getQuery]',
            array(
                $this->em,
                $classMetadata,
            )
        );

        $mockQueryBuilder =  M::mock();

        $repo->shouldReceive('countQuery')->with($mockQueryBuilder)->once()->andReturn(10);
        $repo->shouldReceive('getQuery')->once()->andReturn($mockQueryBuilder);

        $result = $repo->getTotalRows(array(), 99, 10);
        $this->assertEquals(10, $result);
    }

    public function testGetTotalRowsLeftJoin()
    {
        $classMetadata =  new ClassMetadata('Test');
        $classMetadata->setIdentifier(array('id'));


        $mockQueryBuilder =  M::mock();
        $mockQueryBuilder->shouldReceive('select')->once()->with('p')->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('setMaxResults')->once()->with(99)->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('setFirstResult')->once()->with(990)->andReturn($mockQueryBuilder);

        $mockQueryBuilder->shouldReceive('leftJoin')->once()->with('fieldOne', 'aliasOne');
        $mockQueryBuilder->shouldReceive('leftJoin')->once()->with('fieldTwo', 'aliasTwo');

        $repo = M::mock(
            'Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository[countQuery,createQueryBuilder]',
            array(
                $this->em,
                $classMetadata,
                'p',
                array(
                    'p' => array('field' => 'fieldOne', 'alias' => 'aliasOne'),
                    'j' => array('field' => 'fieldTwo', 'alias' => 'aliasTwo'),
                )
            )
        );
        $repo->shouldReceive('countQuery')->once()->andReturn(10);
        $repo->shouldReceive('createQueryBuilder')->once()->andReturn($mockQueryBuilder);

        $result = $repo->getTotalRows(array(), 99, 10);
        $this->assertEquals(10, $result);
    }

    public function testGetTotalRowsLeftJoinWithFilters()
    {
        $classMetadata =  new ClassMetadata('Test');
        $classMetadata->setIdentifier(array('id'));


        $mockQueryBuilder =  M::mock();
        $mockQueryBuilder->shouldReceive('select')->once()->with('p')->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('setMaxResults')->once()->with(99)->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('setFirstResult')->once()->with(990)->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('andWhere')->andReturn($mockQueryBuilder);
        $mockQueryBuilder->shouldReceive('setParameter')->andReturn($mockQueryBuilder);

        $mockQueryBuilder->shouldReceive('leftJoin')->once()->with('fieldOne', 'aliasOne');
        $mockQueryBuilder->shouldReceive('leftJoin')->once()->with('fieldTwo', 'aliasTwo');

        $repo = M::mock(
            'Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository[countQuery,createQueryBuilder]',
            array(
                $this->em,
                $classMetadata,
                'p',
                array(
                    'p' => array('field' => 'fieldOne', 'alias' => 'aliasOne'),
                    'j' => array('field' => 'fieldTwo', 'alias' => 'aliasTwo'),
                )
            )
        );
        $repo->shouldReceive('countQuery')->once()->andReturn(10);
        $repo->shouldReceive('createQueryBuilder')->once()->andReturn($mockQueryBuilder);

        $filters = [ 'supu.tamadre' => 'ola k ase' ];

        $result = $repo->getTotalRows($filters, 99, 10);
        $this->assertEquals(10, $result);
    }
}
