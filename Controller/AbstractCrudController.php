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
    protected $addAction        = false;
    protected $flashMessages    = true;

    /**
     * array with actions, possible values are delete, edit, view
     * @var array
     */
    protected $generalActions   = array();
    protected $actions          = array('edit');
    protected $massActions      = array();
    protected $indexKey         = null;
    protected $indexKeyAction   = null;
    protected $controllerName   = null;
    protected $entityClass      = null;
    protected $formClassName    = null;
    protected $formLayout       = 'KodifySimpleCrudBundle:CRUD:form.html.twig';
    protected $listLayout       = 'KodifySimpleCrudBundle:CRUD:list.html.twig';

    protected $pageTitle = '';
    protected $obj = null;

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request, $destinationUrl = null)
    {
        $formClass = $this->getEntityForm();
        $obj       = $this->getEntityFromRequest($formClass);
        $form      = $this->createForm($formClass, $obj);

        if ($request->isMethod('POST')) {
            $form->bind($request);

            if ($this->validateForm($form, $obj)) {
                try {
                    $this->prePersist($obj);
                    $this->persist($obj);
                    $this->postPersist($obj);

                    if ($this->flashMessages) {
                        $this->get('session')->getFlashBag()->add('success', $formClass->getName() . ' updated successfully');
                    }
                    $this->obj = $obj;

                    return $this->redirect($this->postAddRedirectTo());
                } catch (\Exception $e) {
                    $this->get('logger')->err($e);
                    $this->get('session')->getFlashBag()->add('error', 'Error saving ' . $formClass->getName());
                }
            } else {
                $this->get('session')->getFlashBag()->add('error', 'Error saving ' . $formClass->getName());
            }
        }

        if (null == $destinationUrl) {
            $destinationUrl = 'post_add_' . $this->controllerName;
        }

        return $this->render(
            $this->formLayout,
            array_merge(
                array(
                'cancel_url'       => $this->postAddRedirectTo(),
                'form'             => $form->createView(),
                'formObj'          => $form,
                'new_object'       => ($obj->getId() == null),
                'object'           => $obj,
                'page_title'       => $form->getName(),
                'form_destination' => $destinationUrl,
                ), $this->getAdditionalFormParameters($obj)
            )
        );
    }

    protected function getAdditionalFormParameters($obj)
    {
        return [];
    }

    protected function validateForm($form, $obj = null)
    {
        return $form->isValid();
    }

    /**
     * @param $obj
     */
    protected function persist($obj)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($obj);
        $em->flush();
    }

    public function getEntityFromRequest($formClass)
    {
        $request = $this->get('request');

        $objId = null;
        if ($request->get('id')) {
            $objId = $request->get('id');
        } else if ($request->isMethod('POST')) {
            $formData = $request->get($formClass->getName());
            $objId    = $formData['id'];
        }

        if (!empty($objId)) {
            $em  = $this->getDoctrine()->getManager();
            $obj = $em->getRepository($this->entityClass)->findOneById($objId);
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
            $this->listLayout,
            $this->getTemplateParams()
        );
    }

    public function getTemplateParams()
    {
        $tableHeader   = $this->defineTableHeader();
        $tableRows     = $this->getData();

        $totalRows     = $this->getTotalRows();
        $sortedIndexes = $this->getHeaderIndexes($tableHeader);

        $tableRows = $this->sortTableRows($sortedIndexes, $tableRows);

        $rowActions = $this->getRowActions($this->actions, $this->controllerName);

        $strFrom = $this->getCurrentPage() * $this->getPageSize() + 1;
        $strTo   = $strFrom + $this->getPageSize() - 1;

        $paginator = $this->getPaginator(
            $totalRows,
            $this->getPageSize(),
            $this->getCurrentPage()
        );

        $sort                 = $this->getSort();
        $currentSortField     = '';
        $currentSortDirection = '';
        if (!empty($sort)) {
            $currentSortField     = key($sort);
            $currentSortDirection = $sort[$currentSortField]['direction'];
        }
        $massActions        = $this->getMassActions();
        $massActionAllIds   = '';
        $massActionIdsCount = 0;
        if (count($massActions) > 0 && $this->massActionsApplyToAllPages($massActions)) {
            $tmpAllIds          = $this->getAllRowsId();
            $massActionAllIds   = '';
            $massActionIdsCount = count($tmpAllIds);

            foreach ($tmpAllIds as $partialObject) {
                $massActionAllIds .= $partialObject['id'] . ',';
            }
        }

        return array(
            'page_header'                   => $this->pageTitle,
            'index_key'                     => $this->indexKey,
            'index_key_action'              => $this->indexKeyAction,
            'table_rows'                    => $tableRows,
            'table_header'                  => $tableHeader,
            'has_row_actions'               => !empty($this->actions),
            'table_row_actions'             => $rowActions,
            'main_actions'                  => $this->generalActions,
            'sorted_row_indexes'            => $sortedIndexes,
            'searchable'                    => $this->hasSearchableFields($tableHeader),
            'add_action'                    => $this->addAction,
            'add_action_url'                => $this->getAddActionUrl($this->addAction, $this->controllerName),
            'current_filter'                => $this->getUsedFilterFields(),
            'current_sort'                  => $this->getSort(),
            'current_sort_field'            => $currentSortField,
            'current_sort_direction'        => $currentSortDirection,
            'current_page_size'             => $this->getPageSize(),
            'current_page'                  => $this->getCurrentPage(),
            'total_rows'                    => $totalRows,
            'total_pages'                   => ceil($totalRows / $this->getPageSize()),
            'str_from'                      => $strFrom,
            'str_to'                        => min($strTo, $totalRows),
            'paginator_page'                => $paginator,
            'paginator_next'                => $this->getPaginatorNext(),
            'paginator_prev'                => $this->getPaginatorPrev(),
            'page_sizes'                    => $this->getPageSizes(),
            'custom_row_class_renderer'     => $this->getcustom_row_class_renderer(),
            'custom_action_button_renderer' => $this->getcustom_action_button_renderer(),
            'has_mass_actions'              => (count($massActions) > 0),
            'mass_actions'                  => $massActions,
            'mass_actions_all_ids'          => $massActionAllIds,
            'mass_actions_all_ids_count'    => $massActionIdsCount,
        );
    }

    private function massActionsApplyToAllPages()
    {
        if (is_array($this->massActions) && !empty($this->massActions)) {
            $massActionsRoutes = array();

            foreach ($this->massActions as $massAction) {
                if (isset($massAction['apply_all']) && $massAction['apply_all']) {

                    return true;
                }
            }

            return $massActionsRoutes;
        }

        return false;
    }

    private function getMassActions()
    {
        if (is_array($this->massActions) && !empty($this->massActions)) {
            $massActionsRoutes = array();

            foreach ($this->massActions as $massAction) {
                $massActionURL = $this->container->get('router')->generate($massAction['route_name'] . '_' .$this->controllerName);
                $massActionsRoutes[] = array(
                    'label' => $massAction['label'],
                    'path'  => $massActionURL,
                );
            }

            return $massActionsRoutes;
        }

        return null;
    }

    private function getAddActionUrl($addAction, $controllerName)
    {
        $addActionUrl = null;

        if ($addAction) {
            $addRouteName = 'add_' . $controllerName;
            $addActionUrl = $this->container->get('router')->generate($addRouteName);
        }

        return $addActionUrl;
    }

    protected function getHeaderIndexes($tableHeader)
    {
        $sortedIndexes = array();

        foreach ($tableHeader as $row) {
            if (isset($row['alias']) && isset($row['key'])) {
                $sortedIndexes[] = $row['alias'];
            } else {
                if (isset($row['key'])) {
                    $sortedIndexes[] = $row['key'];
                } else {
                    $sortedIndexes[] = $row['label'];
                }
            }
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
                    $fields         = explode(".", $index);
                    $tmpRow[$index] = $row[lcfirst($fields[0])][$fields[1]];
                } else {
                    if (isset($row[$index])) {
                        $tmpRow[$index] = $row[$index];
                    } else {
                        $tmpRow[$index] = '';
                    }
                }
            }

            $row = $tmpRow;
        }

        return $tableRows;
    }

    private function getRowActionIco($action)
    {
        $ico = '';
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

        return $ico;
    }

    protected function getRowActions($actions, $controllerName)
    {
        $rowActions = array();

        foreach ($actions as $action) {
            $ico        = null;
            $label      = null;
            $cssClass   = null;
            $successAction = null;

            if (is_array($action)) {
                $ico    = (isset($action['ico']) ? $action['ico'] : '');
                $label  = (isset($action['label']) ? $action['label'] : '');
                if (isset($action['css_class'])) {
                    $cssClass = $action['css_class'];
                }
                if (isset($action['success_action'])) {
                    $successAction = $action['success_action'];
                }
                $actionRoute = $action['route_name'];
            } else {
                $ico            = $this->getRowActionIco($action);
                $actionRoute    = $action;
            }

            $route               = $actionRoute . '_' . $controllerName;
            $rowActions[$actionRoute] = array(
                'ico'           => $ico,
                'label'         => $label,
                'url'           => $route,
                'css_class'     => $cssClass,
                'success_action' => $successAction,
            );
        }

        if ($this->indexKeyAction == null) {
            $this->indexKeyAction = $this->indexKey;
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
        $numPages  = ceil($totalRows / $pageSize);
        $total     = 0;

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
        $form             = $this->get('request')->get('form');
        $defaultPageSizes = $this->getPageSizes();
        $pageSize         = $defaultPageSizes[0];
        if (isset($form['page_size'])) {
            $pageSize = $form['page_size'];
        }

        return $pageSize;
    }

    protected function getCurrentPage()
    {
        $form        = $this->get('request')->get('form');
        $currentPage = 0;
        if (isset($form['current_page'])) {
            $currentPage = $form['current_page'];
        }

        if ($currentPage < 0) {
            $currentPage = 0;
        }

        return $currentPage;
    }

    protected function getUsedFilterFields($view = true)
    {
        $filters = $this->get('request')->get('filter');
        if (!$view) {
            $filters = $this->processAlias($filters);
        }

        return $filters;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getDefaultSort()
    {

    }

    protected function getcustom_action_button_renderer()
    {

        return 'KodifySimpleCrudBundle:CRUD:list_action.html.twig';
    }

    protected function getcustom_row_class_renderer()
    {

        return '';
    }

    protected function getSort()
    {
        $post = $this->get('request')->get('sort');

        if (empty($post)) {
            $default = $this->getDefaultSort();
            if (is_array($default)) {
                foreach ($default as $key => $value) {
                    $post[$key]['field']     = $key;
                    $post[$key]['direction'] = $value;
                }
            }
        } else {
            $tmp = array();

            if (is_array($post) && isset($post['field'])) {
                $tmp[$post['field']]['field']     = $post['field'];
                $tmp[$post['field']]['direction'] = $post['dir'];
                $post                             = $tmp;
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
            $this->getUsedFilterFields(false),
            $this->getPageSize(),
            $this->getCurrentPage(),
            $this->getSort(),
            null,
            $this->getQueryFields()
        );
    }

    public function getQueryFields()
    {
        $headers    = $this->defineTableHeader();
        $fields     = array();
        foreach ($headers as $field) {
            $strField = $this->getSelectFromFields($field);

            if ($strField != '') {
                $fields[] = $strField;
            }
        }

        return $fields;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function getSelectFromFields($field)
    {
        $strField = '';
        if (isset($field['table'])) {
            $strField = $field['table'] . '.';
        }

        if (isset($field['key'])) {
            if (isset($field['identity']) && $field['identity']) {
                $strField = 'IDENTITY(' . $strField . $field['key'] . ')';
            } else {
                if (isset($field['group_concat']) && $field['group_concat']) {
                    $strField = 'group_concat(DISTINCT ' . $field['key'] . ')';
                } else {
                    $strField = $strField . $field['key'];
                }
            }
            if (isset($field['alias'])) {
                $strField .= ' as ' . $field['alias'];
            }
        }

        return $strField;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTotalRows()
    {
        $repo = $this->getDoctrine()->getManager()->getRepository($this->entityClass);

        return $repo->getTotalRows(
            $this->getUsedFilterFields(false),
            25,
            0,
            $this->getQueryFields()
        );
    }

    protected function processAlias($filters)
    {
        $tableHeader = $this->defineTableHeader();
        if (is_array($filters)) {
            foreach ($filters as $field => $filter) {
                foreach ($tableHeader as $row) {
                    if (isset($row['key']) && isset($row['alias']) && $row['alias'] == $field) {
                        if (isset($row['table'])) {
                            $filters[$row['table'] . '.' . $row['key']] = $filters[$field];
                            unset($filters[$field]);
                        } else {
                            $filters[$row['key']] = $filters[$field];
                            unset($filters[$field]);
                        }
                        continue;
                    }
                }
            }
        }

        return $filters;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getAllRowsId()
    {
        $repo = $this->getDoctrine()->getManager()->getRepository($this->entityClass);

        return $repo->getAllRowsId(
            $this->getUsedFilterFields()
        );
    }

    protected function prePersist($obj)
    {

    }

    protected function postPersist($obj)
    {

    }

    protected function postAddRedirectTo()
    {
        return $this->generateUrl('get_' . $this->controllerName);
    }
}
