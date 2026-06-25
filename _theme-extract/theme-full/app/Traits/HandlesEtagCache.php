<?php

namespace Pterodactyl\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

trait HandlesEtagCache
{
    /**
     * Generate an ETag from the given content.
     */
    protected function generateEtag(mixed $content): string
    {
        $hash = is_string($content) ? $content : json_encode($content);

        return '"' . md5($hash) . '"';
    }

    /**
     * Check if the request has a matching ETag and return a 304 response if so.
     */
    protected function checkEtagRequest(Request $request, mixed $content): ?\Illuminate\Http\Response
    {
        $etag = $this->generateEtag($content);

        $noneMatch = $request->header('If-None-Match');

        if ($noneMatch === $etag) {
            return Response::make('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'max-age=0, must-revalidate',
            ]);
        }

        return null;
    }

    /**
     * Add ETag headers to the response.
     */
    protected function addEtagHeaders(\Illuminate\Http\Response $response, mixed $content): \Illuminate\Http\Response
    {
        $etag = $this->generateEtag($content);

        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=300, must-revalidate');

        return $response;
    }

    /**
     * Handle ETag caching for a response. Returns a 304 if the ETag matches,
     * otherwise adds the ETag header to the response.
     */
    protected function handleEtagCache(Request $request, mixed $content): \Illuminate\Http\Response
    {
        $notModified = $this->checkEtagRequest($request, $content);
        if ($notModified) {
            return $notModified;
        }

        $response = is_string($content)
            ? Response::make($content, 200, ['Content-Type' => 'text/html'])
            : response()->json($content);

        return $this->addEtagHeaders($response, $content);
    }

    /**
     * Cache content with an ETag for the given duration.
     */
    protected function cacheWithEtag(string $key, int $ttl, callable $callback): mixed
    {
        $cacheKey = "etag:{$key}";

        $cached = Cache::get($cacheKey);

        $content = $callback();

        $etag = $this->generateEtag($content);

        if ($cached && ($cached['etag'] ?? null) === $etag) {
            return $cached['content'];
        }

        Cache::put($cacheKey, [
            'etag' => $etag,
            'content' => $content,
        ], $ttl);

        return $content;
    }
}
