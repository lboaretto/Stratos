<?php
/**
 * Copyright (c) 2009, SoftLayer Technologies, Inc. All rights reserved.
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

require_once dirname(__FILE__) . '/Common/ObjectMask.class.php';
require_once dirname(__FILE__) . '/SoapClient/AsynchronousAction.class.php';

if (!extension_loaded('soap')) {
    throw new Exception('Please load the PHP SOAP extension.');
}

if (version_compare(PHP_VERSION, '5.2.3', '<')) {
    throw new Exception('The SoftLayer API SOAP client class requires at least PHP version 5.2.3.');
}

/**
 * A SoftLayer API SOAP Client
 *
 * SoftLayer_SoapClient provides a simple method for connecting to and making
 * calls from the SoftLayer SOAP API and provides support for many of the
 * SoftLayer API's features. SOAP method calls and client maangement are handled
 * by PHP's built-in SoapClient class. Your PHP installation should load the
 * SOAP extension in order to use this class. Furthemore, this library is
 * supported by PHP versions 5.2.3 and higher. See
 * <http://us2.php.net/manual/en/soap.setup.php> for assistance loading the PHP
 * SOAP extension.
 *
 * Currently the SoftLayer API only allows connections from within the SoftLayer
 * private network. The system using this class must be either directly
 * connected to the SoftLayer private network (eg. purchased from SoftLayer) or
 * has access to the SoftLayer private network via a VPN connection.
 *
 * Making API calls using the SoftLayer_SoapClient class is done in the
 * following steps:
 *
 * 1) Instantiate a new SoftLayer_SoapClient using the
 * SoftLayer_SoapClient::getClient() method. Provide the name of the service
 * that you wish to query and optionally the id number of an object that you
 * wish to instantiate.
 *
 * 2) Define and add optional headers to the client, such as object masks and
 * result limits.
 *
 * 3) Call the API method you wish to call as if it were local to your
 * SoftLayer_SoapClient object. This class throws exceptions if it's unable to
 * execute a query, so it's best to place API method calls in try / catch
 * statements for proper error handling.
 *
 * Once your method is done executing you may continue using the same client if
 * you need to conenct to the same service or define another
 * SoftLayer_SoapClient object if you wish to work with multiple services at
 * once.
 *
 * Here's a simple usage example that retrieves account information by calling
 * the getObject() method in the SoftLayer_Account service:
 *
 * ----------
 *
 * // Initialize an API client for the SoftLayer_Account service.
 * $client = SoftLayer_SoapClient::getClient('SoftLayer_Account');
 *
 * // Retrieve our account record
 * try {
 *     $account = $client->getObject();
 *     var_dump($account);
 * } catch (Exception $e) {
 *     die('Unable to retrieve account information: ' . $e->getMessage());
 * }
 *
 * ----------
 *
 * For a more complex example we'll retrieve a support ticket with id 123456
 * along with the ticket's updates, the user it's assigned to, the servers
 * attached to it, and the datacenter those servers are in. We'll retrieve our
 * extra information using a nested object mask. After we have the ticket we'll
 * update it with the text 'Hello!'.
 *
 * ----------
 *
 * // Initialize an API client for ticket 123456
 * $client = SoftLayer_SoapClient::getClient('SoftLayer_Ticket', 123456);
 *
 * // Create an object mask and assign it to our API client.
 * $objectMask = new SoftLayer_ObjectMask();
 * $objectMask->updates;
 * $objectMask->assignedUser;
 * $objectMask->attachedHardware->datacenter;
 * $client->setObjectMask($objectMask);
 *
 * // Retrieve the ticket record
 * try {
 *     $ticket = $client->getObject();
 *     var_dump($ticket);
 * } catch (Exception $e) {
 *     die('Unable to retrieve ticket record: ' . $e->getMessage());
 * }
 *
 * // Update the ticket
 * $update = new stdClass();
 * $update->entry = 'Hello!';
 *
 * try {
 *     $update = $client->addUpdate($update);
 *     echo 'Updated ticket 123456. The new update\'s id is ' . $update->id . '.');
 * } catch (Exception $e) {
 *     die('Unable to update ticket: ' . $e->getMessage());
 * }
 *
 * ----------
 *
 * This client supports sending multiple calls in parallel to the SoftLayer
 * API. Please see the documentation in the
 * SoftLayer_SoapClient_AsynchronousAction class in
 * SoapClient/AsynchronousAction.php for details.
 *
 * The most up to date version of this library can be found on the SoftLayer
 * github public repositories: http://github.com/softlayer/ . Please post to
 * the SoftLayer forums <http://forums.softlayer.com/> or open a support ticket
 * in the SoftLayer customer portal if you have any questions regarding use of
 * this library.
 *
 * @author      SoftLayer Technologies, Inc. <sldn@softlayer.com>
 * @copyright   Copyright (c) 2008, Softlayer Technologies, Inc
 * @license     http://sldn.softlayer.com/wiki/index.php/License
 * @link        http://sldn.softlayer.com/wiki/index.php/The_SoftLayer_API The SoftLayer API
 * @see         SoftLayer_SoapClient_AsynchronousAction
 */
class Softlayer_SoapClient extends SoapClient
{
    /**
     * Your SoftLayer API username. You may overide this value when calling
     * getClient().
     *
     * @var string
     */
    public static $apiUser = 'set me';

    /**
     * Your SoftLayer API user's authentication key. You may overide this value
     * when calling getClient().
     *
     * @link https://manage.softlayer.com/Administrative/apiKeychain API key management in the SoftLayer customer portal
     * @var string
     */
    public static $apiKey = 'set me';

    /**
     * The base URL of the SoftLayer SOAP API's WSDL files.
     *
     * @var string
     */
    //const API_BASE_URL = 'http://latest.application.staging.softlayer.local/sldn/soap/';
    //const API_BASE_URL = 'http://application.klaude.dev.softlayer.local/sldn/soap/';
    const API_BASE_URL = 'http://api.service.softlayer.com/soap/v3/';

    /**
     * The SOAP headers to send along with a SoftLayer API call
     *
     * @var array
     */
    protected $_headers = array();

    /**
     * The name of the SoftLayer API service you wish to query.
     *
     * @link http://sldn.softlayer.com/wiki/index.php/Category:API_Services A list of SoftLayer API services
     * @var string
     */
    protected $_serviceName;

    /**
     * Whether or not the current call is an asynchronous call.
     *
     * @var bool
     */
    protected $_asynchronous = false;

    /**
     * The object that handles asynchronous calls if the current call is an
     * asynchronous call.
     *
     * @var SoftLayer_SoapClient_AsynchronousAction
     */
    private $_asyncAction = null;

    /**
     * If making an asynchronous call, then this is the name of the function
     * we're calling.
     *
     * @var string
     */
    public $asyncFunctionName = null;

    /**
     * If making an asynchronous call, then this is the result of an
     * asynchronous call as retuned from the
     * SoftLayer_SoapClient_AsynchronousAction class.
     *
     * @var object
     */
    private $_asyncResult = null;

    /**
     * @var bool
     */
    public $oneWay;

    /**
     * Execute a SoftLayer API method
     *
     * @return object
     */
    public function __call($functionName, $arguments = null)
    {
        // The getPortalLoginToken method in the SoftLayer_User_Customer service
        // doesn't require an authentication header.
        if ($this->_serviceName == 'SoftLayer_User_Customer' && $functionName == 'getPortalLoginToken') {
            $this->removeHeader('authenticate');
        }

        // Determine if we shoud be making an asynchronous call. If so strip
        // "Async" from the end of the method name.
        if ($this->_asyncResult == null) {
            $this->_asynchronous = false;
            $this->_asyncAction = null;

            if (preg_match('/Async$/', $functionName) == 1) {
                $this->_asynchronous = true;
                $functionName = str_replace('Async', '', $functionName);

                $this->asyncFunctionName = $functionName;
            }
        }

        try {
            $result = parent::__call($functionName, $arguments, null, $this->_headers, null);
        } catch (SoapFault $e) {
            throw new Exception($e->getMessage());
        }

        if ($this->_asynchronous == true) {
            return $this->_asyncAction;
        }

        // remove the resultLimit header if they set it
        $this->removeHeader('resultLimit');

        return $result;
    }

    /**
     * Create a SoftLayer API SOAP Client
     *
     * Retrieve a new SoftLayer_SoapClient object for a specific SoftLayer API
     * service using either the class' constants API_USER and API_KEY or a
     * custom username and API key for authentication. Provide an optional id
     * value if you wish to instantiate a particular SoftLayer API object.
     *
     * @param string $serviceName The name of the SoftLayer API service you wish to query
     * @param int $id An optional object id if you're instantiating a particular SoftLayer API object. Setting an id defines this client's initialization parameter header.
     * @param string $username An optional API username if you wish to bypass SoftLayer_SoapClient's built-in username.
     * @param string $username An optional API key if you wish to bypass SoftLayer_SoapClient's built-in API key.
     * @return SoftLayer_SoapClient
     */
    public static function getClient($serviceName, $id = null, $username = null, $apiKey = null)
    {
        $serviceName = trim($serviceName);

        if ($serviceName == null) {
            throw new Exception('Please provide a SoftLayer API service name.');
        }

        $soapClient = new SoftLayer_SoapClient(self::API_BASE_URL . $serviceName . '?wsdl');

        if ($username != null && $apiKey != null) {
            $soapClient->setAuthentication($username, $apiKey);
        } elseif (Softlayer_SoapClient::$apiUser != null && Softlayer_SoapClient::$apiKey != null) {
            $soapClient->setAuthentication(Softlayer_SoapClient::$apiUser, Softlayer_SoapClient::$apiKey);
        }

        $soapClient->_serviceName = $serviceName;

        if ($id != null) {
            $soapClient->setInitParameter($id);
        }

        return $soapClient;
    }

    /**
     * Externally set the SoftLayer API username and key.
     *
     * @param string $apiUser
     * @param string apiKey
     */
    public static function setAuthenticationUser($apiUser, $apiKey)
    {
        Softlayer_SoapClient::$apiUser = trim($apiUser);
        Softlayer_SoapClient::$apiKey = trim($apiKey);
    }

    /**
     * Set a SoftLayer API call header
     *
     * Every header defines a customization specific to an SoftLayer API call.
     * Most API calls require authentication and initialization parameter
     * headers, but can also include optional headers such as object masks and
     * result limits if they're supported by the API method you're calling.
     *
     * @see removeHeader()
     * @param string $name The name of the header you wish to set
     * @param object $value The object you wish to set in this header
     */
    public function addHeader($name, $value)
    {
        $this->_headers[$name] = new SoapHeader(self::API_BASE_URL, $name, $value);
    }

    /**
     * Remove a SoftLayer API call header
     *
     * Removing headers may cause API queries to fail.
     *
     * @see addHeader()
     * @param string $name The name of the header you wish to remove
     */
    public function removeHeader($name)
    {
        unset($this->_headers[$name]);
    }

    /**
     * Set a user and key to authenticate a SoftLayer API call
     *
     * Use this method if you wish to bypass the API_USER and API_KEY class
     * constants and set custom authentication per API call.
     *
     * @link https://manage.softlayer.com/Administrative/apiKeychain API key management in the SoftLayer customer portal
     * @param string $username
     * @param string $apiKey
     */
    public function setAuthentication($username, $apiKey)
    {
        $username = trim($username);
        $apiKey = trim($apiKey);

        if ($username == null) {
            throw new Exception('Please provide a SoftLayer API username.');
        }

        if ($apiKey == null) {
            throw new Exception('Please provide a SoftLayer API key.');
        }

        $header = new stdClass();
        $header->username = $username;
        $header->apiKey   = $apiKey;

        $this->addHeader('authenticate', $header);
    }


    /**
     * Set an initialization parameter header on a SoftLayer API call
     *
     * Initialization parameters instantiate a SoftLayer API service object to
     * act upon during your API method call. For instance, if your account has a
     * server with id number 1234, then setting an initialization parameter of
     * 1234 in the SoftLayer_Hardware_Server Service instructs the API to act on
     * server record 1234 in your method calls.
     *
     * @link http://sldn.softlayer.com/wiki/index.php/Using_Initialization_Parameters_in_the_SoftLayer_API Using Initialization Parameters in the SoftLayer API
     * @param int $id The ID number of the SoftLayer API object you wish to instantiate.
     */
    public function setInitParameter($id)
    {
        $id = trim($id);

        if (!is_null($id)) {
            $initParameters = new stdClass();
            $initParameters->id = $id;
            $this->addHeader($this->_serviceName . 'InitParameters', $initParameters);
        }
    }

    /**
     * Set an object mask to a SoftLayer API call
     *
     * Use an object mask to retrieve data related your API call's result.
     * Object masks are skeleton objects that define nested relational
     * properties to retrieve along with an object's local properties.
     *
     * @see SoftLayer_ObjectMask
     * @link http://sldn.softlayer.com/wiki/index.php/Using_Object_Masks_in_the_SoftLayer_API Using object masks in the SoftLayer API
     * @link http://sldn.softlayer.com/wiki/index.php/Category:API_methods_that_can_use_object_masks API methods that can use object masks
     * @param object $mask The object mask you wish to define
     */
    public function setObjectMask($mask)
    {
        if (!is_null($mask)) {
            if (!($mask instanceof SoftLayer_ObjectMask)) {
                throw new Exception('Please provide a SoftLayer_ObjectMask to define an object mask.');
            }

            $objectMask = new stdClass();
            $objectMask->mask = $mask;

            $this->addHeader($this->_serviceName . 'ObjectMask', $objectMask);
        }
    }

    /**
     * Set a result limit on a SoftLayer API call
     *
     * Many SoftLayer API methods return a group of results. These methods
     * support a way to limit the number of results retrieved from the SoftLayer
     * API in a way akin to an SQL LIMIT statement.
     *
     * @link http://sldn.softlayer.com/wiki/index.php/Using_Result_Limits_in_the_SoftLayer_API Using Result Limits in the SoftLayer API
     * @link http://sldn.softlayer.com/wiki/index.php/Category:API_methods_that_can_use_result_limits API methods that can use result limits
     * @param int $limit The number of results to limit your SoftLayer API call to.
     * @param int $offset An optional offset to begin your SoftLayer API call's returned result set at.
     */
    public function setResultLimit($limit, $offset = 0)
    {
        $resultLimit = new stdClass();
        $resultLimit->limit = intval($limit);
        $resultLimit->offset = intval($offset);

        $this->addHeader('resultLimit', $resultLimit);
    }

    /**
     * Process a SOAP request
     *
     * We've overwritten the PHP SoapClient's __doRequest() to allow processing
     * asynchronous SOAP calls. If an asynchronous call was deected in the
     * __call() method then send processing to the
     * SoftLayer_SoapClient_AsynchronousAction class. Otherwise use the
     * SoapClient's built-in __doRequest() method. The results of this method
     * are sent back to __call() for post-processing. Asynchronous calls use
     * handleAsyncResult() to send he results of the call back to __call().
     *
     * @return object
     */
    public function __doRequest($request, $location, $action, $version, $one_way = false)
    {
        // Don't make a call if we already have an asynchronous result.
        if ($this->_asyncResult != null) {
            $result = $this->_asyncResult;
            unset($this->_asyncResult);

            return $result;
        }

        if ($this->oneWay == true) {
            $one_way = true;
            $this->oneWay = false;
        }

        // Use either the SoapClient or SoftLayer_SoapClient_AsynchronousAction
        // class to handle the call.
        if ($this->_asynchronous == false) {
            $result = parent::__doRequest($request, $location, $action, $version, $one_way);

            return $result;
        } else {
            $this->_asyncAction = new SoftLayer_SoapClient_AsynchronousAction($this, $this->asyncFunctionName, $request, $location, $action);
            return '';
        }
    }

    /**
     * Process the results of an asynchronous call.
     *
     * The SoftLayer_SoapClient_AsynchronousAction class uses
     * handleAsyncResult() to return it's call resuls back to this classes'
     * __call() method for post-pocessing.
     *
     * @param string $functionName The name of the SOAP method called.
     * @param string $result The raw SOAP XML output from a SOAP call
     * @return object
     */
    public function handleAsyncResult($functionName, $result)
    {
        $this->_asynchronous = false;
        $this->_asyncResult = $result;

        return $this->__call($functionName, array());
    }
}