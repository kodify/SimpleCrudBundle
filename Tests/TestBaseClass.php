<?php

namespace Kodify\SimpleCrudBundle\Tests;

use \Mockery as M;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Scope;

abstract class TestBaseClass extends \PHPUnit_Framework_TestCase
{
    protected $em = null;

    public function setUp()
    {
        $this->em = M::mock('\Doctrine\ORM\EntityManager[close]');
    }

    public function tearDown()
    {
        M::close();
        parent::tearDown();
    }


    /**
     * @param  string $className
     * @param  string $functionName
     *
     * @return ReflectionMethod
     */
    protected function getMethod($className, $functionName)
    {
        $class = new \ReflectionClass($className);
        $method = $class->getMethod($functionName);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * @param string $controller
     * @param array $params
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function setRequest($controller, $params = array())
    {
        $request = new Request();
        $request->initialize(
            $params,
            array(),
            array('_controller' => '')
        );

        $sessionStorage = new MockArraySessionStorage();
        $session = new Session($sessionStorage);

        $request->setSession($session);

        $container = new Container();
        $container->addScope(new Scope('request'));
        $container->enterScope('request');
        $container->set('request', $request, 'request');

        $mockRouter = $this->getMock('Router', array('generate'));
        $mockRouter->expects($this->any())
            ->method('generate')
            ->will($this->returnArgument(0));

        $container->set('router', $mockRouter);

        $mockTemplating = $this->getMock('Templating', array('renderResponse'));
        $container->set('templating', $mockTemplating);

        $mockTemplating = $this->getMock('Form', array('create', 'createView'));
        $container->set('form.factory', $mockTemplating);

        $controller->setContainer($container);
    }
}
