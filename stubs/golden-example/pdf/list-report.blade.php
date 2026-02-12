{{-- PATTERN: PDF template for a list/table report. --}}
{{-- PATTERN: Includes optional summary stats and filter display. --}}

@extends('aicl::pdf.layout')

@section('content')
    <h1>Projects Report</h1>

    {{-- PATTERN: Optional summary stats section. --}}
    @if(isset($summary))
    <table class="stats-row">
        <tr>
            <td>
                <div class="stat-value">{{ $summary['total'] ?? $projects->count() }}</div>
                <div class="stat-label">Total Projects</div>
            </td>
            <td>
                <div class="stat-value">{{ $summary['active'] ?? 0 }}</div>
                <div class="stat-label">Active</div>
            </td>
            <td>
                <div class="stat-value">{{ $summary['completed'] ?? 0 }}</div>
                <div class="stat-label">Completed</div>
            </td>
            <td>
                <div class="stat-value">{{ $summary['on_hold'] ?? 0 }}</div>
                <div class="stat-label">On Hold</div>
            </td>
        </tr>
    </table>
    @endif

    {{-- PATTERN: Show applied filters. --}}
    @if(isset($filters) && count($filters) > 0)
    <p class="text-small text-muted mb-10">
        <strong>Filters applied:</strong>
        @foreach($filters as $key => $value)
            {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}@if(!$loop->last), @endif
        @endforeach
    </p>
    @endif

    {{-- PATTERN: Data table with column widths. --}}
    <table>
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 25%">Name</th>
                <th style="width: 12%">Status</th>
                <th style="width: 10%">Priority</th>
                <th style="width: 18%">Owner</th>
                <th style="width: 15%">Start Date</th>
                <th style="width: 15%">End Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projects as $project)
                <tr>
                    <td>{{ $project->id }}</td>
                    <td>{{ $project->name }}</td>
                    <td>
                        <span class="badge badge-{{ strtolower(class_basename($project->status)) }}">
                            {{ $project->status->label() }}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $project->priority->value }}">
                            {{ ucfirst($project->priority->value) }}
                        </span>
                    </td>
                    <td>{{ $project->owner?->name ?? '—' }}</td>
                    <td>{{ $project->start_date?->format('M j, Y') ?? '—' }}</td>
                    <td>{{ $project->end_date?->format('M j, Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">No projects found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- PATTERN: Pagination info footer. --}}
    @if($projects instanceof \Illuminate\Pagination\LengthAwarePaginator)
    <p class="text-small text-muted text-right">
        Showing {{ $projects->firstItem() ?? 0 }} to {{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }} projects
    </p>
    @else
    <p class="text-small text-muted text-right">
        Total: {{ $projects->count() }} projects
    </p>
    @endif
@endsection
