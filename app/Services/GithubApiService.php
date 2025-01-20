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
            $response = Http::withHeaders($this->buildHeaders())->get($url, [
                'q'        => $fullQuery,
                'per_page' => $perPage,                'page'     => $page,
            ]);
    
            $response->throw();

            
            $json  = $response->json();
            $items = collect($json['items'] ?? []);
    
            $allItems = $allItems->merge($items);
    
            $hasMore = ($items->count() === $perPage);
    
            $page++;
    
        } while ($hasMore && $page <= 10);    
        return $allItems;
    }

    protected function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
        ];

        return $headers;
    }
}
