<?php

namespace App\Http\Controllers;

use App\OrderStatusUpdater;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function show(Request $request, int $order, OrderStatusUpdater $updater): View
    {
        $order = $request->user()->orders()
            ->with(['items.product', 'items.variant', 'installments.payments', 'payments'])
            ->findOrFail($order);
        $statusDetails = match ($order->order_status) {
            'pending' => ['title' => 'Waiting for confirmation', 'message' => 'Your order has been placed and is waiting for the store to confirm it.'],
            'processing' => ['title' => 'Your order is being processed', 'message' => 'The store is preparing your order.'],
            'out_for_delivery' => ['title' => 'Your order is out for delivery', 'message' => 'Your order has left the store and is on its way to you.'],
            'completed' => ['title' => 'Order completed', 'message' => 'The store has marked this order as completed.'],
            'cancelled' => ['title' => 'Order cancelled', 'message' => 'This order has been cancelled.'],
        };
        $hasBlockingPayment = $updater->hasPaymentsPreventingCancellation($order);
        $paidAmount = (float) $order->payments->sum('amount');

        return view('customer.order-details', [
            'user' => $request->user(),
            'order' => $order,
            'statusDetails' => $statusDetails,
            'hasBlockingPayment' => $hasBlockingPayment,
            'canCancel' => $order->order_status === 'pending' && ! $hasBlockingPayment,
            'paidAmount' => $paidAmount,
            'remainingBalance' => max(0, round($order->payable_amount - $paidAmount, 2)),
            'progressStep' => array_search($order->order_status, ['pending', 'processing', 'out_for_delivery', 'completed'], true),
            'paymentLabel' => match ($order->payment_method) {
                'cod' => 'Cash on delivery',
                'paylater' => 'Pay Later',
                'qrph' => 'QR Ph',
                'gcash' => 'GCash',
                'maya' => 'Maya',
                default => ucfirst($order->payment_method),
            },
        ]);
    }

    public function cancel(Request $request, int $order, OrderStatusUpdater $updater): RedirectResponse
    {
        $order = $request->user()->orders()->findOrFail($order);
        try {
            $updater->update($order, 'cancelled', requiredCurrentStatus: 'pending');
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('customer.orders.show', $order));
        }

        return redirect()->route('customer.orders.show', $order)
            ->with('success', 'Your order has been cancelled.');
    }
}
