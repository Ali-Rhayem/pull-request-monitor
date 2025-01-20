<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GithubApiService;

class FetchPullRequests extends Command
{
    protected $signature = 'pr:fetch';
    protected $description = 'Fetch pull request information from GitHub and write it to text files.';

    protected GithubApiService $github;

    public function __construct(GithubApiService $github) {
        $this->github = $github;
    }

    public function handle()
    {
        //
    }
}
