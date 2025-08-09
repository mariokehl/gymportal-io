<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FinancesController extends Controller
{
    public function index(Request $request): Response
    {
        $gymId = Auth::user()->current_gym_id;

        $query = Payment::with(['membership.member', 'invoice'])
            ->where('gym_id', $gymId);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('membership.member', function($memberQuery) use ($search) {
                      $memberQuery->where('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->input('amount_from'));
        }

        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->input('amount_to'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $payments = $query->paginate(20)->withQueryString();

        // Get filter options
        $statusOptions = [
            'pending' => 'Ausstehend',
            'paid' => 'Bezahlt',
            'failed' => 'Fehlgeschlagen',
            'refunded' => 'Erstattet',
            'expired' => 'Verfallen',
        ];

        $paymentMethodOptions = array_map(fn($method) => $method['name'], Auth::user()->currentGym->payment_methods_config);

        // Get summary statistics
        $totalAmount = Payment::where('gym_id', $gymId)->sum('amount');
        $paidAmount = Payment::where('gym_id', $gymId)->where('status', 'paid')->sum('amount');
        $pendingAmount = Payment::where('gym_id', $gymId)->where('status', 'pending')->sum('amount');
        $failedAmount = Payment::where('gym_id', $gymId)->where('status', 'failed')->sum('amount');

        return Inertia::render('Finances/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status', 'payment_method', 'date_from', 'date_to', 'amount_from', 'amount_to', 'sort_by', 'sort_order']),
            'statusOptions' => $statusOptions,
            'paymentMethodOptions' => $paymentMethodOptions,
            'statistics' => [
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'failed_amount' => $failedAmount,
                'total_count' => Payment::where('gym_id', $gymId)->count(),
                'paid_count' => Payment::where('gym_id', $gymId)->where('status', 'paid')->count(),
                'pending_count' => Payment::where('gym_id', $gymId)->where('status', 'pending')->count(),
                'failed_count' => Payment::where('gym_id', $gymId)->where('status', 'failed')->count(),
            ]
        ]);
    }

    public function export(Request $request)
    {
        $gymId = auth()->user()->current_gym->id;
        $paymentIds = $request->input('payment_ids', []);
        $exportType = $request->input('export_type', 'csv');

        $payments = Payment::with(['membership.member', 'invoice'])
            ->where('gym_id', $gymId)
            ->whereIn('id', $paymentIds)
            ->get();

        switch ($exportType) {
            case 'pain008':
                return $this->exportPain008($payments);
            case 'csv':
                return $this->exportCsv($payments);
            case 'pdf':
                return $this->exportPdf($payments);
            default:
                return response()->json(['error' => 'Unsupported export type'], 400);
        }
    }

    private function exportPain008($payments)
    {
        // Filter nur SEPA-Lastschriften
        $sepaPayments = $payments->where('payment_method', 'sepa');

        if ($sepaPayments->isEmpty()) {
            return response()->json(['error' => 'Keine SEPA-Lastschriften ausgewählt'], 400);
        }

        // PAIN.008 XML Generation
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></Document>');

        $cstmrDrctDbtInitn = $xml->addChild('CstmrDrctDbtInitn');
        $grpHdr = $cstmrDrctDbtInitn->addChild('GrpHdr');
        $grpHdr->addChild('MsgId', 'SEPA-' . date('YmdHis'));
        $grpHdr->addChild('CreDtTm', date('c'));
        $grpHdr->addChild('NbOfTxs', $sepaPayments->count());
        $grpHdr->addChild('CtrlSum', $sepaPayments->sum('amount'));

        $initgPty = $grpHdr->addChild('InitgPty');
        $initgPty->addChild('Nm', auth()->user()->current_gym->name);

        $pmtInf = $cstmrDrctDbtInitn->addChild('PmtInf');
        $pmtInf->addChild('PmtInfId', 'SEPA-' . date('YmdHis'));
        $pmtInf->addChild('PmtMtd', 'DD');
        $pmtInf->addChild('NbOfTxs', $sepaPayments->count());
        $pmtInf->addChild('CtrlSum', $sepaPayments->sum('amount'));

        $pmtTpInf = $pmtInf->addChild('PmtTpInf');
        $svcLvl = $pmtTpInf->addChild('SvcLvl');
        $svcLvl->addChild('Cd', 'SEPA');
        $lclInstrm = $pmtTpInf->addChild('LclInstrm');
        $lclInstrm->addChild('Cd', 'CORE');
        $pmtTpInf->addChild('SeqTp', 'RCUR');

        $pmtInf->addChild('ReqdColltnDt', date('Y-m-d'));

        $cdtr = $pmtInf->addChild('Cdtr');
        $cdtr->addChild('Nm', auth()->user()->current_gym->name);

        $cdtrAcct = $pmtInf->addChild('CdtrAcct');
        $cdtrAcct->addChild('Id')->addChild('IBAN', 'DE89370400440532013000'); // Beispiel IBAN

        $cdtrAgt = $pmtInf->addChild('CdtrAgt');
        $cdtrAgt->addChild('FinInstnId')->addChild('BIC', 'COBADEFFXXX'); // Beispiel BIC

        $cdtrSchmeId = $pmtInf->addChild('CdtrSchmeId');
        $cdtrSchmeId->addChild('Id')->addChild('PrvtId')->addChild('Othr')->addChild('Id', 'DE98ZZZ09999999999'); // Beispiel Gläubiger-ID

        foreach ($sepaPayments as $payment) {
            $drctDbtTxInf = $pmtInf->addChild('DrctDbtTxInf');
            $pmtId = $drctDbtTxInf->addChild('PmtId');
            $pmtId->addChild('EndToEndId', 'PAYMENT-' . $payment->id);

            $instdAmt = $drctDbtTxInf->addChild('InstdAmt', $payment->amount);
            $instdAmt->addAttribute('Ccy', $payment->currency);

            $drctDbtTx = $drctDbtTxInf->addChild('DrctDbtTx');
            $mndtRltdInf = $drctDbtTx->addChild('MndtRltdInf');
            $mndtRltdInf->addChild('MndtId', 'MANDATE-' . $payment->membership->member->id);
            $mndtRltdInf->addChild('DtOfSgntr', $payment->membership->start_date->format('Y-m-d'));

            $dbtrAgt = $drctDbtTxInf->addChild('DbtrAgt');
            $dbtrAgt->addChild('FinInstnId')->addChild('BIC', 'DEUTDEFFXXX'); // Beispiel BIC

            $dbtr = $drctDbtTxInf->addChild('Dbtr');
            $dbtr->addChild('Nm', $payment->membership->member->first_name . ' ' . $payment->membership->member->last_name);

            $dbtrAcct = $drctDbtTxInf->addChild('DbtrAcct');
            $dbtrAcct->addChild('Id')->addChild('IBAN', 'DE89370400440532013000'); // Beispiel - sollte aus Mitgliedsdaten kommen

            $rmtInf = $drctDbtTxInf->addChild('RmtInf');
            $rmtInf->addChild('Ustrd', $payment->description);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return response($dom->saveXML(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="sepa_pain008_' . date('Y-m-d_H-i-s') . '.xml"'
        ]);
    }

    private function exportCsv($payments)
    {
        $csvData = [];
        $csvData[] = [
            'ID',
            'Datum',
            'Mitglied',
            'Beschreibung',
            'Betrag',
            'Währung',
            'Status',
            'Zahlungsart',
            'Transaktions-ID',
            'Fälligkeitsdatum',
            'Bezahlt am',
            'Notizen'
        ];

        foreach ($payments as $payment) {
            $csvData[] = [
                $payment->id,
                $payment->created_at->format('d.m.Y H:i'),
                $payment->membership->member->first_name . ' ' . $payment->membership->member->last_name,
                $payment->description,
                $payment->formatted_amount,
                $payment->currency,
                $payment->status_text,
                $payment->payment_method_text,
                $payment->transaction_id,
                $payment->due_date ? $payment->due_date->format('d.m.Y') : '',
                $payment->paid_date ? $payment->paid_date->format('d.m.Y') : '',
                $payment->notes
            ];
        }

        $output = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="zahlungen_' . date('Y-m-d_H-i-s') . '.csv"'
        ]);
    }

    private function exportPdf($payments)
    {
        // PDF Export würde hier implementiert werden
        // Beispiel mit einer PDF-Bibliothek wie DOMPDF oder TCPDF
        return response()->json(['message' => 'PDF Export noch nicht implementiert'], 501);
    }
}
