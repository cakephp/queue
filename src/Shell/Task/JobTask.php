<?php
declare(strict_types=1);

namespace App\Shell\Task;

use Bake\Shell\Task\SimpleBakeTask;

class JobTask extends SimpleBakeTask
{
    public $pathFragment = 'Job/';

    /**
     * @return string
     */
    public function name()
    {
        return 'job';
    }

    /**
     * @param string $name Name.
     * @return string
     */
    public function fileName($name)
    {
        return $name . 'Job.php';
    }

    /**
     * @return string
     */
    public function template()
    {
        return 'job';
    }
}
