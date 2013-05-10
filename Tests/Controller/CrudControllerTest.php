<?php

namespace Kodify\SimpleCrudBundle\Tests\Controller;

use \Mockery as M;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Scope;

use Kodify\SimpleCrudBundle\Controller\CrudController;
use Kodify\SimpleCrudBundle\Tests\TestBaseClass;

/**
 * @group crud
 */
class CrudControllerTest extends TestBaseClass
{
    private function callControllerMethod($methodName, $params = array(), $request = null, $obj = null)
    {
        $foo = self::getMethod('Kodify\SimpleCrudBundle\Controller\AbstractCrudController', $methodName);
        if ($obj == null) {
            $obj = $this->getMockForAbstractClass('Kodify\SimpleCrudBundle\Controller\AbstractCrudController');
        }

        if ($request != null) {
            $this->setRequest($obj, $request);
        } else {
            $this->setRequest($obj);
        }

        return $foo->invokeArgs($obj, $params);
    }

    public function testSortTableRows()
    {
        $result = $this->callControllerMethod(
            'sortTableRows',
            array(
                array('a', 'b', 'c'),
                array(
                    array(
                        'c' => 1,
                        'b' => 2,
                        'a' => 3,
                    )
                )
            )
        );

        $diff = array_intersect_assoc($result[0], array('a' => 3, 'b' => 2, 'c' => 1));
        $this->assertTrue(count($diff) == 3);
    }

    public function testSortTableRowsLeftJoin()
    {
        $result = $this->callControllerMethod(
            'sortTableRows',
            array(
                array('a', 'b', 'c.d'),
                array(
                    array(
                        'c' => array(
                            'd' => 1
                        ),
                        'b' => 2,
                        'a' => 3,
                    )
                )
            )
        );

        $diff = array_intersect_assoc($result[0], array('a' => 3, 'b' => 2, 'c.d' => 1));
        $this->assertTrue(count($diff) == 3);
    }

    public function paginatorDataProviderCount()
    {
        return array(
            array('1', array(2, 2, 0)),
            array('2', array(4, 2, 0)),
            array('2', array(5, 3, 0)),
            array('3', array(5, 2, 0)),
            array('7', array(100, 2, 0))
        );
    }

    /**
     * @dataProvider paginatorDataProviderCount
     */
    public function testGetPaginatorCount($expected, $data)
    {
        $result = $this->callControllerMethod('getPaginator', $data);
        $this->assertEquals(count($result), $expected);
    }


    public function paginatorDataProviderMath()
    {
        return array(
            array(array(100, 10, 0), array(0, 1, 2, 3, 4, 5, 6)),
            array(array(100, 10, 1), array(0, 1, 2, 3, 4, 5, 6)),
            array(array(100, 10, 5), array(2, 3, 4, 5, 6, 7, 8)),
            array(array(100, 10, 10), array(7, 8, 9)),
        );
    }

    /**
     * @dataProvider paginatorDataProviderMath
     */
    public function testGetPaginatorMatch($data, $expectedData)
    {
        $result = $this->callControllerMethod('getPaginator', $data);
        $diff   = array_intersect($result, $expectedData);
        $this->assertTrue(count($diff) == count($expectedData));
    }

    public function getCurrentPageDataProvider()
    {
        return array(
            array(-1, 0),
            array(0, 0),
            array(1, 1),
            array(10, 10),
            array(null, 0)
        );
    }

    /**
     * @dataProvider getCurrentPageDataProvider
     */
    public function testGetCurrentPage($param, $expected)
    {
        $result = $this->callControllerMethod(
            'getCurrentPage',
            array(),
            array('form' => array('current_page' => $param))
        );

        $this->assertEquals($result, $expected);
    }

    public function getPageSizeDataProvider()
    {
        $result = $this->callControllerMethod('getPageSizes');

        return array(
            array(-1, -1),
            array(0, 0),
            array(1, 1),
            array(10, 10),
            array(null, $result[0])
        );
    }

    /**
     * @dataProvider getPageSizeDataProvider
     */
    public function testGetPageSize($param, $expected)
    {
        $form = array('form' => array('page_size' => $param));

        $result = $this->callControllerMethod('getPageSize', array(), $form);
        $this->assertEquals($result, $expected);
    }

    public function testGetSort()
    {
        $form   = array('sort' => 'test');
        $result = $this->callControllerMethod('getSort', array(), $form);
        $this->assertEquals($result, 'test');
    }

    public function testGetSortWithPost()
    {
        $form   = array('sort' => array('field' => 'id', 'dir' => 'ASC'));
        $result = $this->callControllerMethod('getSort', array(), $form);
        $this->assertEquals($result, array('id' => array('field' => 'id', 'direction' => 'ASC')));
    }

    public function testGetUsedFilterFields()
    {
        $form   = array('filter' => 'test');
        $result = $this->callControllerMethod('getUsedFilterFields', array(), $form);
        $this->assertEquals($result, 'test');
    }

    public function testGetPaginatorNext()
    {
        $form   = array('form' => array('current_page' => 1));
        $result = $this->callControllerMethod('getPaginatorNext', array(), $form);
        $this->assertEquals($result, 2);
    }

    public function testGetPaginatorPrev()
    {
        $form   = array('form' => array('current_page' => 1));
        $result = $this->callControllerMethod('getPaginatorPrev', array(), $form);
        $this->assertEquals($result, 0);
    }

    public function testGetPageSizes()
    {
        $result = $this->callControllerMethod('getPageSizes', array());
        $this->assertTrue(is_array($result));
    }

    public function testGetRowActions()
    {
        $result = $this->callControllerMethod('getRowActions', array(array('delete', 'edit', 'view'), 'test'));
        $this->assertTrue(is_array($result));

        foreach ($result as $action) {
            $this->assertArrayHasKey('ico', $action);
            $this->assertArrayHasKey('url', $action);
        }
    }

    public function testCustomGetRowActions()
    {
        $result = $this->callControllerMethod(
            'getRowActions',
            array(
                array(
                    array(
                        'ico'        => 'testIco',
                        'label'      => 'testLabel',
                        'route_name' => 'testRoute'
                    )
                ),
                'test'
            )
        );
        $this->assertTrue(is_array($result));

        foreach ($result as $action) {
            $this->assertArrayHasKey('ico', $action);
            $this->assertEquals($action['ico'], 'testIco');
            $this->assertArrayHasKey('label', $action);
            $this->assertEquals($action['label'], 'testLabel');

        }
    }

    public function hasSearchableFieldsDataProvider()
    {
        return array(
            array(array(
                array('filterable' => true),
                array('filterable' => false)),
                true),
            array(array(
                array('filterable' => false),
                array('filterable' => false)),
                false),
            array(array(), false)
        );
    }

    /**
     * @dataProvider hasSearchableFieldsDataProvider
     */
    public function testHasSearchableFields($params, $expected)
    {
        $result = $this->callControllerMethod('hasSearchableFields', array($params));
        $this->assertEquals($result, $expected);
    }

    public function testGetHeaderIndexes()
    {
        $params = array(
            array(
                'tmp' => 'value1',
                'key' => 'value1'
            ),
            array(
                'tmp2' => 'value1',
                'key'  => 'value2'
            )
        );

        $result = $this->callControllerMethod('getHeaderIndexes', array($params));

        $diff = array_intersect($result, array('value1', 'value2'));
        $this->assertTrue(count($diff) == 2);
    }

    public function getAddActionUrlDataProvider()
    {
        return array(
            array(array(true, 'controller1'), 'add_controller1'),
            array(array(false, ''), null)
        );
    }

    /**
     * @dataProvider getAddActionUrlDataProvider
     */
    public function testGetAddActionUrl($params, $expected)
    {
        $result = $this->callControllerMethod('getAddActionUrl', $params);
        $this->assertEquals($result, $expected);
    }


    public function testGetTemplateParams()
    {
        $stub = $this->getMockBuilder('Kodify\SimpleCrudBundle\Controller\AbstractCrudController')
            ->setMethods(
                array(
                    'defineTableHeader',
                    'getData',
                    'getTotalRows',
                    'getPageSize'
                )
            )
            ->getMock();

        $stub->expects($this->any())->method('defineTableHeader')
            ->will($this->returnValue(array()));

        $stub->expects($this->any())->method('getData')
            ->will($this->returnValue(array()));

        $stub->expects($this->any())->method('getTotalRows')
            ->will($this->returnValue(100));

        $stub->expects($this->any())->method('getPageSize')
            ->will($this->returnValue(10));

        $this->setRequest($stub);

        $result = $stub->getTemplateParams();

        $indexList = array('page_header', 'index_key', 'table_rows',
            'table_header', 'has_row_actions', 'table_row_actions',
            'sorted_row_indexes', 'searchable', 'add_action', 'add_action_url',
            'current_filter', 'current_sort', 'current_page_size', 'current_page',
            'total_rows', 'total_pages', 'str_from', 'str_to', 'paginator_page',
            'paginator_next', 'paginator_prev', 'page_sizes');

        foreach ($indexList as $index) {
            $this->assertArrayHasKey($index, $result);
        }

        $this->assertEquals($result['total_rows'], 100);
        $this->assertEquals($result['str_from'], 1);
        $this->assertEquals($result['str_to'], 10);
        $this->assertEquals($result['total_pages'], 10);
    }

    /**
     * @group addAction
     */
    public function testGetActionEmpty()
    {
        $mockRequest = M::mock('Symfony\Component\HttpFoundation\Request[isMethod]');
        $mockRequest->shouldReceive('isMethod')->once()->andReturn(false);

        $mockForm = M::mock('Symfony\Component\Form\Form[createView,getName]')
            ->shouldReceive('createView')->once()->andReturn('view')
            ->shouldReceive('getName')->times(1)->andReturn('forn name')
            ->getMock();

        $mockEntity = M::mock()
            ->shouldReceive('getName')->times(0)->andReturn('entity name')
            ->shouldReceive('getId')->once()->andReturn(1)
            ->getMock();

        $mockFormObject = M::mock();

        $methods    = 'getEntityForm,getEntityFromRequest,createForm,generateUrl,render';
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[' . $methods . ']');
        $controller->shouldReceive('getEntityForm')->once();
        $controller->shouldReceive('createForm')->andReturn($mockForm);
        $controller->shouldReceive('generateUrl')->once()->andReturn('url');
        $controller->shouldReceive('render')->once();
        $controller->shouldReceive('getEntityFromRequest')->once()->andReturn($mockEntity);

        $controller->addAction($mockRequest);

        $this->assertTrue(true, 'If we arrive here everything was called in the correct order');
    }

    public function testGetActionInvalid()
    {
        $mockRequest = M::mock('Symfony\Component\HttpFoundation\Request[isMethod]');
        $mockRequest->shouldReceive('isMethod')->once()->andReturn(true);

        $mockForm = M::mock('Symfony\Component\Form\Form[getName,createView,bind,isValid]')
            ->shouldReceive('createView')->once()->andReturn('view')
            ->shouldReceive('isValid')->once()->andReturn(false)
            ->shouldReceive('bind')->once()->with($mockRequest)
            ->shouldReceive('getName')->once()->andReturn('form name')
            ->getMock();

        $mockEntity = M::mock()
            ->shouldReceive('getName')->times(0)->andReturn('entity name')
            ->shouldReceive('getId')->once()->andReturn(1)
            ->getMock();

        $mockFlashBag = M::mock();
        $mockFlashBag->shouldReceive('add')->once()->with('error', 'Error saving test');

        $mockSession = M::mock();
        $mockSession->shouldReceive('getFlashBag')->andReturn($mockFlashBag);


        $mockFormObject = M::mock();
        $mockFormObject->shouldReceive('getName')->once()->andReturn('test');

        $methods    = 'getEntityForm,getEntityFromRequest,createForm,generateUrl,render,get';
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[' . $methods . ']');
        $controller->shouldReceive('getEntityForm')->once()->andReturn($mockFormObject);
        $controller->shouldReceive('createForm')->andReturn($mockForm);
        $controller->shouldReceive('generateUrl')->once()->andReturn('url');
        $controller->shouldReceive('render')->once();
        $controller->shouldReceive('getEntityFromRequest')->once()->andReturn($mockEntity);
        $controller->shouldReceive('get')->once()->andReturn($mockSession);

        $controller->addAction($mockRequest);

        $this->assertTrue(true, 'If we arrive here everything was called in the correct order');
    }

    public function testGetActionValid()
    {
        $mockRequest = M::mock('Symfony\Component\HttpFoundation\Request[isMethod]');
        $mockRequest->shouldReceive('isMethod')->once()->andReturn(true);

        $mockForm = M::mock('Symfony\Component\Form\Form[createView,bind,isValid]');
        $mockForm->shouldReceive('createView')->never()->andReturn('view');
        $mockForm->shouldReceive('isValid')->once()->andReturn(true);
        $mockForm->shouldReceive('bind')->once()->with($mockRequest);

        $mockEntity = M::mock();
        $mockEntity->shouldReceive('getName')->never()->andReturn('entity name');

        $mockFlashBag = M::mock();
        $mockFlashBag->shouldReceive('add')->once()->with('success', 'test updated successfully');

        $mockSession = M::mock();
        $mockSession->shouldReceive('getFlashBag')->andReturn($mockFlashBag);

        $mockFormObject = M::mock();
        $mockFormObject->shouldReceive('getName')->once()->andReturn('test');

        $mockManager = M::mock();
        $mockManager->shouldReceive('persist')->once()->with($mockEntity);
        $mockManager->shouldReceive('flush')->once();

        $mockDoctrine = M::mock();
        $mockDoctrine->shouldReceive('getManager')->once()->andReturn($mockManager);

        $methods    = 'getEntityForm,getEntityFromRequest,createForm,generateUrl,render,get,getDoctrine';
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[' . $methods . ']');
        $controller->shouldReceive('getEntityForm')->once()->andReturn($mockFormObject);
        $controller->shouldReceive('createForm')->andReturn($mockForm);
        $controller->shouldReceive('generateUrl')->once()->andReturn('url');
        $controller->shouldReceive('render')->never();
        $controller->shouldReceive('getEntityFromRequest')->once()->andReturn($mockEntity);
        $controller->shouldReceive('get')->once()->andReturn($mockSession);
        $controller->shouldReceive('getDoctrine')->once()->andReturn($mockDoctrine);

        $controller->addAction($mockRequest);

        $this->assertTrue(true, 'If we arrive here everything was called in the correct order');
    }

    public function testGetEntityFromRequestEmptyRequest()
    {
        $mockRequest = M::mock('Symfony\Component\HttpFoundation\Request[get,isMethod]');
        $mockRequest->shouldReceive('isMethod')->once()->andReturn(false);
        $mockRequest->shouldReceive('get')->once()->andReturn(null);

        $methods    = 'getDoctrine, get';
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[' . $methods . ']');
        $controller->shouldReceive('get')->once()->andReturn($mockRequest);

        $refl    = new \ReflectionObject($controller);
        $message = $refl->getProperty('entityClass');
        $message->setAccessible(true);
        $message->setValue($controller, 'stdClass');

        $result = $this->callControllerMethod('getEntityFromRequest', array(null), null, $controller);
        $this->assertInstanceOf('\stdClass', $result);
    }

    public function testGetEntityFromRequestWithId()
    {
        $methods    = 'getDoctrine,get';
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[' . $methods . ']');

        $mockEntityRepo = M::mock();
        $mockEntityRepo->shouldReceive('findOneById')->once()->andReturn(new \stdClass());

        $mockManager = M::mock();
        $mockManager->shouldReceive('getRepository')->once()->andReturn($mockEntityRepo);

        $mockDoctrine = M::mock();
        $mockDoctrine->shouldReceive('getManager')->once()->andReturn($mockManager);

        $mockRequest = M::mock('Symfony\Component\HttpFoundation\Request[get,isMethod]');
        $mockRequest->shouldReceive('isMethod')->never()->andReturn(true);
        $mockRequest->shouldReceive('get')->times(2)->andReturn(1);

        $controller->shouldReceive('getDoctrine')->once()->andReturn($mockDoctrine);
        $controller->shouldReceive('get')->once()->andReturn($mockRequest);

        $mockFormClass = M::mock();
        $mockFormClass->shouldReceive('getName')->never()->andReturn('test');

        $result = $controller->getEntityFromRequest($mockFormClass);
        $this->assertInstanceOf('\stdClass', $result);
    }

    public function testGetEntityFromRequestWithFormId()
    {
        $methods    = 'getDoctrine,get';
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[' . $methods . ']');

        $mockEntityRepo = M::mock();
        $mockEntityRepo->shouldReceive('findOneById')->once()->andReturn(new \stdClass());

        $mockManager = M::mock();
        $mockManager->shouldReceive('getRepository')->once()->andReturn($mockEntityRepo);

        $mockDoctrine = M::mock();
        $mockDoctrine->shouldReceive('getManager')->once()->andReturn($mockManager);

        $returnGet = function ($tmp) {
            if ($tmp != 'id') {

                return $tmp;
            }
        };

        $mockRequest = M::mock('Symfony\Component\HttpFoundation\Request[get,isMethod]');
        $mockRequest->shouldReceive('isMethod')->once()->andReturn(true);
        $mockRequest->shouldReceive('get')->times(2)->andReturnUsing($returnGet);

        $controller->shouldReceive('getDoctrine')->once()->andReturn($mockDoctrine);
        $controller->shouldReceive('get')->once()->andReturn($mockRequest);


        $mockFormClass = M::mock();
        $mockFormClass->shouldReceive('getName')->once()->andReturn(array('id' => 10));

        $result = $controller->getEntityFromRequest($mockFormClass);
        $this->assertInstanceOf('\stdClass', $result);
    }

    public function testGetEntityForm()
    {
        $controller = M::mock('Kodify\SimpleCrudBundle\Controller\AbstractCrudController[]');

        $refl    = new \ReflectionObject($controller);
        $message = $refl->getProperty('formClassName');
        $message->setAccessible(true);
        $message->setValue($controller, 'stdClass');

        $result = $controller->getEntityForm();
        $this->assertInstanceOf('\stdClass', $result);
    }
}
