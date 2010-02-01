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


class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initAutoload()
    {
        $moduleLoader = new Zend_Application_Module_Autoloader(array(
            'namespace' => '',
            'basePath'  => APPLICATION_PATH
        ));
        return $moduleLoader;
    }
}

/**
 * Recursively copy one directory to another.
 *
 * @param string $source The source path to copy.
 * @param string $destination The path to copy to.
 */
function recursiveCopy($source, $destination)
{
    mkdir($destination);

    $sourceDir = dir($source);

    while (false !== ($entry = $sourceDir->read())) {
        if ($entry != '.' && $entry != '..') {
            if (is_dir($source . '/' . $entry)) {
                recursiveCopy($source . '/' . $entry, $destination . '/' . $entry);
            } else {
                copy($source . '/' . $entry, $destination . '/' . $entry);
            }
        }
    }
}

/**
 * Recursively delete a directory.
 *
 * @param string $path The directory path to delete.
 */
function recursiveDelete($path)
{
    if (is_dir($path)) {
        $directory = dir($path);

        while (false !== ($entry = $directory->read())) {
            if ($entry != '.' && $entry != '..') {
                recursiveDelete($path . '/' . $entry);
            }
        }

        rmdir($path);
    } else {
        unlink($path);
    }
}

/*
 * Make sure we can load SoftLayer_ classes.
 */
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();
$loader->registerNamespace('SoftLayer_');

/*
 * Define common elements.
 */
define('SKIN_PATH', realpath(APPLICATION_PATH . '/../public/skins'));
define('LANGUAGE_PATH', realpath(APPLICATION_PATH . '/../data/languages'));
define('CONFIG_PATH', APPLICATION_PATH . '/configs');

/*
 * Let's fire 'er up!
 */
Zend_Session::start();
$currentUser = new Zend_Session_Namespace('currentUser');

/*
 * Pull global site settings
 */
$config = new Zend_Config_Ini(CONFIG_PATH . '/settings.ini', 'production');
Zend_Registry::set('config', $config);

/*
 * Set view helpers
 */
$doctypeHelper = new Zend_View_Helper_Doctype();
$doctypeHelper->doctype('XHTML1_TRANSITIONAL');

$metaHelper = new Zend_View_Helper_HeadMeta();
$metaHelper->appendHttpEquiv('Content-Type', 'text/html; charset=UTF-8');
$metaHelper->appendHttpEquiv('pragma', 'no-cache');
$metaHelper->appendHttpEquiv('Cache-Control', 'no-cache');

$titleHelper = new Zend_View_Helper_HeadTitle();
$titleHelper->setSeparator(' - ');
$titleHelper->headTitle($config->defaults->title);

/*
 * Set up translators.
 */
$language = ($currentUser->language == null) ? $config->defaults->language : $currentUser->language;
$translate = new Zend_Translate('csv', LANGUAGE_PATH . '/' . $language . '.csv', $language);
Zend_Registry::set('Zend_Translate', $translate);

/*
 * Set the skin. If the skin doesn't exist then set the default skin.
 */
$skin = ($currentUser->skin == null) ? $config->defaults->skin : $currentUser->skin;

try {
    require_once APPLICATION_PATH . '/models/Skin.php';
    $skinTest = new Model_Skin($skin);
    unset($skinTest);
} catch (Exception $e) {
    $skin = 'default';
}

Zend_Registry::set('skin', $skin);

/*
 * Avoid users having to set up mod_rewrite rules to get to /index.php/ URLs.
 * This also lets them set their docroots to something other than the public/
 * directory, a bad idea, but it's their bad idea to have.
 */
$baseUrl = str_replace('//', '/', dirname($_SERVER['SCRIPT_NAME']) . '/index.php');
$baseUrlDir = (dirname($baseUrl) == '/') ? '' : dirname($baseUrl);
$controller = Zend_Controller_Front::getInstance();
$controller->setBaseUrl($baseUrl);
Zend_Registry::set('baseUrlDir', $baseUrlDir);

/*
 * Clean up before we process the page
 */
unset($currentUser);
unset($controller);
