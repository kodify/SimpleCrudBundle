<?php
namespace Kodify\SimpleCrudBundle\Controller;

interface CrudControllerInterface
{
    public function getData();

    public function getTotalRows();

    /**
     * define GRID table header, the structure of the returned array should be:
     *
     * array(
     *       array(
     *           'label' => 'Label 1',
     *           'sortable' => true,
     *           'default_sort_order' => 'DESC'
     *           'filterable' => true,
     *           'key' => 'id'
     *       ),
     *       array(
     *           'label' => 'Label 2',
     *           'sortable' => false,
     *           'filterable' => true,
     *           'key' => 'name'
     *       ),
     *       array(
     *           'label' => 'Label 3',
     *           'key' => 'description',
     *           'filterable' => true,
     *           'options' => array('1' => 'Sí', '0' => 'No')
     *       )
     *       ...
     *   );
     */
    public function defineTableHeader();
}
