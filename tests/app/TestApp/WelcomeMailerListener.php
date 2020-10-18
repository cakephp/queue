<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace TestApp;

use Cake\Event\EventListenerInterface;

/**
 * Class WelcomeMailerListener
 *
 * @package Queue\Test\test_app\App\Listener
 */
class WelcomeMailerListener implements EventListenerInterface
{
    /**
     * @return array
     */
    public function implementedEvents(): array
    {
        return [];
    }
}
