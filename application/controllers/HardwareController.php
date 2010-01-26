<?php
/**
 * Copyright (c) 2010, SoftLayer Technologies, Inc. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *  * Neither SoftLayer Technologies, Inc. nor the names of its contributors may
 *    be used to endorse or promote products derived from this software without
 *    specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * All hardware functions.
 *
 * Show and process requests for a list of hardware as well as any function you
 * can perform on a single server.
 *
 * @author      SoftLayer Technologies, Inc. <sldn@softlayer.com>
 * @copyright   Copyright (c) 2010, Softlayer Technologies, Inc
 * @license     http://sldn.softlayer.com/wiki/index.php/License
 */
class HardwareController extends Zend_Controller_Action
{
    public function init()
    {
        /*
         * Handle the current user and login redirection.
         * All pages except /user/login are redirected to /user/login if the
         * user is not logged in.
         */
        $currentUser = new Zend_Session_Namespace('currentUser');
        $requestParameters = $this->getRequest()->getParams();

        if ($requestParameters['controller'] != 'user' && $requestParameters['action'] != 'login') {
            if ($currentUser->username == null) {
                $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'user', 'action' => 'login'));
            } else {
                $this->view->currentUser = $currentUser;
                SoftLayer_SoapClient::setAuthenticationUser($currentUser->username, $currentUser->apiKey);
            }
        }

        /*
         * Set common view elements.
         */
        $this->view->translate = Zend_Registry::get('Zend_Translate');
        $this->view->baseUrl = $this->getRequest()->getBaseUrl();
    }

    /**
     * Get a list of servers on the account.
     *
     * Present a list of hardware to the user including hostname, domain,
     * public IP (if applicable), private IP, and monitoring state.
     */
    public function indexAction()
    {
        $client = SoftLayer_SoapClient::getClient('SoftLayer_Account');

        $objectMask = new SoftLayer_ObjectMask();
        $objectMask->hardware->datacenter;
        $objectMask->hardware->hardwareStatus;
        $objectMask->hardware->networkMonitors->lastResult;
        $client->setObjectMask($objectMask);

        try {
            $hardware = $client->getHardware();

            /*
             * Loop through the servers in our result and set their state to
             * down if any of their network monitors report down.
             */
            foreach ($hardware as $i => $server) {
                if (isset($server->networkMonitors) && count($server->networkMonitors) > 0) {
                    foreach ($server->networkMonitors as $monitor) {
                        if (isset($monitor->lastResult)) {
                            if (isset($monitor->lastResult->reponseStatus)) {
                                if ($monitor->lastResult->reponseStatus == 2) {
                                    $hardware[$i]->statusMessage = 'up';
                                } else {
                                    $hardware[$i]->statusMessage = 'down';
                                    break;
                                }
                            } else {
                                $hardware[$i]->statusMessage = 'pending';
                            }
                        } else {
                            $hardware[$i]->statusMessage = 'pending';
                        }
                    }
                } else {
                    $hardware[$i]->statusMessage = 'n/a';
                }
            }

        } catch (Exception $e) {
            $this->view->errorMessage = $this->view->translate->_('Unable to retrieve hardware list.') . ' ' . $e->getMessage();
        }

        $this->view->pageTitle = $this->view->translate->_('My hardware');
        $this->view->headTitle($this->view->translate->_('My hardware'));
        $this->view->hardware = $hardware;
    }

    /**
     * View a single server.
     *
     * Get general, hardware, software, public network, private network, and
     * service information for a single server denoted by the parameter 'id'.
     */
    public function viewAction()
    {
        $hardware = null;

        if ($this->_getParam('id') == null) {
            $this->view->errorMessage = $this->view->translate->_('Please provide a hardware id.');
        } else {
            $client = SoftLayer_SoapClient::getClient('SoftLayer_Hardware_Server', $this->_getParam('id'));

            /*
             * Grab a lot of data along with our hardware record.
             * General info:
             */
            $objectMask = new SoftLayer_ObjectMask();
            $objectMask->serverRoom;
            $objectMask->datacenter;
            $objectMask->hardwareStatus;
            $objectMask->provisionDate;
            $objectMask->lastOperatingSystemReload;

            /*
             * Hardware info:
             */
            $objectMask->components->hardwareComponentModel->hardwareGenericComponentModel->hardwareComponentType;
            $objectMask->components->attributes->hardwareComponentAttributeType;

            /*
             * Software info
             */
            $objectMask->softwareComponents->softwareLicense->softwareDescription->drivers;
            $objectMask->softwareComponents->passwords;
            $objectMask->operatingSystem;
            $objectMask->activeTransaction;

            /*
             * Public network info
             */
            $objectMask->frontendNetworkComponents->primarySubnet;
            $objectMask->frontentNetworkComponents->subnets;
            $objectMask->bandwidthAllocation;
            $objectMask->virtualRackName;
            $objectMask->nextBillingCycleBandwidthAllocation;

            /*
             * Private and management network info
             */
            $objectMask->backendNetworkComponents->primarySubnet;
            $objectMask->backendNetworkComponents->subnets;
            $objectMask->remoteManagementUsers;

            /*
             * Service info
             */
            $objectMask->businessContinuanceInsuranceFlag;
            $objectMask->firewallServiceComponent;
            $objectMask->account->nasNetworkStorage;
            $objectMask->account->iscsiNetworkStorage;
            $objectMask->account->evaultNetworkStorage;
            $objectMask->monitoringServiceComponent;

            $client->setObjectMask($objectMask);

            try {
                $hardware = $client->getObject();
            } catch (Exception $e) {
                $this->view->errorMessage = $this->view->translate('Error retrieving hardware record.') . ' ' . $e->getMessage();
            }

            if ($hardware != null) {
                /*
                 * Translate the hardware components into something more
                 * readable.
                 */
                $components = array();

                if (isset($hardware->components) && count($hardware->components) > 0) {
                    /*
                     * Components are sorted by type in this order:
                     */
                    $componentTypes = array(
                        3 =>  'Motherboard',
                        2 =>  'Processor',
                        4 =>  'RAM',
                        5 =>  'Drive Controller',
                        8 =>  'Network Card',
                        12 => 'Video Card',
                        13 => 'Battery',
                        1 =>  'Hard Drive',
                        14 => 'Remote Mgmt Card'
                    );

                    foreach ($componentTypes as $componentTypeId => $componentType) {
                        foreach ($hardware->components as $component) {
                            $description = null;

                            if ($component->hardwareComponentModel->hardwareGenericComponentModel->hardwareComponentTypeId == $componentTypeId) {
                                /*
                                 * Build a component description
                                 */
                                switch($componentTypeId) {
                                    /*
                                     * RAID cards get special treatment.
                                     */
                                    case 5:
                                        $component->hardwareComponentModel->version = preg_replace('/RAID-\d+|JBOD/i', 'RAID', $component->hardwareComponentModel->version);
                                        $description = $component->hardwareComponentModel->manufacturer . ' ' . $component->hardwareComponentModel->name . ' ' . $component->hardwareComponentModel->version;

                                        if (preg_match('/RAID/i', $component->hardwareComponentModel->description)) {
                                            $raidAttribute = null;

                                            if ($component->attributes) {
                                                foreach ($component->attributes as $attribute) {
                                                    if ($attribute->hardwareComponentAttributeType->keyName == 'RAID') {
                                                        $raidAttribute = $attribute;
                                                        break;
                                                    }
                                                }

                                                if ($raidAttribute && $raidAttribute->value) {
                                                    $description .= ' ' . $raidAttribute->hardwareComponentAttributeType->name . '-' . $raidAttribute->value;
                                                }
                                            }
                                        }

                                        break;
                                    default:
                                        $description = $component->hardwareComponentModel->manufacturer . ' ' . $component->hardwareComponentModel->name . ' ' . $component->hardwareComponentModel->version;
                                }

                                /*
                                 * Append a capacity if necessary.
                                 */
                                if (in_array($componentTypeId, array(2, 4, 1))) {
                                    $description .= ' (' . $component->hardwareComponentModel->capacity . $component->hardwareComponentModel->hardwareGenericComponentModel->units . ')';
                                }

                                $components[strtolower($componentType)][$description]++;
                            }
                        }
                    }

                    unset($hardware->components);
                    $hardware->componets = $components;
                    unset($components);
                }

                /*
                 * Translate software components
                 */
                if (count($hardware->softwareComponents) > 0) {
                    for ($operatingSystem = 1; $operatingSystem >= 0; $operatingSystem--) {
                        foreach ($hardware->softwareComponents as $component) {
                            if ($operatingSystem && $hardware->operatingSystem->id != $component->id) continue;
                            if (!$operatingSystem && $hardware->operatingSystem->id == $component->id) continue;

                            /*
                             * Get the type of software component.
                             *
                             * If we're looking at the operating system then
                             * save the drivers for later.
                             */
                            if ($hardware->operatingSystem->id == $component->id) {
                                $driverDescriptions = array();

                                if (count($component->softwareLicense->softwareDescription->drivers) > 0) {
                                    foreach ($component->softwareLicense->softwareDescription->drivers as $driver) {
                                        $driverDescriptions[] = $driver->description;
                                    }
                                }

                                $description = 'operating system';
                            } elseif ($component->softwareLicense->softwareDescription->manufacturer == 'eVault') {
                                $description = 'backup';
                            } elseif ($component->softwareLicense->softwareDescription->manufacturer == 'Passmark') {
                                $description = 'certification';
                            } elseif (stripos($component->softwareLicense->softwareDescription->name, 'Anti-Virus') !== false
                                || stripos($component->softwareLicense->softwareDescription->name, 'LinuxShield') !== false) {

                                $description = 'antivirus';
                            } elseif ($component->softwareLicense->softwareDescription->controlPanel) {
                                $description = 'control panel';
                            } elseif (stripos($component->softwareLicense->softwareDescription->name, 'SQL') !== false
                                || stripos($component->softwareLicense->softwareDescription->name, 'MySQL') !== false) {

                                $description = 'database';
                            } elseif (stripos($component->softwareLicense->softwareDescription->name, 'Firewall') !== false) {
                                $description = 'firewall';
                            }

                            /*
                             * Build the software name.
                             *
                             * Filter out the manufacturer if it's included in
                             * the name.
                             */
                            $name = '';

                            if (stristr($component->softwareLicense->softwareDescription->name, $component->softwareLicense->softwareDescription->manufacturer) === false) {
                                $name .= $component->softwareLicense->softwareDescription->manufacturer;
                            }

                            $name .= ' ' . $component->softwareLicense->softwareDescription->name
                                . ' ' . $component->softwareLicense->softwareDescription->version;

                            /*
                             * Build software passwords. Only display them if
                             * there's no active transaction running on this
                             * server.
                             */
                            $passwords = array();

                            if (!isset($hardware->activeTransaction->transactionGroup) && count($component->passwords) > 0) {
                                foreach ($component->passwords as $password) {
                                    $passwords[] = array(
                                        'username' => $password->username,
                                        'password' => $password->password,
                                    );
                                }
                            }

                            $softwareComponents[$description] = array(
                                'name' => $name,
                                'passwords' => $passwords,
                            );
                        }
                    }

                    unset($hardware->softwareComponents);
                    $hardware->softwareComponents = $softwareComponents;
                    unset($softwareComponents);
                }

                /*
                 * Public network munging.
                 */
                if (count($hardware->frontendNetworkComponents) > 0) {
                    $hardware->publicNetworkComponent = $hardware->frontendNetworkComponents[0];
                    unset($hardware->frontendNetworkComponents);
                }

                /*
                 * Determine which backend network component is for the private
                 * network and which is for the management network.
                 */
                foreach ($hardware->backendNetworkComponents as $networkComponent) {
                    if ($networkComponent->name == 'eth') {
                        $hardware->privateNetworkComponent = $networkComponent;
                    } elseif ($networkComponent->name == 'mgmt') {
                        $hardware->managementNetworkComponent = $networkComponent;
                    }
                }

                unset($hardware->backendNetworkComponents);
            }
        }

        if ($hardware != null) {
            $this->view->pageTitle = $hardware->hostname . '.' . $hardware->domain;
            $this->view->headTitle($hardware->hostname . '.' . $hardware->domain);
        }

        $this->view->hardware = $hardware;
    }

    /**
     * View sensor data for a single piece of hardware.
     */
    public function viewsensorsAction()
    {
        $hardware = null;
        $sensorData = null;

        if ($this->_getParam('id') == null) {
            $this->view->errorMessage = $this->view->translate->_('Please provide a hardware id.');
        } else {
            $client = SoftLayer_SoapClient::getClient('SoftLayer_Hardware_Server', $this->_getParam('id'));

            try {
                /*
                 * Asynchronous calls will execute faster.
                 */
                $hardware = $client->getObjectAsync();
                $sensorData = $client->getSensorDataWithGraphsAsync();

                $hardware = $hardware->wait();
                $sensorData = $sensorData->wait();
            } catch (Exception $e) {
                $this->view->errorMessage = $this->view->translate->_('Unable to retrieve hardware and sensor data.') . ' ' . $e->getMessage();
            }
        }

        if ($hardware != null && $sensorData != null) {
            $this->view->pageTitle = $hardware->hostname . '.' . $hardware->domain . ' ' . $this->view->translate->_('hardware sensors');
            $this->view->headTitle($hardware->hostname . '.' . $hardware->domain . ' ' . $this->view->translate->_('hardware sensors'));
        }

        $this->view->hardware = $hardware;
        $this->view->sensorData = $sensorData;
    }

    /**
     * Reboot a server
     *
     * Allow user reboot via management card, power strip, or both.
     */
    public function rebootAction() {
        $hardware = null;
        $rebootSuccessful = false;

        if ($this->_getParam('id') == null) {
            $this->view->errorMessage = $this->view->translate->_('Please provide a hardware id.');
        } else {
            $client = SoftLayer_SoapClient::getClient('SoftLayer_Hardware_Server', $this->_getParam('id'));

            /*
             * Build the reboot form.
             */
            $form = new Zend_Form();
            $form->setMethod('post');

            $rebootMethod = $form->createElement('select', 'rebootMethod');
            $rebootMethod->setLabel(ucfirst($this->view->translate->_('reboot via')));
            $rebootMethod->addMultiOptions(array(
                'rebootDefault' => $this->view->translate->_('management card with powerstrip fallback'),
                'rebootSoft' => $this->view->translate->_('management card only'),
                'powerCycle' => $this->view->translate->_('power strip only'),
            ));
            $rebootMethod->setRequired(true);

            $form->addElement($rebootMethod);
            $form->addElement('submit', 'reboot', array('label' => ucfirst($this->view->translate->_('reboot'))));

            /*
             * Get hardware info.
             */
            $objectMask = new SoftLayer_ObjectMask();
            $objectMask->recentRemoteManagementCommands;
            $client->setObjectMask($objectMask);

            try {
                $hardware = $client->getObject();
            } catch (Exception $e) {
                $this->view->errorMessage = $this->translate->_('Error retrieving hardware record.') . ' ' . $e->getMessage();
            }

            /*
             * Handle the reboot request.
             */
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();

                if ($form->isValid($formData)) {
                    try {
                        switch ($form->getValue('rebootMethod')) {
                            case 'rebootDefault':
                                $result = $client->rebootDefault();
                                break;
                            case 'rebootSoft':
                                $result = $client->rebootSoft();
                                break;
                            case 'powerCycle':
                                $result = $client->powerCycle();
                                break;
                        }

                        $rebootSuccessful = true;
                    } catch (Exception $e) {
                        $this->view->errorMessage = $this->view->translate->_('Reboot failed.') . ' ' . $e->getMessage();
                    }
                } else {
                    $this->view->errorMessage = $this->view->translate->_('Reboot failed.') . ' ' . $this->view->translate->_('Please completely fill out the reboot form.');
                    $form->populate($formData);
                }
            }
        }

        if ($hardware != null) {
            $this->view->pageTitle = ucfirst($this->view->translate->_('reboot')) . ' ' . $hardware->hostname . '.' . $hardware->domain;
            $this->view->headTitle(ucfirst($this->view->translate->_('reboot')) . ' ' . $hardware->hostname . '.' . $hardware->domain);
            $this->view->form = $form;
        }

        $this->view->rebootSuccessful = $rebootSuccessful;
        $this->view->hardware = $hardware;
    }
}

