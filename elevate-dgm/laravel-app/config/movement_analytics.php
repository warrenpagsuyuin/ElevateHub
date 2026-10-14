<?php

// Structure and policy only. Reported counts must come from attributed imports or system records.
$groups = [
    'MAIN' => [
        'the-hub'=>'The Hub', 'ubelt'=>'Ubelt', 'anonas'=>'Anonas', 'pasig'=>'Pasig',
        'main-ortigas'=>'Main Ortigas', 'bay-area'=>'Bay Area', 'north-edsa'=>'North Edsa', 'eastwood'=>'Eastwood',
    ],
    'NCP' => [
        'mindanao'=>'Mindanao', 'south-luzon'=>'South Luzon', 'metro-east'=>'Metro East',
        'metro-north'=>'Metro North', 'central-luzon'=>'Central Luzon', 'metro-south'=>'Metro South',
        'metro-west'=>'Metro West', 'visayas'=>'Visayas', 'north-west-luzon'=>'North West Luzon', 'north-east-luzon'=>'North East Luzon',
    ],
    'ICP' => ['middle-east'=>'Middle East', 'europe'=>'Europe', 'oceania'=>'Oceania', 'north-america'=>'North America', 'asia'=>'Asia'],
];
$units = [['key'=>'elevate', 'parent'=>null, 'type'=>'organization', 'name'=>'Elevate', 'group'=>null, 'has_campus'=>true]];
foreach ($groups as $group => $names) {
    $parent = strtolower($group);
    $units[] = ['key'=>$parent, 'parent'=>'elevate', 'type'=>'group', 'name'=>$group, 'group'=>$group, 'has_campus'=>$group !== 'ICP'];
    foreach ($names as $key => $name) {
        $units[] = ['key'=>$parent.'.'.$key, 'parent'=>$parent, 'type'=>'unit', 'name'=>$name, 'group'=>$group, 'has_campus'=>$group !== 'ICP'];
    }
}
foreach (['mindanao'=>['n'=>'North','ne'=>'North East','nw'=>'North West','s'=>'South'], 'metro-east'=>['1'=>'1','2'=>'2'], 'metro-north'=>['1'=>'1','2'=>'2'], 'central-luzon'=>['hub-a'=>'Hub A','hub-b'=>'Hub B']] as $parent => $children) {
    foreach ($children as $key => $name) {
        $units[] = ['key'=>'ncp.'.$parent.'.'.$key, 'parent'=>'ncp.'.$parent, 'type'=>'sub_unit', 'name'=>$groups['NCP'][$parent].' '.$name, 'group'=>'NCP', 'has_campus'=>true];
    }
}

return [
    'sample_enabled' => env('MOVEMENT_ANALYTICS_SAMPLE', false),
    'cache' => ['seconds' => 60],
    'units'=>$units,
    'satellite_mappings'=>[
        'pasig'=>'main.pasig', 'anonas'=>'main.anonas', 'ubelt'=>'main.ubelt',
        'the-hub'=>'main.the-hub', 'main-ortigas'=>'main.main-ortigas', 'eastwood'=>'main.eastwood',
    ],
    'checkpoints'=>[
        ['key'=>'2025-12','label'=>'Dec 2025','as_of_date'=>'2025-12-31','kind'=>'baseline','status'=>'open'],
        ['key'=>'2026-03','label'=>'Mar 2026','as_of_date'=>'2026-03-31','kind'=>'quarter','status'=>'open'],
        ['key'=>'2026-06','label'=>'Jun 2026','as_of_date'=>'2026-06-30','kind'=>'quarter','status'=>'open'],
        ['key'=>'2026-09','label'=>'Sep 2026','as_of_date'=>'2026-09-30','kind'=>'quarter','status'=>'open'],
        ['key'=>'2026-12','label'=>'Dec 2026','as_of_date'=>'2026-12-31','kind'=>'quarter','status'=>'open'],
    ],
    'metrics'=>['dleaders','dgroups','dmembers','campus','new_leaders'],
    'active_definition'=>[
        'source'=>'member_status_history', 'active_value'=>'Active',
        'allowed_statuses'=>['Active','Inactive','New'], 'editor_role'=>'leader',
        'unverified_legacy_status'=>'not_reported',
    ],
    // Confirmed definitions; no historical memberships are inferred from current role strings.
    'definitions'=>[
        'dmembers_include_leaders'=>true, 'campus_basis'=>'leader_reported_totals', 'placement_basis'=>'joined_as_member',
        'new_leader_basis'=>'first_qualification', 'new_leader_minimum_members'=>3, 'new_leaders_include_reactivations'=>false,
    ],
    'status_metric'=>'dmembers',
    'close'=>[
        'required_metrics'=>['dleaders','dgroups','dmembers','campus'],
        'blocking_issue_codes'=>['conflict','declared_conflict','parent_mismatch','overlapping_assignments'],
    ],
    'thresholds'=>['stable_change_pct'=>0, 'anomaly_change_pct'=>40, 'members_per_group_min'=>2, 'members_per_group_max'=>8],
    'csv'=>[
        'unit_headers'=>['unit','unit_key','satellite','satellite_region','district_unit'],
        'checkpoint_headers'=>['checkpoint','period'],
        'metric_headers'=>[
            'dleaders'=>['dleaders','dl','d_leaders'], 'dgroups'=>['dgroups','dg','d_groups'],
            'dmembers'=>['dmembers','dm','d_members'], 'campus'=>['campus','campuses'], 'new_leaders'=>['new_leaders'],
        ],
        'unit_aliases'=>['u_belt'=>'main.ubelt','elevate_main'=>'main','elevate_ncp'=>'ncp','elevate_icp'=>'icp'],
    ],
    'declared_conflicts'=>[
        ['unit'=>'ncp.visayas', 'checkpoint'=>'2025-12', 'code'=>'visayas-baseline-disagreement',
            'metrics'=>['dleaders','dgroups','dmembers'],
            'alternatives'=>[['dleaders'=>68,'dgroups'=>68,'dmembers'=>358], ['dleaders'=>106,'dgroups'=>106,'dmembers'=>472]],
            'reason'=>'Conflicting Dec 2025 Visayas figures reported across sheet tabs. Both alternatives were supplied by the project owner; source CSV verification and explicit resolution are required.'],
    ],
];
