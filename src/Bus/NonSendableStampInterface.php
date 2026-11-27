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
 * Marker for stamps that never leave the process.
 *
 * Writers (such as `Envelope::toMetadataArray()`) must skip these stamps:
 * they wrap live objects (transport context, handler results) that have
 * no meaningful transport representation.
 */
interface NonSendableStampInterface extends StampInterface
{
}
