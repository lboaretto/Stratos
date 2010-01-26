<?php

class Model_Language
{
    /**
     * A language's name
     *
     * @var string
     */
    public $name;

    /**
     * A language file's path
     *
     * @var string
     */
    public $path;

    /**
     * Retrieve a single language.
     *
     * @param string $name The name of the language to retrieve.
     *
     * @throws Exception
     * @return Model_Language
     */
    public function __construct($name)
    {
        $name = trim($name);

        if (!file_exists(LANGUAGE_PATH . '/' . $name . '.csv')) {
            throw new Exception('Language ' . $name . ' not found.');
        }

        $this->name = $name;
        $this->path = LANGUAGE_PATH . '/' . $name . '.csv';
    }

    /**
     * Get a list of all installed languages.
     *
     * @return array
     */
    public static function getAllLanguages()
    {
        $return = array();

        $dir = dir(LANGUAGE_PATH);

        while (false !== ($entry = $dir->read())) {
            if ($entry != '.' && $entry != '..' && !preg_match('/^\\./', $entry)) {
                $return[] = new Model_Language(str_replace('.csv', '', $entry));
            }
        }

        return $return;
    }

    /**
     * Create a new language using an existing one as a template.
     *
     * @param string $name The name of the new language.
     * @param string $baseLanguageName The name of the language to base the new language from.
     *
     * @throws Exception
     * @return Model_Language
     */
    public static function addLanguage($name, $baseLanguageName)
    {
        $name = trim($name);
        $baseLanguageName = trim($baseLanguageName);

        /*
         * Make sure we're given good language names.
         */
        if ($name == null) {
            throw new Exception('Please provide a language name.');
        }

        if ($baseLanguageName == null) {
            throw new Exception('Please provide a base language name.');
        }

        /*
         * Make sure the base language exists.
         */
        try {
            $baseLanguage = new Model_Language($baseLanguageName);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        /*
         * Make sure the new language doesn't already exist.
         */
        try {
            $language = new Model_Language($name);
        } catch (Exception $e) {
            // no-op
        }

        if ($language != null) {
            throw new Exception('The language ' . $name . ' already exists.');
        }

        /*
         * Copy the base language to the new language.
         */
        copy($baseLanguage->path, LANGUAGE_PATH . '/' . $name . '.csv');

        /*
         * Return the new language.
         */
        return new Model_Language($name);
    }

    /**
     * Get a language's CSV content
     *
     * @return string
     */
    public function getLanguageContent()
    {
        return file_get_contents($this->path);
    }

    /**
     * Update a language's CSV file
     *
     * @param string $content The content to save to the language.
     *
     * @throws Exception
     * @return bool
     */
    public function updateLanguage($content)
    {
        $content = trim($content);

        if ($content == null) {
            throw new Exception('Please provide language content.');
        }

        if (file_put_contents($this->path, $content) === false) {
            throw new Exception('Unable to save language.');
        }

        return true;
    }

    /**
     * Delete a language by removing it's CSV file.
     *
     * @return bool
     */
    public function deleteLanguage()
    {
        return unlink($this->path);
    }
}

