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

require_once 'vendor/autoload.php';

use Connect\Console\ConnectApplication;
use Connect\Console\Configuration;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\Definition\Processor;

$config = Yaml::parse(__DIR__ . DIRECTORY_SEPARATOR . 'config.yml');
$configuration = new Configuration();
$processor     = new Processor();
$processedConfiguration = $processor->processConfiguration($configuration, [$config]);

$console = new ConnectApplication();
$console
    ->setSchemaPath(__DIR__ . DIRECTORY_SEPARATOR . 'schemas')
    ->setConfig($processedConfiguration)
    ->add(new Connect\Console\Command\ImportCommand);
$console->run();