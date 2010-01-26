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
 * Show the user dashboard.
 *
 * Users see ths page after they log in.
 *
 * @author      SoftLayer Technologies, Inc. <sldn@softlayer.com>
 * @copyright   Copyright (c) 2010, Softlayer Technologies, Inc
 * @license     http://sldn.softlayer.com/wiki/index.php/License
 */
class IndexController extends Zend_Controller_Action
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

    public function indexAction()
    {
        $client = SoftLayer_SoapClient::getClient('SoftLayer_Account');

        $objectMask = new SoftLayer_ObjectMask();
        $objectMask->hardwareCount;
        $objectMask->virtualGuestCount;

        /*
         * Bandwidth usage
         */
        $objectMask->hardware->billingCyclePublicBandwidthUsage;
        $objectMask->hardware->billingItem->bandwidthAllocation;
        $objectMask->virtualGuests->billingCyclePublicBandwidthUsage;
        $objectMask->virtualGuests->billingItem->bandwidthAllocation;

        /*
         * Monitoring status
         */
        $objectMask->networkMonitorUpHardwareCount;
        $objectMask->networkMonitorRecoveringHardwareCount;
        $objectMask->networkMonitorDownHardwareCount;
        $objectMask->networkMonitorUpVirtualGuestCount;
        $objectMask->networkMonitorRecoveringVirtualGuestCount;
        $objectMask->networkMonitorDownVirtualGuestCount;

        $client->setObjectMask($objectMask);

        try {
            $account = $client->getObject();
        } catch (Exception $e) {
            $this->view->errorMessage = 'Unable to retrieve account information. ' . $e->getMessage();
        }

        /*
         * Calculate servers and CCIs over 85% and 100% bandwidth.
         */
        $serversOver85PercentBandwidth = 0;
        $serversOver100PercentBandwidth = 0;
        $instancesOver85PercentBandwidth = 0;
        $instancesOver100PercentBandwidth = 0;

        foreach ($account->hardware as $server) {
            if (isset($server->billingCyclePublicBandwidthUsage)
                && isset($server->billingItem)
                && isset($server->billingItem->bandwidthAllocation)
                && $server->billingItem->bandwidthAllocation->amount > 0) {

                $usagePercent = $server->billingCyclePublicBandwidthUsage->amountOut / $server->billingItem->bandwidthAllocation->amount;
            } else {
                $usagePercent = 0;
            }

            if ($usagePercent >= 0.85) {
                $serversOver85PercentBandwidth++;
            } elseif ($usagePercent >= 1) {
                $serversOver100PercentBandwidth++;
            }
        }

        foreach ($account->virtualGuests as $server) {
            if (isset($server->billingCyclePublicBandwidthUsage)
                && isset($server->billingItem)
                && isset($server->billingItem->bandwidthAllocation)
                && $server->billingItem->bandwidthAllocation->amount > 0) {

                $usagePercent = $server->billingCyclePublicBandwidthUsage->amountOut / $server->billingItem->bandwidthAllocation->amount;
            } else {
                $usagePercent = 0;
            }

            if ($usagePercent >= 0.85) {
                $instancesOver85PercentBandwidth++;
            } elseif ($usagePercent >= 1) {
                $instancesOver100PercentBandwidth++;
            }
        }

        $this->view->pageTitle = ucfirst($this->view->translate->_('Home'));
        $this->view->headTitle(ucfirst($this->view->translate->_('Home')));
        $this->view->account = $account;
        $this->view->serversOver85PercentBandwidth = $serversOver85PercentBandwidth;
        $this->view->serversOver100PercentBandwidth = $serversOver100PercentBandwidth;
        $this->view->instancesOver85PercentBandwidth = $instancesOver85PercentBandwidth;
        $this->view->instancesOver100PercentBandwidth = $instancesOver100PercentBandwidth;
    }
}
