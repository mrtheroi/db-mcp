<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginCodeMail;
use App\Models\LoginCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LoginCodeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = $request->string('email')->trim()->lower()->value();

        Mail::to($email)->send(new LoginCodeMail(LoginCode::issue($email)));

        return response()->json(['message' => 'If the email is valid, a login code has been sent.'], 202);
    }
}
