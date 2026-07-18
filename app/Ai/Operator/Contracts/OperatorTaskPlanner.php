<?php

namespace App\Ai\Operator\Contracts;

use App\Models\OperatorTask;
use App\Models\User;

interface OperatorTaskPlanner
{
    /** @param list<array{name:string,risk:string,arguments:list<string>}> $tools */
    public function nextAction(OperatorTask $task, User $user, array $tools): array;
}
