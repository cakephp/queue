<?php
namespace App\Shell\Task;

use Bake\Shell\Task\SimpleBakeTask;

class JobTask extends SimpleBakeTask
{
    public $pathFragment = 'Job/';

    public function name()
    {
        return 'job';
    }

    public function fileName($name)
    {
        return $name . 'Job.php';
    }

    public function template()
    {
        return 'job';
    }
}
