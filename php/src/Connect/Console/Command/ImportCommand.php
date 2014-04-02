<?php

/**
 * Nexway Connect PHP Importer
 * version 1.0-alpha
 *
 * @author Christophe Eblé <ceble@nexway.com>
 * @copyright (c) 2013 Nexway
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Connect\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    /**
     * XML mime type
     */
    const XML_MIME = 'application/xml';

    /**
     * @const string Path to Connect XSD
     */
    const SCHEMA_PATH = '1.0/connect.xsd';

    /**
     * Keeps reference of the output interface
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * Keeps reference of the input interface
     *
     * @var InputInterface
     */
    private $input;

    /**
     * Returns information about the connect:import command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('connect:import')
            ->setDescription('Imports Apps on Nexway\'s network')
            ->addArgument(
                'directory',
                InputArgument::REQUIRED,
                'Path of the directory where to retrieve apps'
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                'If set, the XML file will be validated against Nexway Connect\'s XSD locally'
            )
        ;
    }

    /**
     * Check PHP requirements in order to run that command
     *
     * @return void
     */
    protected function checkCapabilities()
    {
        if (!class_exists('\OAuth')) {
            throw new \Exception('This command needs the OAuth PECL Package to be installed http://pecl.php.net/package/oauth');
        }
    }

    /**
     * Executes the import command
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|integer null or 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract method is not implemented
     * @see    setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkCapabilities();

        $this->output = $output;
        $this->input  = $input;

        // Get directory
        $directory = $input->getArgument('directory');
        $d = new \SplFileInfo($directory);

        // Check if directory exists
        if (!$d->isDir()) {
            throw new \Exception('directory argument must contain a VALID directory!');
        }

        // Check if directory is readable
        if (!$d->isReadable()) {
            throw new \Exception('directory must be READABLE!');
        }

        $this->importFromDirectory($d);
    }

    /**
     * Imports an app from a directory
     *
     * @param SplFileInfo  $directory Source directory where XML files are pulled out
     *
     * @return void
     */
    protected function importFromDirectory(\SplFileInfo $directory)
    {
        // Launch import process, checking how many files we have in the directory
        $fs = new \FilesystemIterator($directory->getRealPath());

        $this->output->writeln(sprintf('<fg=green>Start import from %s</fg=green>', $directory->getPathName()));

        $xmlFiles = [];
        // Iterating each file in the directory to process further
        foreach ($fs as $file) {
            // If we deal with a file
            if ($file->isFile()) {
                $mime = mime_content_type($file->getPathName());
                if ($mime !== self::XML_MIME || !$file->isReadable() || $file->getExtension() !== 'xml') {
                    $this->output->writeln(sprintf('<comment>Ignore %s (wrong mime/extension/unreadable)</comment>', $file->getFilename()));
                    continue;
                }
                // Start import
                $this->submit($file);
            }
        }
    }

    /**
     * Submits the app to NexwayConnect using HTTP and OAuth 1.0 authentication
     *
     * @param SplFileInfo $file XML file to submit
     *
     * @return void
     */
    protected function submit(\SplFileInfo $file)
    {
        $xml = file_get_contents($file->getPathName());

        // Validate XML locally if asked
        if ($this->input->getOption('validate')) {
            $this->output->writeln('<info>Validating XML</info>');
            $this->schemaValidate($xml);
        }

        // Initialize OAuth class
        $oAuth = new \OAuth(
            $this->getApplication()->config['oauth']['consumer']['key'],
            $this->getApplication()->config['oauth']['consumer']['secret'],
            OAUTH_SIG_METHOD_HMACSHA1,
            OAUTH_AUTH_TYPE_AUTHORIZATION
        );

        $this->output->writeln(sprintf('<fg=green>Importing %s</fg=green>', $file->getFileName()));

        // Start submission
        $oAuth
            ->setToken(
                $this->getApplication()->config['oauth']['token']['key'],
                $this->getApplication()->config['oauth']['token']['secret']
            );

        // Check if we need to debug the oAuth process
        if ($this->debugEnabled()) {
            $oAuth->enableDebug();
        }

        $oAuth->setAuthType(OAUTH_AUTH_TYPE_AUTHORIZATION);
        $oAuth
            ->fetch(
                $this->getApplication()->config['oauth']['endpoint'],
                $xml,
                OAUTH_HTTP_METHOD_POST,
                array(
                    'Accept'       => 'application/xml',
                    'Content-Type' => 'application/xml',
                )
            );

        if ($this->debugEnabled()) {
            $this->output->writeln('<comment>'.$oAuth->debugInfo.'</comment>');
        }

        $this->output->writeln($oAuth->getLastResponse());
    }

    /**
     * Checks if debug is enabled
     *
     * @return bool
     */
    protected function debugEnabled()
    {
        if (true === $this->getApplication()->config['debug']) {
            return TRUE;
        }

        return FALSE;
    }


    /**
     * Validates the incoming XML with the Connect XSD
     *
     * @param string xml XML to validate
     *
     * @return void
     */
    protected function schemaValidate($xml)
    {
        libxml_use_internal_errors(true);

        $schemaDir  = $this->getApplication()->getSchemaPath();
        $schemaPath = $schemaDir . DIRECTORY_SEPARATOR . self::SCHEMA_PATH;

        $reader = new \XMLReader();

        // Import XML
        $reader->XML($xml, NULL, LIBXML_NOCDATA);

        if ($reader->setSchema($schemaPath)) {
            while ($reader->read());
            $schemaErrors = libxml_get_errors();
            if ($schemaErrors) {
                foreach ($schemaErrors as $schemaError) {
                    $this->outputXmlSchemaError($schemaError);
                }
                libxml_clear_errors();
                $reader->close();
                exit;
            }
        }
    }

    /**
     * Outputs LibXML errors
     *
     * @param  XMLReaderElement $error Error
     *
     * @return void
     */
    protected function outputXmlSchemaError($error)
    {
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $errorType = '<comment>Warning %s</comment>';
                break;
             case LIBXML_ERR_ERROR:
                $errorType = '<error>Error %s</error>';
                break;
            case LIBXML_ERR_FATAL:
                $errorType = '<error>Fatal %s</error>';
                break;
        }

        $this->output->writeln(sprintf($errorType, trim($error->message)));
    }
}