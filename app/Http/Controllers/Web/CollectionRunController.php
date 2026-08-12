<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CollectionCase;
use App\Models\CollectionRun;
use App\Models\Gym;
use App\Services\CollectionExportService;
use App\Services\CollectionService;
use App\Services\DunningService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CollectionRunController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CollectionService $collectionService,
        protected DunningService $dunningService,
        protected CollectionExportService $exportService,
    ) {}

    /**
     * Overview of all collection runs and the members ready for handover.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', CollectionRun::class);

        $gym = Auth::user()->currentGym;

        $runs = CollectionRun::where('gym_id', $gym->id)
            ->orderByDesc('handed_over_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Finances/Inkasso/Index', [
            'runs' => $runs,
            'readyMembers' => $this->readyRows($gym),
            'statistics' => $this->collectionService->statistics($gym),
            'settings' => $gym->getInkassoSettingsForDisplay(),
        ]);
    }

    /**
     * Detail view of a single run.
     */
    public function show(CollectionRun $run): Response
    {
        $this->authorize('view', $run);

        $run->load(['cases.member', 'cases.claims']);

        return Inertia::render('Finances/Inkasso/Show', [
            'run' => $run,
            'cases' => $run->cases->map(fn (CollectionCase $case) => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'partner_reference' => $case->partner_reference,
                'status' => $case->status,
                'status_text' => $case->status_text,
                'status_color' => $case->status_color,
                'total_amount' => $case->total_amount,
                'paid_amount' => $case->paid_amount,
                'open_amount' => $case->open_amount,
                'member' => [
                    'id' => $case->member?->id,
                    'first_name' => $case->member?->first_name,
                    'last_name' => $case->member?->last_name,
                    'member_number' => $case->member?->member_number,
                ],
            ]),
            'settings' => $run->gym->getInkassoSettingsForDisplay(),
        ]);
    }

    /**
     * Create a new run for the selected members.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CollectionRun::class);

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],
        ]);

        $gym = Auth::user()->currentGym;

        try {
            $run = $this->collectionService->createRun(
                $gym,
                $validated['member_ids'],
                Auth::id(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['member_ids' => $e->getMessage()]);
        }

        $result = $this->collectionService->transmitRun($run);

        $message = "Inkassolauf {$run->run_number} erstellt · {$run->member_count} Mitglieder übergeben.";

        if ($result['failed'] > 0) {
            $message .= " {$result['failed']} Akten konnten nicht übertragen werden.";
        }

        return redirect()
            ->route('finances.inkasso.show', $run)
            ->with('success', $message);
    }

    /**
     * Undo a run: all members leave the collection status.
     */
    public function undo(CollectionRun $run): RedirectResponse
    {
        $this->authorize('delete', $run);

        $this->collectionService->undoRun($run);

        return back()->with('success', "Inkassolauf {$run->run_number} rückgängig gemacht.");
    }

    /**
     * Download the detail file of a run.
     */
    public function export(Request $request, CollectionRun $run)
    {
        $this->authorize('view', $run);

        $format = $request->input('format') === 'xlsx' ? 'xlsx' : 'csv';

        return $this->exportService->download($run, $format);
    }

    /**
     * Members that completed the dunning process, shaped for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readyRows(Gym $gym): array
    {
        return collect($this->dunningService->readyForCollection($gym))
            ->map(fn (array $row) => [
                'id' => $row['member']->id,
                'first_name' => $row['member']->first_name,
                'last_name' => $row['member']->last_name,
                'member_number' => $row['member']->member_number,
                'level' => $row['level'],
                'claims' => $row['claims'],
                'open_amount' => $row['open_amount'],
                'block' => $row['block'],
            ])
            ->values()
            ->all();
    }
}
