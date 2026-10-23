<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Services\Filtering\FilterDefinition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the filtered member list (all matches or a selection).
 */
class MemberExportService
{
    public function __construct(private readonly MemberListService $memberList) {}

    /**
     * @param  list<int>|null  $memberIds  only these members (null = all matches)
     * @param  list<int>  $excludedIds  matches deselected in "select all" mode
     */
    public function query(Gym $gym, FilterDefinition $definition, ?array $memberIds = null, array $excludedIds = []): Builder
    {
        return $this->memberList->query($gym, $definition)
            ->when($memberIds !== null, fn (Builder $query) => $query->whereIn('members.id', $memberIds))
            ->when($excludedIds !== [], fn (Builder $query) => $query->whereNotIn('members.id', $excludedIds));
    }

    public function download(Builder $query): StreamedResponse
    {
        $filename = 'mitglieder_'.now()->format('Y-m-d_H-i').'.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM so Excel detects the encoding of umlauts.
            fwrite($output, "\u{FEFF}");
            fputcsv($output, array_keys($this->columns()), ';');

            $query->lazyById(500, 'members.id', 'id')->each(function (Member $member) use ($output) {
                fputcsv($output, array_map(
                    fn (callable $column) => $this->sanitizeCell($column($member)),
                    array_values($this->columns())
                ), ';');
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, callable(Member): mixed>
     */
    private function columns(): array
    {
        $date = fn ($value) => $value ? Carbon::parse($value)->format('d.m.Y') : '';

        return [
            'Mitgliedsnummer' => fn (Member $member) => $member->member_number,
            'Anrede' => fn (Member $member) => $member->salutation,
            'Vorname' => fn (Member $member) => $member->first_name,
            'Nachname' => fn (Member $member) => $member->last_name,
            'E-Mail' => fn (Member $member) => $member->email,
            'Alter' => fn (Member $member) => $member->age,
            'Status' => fn (Member $member) => $member->status_text,
            'PLZ' => fn (Member $member) => $member->postal_code,
            'Ort' => fn (Member $member) => $member->city,
            'Eintrittsdatum' => fn (Member $member) => $date($member->joined_date),
            'Tarife' => fn (Member $member) => $member->memberships
                ->map(fn ($membership) => $membership->membershipPlan?->name)->filter()->unique()->implode(', '),
            'Zahlungsart' => fn (Member $member) => $this->memberList->paymentMethod($member)?->type_text,
            'Offene Posten (EUR)' => fn (Member $member) => number_format((float) $member->outstanding_balance, 2, ',', ''),
            'Fehlgeschlagene Zahlungen' => fn (Member $member) => (int) $member->failed_payment_count,
            'Check-Ins' => fn (Member $member) => (int) $member->check_in_count,
            'Letzter Check-In' => fn (Member $member) => $date($member->last_check_in_at),
            'NFC-Tags' => fn (Member $member) => implode(', ', $this->memberList->nfcTags($member)),
        ];
    }

    /**
     * Neutralizes spreadsheet formulas (CSV injection): a cell starting with
     * =, +, -, @, tab or carriage return is prefixed with an apostrophe.
     */
    private function sanitizeCell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
