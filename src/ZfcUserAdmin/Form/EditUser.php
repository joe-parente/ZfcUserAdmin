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
                        'style' => 'margin: .7em;',
                    ),
                    'options' => array(
                        'label' => 'User role',
                        'label_attributes' => ['style' => 'margin-top: .3em;' ],
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
                        'label_attributes' => ['style="margin-right: 1em']
                    ),
                )
        );
        $this->add(
                array(
                    'type' => 'Zend\Form\Element\Checkbox',
                    'name' => 'emailNotification',
                    'attributes' => array(
                        'style' => 'display: inline;',
                    ),
                    'options' => array(
                        'label' => 'Receives Invoice Notification:',
                        'label_attributes' => ['style="margin-right: 1em']
                    ),
                )
        );
        foreach ($this->getUserEditOptions()->getEditFormElements() as $name => $element) {
            // avoid adding fields twice (e.g. email)
            //if ($this->get($element)) continue;
            if ($name == 'Zip') {
                $this->add([
                    'type' => 'Zend\Form\Element\Select',
                    'name' => 'addressstate',
                    'options' => Array(
                        'label' => 'State',
                        'value_options' => Array(
                            "AL" => 'Alabama',
                            "AK" => 'Alaska',
                            "AZ" => 'Arizona',
                            "AR" => 'Arkansas',
                            "CA" => 'California',
                            "CO" => 'Colorado',
                            "CT" => 'Connecticut',
                            "DE" => 'Delaware',
                            "DC" => 'District Of Columbia',
                            "FL" => 'Florida',
                            "GA" => 'Georgia',
                            "HI" => 'Hawaii',
                            "ID" => 'Idaho',
                            "IL" => 'Illinois',
                            "IN" => 'Indiana',
                            "IA" => 'Iowa',
                            "KS" => 'Kansas',
                            "KY" => 'Kentucky',
                            "LA" => 'Louisiana',
                            "ME" => 'Maine',
                            "MD" => 'Maryland',
                            "MA" => 'Massachusetts',
                            "MI" => 'Michigan',
                            "MN" => 'Minnesota',
                            "MS" => 'Mississippi',
                            "MO" => 'Missouri',
                            "MT" => 'Montana',
                            "NE" => 'Nebraska',
                            "NV" => 'Nevada',
                            "NH" => 'New Hampshire',
                            "NJ" => 'New Jersey',
                            "NM" => 'New Mexico',
                            "NY" => 'New York',
                            "NC" => 'North Carolina',
                            "ND" => 'North Dakota',
                            "OH" => 'Ohio',
                            "OK" => 'Oklahoma',
                            "OR" => 'Oregon',
                            "PA" => 'Pennsylvania',
                            "RI" => 'Rhode Island',
                            "SC" => 'South Carolina',
                            "SD" => 'South Dakota',
                            "TN" => 'Tennessee',
                            "TX" => 'Texas',
                            "UT" => 'Utah',
                            "VT" => 'Vermont',
                            "VA" => 'Virginia',
                            "WA" => 'Washington',
                            "WV" => 'West Virginia',
                            "WI" => 'Wisconsin',
                            "WY" => 'Wyoming',
                        ))]
                );
            }
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
        
        $this->add([
            'name' => 'cancel',
            'type' => 'Zend\Form\Element\Button',
            'attributes' => [
                'onclick' => 'window.location = "/admin/user/list";',
                'value' => 'Cancel',
                'style' => 'display: inline; float: right; margin-right: 24em; margin-top: -1.825em;'
            ],
            'options' => [
                'label' => 'Cancel',
            ],
                ], [
            'priority' => -1000,]
        );

        $this->add([
            'name' => 'delete',
            'type' => 'Zend\Form\Element\Button',
            'attributes' => [
                'onclick' => "confirmRemove('/admin/user/remove/' + $(\"input[name='userId']\").val())",
                'value' => 'Delete this User',
                'style' => 'display: inline; float: right; margin-right: 10em; padding-left: -10em;'
            ],
            'options' => [
                'label' => 'Delete This User',
            ]
        ]);
        $this->add(array(
            'type' => 'Zend\Form\Element\Hidden',
            'name' => 'userId',
            'attributes' => array(
                'type' => 'hidden'
            ),
        ));
        $this->add([
            'type' => 'Zend\Form\Element\Hidden',
            'name' => 'parent_list',
            'attributes' => [
                'value' => '',
                'id' => 'parent_list',
            ],
        ]);
        $this->add([
            'type' => 'Zend\Form\Element\Hidden',
            'name' => 'department_list',
            'attributes' => [
                'value' => '',
                'id' => 'department_list',
            ],
        ]);
        $this->add([
            'type' => 'Zend\Form\Element\Hidden',
            'name' => 'region_list',
            'attributes' => [
                'value' => '',
                'id' => 'region_list',
            ],
        ]);
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
