<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddMetadataToFailedJobs extends BaseMigration
{
    /**
     * Add nullable envelope metadata so failed jobs keep bookkeeping fields
     * (`tags`, `_uniqueId`, `batch_id`) through store and requeue. Nullable
     * for backward compatibility with existing rows and legacy messages
     * dispatched without a metadata envelope.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('queue_failed_jobs');
        $table->addColumn('metadata', 'text', [
                'null' => true,
                'default' => null,
            ])
            ->update();
    }
}
