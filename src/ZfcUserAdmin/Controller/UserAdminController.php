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
                    $this->flashMessenger()->addMessage('The user was created');
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
        $this->_session = new Container($this->_namespace);
        $this->_session['user'] = $user->getId();

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
                    $this->flashMessenger()->addMessage('The user was edited');

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
                $this->flashMessenger()->addMessage('The user was deleted');
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
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getParent()->getName() . "]]></cell>";
            $s .= "<cell>" . $allDepartments[$x]->getId() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getName() . "]]></cell>";
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

    public function compareDepartmentsAscending($a, $b) {
        return strcmp($a->getName(), $b->getName());
    }

    public function compareDepartmentsDescending($a, $b) {
        return strcmp($b->getName(), $a->getName());
    }

    public function getAllClientsAction() {

        $this->_session = new Container($this->_namespace);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $region = $parms['region'];
        $multiRegion = $this->_session['multi-region'];
        if (!is_array($multiRegion) || !in_array($region, $multiRegion)) {
            $multiRegion[] = $region;
        }
        $this->_session['multi-region'] = $multiRegion;
        //echo 'Multi-region' . var_dump($multiRegion);
        $allDepartments = $this->getAllClients($this->_session['multi-region']);


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
        if (isset($parms['sidx'])) {
            if (isset($parms['sord'])) {
                if ($parms['sord'] == 'asc') {
                    usort($allDepartments, [$this, 'compareDepartmentsAscending']);
                } else {
                    usort($allDepartments, [$this, 'compareDepartmentsDescending']);
                }
            }
        }
        for ($x = $start; $x < $start + $rows; $x++) {
            if (!isset($allDepartments[$x]))
                break;
            $s .= "<row id='" . $allDepartments[$x]->getId() . "'>";
            $EntityManager = $this
                    ->getServiceLocator()
                    ->get('Doctrine\ORM\EntityManager');
            $region_id = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $allDepartments[$x]->getRegionId()])
                    ->getR5wRegionname();
            $s .= "<cell>" . $region_id . "</cell>";
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

    public function getAllClients($regions) {

        foreach ($regions as $region) {


            $EntityManager = $this
                    ->getServiceLocator()
                    ->get('Doctrine\ORM\EntityManager');
            $region_id = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionname' => $region])
                    ->getR5wRegionpky();

            $departments = $EntityManager
                    ->getRepository('Application\Entity\Client')
                    ->findBy(['parent' => null, 'region_id' => $region_id]);
            foreach ($departments as $department) {
                $return[] = $department;
            }
        }
        return $return;
    }

    public function getUserAvailableClientsAction() {

        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $this->_session = new Container($this->_namespace);
        $userId = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userId]);

        $userclients = $user->getClients();
        foreach ($userclients as $client) {
            $region = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $client->getRegionId()]);
            $regionList[] = $region->getR5wRegionname();
        }
        $allClients = $this->getAllClients($regionList);

        foreach ($allClients as $client) {
            foreach ($userclients as $selected) {
                if ($client->getId() != $selected->getId()) {
                    $allDepartments[] = $client;
                }
            }
        }

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
            $region_id = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $allDepartments[$x]->getRegionId()])
                    ->getR5wRegionname();
            $s .= "<cell>" . $region_id . "</cell>";
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

    public function getUserSelectedClientsAction() {

        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $this->_session = new Container($this->_namespace);
        $userId = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userId]);

        $allClients = $user->getClients();
        foreach ($allClients as $client) {
            $allDepartments[] = $client->getParent();
        }
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
            $region_id = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $allDepartments[$x]->getRegionId()])
                    ->getR5wRegionname();
            $s .= "<cell>" . $region_id . "</cell>";
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

    public function getRegionsAction() {

        $this->_session = new Container($this->_namespace);
        unset($this->_session['multi-region']);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $xref = $EntityManager
                ->getRepository('Application\Entity\Regionxref')
                ->findAll();
        $allDepartments = $xref;

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
            $s .= "<row id='" . $allDepartments[$x]->getPky() . "'>";
            $s .= "<cell>" . $allDepartments[$x]->getR5wRegionname() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getCompanyname() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($s);

        return $response;
    }

    public function getUserRegions(\Application\Entity\User $user) {

        $regions = [];

        foreach ($user->getClients() as $client) {
            $regions[] = $client->getRegionId();
        }
        return $regions;
    }

    public function getAvailableRegionsAction() {
        $this->_session = new Container($this->_namespace);
        unset($this->_session['multi-region']);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $userid = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $xref = $EntityManager
                ->getRepository('Application\Entity\Regionxref')
                ->findAll();
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userid]);

        $allRegions = $xref;
        $selected = $this->getUserRegions($user);

        foreach ($allRegions as $dept) {
            if (!in_array($dept->getR5wRegionpky(), $selected)) {
                $allDepartments[] = $dept;
            }
        }

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
            $s .= "<row id='" . $allDepartments[$x]->getPky() . "'>";
            $s .= "<cell>" . $allDepartments[$x]->getR5wRegionname() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getCompanyname() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($s);

        return $response;
    }

    public function getSelectedRegionsAction() {
        $this->_session = new Container($this->_namespace);
        unset($this->_session['multi-region']);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $userid = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $xref = $EntityManager
                ->getRepository('Application\Entity\Regionxref')
                ->findAll();
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userid]);

        $allRegions = $xref;
        $selected = $this->getUserRegions($user);

        foreach ($allRegions as $dept) {
            if (in_array($dept->getR5wRegionpky(), $selected)) {
                $allDepartments[] = $dept;
            }
        }

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
            $s .= "<row id='" . $allDepartments[$x]->getPky() . "'>";
            $s .= "<cell>" . $allDepartments[$x]->getR5wRegionname() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getCompanyname() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($s);

        return $response;
    }

    public function getUserSelectedDepartmentsAction() {
        $this->_session = new Container($this->_namespace);
        unset($this->_session['multi-region']);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $userid = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userid]);
        $regions = $this->getUserRegions($user);

        $allDepartments = $user->getClients();
//        foreach ($allClients as $dept) {
//            if (!in_array($dept->getRegionId(), $regions)) {
//                $allDepartments[] = $dept;
//            }
//        }

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
            $s .= "<cell>" . $allDepartments[$x]->getParentName() . "</cell>";
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

    public function getAvailableParentsAction() {
        $this->_session = new Container($this->_namespace);
        unset($this->_session['multi-region']);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $userid = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userid]);
        $regions = $this->getUserRegions($user);

        $allClients = $this->getAllClients($regions);

        foreach ($allClients as $dept) {
            if (!in_array($dept->getRegionId(), $regions)) {
                $allDepartments[] = $dept;
            }
        }

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
            $s .= "<row id='" . $allDepartments[$x]->getPky() . "'>";
            $s .= "<cell>" . $allDepartments[$x]->getR5wRegionname() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getCompanyname() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($s);

        return $response;
    }

    public function getUserAvailableDepartmentsAction() {
        $this->_session = new Container($this->_namespace);
        unset($this->_session['multi-region']);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $userid = $this->_session['user'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userid]);
        $selectedDepartments = $user->getClients();
        foreach ($selectedDepartments as $dept) {
            $parentList[] = $dept->getParent()->getId();
            $departmentList[] = $dept->getId();
        }
        // die(var_dump($selectList));
        $availableDepartments = $EntityManager
                ->getRepository('Application\Entity\Client')
                ->findBy(['parent' => $parentList]);

        $allDepartments = [];
        foreach ($departmentList as $selected) {
            foreach ($availableDepartments as $dept) {
                if ($dept->getId() != $selected) {
                    $allDepartments[] = $dept;
                }
            }
        }
//        foreach ($allClients as $dept) {
//            if (!in_array($dept->getRegionId(), $regions)) {
//                $allDepartments[] = $dept;
//            }
//        }

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
            $s .= "<cell>" . $allDepartments[$x]->getParentName() . "</cell>";
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

}
