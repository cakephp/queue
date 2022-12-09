<?php
declare(strict_types=1);

namespace Cake\Queue\Model\Entity;

use Cake\ORM\Entity;

/**
 * FailedJob Entity
 *
 * @property int $id
 * @property string $class
 * @property string $method
 * @property string $data
 * @property string|null $config
 * @property string|null $priority
 * @property string|null $queue
 * @property string|null $exception
 * @property \Cake\I18n\FrozenTime|null $created
 */
class FailedJob extends Entity
{
    /**
     * Map of fields in this entity that can be safely assigned, each
     * field name points to a boolean indicating its status. An empty array
     * means no fields are accessible
     *
     * The special field '\*' can also be mapped, meaning that any other field
     * not defined in the map will take its value. For example, `'*' => true`
     * means that any field not defined in the map will be accessible by default
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'class' => true,
        'method' => true,
        'data' => true,
        'config' => true,
        'priority' => true,
        'queue' => true,
        'exception' => true,
        'created' => true,
    ];

    /**
     * @return array
     */
    protected function _getDecodedData(): array
    {
        return json_decode($this->data, true);
    }
}
