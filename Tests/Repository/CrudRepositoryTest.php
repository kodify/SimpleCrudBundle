<?php

namespace Kodify\SimpleCrudBundle\Tests\Repository;

use \Mockery as M;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Scope;

use Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository;
use Kodify\SimpleCrudBundle\Tests\TestBaseClass;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @group crud
 */
class CrudRepository extends TestBaseClass
{
    private function callControllerMethod($methodName, $params = array(), $changeProtectedAttributes = array())
    {
        $foo = self::getMethod('Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository', $methodName);
        $obj = $this->getMockForAbstractClass(
            'Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository',
            array(
                $this->em,
                new ClassMetadata('Test')
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
        $this->assertEquals(count($result->getDQLPart('where')->getParts()), 5);
        $this->assertEquals(count($orderBy), 4);

        $orderPos0 = $orderBy[0]->getParts();
        $this->assertEquals($orderPos0[0], 'p.f1 ASC');
        $orderPos1 = $orderBy[1]->getParts();
        $this->assertEquals($orderPos1[0], 'p.f2 DESC');

        $this->assertEquals($result->getFirstResult(), 55 * 2);
        $this->assertEquals($result->getMaxResults(), 55);
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
        $this->assertEquals(count($where), 1);
        $this->assertEquals($where[0], 'p.id != :value_id');

        $params = $result->getParameters();
        $this->assertEquals(count($params), 1);
        $this->assertEquals($params[0]->getName(), 'value_id');
        $this->assertEquals($params[0]->getValue(), '10');
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
                            'value' => '10',
                        )
                    )
                )
            ),
            array(
                array('id' => array('operator' => 'left_like', 'value' => 'test')),
                array(
                    'qty' => 1,
                    'where' => array(
                        "p.id LIKE '%test'"
                    ),
                    'params_qty' => 0
                )
            ),
            array(
                array('id' => array('operator' => 'right_like', 'value' => 'test')),
                array(
                    'qty' => 1,
                    'where' => array(
                        "p.id LIKE 'test%'"
                    ),
                    'params_qty' => 0
                )
            ),
            array(
                array('id' => array('operator' => 'full_like', 'value' => 'test')),
                array(
                    'qty' => 1,
                    'where' => array(
                        "p.id LIKE '%test%'"
                    ),
                    'params_qty' => 0
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
                        "p.id1 LIKE '%test1'",
                        "p.id2 LIKE 'test2%'",
                        "p.id3 LIKE '%test3%'"
                    ),
                    'params_qty' => 0
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
        $this->assertEquals(count($where), $expected['qty']);
        $this->assertEquals($where[0], $expected['where'][0]);

        $params = $result->getParameters();
        $this->assertEquals(count($params), $expected['params_qty']);

        if ($expected['params_qty'] > 0) {
            $this->assertEquals($params[0]->getName(), $expected['params'][0]['name']);
            $this->assertEquals($params[0]->getValue(), $expected['params'][0]['value']);
        }
    }

    public function testGetQueryWithLeftJoin()
    {
        $leftJoin = array(
            array('field' => 'p.video', 'alias' => 'Video'),
            array('field' => 'p.test2', 'alias' => 'Test')
        );

        $result = $this->callControllerMethod(
            'getQuery',
            array(
                array(
                    'f1', 'f2', 'f3', 'f4', 'f5', 'v.id' => 'f6'
                ),
                55,
                2,
                array(
                    array(
                        'field' => 'v.f1',
                        'direction' => 'ASC'
                    )
                ),
                array(
                    'f2' => 'DESC',
                    'f3' => 'ASC',
                    'f4' => 'ASC'
                )
            ),
            array(
                'selectEntities' => 'p, Video',
                'selectLeftJoin' => $leftJoin
            )
        );

        $orderBy = $result->getDQLPart('orderBy');

        $this->assertTrue($result instanceof \Doctrine\ORM\QueryBuilder);

        $join = $result->getDQLPart('join');
        $this->assertEquals(count($join['p']), 2);
        foreach ($join['p'] as $key => $j) {
            $this->assertEquals($j->getJoinType(), 'LEFT');
            $this->assertEquals($j->getJoin(), $leftJoin[$key]['field']);
            $this->assertEquals($j->getAlias(), $leftJoin[$key]['alias']);
        }

        $this->assertEquals(count($result->getDQLPart('where')->getParts()), 6);
        $this->assertEquals(count($orderBy), 4);

        $orderPos0 = $orderBy[0]->getParts();
        $this->assertEquals($orderPos0[0], 'v.f1 ASC');
        $orderPos1 = $orderBy[1]->getParts();
        $this->assertEquals($orderPos1[0], 'p.f2 DESC');

        $this->assertEquals($result->getFirstResult(), 55 * 2);
        $this->assertEquals($result->getMaxResults(), 55);
    }
}