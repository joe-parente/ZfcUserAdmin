<?php

return array(
    'view_manager' => array(
        'template_path_stack' => array(
            'zfcuseradmin' => __DIR__ . '/../view',
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'zfcuseradmin' => 'ZfcUserAdmin\Controller\UserAdminController',
        ),
    ),
    'router' => array(
        'routes' => array(
            'zfcadmin' => array(
                'child_routes' => array(
                    'zfcuseradmin' => array(
                        'type' => 'Literal',
                        'priority' => 1000,
                        'options' => array(
                            'route' => '/user',
                            'defaults' => array(
                                'controller' => 'zfcuseradmin',
                                'action' => 'index',
                            ),
                        ),
                        'child_routes' => array(
                            'list' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/list[/:p]',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'list',
                                    ),
                                ),
                            ),
                            'create' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/create',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'create'
                                    ),
                                ),
                            ),
                            'edit' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/edit/:userId',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'edit',
                                        'userId' => 0
                                    ),
                                ),
                            ),
                            'remove' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/remove/:userId',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'remove',
                                        'userId' => 0
                                    ),
                                ),
                            ),
                            'getdepartments' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getdepartments',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getdepartments',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'updatedepartments' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/updatedepartments',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'updatedepartments',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'selectdepartment' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/selectdepartment',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'selectdepartment',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getallclients' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getallclients',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getallclients',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getuseravailableclients' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getuseravailableclients',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getuseravailableclients',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getuserselectedclients' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getuserselectedclients',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getuserselectedclients',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getregions' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getregions',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getregions',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getavailableregions' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getavailableregions',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getavailableregions',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getselectedregions' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getselectedregions',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getselectedregions',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getavailableparents' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getavailableparents',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getavailableparents',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getuserselecteddepartments' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getuserselecteddepartments',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getuserselecteddepartments',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getuseravailabledepartments' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getuseravailabledepartments',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getuseravailabledepartments',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                            'getuseradminparents' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getuseradminparents',
                                    'constraints' => array(
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ),
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getuseradminparents',
                                    ),
                                ),
                            ),
                            'getadminfilteredusers' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getadminfilteredusers',
                                    'constraints' => array(
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ),
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getadminfilteredusers',
                                    ),
                                'mayterminate' => true,
                                ),
                            ),
                            'getuserlist' => array(
                                'type' => 'Literal',
                                'options' => array(
                                    'route' => '/getuserlist',
                                    'defaults' => array(
                                        'controller' => 'zfcuseradmin',
                                        'action' => 'getuserlist',
                                    ),
                                ),
                                'mayterminate' => true,
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'navigation' => array(
        'admin' => array(
            'zfcuseradmin' => array(
                'label' => 'Users',
                'route' => 'zfcadmin/zfcuseradmin/list',
                'pages' => array(
                    'create' => array(
                        'label' => 'New User',
                        'route' => 'zfcadmin/zfcuseradmin/create',
                    ),
                ),
            ),
        ),
    ),
    'zfcuseradmin' => array(
        'zfcuseradmin_mapper' => 'ZfcUserAdmin\Mapper\UserZendDb',
    )
);
