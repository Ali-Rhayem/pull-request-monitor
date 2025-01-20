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

        $response = Http::withHeaders($this->buildHeaders())
            ->get($url, [
                'q'       => $fullQuery,
                'per_page' => 100,
            ]);

        $response->throw();

        $json = $response->json();

        return collect($json['items'] ?? []);
    }

    protected function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
        ];

        return $headers;
    }
}
