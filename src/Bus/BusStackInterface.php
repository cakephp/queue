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
 * @since         3.0.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Bus;

/**
 * Next middleware resolver for the bus pipeline.
 */
interface BusStackInterface
{
    /**
     * Return the next middleware in the chain.
     *
     * When no middleware is left, a terminal middleware is returned
     * which leaves the envelope unchanged.
     *
     * @return \Cake\Queue\Bus\BusMiddlewareInterface
     */
    public function next(): BusMiddlewareInterface;
}
