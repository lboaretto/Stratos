<?php

class Model_Skin
{
    /**
     * A skin's name
     *
     * @var string
     */
    public $name;

    /**
     * A skin's path
     *
     * @var string
     */
    public $path;

    /**
     * The name of the skin's logo file.
     *
     * @var string
     */
    public $logoFile = 'logo.png';

    /**
     * Retrieve a single skin
     *
     * @param string $name The name of the skin to retrieve.
     *
     * @throws Exception
     * @return Model_Skin
     */
    public function __construct($name)
    {
        $name = trim($name);

        if (!is_dir(SKIN_PATH . '/' . $name)) {
            throw new Exception('Skin ' . $name . ' not found.');
        }

        $this->name = $name;
        $this->path = SKIN_PATH . '/' . $name;
    }

    /**
     * Get a list of all installed skins.
     *
     * @return array
     */
    public static function getAllSkins()
    {
        $return = array();

        $dir = dir(SKIN_PATH);

        while (false !== ($entry = $dir->read())) {
            if ($entry != '.' && $entry != '..' && is_dir($dir->path . '/' . $entry)) {
                $return[] = new Model_Skin($entry);
            }
        }

        return $return;
    }

    /**
     * Create a new skin using an existing one as a template.
     *
     * @param string $name The name of the new skin.
     * @param string $baseSkinName The name of the skin to base the new skin from.
     *
     * @throws Exception
     * @return Model_Skin
     */
    public static function addSkin($name, $baseSkinName)
    {
        $name = trim($name);
        $baseSkinName = trim($baseSkinName);

        /*
         * Make sure we're given good skin names.
         */
        if ($name == null) {
            throw new Exception('Please provide a skin name.');
        }

        if ($baseSkinName == null) {
            throw new Exception('Please provide a base skin name.');
        }

        /*
         * Make sure the base skin exists.
         */
        try {
            $baseSkin = new Model_Skin($baseSkinName);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        /*
         * Make sure the new skin doesn't already exist.
         */
        try {
            $skin = new Model_Skin($name);
        } catch (Exception $e) {
            // no-op
        }

        if ($skin != null) {
            throw new Exception('The skin ' . $name . ' already exists.');
        }

        /*
         * Copy the base skin to the new skin.
         */
        recursiveCopy(SKIN_PATH . '/' . $baseSkinName, SKIN_PATH . '/' . $name);

        /*
         * Return the new skin.
         */
        return new Model_Skin($name);
    }

    /**
     * Get the contents of a skin's CSS file
     *
     * @return string
     */
    public function getCssContent()
    {
        if (file_exists($this->path . '/styles/main.css')) {
            return file_get_contents($this->path . '/styles/main.css');
        } else {
            return null;
        }
    }

    /**
     * Update a skin's CSS content.
     *
     * @param string $content The new CSS content to save.
     *
     * @throws Exception
     * @return bool
     */
    public function updateCss($content)
    {
        $content = trim($content);

        if ($content == null) {
            throw new Exception('Please provide CSS content.');
        }

        if (file_put_contents($this->path . '/styles/main.css', $content) === false) {
            throw new Exception('Unable to save CSS content.');
        }

        return true;
    }

    /**
     * Delete a skin by removing it's directory.
     *
     * @return bool
     */
    public function deleteSkin()
    {
        recursiveDelete($this->path);

        return true;
    }
}
