<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->throttleApi();

        /**
         * Returning null stops Laravel trying to redirect an unauthenticated
         * API caller to a `login` route that does not exist in a headless
         * backend. Without this, a request missing `Accept: application/json`
         * gets a 500 ("Route [login] not defined") instead of a 401.
         */
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/login'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * One error shape for the whole API, loosely following RFC 7807.
         *
         * ARAMS 1.0 returned raw exception text to the client — for example
         * 'Submission failed: ' . $e->getMessage() and a PDO connection error
         * printed verbatim on the login page. Nothing here leaks internals
         * unless the app is in debug mode.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $title, $detail, $errors] = match (true) {
                $e instanceof ValidationException => [
                    422, 'Validation failed', 'Some fields need attention.', $e->errors(),
                ],
                $e instanceof AuthenticationException => [
                    401, 'Unauthenticated', 'A valid, active session is required.', null,
                ],
                $e instanceof AuthorizationException => [
                    403, 'Forbidden', $e->getMessage() ?: 'You may not perform this action.', null,
                ],
                $e instanceof ModelNotFoundException => [
                    404, 'Not found', 'The requested resource does not exist.', null,
                ],
                /**
                 * Must precede the RuntimeException arm below: every Symfony
                 * HttpException extends RuntimeException, so checking the
                 * broader type first turns an authorization 403 into a 422.
                 */
                $e instanceof HttpExceptionInterface => [
                    $e->getStatusCode(),
                    $e->getStatusCode() === 403 ? 'Forbidden' : 'Request failed',
                    $e->getMessage() ?: 'The request could not be completed.',
                    null,
                ],
                /**
                 * Database errors must be caught before the RuntimeException
                 * arm: QueryException extends PDOException extends
                 * RuntimeException, so without these two cases a constraint
                 * violation was returned to the client with the full SQL
                 * statement, database name, host and port in `detail`.
                 *
                 * That is the same internal disclosure ARAMS 1.0 shipped, and
                 * it survived here because the tests asserted status codes
                 * without ever reading the body.
                 */
                $e instanceof UniqueConstraintViolationException => [
                    409,
                    'Conflict',
                    'That would duplicate a record that already exists.',
                    null,
                ],
                $e instanceof QueryException => [
                    500,
                    'Server error',
                    config('app.debug') ? $e->getMessage() : 'A database error occurred.',
                    null,
                ],
                $e instanceof RuntimeException => [
                    // Domain and workflow refusals — safe, intentional messages
                    // written for the reader, never raw exception text.
                    422, 'Action not allowed', $e->getMessage(), null,
                ],
                default => [
                    500,
                    'Server error',
                    config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                    null,
                ],
            };

            return response()->json(array_filter([
                'title'  => $title,
                'status' => $status,
                'detail' => $detail,
                'errors' => $errors,
            ], fn ($v) => $v !== null), $status);
        });
    })->create();
