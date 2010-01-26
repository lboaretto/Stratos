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
 * Run the Stratos installer.
 *
 * Run a quick PHP and filesystem permission sheck then set up the first user.
 * Users should be re-directed here if they're not logged in and no users are
 * configured yet.
 *
 * @author      SoftLayer Technologies, Inc. <sldn@softlayer.com>
 * @copyright   Copyright (c) 2010, Softlayer Technologies, Inc
 * @license     http://sldn.softlayer.com/wiki/index.php/License
 */
class InstallController extends Zend_Controller_Action
{
    public function init()
    {
        /*
         * Handle the current user and login redirection.
         * If the user is logged in then redirect them to the index page.
         */
        $currentUser = new Zend_Session_Namespace('currentUser');
        $requestParameters = $this->getRequest()->getParams();

        if ($currentUser->username != null) {
            $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'index', 'action' => null));
        }

        /*
         * Set common view elements.
         */
        $this->view->translate = Zend_Registry::get('Zend_Translate');
        $this->view->baseUrl = $this->getRequest()->getBaseUrl();
    }

    /**
     * Make sure PHP is up to snuff, that we can write to the right places, and
     * make the first user.
     */
    public function indexAction()
    {
        $phpCheck = array(
            'PHP Version >= 5.2.3' => (version_compare(PHP_VERSION, '5.2.3') >= 0),
            'Standard Extension Loaded' => extension_loaded('standard'),
            'SOAP Extension Loaded' => extension_loaded('soap'),
            'PCRE Extension Loaded' => extension_loaded('pcre'),
            'PDO Extension Loaded' => extension_loaded('pdo'),
            'PDO SQLite Extension Loaded' => extension_loaded('pdo_sqlite'),
            'SPL Extension Loaded' => extension_loaded('spl'),
            'Session Extension Loaded' => extension_loaded('session'),
            'Ctype Extension Loaded' => extension_loaded('ctype'),
        );

        $systemCheck = array(
            'Languages Directory (' . LANGUAGE_PATH . ') Writable' => is_writable(LANGUAGE_PATH),
            'Skins Directory (' . SKIN_PATH . ') Writable' => is_writable(SKIN_PATH),
            'Database Directory (' . APPLICATION_PATH . '/../data/db) Writable' => is_writable(APPLICATION_PATH . '/../data/db'),
            'Configuration File (' . CONFIG_PATH . '/settings.ini' . ') Writable' => is_writable(CONFIG_PATH . '/settings.ini'),
        );

        /*
         * Show an error if there are any PHP or system errors.
         */
        $hasPhpErrors = false;
        $hasSystemErrors = false;

        foreach ($phpCheck as $check) {
            if (!$check) {
                $hasPhpErrors = true;
                break;
            }
        }

        foreach ($systemCheck as $check) {
            if (!$check) {
                $hasSystemErrors = true;
                break;
            }
        }

        if (!$hasPhpErrors && !$hasSystemErrors) {
            /*
             * Build the add user form.
             */
            $config = Zend_Registry::get('config');
            $skins = Model_Skin::getAllSkins();
            $languages = Model_Language::getAllLanguages();

            /*
             * Turn the skin and language lists into something more Zend_Form
             * friendly.
             */
            foreach ($skins as $skin) {
                $skinList[$skin->name] = $skin->name;
            }

            foreach ($languages as $language) {
                $languageList[$language->name] = $language->name;
            }

            $form = new Zend_Form();
            $form->setMethod('post');

            $username = $form->createElement('text', 'username');
            $username->setLabel(ucfirst($this->view->translate->_('username')));
            $username->setRequired(true);
            $username->addValidator('alnum');

            $apiKey = $form->createElement('text', 'apiKey');
            $apiKey->setLabel(ucfirst($this->view->translate->_('API key')));
            $apiKey->setRequired(true);
            $apiKey->addValidator('alnum');

            $skin = $form->createElement('select', 'skin');
            $skin->setLabel(ucfirst($this->view->translate->_('skin')));
            $skin->addMultiOptions($skinList);
            $skin->setValue($config->defaults->skin);
            $skin->setRequired(true);

            $language = $form->createElement('select', 'language');
            $language->setLabel(ucfirst($this->view->translate->_('language')));
            $language->addMultiOptions($languageList);
            $language->setValue($config->defaults->language);
            $language->setRequired(true);

            $form->addElement($username);
            $form->addElement($apiKey);
            $form->addElement($skin);
            $form->addElement($language);
            $form->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

            /*
             * Process form submission.
             */
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();

                if ($form->isValid($formData)) {
                    /*
                     * Try out the username and API key to make sure they
                     * entered a good one.
                     */
                    $account = null;
                    $client = SoftLayer_SoapClient::getClient('SoftLayer_Account', null, $form->getValue('username'), $form->getValue('apiKey'));

                    try {
                        $account = $client->getObject();
                    } catch (Exception $e) {
                        $this->view->errorMessage = $this->view->translate->_('Please enter a valid username and API key combination.');
                    }

                    /*
                     * Add the user.
                     */
                    if ($account != null) {
                        try {
                            $user = Model_DbTable_User::addUser($form->getValue('username'), $form->getValue('apiKey'), $form->getValue('skin'), $form->getValue('language'), true);
                            $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'index', 'action' => null));
                        } catch (Exception $e) {
                            $this->view->errorMessage = $this->view->translate->_('Unable to add user.') . ' ' . $e->getMessage();
                        }
                    }
                } else {
                   $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
                }

                $form->populate($formData);
            }

            $this->view->form = $form;
        }

        $this->view->pageTitle = 'Installation';
        $this->view->headTitle('Installation');

        $this->view->phpCheck = $phpCheck;
        $this->view->systemCheck = $systemCheck;
        $this->view->hasPhpErrors = $hasPhpErrors;
        $this->view->hasSystemErrors = $hasSystemErrors;
    }
}
