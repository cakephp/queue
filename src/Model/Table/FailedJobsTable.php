<?php
declare(strict_types=1);

namespace Cake\Queue\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * FailedJobs Model
 *
 * @method \Cake\Queue\Model\Entity\FailedJob newEmptyEntity()
 * @method \Cake\Queue\Model\Entity\FailedJob newEntity(array $data, array $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob[] newEntities(array $data, array $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, ...$args)
 * @method \Cake\Queue\Model\Entity\FailedJob findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \Cake\Queue\Model\Entity\FailedJob[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class FailedJobsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('queue_failed_jobs');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('class')
            ->maxLength('class', 255)
            ->requirePresence('class', 'create')
            ->notEmptyString('class');

        $validator
            ->scalar('method')
            ->maxLength('method', 255)
            ->requirePresence('method', 'create')
            ->notEmptyString('method');

        $validator
            ->scalar('data')
            ->requirePresence('data', 'create')
            ->notEmptyString('data');

        $validator
            ->scalar('config')
            ->maxLength('config', 255)
            ->notEmptyString('config');

        $validator
            ->scalar('priority')
            ->maxLength('priority', 255)
            ->allowEmptyString('priority');

        $validator
            ->scalar('queue')
            ->maxLength('queue', 255)
            ->notEmptyString('queue');

        $validator
            ->scalar('exception')
            ->allowEmptyString('exception');

        return $validator;
    }
}
