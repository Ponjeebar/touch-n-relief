<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class UseCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalUrl = rtrim((string) config('app.url'), '/');
        $canonicalHost = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
        $requestHost = strtolower($request->getHost());

        if ($canonicalHost !== '' && $requestHost === 'www.'.$canonicalHost) {
            $status = $request->isMethodSafe() ? 301 : 308;
            $response = redirect()->to($canonicalUrl.$request->getRequestUri(), $status);

            return $this->expireLegacyCookies($response, $canonicalHost);
        }

        $response = $next($request);

        if ($canonicalHost !== '' && $requestHost === $canonicalHost) {
            $this->expireSharedDomainCookies($response, $canonicalHost);
        }

        return $response;
    }

    private function expireLegacyCookies(Response $response, string $canonicalHost): Response
    {
        foreach ($this->cookieNames() as $name) {
            $response->headers->setCookie($this->expiredCookie($name));
            $response->headers->setCookie($this->expiredCookie($name, '.'.$canonicalHost));
        }

        return $response;
    }

    private function expireSharedDomainCookies(Response $response, string $canonicalHost): void
    {
        foreach ($this->cookieNames() as $name) {
            $response->headers->setCookie($this->expiredCookie($name, '.'.$canonicalHost));
        }
    }

    /** @return array<int, string> */
    private function cookieNames(): array
    {
        return [(string) config('session.cookie'), 'XSRF-TOKEN'];
    }

    private function expiredCookie(string $name, ?string $domain = null): Cookie
    {
        return Cookie::create($name)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withDomain($domain)
            ->withSecure(true)
            ->withHttpOnly($name !== 'XSRF-TOKEN')
            ->withSameSite('lax');
    }
}
