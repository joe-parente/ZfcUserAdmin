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

        $this->add(
                array(
                    'type' => 'DoctrineORMModule\Form\Element\EntityRadio',
                    'name' => 'roles',
                    'attributes' => array(
                        'multiple' => false,
                        'style' => 'margin: .7em;',
                    ),
                    'options' => array(
                        'object_manager' => $objectManager,
                        'target_class' => 'Application\Entity\Role',
                        'property' => 'roleId',
                        'label' => 'Role:',
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
        $this->add(
                array(
                    'type' => 'DoctrineModule\Form\Element\ObjectSelect',
                    'name' => 'name',
                    'options' => array(
                        'object_manager' => $objectManager,
                        'target_class' => 'Application\Entity\Regionxref',
                        'property' => 'r5wRegionname',
                        'label' => 'Region',
                        'display_empty_item' => true,
                        'empty_item_label' => 'Select Region'
                    ),
                    'attributes' => [
                        'onChange' => 'loadParentGrid($(this).val());',
                    ],
                )
        );
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
//        $this->add(array(
//            'name' => 'parentclientid',
//            'type' => 'Application\Form\Element\DtgAutocompleteElement',
//            'options' => array(
//                'label' => 'Parent Client',
//                'sm' => $serviceManager, // don't forget to send Service Manager
//                'property' => 'parentclientid',
//            ),
//            'attributes' => array(
//                'required' => true,
//                'class' => 'form-control input-sm',
//                'style' => 'width: 150px;',
//            )
//        ));
        $this->get('submit')->setAttribute('label', 'Create');
        $this->add([
            'name' => 'cancel',
            'type' => 'Zend\Form\Element\Button',
            'attributes' => [
                'onclick' => 'window.location = "/admin/user/list";',
                'value' => 'Cancel',
            ],
            'options' => [
                'label' => 'Cancel',
            ]
        ]);
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
