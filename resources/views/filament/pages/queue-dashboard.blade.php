<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Queue Overview
        </x-slot>
        <x-slot name="description">
            Monitor your application's job queues and manage failed jobs.
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <p>
                This dashboard provides an overview of your application's queue system.
                You can see pending jobs, failed jobs, and retry or delete failed jobs as needed.
            </p>
            <ul>
                <li><strong>Pending Jobs:</strong> Jobs waiting to be processed across all queues.</li>
                <li><strong>Failed Jobs:</strong> Jobs that have failed and may need attention.</li>
                <li><strong>Last Failure:</strong> When the most recent job failure occurred.</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
