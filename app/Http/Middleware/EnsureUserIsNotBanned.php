<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user has been banned, log them out immediately
     * and redirect them to the login page with an error message.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'حساب شما توسط مدیریت مسدود شده است. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.',
            ]);
        }

        return $next($request);
    }
}
