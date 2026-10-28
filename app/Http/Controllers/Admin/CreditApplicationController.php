<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CreditApplicationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $status = $validated['status'] ?? '';
        $query = CreditApplication::with('user')->latest();

        if ($status !== '') {
            $query->where('status', $status);
        }

        $applications = $query->paginate(15)->withQueryString();

        return view('admin.credit_applications.index', compact('applications', 'status'));
    }

    public function show(CreditApplication $application)
    {
        $application->load('user');

        return view('admin.credit_applications.show', compact('application'));
    }

    public function document(CreditApplication $application, string $type)
    {
        abort_unless(in_array($type, ['id', 'selfie'], true), 404);

        $path = $type === 'id'
            ? $application->id_photo_path
            : $application->selfie_photo_path;

        // Only serve files stored in this applicant's private folder.
        $expectedFolder = "credit-applications/{$application->user_id}/";
        abort_unless(str_starts_with($path, $expectedFolder), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, CreditApplication $application)
    {
        $validated = $request->validate([
            'credit_limit' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        DB::transaction(function () use ($application, $validated) {
            $application = CreditApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            abort_unless($application->status === 'pending', 409, 'This application was already reviewed.');

            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($application->user_id);

            $application->status = 'approved';
            $application->reviewed_at = now();
            $application->rejection_reason = null;
            $application->save();
            
            $user->credit_due_day = $application->credit_due_day;
            $user->credit_approved = true;
            $user->credit_limit = $validated['credit_limit'];
            $user->save();
        });

        return redirect()
            ->route('admin.credit-applications.show', $application)
            ->with('success', 'Application approved and credit limit set.');
    }

    public function reject(Request $request, CreditApplication $application)
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($application, $validated) {
            $application = CreditApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            abort_unless($application->status === 'pending', 409, 'This application was already reviewed.');

            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($application->user_id);

            $application->status = 'rejected';
            $application->reviewed_at = now();
            $application->rejection_reason = $validated['rejection_reason'] ?? null;
            $application->save();

            $user->credit_approved = false;
            $user->save();
        });

        return redirect()
            ->route('admin.credit-applications.show', $application)
            ->with('success', 'Application rejected.');
    }
}
