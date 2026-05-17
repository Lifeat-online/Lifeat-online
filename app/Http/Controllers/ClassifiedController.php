<?php

namespace App\Http\Controllers;

use App\Models\Classified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClassifiedController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q'));
        $classifieds = Classified::query()
            ->with('contentTranslations')
            ->where('status', 'published')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('city', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('classifieds.index', [
            'classifieds' => $classifieds,
            'filters' => ['q' => $q],
        ]);
    }

    public function show(Classified $classified): View
    {
        abort_if($classified->status !== 'published', 404);
        $classified->load('contentTranslations');

        return view('classifieds.show', ['classified' => $classified]);
    }
}
