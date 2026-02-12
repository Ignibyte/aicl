{{-- PATTERN: PDF template for a single entity report. --}}
{{-- PATTERN: Extends aicl::pdf.layout which provides styles and structure. --}}
{{-- PATTERN: Uses Blade escaped output {{ }} for text, {!! !!} only for pre-formatted content. --}}

@extends('aicl::pdf.layout')

@section('content')
    <h1>{{ $project->name }}</h1>

    <!-- Status and Priority -->
    <p>
        <span class="badge badge-{{ strtolower(class_basename($project->status)) }}">{{ $project->status->label() }}</span>
        <span class="badge badge-{{ $project->priority->value }}">{{ ucfirst($project->priority->value) }} Priority</span>
    </p>

    <!-- PATTERN: Key-value info grid using table layout (DomPDF limitation). -->
    <h2>Project Details</h2>
    <table class="info-grid">
        <tr>
            <td>
                <div class="label">Project ID</div>
                <div class="value">{{ $project->id }}</div>
            </td>
            <td>
                <div class="label">Owner</div>
                <div class="value">{{ $project->owner?->name ?? 'Unassigned' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Start Date</div>
                <div class="value">{{ $project->start_date?->format('F j, Y') ?? 'Not set' }}</div>
            </td>
            <td>
                <div class="label">End Date</div>
                <div class="value">{{ $project->end_date?->format('F j, Y') ?? 'Not set' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Budget</div>
                <div class="value">{{ $project->budget ? '$' . number_format($project->budget, 2) : 'Not set' }}</div>
            </td>
            <td>
                <div class="label">Created</div>
                <div class="value">{{ $project->created_at->format('F j, Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- PATTERN: Conditional sections with @if --}}
    @if($project->description)
    <h2>Description</h2>
    <div class="card">
        {!! nl2br(e($project->description)) !!}
    </div>
    @endif

    @if($project->tags && $project->tags->count() > 0)
    <h2>Tags</h2>
    <p>
        @foreach($project->tags as $tag)
            <span class="badge" style="background-color: {{ $tag->color ?? '#e5e7eb' }}; color: #374151;">
                {{ $tag->name }}
            </span>
        @endforeach
    </p>
    @endif

    {{-- PATTERN: Activity timeline for audit history. --}}
    @if(isset($activities) && $activities->count() > 0)
    <h2>Recent Activity</h2>
    <div class="card">
        @foreach($activities as $activity)
            <div class="timeline-item">
                <div class="timeline-date">{{ $activity->created_at->format('M j, Y g:i A') }}</div>
                <div class="timeline-content">
                    {{ $activity->description }}
                    @if($activity->causer)
                        <span class="text-muted">by {{ $activity->causer->name }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    <div class="text-small text-muted mt-10">
        <p>Last updated: {{ $project->updated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>
@endsection
