<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Payment;
use App\Models\Report;
use App\Support\Payments\EsewaGateway;
use App\Support\Payments\KhaltiGateway;
use App\Support\Payments\PaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentController extends Controller
{
    /**
     * Session key holding the redeemable payment id for a given report.
     */
    public static function unlockKey(Report $report): string
    {
        return 'download_unlock.'.$report->id;
    }

    /**
     * Begin a payment for the report's download charge via the chosen gateway.
     */
    public function start(Request $request, Report $report, string $gateway): View|RedirectResponse
    {
        $enabled = PaymentSettings::enabledGateways();
        $price = PaymentSettings::price();

        if (! in_array($gateway, $enabled, true) || $price <= 0) {
            throw new NotFoundHttpException;
        }

        $config = $gateway === 'esewa' ? PaymentSettings::esewa() : PaymentSettings::khalti();

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'report_id' => $report->id,
            'gateway' => $gateway,
            'mode' => $config['mode'],
            'amount' => $price,
            'transaction_uuid' => 'RG-'.$report->id.'-'.Str::lower(Str::random(16)),
            'status' => Payment::STATUS_PENDING,
        ]);

        if ($gateway === 'esewa') {
            $esewa = new EsewaGateway;

            return view('payments.esewa-redirect', [
                'action' => $esewa->formUrl(),
                'params' => $esewa->formParams(
                    $payment,
                    route('reports.pay.esewa.callback', $report),
                    route('reports.pay.esewa.callback', $report),
                ),
            ]);
        }

        $paymentUrl = (new KhaltiGateway)->initiate(
            $payment,
            route('reports.pay.khalti.callback', $report),
            route('reports.output', $report),
            'Report download — '.($report->title ?: 'Report #'.$report->id),
        );

        if (! $paymentUrl) {
            $payment->update(['status' => Payment::STATUS_FAILED]);

            return redirect()->route('reports.output', $report)
                ->with('payment-error', 'Could not start the Khalti payment. Please try again.');
        }

        return redirect()->away($paymentUrl);
    }

    /**
     * eSewa redirects here after payment (both success and failure URLs).
     */
    public function esewaCallback(Request $request, Report $report): RedirectResponse
    {
        $payment = $this->pendingPayment($request, $report, 'esewa');

        if ($payment && (new EsewaGateway)->verify($payment, $request->query('data'))) {
            return $this->complete($report, $payment);
        }

        $payment?->update(['status' => Payment::STATUS_FAILED]);

        return redirect()->route('reports.output', $report)
            ->with('payment-error', 'Payment was not completed.');
    }

    /**
     * Khalti redirects here after payment with the pidx and status.
     */
    public function khaltiCallback(Request $request, Report $report): RedirectResponse
    {
        $payment = Payment::where('report_id', $report->id)
            ->where('gateway', 'khalti')
            ->where('status', Payment::STATUS_PENDING)
            ->where('pidx', $request->query('pidx'))
            ->latest()
            ->first();

        if ($payment && (new KhaltiGateway)->verify($payment)) {
            return $this->complete($report, $payment);
        }

        $payment?->update(['status' => Payment::STATUS_FAILED]);

        return redirect()->route('reports.output', $report)
            ->with('payment-error', 'Payment was not completed.');
    }

    /**
     * Spend the unlock when the user takes their paid download, so the next
     * download requires a fresh payment.
     */
    public function consume(Request $request, Report $report): Response
    {
        $paymentId = $request->session()->get(self::unlockKey($report));

        $payment = $paymentId
            ? Payment::where('id', $paymentId)->where('report_id', $report->id)->first()
            : null;

        $paid = false;

        if ($payment && $payment->isRedeemable()) {
            $payment->update(['consumed_at' => now()]);
            $paid = true;
        }

        // Record the download (paid or free) classified by cover type, so the
        // admin dashboard can report TU / London Met / Custom usage.
        Download::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'cover_type' => $report->coverType(),
            'paid' => $paid,
        ]);

        $request->session()->forget(self::unlockKey($report));

        return response()->noContent();
    }

    /**
     * Mark a verified payment complete and arm the one-shot download unlock.
     */
    private function complete(Report $report, Payment $payment): RedirectResponse
    {
        $payment->update(['status' => Payment::STATUS_COMPLETED]);

        session([self::unlockKey($report) => $payment->id]);

        return redirect()->route('reports.output', $report)
            ->with('payment-success', 'Payment received — your download is ready.');
    }

    private function pendingPayment(Request $request, Report $report, string $gateway): ?Payment
    {
        return Payment::where('report_id', $report->id)
            ->where('gateway', $gateway)
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->first();
    }
}
