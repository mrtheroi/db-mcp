<?php

namespace App\Http\Controllers;

use App\Memory\Application\BuildProjectContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContextController extends Controller
{
    public function __invoke(Request $request, BuildProjectContext $buildContext): Response
    {
        $request->validate([
            'project' => ['required', 'string', 'max:255'],
        ]);

        return response($buildContext($request->user()->id, $request->query('project')))
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
