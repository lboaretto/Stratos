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
 * User login and logout.
 *
 * @author      SoftLayer Technologies, Inc. <sldn@softlayer.com>
 * @copyright   Copyright (c) 2010, Softlayer Technologies, Inc
 * @license     http://sldn.softlayer.com/wiki/index.php/License
 */
class UserController extends Zend_Controller_Action
{

    public function init()
    {
        /*
         * If no users are installed then go to the installer page.
         */
        $allUsers = Model_DbTable_User::getAllUsers();

        if (count($allUsers) < 1) {
            $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'install', 'action' => 'index'));
        }

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
     * There is no index action for the user controller. Perform a no-op.
     */
    public function indexAction()
    {

    }

    /**
     * Present a login form and handle user authentication.
     */
    public function loginAction()
    {
        /*
         * Build the login form
         */
        $form = new Zend_Form();
        $form->setMethod('post');

        $username = $form->createElement('text', 'username');
        $username->setLabel($this->view->translate->_('Username'));
        $username->setRequired(true);
        $username->addValidator('alnum');

        $password = $form->createElement('password', 'password');
        $password->setLabel($this->view->translate->_('Password'));
        $password->setRequired(true);

        $form->addElement($username);
        $form->addElement($password);
        $form->addElement('submit', 'login', array('label' => $this->view->translate->_('Login')));

        /*
         * Handle authentication
         */
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();

            if ($form->isValid($formData)) {
                try {
                    Model_DbTable_User::authenticate($form->getValue('username'), $form->getValue('password'));

                    /*
                     * Set the current user session
                     */
                    $user = Model_DbTable_User::findByUsername($form->getValue('username'));

                    $currentUser = new Zend_Session_Namespace('currentUser');
                    $currentUser->id        = $user->id;
                    $currentUser->username  = $user->username;
                    $currentUser->apiKey    = $user->apiKey;
                    $currentUser->language  = $user->language;
                    $currentUser->skin      = $user->skin;
                    $currentUser->isAdmin   = $user->isAdmin;

                    /*
                     * Redirect back to the index page.
                     */
                    $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'index', 'action' => 'index'));
                } catch (Exception $e) {
                    $this->view->errorMessage = $this->view->translate->_('Login failed.') . ' ' . $e->getMessage();
                }
            } else {
                $this->view->errorMessage = $this->view->translate->_('Login failed.') . ' ' . $this->view->translate->_('Please completely fill out the login form.');
                $form->populate($formData);
            }
        }

        $this->view->headTitle($this->view->translate->_('Login'));
        $this->view->form = $form;
    }

    /**
     * Destroy the session and redirect the user back to the index page upon
     * logout.
     */
    public function logoutAction()
    {
        Zend_Session::destroy();
        $this->_redirect('/');
    }
}





