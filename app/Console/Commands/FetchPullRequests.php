<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GithubApiService;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Revolution\Google\Sheets\Facades\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as Google_Service_Sheets_Request;

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
        // 1- List of all open pull requests created more than 14 days ago
        $Date14DaysAgo = Carbon::now()->subDays(14)->format('Y-m-d');
        $queryString = 'is:pr is:open created:<' . $Date14DaysAgo;
        $oldPullRequests = $this->github->searchPullRequests($queryString);
        $this->writeToFile('1-old-pull-requests.txt', $oldPullRequests);

        // 2- List of all open pull requests with a review required:
        $queryString = 'is:pr is:open review:required';
        $pullRequestsWithReview = $this->github->searchPullRequests($queryString);
        $this->writeToFile('2-pull-requests-with-review.txt', $pullRequestsWithReview);

        // 3- List of all open pull requests where review status is `success`:
        $queryString = 'is:pr is:open status:success';
        $pullRequestsWithSuccessStatus = $this->github->searchPullRequests($queryString);
        $this->writeToFile('3-pull-requests-with-success-status.txt', $pullRequestsWithSuccessStatus);

        // 4- List of all open pull requests with no reviews requested (no assigned reviewers)
        $queryString = 'is:pr is:open -review:required';
        $pullRequestsWithSuccessStatus = $this->github->searchPullRequests($queryString);
        $this->writeToFile('4-pull-requests-with-no-reviews-requested.txt', $pullRequestsWithSuccessStatus);

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

    protected function writeToGoogleSheet(string $sheetName, Collection $prs)
    {
        $spreadsheetId = env('POST_SPREADSHEET_ID');
    
        if ($prs->isEmpty()) {
            $this->info("No pull requests found. Skipped writing to Google Sheet: '{$sheetName}'");
            return;
        }
    
        $service = Sheets::getService();
    
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $sheetTitles = collect($spreadsheet->getSheets())->pluck('properties.title');
    
        if (!$sheetTitles->contains($sheetName)) {
            $requests = [];
    
            $requests[] = new Google_Service_Sheets_Request([
                'addSheet' => [
                    'properties' => ['title' => $sheetName],
                ],
            ]);
    
            $body = new BatchUpdateSpreadsheetRequest(['requests' => $requests]);
            $service->spreadsheets->batchUpdate($spreadsheetId, $body);
    
            $this->info("Created new sheet/tab named: {$sheetName}");
        }
    
        $rows = $prs->map(function ($pr) {
            return [
                $pr['number'],
                $pr['title'],
                $pr['html_url'],
                $pr['created_at'],
            ];
        })->toArray();
    
        array_unshift($rows, ['PR Number', 'Title', 'URL', 'Created At']);
    
        Sheets::spreadsheet($spreadsheetId)
            ->sheet($sheetName)
            ->range('A1')
            ->append($rows);
    
        $this->info("Appended " . count($rows) . " rows to sheet: '{$sheetName}'");
    }    
}
