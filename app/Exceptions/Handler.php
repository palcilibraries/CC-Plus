<?php

namespace App\Exceptions;

// use Exception;
use Throwable;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        // Database/query exceptions return formatted JSON string
        if ( ($exception instanceof \PDOException) || ($exception instanceof \QueryException) ) {
            $msg = "Database Error";
            foreach ($exception->errorInfo as $data) {
                $msg .= " : " . $data;
            }
            return response()->json(['result' => false, 'msg' => $msg, 'ccp_key' => session('ccp_con_key')]);
        }
        return parent::render($request, $exception);
    }
}
