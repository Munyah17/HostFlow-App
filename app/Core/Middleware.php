<?php

declare(strict_types=1);

namespace App\Core;

final class Middleware
{
    public static function run(array $middleware, string $method): void
    {
        if ($method === 'POST' && !in_array('nocsrf', $middleware, true)) {
            Csrf::verify();
        }
        foreach ($middleware as $item) {
            [$name, $arg] = array_pad(explode(':', $item, 2), 2, '');
            match ($name) {
                'auth' => self::auth(),
                'guest' => self::guest(),
                'staff' => self::roles([Auth::SUPER_ADMIN, Auth::ADMIN]),
                'super' => self::roles([Auth::SUPER_ADMIN]),
                'client' => self::roles([Auth::CLIENT]),
                'role' => self::roles(explode(',', $arg)),
                'nocsrf' => null,
                default => throw new \RuntimeException("Unknown middleware: {$name}"),
            };
        }
    }

    private static function auth(): void
    {
        if (!Auth::check()) {
            if (Request::method() === 'GET') {
                Session::set('_intended', Request::path() . (empty($_GET) ? '' : '?' . http_build_query($_GET)));
            }
            Session::flash('warning', 'Please sign in to continue.');
            redirect('/login');
        }
    }

    private static function guest(): void
    {
        if (Auth::check()) {
            redirect(Auth::home());
        }
    }

    private static function roles(array $roles): void
    {
        self::auth();
        if (!in_array(Auth::role(), $roles, true)) {
            throw new HttpException(403);
        }
    }
}
