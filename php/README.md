NexwayConnect SDK for PHP (c) Nexway, 2013 (http://www.nexway.com)

Introduction
------------

NexwayConnect is a submission platform to sell apps on the Nexway Network.

Requirements
------------
- PHP CLI (version 5.3 minimum)
- OAuth PECL Extension http://pecl.php.net/package/oauth

Installation
------------
Once the repository is checked out, go to "php" directory.

This project uses Composer for installing dependencies.
Prior to using the SDK you have to install Composer, here's how to install it: https://getcomposer.org/doc/01-basic-usage.md

Once installed, type the command below in your terminal.

	php composer.phar install

Usage
-----

Once the repository is checked out, go to "php" directory and edit the file **config.yml**.
You have to replace the OAuth configuration keys with the one given by Nexway Team.

### XML files ###

To start submission, you have to place XML files inside a directory which are readable by the script.
The default installation has a directory called **apps** where you'll find an XML sample.
When you're ready to go live, create similar input files with a **.xml** extension and they will be picked up by the script. Once imported, it is advised to remove them.

### Submission ###

When you're ready, launch that command using a PHP interpreter on the commandline. **apps** is the relative or absolute path of Directory you want to process.

	$ php console.php connect:import apps

To validate the file locally against XML schemas, pass the *--validate* optional flag.

	$ php console.php connect:import --validate apps

For any issue, please consult http://kineticwiki.nexway.com/index.php?title=Connect or contact the Nexway engineering team.
