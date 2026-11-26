<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChatReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['open', 'resolved'])]]);
        $status = $data['status'] ?? 'open';
        $reports = ChatReport::query()
            ->with(['message.conversation:id,item_name', 'message.sender:id,name', 'reporter:id,name'])
            ->when($status === 'open', fn ($query) => $query->whereNull('resolved_at'), fn ($query) => $query->whereNotNull('resolved_at'))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.chat-reports.index', compact('reports', 'status'));
    }

    public function resolve(Request $request, ChatReport $report): RedirectResponse
    {
        $data = $request->validate(['resolution' => ['required', 'string', 'max:2000']], [], ['resolution' => 'Catatan keputusan']);

        $updated = ChatReport::query()->whereKey($report->id)->whereNull('resolved_at')->update([
            'resolution' => $data['resolution'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return to_route('admin.chat-reports.index')->with('status', $updated
            ? 'Keputusan laporan telah dicatat.'
            : 'Laporan ini sudah ditinjau.');
    }
}
