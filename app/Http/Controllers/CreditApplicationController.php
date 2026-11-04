<?php

namespace App\Http\Controllers;

use App\PayLaterPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class CreditApplicationController extends Controller
{
    public function create(Request $request, PayLaterPayments $payments)
    {
        $user = $request->user();
        $user->refreshCreditHold();

        $openPaymentAttempt = $user->creditPaymentAttempts()
            ->whereIn('status', ['creating', 'pending', 'review'])
            ->latest('id')
            ->first();
        if ($openPaymentAttempt) {
            try {
                $openPaymentAttempt = $payments->sync($openPaymentAttempt);
            } catch (Throwable) {
                // Keep the saved attempt available when the provider cannot be reached.
            }
            if (in_array($openPaymentAttempt->status, ['succeeded', 'failed'], true)) {
                $openPaymentAttempt = null;
            }
            $user->refresh();
        }
        $orders = $user->orders()
            ->where('payment_method', 'paylater')
            ->where('order_status', '!=', 'cancelled')
            ->with(['items.product', 'items.variant', 'installments.payments'])
            ->orderByDesc('order_date')
            ->get();
        $outstandingBalance = $user->unpaidCreditBalance();
        $hasCreditAccount = $user->credit_approved || $outstandingBalance > 0 || $openPaymentAttempt !== null;
        $availableCredit = $user->credit_approved ? max(0, (float) $user->credit_limit - $outstandingBalance) : 0;
        $revokedApplication = $user->creditApplications()->whereNotNull('revoked_at')->orderByDesc('revoked_at')->latest('id')->first();

        return view('customer.credit_apply', [
            'user' => $user,
            'application' => $user->latestCreditApplication,
            'openPaymentAttempt' => $openPaymentAttempt,
            'paymentAttempts' => $user->creditPaymentAttempts()->latest('id')->limit(10)->get(),
            'orders' => $orders,
            'hasCreditAccount' => $hasCreditAccount,
            'availableCredit' => $availableCredit,
            'revokedApplication' => $revokedApplication,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->credit_approved) {
            return redirect()->route('credit.apply')
                ->with('success', 'Your credit application is already approved.');
        }

        if ($user->creditApplications()->where('status', 'pending')->exists()) {
            return redirect()->route('credit.apply')
                ->withErrors(['application' => 'You already have an application under review.']);
        }

        $firstSubmittedApplication = $user->creditApplications()
            ->oldest('id')
            ->first();

        $lockedDueDay = $firstSubmittedApplication?->credit_due_day;

        $creditDueDayRule = $lockedDueDay !== null
            ? Rule::in([$lockedDueDay])
            : Rule::in(['15', 'last_day']);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:150'],
            'middle_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'phone_number' => ['required', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,phone_no'],
            'province' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:2000'],
            'barangay' => ['required', 'string', 'max:2000'],
            'street' => ['required', 'string', 'max:2000'],
            'credit_due_day' => ['required', $creditDueDayRule],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'id_type' => [
                'required',
                Rule::in(['national_id', 'drivers_license', 'umid', 'passport']),
            ],
            'id_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'selfie_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'accept_paylater_terms' => ['accepted'],
            'accept_credit_privacy' => ['accepted'],
        ]);

        $directory = "credit-applications/{$user->id}";

        $idPhotoPath = $validated['id_photo']->store($directory, 'local');
        $selfiePhotoPath = $validated['selfie_photo']->store($directory, 'local');

        if ($idPhotoPath === false || $selfiePhotoPath === false) {
            Storage::disk('local')->delete(array_filter([$idPhotoPath, $selfiePhotoPath]));

            return back()->withInput()
                ->withErrors(['upload' => 'The files could not be saved. Please try again.']);
        }

        $application = $user->creditApplications()->make([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'province' => $validated['province'],
            'city' => $validated['city'],
            'barangay' => $validated['barangay'],
            'street' => $validated['street'],
            'credit_due_day' => $validated['credit_due_day'],
            'monthly_income' => $validated['monthly_income'] ?? null,
            'id_type' => $validated['id_type'],
        ]);

        $application->id_photo_path = $idPhotoPath;
        $application->selfie_photo_path = $selfiePhotoPath;
        $application->terms_version = 'paylater-v2';
        $application->privacy_notice_version = 'credit-privacy-v1';
        $application->privacy_notice_accepted_at = now();
        $application->terms_accepted_at = now();

        try {
            $saved = $application->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete([$idPhotoPath, $selfiePhotoPath]);
            report($exception);

            return back()->withInput()
                ->withErrors(['application' => 'The application could not be saved. Please try again.']);
        }

        if (! $saved) {
            Storage::disk('local')->delete([$idPhotoPath, $selfiePhotoPath]);

            return back()->withInput()
                ->withErrors(['application' => 'The application could not be saved. Please try again.']);
        }

        return redirect()->route('credit.apply')
            ->with('success', 'Your credit application was submitted for review.');
    }
}
