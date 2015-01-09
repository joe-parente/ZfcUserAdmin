<?php

namespace ZfcUserAdmin\Form;

use ZfcUser\Entity\UserInterface;
use ZfcUser\Form\Register;
use ZfcUser\Options\RegistrationOptionsInterface;
use ZfcUserAdmin\Options\UserEditOptionsInterface;
use Zend\Form\Form;
use Zend\Form\Element;

class EditUser extends Register {

    /**
     * @var \ZfcUserAdmin\Options\UserEditOptionsInterface
     */
    protected $userEditOptions;
    protected $userEntity;
    protected $serviceManager;

    public function __construct($name = null, UserEditOptionsInterface $options, RegistrationOptionsInterface $registerOptions, $serviceManager) {
        $this->setUserEditOptions($options);
        $this->setServiceManager($serviceManager);
        parent::__construct($name, $registerOptions);
        $objectManager = $this->serviceManager
                ->get('Doctrine\ORM\EntityManager');
        //$this->setHydrator(new \DoctrineModule\Stdlib\Hydrator\DoctrineObject($objectManager, 'Application\Entity\Client'));

        $this->remove('captcha');

        if ($this->userEditOptions->getAdminRandomPassword()) {
            $this->add(array(
                'name' => 'reset_password',
                'type' => 'Zend\Form\Element\Checkbox',
                'options' => array(
                    'label' => 'Reset password to random',
                ),
            ));
        }
        if ($this->userEditOptions->getAllowPasswordChange()) {
            $password = $this->get('password');
            $password->setAttribute('required', false);
            $password->setOptions(array('label' => 'Password (Enter only if you want to change)'));

            // $this->remove('passwordVerify');
        } else {
            $this->remove('password')->remove('passwordVerify');
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
                    'type' => 'DoctrineModule\Form\Element\ObjectSelect',
                    'name' => 'clients',
                    'attributes' => array(
                        'multiple' => true,
                    ),
                    'options' => array(
                        'object_manager' => $objectManager,
                        'target_class' => 'Application\Entity\Client',
                        'property' => 'name',
//                        'is_method' => true,
//                        'find_method' => array(
//                            'name' => 'clientsByUser',
//                            'params' => array(
//                                'criteria' => array('id' => $parent),
//                            ),
//                        )
                    )
        ));
//                $this->add(
//                array(
//                    'type' => 'DoctrineModule\Form\Element\ObjectSelect',
//                    'name' => 'dapartments',
//                    'attributes' => array(
//                        'multiple' => false,
//                    ),
//                    'options' => array(
//                        'object_manager' => $objectManager,
//                        'target_class' => 'Application\Entity\Client',
//                        'property' => 'name',
////                        'is_method' => true,
////                        'find_method' => array(
////                            'name' => 'clientsByUser',
////                            'params' => array(
////                                'criteria' => array('id' => $parent),
////                            ),
////                        )
//                    )
//        ));
                
        foreach ($this->getUserEditOptions()->getEditFormElements() as $name => $element) {
            // avoid adding fields twice (e.g. email)
            //if ($this->get($element)) continue;

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

        $this->get('submit')->setLabel('Save')->setValue('Save');

        $this->add(array(
            'name' => 'userId',
            'attributes' => array(
                'type' => 'hidden'
            ),
        ));
    }

    public function setUser($userEntity) {
        $this->userEntity = $userEntity;
        $this->getEventManager()->trigger('userSet', $this, array('user' => $userEntity));
    }

    public function getUser() {
        return $this->userEntity;
    }

    public function populateFromUser(UserInterface $user) {
        foreach ($this->getElements() as $element) {
            /** @var $element \Zend\Form\Element */
            $elementName = $element->getName();
            if (strpos($elementName, 'password') === 0)
                continue;

            $getter = $this->getAccessorName($elementName, false);
            if (method_exists($user, $getter))
                $element->setValue(call_user_func(array($user, $getter)));
        }

        foreach ($this->getUserEditOptions()->getEditFormElements() as $element) {
            $getter = $this->getAccessorName($element, false);
            $this->get($element)->setValue(call_user_func(array($user, $getter)));
        }
        $this->get('userId')->setValue($user->getId());
    }

    protected function getAccessorName($property, $set = true) {
        $parts = explode('_', $property);
        array_walk($parts, function (&$val) {
            $val = ucfirst($val);
        });
        return (($set ? 'set' : 'get') . implode('', $parts));
    }

    public function setUserEditOptions(UserEditOptionsInterface $userEditOptions) {
        $this->userEditOptions = $userEditOptions;
        return $this;
    }

    public function getUserEditOptions() {
        return $this->userEditOptions;
    }

    public function setServiceManager($serviceManager) {
        $this->serviceManager = $serviceManager;
    }

    public function getServiceManager() {
        return $this->serviceManager;
    }

}
