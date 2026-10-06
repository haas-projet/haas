<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Support\Http\MemberSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LogoutController extends Controller
{
    public function __invoke(Request $request, MemberSession $session): Response
    {
        $session->end($request);

        return new Response('', 204);
    }
}
