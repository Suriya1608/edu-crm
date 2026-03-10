<div class="chart-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Time Period</label>
            <select name="date_range" class="form-select form-select-sm">
                <option value="7" {{ ($filters['date_range'] ?? '30') === '7' ? 'selected' : '' }}>Last 7 Days</option>
                <option value="30" {{ ($filters['date_range'] ?? '30') === '30' ? 'selected' : '' }}>Last 30 Days</option>
                <option value="90" {{ ($filters['date_range'] ?? '30') === '90' ? 'selected' : '' }}>Last 90 Days</option>
                <option value="quarter" {{ ($filters['date_range'] ?? '30') === 'quarter' ? 'selected' : '' }}>This Quarter</option>
                <option value="year" {{ ($filters['date_range'] ?? '30') === 'year' ? 'selected' : '' }}>This Year</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Source</label>
            <select name="source" class="form-select form-select-sm">
                <option value="all">All Sources</option>
                @foreach (($filterOptions['sources'] ?? collect()) as $source)
                    <option value="{{ $source }}" {{ ($filters['source'] ?? 'all') === $source ? 'selected' : '' }}>{{ $source }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Telecaller</label>
            <select name="telecaller" class="form-select form-select-sm">
                <option value="all">All Telecallers</option>
                @foreach (($filterOptions['telecallers'] ?? collect()) as $tele)
                    <option value="{{ $tele->id }}" {{ (string)($filters['telecaller'] ?? 'all') === (string)$tele->id ? 'selected' : '' }}>{{ $tele->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm w-100">Search</button>
            <a href="{{ $baseRoute }}" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
        </div>
    </form>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-sm btn-outline-secondary"
            href="{{ route('manager.reports.export', ['report' => $reportKey, 'format' => 'excel'] + request()->query()) }}">
            <span class="material-icons me-1" style="font-size:16px;">file_download</span> Export Excel
        </a>
        <a class="btn btn-sm btn-primary"
            href="{{ route('manager.reports.export', ['report' => $reportKey, 'format' => 'pdf'] + request()->query()) }}"
            target="_blank">
            <span class="material-icons me-1" style="font-size:16px;">picture_as_pdf</span> Export PDF
        </a>
    </div>
</div>

