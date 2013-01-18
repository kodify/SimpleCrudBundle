SimpleCrudBundle
================
Simple CRUD bundle for Symfony2 Framework.

What is this Downloader?
------------------------
This bundle aims to solve the gap between using SonataAdminBundle and begin from scratch.


Functionalities:
------------------------
* Grid: Entity list
** Sort
** Filter
** Pagination
** Customizable cell view
* add/edit action from a given Form

Todo:
------------------------
* Auto add/edit form generation
* Export
* Internationalization
* remove $this->indexKey attribute and get from Doctrine Entity


Examples:
================

Controller
-------------------------------------------

<?php

namespace Kodify\AcmeBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Kodify\SimpleCrudBundle\Controller\AbstractCrudController;
use Kodify\SimpleCrudBundle\Controller\CrudControllerInterface;

class CommentController extends AbstractCrudController implements CrudControllerInterface
{
    protected $pageTitle = 'Entity manager';
    protected $controllerName = 'comment';
    protected $entityClass = 'Kodify\AcmeBundle\Entity\Comment';

    /**
     * @Route("/comment/list", name="get_comment")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAction(Request $request)
    {
        $this->indexKey = 'id';//Entity id field
        //$this->addAction = false; //to show or not add input
        $this->actions = array(
            array(
                'route_name' => 'cut',//route_name + $this->controllerName should match an existing route
                'ico' => 'wrench'//http://twitter.github.com/bootstrap/base-css.html#icons
            )
        );

        return $this->renderTable();
    }

    /**
     * @Route("/comment/add", name="add_comment")
     * @Route("/comment/edit", name="edit_comment")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        return parent::addAction($request);
    }

    /**
     * @Route("/comment/acme/{id}", name="cut_comment", requirements={"id"="\d+"})
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function acmeCommentAction($id)
    {
        ...
    }

    /**
     * defines grid columns
     */
    public function defineTableHeader()
    {
        $tableHeader = array(
            array(
                'label' => 'Id',
                'sortable' => true,
                'filterable' => true,
                'default_sort_order' => 'DESC',
                'key' => 'id', //entity field
                'class' => 'input-micro'//input width from bootstrap [input-micro, input-mini, input-smal...]
            ),
            array(
                'label' => 'Original filename',
                'sortable' => true,
                'filterable' => true,
                'key' => 'originalName',
                'filter_operator' => 'RIGHT_LIKE', // LEFT_LIKE '%term', FULL_LIKE '%term%', RIGHT_LIKE 'term%', IN, NOT IN, =, !=, >=... by default =
                'customRowRenderer' => 'AcmeBundle:Comment/Crud:row_original_filename_renderer.html.twig' //Custom cell renderer
            ),
            array(
                'label' => 'Status',
                'sortable' => true,
                'filterable' => true,
                'key' => 'status',
                'type' => 'options'//Select input
                'options' => Comment::getPossibleStatus(),//options for select
            ),
            array(
                'label' => 'Relationed Entity Id',
                'sortable' => true,
                'filterable' => true,
                'key' => 'Post.id',
                'class' => 'input-mini'
            ),
            array(
                'label' => 'Blocked by',
                'sortable' => false,
                'filterable' => false,
                'key' => 'blockedBy'
            )
        );

        return $tableHeader;
    }

    /**
     * default sort for grid
     */
    public function getDefaultSort()
    {
        return array('id' => 'ASC');
    }
}

Repository
-------------------------------------------


Simple Repository
----------------------
<?php
namespace Kodify\AcmeBundle\Repository;

use Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository;

class TagRepository extends AbstractCrudRepository
{

}

Repository to show grid with relationed entities
----------------------

<?php
namespace Kodify\AcmeBundle\Repository;

use Kodify\SimpleCrudBundle\Repository\AbstractCrudRepository;

class ClipRepository extends AbstractCrudRepository
{
    protected $selectEntities = 'p, Post';
    protected $selectLeftJoin = array(array('field' => 'p.test', 'alias' => 'Comment'));
}


Installation
------------
### Composer:

Add the following dependencies to your projects composer.json file:

      "require": {
          "kodify/simplecrudbundle": "dev-master"
      }
