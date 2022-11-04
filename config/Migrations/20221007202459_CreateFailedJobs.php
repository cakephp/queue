<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateFailedJobs extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('queue_failed_jobs');
        $table->addColumn('class', 'string', [
                'length' => 255,
                'null' => false,
                'default' => null,
            ])
            ->addColumn('method', 'string', [
                'length' => 255,
                'null' => false,
                'default' => null,
            ])
            ->addColumn('data', 'text', [
                'null' => false,
                'default' => null,
            ])
            ->addColumn('config', 'string', [
                'length' => 255,
                'null' => false,
                'default' => null,
            ])
            ->addColumn('priority', 'string', [
                'length' => 255,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('queue', 'string', [
                'length' => 255,
                'null' => false,
                'default' => null,
            ])
            ->addColumn('exception', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();
    }
}
