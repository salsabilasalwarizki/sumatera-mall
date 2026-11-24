@extends('layouts.app')

@section('title', 'Team — SumatraMall')

@section('content')
@php
$teamMembers = [
    ['number' => '01', 'name' => '', 'identity' => '', 'photo' => 'images/team/member-01.jpg'],
    ['number' => '02', 'name' => '', 'identity' => '', 'photo' => 'images/team/member-02.jpg'],
    ['number' => '03', 'name' => '', 'identity' => '', 'photo' => 'images/team/member-03.jpg'],
    ['number' => '04', 'name' => '', 'identity' => '', 'photo' => 'images/team/member-04.jpg'],
    ['number' => '05', 'name' => '', 'identity' => '', 'photo' => 'images/team/member-05.jpg'],
];
@endphp
<div class="max-w-6xl mx-auto px-6">
    <header class="mb-12">
        <span class="bubble-filter bubble-filter--active" data-i18n="team_eyebrow">ABOUT THE PROJECT</span>
        <h1 class="font-display text-5xl font-bold mt-5 mb-3 gradient-text" data-i18n="team_title">The team behind SumatraMall</h1>
        <p class="max-w-2xl text-gray-400" data-i18n="team_description">
            A multidisciplinary project bringing mall discovery, semantic data, and practical trip planning together.
        </p>
        <p class="max-w-3xl text-sm text-gray-500 mt-4" data-i18n="team_project_detail">
            SumatraMall connects mall, store, category, and location data with SPARQL so visitors can search, compare destinations, discover stores, and plan routes across Sumatra.
        </p>
    </header>

    <div class="grid md:grid-cols-3 gap-6">
        <article class="glass team-card rounded-3xl p-7">
            <span class="team-card__icon" aria-hidden="true">01</span>
            <h2 class="font-display text-xl font-bold mt-6 mb-3" data-i18n="team_semantic_title">Semantic data</h2>
            <p class="text-sm text-gray-400" data-i18n="team_semantic_description">
                Connecting mall, store, category, and location information in a browsable knowledge graph.
            </p>
        </article>
        <article class="glass team-card rounded-3xl p-7">
            <span class="team-card__icon" aria-hidden="true">02</span>
            <h2 class="font-display text-xl font-bold mt-6 mb-3" data-i18n="team_discovery_title">Mall discovery</h2>
            <p class="text-sm text-gray-400" data-i18n="team_discovery_description">
                Building clear search, useful filters, and accessible ways to explore shopping destinations.
            </p>
        </article>
        <article class="glass team-card rounded-3xl p-7">
            <span class="team-card__icon" aria-hidden="true">03</span>
            <h2 class="font-display text-xl font-bold mt-6 mb-3" data-i18n="team_integrations_title">Connected experiences</h2>
            <p class="text-sm text-gray-400" data-i18n="team_integrations_description">
                Bringing together maps, open data, and trip-planning tools while making data limits clear.
            </p>
        </article>
    </div>

    <section class="mt-14" aria-labelledby="team-members-title">
        <div class="flex flex-wrap justify-between items-end gap-4 mb-6">
            <div>
                <h2 id="team-members-title" class="font-display text-3xl font-bold" data-i18n="team_members_title">Meet the team</h2>
                <p class="text-sm text-gray-400 mt-2" data-i18n="team_edit_instructions">
                    Add member names and identities in this page, and place photos in public/images/team/member-01.jpg through member-05.jpg.
                </p>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($teamMembers as $member)
            <article class="glass team-card rounded-3xl p-5">
                @if(file_exists(public_path($member['photo'])))
                <img class="team-card__photo" src="{{ asset($member['photo']) }}"
                     alt=""
                     loading="lazy" decoding="async">
                @else
                <div class="team-card__photo team-card__photo--empty">
                    <span aria-hidden="true">{{ $member['number'] }}</span>
                    <span data-i18n="team_photo_placeholder">Add photo</span>
                </div>
                @endif
                <h3 class="font-display text-lg font-bold mt-5">
                    @if(filled($member['name']))
                        {{ $member['name'] }}
                    @else
                        <span data-i18n="team_member_placeholder">Team member</span> {{ $member['number'] }}
                    @endif
                </h3>
                <p class="text-sm text-gray-400 mt-1">
                    @if(filled($member['identity']))
                        {{ $member['identity'] }}
                    @else
                        <span data-i18n="team_identity_placeholder">Add name, role, and team identity</span>
                    @endif
                </p>
            </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
