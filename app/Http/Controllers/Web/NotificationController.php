<?php
// app/Http/Controllers/Web/NotificationController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        return Inertia::render('Notifications/Index');
    }
}
