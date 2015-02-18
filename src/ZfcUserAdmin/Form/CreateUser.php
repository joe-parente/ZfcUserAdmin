<?php

namespace ZfcUserAdmin\Form;

use ZfcUserAdmin\Options\UserCreateOptionsInterface;
use ZfcUser\Options\RegistrationOptionsInterface;
use ZfcUser\Form\Register as Register;

class CreateUser extends Register {

    /**
     * @var RegistrationOptionsInterface
     */
    protected $createOptionsOptions;
    protected $serviceManager;

    /**
     * @var UserCreateOptionsInterface
     */
    protected $createOptions;

    public function __construct($name = null, UserCreateOptionsInterface $createOptions, RegistrationOptionsInterface $registerOptions, $serviceManager) {
        $this->setCreateOptions($createOptions);
        $this->setServiceManager($serviceManager);
        parent::__construct($name, $registerOptions);
        $objectManager = $this->serviceManager
                ->get('Doctrine\ORM\EntityManager');

        if ($createOptions->getCreateUserAutoPassword()) {
            $this->remove('password');
            $this->remove('passwordVerify');
        }

        foreach ($this->getCreateOptions()->getCreateFormElements() as $name => $element) {

            $this->add(array(
                'name' => $element,
                'options' => array(
                    'label' => $name,
                ),
                'attributes' => array(
                    'type' => 'text'
                ),
            ));
        }
        $this->add(
                array(
                    'type' => 'DoctrineORMModule\Form\Element\EntityRadio',
                    'name' => 'roles',
                    'attributes' => array(
                        'multiple' => false,
                    ),
                    'options' => array(
                        'object_manager' => $objectManager,
                        'target_class' => 'Application\Entity\Role',
                        'property' => 'roleId',
                    )
                )
        );
        $this->add(
                array(
                    'type' => 'Zend\Form\Element\Checkbox',
                    'name' => 'state',
                    'attributes' => array(
                        'style' => 'display: inline;',
                    ),
                    'options' => array(
                        'label' => 'Suspended:',
                    ),
                )
        );
        $this->add(array(
            'name' => 'parentclientid',
            'type' => 'Application\Form\Element\DtgAutocompleteElement',
            'options' => array(
                'label' => 'Parent Client',
                'sm' => $serviceManager, // don't forget to send Service Manager
                'property' => 'parentclientid',
            ),
            'attributes' => array(
                'required' => true,
                'class' => 'form-control input-sm',
                'style' => 'width: 150px;',
            )
        ));
        $this->get('submit')->setAttribute('label', 'Create');
    }

    public function setCreateOptions(UserCreateOptionsInterface $createOptionsOptions) {
        $this->createOptions = $createOptionsOptions;
        return $this;
    }

    public function getCreateOptions() {
        return $this->createOptions;
    }

    public function setServiceManager($serviceManager) {
        $this->serviceManager = $serviceManager;
    }

    public function getServiceManager() {
        return $this->serviceManager;
    }

}
