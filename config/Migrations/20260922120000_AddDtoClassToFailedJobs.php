<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddDtoClassToFailedJobs extends BaseMigration
{
    /**
     * Add nullable DTO class metadata so failed jobs keep enough information
     * to rehydrate DTOs on requeue. Nullable for backward compatibility with
     * existing rows and legacy array messages without a DTO.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('queue_failed_jobs');
        $table->addColumn('dto_class', 'string', [
                'length' => 255,
                'null' => true,
                'default' => null,
            ])
            ->update();
    }
}
