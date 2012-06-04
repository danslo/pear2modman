<?php

class ModmanGenerator
{

    protected $_packageContents  = null;
    protected $_modmanDirectory  = '';
    protected $_packageDirectory = '';

    /**
     * Sets PEAR package directory.
     *
     * @param string $packageDirectory
     * @return void
     */
    public function ModmanGenerator($packageDirectory)
    {
        $this->_packageDirectory = $packageDirectory;
    }

    /**
     * Reads PEAR package.xml and loads simplexml tree.
     *
     * @return string
     * @throws Exception
     */
    protected function _getPackageContents()
    {
        if ($this->_packageContents === null) {
            $packageFile = $this->_packageDirectory . '/package.xml';
            if (!file_exists($packageFile)) {
                throw new Exception('Could not find package file: ' . $packageFile);
            }

            $xml = simplexml_load_file($packageFile);
            if ($xml) {
                $this->_packageContents = current($xml->xpath('/package/contents'));
            }
        }
        return $this->_packageContents;
    }

    /**
     * Gets a valid package directory.
     *
     * @return string
     * @throws Exception
     */
    protected function _getPackageDirectory()
    {
        if (empty($this->_packageDirectory)) {
            throw new Exception('No valid package directory specified.');
        }
        return $this->_packageDirectory;
    }

    /**
     * Gets, and optionally creates, the modman output directory.
     *
     * @return string
     * @throws Exception
     */
    protected function _getModmanDirectory()
    {
        if (empty($this->_modmanDirectory)) {
            $this->_modmanDirectory = $this->_packageDirectory . '/modman';
            @mkdir($this->_modmanDirectory, 0755, true);
            if (!file_exists($this->_modmanDirectory)) {
                throw new Exception('Output modman directory does not exist.');
            }
        }
        return $this->_modmanDirectory;
    }

    /**
     * Gets the path to modman index file.
     *
     * @return string
     */
    protected function _getModmanFile()
    {
        return $this->_getModmanDirectory() . '/modman';
    }

    /**
     * Writes an entry to the modman index file.
     *
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function _writeModmanLine($from, $to)
    {
        return file_put_contents($this->_getModmanFile(), $from . ' ' . $to . PHP_EOL, FILE_APPEND);
    }

    /**
     * Copies a folder from one location to another.
     *
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function _copyFolder($from, $to)
    {
        /**
         * TODO: Replace with some recursive copy() implementation.
         */
        return shell_exec(sprintf('cp -Rf %s %s', $from, $to));
    }

    /**
     * Converts local code to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleCodeTarget($target, $codePool)
    {
        /**
         * Get package code path.
         */
        $namespaceNode = $target->children()->dir;
        $originalCodePath = sprintf(
            'app/code/%s/%s/%s',
            $codePool,
            (string)$namespaceNode->attributes()->name,
            (string)$namespaceNode->children()->dir->attributes()->name
        );

        /**
         * Copy it to modman target.
         */
        $this->_copyFolder(
            $this->_getPackageDirectory() . '/' . $originalCodePath,
            $this->_getModmanDirectory() . '/code'
        );

        /**
         * Write entry in modman index file.
         */
        $this->_writeModmanLine('code', $originalCodePath);

        return $this;
    }

    /**
     * Converts bootstrap file to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleEtcTarget($target)
    {
        /**
         * Get path to our bootstrap file.
         */
        $bootstrapPath = sprintf(
            'app/etc/modules/%s',
            (string)$target->children()->dir->file->attributes()->name
        );

        /**
         * Copy it to modman target.
         */
        copy(
            $this->_getPackageDirectory() . '/' . $bootstrapPath,
            $this->_getModmanDirectory()  . '/' . 'bootstrap.xml'
        );

        /**
         *Write entry in modman index file.
         */
        $this->_writeModmanLine('bootstrap.xml', $bootstrapPath);

        return $this;
    }

    /**
     * Converts design files to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleDesignTarget($target)
    {
        // STUB
        return $this;
    }

    /**
     * Converts skin files to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleSkinTarget($target)
    {
        // STUB
        return $this;
    }

    /**
     * Starts the conversion of a specified PEAR package.
     *
     * @return \ModmanGenerator
     * @throws Exception
     */
    public function start()
    {
        foreach ($this->_getPackageContents() as $target) {
            $targetType = (string)$target->attributes()->name;
            switch($targetType) {
                case 'magecommunity':
                case 'magelocal':
                    $this->_handleCodeTarget($target, str_replace('mage', '', $targetType));
                    break;
                case 'mageetc':
                    $this->_handleEtcTarget($target);
                    break;
                case 'magedesign':
                    $this->_handleDesignTarget($target);
                    break;
                case 'mageskin':
                    $this->_handleSkinTarget($target);
                    break;
                default:
                    throw new Exception(sprintf('Unhandle content target: %s', $targetType));
            }
        }
        return $this;
    }

}

$generator = new ModmanGenerator($argv[1]);
try {
    $generator->start();
} catch (Exception $e) {
    printf('Generator failed: %s' . PHP_EOL, $e->getMessage());
}