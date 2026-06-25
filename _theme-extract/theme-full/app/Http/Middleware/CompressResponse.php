<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompressResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$this->shouldCompress($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || strlen($content) < 1024) {
            return $response;
        }

        $encoding = $this->getPreferredEncoding($request);

        if (!$encoding) {
            return $response;
        }

        $compressed = match ($encoding) {
            'gzip' => gzencode($content, 6),
            'deflate' => compress($content),
            'br' => function_exists('brotli_compress') ? brotli_compress($content, 6) : false,
            default => false,
        };

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', $encoding);
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }

    /**
     * Determine if the response should be compressed.
     */
    protected function shouldCompress(Request $request, Response $response): bool
    {
        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        $compressibleTypes = [
            'text/html',
            'text/css',
            'text/plain',
            'text/xml',
            'application/json',
            'application/javascript',
            'application/xml',
            'application/rss+xml',
            'application/atom+xml',
            'image/svg+xml',
        ];

        $isCompressible = false;
        foreach ($compressibleTypes as $type) {
            if (str_contains($contentType, $type)) {
                $isCompressible = true;
                break;
            }
        }

        return $isCompressible;
    }

    /**
     * Get the preferred encoding from the request's Accept-Encoding header.
     */
    protected function getPreferredEncoding(Request $request): ?string
    {
        $acceptEncoding = $request->header('Accept-Encoding', '');

        if (str_contains($acceptEncoding, 'br') && function_exists('brotli_compress')) {
            return 'br';
        }

        if (str_contains($acceptEncoding, 'gzip')) {
            return 'gzip';
        }

        if (str_contains($acceptEncoding, 'deflate')) {
            return 'deflate';
        }

        return null;
    }
}
