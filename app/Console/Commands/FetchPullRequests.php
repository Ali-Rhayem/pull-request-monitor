<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GithubApiService;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class FetchPullRequests extends Command
{
    protected $signature = 'pr:fetch';
    protected $description = 'Fetch pull request information from GitHub and write it to text files.';

    protected GithubApiService $github;

    public function __construct(GithubApiService $github)
    {
        parent::__construct();
        $this->github = $github;
    }

    public function handle()
    {
        $Date14DaysAgo = Carbon::now()->subDays(14)->format('Y-m-d');
        $queryString = sprintf('is:pr is:open created:<%s', $Date14DaysAgo);
        $oldPullRequests = $this->github->searchPullRequests($queryString);
        $this->writeToFile('1-old-pull-requests.txt', $oldPullRequests);

        $this->info('pull request data has been fetched and written to text file!');
        return Command::SUCCESS;
    }

    protected function writeToFile(string $fileName, Collection $prs): void
    {
        $path = storage_path('app/' . $fileName);

        $lines = $prs->map(function ($pr) {
            $number = $pr['number'];
            $title = $pr['title'];
            $url = $pr['html_url'];
            $createdAt = $pr['created_at'];
            return "PR #{$number} - {$title}\nURL: {$url}\nCreated At: {$createdAt}\n----\n";
        })->implode("\n");

        file_put_contents($path, $lines);
    }
}
