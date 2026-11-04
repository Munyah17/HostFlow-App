<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfService
{
    public static function invoice(int $invoiceId): string
    {
        $invoice = InvoiceService::find($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException('Invoice not found.');
        }
        $html = View::partial('pdf.invoice', [
            'invoice' => $invoice,
            'items' => InvoiceService::items($invoiceId),
            'payments' => db()->fetchAll("SELECT * FROM payments WHERE invoice_id = ? AND status = 'completed' ORDER BY paid_at", [$invoiceId]),
        ]);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', BASE_PATH . '/storage/cache');
        $options->set('fontCache', BASE_PATH . '/storage/cache');
        $options->set('chroot', BASE_PATH . '/public');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        return (string) $dompdf->output();
    }

    public static function sendInvoice(int $invoiceId): never
    {
        $invoice = db()->fetch('SELECT number FROM invoices WHERE id = ?', [$invoiceId]);
        $pdf = self::invoice($invoiceId);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $invoice['number']) . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }
}
