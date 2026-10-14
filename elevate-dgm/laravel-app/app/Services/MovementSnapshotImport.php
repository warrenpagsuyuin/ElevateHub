<?php
namespace App\Services;

use App\Models\Checkpoint;
use App\Models\OrgUnit;
use App\Models\UnitSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MovementSnapshotImport
{
    public function run(array $files, ?User $actor, bool $dryRun = true): array
    {
        if (!$dryRun && (!$actor || $actor->normalizedRole() !== 'admin')) throw new \DomainException('A recorded admin actor is required for an import.');
        if (!$files) throw new \DomainException('Supply at least one MAIN, NCP or ICP CSV.');
        $manifest = [];
        foreach ($files as $group => $path) {
            if (!in_array($group,['MAIN','NCP','ICP'],true) || !is_file($path) || !is_readable($path)) throw new \DomainException('Invalid or unreadable CSV for '.$group);
            $manifest[$group] = ['name'=>basename($path),'sha256'=>hash_file('sha256',$path)];
        }
        ksort($manifest);
        $fingerprint = hash('sha256',json_encode([array_map(fn ($file) => $file['sha256'],$manifest),config('movement_analytics')],JSON_THROW_ON_ERROR));
        $prior = DB::table('analytics_imports')->where('fingerprint',$fingerprint)->first();
        if ($prior) return ['unchanged'=>true,'rows'=>0,'snapshots'=>0,'issues'=>json_decode($prior->issues,true)];
        $rows = [];
        foreach ($files as $group => $path) $rows = array_merge($rows,$this->read($path,$group));
        if (!$rows) throw new \DomainException('No data rows found. No records imported.');
        // A dry run executes this same plan without starting a write transaction.
        if ($dryRun) return $this->plan($rows)['summary'];
        return DB::transaction(function () use ($rows,$actor,$fingerprint,$manifest) {
            $prior = DB::table('analytics_imports')->where('fingerprint',$fingerprint)->first();
            if ($prior) return ['unchanged'=>true,'rows'=>0,'snapshots'=>0,'issues'=>json_decode($prior->issues,true)];
            Checkpoint::whereIn('id',array_unique(array_column($rows,'checkpoint_id')))->lockForUpdate()->get();
            $plan = $this->plan($rows);
            $importId = DB::table('analytics_imports')->insertGetId([
                'fingerprint'=>$fingerprint,'files'=>json_encode($manifest,JSON_THROW_ON_ERROR),
                'issues'=>json_encode($plan['summary']['issues'],JSON_THROW_ON_ERROR),'actor_id'=>$actor->id,'created_at'=>now(),
            ]);
            foreach ($rows as $row) DB::table('analytics_import_rows')->insert([
                'import_id'=>$importId,'unit_id'=>$row['unit_id'],'checkpoint_id'=>$row['checkpoint_id'],
                'source_tab'=>$row['source_tab'],'row_number'=>$row['row_number'],
                'payload'=>json_encode($row['payload'],JSON_THROW_ON_ERROR),'created_at'=>now(),
            ]);
            foreach ($plan['snapshots'] as $snapshot) {
                $snapshot['quality_flags'] = array_values(array_filter($plan['summary']['issues'],fn ($issue) => $issue['unit_id']===$snapshot['unit_id'] && $issue['checkpoint_id']===$snapshot['checkpoint_id']));
                UnitSnapshot::updateOrCreate(array_intersect_key($snapshot,array_flip(['unit_id','checkpoint_id','source'])),$snapshot);
            }
            foreach ($plan['summary']['issues'] as $issue) DB::table('analytics_data_issues')->updateOrInsert(
                ['fingerprint'=>hash('sha256',json_encode([$issue['code'],$issue['unit_id'],$issue['checkpoint_id'],$issue['metric'] ?? null]))],
                ['code'=>$issue['code'],'unit_id'=>$issue['unit_id'],'checkpoint_id'=>$issue['checkpoint_id'],
                    'reason'=>$issue['reason'],'details'=>json_encode($issue,JSON_THROW_ON_ERROR),'status'=>'open','created_at'=>now(),'updated_at'=>now()]
            );
            return $plan['summary'];
        });
    }

    private function read(string $path, string $group): array
    {
        $handle = fopen($path,'rb');
        try {
            [$headers,$line] = $this->headers($handle,$group);
            if (count($headers)!==count(array_unique($headers))) throw new \DomainException($group.': duplicate CSV columns.');
            $unitColumn = $this->column($headers,config('movement_analytics.csv.unit_headers'));
            $checkpointColumn = $this->column($headers,config('movement_analytics.csv.checkpoint_headers'));
            if ($unitColumn === null) throw new \DomainException($group.': use a unit or unit_key column; see docs/movement-analytics.md.');
            $checkpoints = Checkpoint::all();
            if ($checkpoints->isEmpty()) throw new \DomainException('Run analytics:setup before importing.');
            $rows = [];
            while (($cells=fgetcsv($handle,0,',','"','')) !== false) {
                $line++;
                if (count(array_filter($cells,fn ($v) => trim((string)$v)!==''))===0) continue;
                try {
                    if (count($cells)!==count($headers)) throw new \DomainException('CSV column count differs from the header.');
                    $unit = $this->unit((string)$cells[$unitColumn],$group);
                    if ($checkpointColumn !== null) {
                        $period = $this->normalize($cells[$checkpointColumn]);
                        $checkpoint = $checkpoints->first(fn ($cp) => in_array($period,[$this->normalize($cp->key),$this->normalize($cp->label),$this->normalize($cp->as_of_date->toDateString())],true));
                        if (!$checkpoint) throw new \DomainException('Unknown checkpoint.');
                        $periods = [[$checkpoint,$this->metricColumns($headers)]];
                    } else {
                        $periods = [];
                        foreach ($checkpoints as $checkpoint) {
                            $columns = $this->metricColumns($headers,[$this->normalize($checkpoint->key),$this->normalize($checkpoint->label)]);
                            if (array_filter($columns,fn ($column) => $column!==null)) $periods[] = [$checkpoint,$columns];
                        }
                    }
                    if (!$periods) throw new \DomainException('No recognized checkpoint metric columns.');
                    foreach ($periods as [$checkpoint,$columns]) {
                        if (count(array_filter($columns,fn ($column) => $column!==null))===0) throw new \DomainException('No recognized metric columns.');
                        $payload = ['metric_states'=>[]];
                        foreach ($columns as $metric => $column) {
                            $raw = $column===null ? '' : trim((string)$cells[$column]);
                            [$value,$state] = $this->value($raw);
                            if ($metric==='campus' && !$unit->has_campus) {
                                if ($value!==null) throw new \DomainException('Campus is not applicable to this unit; a number, including 0, is invalid.');
                                $state='not_applicable';
                            }
                            $payload[$metric]=$value; $payload['metric_states'][$metric]=$state;
                        }
                        $at = $this->column($headers,['reported_at']);
                        $reported = $at===null ? '' : trim($cells[$at]);
                        if ($reported!=='' && !$this->validDate($reported)) throw new \DomainException('reported_at must be a valid YYYY-MM-DD date.');
                        $remarks = $this->column($headers,['remarks']);
                        $payload['reported_at']=$reported ?: null;
                        $payload['remarks']=$remarks===null ? null : (trim($cells[$remarks]) ?: null);
                        $rows[]=['unit_id'=>$unit->id,'checkpoint_id'=>$checkpoint->id,'source_tab'=>$group.' Data','row_number'=>$line,'payload'=>$payload];
                    }
                } catch (\DomainException $error) { throw new \DomainException($group.' Data row '.$line.': '.$error->getMessage()); }
            }
            return $rows;
        } finally { fclose($handle); }
    }

    private function plan(array $rows): array
    {
        $issues=[]; $snapshots=[];
        $units=OrgUnit::all()->keyBy('id'); $checkpoints=Checkpoint::all()->keyBy('id');
        $buckets=collect($rows)->groupBy(fn ($row) => $row['unit_id'].':'.$row['checkpoint_id']);
        foreach ($buckets as $entries) {
            $first=$entries->first(); $unit=$units[$first['unit_id']]; $checkpoint=$checkpoints[$first['checkpoint_id']];
            $existing=UnitSnapshot::where('unit_id',$unit->id)->where('checkpoint_id',$checkpoint->id)->where('source','legacy_import')->first();
            if ($checkpoint->status==='closed' || $existing?->closed_at) throw new \DomainException('Checkpoint/snapshot is closed: '.$unit->key.' / '.$checkpoint->key);
            $payloads=DB::table('analytics_import_rows')->where('unit_id',$unit->id)->where('checkpoint_id',$checkpoint->id)->pluck('payload')->map(fn ($json) => json_decode($json,true))->all();
            $payloads=array_merge($payloads,$entries->pluck('payload')->all());
            $snapshot=['unit_id'=>$unit->id,'checkpoint_id'=>$checkpoint->id,'source'=>'legacy_import','conflict'=>false,'metric_states'=>[],'quality_flags'=>[]];
            foreach (config('movement_analytics.metrics') as $metric) {
                $variants=[];
                foreach ($payloads as $payload) {
                    $pair=[$payload[$metric],$payload['metric_states'][$metric]];
                    $variants[json_encode($pair)]=$pair;
                }
                if (count($variants)>1) {
                    $snapshot[$metric]=null; $snapshot['metric_states'][$metric]='conflict'; $snapshot['conflict']=true;
                    $issues[]=$this->issue('conflict',$unit,$checkpoint,$metric,'Different reported values; originals retained for explicit resolution.',['alternatives'=>array_values($variants)]);
                } else { [$snapshot[$metric],$snapshot['metric_states'][$metric]]=array_values($variants)[0]; }
            }
            foreach (config('movement_analytics.declared_conflicts') as $conflict) {
                if ($conflict['unit']!==$unit->key || $conflict['checkpoint']!==$checkpoint->key) continue;
                foreach ($conflict['metrics'] as $metric) { $snapshot[$metric]=null; $snapshot['metric_states'][$metric]='conflict'; }
                $snapshot['conflict']=true;
                $issues[]=$this->issue('declared_conflict',$unit,$checkpoint,null,$conflict['reason'],['alternatives'=>$conflict['alternatives']]);
            }
            $dates=array_filter(array_column($payloads,'reported_at')); sort($dates);
            $snapshot['reported_at']=$dates ? end($dates) : null;
            $snapshot['remarks']=implode("\n",array_unique(array_filter(array_column($payloads,'remarks')))) ?: null;
            $snapshots[$unit->id.':'.$checkpoint->id]=$snapshot;
        }
        $all=[];
        foreach (UnitSnapshot::whereIn('checkpoint_id',array_unique(array_column($rows,'checkpoint_id')))->where('source','legacy_import')->get() as $row) $all[$row->unit_id.':'.$row->checkpoint_id]=$row->toArray();
        $all=array_replace($all,$snapshots);
        foreach (array_unique(array_column($rows,'checkpoint_id')) as $checkpointId) {
            $checkpoint=$checkpoints[$checkpointId];
            foreach ($units as $parent) {
                $children=$units->where('parent_id',$parent->id);
                if ($children->isEmpty()) continue;
                $parentRow=$all[$parent->id.':'.$checkpointId] ?? null;
                // Compare supplied parent assertions with immediate children; never add both levels.
                if (!$parentRow) continue;
                foreach (config('movement_analytics.metrics') as $metric) {
                    if (($parentRow['metric_states'][$metric] ?? null)!=='reported') continue;
                    $sum=0; $missing=[];
                    foreach ($children as $child) {
                        if ($metric==='campus' && !$child->has_campus) continue;
                        $row=$all[$child->id.':'.$checkpointId] ?? null;
                        if (($row['metric_states'][$metric] ?? null)!=='reported') $missing[]=$child->key;
                        else $sum+=$row[$metric];
                    }
                    if ($missing) $issues[]=$this->issue('incomplete_children',$parent,$checkpoint,$metric,'Cannot reconcile: child values are missing or conflicted.',['children'=>$missing]);
                    elseif ($parentRow[$metric]!==$sum) $issues[]=$this->issue('parent_mismatch',$parent,$checkpoint,$metric,'Reported parent does not equal the sum of its children.',['reported'=>$parentRow[$metric],'children_sum'=>$sum]);
                }
            }
        }
        return ['snapshots'=>$snapshots,'summary'=>['unchanged'=>false,'rows'=>count($rows),'snapshots'=>count($snapshots),'issues'=>$issues]];
    }

    private function issue(string $code, OrgUnit $unit, Checkpoint $checkpoint, ?string $metric, string $reason, array $details=[]): array
    {
        return ['code'=>$code,'unit_id'=>$unit->id,'unit'=>$unit->key,'checkpoint_id'=>$checkpoint->id,'checkpoint'=>$checkpoint->key,'metric'=>$metric,'reason'=>$reason]+$details;
    }

    private function value(string $raw): array
    {
        if ($raw==='') return [null,'not_reported'];
        if ($raw==='-') return [null,'not_applicable'];
        if (!preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)$/D',$raw)) throw new \DomainException('Counts must be nonnegative whole numbers, blank, or "-".');
        $number=(float)str_replace(',','',$raw);
        if ($number>2147483647) throw new \DomainException('Count exceeds the supported integer range.');
        return [(int)$number,'reported'];
    }

    private function unit(string $value, string $group): OrgUnit
    {
        $normal=$this->normalize($value);
        $alias=config('movement_analytics.csv.unit_aliases.'.$normal);
        if (in_array($normal,['total','group_total'],true)) $alias=strtolower($group);
        $matches=OrgUnit::all()->filter(fn ($unit) => ($unit->group===$group || $unit->key==='elevate') &&
            ($unit->key===$alias || $this->normalize($unit->key)===$normal || $this->normalize($unit->name)===$normal));
        if ($matches->count()!==1) throw new \DomainException('Unknown or ambiguous unit. Use its configured unit_key.');
        return $matches->first();
    }

    private function metricColumns(array $headers, array $periods=[]): array
    {
        $columns=[];
        foreach (config('movement_analytics.csv.metric_headers') as $metric=>$aliases) {
            $candidates=$aliases;
            if ($periods) { $candidates=[]; foreach ($periods as $period) foreach ($aliases as $alias) { $candidates[]=$period.'_'.$alias; $candidates[]=$alias.'_'.$period; } }
            $columns[$metric]=$this->column($headers,$candidates);
        }
        return $columns;
    }
    private function headers($handle, string $group): array
    {
        $header=fgetcsv($handle,0,',','"',''); $line=1;
        if (!$header) throw new \DomainException($group.': missing CSV header.');
        $normalize=fn ($row) => array_map(fn ($v) => $this->normalize(ltrim((string)$v,"\xEF\xBB\xBF")),$row);
        $top=$normalize($header);
        // A tab-name banner is unambiguous; other unknown layouts fail validation.
        if (count(array_filter($top,fn ($v) => $v!==''))===1 && in_array(reset($top),[$this->normalize($group.' Data'),$this->normalize($group)],true)) {
            $header=fgetcsv($handle,0,',','"',''); $line++;
            if (!$header) throw new \DomainException($group.': missing header after tab title.');
            $top=$normalize($header);
        }
        $periods=[];
        foreach (config('movement_analytics.checkpoints') as $checkpoint) {
            $periods[]=$this->normalize($checkpoint['key']); $periods[]=$this->normalize($checkpoint['label']);
        }
        $hasMetrics=count(array_filter($this->metricColumns($top),fn ($v) => $v!==null))>0;
        foreach ($periods as $period) $hasMetrics=$hasMetrics || count(array_filter($this->metricColumns($top,[$period]),fn ($v) => $v!==null))>0;
        if ($hasMetrics) return [$top,$line];
        $lower=fgetcsv($handle,0,',','"',''); $line++;
        if (!$lower || count($lower)!==count($top)) throw new \DomainException($group.': unsupported header. Use documented long or checkpoint-column CSV format.');
        $lower=$normalize($lower); $period=null; $combined=[];
        $metricNames=array_merge(...array_values(config('movement_analytics.csv.metric_headers')));
        foreach ($top as $i=>$label) {
            if (in_array($label,$periods,true)) $period=$label;
            elseif ($label!=='') $period=null;
            if ($period!==null && in_array($lower[$i],$metricNames,true)) $combined[]=$period.'_'.$lower[$i];
            else $combined[]=$lower[$i] ?: $label;
        }
        return [$combined,$line];
    }
    private function column(array $headers, array $names): ?int
    {
        $matches=array_keys(array_intersect($headers,$names));
        if (count($matches)>1) throw new \DomainException('Ambiguous equivalent columns; provide one column per field.');
        return $matches[0] ?? null;
    }
    private function normalize(?string $value): string { return trim(preg_replace('/[^a-z0-9]+/','_',strtolower(trim((string)$value))),'_'); }
    private function validDate(string $value): bool
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date && $date->format('Y-m-d')===$value;
    }
}
