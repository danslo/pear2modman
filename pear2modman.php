<?php

define('DS', DIRECTORY_SEPARATOR);

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
            $packageFile = $this->_packageDirectory . DS . 'package.xml';
            if (!file_exists($packageFile)) {
                throw new Exception(sprintf('Could not find package file: %s', $packageFile));
            }

            $xml = @simplexml_load_file($packageFile);
            if ($xml) {
                $this->_packageContents = current($xml->xpath('/package/contents'));
            } else {
                throw new Exception('Errors in package.xml, aborting.');
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
            $this->_modmanDirectory = $this->_packageDirectory . DS . 'modman';
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
        return $this->_getModmanDirectory() . DS . 'modman';
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
        // TODO: Replace with some recursive copy() implementation.
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
        // Skip the namespace.
        $code = $this->_getDirectoriesFromNode($target, 1);

        // Copy all modules.
        $codePath = sprintf('app/code/%s/%s', $codePool, $code['path']);
        $this->_copyFolder(sprintf('%s/%s', $this->_getPackageDirectory(), $codePath), sprintf('%s/code', $this->_getModmanDirectory()));

        // Write modman lines.
        foreach ($code['directories'] as $directory) {
            // Get package code path.
            $moduleName = $this->_getNodeName($directory);
            $modulePath = sprintf('%s/%s', $codePath, $moduleName);

            // Write entry in modman index file.
            $this->_writeModmanLine(sprintf('code/%s', $moduleName), $modulePath);
        }
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
        foreach ($target->dir->file as $bootstrap) {
            // Get name of bootstrap file.
            $bootstrapFile = $this->_getNodeName($bootstrap);

            // Get path to our bootstrap file.
            $bootstrapPath = sprintf('app/etc/modules/%s', $bootstrapFile);

            // Copy it to modman target.
            copy(sprintf('%s/%s', $this->_getPackageDirectory(), $bootstrapPath), sprintf('%s/%s', $this->_getModmanDirectory(), $bootstrapFile));

            $this->_writeModmanLine($bootstrapFile, $bootstrapPath);
        }
        return $this;
    }

    /**
     * Extracts all directories inside a directory $fromLevel deep.
     * Also records the path it took while going there.
     *
     * @param SimpleXMLElement $node
     * @param int $maxLevel
     * @return string
     */
    protected function _getDirectoriesFromNode($node, $fromLevel = 0)
    {
        $current = $node;
        $currentLevel = 0;
        $path = '';
        do {
            $current = $current->children()->dir;
            $path .= $this->_getNodeName($current) . DS;
            $currentLevel++;
        } while ($currentLevel != $fromLevel);

        return array(
            'directories' => $current->children(),
            'path'        => rtrim($path, DS)
        );
    }

    protected function _handleThemeTarget($target, $themeType, $themeFolder)
    {
        // Go through the different areas.
        foreach ($target->dir as $area) {
            $areaName = $this->_getNodeName($area);

            // Go through the different types of files (layout, template).
            $types = $this->_getDirectoriesFromNode($area, 2);
            foreach ($types['directories'] as $type) {
                $typeName = $this->_getNodeName($type);

                // Determine modman directories.
                $originDirectory = sprintf('%s/%s/%s', $themeType, $areaName, $typeName);
                $targetDirectory = '';
                switch ($areaName) {
                    case 'adminhtml':
                        $targetDirectory = sprintf('%s/%s/default/default/%s', $themeFolder, $areaName, $typeName);
                        break;
                    case 'frontend':
                        $targetDirectory = sprintf('%s/%s/base/default/%s', $themeFolder, $areaName, $typeName);
                        break;
                    default:
                        throw new Exception(sprintf('Unhandled design area: %s', $areaName));
                }

                // Do the copying.
                @mkdir(sprintf('%s/%s/%s', $this->_getModmanDirectory(), $themeType, $areaName), 0755, true);
                $absoluteOriginDirectory = sprintf('%s/%s', $this->_getModmanDirectory(), $originDirectory);
                $absoluteTargetDirectory = sprintf('%s/%s/%s/%s/%s', $this->_getPackageDirectory(), $themeFolder, $areaName, $types['path'], $typeName);
                $this->_copyFolder($absoluteTargetDirectory, $absoluteOriginDirectory);

                // Write modman lines.
                switch ($themeType) {
                    case 'skin':
                        $this->_writeModmanLine($originDirectory, $targetDirectory);
                        break;

                    case 'design':
                        switch ($typeName) {
                            // These can be applied with a wildcard.
                            case 'layout':
                                $this->_writeModmanLine(sprintf('%s/*', $originDirectory), $targetDirectory . DS);
                                break;
                            // But these might exist in core files, so we must individually apply them.
                            case 'locale':
                            case 'template':
                                foreach ($this->_getFiles($type) as $file) {
                                    $this->_writeModmanLine(sprintf('%s/%s', $originDirectory, $file), sprintf('%s/%s', $targetDirectory, $file));
                                }
                                break;
                            default:
                                throw new Exception(sprintf('Unhandled design file type: %s', $typeName));
                        }
                        break;
                }
            }
        }
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
        $this->_handleThemeTarget($target, 'design', 'app/design');
        return $this;
    }

    /**
     * Gets the name attribute of a simple XML node.
     *
     * @param SimpleXMLElement $node
     * @return string
     */
    protected function _getNodeName($node)
    {
        return (string)$node->attributes()->name;
    }

    /**
     * Converts skin files to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleSkinTarget($target)
    {
        $this->_handleThemeTarget($target, 'skin', 'skin');
        return $this;
    }

    /**
     * Converts locale files to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleLocaleTarget($target)
    {
        // Copy the whole locale folder.
        $this->_copyFolder($this->_getPackageDirectory() . DS . 'app/locale', $this->_getModmanDirectory()  . DS . 'locale');

        // Go through all locales and write modman lines.
        foreach ($target->dir as $dir) {
            $locale = $this->_getNodeName($dir);
            $this->_writeModmanLine(sprintf('locale/%s/*', $locale), sprintf('app/locale/%s/', $locale));
        }
        return $this;
    }

    /**
     * Converts objects in magento root to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleWebTarget($target)
    {
        foreach ($target as $web) {
            $webName = $this->_getNodeName($web);
            switch ($webName) {
                case 'js':
                    $this->_copyFolder(sprintf('%s/js', $this->_getPackageDirectory()), sprintf('%s/js', $this->_getModmanDirectory()));
                    $this->_writeModmanLine('js/*', 'js/');
                    break;
                default:
                    throw new Exception(sprintf('Unhandled web target: %s', $webName));
            }
        }
        return $this;
    }

    /**
     * Converts libraries to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleLibTarget($target)
    {
        $this->_copyFolder(sprintf('%s/lib', $this->_getPackageDirectory()), sprintf('%s/lib', $this->_getModmanDirectory()));
        $this->_writeModmanLine('lib/*', 'lib/');
        return $this;
    }

    /**
     * Recursively gets all files in a directory node.
     *
     * @staticvar array $files
     * @param SimpleXMLElement $target
     * @param string $path
     * @return array
     */
    protected function _getFiles($target, $path = '', &$files = array())
    {
        if ($target->dir) {
            foreach ($target->dir as $dir) {
                $path .= $this->_getNodeName($dir) . DS;
                if ($dir->file) {
                    foreach ($dir->file as $file) {
                        $files[] = $path . $this->_getNodeName($file);
                    }
                }
                $this->_getFiles($dir, $path, $files);
            }
        }
        return $files;
    }

    /**
     * Converts mage files to modman.
     *
     * @param SimpleXMLElement $target
     * @return \ModmanGenerator
     */
    protected function _handleMageTarget($target)
    {
        // We just copy every rootfolder recursively into the modman directory.
        foreach ($target->dir as $dir) {
            $rootDirectory = $this->_getNodeName($dir);
            $this->_copyFolder(
                sprintf('%s/%s/*', $this->_getPackageDirectory(), $rootDirectory),
                sprintf('%s/%s/',  $this->_getModmanDirectory(),  $rootDirectory)
            );
        }

        // But when applying symlinks we must be careful. Therefore we only apply them
        // on the deepest level (the actual files).
        $files = $this->_getFiles($target);
        foreach ($files as $file) {
            $this->_writeModmanLine($file, $file);
        }
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
            $targetType = str_replace('mage', '', $this->_getNodeName($target));
            if (empty($targetType)) {
                $this->_handleMageTarget($target);
            } else {
                switch($targetType) {
                    case 'community':
                    case 'local':
                        $this->_handleCodeTarget($target, $targetType);
                        break;
                    default:
                        $method = sprintf('_handle%sTarget', ucfirst($targetType));
                        if (method_exists($this, $method)) {
                            $this->{$method}($target);
                        } else {
                            throw new Exception(sprintf('Unhandled content target: %s', $this->_getNodeName($target)));
                        }
                }
            }
        }
        return $this;
    }

}

$generator = new ModmanGenerator(isset($argv[1]) ? $argv[1] : $_GET['module']);
try {
    $generator->start();
    printf('Done!' . PHP_EOL);
} catch (Exception $e) {
    printf('Generator failed: %s' . PHP_EOL, $e->getMessage());
}
