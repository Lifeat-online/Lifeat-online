<?php

namespace App\Http\Controllers;

use App\Services\AskLifeService;
use App\Services\VoiceGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AskLifeController extends Controller
{
    public function store(Request $request, AskLifeService $askLife): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return response()->json($askLife->answer($validated['question'], $request->user()));
    }

    public function speak(Request $request, VoiceGatewayService $voice): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'min:2', 'max:1000'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'af'])],
        ]);

        $result = $voice->speakAskLife(
            $validated['text'],
            $validated['locale'] ?? null,
            $request->user()
        );

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }
}
