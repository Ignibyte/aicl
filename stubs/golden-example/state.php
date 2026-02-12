<?php

// PATTERN: Abstract state class defines the state machine contract.
// PATTERN: Uses Spatie\ModelStates\State as the base.
// PATTERN: All concrete states must implement label(), color(), icon().

namespace Aicl\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ProjectState extends State
{
    // PATTERN: label() returns human-readable display name.
    abstract public function label(): string;

    // PATTERN: color() returns Filament color name for badges/UI.
    abstract public function color(): string;

    // PATTERN: icon() returns Heroicon name for status indicators.
    abstract public function icon(): string;

    // PATTERN: config() defines allowed transitions.
    // PATTERN: Set a default state and explicitly list every valid transition.
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Active::class, OnHold::class)
            ->allowTransition(Active::class, Completed::class)
            ->allowTransition(OnHold::class, Active::class)
            ->allowTransition(OnHold::class, Completed::class)
            ->allowTransition(Completed::class, Archived::class);
    }
}

// --- Concrete states (each in its own file in practice) ---

// PATTERN: Each concrete state is a minimal class with label/color/icon.

// Draft.php
class Draft extends ProjectState
{
    public function label(): string
    {
        return 'Draft';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-pencil-square';
    }
}

// Active.php
class Active extends ProjectState
{
    public function label(): string
    {
        return 'Active';
    }

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'heroicon-o-play';
    }
}

// OnHold.php
class OnHold extends ProjectState
{
    public function label(): string
    {
        return 'On Hold';
    }

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'heroicon-o-pause';
    }
}

// Completed.php
class Completed extends ProjectState
{
    public function label(): string
    {
        return 'Completed';
    }

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }
}

// Archived.php
class Archived extends ProjectState
{
    public function label(): string
    {
        return 'Archived';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'heroicon-o-archive-box';
    }
}
