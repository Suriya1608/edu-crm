<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class ExotelController extends Controller
//
{
    public function generateToken()
    {
        $response = Http::withBasicAuth(
            env('EXOTEL_SID'),
            env('EXOTEL_TOKEN')
        )->post("https://api.exotel.com/v1/Accounts/" . env('EXOTEL_SID') . "/Users", [
            'UserId' => Auth::id(),
            'DisplayName' => Auth::user()->name
        ]);

        return response()->json([
            'apiKey' => env('EXOTEL_API_KEY'),
            'userId' => Auth::id(),
            'displayName' => Auth::user()->name
        ]);
    }
}
