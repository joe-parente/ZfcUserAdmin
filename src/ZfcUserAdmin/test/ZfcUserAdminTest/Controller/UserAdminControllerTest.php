<?php

namespace ZfcUserAdminTest;

use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

/**
 * Description of UserAdminControllerTest
 *
 * @author jparente
 */
class UserAdminControllerTest extends AbstractHttpControllerTestCase {

    public function setUp() {
        $this->setApplicationConfig(
                include '/config/application.config.php'
        );
        parent::setUp();
    }

    public function testIndexAction() {
       $this->dispatch('/admin/user/list');
       $this->assertResponseStatusCode(200);
    }
    public function testGetFilteredUsers() {
        
    }

}
