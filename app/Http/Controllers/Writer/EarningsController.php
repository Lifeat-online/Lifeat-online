<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\ArticleWordLedger;
use App\Support\Onboarding\WriterOnboardingChecklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    public function index(Request $request, WriterOnboardingChecklist $onboarding): View
    {
        $ledgers = ArticleWordLedger::with(['article', 'approver'])
            ->where('writer_user_id', $request->user()->id)
            ->latest('approved_at')
            ->paginate(15);

        return view('writer.earnings.index', [
            'ledgers' => $ledgers,
            'summary' => [
                'pending' => (float) ArticleWordLedger::where('writer_user_id', $request->user()->id)->where('status', 'pending')->sum('gross_amount'),
                'batched' => (float) ArticleWordLedger::where('writer_user_id', $request->user()->id)->where('status', 'batched')->sum('gross_amount'),
                'paid' => (float) ArticleWordLedger::where('writer_user_id', $request->user()->id)->where('status', 'paid')->sum('gross_amount'),
            ],
            'writerOnboarding' => $onboarding->forUser($request->user()),
        ]);
    }
}
