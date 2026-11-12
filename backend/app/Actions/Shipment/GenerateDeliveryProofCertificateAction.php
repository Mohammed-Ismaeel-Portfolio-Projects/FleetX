<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\DeliveryCertificate;
use App\Models\Shipment;
use App\Models\User;
use App\Support\DriverPodStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDeliveryProofCertificateAction
{
    public function __construct(
        private readonly DriverPodStorage $podStorage
    ) {}

    public function executeForClient(User $user, string $shipmentId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        if (! Str::isUuid($shipmentId)) {
            return ApiResult::notFound('Shipment not found');
        }

        $shipment = Shipment::query()
            ->forCustomer($companyId)
            ->with(['deliveryProof.driver.user', 'customerCompany', 'assignments.driver.user'])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        return $this->generate($user, $shipment);
    }

    public function executeForAdmin(User $user, string $shipmentId): ApiResult
    {
        if (! Str::isUuid($shipmentId)) {
            return ApiResult::notFound('Shipment not found');
        }

        $shipment = Shipment::query()
            ->with(['deliveryProof.driver.user', 'customerCompany', 'assignments.driver.user'])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        return $this->generate($user, $shipment);
    }

    private function generate(User $user, Shipment $shipment): ApiResult
    {
        $proof = $shipment->deliveryProof;
        if ($proof === null) {
            return ApiResult::notFound('Delivery proof evidence is not available for this shipment');
        }

        // Photo Base64
        $photoBase64 = null;
        $photoKey = is_string($proof->image_url) ? str_replace('\\', '/', $proof->image_url) : '';
        if ($photoKey !== '' && $this->podStorage->exists($photoKey)) {
            try {
                $raw = Storage::disk(DriverPodStorage::DISK)->get($photoKey);
                $mime = $this->podStorage->mimeType($photoKey);
                $photoBase64 = 'data:'.$mime.';base64,'.base64_encode($raw);
            } catch (\Throwable) {
                $photoBase64 = null;
            }
        }

        // Signature Base64 SVG or Image
        $sigBase64 = null;
        $sigKey = is_string($proof->signature) ? str_replace('\\', '/', $proof->signature) : '';
        if ($sigKey !== '' && $this->podStorage->exists($sigKey)) {
            try {
                $rawSig = Storage::disk(DriverPodStorage::DISK)->get($sigKey);
                if (str_ends_with($sigKey, '.json')) {
                    $decoded = json_decode($rawSig, true);
                    $strokes = is_array($decoded) ? ($decoded['strokes'] ?? $decoded) : [];
                    $svg = $this->renderStrokesToSvg($strokes);
                    $sigBase64 = 'data:image/svg+xml;base64,'.base64_encode($svg);
                } else {
                    $mime = $this->podStorage->mimeType($sigKey);
                    $sigBase64 = 'data:'.$mime.';base64,'.base64_encode($rawSig);
                }
            } catch (\Throwable) {
                $sigBase64 = null;
            }
        }

        // Driver details
        $driver = $proof->driver;
        if ($driver === null && $proof->assignment_id) {
            $assignment = $shipment->assignments->firstWhere('id', $proof->assignment_id);
            $driver = $assignment?->driver;
        }

        $driverName = $driver?->user?->name ?? 'Assigned Driver';

        // Origin & Destination
        $origin = implode(', ', array_filter([$shipment->pickup_company, $shipment->pickup_city, $shipment->pickup_country]));
        $destination = implode(', ', array_filter([$shipment->delivery_company, $shipment->delivery_city, $shipment->delivery_country]));

        $preliminaryHtml = $this->buildHtmlTemplate([
            'shipmentNumber' => $shipment->shipment_number ?? 'FX-'.substr($shipment->id, 0, 8),
            'referenceNumber' => $shipment->reference_number ?? 'N/A',
            'customerName' => $shipment->customerCompany?->name ?? 'FLeetX Customer',
            'origin' => $origin ?: 'N/A',
            'destination' => $destination ?: 'N/A',
            'confirmedAt' => $proof->confirmed_at?->format('Y-m-d H:i:s T') ?? date('Y-m-d H:i:s'),
            'receiverName' => $proof->receiver_name ?? 'Recipient',
            'receiverRole' => $proof->receiver_role ?? 'N/A',
            'conditionStatus' => strtoupper($proof->condition_status ?? 'GOOD'),
            'deliveryNotes' => $proof->delivery_notes ?? 'Delivered successfully without exceptions.',
            'latitude' => $proof->latitude !== null ? number_format((float) $proof->latitude, 6) : null,
            'longitude' => $proof->longitude !== null ? number_format((float) $proof->longitude, 6) : null,
            'driverName' => $driverName,
            'photoBase64' => $photoBase64,
            'sigBase64' => $sigBase64,
            'generatedAt' => date('Y-m-d H:i:s T'),
            'hash' => 'calculating...',
        ]);

        $contentHash = hash('sha256', $preliminaryHtml);
        $finalHtml = str_replace('calculating...', $contentHash, $preliminaryHtml);

        $pdfBytes = Pdf::loadHTML($finalHtml)->setPaper('a4', 'portrait')->output();
        $fileHash = hash('sha256', $pdfBytes);

        // Store generated certificate on private disk
        $relativeDir = 'delivery-certificates/'.$shipment->id;
        $relativeFile = $relativeDir.'/POD-Certificate.pdf';
        Storage::disk(DriverPodStorage::DISK)->put($relativeFile, $pdfBytes);

        // Update database audit record
        DeliveryCertificate::updateOrCreate(
            ['shipment_id' => $shipment->id],
            [
                'delivery_proof_id' => $proof->id,
                'generated_by' => $user->id,
                'generated_at' => now(),
                'file_path' => $relativeFile,
                'hash' => $fileHash,
            ]
        );

        $safeNum = $shipment->shipment_number ? Str::slug($shipment->shipment_number) : 'shipment';

        return ApiResult::success([
            'pdf_bytes' => $pdfBytes,
            'filename' => "POD-Certificate-{$safeNum}.pdf",
            'hash' => $fileHash,
        ], 'Proof of delivery certificate generated successfully');
    }

    private function renderStrokesToSvg(array $strokes, int $width = 300, int $height = 150): string
    {
        $paths = '';
        foreach ($strokes as $stroke) {
            $points = $stroke['points'] ?? [];
            if (empty($points)) {
                continue;
            }
            $d = '';
            foreach ($points as $idx => $p) {
                $x = (float) ($p['x'] ?? 0);
                $y = (float) ($p['y'] ?? 0);
                $d .= ($idx === 0) ? "M {$x} {$y}" : " L {$x} {$y}";
            }
            $paths .= "<path d=\"{$d}\" stroke=\"#1E3A8A\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" fill=\"none\" />";
        }

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$width} {$height}\" width=\"{$width}\" height=\"{$height}\">{$paths}</svg>";
    }

    private function buildHtmlTemplate(array $data): string
    {
        $photoHtml = $data['photoBase64']
            ? '<img src="'.$data['photoBase64'].'" style="max-width:100%; height:160px; border-radius:6px; border:1px solid #CBD5E1; object-fit:cover;" />'
            : '<div style="height:140px; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:6px; text-align:center; line-height:140px; color:#94A3B8; font-size:12px;">No Photo Evidence</div>';

        $sigHtml = $data['sigBase64']
            ? '<img src="'.$data['sigBase64'].'" style="max-width:100%; height:120px; border-radius:6px; border:1px solid #CBD5E1; background:#FAFAFA;" />'
            : '<div style="height:120px; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:6px; text-align:center; line-height:120px; color:#94A3B8; font-size:12px;">No Digital Signature</div>';

        $gpsHtml = ($data['latitude'] && $data['longitude'])
            ? "{$data['latitude']}, {$data['longitude']} (<a href=\"https://www.google.com/maps/search/?api=1&query={$data['latitude']},{$data['longitude']}\" style=\"color:#2563EB;\">Google Maps</a>)"
            : 'Location snapshot unavailable';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1E293B; margin: 0; padding: 24px; font-size: 13px; line-height: 1.5; }
    .header { border-bottom: 3px solid #1E3A8A; padding-bottom: 16px; margin-bottom: 24px; }
    .logo { font-size: 24px; font-weight: 800; color: #1E3A8A; letter-spacing: 1px; }
    .title { font-size: 16px; font-weight: 700; color: #0F172A; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; }
    .badge { display: inline-block; background: #DCFCE7; color: #15803D; font-weight: 700; padding: 4px 10px; border-radius: 999px; font-size: 11px; }
    .grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .grid th, .grid td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #E2E8F0; }
    .grid th { background: #F8FAFC; color: #64748B; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
    .card { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; margin-bottom: 20px; }
    .section-title { font-size: 12px; font-weight: 700; color: #1E3A8A; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #E2E8F0; padding-bottom: 4px; }
    .footer { border-top: 1px solid #E2E8F0; margin-top: 30px; padding-top: 12px; font-size: 10px; color: #94A3B8; text-align: center; }
    .hash { font-family: monospace; font-size: 9px; color: #64748B; background: #F1F5F9; padding: 4px 8px; border-radius: 4px; display: inline-block; margin-top: 4px; }
</style>
</head>
<body>
    <div class="header">
        <table style="width:100%;">
            <tr>
                <td>
                    <div class="logo">FLeetX</div>
                    <div class="title">Proof of Delivery Certificate</div>
                </td>
                <td style="text-align:right;">
                    <div class="badge">&#10003; DELIVERED & VERIFIED</div>
                    <div style="font-size:11px; color:#64748B; margin-top:6px;">Date: {$data['confirmedAt']}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Shipment Information</div>
    <table class="grid">
        <tr>
            <th>Shipment #</th>
            <td><strong>{$data['shipmentNumber']}</strong></td>
            <th>Reference #</th>
            <td>{$data['referenceNumber']}</td>
        </tr>
        <tr>
            <th>Customer</th>
            <td>{$data['customerName']}</td>
            <th>Driver</th>
            <td>{$data['driverName']}</td>
        </tr>
        <tr>
            <th>Origin</th>
            <td>{$data['origin']}</td>
            <th>Destination</th>
            <td>{$data['destination']}</td>
        </tr>
    </table>

    <div class="section-title">Delivery & Receiver Details</div>
    <table class="grid">
        <tr>
            <th>Receiver Name</th>
            <td><strong>{$data['receiverName']}</strong></td>
            <th>Receiver Role</th>
            <td>{$data['receiverRole']}</td>
        </tr>
        <tr>
            <th>Condition</th>
            <td><strong style="color:#15803D;">{$data['conditionStatus']}</strong></td>
            <th>GPS Location</th>
            <td>{$gpsHtml}</td>
        </tr>
    </table>

    <div class="card">
        <div style="font-weight:700; font-size:11px; color:#64748B; text-transform:uppercase; margin-bottom:4px;">Delivery Notes</div>
        <div>{$data['deliveryNotes']}</div>
    </div>

    <div class="section-title">Evidence Attachments</div>
    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:10px;">
                <div style="font-weight:700; font-size:11px; color:#64748B; margin-bottom:6px;">Photo Evidence</div>
                {$photoHtml}
            </td>
            <td style="width:50%; vertical-align:top; padding-left:10px;">
                <div style="font-weight:700; font-size:11px; color:#64748B; margin-bottom:6px;">Recipient Signature</div>
                {$sigHtml}
            </td>
        </tr>
    </table>

    <div class="footer">
        <div>Generated by FLeetX Intelligent Fleet Management System on {$data['generatedAt']}</div>
        <div>Document SHA-256 Integrity Hash:</div>
        <div class="hash">{$data['hash']}</div>
    </div>
</body>
</html>
HTML;
    }
}
