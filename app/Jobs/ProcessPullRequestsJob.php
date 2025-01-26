<?php

namespace App\Jobs;

use App\Services\GithubApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Revolution\Google\Sheets\Facades\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as Google_Service_Sheets_Request;

class ProcessPullRequestsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Dispatchable;

    protected $owner;
    protected $repo;

    /**
     * Create a new job instance.
     *
     * @param string $owner
     * @param string $repo
     */
    public function __construct(string $owner, string $repo)
    {
        $this->owner = $owner;
        $this->repo = $repo;
    }

    /**
     * Execute the job.
     *
     * @param GithubApiService $github
     * @return void
     */
    public function handle(GithubApiService $github)
    {
        $queries = [
            'Old Pull Requests' => 'is:pr is:open created:<' . now()->subDays(14)->format('Y-m-d'),
            'Pull Requests with Review Required' => 'is:pr is:open review:required',
            'Pull Requests with Success Status' => 'is:pr is:open status:success',
            'Pull Requests with No Reviews Requested' => 'is:pr is:open -review:required',
        ];

        foreach ($queries as $sheetName => $queryString) {
            try {
                $pullRequests = $github->searchPullRequests($this->owner, $this->repo, $queryString);

                if ($pullRequests->isEmpty()) {
                    info("No data found for {$this->owner}/{$this->repo} with query: {$queryString}");
                    continue;
                }

                $fileName = "{$this->repo}_{$sheetName}.txt";
                $this->writeToFile($fileName, $pullRequests);
                $this->writeToGoogleSheet($sheetName, $pullRequests);
            } catch (\Exception $e) {
                error_log("Error processing {$this->owner}/{$this->repo}: " . $e->getMessage());
            }
        }
    }

    protected function writeToFile(string $fileName, $prs): void
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

    protected function writeToGoogleSheet(string $sheetName, $prs): void
    {
        $spreadsheetId = env('POST_SPREADSHEET_ID');

        if ($prs->isEmpty()) {
            info("No pull requests found. Skipped writing to Google Sheet: '{$sheetName}'");
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

            info("Created new sheet/tab named: {$sheetName}");
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

        info("Appended " . count($rows) . " rows to sheet: '{$sheetName}'");
    }
}
