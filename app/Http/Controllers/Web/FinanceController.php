<?php
// app/Http/Controllers/Web/FinanceController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index()
    {
        return Inertia::render('Finances/Index');
    }
}
