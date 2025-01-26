<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GithubApiService;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Jobs\ProcessPullRequestsJob;

class FetchPullRequests extends Command
{
    protected $signature = 'pr:fetch';
    protected $description = 'Fetch pull request information from GitHub and write it to text files.';


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $repositories = [
            ['owner' => config('github.owner'), 'repo' => config('github.repo')],
            // Add more repositories if needed
        ];

        foreach ($repositories as $repository) {
            $owner = $repository['owner'];
            $repo = $repository['repo'];

            $this->info("Dispatching job for repository: {$owner}/{$repo}...");

            ProcessPullRequestsJob::dispatch($owner, $repo);
        }

        $this->info('All jobs have been dispatched!');
        return Command::SUCCESS;
    }
}
