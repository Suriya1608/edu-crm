<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TelecallerLeadActivityExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private Collection $telecallers,
        private string $periodLabel = 'Last 30 Days',
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1 — Summary per telecaller
        $summaryRows = [];
        foreach ($this->telecallers as $tc) {
            $leads    = collect($tc['leads']);
            $calls    = $leads->sum('call_count');
            $msgs     = $leads->sum('msg_count');
            $meetings = $leads->sum('meeting_count');
            $converted = $leads->where('status', 'converted')->count();
            $summaryRows[] = [
                $tc['name'],
                $leads->count(),
                $converted,
                $calls,
                $msgs,
                $meetings,
            ];
        }
        $sheets[] = new ArrayExport(
            $summaryRows,
            ['Telecaller', 'Leads Handled', 'Converted', 'Total Calls', 'WhatsApp Messages', 'Meetings'],
            'Summary (' . $this->periodLabel . ')'
        );

        // Sheets per telecaller: Leads overview
        foreach ($this->telecallers as $tc) {
            $leads = collect($tc['leads']);

            $leadRows = $leads->map(fn($l) => [
                $l['lead_code'],
                $l['name'],
                $l['phone'],
                ucfirst($l['status']),
                $l['source'],
                $l['course'],
                $l['final_course'],
                $l['created_at'],
                $l['call_count'],
                $l['msg_count'],
                $l['meeting_count'],
            ])->all();

            $sheetName = substr($tc['name'], 0, 28) . ' - Leads';
            $sheets[] = new ArrayExport(
                $leadRows,
                ['Lead Code', 'Name', 'Phone', 'Status', 'Source', 'Course', 'Final Course', 'Created', 'Calls', 'Messages', 'Meetings'],
                $sheetName
            );
        }

        // Sheet — All Calls
        $callRows = [];
        foreach ($this->telecallers as $tc) {
            foreach ($tc['leads'] as $lead) {
                foreach ($lead['calls'] as $call) {
                    $callRows[] = [
                        $tc['name'],
                        $lead['lead_code'],
                        $lead['name'],
                        $call['date'],
                        ucfirst($call['direction']),
                        ucfirst($call['status']),
                        ucfirst($call['outcome']),
                        $call['duration'],
                    ];
                }
            }
        }
        if (!empty($callRows)) {
            $sheets[] = new ArrayExport(
                $callRows,
                ['Telecaller', 'Lead Code', 'Lead Name', 'Date', 'Direction', 'Status', 'Outcome', 'Duration'],
                'All Calls'
            );
        }

        // Sheet — All Messages
        $msgRows = [];
        foreach ($this->telecallers as $tc) {
            foreach ($tc['leads'] as $lead) {
                foreach ($lead['messages'] as $msg) {
                    $msgRows[] = [
                        $tc['name'],
                        $lead['lead_code'],
                        $lead['name'],
                        $msg['date'],
                        ucfirst($msg['direction']),
                        ucfirst($msg['type']),
                        mb_strimwidth($msg['body'], 0, 200, '...'),
                    ];
                }
            }
        }
        if (!empty($msgRows)) {
            $sheets[] = new ArrayExport(
                $msgRows,
                ['Telecaller', 'Lead Code', 'Lead Name', 'Date', 'Direction', 'Type', 'Message'],
                'All Messages'
            );
        }

        // Sheet — All Meetings
        $meetingRows = [];
        foreach ($this->telecallers as $tc) {
            foreach ($tc['leads'] as $lead) {
                foreach ($lead['meetings'] as $mt) {
                    $meetingRows[] = [
                        $tc['name'],
                        $lead['lead_code'],
                        $lead['name'],
                        $mt['title'],
                        $mt['time'],
                        ucfirst($mt['type']),
                        ucfirst($mt['status']),
                        $mt['notes'],
                    ];
                }
            }
        }
        if (!empty($meetingRows)) {
            $sheets[] = new ArrayExport(
                $meetingRows,
                ['Telecaller', 'Lead Code', 'Lead Name', 'Title', 'Meeting Time', 'Type', 'Status', 'Notes'],
                'All Meetings'
            );
        }

        return $sheets;
    }
}
