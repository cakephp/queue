# Queue plugin for CakePHP

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.txt)
[![Build Status](https://img.shields.io/travis/com/cakephp/queue/master.svg?style=flat-square)](https://travis-ci.com/cakephp/queue)
[![Coverage Status](https://img.shields.io/codecov/c/github/cakephp/queue/master.svg?style=flat-square)](https://codecov.io/github/cakephp/queue?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/cakephp/queue.svg?style=flat-square)](https://packagist.org/packages/cakephp/queue)

This is a Queue system for CakePHP 4.

The plugin consists of a CakePHP shell wrapper and Queueing libraries for the [php-queue](https://php-enqueue.github.io) queue library.

## Installation

You can install this plugin into your CakePHP application using [Composer](http://getcomposer.org).

Run the following command
```sh
composer require cakephp/queue
 ```

Install the transport you wish to use. For a list of available transports, see [this page](https://php-enqueue.github.io/transport). The example below is for pure-php redis:

```shell
composer require enqueue/redis predis/predis:^1
```

## Configuration

You can load the plugin using the shell command:

```
bin/cake plugin load Cake/Queue
```

Or you can manually add the loading statement in the **src/Application.php** file of your application:
```php
public function bootstrap()
{
    parent::bootstrap();
    $this->addPlugin('Cake/Queue');
}
```

Additionally, you will need to configure the ``default`` queue configuration in your **config/app.php** file.

## Documentation

Full documentation of the plugin can be found on the [CakePHP Cookbook](https://book.cakephp.org/queue/1/).
