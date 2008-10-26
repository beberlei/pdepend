<?php
/**
 * This file is part of PHP_Depend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008, Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage TextUI
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend.php';
require_once 'PHP/Depend/Log/LoggerFactory.php';
require_once 'PHP/Depend/TextUI/ResultPrinter.php';

// TODO: Refactory this reflection dependency
require_once 'PHP/Reflection/AST/Iterator/PackageFilter.php';

/**
 * The command line runner starts a PDepend process.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage TextUI
 * @author     Manuel Pichler <mapi@pdepend.org>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_TextUI_Runner
{
    /**
     * Marks the default success exit.
     */
    const SUCCESS_EXIT = 0;
    
    /**
     * Marks an internal exception exit.
     */
    const EXCEPTION_EXIT = 2;
    
    /**
     * List of allowed file extensions. Default file extensions are <b>php</b>
     * and <p>php5</b>.
     *
     * @type array<string>
     * @var array(string) $_extensions
     */
    private $_extensions = array('php', 'php5');
    
    /**
     * List of exclude directories. Default exclude dirs are <b>.svn</b> and
     * <b>CVS</b>.
     *
     * @type array<string>
     * @var array(string) $_excludeDirectories
     */
    private $_excludeDirectories = array('.svn', 'CVS');
    
    /**
     * List of exclude packages.
     *
     * @type array<string>
     * @var array(string) $_excludePackages
     */
    private $_excludePackages = array();
    
    /**
     * List of source code directories. 
     *
     * @type array<string>
     * @var array(string) $_sourceDirectories
     */
    private $_sourceDirectories = array();
    
    /**
     * Should the parse ignore doc comment annotations?
     *
     * @type boolean
     * @var boolean $_withoutAnnotations
     */
    private $_withoutAnnotations = false;
    
    /**
     * Should PHP_Depend treat <b>+unknown</b> as a regular project package?
     *
     * @type boolean
     * @var boolean $_supportBadDocumentation
     */
    private $_supportBadDocumentation = false;
    
    /**
     * List of log identifiers and log files.
     *
     * @type array<string>
     * @var array(string=>string) $_loggers
     */
    private $_loggerMap = array();
    
    /**
     * List of cli options for loggers or analyzers.
     *
     * @type array<mixed>
     * @var array(string=>mixed) $_options
     */
    private $_options = array();
    
    /**
     * Sets a list of allowed file extensions.
     * 
     * NOTE: If you call this method, it will replace the default file extensions. 
     *
     * @param array(string) $extensions List of file extensions.
     * 
     * @return void
     */
    public function setFileExtensions(array $extensions)
    {
        $this->_extensions = $extensions;
    }
    
    /**
     * Sets a list of exclude directories.
     * 
     * NOTE: If this method is called, it will overwrite the default settings.
     *
     * @param array(string) $excludeDirectories All exclude directories.
     * 
     * @return void
     */
    public function setExcludeDirectories(array $excludeDirectories)
    {
        $this->_excludeDirectories = $excludeDirectories;
    }
    
    /**
     * Sets a list of exclude packages.
     *
     * @param array(string) $excludePackages Exclude packages.
     * 
     * @return void
     */
    public function setExcludePackages(array $excludePackages)
    {
        $this->_excludePackages = $excludePackages;
    }
    
    /**
     * Sets a list of source directories.
     *
     * @param array(string) $sourceDirectories The source directories.
     * 
     * @return void
     */
    public function setSourceDirectories(array $sourceDirectories)
    {
        $this->_sourceDirectories = $sourceDirectories;
    }
    
    /**
     * Should the parser ignore doc comment annotations?
     *
     * @return void
     */
    public function setWithoutAnnotations()
    {
        $this->_withoutAnnotations = true;
    }
    
    /**
     * Should PHP_Depend support projects with a bad documentation. If this
     * option is set to <b>true</b>, PHP_Depend will treat the default package
     * <b>+unknown</b> as a regular project package.
     *
     * @return void
     */
    public function setSupportBadDocumentation()
    {
        $this->_supportBadDocumentation = true;
    }
    
    /**
     * Adds a logger to this runner.
     *
     * @param string $loggerID    The logger identifier.
     * @param string $logFileName The log file name.
     * 
     * @return void
     */
    public function addLogger($loggerID, $logFileName)
    {
        $this->_loggerMap[$loggerID] = $logFileName;
    }
    
    /**
     * Adds a logger or analyzer option.
     *
     * @param string       $identifier The option identifier.
     * @param string|array $value      The option value.
     * 
     * @return void
     */
    public function addOption($identifier, $value)
    {
        $this->_options[$identifier] = $value;
    }
    
    /**
     * Starts the main PDepend process and returns <b>true</b> after a successful
     * execution.
     *
     * @return boolean
     * @throws RuntimeException An exception with a readable error message and
     * an exit code. 
     */
    public function run()
    {
        $pdepend = new PHP_Depend();
        $pdepend->setOptions($this->_options);

        foreach ($this->_extensions as $extension) {
            $pdepend->addExtension($extension);
        }

        foreach ($this->_excludeDirectories as $excludeDirectory) {
            $pdepend->addExcludeDirectory($excludeDirectory);
        }
        
        if (count($this->_excludePackages) > 0) {
            $exclude = $this->_excludePackages;
            $filter  = new PHP_Reflection_AST_Iterator_PackageFilter($exclude);
            $pdepend->addCodeFilter($filter);
        }

        if ($this->_withoutAnnotations === true) {
            $pdepend->setWithoutAnnotations();
        }
        if ($this->_supportBadDocumentation === true) {
            $pdepend->setSupportBadDocumentation();
        }
        
        // Try to set all source directories.
        try {
            foreach ($this->_sourceDirectories as $sourceDirectory) {
                $pdepend->addDirectory($sourceDirectory);
            }
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), self::EXCEPTION_EXIT);
        }
        
        if (count($this->_loggerMap) === 0) {
            throw new RuntimeException('No output specified.', self::EXCEPTION_EXIT);
        }
        
        $loggerFactory = new PHP_Depend_Log_LoggerFactory();
        
        // To append all registered loggers.
        try {
            foreach ($this->_loggerMap as $loggerID => $logFileName) {
                // Create a new logger
                $logger = $loggerFactory->createLogger($loggerID, $logFileName);
                
                $pdepend->addLogger($logger);
            }
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), self::EXCEPTION_EXIT);
        }
        
        // TODO: Make the result printer class configurable
        $resultPrinter = new PHP_Depend_TextUI_ResultPrinter();
        
        $pdepend->addProcessListener($resultPrinter);
        
        try {
            $pdepend->analyze();
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage(), self::EXCEPTION_EXIT);
        }
        
        return self::SUCCESS_EXIT;
    }
}