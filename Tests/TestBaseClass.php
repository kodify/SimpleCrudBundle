<?php

namespace Kodify\SimpleCrudBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Templating\TemplatingExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\Routing\Router;

abstract class TestBaseClass extends KernelTestCase
{
    protected $em = null;


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

        $mockRouter = $this->createPartialMock(Router::class, array('generate'));
        $mockRouter->expects($this->any())
            ->method('generate')
            ->will($this->returnArgument(0));

        $container->set('router', $mockRouter);

        $mockTemplating = $this->createPartialMock(TemplatingExtension::class, array('renderResponse'));
        $container->set('templating', $mockTemplating);

        $mockTemplating = $this->createPartialMock(Form::class, array('create', 'createView'));
        $container->set('form.factory', $mockTemplating);

        $controller->setContainer($container);
    }
}
