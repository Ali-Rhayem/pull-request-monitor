<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class GithubApiService
{
    /**
     * 
     * @param  string
     * @return Collection
     */
    public function searchPullRequests(string $owner, string $repo, string $queryString): Collection
    {
        $url = 'https://api.github.com/search/issues';

        $fullQuery = "repo:{$owner}/{$repo} " . $queryString;

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
            } catch (\Exception $e) {
                error_log('Error: ' . $e->getMessage());
                break;
            }
        } while ($hasMore && $page <= 10);
        return $allItems;
    }

    protected function buildHeaders(): array
    {
        $token = config('github.token');

        if (empty($token)) {
            throw new \Exception('GitHub token is not set in the configuration.');
        }

        return [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    protected function isRateLimited($response): bool
    {
        return $response->status() === 403 && str_contains($response->header('X-RateLimit-Remaining', ''), '0');
    }
}
