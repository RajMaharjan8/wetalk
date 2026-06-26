<?php

use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] class extends Component
{
    use WithPagination;

    /** Filter: all | completed | pending | failed. */
    public string $filter = 'completed';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function getTransactionsProperty()
    {
        return Payment::with(['user', 'report'])
            ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
            ->latest()
            ->paginate(25);
    }

    public function getSummaryProperty(): array
    {
        return [
            'revenue' => (float) Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount'),
            'completed' => Payment::where('status', Payment::STATUS_COMPLETED)->count(),
            'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
        ];
    }

    /** Tailwind classes for a status pill. */
    public function statusBadge(string $status): string
    {
        return [
            'completed' => 'bg-green-50 text-green-700 ring-green-200',
            'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'failed' => 'bg-red-50 text-red-700 ring-red-200',
            'canceled' => 'bg-slate-100 text-slate-600 ring-slate-200',
        ][$status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
    }
}; ?>

@php($title = 'Transactions')

<div class="space-y-6">

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-3xl font-semibold tracking-tight text-slate-900">Rs. {{ number_format($this->summary['revenue'], 2) }}</p>
            <p class="mt-1 text-sm text-slate-500">Total revenue</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($this->summary['completed']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Completed payments</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($this->summary['pending']) }}</p>
            <p class="mt-1 text-sm text-slate-500">Pending</p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Transactions</h2>
            <div class="flex gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium">
                @foreach (['completed' => 'Completed', 'pending' => 'Pending', 'failed' => 'Failed', 'all' => 'All'] as $value => $label)
                    <button type="button" wire:click="$set('filter', '{{ $value }}')" class="rounded-md px-3 py-1.5 {{ $filter === $value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($this->transactions->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-slate-400">No transactions to show.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                            <th class="px-6 py-3">User</th>
                            <th class="px-6 py-3">Report</th>
                            <th class="px-6 py-3">Gateway</th>
                            <th class="px-6 py-3">Amount</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Reference</th>
                            <th class="px-6 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->transactions as $tx)
                            <tr wire:key="tx-{{ $tx->id }}" class="text-slate-700">
                                <td class="px-6 py-3">
                                    <p class="font-medium text-slate-900">{{ $tx->user?->name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-slate-400">{{ $tx->user?->email }}</p>
                                </td>
                                <td class="px-6 py-3 max-w-[200px] truncate">{{ $tx->report?->title ?: $tx->report?->module_title ?: '—' }}</td>
                                <td class="px-6 py-3">
                                    {{ $tx->gateway === 'esewa' ? 'eSewa' : ucfirst($tx->gateway) }}
                                    <span class="ml-1 text-xs text-slate-400">({{ $tx->mode }})</span>
                                </td>
                                <td class="px-6 py-3 font-medium text-slate-900">Rs. {{ number_format($tx->amount, 2) }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 {{ $this->statusBadge($tx->status) }}">{{ ucfirst($tx->status) }}</span>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs text-slate-500">{{ $tx->ref_id ?? '—' }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-6 py-3">{{ $this->transactions->links() }}</div>
        @endif
    </div>
</div>
