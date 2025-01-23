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

        $repositories = [
            ['owner' => config('github.owner'), 'repo' => config('github.repo')],
        ];

        foreach ($repositories as $repository) {
            $owner = $repository['owner'];
            $repo = $repository['repo'];

            $this->info("Fetching pull requests for {$owner}/{$repo}...");

            $this->fetchAndProcessPullRequests($owner, $repo, 'is:pr is:open created:<' . Carbon::now()->subDays(14)->format('Y-m-d'), 'Old Pull Requests', '1-old-pull-requests.txt');
            $this->fetchAndProcessPullRequests($owner, $repo, 'is:pr is:open review:required', 'Pull Requests with Review Required', '2-pull-requests-with-review.txt');
            $this->fetchAndProcessPullRequests($owner, $repo, 'is:pr is:open status:success', 'Pull Requests with Success Status', '3-pull-requests-with-success-status.txt');
            $this->fetchAndProcessPullRequests($owner, $repo, 'is:pr is:open -review:required', 'Pull Requests with No Reviews Requested', '4-pull-requests-with-no-reviews-requested.txt');
        }

        $this->info('All pull request data has been fetched and written!');
        return Command::SUCCESS;
    }

    protected function fetchAndProcessPullRequests(string $owner, string $repo, string $queryString, string $sheetName, string $fileName): void
    {
        try {
            $pullRequests = $this->github->searchPullRequests($owner, $repo, $queryString);

            if ($pullRequests->isEmpty()) {
                $this->info("No data found for {$owner}/{$repo} with query: {$queryString}");
                return;
            }

            $this->writeToFile($fileName, $pullRequests);
            $this->writeToGoogleSheet($sheetName, $pullRequests);

        } catch (\Exception $e) {
            $this->error("Error processing {$owner}/{$repo}: " . $e->getMessage());
        }
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

    protected function writeToGoogleSheet(string $sheetName, Collection $prs): void
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
