<?php

namespace App\Console\Commands;

use App\Memory\Application\MergeProjects;
use App\Memory\Domain\ProjectName;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('memory:merge-projects {from : Project whose memories are moved} {to : Project that receives them} {--email= : Only move the rows of this user}')]
#[Description('Move the observations and prompts of one project into another')]
class MergeMemoryProjects extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MergeProjects $mergeProjects): int
    {
        $from = ProjectName::normalize($this->argument('from'));
        $to = ProjectName::normalize($this->argument('to'));

        if ($from === null || $to === null) {
            $this->error('Project names must not be blank.');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error("Cannot merge project {$from} into itself.");

            return self::FAILURE;
        }

        $email = $this->option('email');
        $userId = null;

        if ($email !== null) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $this->error("User {$email} not found.");

                return self::FAILURE;
            }

            $userId = $user->id;
        }

        $moved = DB::transaction(fn () => $mergeProjects($from, $to, $userId));

        $this->line("Moved {$moved['observations']} observations and {$moved['prompts']} prompts from {$from} to {$to}.");
        $this->line("{$moved['collisions']} topic_key collisions (same user and topic_key now twice in {$to}) left to resolve.");

        return self::SUCCESS;
    }
}
