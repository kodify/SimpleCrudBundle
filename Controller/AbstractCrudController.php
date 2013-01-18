<?php

namespace Kodify\SimpleCrudBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

abstract class AbstractCrudController extends Controller
{
    protected $addAction = false;

    /**
     * array with actions, possible values are delete, edit, view
     * @var array
     */
    protected $actions = array('edit');
    protected $indexKey = null;
    protected $controllerName = null;
    protected $entityClass = null;
    protected $formClassName = null;
    protected $formLayout = 'KodifySimpleCrudBundle:CRUD:form.html.twig';
    protected $pageTitle = '';

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $formClass = $this->getEntityForm();        
        $obj = $this->getEntityFromRequest($formClass);
        $form = $this->createForm($formClass, $obj);

        if ($request->isMethod('POST')) {
            $form->bind($request);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($obj);
                $em->flush();

                $this->get('session')->setFlash('success', $formClass->getName() . ' updated successfully');

                return $this->redirect($this->generateUrl('get_' . $this->controllerName));
            } else {
                $this->get('session')->setFlash('error', 'Error saving ' . $formClass->getName());
            }
        }

        return $this->render(
            $this->formLayout,
            array(
                'cancel_url' => $this->generateUrl('get_' . $this->controllerName),
                'form' => $form->createView(),
                'new_object' => ($obj->getId() == null),
                'page_title' => $obj->getName(),
                'form_destination' => 'add_' . $this->controllerName
            )
        );
    }

    public function getEntityFromRequest($formClass)
    {
        $request = $this->get('request');

        $obj_id = null;
        if ($request->get('id')) {
            $obj_id = $request->get('id');
        } else if ($request->isMethod('POST')) {
            $formData = $request->get($formClass->getName());
            $obj_id = $formData['id'];
        }

        if (!empty($obj_id)) {
            $em = $this->getDoctrine()->getManager();
            $obj = $em->getRepository($this->entityClass)->findOneById($obj_id);
        } else {
            $obj = new $this->entityClass;
        }

        return $obj;
    }

    public function getEntityForm()
    {
        return new $this->formClassName;
    }

    /** 
     * @codeCoverageIgnore 
     * @return Response A Response instance
     */
    public function renderTable()
    {
        return parent::render(
            'KodifySimpleCrudBundle:CRUD:list.html.twig',
            $this->getTemplateParams()
        );
    }

    public function getTemplateParams()
    {
        $tableHeader = $this->defineTableHeader();
        $tableRows = $this->getData();
        $totalRows = $this->getTotalRows();
        $sortedIndexes = $this->getHeaderIndexes($tableHeader);

        $tableRows = $this->sortTableRows($sortedIndexes, $tableRows);

        $rowActions = $this->getRowActions($this->actions, $this->controllerName);

        $strFrom = $this->getCurrentPage() * $this->getPageSize() + 1;
        $strTo = $strFrom + $this->getPageSize() - 1;

        $paginator = $this->getPaginator(
            $totalRows,
            $this->getPageSize(),
            $this->getCurrentPage()
        );

        $sort = $this->getSort();
        $current_sort_field = '';
        $current_sort_direction = '';
        if (!empty($sort)) {
            $current_sort_field = key($sort);
            $current_sort_direction = $sort[$current_sort_field]['direction'];
        }

        return array(
            'page_header' => $this->pageTitle,
            'index_key' => $this->indexKey,
            'table_rows' => $tableRows,
            'table_header' => $tableHeader,
            'has_row_actions' => !empty($this->actions),
            'table_row_actions' => $rowActions,
            'sorted_row_indexes' => $sortedIndexes,
            'searchable' => $this->hasSearchableFields($tableHeader),
            'add_action' => $this->addAction,
            'add_action_url' => $this->getAddActionUrl($this->addAction, $this->controllerName),
            'current_filter' => $this->getUsedFilterFields(),
            'current_sort' => $this->getSort(),
            'current_sort_field' => $current_sort_field,
            'current_sort_direction' => $current_sort_direction,
            'current_page_size' => $this->getPageSize(),
            'current_page' => $this->getCurrentPage(),
            'total_rows' => $totalRows,
            'total_pages' => ceil($totalRows / $this->getPageSize()),
            'str_from' => $strFrom,
            'str_to' => min($strTo, $totalRows),
            'paginator_page' => $paginator,
            'paginator_next' => $this->getPaginatorNext(),
            'paginator_prev' => $this->getPaginatorPrev(),
            'page_sizes' => $this->getPageSizes()
        );
    }

    private function getAddActionUrl($addAction, $controllerName)
    {
        $addActionUrl = null;

        if ($addAction) {
            $addRouteName = 'add_'.$controllerName;
            $addActionUrl = $this->container->get('router')->generate($addRouteName);
        }

        return $addActionUrl;
    }

    private function getHeaderIndexes($tableHeader)
    {
        $sortedIndexes = array();
        foreach ($tableHeader as $row) {
            $sortedIndexes[] = $row['key'];
        }

        return $sortedIndexes;
    }

    private function hasSearchableFields($tableHeader)
    {
        foreach ($tableHeader as $row) {
            if (isset($row['filterable']) && $row['filterable']) {
                return true;
            }
        }

        return false;
    }

    private function sortTableRows($sortedIndexes, $tableRows)
    {
        foreach ($tableRows as &$row) {
            $tmpRow = array();
            foreach ($sortedIndexes as $index) {
                if (strpos($index, '.') > 0) {
                    $fields = explode(".", $index);
                    $tmpRow[$index] = $row[strtolower($fields[0])][$fields[1]];
                } else {
                    $tmpRow[$index] = $row[$index];
                }
            }

            $row = $tmpRow;
        }

        return $tableRows;
    }

    private function getRowActions($actions, $controllerName)
    {
        $rowActions = array();

        foreach ($actions as $action) {
            $ico = null;
            $label = null;

            if (is_array($action)) {
                $ico = (isset($action['ico']) ? $action['ico'] : '');
                $label = (isset($action['label']) ? $action['label'] : '');
                $action = $action['route_name'];
            } else {
                switch ($action) {
                    case 'delete':
                        $ico = 'trash';
                        break;
                    case 'edit':
                        $ico = 'edit';
                        break;
                    case 'view':
                        $ico = 'search';
                        break;
                }
            }

            $route = $action.'_'.$controllerName;
            $rowActions[$action] = array(
                'ico' => $ico,
                'label' => $label,
                'url' => $route
            );
        }

        return $rowActions;
    }

    protected function getPageSizes()
    {
        return array(25, 100, 250, 500);
    }

    protected function getPaginatorPrev()
    {
        $priorPage = $this->getCurrentPage() - 1;
        return $priorPage;
    }

    protected function getPaginatorNext()
    {
        $priorPage = $this->getCurrentPage() + 1;
        return $priorPage;
    }

    protected function getPaginator($totalRows, $pageSize, $currentPage)
    {
        $linkPages = array();
        $numPages = ceil($totalRows / $pageSize);
        $total = 0;

        $init = $currentPage - 3;
        if ($init < 0) {
            $init = 0;
        }

        for ($init; $total < 7 && $init < $numPages; $init++) {
            $linkPages[] = $init;
            $total++;
        }

        return $linkPages;
    }

    protected function getPageSize()
    {
        $form = $this->get('request')->get('form');
        $defaultPageSizes = $this->getPageSizes();
        $pageSize = $defaultPageSizes[0];
        if (isset($form['page_size'])) {
            $pageSize = $form['page_size'];
        }

        return $pageSize;
    }

    protected function getCurrentPage()
    {
        $form = $this->get('request')->get('form');
        $currentPage = 0;
        if (isset($form['current_page'])) {
            $currentPage = $form['current_page'];
        }

        if ($currentPage < 0) {
            $currentPage = 0;
        }

        return $currentPage;
    }

    protected function getUsedFilterFields()
    {
        return $this->get('request')->get('filter');
    }

    protected function getDefaultSort()
    {

    }

    protected function getSort()
    {
        $post = $this->get('request')->get('sort');

        if (empty($post)) {
            $default = $this->getDefaultSort();
            if (is_array($default)) {
                foreach ($default as $key => $value) {
                    $post[$key]['field'] = $key;
                    $post[$key]['direction'] = $value;
                }
            }
        } else {
            $tmp = array();

            if (is_array($post) && isset($post['field'])) {
                $tmp[$post['field']]['field'] = $post['field'];
                $tmp[$post['field']]['direction'] = $post['dir'];
                $post = $tmp;
            }
        }

        return $post;
    }

    /** 
     * @codeCoverageIgnore 
     */
    public function getData()
    {
        $repo = $this->getDoctrine()->getManager()->getRepository($this->entityClass);
        return $repo->getRows(
            $this->getUsedFilterFields(),
            $this->getPageSize(),
            $this->getCurrentPage(),
            $this->getSort()
        );
    }

    /** 
     * @codeCoverageIgnore 
     */
    public function getTotalRows()
    {
        $repo = $this->getDoctrine()->getManager()->getRepository($this->entityClass);
        return $repo->getTotalRows(
            $this->getUsedFilterFields()
        );
    }
}
