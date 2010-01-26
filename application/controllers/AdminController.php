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
 * Control all Stratos-related administrative functions.
 *
 * Change things like users, skins, languages, and general configuration.
 * Administrative pages may only be accessed by users with the isAdmin flag
 * set.
 *
 * @author      SoftLayer Technologies, Inc. <sldn@softlayer.com>
 * @copyright   Copyright (c) 2010, Softlayer Technologies, Inc
 * @license     http://sldn.softlayer.com/wiki/index.php/License
 */
class AdminController extends Zend_Controller_Action
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
         * Only admin users can view pages from the admin controller.
         */
        if (!$currentUser->isAdmin) {
            $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'index', 'action' => 'index'));
        }

        /*
         * Set common view elements.
         */
        $this->view->translate = Zend_Registry::get('Zend_Translate');
        $this->view->baseUrl = $this->getRequest()->getBaseUrl();
    }

    /**
     * General application configuration.
     *
     * Present a screen to the user allowing them to choose the title for every
     * page, select a default skin, and select a default language.
     */
    public function indexAction()
    {
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

        /*
         * Form ahoy.
         */
        $form = new Zend_Form();
        $form->setMethod('post');

        $title = $form->createElement('text', 'title');
        $title->setLabel(ucfirst($this->view->translate->_('default title')));
        $title->setRequired(true);
        $title->setValue($config->defaults->title);

        $skin = $form->createElement('select', 'skin');
        $skin->setLabel(ucfirst($this->view->translate->_('default skin')));
        $skin->addMultiOptions($skinList);
        $skin->setValue($config->defaults->skin);
        $skin->setRequired(true);

        $language = $form->createElement('select', 'language');
        $language->setLabel(ucfirst($this->view->translate->_('default language')));
        $language->addMultiOptions($languageList);
        $language->setValue($config->defaults->language);
        $language->setRequired(true);

        $form->addElement($title);
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
                 * Modify the config.
                 */
                $config = new Zend_Config_Ini(CONFIG_PATH . '/settings.ini', 'production', array('allowModifications' => true));

                $config->defaults->title = $form->getValue('title');
                $config->defaults->skin = $form->getValue('skin');
                $config->defaults->language = $form->getValue('language');

                $writer = new Zend_Config_Writer_Ini(array(
                    'config' => $config,
                    'filename' => CONFIG_PATH . '/settings.ini'));
                $writer->write();

                $this->view->statusMessage = $this->view->translate->_('Configuration changes saved.');
            } else {
                $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
            }

            $form->populate($formData);
        }

        $this->view->pageTitle = $this->view->translate->_('Administration');
        $this->view->headTitle($this->view->translate->_('Administration'));
        $this->view->form = $form;
    }

    /**
     * Present the add user form and get a list of locally configured users.
     */
    public function usersAction()
    {
        /*
         * Build the add form.
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

        $isAdmin = $form->createElement('checkbox', 'isAdmin');
        $isAdmin->setLabel(ucfirst($this->view->translate->_('administrator')));

        $form->addElement($username);
        $form->addElement($apiKey);
        $form->addElement($skin);
        $form->addElement($language);
        $form->addElement($isAdmin);
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
                        $user = Model_DbTable_User::addUser($form->getValue('username'), $form->getValue('apiKey'), $form->getValue('skin'), $form->getValue('language'), $form->getValue('isAdmin'));
                        $this->view->statusMessage = $this->view->translate->_('User added.');
                    } catch (Exception $e) {
                        $this->view->errorMessage = $this->view->translate->_('Unable to add user.') . ' ' . $e->getMessage();
                    }
                }
            } else {
               $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
            }

            $form->populate($formData);
        }

        /*
         * Get a user list.
         */
        $users = array();

        try {
            $users = Model_DbTable_User::getAllUsers();
        } catch (Exception $e) {
            $this->view->errorMessage = $this->view->translate->_('Unable to load user list.' . ' ' . $e->getMessage());
        }

        $this->view->pageTitle = $this->view->translate->_('Users');
        $this->view->headTitle($this->view->translate->_('Users'));
        $this->view->users = $users;
        $this->view->form = $form;
    }

    /**
     * Edit a local user.
     *
     * This does not affect the user's corresponding SoftLayer user account.
     */
    public function edituserAction()
    {
        $user = null;

        /*
         * Get user info.
         */
        try {
            $user = new Model_DbTable_User($this->_getParam('id'));
        } catch (Exception $e) {
            $this->view->errorMessage = $this->translate->_('Unable to locate user.') . ' ' . $e->getMessage();
        }

        if ($user != null) {
            /*
             * Build the delete form. Only show it if the user isn't trying to
             * delete themselves.
             */
            $deleteForm = null;

            if ($user->id != $this->view->currentUser->id) {
                $deleteForm = new Zend_Form();
                $deleteForm->setMethod('post');

                $hidden = $deleteForm->createElement('hidden', 'mode');
                $hidden->setValue('delete');

                $deleteForm->addElement($hidden);
                $deleteForm->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));
            }

            /*
             * Build the edit form.
             */
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
            $username->setValue($user->username);

            $apiKey = $form->createElement('text', 'apiKey');
            $apiKey->setLabel(ucfirst($this->view->translate->_('API key')));
            $apiKey->setRequired(true);
            $apiKey->addValidator('alnum');
            $apiKey->setValue($user->apiKey);

            $skin = $form->createElement('select', 'skin');
            $skin->setLabel(ucfirst($this->view->translate->_('skin')));
            $skin->addMultiOptions($skinList);
            $skin->setValue($user->skin);
            $skin->setRequired(true);

            $language = $form->createElement('select', 'language');
            $language->setLabel(ucfirst($this->view->translate->_('language')));
            $language->addMultiOptions($languageList);
            $language->setValue($user->language);
            $language->setRequired(true);

            $isAdmin = $form->createElement('checkbox', 'isAdmin');
            $isAdmin->setLabel(ucfirst($this->view->translate->_('administrator')));
            $isAdmin->setChecked($user->isAdmin);

            $hidden = $form->createElement('hidden', 'mode');
            $hidden->setValue('edit');

            $form->addElement($username);
            $form->addElement($apiKey);
            $form->addElement($skin);
            $form->addElement($language);
            $form->addElement($isAdmin);
            $form->addElement($hidden);
            $form->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

            /*
             * Process form submission.
             */
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();

                /*
                 * Delete the user.
                 */
                if ($formData['mode'] == 'delete') {
                    /*
                     * Users may not delete themselves.
                     */
                    if ($user->id == $this->view->currentUser->id) {
                        $this->view->errorMessage = $this->view->translate->_('You may not delete your user account.');
                    } else {
                        try {
                            $user->deleteUser();
                            $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'admin', 'action' => 'users', 'id' => null));
                        } catch (Exception $e) {
                            $this->view->errorMessage = $this->view->translate->_('Unable to delete user.') . ' ' . $e->getMessage();
                        }
                    }

                /*
                 * Edit the user.
                 */
                } else {
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
                         * If the user is editing themself then make sure they
                         * don't take away their own admin privileges.
                         */
                        if ($user->id == $this->view->currentUser->id && $form->getValue('isAdmin') != $this->view->currentUser->isAdmin) {
                            $account = null;
                            $this->view->errorMessage = $this->view->translate->_('You may not change your administrative status.');
                        }

                        /*
                         * Update the user.
                         */
                        if ($account != null) {
                            try {
                                $user->updateUser($form->getValue('username'), $form->getValue('apiKey'), $form->getValue('skin'), $form->getValue('language'), $form->getValue('isAdmin'));
                                $this->view->statusMessage = $this->view->translate->_('User saved.');
                            } catch (Exception $e) {
                                $this->view->errorMessage = $this->view->translate->_('Unable to save user.') . ' ' . $e->getMessage();
                            }
                        }
                    } else {
                       $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
                    }
                }

                $form->populate($formData);
            }

            $this->view->pageTitle = $this->view->translate->_('Edit') . ' ' . $user->username;
            $this->view->headTitle($this->view->translate->_('Edit') . ' ' . $user->username);
            $this->view->deleteForm = $deleteForm;
            $this->view->form = $form;
        }

        $this->view->user = $user;

    }

    public function skinsAction()
    {
        /*
         * Build the add form.
         */
        $config = Zend_Registry::get('config');
        $skins = Model_Skin::getAllSkins();

        /*
         * Turn the skin list into something more Zend_Form friendly.
         */
        foreach ($skins as $skin) {
            $skinList[$skin->name] = $skin->name;
        }

        $form = new Zend_Form();
        $form->setMethod('post');

        $name = $form->createElement('text', 'name');
        $name->setLabel(ucfirst($this->view->translate->_('name')));
        $name->setRequired(true);
        $name->addValidator('alnum');

        $baseSkinName = $form->createElement('select', 'baseSkinName');
        $baseSkinName->setLabel(ucfirst($this->view->translate->_('base skin')));
        $baseSkinName->addMultiOptions($skinList);
        $baseSkinName->setRequired(true);

        $form->addElement($name);
        $form->addElement($baseSkinName);
        $form->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

        /*
         * Process form submission.
         */
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();

            if ($form->isValid($formData)) {
                /*
                 * Add the skin.
                 */
                try {
                    $skin = Model_Skin::addSkin($form->getValue('name'), $form->getValue('baseSkinName'));
                    $this->view->statusMessage = $this->view->translate->_('Skin added.');
                    $skins = Model_Skin::getAllSkins();
                } catch (Exception $e) {
                    $this->view->errorMessage = $this->view->translate->_('Unable to add skin.') . ' ' . $e->getMessage();
                }
            } else {
               $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
            }

            $form->populate($formData);
        }

        $this->view->pageTitle = $this->view->translate->_('Skins');
        $this->view->headTitle($this->view->translate->_('Skins'));
        $this->view->skins = $skins;
        $this->view->form = $form;
        $this->view->config = $config;
    }

    public function editskinAction()
    {
        $skin = null;
        $config = Zend_Registry::get('config');

        /*
         * Retrieve the skin.
         */
        try {
            $skin = new Model_Skin($this->_getParam('name'));
        } catch (Exception $e) {
            $this->view->errorMessage = $this->translate->_('Unable to locate skin.') . ' ' . $e->getMessage();
        }

        if ($skin != null) {
            /*
             * Build the delete form. Only show it if the skin is not the default system skin.
             */
            $deleteForm = null;

            if ($skin->name != $config->defaults->skin) {
                $deleteForm = new Zend_Form();
                $deleteForm->setMethod('post');

                $hidden = $deleteForm->createElement('hidden', 'mode');
                $hidden->setValue('delete');

                $deleteForm->addElement($hidden);
                $deleteForm->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));
            }

            /*
             * Build the edit forms. One for the logo, one for the css.
             */
            $skins = Model_Skin::getAllSkins();

            $logoForm = new Zend_Form();
            $logoForm->setMethod('post');
            $logoForm->setAttrib('enctype', 'multipart/form-data');

            $logo = $logoForm->createElement('file', 'logo');
            $logo->setLabel(ucfirst($this->view->translate->_('new logo')));
            $logo->addValidator('Count', false, 1);
            $logo->setRequired(true);
            $logo->addValidator('Extension', false, 'png');
            $logo->setDestination($skin->path . '/images');

            $hidden = $logoForm->createElement('hidden', 'mode');
            $hidden->setValue('logo');

            $logoForm->addElement($logo);
            $logoForm->addElement($hidden);
            $logoForm->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

            $cssForm = new Zend_Form();
            $cssForm->setMethod('post');

            $css = $cssForm->createElement('textarea', 'css');
            $css->setRequired(true);
            $css->setValue($skin->getCssContent());
            $css->setAttrib('rows','24');
            $css->setAttrib('cols','80');
            $css->setAttrib('style', 'margin-left: 60px;');

            $hidden = $cssForm->createElement('hidden', 'mode');
            $hidden->setValue('css');

            $cssForm->addElement($css);
            $cssForm->addElement($hidden);
            $cssForm->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

            /*
             * Process form submission.
             */
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();

                switch($formData['mode']) {
                    /*
                     * Delete the skin.
                     */
                    case 'delete':
                        /*
                         * You may not delete the default system skin.
                         */
                        if ($skin->name == $config->defaults->skin) {
                            $this->view->errorMessage = $this->view->translate->_('You may not delete the default system skin.');
                        } else {
                            try {
                                $skin->deleteSkin();
                                $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'admin', 'action' => 'skins', 'id' => null, 'name' => null));
                            } catch (Exception $e) {
                                $this->view->errorMessage = $this->view->translate->_('Unable to delete skin.') . ' ' . $e->getMessage();
                            }
                        }

                        break;

                    /*
                     * Change the logo.
                     */
                    case 'logo':
                        if ($logoForm->isValid($formData)) {
                            if ($logoForm->logo->receive()) {
                                rename($logoForm->logo->getFileName(), $skin->path . '/images/logo.png');
                                $this->view->statusMessage = $this->view->translate->_('Skin saved.');
                            } else {
                                $this->view->errorMessage = $this->view->translate->_('Unable to save skin.') . ' ' . $e->getMessage();
                            }
                        } else {
                           $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
                        }

                        break;

                    /*
                     * Change the CSS
                     */
                    case 'css':
                        if ($cssForm->isValid($formData)) {
                            try {
                                $skin->updateCss($cssForm->getValue('css'));
                                $this->view->statusMessage = $this->view->translate->_('Skin saved.');
                            } catch (Exception $e) {
                                $this->view->errorMessage = $this->view->translate->_('Unable to save skin.') . ' ' . $e->getMessage();
                            }
                        } else {
                           $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
                        }

                        $cssForm->populate($formData);
                        break;
                }
            }

            $this->view->pageTitle = $this->view->translate->_('Edit') . ' ' . $skin->name;
            $this->view->headTitle($this->view->translate->_('Edit') . ' ' . $skin->name);
            $this->view->logoForm = $logoForm;
            $this->view->cssForm = $cssForm;
            $this->view->deleteForm = $deleteForm;
        }

        $this->view->skin = $skin;
    }

    public function languagesAction()
    {
        /*
         * Build the add form.
         */
        $config = Zend_Registry::get('config');
        $languages = Model_Language::getAllLanguages();

        /*
         * Turn the language list into something more Zend_Form friendly.
         */
        foreach ($languages as $language) {
            $languageList[$language->name] = $language->name;
        }

        $form = new Zend_Form();
        $form->setMethod('post');

        $name = $form->createElement('text', 'name');
        $name->setLabel(ucfirst($this->view->translate->_('name')));
        $name->setRequired(true);
        $name->addValidator('alnum');

        $baseLanguageName = $form->createElement('select', 'baseLanguageName');
        $baseLanguageName->setLabel(ucfirst($this->view->translate->_('base language')));
        $baseLanguageName->addMultiOptions($languageList);
        $baseLanguageName->setRequired(true);

        $form->addElement($name);
        $form->addElement($baseLanguageName);
        $form->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

        /*
         * Process form submission.
         */
        if ($this->getRequest()->isPost()) {
            $formData = $this->getRequest()->getPost();

            if ($form->isValid($formData)) {
                /*
                 * Add the language.
                 */
                try {
                    $language = Model_Language::addLanguage($form->getValue('name'), $form->getValue('baseLanguageName'));
                    $this->view->statusMessage = $this->view->translate->_('Language added.');
                    $languages = Model_Language::getAllLanguages();
                } catch (Exception $e) {
                    $this->view->errorMessage = $this->view->translate->_('Unable to add language.') . ' ' . $e->getMessage();
                }
            } else {
               $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
            }

            $form->populate($formData);
        }

        $this->view->pageTitle = $this->view->translate->_('Languages');
        $this->view->headTitle($this->view->translate->_('Languages'));
        $this->view->languages = $languages;
        $this->view->form = $form;
        $this->view->config = $config;
    }

public function editlanguageAction()
    {
        $language = null;
        $config = Zend_Registry::get('config');

        /*
         * Retrieve the language.
         */
        try {
            $language = new Model_Language($this->_getParam('name'));
        } catch (Exception $e) {
            $this->view->errorMessage = $this->translate->_('Unable to locate language.') . ' ' . $e->getMessage();
        }

        if ($language != null) {
            /*
             * Build the delete form. Only show it if the language is not the default system language.
             */
            $deleteForm = null;

            if ($language->name != $config->defaults-> language) {
                $deleteForm = new Zend_Form();
                $deleteForm->setMethod('post');

                $hidden = $deleteForm->createElement('hidden', 'mode');
                $hidden->setValue('delete');

                $deleteForm->addElement($hidden);
                $deleteForm->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));
            }

            /*
             * Build the edit forms. One for the logo, one for the css.
             */
            $languages = Model_Language::getAllLanguages();

            $form = new Zend_Form();
            $form->setMethod('post');

            $lang = $form->createElement('textarea', 'language');
            $lang->setRequired(true);
            $lang->setValue($language->getLanguageContent());
            $lang->setAttrib('rows','24');
            $lang->setAttrib('cols','80');
            $lang->setAttrib('style', 'margin-left: 60px;');

            $hidden = $form->createElement('hidden', 'mode');
            $hidden->setValue('edit');

            $form->addElement($lang);
            $form->addElement($hidden);
            $form->addElement('submit', 'submit', array('label' => $this->view->translate->_('Submit')));

            /*
             * Process form submission.
             */
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();

                switch($formData['mode']) {
                    /*
                     * Delete the language.
                     */
                    case 'delete':
                        /*
                         * You may not delete the default system language.
                         */
                        if ($language->name == $config->defaults->language) {
                            $this->view->errorMessage = $this->view->translate->_('You may not delete the default system language.');
                        } else {
                            try {
                                $language->deleteLanguage();
                                $this->_helper->_redirector->goToRouteAndExit(array('controller' => 'admin', 'action' => 'languages', 'id' => null, 'name' => null));
                            } catch (Exception $e) {
                                $this->view->errorMessage = $this->view->translate->_('Unable to delete language.') . ' ' . $e->getMessage();
                            }
                        }

                        break;

                    /*
                     * Change the language's content.
                     */
                    case 'edit':
                        if ($form->isValid($formData)) {
                            try {
                                $language->updateLanguage($form->getValue('language'));
                                $this->view->statusMessage = $this->view->translate->_('Language saved.');
                            } catch (Exception $e) {
                                $this->view->errorMessage = $this->view->translate->_('Unable to save language.') . ' ' . $e->getMessage();
                            }
                        } else {
                           $this->view->errorMessage = $this->view->translate->_('Please completely fill out the configuration form.');
                        }

                        $form->populate($formData);
                        break;
                }
            }

            $this->view->pageTitle = $this->view->translate->_('Edit') . ' ' . $language->name;
            $this->view->headTitle($this->view->translate->_('Edit') . ' ' . $language->name);
            $this->view->form = $form;
            $this->view->deleteForm = $deleteForm;
        }

        $this->view->language = $language;
    }
}
