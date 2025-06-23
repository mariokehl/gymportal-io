<?php
// app/Http/Controllers/Web/ContractController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function index()
    {
        return Inertia::render('Contracts/Index');
    }
}
