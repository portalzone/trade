<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Waybill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class WaybillService
{
    /**
     * Generate waybill for an order
     */
    public function generateWaybill(Order $order, array $data = []): Waybill
    {
        DB::beginTransaction();

        try {
            $existing = Waybill::where('order_id', $order->id)->first();
            if ($existing) {
                throw new \Exception('Waybill already exists for this order');
            }

            $seller = $order->seller;
            $buyer = $order->buyer;

            $waybill = Waybill::create([
                'order_id' => $order->id,
                'waybill_number' => Waybill::generateWaybillNumber(),
                'tracking_code' => Waybill::generateTrackingCode(),
                'sender_name' => $seller->full_name,
                'sender_address' => $data['sender_address'] ?? 'Address not provided',
                'sender_phone' => $seller->phone ?? 'Not provided',
                'recipient_name' => $buyer->full_name,
                'recipient_address' => $data['recipient_address'] ?? 'Address not provided',
                'recipient_phone' => $buyer->phone ?? 'Not provided',
                'item_description' => $order->title,
                'weight' => $data['weight'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'declared_value' => $order->price,
                'delivery_type' => $data['delivery_type'] ?? 'standard',
                'courier_service' => $data['courier_service'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'generated_at' => now(),
            ]);

            AuditService::log(
                'waybill.generated',
                "Waybill generated for order #{$order->id}",
                $waybill,
                [],
                ['waybill_number' => $waybill->waybill_number],
                ['order_id' => $order->id]
            );

            DB::commit();

            return $waybill;
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'waybill.generation_failed',
                'Failed to generate waybill',
                $e->getMessage(),
                ['order_id' => $order->id]
            );

            throw $e;
        }
    }

    /**
     * Generate PDF for waybill
     */
    public function generatePDF(Waybill $waybill)
    {
        return Pdf::loadView('pdf.waybill', ['waybill' => $waybill])
            ->setPaper('a4', 'portrait');
    }

    /**
     * Download waybill PDF
     */
    public function downloadPDF(Waybill $waybill)
    {
        $pdf = $this->generatePDF($waybill);
        $filename = 'waybill-' . $waybill->waybill_number . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Stream waybill PDF (view in browser)
     */
    public function streamPDF(Waybill $waybill)
    {
        $pdf = $this->generatePDF($waybill);
        
        return $pdf->stream();
    }

    /**
     * Get waybill by order
     */
    public function getByOrder(Order $order): ?Waybill
    {
        return Waybill::where('order_id', $order->id)->first();
    }

    /**
     * Update waybill tracking information
     */
    public function updateTracking(Waybill $waybill, array $data): Waybill
    {
        $waybill->update([
            'courier_service' => $data['courier_service'] ?? $waybill->courier_service,
            'metadata' => array_merge($waybill->metadata ?? [], $data['metadata'] ?? []),
        ]);

        return $waybill->fresh();
    }
}
