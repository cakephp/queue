<?php
declare(strict_types=1);

namespace TestApp\Job;

use Cake\Queue\Attribute\Queue;

#[Queue('parent-queue')]
class AttributeParentJob extends AttributeBaseJob
{
}
