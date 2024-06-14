<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use stdClass;

class LoginController extends Controller
{
    public function auth(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = auth()->user();
        $response = new stdClass();
        $response->message = 'Login Successfully';
        $response->data = $user;
        $response->token = $user->createToken('authToken')->plainTextToken;

        return response()->json($response, 200);

        // return response()->noContent();
    }
    
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required'],
        ]);
        $user = User::create([
            'username' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        return response()->json($user);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        $user = Socialite::driver('google')->stateless()->user();

        $existingUser = User::where('google_id', $user->getId())->first();

        if (!$existingUser) {
            $newUser = User::create([
                'username' => $user->getName(),
                'email' => $user->getEmail(),
                'google_id' => $user->getId()
            ]);
            $response = new stdClass();
            $response->message = 'Login Successfully';
            $response->data = $user;
            $response->token = $newUser->createToken('authToken')->plainTextToken;


            return response()->json($response, 200);
        }
        $response = new stdClass();
        $response->message = 'Login Successfully';
        $response->data = $user;
        $response->token = $existingUser->createToken('authToken')->plainTextToken;

        return response()->json(['data' => $response->data, 'token' => $response->token, 'status' => 200, 'message' => $response->message]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
