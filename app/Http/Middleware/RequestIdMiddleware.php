<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-ID', '');
        $id = $this->sanitize($incoming) ?: (string) Str::uuid();
        // store for later retrieval (e.g., in exception renderer)
        $request->attributes->set('request_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }

    private function sanitize(string $value): string
    {
        $trim = trim($value);
        if ($trim === '') {
            return '';
        }
        if (strlen($trim) > 128) {
            return '';
        }
        if (! preg_match('/^[A-Za-z0-9_.\-]+$/', $trim)) {
            return '';
        }

        return $trim;
    }
}
