<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
        ]);
        
        return response()->json($user, 201);
    }
    
    public function index()
    {
        $users = User::with('posts')
            ->where('active', true)
            ->get();
            
        return response()->json($users);
    }
}