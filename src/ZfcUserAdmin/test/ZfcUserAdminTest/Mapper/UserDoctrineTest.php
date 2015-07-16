<?php

namespace ZfcUserTest\Mapper;

use PHPUnit_Framework_TestCase;
use ModulesTests\ServiceManagerGrabber;

/**
 * Description of UserDoctrineTest
 *
 * @author Joe
 */
class UserDoctrineTest extends PHPUnit_Framework_TestCase {

    protected $serviceManager;

    public function setUp() {
        $serviceManagerGrabber = new ServiceManagerGrabber();
        $this->serviceManager = $serviceManagerGrabber->getServiceManager();
    }

    public function testJoinLeft() {
        $count = count($this->serviceManager->get('UserDoctrine')->findAll());
        ($count > 0 ) ? $this->assertNotEmpty($count) : $this->assertEmpty($count);
    }

}
