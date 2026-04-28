<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Contracts\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact.index', [
            'contact' => [
                'email' => (string) Setting::getValue('contact.email', 'support@life.local'),
                'phone' => (string) Setting::getValue('contact.phone', '+27 58 000 0000'),
                'whatsapp' => (string) Setting::getValue('contact.whatsapp', '+27 82 000 0000'),
                'hours' => (string) Setting::getValue('contact.hours', 'Mon-Fri, 08:00-17:00'),
                'region' => (string) Setting::getValue('contact.region', 'Eastern Freestate'),
            ],
        ]);
    }
}
