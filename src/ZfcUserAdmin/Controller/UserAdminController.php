<?php

namespace ZfcUserAdmin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator;
use Zend\Stdlib\Hydrator\ClassMethods;
use ZfcUser\Mapper\UserInterface;
use ZfcUser\Options\ModuleOptions as ZfcUserModuleOptions;
use ZfcUserAdmin\Options\ModuleOptions;
use Zend\Session\Container;

class UserAdminController extends AbstractActionController {

    protected $options, $userMapper;
    protected $zfcUserOptions;
    protected $_namespace = 'InquiryController';
    protected $_session;
    protected $_data;

    /**
     * @var \ZfcUserAdmin\Service\User
     */
    protected $adminUserService;

    public function listAction() {

        $this->_session = new Container($this->_namespace);
        $this->_session['multi-clients'] = [];

        $userMapper = $this->getUserMapper();
        $users = $userMapper->findAll();
        if (is_array($users)) {
            $paginator = new Paginator\Paginator(new Paginator\Adapter\ArrayAdapter($users));
        } else {
            $paginator = $users;
        }

        $paginator->setItemCountPerPage(100);
        $paginator->setCurrentPageNumber($this->getEvent()->getRouteMatch()->getParam('p'));
        return array(
            'users' => $paginator,
            'userlistElements' => $this->getOptions()->getUserListElements()
        );
    }

    public function createAction() {


        /** @var $form \ZfcUserAdmin\Form\CreateUser */
        $form = $this->getServiceLocator()->get('zfcuseradmin_createuser_form');
        $request = $this->getRequest();

        /** @var $request \Zend\Http\Request */
        if ($request->isPost()) {
            $zfcUserOptions = $this->getZfcUserOptions();
            $class = $zfcUserOptions->getUserEntityClass();
            $user = new $class();
//            $form->setHydrator(new ClassMethods());
            $hydrator = $this->getServiceLocator()->get('zfcuser_user_hydrator');
            $form->setHydrator($hydrator);
            $form->bind($user);
            $form->setData($request->getPost());

            if ($form->isValid()) {
                $user = $this->getAdminUserService()->create($form, (array) $request->getPost());
                if ($user) {
                    $this->flashMessenger()->addSuccessMessage('The user was created');
                    return $this->redirect()->toRoute('zfcadmin/zfcuseradmin/list');
                }
            }
        }

        return array(
            'createUserForm' => $form
        );
    }

    public function editAction() {
        $userId = $this->getEvent()->getRouteMatch()->getParam('userId');
        $user = $this->getUserMapper()->findById($userId);

        /** @var $form \ZfcUserAdmin\Form\EditUser */
        $form = $this->getServiceLocator()->get('zfcuseradmin_edituser_form');
        $hydrator = $this->getServiceLocator()->get('zfcuser_user_hydrator');
        $form->setHydrator($hydrator);
        $form->bind($user);

        /** @var $request \Zend\Http\Request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $form->setData($request->getPost());
            $tester = $user->getRoles();
            if ($form->isValid()) {

                $user = $this->getAdminUserService()->edit($form, (array) $request->getPost(), $user);
                if ($user) {
                    $this->flashMessenger()->addSuccessMessage('The user was edited');

                    return $this->redirect()->toRoute('zfcadmin/zfcuseradmin/list');
                }
            }
        } else {
            $form->populateFromUser($user);
        }

        return array(
            'editUserForm' => $form,
            'userId' => $userId,
            'createddate' => $user->getCreatedDate(),
            'modifieddate' => $user->getModifiedDate(),
            'lastlogindate' => $user->getLastLoginDate(),
        );
    }

    public function removeAction() {
        $userId = $this->getEvent()->getRouteMatch()->getParam('userId');
        $identity = $this->zfcUserAuthentication()->getIdentity();
        if ($identity && $identity->getId() == $userId) {
            $this->flashMessenger()->addErrorMessage('You can not delete yourself');
        } else {
            $user = $this->getUserMapper()->findById($userId);
            if ($user) {
                $this->getUserMapper()->remove($user);
                $this->flashMessenger()->addSuccessMessage('The user was deleted');
            }
        }

        return $this->redirect()->toRoute('zfcadmin/zfcuseradmin/list');
    }

    public function setOptions(ModuleOptions $options) {
        $this->options = $options;
        return $this;
    }

    public function getOptions() {
        if (!$this->options instanceof ModuleOptions) {
            $this->setOptions($this->getServiceLocator()->get('zfcuseradmin_module_options'));
        }
        return $this->options;
    }

    public function getUserMapper() {
        if (null === $this->userMapper) {
            $this->userMapper = $this->getServiceLocator()->get('zfcuser_user_mapper');
        }
        return $this->userMapper;
    }

    public function setUserMapper(UserInterface $userMapper) {
        $this->userMapper = $userMapper;
        return $this;
    }

    public function getAdminUserService() {
        if (null === $this->adminUserService) {
            $this->adminUserService = $this->getServiceLocator()->get('zfcuseradmin_user_service');
        }
        return $this->adminUserService;
    }

    public function setAdminUserService($service) {
        $this->adminUserService = $service;
        return $this;
    }

    public function setZfcUserOptions(ZfcUserModuleOptions $options) {
        $this->zfcUserOptions = $options;
        return $this;
    }

    /**
     * @return \ZfcUser\Options\ModuleOptions
     */
    public function getZfcUserOptions() {
        if (!$this->zfcUserOptions instanceof ZfcUserModuleOptions) {
            $this->setZfcUserOptions($this->getServiceLocator()->get('zfcuser_module_options'));
        }
        return $this->zfcUserOptions;
    }

    public function getDepartmentsAction() {

        $this->_session = new Container($this->_namespace);


        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $rows = $parms['rows'];
        $parent = $parms['parent'];

        $this->_session['multi-clients'][] = $parent;
        $allDepartments = $this->getDepartmentList($this->_session['multi-clients']);
        if (($parms['user'] != 'undefined')) {
            $userDepartments = $this->getUserDepartments($parms['user']);
        } else {
            $userDepartments = '';
        }
        $count = count($allDepartments);
        $total_pages = ceil($count / $rows);
        $deptTable = [];
        foreach ($allDepartments as $dept) {
            $id = $dept->getId();
            if ($userDepartments == '') {
                break;
            }
            $deptTable[$id] = false;
            foreach ($userDepartments as $userDepot) {

                if ($userDepot->getId() == $id) {

                    $deptTable[$id] = true;
                }
            }
        }


        $s = "<?xml version='1.0' encoding='utf-8'?>";
        $s .= "<rows>";
        $s .= "<page>" . $page . "</page>";
        $s .= "<total>" . $total_pages . "</total>";
        $s .= "<records>" . $count . "</records>";

        $start = (($page - 1) * $rows);
        if ($parms['_search'] === 'true') {

            $filteredIssues = array_filter($allDepartments, function($e) {

                $parms = ($this->params()->fromQuery());
                if ($this->matchElement($parms['searchString'], $e->$parms['searchField'], $parms['searchOper'])) {
                    return $e;
                }
            });
            $allDepartments = array_values($filteredIssues);
            $start = 0;
            $rows = 5000;
        }

        for ($x = $start; $x < $start + $rows; $x++) {

            if (!isset($allDepartments[$x]))
                break;

            $s .= "<row id='" . $allDepartments[$x]->getId() . "'>";
            $s .= "<cell>" . $allDepartments[$x]->getParent()->getId() . "</cell>";
            $s .= "<cell>" . $allDepartments[$x]->getId() . "</cell>";
            $s .= "<cell>" . $allDepartments[$x]->getName() . "</cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        return($this->setUpResponse($s));
    }

    public function getDepartmentList($parent) {

        foreach ($parent as $each) {
            $EntityManager = $this
                    ->getServiceLocator()
                    ->get('Doctrine\ORM\EntityManager');
            $depts = $EntityManager
                    ->getRepository('Application\Entity\Client')
                    ->findBy(['parent' => $each]);
            foreach ($depts as $dept) {
                $return[] = $dept;
            }
        }
        return $return;
    }

    public function getUserDepartments($userId) {

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findBy(['id' => $userId]);
        $departments = $user[0]->getClients();

        return $departments;
    }

    public function updateDepartmentAction() {

        
        $this->_session = new Container($this->_namespace);
        
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $allDepartments = $this->getDepartmentList($this->_session['multi-clients']);

        $count = count($allDepartments);
        $rows = $parms['rows'];
        $total_pages = ceil($count / $rows);

        $s = "<?xml version='1.0' encoding='utf-8'?>";
        $s .= "<rows>";
        $s .= "<page>" . $page . "</page>";
        $s .= "<total>" . $total_pages . "</total>";
        $s .= "<records>" . $count . "</records>";

        $start = (($page - 1) * $rows);

        if ($parms['_search'] === 'true') {
            $filteredIssues = array_filter($allDepartments, function($e) {

                $parms = ($this->params()->fromQuery());
                if ($this->matchElement($parms['searchString'], $e->$parms['searchField'], $parms['searchOper'])) {
                    return $e;
                }
            });
            $allDepartments = array_values($filteredIssues);
            $start = 0;
            $rows = 5000;
        }

        for ($x = $start; $x < $start + $rows; $x++) {
            if (!isset($allDepartments[$x]))
                break;
            $s .= "<row id='" . $allDepartments[$x]->getId() . "'>";
            if (isset($deptTable[$allDepartments[$x]->getId()])) {

                $inThere = $deptTable[$allDepartments[$x]->getId()];
                $s .= "<cell>" . $inThere . "</cell>";
            } else {
                $s .= "<cell>" . false . "</cell>";
            }
            $s .= "<cell>" . $allDepartments[$x]->getId() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getName() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";

        return($this->setUpResponse($s));
    }

    public function setUpResponse($text) {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($text);
        return $response;
    }

    public function selectDepartmentAction() {

        $parms = $_POST;

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');

        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findBy(['id' => $parms['user']]);
        $client = $EntityManager
                ->getRepository('Application\Entity\Client')
                ->findOneBy(['id' => $parms['id']]);
        if ($parms['ischecked'] == "true") {
            $user[0]->addClient($client);
        } else {
            $user[0]->removeClient($parms['id']);
        }
        $EntityManager->persist($user[0]);
        $EntityManager->flush();

        return $this->setUpResponse("<?xml version='1.0' encoding='utf-8'?><data></data>");
    }

    public static function matchElement($needle, $haystack, $op) {

        switch ($op) {
            case 'eq':
                if ($needle == $haystack) {
                    return true;
                }
                break;
            case 'ne':
                if ($needle != $haystack) {
                    return true;
                }
                break;
            case 'lt':
                if ($needle > $haystack) {
                    return true;
                }
                break;
            case 'gt':
                if ($needle < $haystack) {
                    return true;
                }
                break;
            case 'cn':
                $result = strpos($haystack, $needle);
                if ($result !== false) {
                    return true;
                }
        }


        return false;
    }

    public function getAllClientsAction() {

        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $allDepartments = $this->getAllClients();

        $count = count($allDepartments);
        $rows = $parms['rows'];
        $total_pages = ceil($count / $rows);

        $s = "<?xml version='1.0' encoding='utf-8'?>";
        $s .= "<rows>";
        $s .= "<page>" . $page . "</page>";
        $s .= "<total>" . $total_pages . "</total>";
        $s .= "<records>" . $count . "</records>";

        $start = (($page - 1) * $rows);

        if ($parms['_search'] === 'true') {
            $filteredIssues = array_filter($allDepartments, function($e) {

                $parms = ($this->params()->fromQuery());
                $searchFunction = 'get' . $parms['searchField'];
                if ($this->matchElement($parms['searchString'], $e->$searchFunction(), $parms['searchOper'])) {
                    return $e;
                }
            });
            $allDepartments = array_values($filteredIssues);
            $start = 0;
            $rows = 5000;
        }

        for ($x = $start; $x < $start + $rows; $x++) {
            if (!isset($allDepartments[$x]))
                break;
            $s .= "<row id='" . $allDepartments[$x]->getId() . "'>";
            $s .= "<cell>" . $allDepartments[$x]->getId() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getName() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($s);

        return $response;
    }

    public function getAllClients() {

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $departments = $EntityManager
                ->getRepository('Application\Entity\Client')
                ->findBy(['parent' => null]);

        return $departments;
    }

}
