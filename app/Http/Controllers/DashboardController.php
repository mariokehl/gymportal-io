<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'user' => auth()->user()
        ]);
    }
}
