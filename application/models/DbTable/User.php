<?php

class Model_DbTable_User extends Zend_Db_Table
{
    /**
     * The name of the table containing users.
     *
     * @var string
     */
    protected $_name = 'user';

    /**
     * The primary key of this table is the id column.
     *
     * @var string
     */
    protected $_primary = 'id';

    /**
     * A user's local identifier.
     *
     * @var int
     */
    public $id;

    /**
     * A user's username.
     *
     * @var string
     */
    public $username;

    /**
     * A user's SoftLayer API key.
     *
     * @var string
     */
    public $apiKey;

    /**
     * A user's preferred language.
     *
     * @var string
     */
    public $language;

    /**
     * A user's preferred skin.
     *
     * @var string
     */
    public $skin;

    /**
     * Whether a user is an admin or not.
     *
     * @var bool
     */

    /**
     * Whether a user is an admin or not.
     *
     * @var bool
     */
    public $isAdmin;

    /**
     * Initialize a single user object
     *
     * @param int $id The id of the user we wish to retrieve.
     * @param array $data Skip the database query and instead populate the object with this data.
     *
     * @throws Exception
     * @return Model_DbTable_User
     */
    public function __construct($id = null, $data = array())
    {
        parent::__construct();

        if ($id != null) {
            if (count($data) > 0) {
                $this->initUser($data);
            } else {
                $id = (int)$id;

                try {
                    $user = $this->fetchRow('id = ' . $id)->toArray();
                } catch (Exception $e) {
                    throw new Exception('Count not find user ' . $id . '.');
                }

                $this->initUser($user);
            }
        }
    }

    /**
     * Populate user info.
     *
     * @param array $data User information
     */
    private function initUser($data)
    {
        $this->id       = $data['id'];
        $this->username = $data['username'];
        $this->apiKey   = $data['apikey'];
        $this->language = $data['language'];
        $this->skin     = $data['skin'];
        $this->isAdmin  = $data['isAdmin'];
    }

    /**
     * Add a new user
     *
     * @param string $username The user's username
     * @param string $apiKey The user's SoftLayer API key
     * @param string $skin The user's preferred skin
     * @param string $language The user's preferred language
     * @param bool $isAdmin Whether the user is an admin or not.
     *
     * @throws Exception
     * @return bool
     */
    public static function addUser($username, $apiKey, $skin, $language, $isAdmin = false)
    {
        $username = trim($username);
        $apiKey = trim($apiKey);
        $skin = trim($skin);
        $language = trim($language);
        $isAdmin = (bool)$isAdmin;

        if ($username == null) {
            throw new Exception('Please provide a username.');
        }

        if ($apiKey == null) {
            throw new Exception('Please provide an API key.');
        }

        $user = Model_DbTable_User::findByUsername($username);

        if ($user != null) {
            throw new Exception('The user ' . $username . ' already exists.');
        }

        $data = array(
            'username' => $username,
            'apiKey' => $apiKey,
            'skin' => $skin,
            'language' => $language,
            'isAdmin' => $isAdmin,
        );

        $user = new Model_DbTable_User(null, $data);
        $user->insert($data);
        return true;
    }

    /**
     * Edit a user
     *
     * @param string $username The user's username
     * @param string $apiKey The user's SoftLayer API key
     * @param string $skin The user's preferred skin
     * @param string $language The user's preferred language
     * @param bool $isAdmin Whether the user is an admin or not
     *
     * @throws Exception
     * @return bool
     */
    public function updateUser($username, $apiKey, $skin, $language, $isAdmin)
    {
        $username = trim($username);
        $apiKey = trim($apiKey);
        $skin = trim($skin);
        $language = trim($language);
        $isAdmin = (bool)$isAdmin;

        if ($username == null) {
            throw new Exception('Please provide a username.');
        }

        if ($apiKey == null) {
            throw new Exception('Please provide an API key.');
        }

        $data = array(
            'username' => $username,
            'apiKey' => $apiKey,
            'skin' => $skin,
            'language' => $language,
            'isAdmin' => $isAdmin,
        );

        $result = $this->update($data, 'id = '. $this->id);

        if ($result == 0) {
            throw new Exception('Unable to update user.');
        } elseif ($result > 1) {
            throw new Exception('More than one user was updated.');
        }

        return true;
    }

    /**
     * Delete a single user
     *
     * @throws Exception
     * @return bool
     */
    public function deleteUser()
    {
        $result = $this->delete('id = ' . $this->id);

        if ($result == 0) {
            throw new Exception('Unable to delete user.');
        } elseif ($result > 1) {
            throw new Exception('More than one user was deleted!');
        }

        return true;
    }

    /**
     * Find a single user by username
     *
     * @param string $username The username to search for.
     * @return Model_DbTable_User
     */
    public static function findByUsername($username)
    {
        $username = trim($username);

        $user = new Model_DbTable_User();
        $user = $user->fetchRow('username = "' . $username . '"');

        if ($user != null) {
            $user = $user->toArray();
        }

        return ($user['id'] === null ? null : new Model_DbTable_User($user['id']));
    }

    /**
     * Authenticate a user
     *
     * Authentication is handled by SoftLayer's authentication system. Passwords
     * are not stored locally.
     *
     * @param string $username
     * @param string $password
     * @throws Exception
     * @return bool
     */
    public static function authenticate($username, $password)
    {
        /*
         * Make sure the user exists locally first.
         */
        $user = Model_DbTable_User::findByUsername($username);

        if ($user == null) {
            throw new Exception('Invalid login credentials provided.');
        }

        /*
         * Attempt to authenticate to the SoftLayer API. API docs at
         * http://sldn.softlayer.com/wiki/index.php/SoftLayer_User_Customer::getPortalLoginToken
         */
        $client = SoftLayer_SoapClient::getClient('SoftLayer_User_Customer');

        try {
            $result = $client->getPortalLoginToken($username, $password);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return true;
    }

    /**
     * Retrieve all users
     *
     * @return array
     */
    public static function getAllUsers()
    {
        $return = array();

        $users = new Model_DbTable_User();
        $users = $users->fetchAll();

        foreach ($users as $user) {
            $return[] = new Model_DbTable_User($user['id'], $user);
        }

        return $return;
    }
}
