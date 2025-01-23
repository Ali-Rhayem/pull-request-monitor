<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class GithubApiService
{
    protected string $owner;
    protected string $repo;

    public function __construct()
    {
        $this->owner = config('github.owner');
        $this->repo  = config('github.repo');
    }

    /**
     * 
     * @param  string
     * @return Collection
     */
    public function searchPullRequests(string $queryString): Collection
    {
        $url = 'https://api.github.com/search/issues';

        $fullQuery = 'repo:' . $this->owner . '/' . $this->repo . ' ' . $queryString;
    
        $allItems  = collect();
        $page      = 1;
        $perPage   = 100;
    
        do {
            try {
                $response = Http::withHeaders($this->buildHeaders())->get($url, [
                    'q'        => $fullQuery,
                    'per_page' => $perPage,
                    'page'     => $page,
                ]);

                if ($response->status() === 403 && $this->isRateLimited($response)) {
                    $retryAfter = $response->header('Retry-After', 60);
                    sleep($retryAfter);
                    continue;
                }

                $response->throw();

                $json  = $response->json();
                $items = collect($json['items'] ?? []);

                $allItems = $allItems->merge($items);

                $hasMore = ($items->count() === $perPage);

                $page++;
            }
            catch (\Exception $e) {
                error_log('Error: ' . $e->getMessage());
                break;
            }
        } while ($hasMore && $page <= 10);    
        return $allItems;
    }

    protected function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . config('github.token'),
        ];

        return $headers;
    }

        /**
     * Check if the response indicates a rate limit issue.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return bool
     */
    protected function isRateLimited($response): bool
    {
        return $response->status() === 403 && str_contains($response->header('X-RateLimit-Remaining', ''), '0');
    }
}
