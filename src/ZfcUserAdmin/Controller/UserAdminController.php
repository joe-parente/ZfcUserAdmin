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
        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $regions = $EntityManager
                ->getRepository('Application\Entity\Regionxref')
                ->findAll();
        $options = '<option value="0">None</option>';
        foreach ($regions as $region) {
            $options .= '<option value="' . $region->getR5wRegionpky() . '">' . $region->getR5wRegionname() . '</option>';
        }

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
            'options' => $options,
            'userlistElements' => $this->getOptions()->getUserListElements()
        );
    }

    public function getAdminFilteredUsersAction() {

        $parms = ($this->params()->fromPost());
        $page = $parms['page'];

        $allUsers = $this->getFilteredUsers($parms);

        $count = count($allUsers);
        $rows = 100; // $parms['rows'];
        $total_pages = ceil($count / $rows);

        $start = (($page - 1) * $rows);
        $rows = 5000;

        $jsonData = [];

        for ($x = $start; $x < $start + $rows; $x++) {
            if (!isset($allUsers[$x]))
                break;
            $link = '<span><a href="/admin/user/edit/' .
                    $allUsers[$x]->getId() .
                    '"><span class="underliner">Edit</span></a>&nbsp;|&nbsp;<a onclick="return confirm(\'Really delete user?\')" href="/admin/user/remove/' .
                    $allUsers[$x]->getId() .
                    '"><span class="underliner">Delete</span></a></span>&nbsp;|&nbsp;<a onclick="return confirm(\'Really impersonate this user?\')" href="/admin/user/impersonate/' .
                    $allUsers[$x]->getId() . '"<span class="underliner">Impersonate</span></a></span>';
            $jsonData['rows'][$x] = [
                'act' => '',
                'view' => '',
                'id' => $allUsers[$x]->getId(),
                'billingcontactkey' => $allUsers[$x]->getBillingContactKey(),
                'firstname' => $allUsers[$x]->getFirstName(),
                'lastname' => $allUsers[$x]->getLastName(),
                'email' => $allUsers[$x]->getEmail(),
                'parentclientid' => $allUsers[$x]->getParentClientId(),
                'parentname' => $allUsers[$x]->getParentName(),
                'lastlogindatetime' => $allUsers[$x]->getLastLoginDate(),
                'acceptedagreement' => $allUsers[$x]->getAcceptedAgreement(),
                'roles' => $allUsers[$x]->getRoles()->getRoleId(),
                'actions' => $link
            ];
        }
        $jsonData['page'] = $page;
        $jsonData['records'] = count($allUsers);
        $jsonData['total'] = count($allUsers);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $response->setStatusCode(200);
        $response->setContent(json_encode($jsonData));
        return $response;
    }

    public function createAction() {

        $this->_session = new Container($this->_namespace);

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
            $currentPassword = $user->getPassword();
            if ($form->isValid()) {

                if ($form->getData()->getPassword() == '') {
                    error_log('password from form is empty');
                }

                $user = $this->getAdminUserService()->edit($form, (array) $request->getPost(), $user, $currentPassword);
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

    public function getUserAdminParentsAction() {

        $parms = $_POST;
        $region = $parms['region'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');

        $subs = $EntityManager
                ->getRepository('Application\Entity\Client')
                ->findBy(['region_id' => $region, 'parent' => null], ['name' => 'asc']);
        $s = '';
        foreach ($subs as $sub) {
            $s .= '<option value=' . $sub->getId() . '>' . $sub->getName() . '</option>';
        }
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'text/html');
        $response->setStatusCode(200);
        $response->setContent($s);
        return $response;
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
        //       $parent = $parms['parent'];
        if (isset($parms['parent'])) {
            $parents = explode(',', $parms['parent']);
        } else {
            $parents = null;
        }
        $allDepartments = $this->getDepartmentList($parents);
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
            $EntityManager = $this
                    ->getServiceLocator()
                    ->get('Doctrine\ORM\EntityManager');

            $region_id = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $allDepartments[$x]->getRegionId()])
                    ->getR5wRegionname();
            $s .= "<cell>" . $region_id . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getParent()->getName() . "]]></cell>";
            $s .= "<cell>" . $allDepartments[$x]->getId() . "</cell>";
            $s .= "<cell><![CDATA[" . $allDepartments[$x]->getName() . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";
        return($this->setUpResponse($s));
    }

    public function getDepartmentList($parent) {

        $newParent = array_unique($parent);
        $return = [];

        foreach ($newParent as $each) {
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

    public function compareIdsDescending($a, $b) {
        return strcmp($b->getId(), $a->getId());
    }

    public function compareIdsAscending($a, $b) {
        return strcmp($a->getId(), $b->getId());
    }

    public function compareGenericAscending($a, $b, $field) {
        $methodName = 'get' . $field;
        return strcmp($a->$methodName(), $b->$methodName());
    }

    public function compareGenericDescending($a, $b, $field) {
        $methodName = 'get' . $field;
        return strcmp($b->$methodName(), $a->$methodName());
    }

    public function getAllClientsAction() {

        $this->_session = new Container($this->_namespace);
        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];

        if (isset($parms['region'])) {
            $region = explode(',', $parms['region']);
        } else {
            $region = [];
        }
        $sortIndex = $parms['sidx'];
        $sortOrder = $parms['sord'];
        $allDepartments = $this->getAllClients($region);


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

        $return = [];

        foreach ($regions as $region) {
            if (empty($region)) {
                continue;
            }
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
                $return[$department->getId()] = $department;
            }
        }
        return $return;
    }

    public function getUserAvailableClientsAction() {

        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $this->_session = new Container($this->_namespace);
        $userId = $this->_session['user'];
        if (isset($parms['region'])) {
            $regions = explode(',', $parms['region']);
        } else {
            $regions = [];
        }

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userId]);

        $userclients = $user->getClients();
        $multiRegion = [];
        foreach ($userclients as $client) {
            $userRegion = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $client->getRegionId()]);
            $multiRegion[] = $userRegion->getR5wRegionname();
        }

//        if (!is_array($multiRegion) || !in_array($region, $multiRegion)) {
//            if (!is_null($region)) {
//                $multiRegion[] = $region;
//            }
//        }

        $allClients = $this->getAllClients(array_unique(array_merge($regions, $multiRegion)));



        foreach ($userclients as $client) {

            if (array_key_exists($client->getParent()->getId(), $allClients)) {

                unset($allClients[$client->getParent()->getId()]);
            }
        }
        $allDepartments = $allClients;

        $count = count($allDepartments);
        $rows = $parms['rows'];
        $total_pages = ceil($count / $rows);

        $s = "<?xml version='1.0' encoding='utf-8'?>";
        $s .= "<rows>";
        $s .= "<page>" . $page . "</page>";
        $s .= "<total>" . $total_pages . "</total>";
        $s .= "<records>" . $count . "</records>";

        $start = (($page - 1) * $rows);
        if (isset($parms['sidx'])) {
            if (isset($parms['sord'])) {
                if ($parms['sord'] == 'asc') {
                    usort($allDepartments, [$this, 'compareDepartmentsAscending']);
                } else {
                    usort($allDepartments, [$this, 'compareDepartmentsDescending']);
                }
            }
        }
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
            $badDepartments[] = $client->getParent();
        }
        $allDepartments = array_unique($badDepartments);
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
                continue;
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

        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];
        $userid = $this->_session['user'];
        $parentList = (isset($parms['parent'])) ? $parms['parent'] : '';
        $departmentList = (isset($parms['departments'])) ? explode(',', $parms['departments']) : '';

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');
        $user = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findOneBy(['id' => $userid]);
        $allDepartments = $EntityManager
                ->getRepository('Application\Entity\Client')
                ->findBy(['parent' => $parentList]);
        if ($departmentList != '') {
            foreach (array_unique($departmentList) as $department) {

                if (!is_null($department)) {
                    $clientDepartments[] = $EntityManager
                            ->getRepository('Application\Entity\Client')
                            ->findOneBy(['id' => $department]);
                }
            }
        } else {
            $clientDepartments = $user->getClients();
        }

        foreach ($clientDepartments as $client) {
            $allDepartments[] = $client;
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
                ->findBy(['parent' => array_unique($parentList)]);

        $allDepartments = [];
        foreach ($availableDepartments as $available) {

            if (!in_array($available->getId(), $departmentList)) {
                $allDepartments[] = $dept;
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
            $region_id = $EntityManager
                    ->getRepository('Application\Entity\Regionxref')
                    ->findOneBy(['r5wRegionpky' => $allDepartments[$x]->getRegionId()])
                    ->getR5wRegionname();
            $s .= "<cell>" . $region_id . "</cell>";
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

    private function _getUsers($parms) {

//        $sortIndex = $parms['sidx'];
//        $sortDirection = $parms['sord'];

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');

        if (!isset($sortIndex)) {
            $sortIndex = 'id';
        }

        if (!isset($sortDirection)) {
            $sortDirection = 'asc';
        }

        $allUsers = $EntityManager
                ->getRepository('Application\Entity\User')
                ->findBy([], [$sortIndex => $sortDirection]);
        return $allUsers;
    }

    public function getUserListAction() {

        $parms = ($this->params()->fromQuery());
        $page = $parms['page'];

        $allUsers = $this->_getUsers($parms);

        $count = count($allUsers);
        $rows = $parms['rows'];
        $total_pages = ceil($count / $rows);

        $s = "<?xml version='1.0' encoding='utf-8'?>";
        $s .= "<rows>";
        $s .= "<page>" . $page . "</page>";
        $s .= "<total>" . $total_pages . "</total>";
        $s .= "<records>" . $count . "</records>";

        $start = (($page - 1) * $rows);

        if ($parms['_search'] === 'true') {
            $filteredIssues = array_filter($allUsers, function($e) {

                $parms = ($this->params()->fromQuery());
                $searchFunction = 'get' . $parms['searchField'];
                if ($this->matchElement($parms['searchString'], $e->$searchFunction(), $parms['searchOper'])) {
                    return $e;
                }
            });
            $allUsers = array_values($filteredIssues);
            $start = 0;
            $rows = 5000;
        }

        for ($x = $start; $x < $start + $rows; $x++) {
            if (!isset($allUsers[$x]))
                break;
            $s .= "<row id='" . $allUsers[$x]->getId() . "'>";
            $s .= "<cell>" . $allUsers[$x]->getId() . "</cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getBillingContactKey() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getFirstName() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getLastName() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getEmail() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getParentClientId() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getParentName() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getLastLoginDate() . "]]></cell>";
            $s .= "<cell><![CDATA[" . $allUsers[$x]->getRoles()->getRoleId() . "]]></cell>";
            $link = '<span><a href="/admin/user/edit/' . $allUsers[$x]->getId() . '"><span class="underliner">Edit</span></a>&nbsp;|&nbsp;<a onclick="return confirm(\'Really delete user?\')" href="/admin/user/remove/' . $allUsers[$x]->getId() . '"><span class="underliner">Delete</span></a></span>';
            $s .= "<cell><![CDATA[" . $link . "]]></cell>";
            $s .= "</row>";
        }
        $s .= "</rows>";

        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/xml');
        $response->setStatusCode(200);
        $response->setContent($s);
        return $response;
    }

    public function getFilteredUsers($filter) {

        $EntityManager = $this
                ->getServiceLocator()
                ->get('Doctrine\ORM\EntityManager');

        $qb = $EntityManager->createQueryBuilder();
        $qb->select('u');
        $qb->from('Application\Entity\User', 'u');


        if ($filter['parentclientid'] && $filter['region']) {

            $users = [];
            $children = $EntityManager->find('\Application\Entity\Client', $filter['parentclientid'])->getChildren();
            foreach ($children as $child) {
                foreach ($child->getUsers() as $user) {
                    if (!in_array($user, $users)) {
                        $users[] = $user;
                    }
                }
            }
        } else {
            if ($filter['email']) {
                $qb->andWhere('u.email LIKE :email');
                $qb->setParameter('email', '%' . $filter['email'] . '%');
            }
            if ($filter['fname']) {
                $qb->andWhere('u.firstname LIKE :fname');
                $qb->setParameter('fname', '%' . $filter['fname'] . '%');
            }
            if ($filter['lname']) {
                $qb->andWhere('u.lastname LIKE :lname');
                $qb->setParameter('lname', '%' . $filter['lname'] . '%');
            }
            $qb->setMaxResults(100);
            $query = $qb->getQuery();
            $sql = $query->getDQL();

            $users = $query->getResult();
        }
        $return = [];
        foreach ($users as $user) {
            $return[] = $user;
        }
        return $return;
    }

}
