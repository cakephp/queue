<?php
declare(strict_types=1);

namespace Cake\Queue\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;
use TestApp\Job\LogToDebugJob;
use TestApp\Job\MaxAttemptsIsThreeJob;

/**
 * FailedJobsFixture
 */
class FailedJobsFixture extends TestFixture
{
    public $table = 'queue_failed_jobs';

    /**
     * Fields
     *
     * @var array
     */
    // phpcs:disable
    public $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'class' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'method' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'data' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'config' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'priority' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'queue' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'exception' => ['type' => 'text', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'precision' => null, 'null' => true, 'default' => null, 'comment' => ''],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
        ],
    ];
    // phpcs:enable
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'class' => LogToDebugJob::class,
                'method' => 'execute',
                'data' => '{"sample_data_1": "sample value", "sample_data_2": 1}',
                'config' => 'default',
                'priority' => null,
                'queue' => 'default',
                'exception' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created' => '2022-10-11 18:42:29',
            ],
            [
                'id' => 2,
                'class' => MaxAttemptsIsThreeJob::class,
                'method' => 'execute',
                'data' => '{"sample_data_1": "sample value", "sample_data_2": 1}',
                'config' => 'default',
                'priority' => null,
                'queue' => 'default',
                'exception' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created' => '2022-10-11 18:42:29',
            ],
            [
                'id' => 3,
                'class' => LogToDebugJob::class,
                'method' => 'execute',
                'data' => '{"sample_data_1": "sample value", "sample_data_2": 1}',
                'config' => 'alternate_config',
                'priority' => null,
                'queue' => 'alternate_queue',
                'exception' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created' => '2022-10-11 18:42:29',
            ],
        ];
        parent::init();
    }
}
