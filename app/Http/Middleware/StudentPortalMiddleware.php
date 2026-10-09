<?php

namespace App\Http\Middleware;

use AbsenShalat\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentPortalMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Student::query()
            ->with('classroom')
            ->find($request->session()->get('student_portal_id'));

        if ($student === null) {
            $request->session()->forget('student_portal_id');

            return redirect('/');
        }

        $request->attributes->set('studentPortalStudent', $student);

        return $next($request);
    }
}