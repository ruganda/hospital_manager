@props(['name' => 'patient_id', 'selected' => null, 'label' => 'Patient', 'required' => true])
@php
    $patientOptions = \App\Models\Patient::with('user')->get()
        ->sortBy(fn ($p) => $p->name)
        ->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.$p->patient_no.' · '.$p->user->phone])
        ->all();
@endphp
<x-select :name="$name" :label="$label" :options="$patientOptions" :value="$selected?->id" placeholder="Select a patient…" :required="$required" />
