(() => {
    const app = document.getElementById('movement-app');
    if (!app) return;
    const $ = (id) => document.getElementById(`mv-${id}`);
    const form = $('filters');
    const names = { dleaders: 'DGroup leaders', dgroups: 'DGroups', dmembers: 'DGroup members', campus: 'Campus', new_leaders: 'New leaders' };
    const colors = ['#96969f', '#686871', '#353539', '#b42318', '#151515'];
    const fmt = new Intl.NumberFormat('en-PH');
    const percent = value => `${Number(value || 0).toFixed(1)}%`;
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const label = (cell) => cell?.state === 'not_applicable' ? 'N/A' : cell?.state === 'conflict' ? 'Conflict' : cell?.value == null ? '—' : fmt.format(cell.value);
    const provenance = c => c ? [({live:'Live',mixed_live:'Mixed live + reported',legacy_import:'Imported report',system:'Closed snapshot',correction:'Audited correction',mixed:'Mixed reports',sample:'SAMPLE DATA',resolved_report:'Resolved report'}[c.source] || (c.state==='not_applicable'?'Not applicable':'No source')), c.as_of && ('as of '+c.as_of), c.coverage && (c.coverage.known+' of '+c.coverage.total+' reporting units'), c.record_coverage && (c.record_coverage.known+' of '+c.record_coverage.total+' records with known fields'), c.quality!=='ok' && c.quality].filter(Boolean).join(' ? ') : 'No data';
    const stateLabel = (state) => ({ reported: 'Reported count', not_reported: 'Not reported', not_applicable: 'Not applicable', conflict: 'Needs resolution' }[state] || 'Not reported');
    const empty = (title, text) => `<div class="mv-empty"><strong>${esc(title)}</strong><span>${esc(text)}</span></div>`;
    const dateLabel = value => value ? new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' }) : 'No reporting evidence yet';
    let data = null, metric = 'dmembers', controller = null, requestId = 0, returnFocus = null, showAllUnits = false, loading = false;
    const stateCard = $('state-card'), stateActions = $('state-actions');
    const setState = (text, kind = 'loading', actions = '', supportId = null) => {
        stateCard.hidden = false;
        $('status').className = `mv-state mv-state-${kind}`;
        $('status').innerHTML = (kind === 'loading' ? '<span class="mv-loader" aria-hidden="true"></span> ' : '') + esc(text) + (supportId ? '<small class="mv-support-id">Support reference: '+esc(supportId)+'</small>' : '');
        stateActions.hidden = !actions;
        stateActions.innerHTML = actions;
        $('retry').hidden = kind !== 'error';
        $('retry').disabled = loading;
        $('retry').textContent = loading ? 'Trying again…' : 'Try again';
    };
    const validPayload = payload => payload && typeof payload === 'object'
        && payload.checkpoint && typeof payload.checkpoint === 'object'
        && payload.completeness && typeof payload.completeness === 'object'
        && payload.units && typeof payload.units === 'object'
        && Array.isArray(payload.issues) && payload.meta && typeof payload.meta === 'object';
    const renderEmpty = () => {
        const checkpoint = data.checkpoint?.label || 'this checkpoint';
        const completeness = data.completeness || {};
        $('completeness').textContent = `Data completeness: ${completeness.reported || 0} / ${completeness.total || 0} units reported (${percent(completeness.percent)}).`;
        $('content').hidden = true;
        setState(`No reported data for ${checkpoint} yet. ${completeness.reported || 0} of ${completeness.total || 0} units have reported. ${completeness.live || 0} units have live member records.`, 'empty',
            '<button class="mv-button" type="button" data-use-live>Switch count source to live records</button><a class="mv-button mv-dark" href="/analytics/checkpoints">Review checkpoint close</a>');
    };
    const params = () => {
        const result = new URLSearchParams(new FormData(form));
        if (!result.get('group')) result.delete('group');
        if(new URLSearchParams(location.search).get('sample')==='1') result.set('sample','1');
        return result;
    };
    const adoptUrl = () => {
        const query = new URLSearchParams(location.search);
        for (const name of ['checkpoint', 'compare', 'group', 'mode']) {
            const field = form.elements.namedItem(name);
            const value = query.get(name);
            if (value !== null && [...field.options].some(o => o.value === value)) field.value = value;
            else field.value = name === 'checkpoint' ? app.dataset.defaultCheckpoint : ({compare:'baseline',group:'',mode:'auto'}[name]);
        }
        metric = Object.hasOwn(names, query.get('metric')) ? query.get('metric') : 'dmembers';
        $('sort').value = query.get('sort') === 'growth' ? 'growth' : 'count';
    };
    const saveUrl = (replace = false) => {
        const query = params(); query.set('metric', metric); query.set('sort', $('sort').value);
        history[replace ? 'replaceState' : 'pushState']({}, '', `${location.pathname}?${query}`);
    };
    const change = value => {
        if (value?.variance == null) return '—';
        const direction = value.variance < 0 ? '↓ Decline' : value.variance > 0 ? '↑ Growth' : '→ Stable';
        const sign = value.variance > 0 ? '+' : '';
        return `${direction} ${sign}${fmt.format(value.variance)} (${value.pct > 0 ? '+' : ''}${Number(value.pct || 0).toFixed(1)}%)`;
    };
    const unitRows = () => Object.values(data.units).filter(u => u.type === 'unit');
    const unitForKey = key => Object.values(data.units).find(unit => unit.key === key);
    const periodRows = () => Object.values(data.periods);
    const chip = status => `<span class="mv-status-chip ${['Declining','Anomaly'].includes(status) ? 'alert' : ''}">${esc(status)}</span>`;
    const openDrawer = (title, body, kicker = 'UNIT DETAIL') => {
        if (!$('drawer').open) returnFocus = document.activeElement;
        $('drawer-title').textContent = title; $('drawer-kicker').textContent = kicker; $('drawer-body').innerHTML = body;
        if (!$('drawer').open) $('drawer').showModal();
        $('close').focus();
    };
    const unitDetail = key => {
        const unit = unitForKey(key); if (!unit) return;
        let html = `<p>${esc(unit.group || 'Movement total')} · ${esc(names[metric])} · ${esc(data.checkpoint.label)}</p><div class="mv-kpi-value">${esc(label(unit.metrics[metric]))}</div>${chip(unit.status)}`;
        html += '<p>'+esc(provenance(unit.metrics[metric]))+'</p><p>'+esc(unit.metrics[metric].basis || '')+'</p><h3>System count vs last reported</h3>';
        const comparison = unit.system_vs_last_reported[metric];
        html += `<div class="mv-detail-row"><span>Last reported ${esc(comparison.checkpoint || '')}</span><strong>${esc(label(comparison.reported))}</strong></div><div class="mv-detail-row"><span>System difference</span><strong>${comparison.difference == null ? '—' : esc(`${comparison.difference > 0 ? '+' : ''}${fmt.format(comparison.difference)}`)}</strong></div>`;
        html += '<h3>Checkpoint history</h3>' + periodRows().map(p => `<div class="mv-detail-row"><span>${esc(p.checkpoint.label)} · ${esc(p.checkpoint.status)}</span><strong>${esc(label(p.units[unit.id][metric]))}</strong></div>`).join('');
        html += '<h3>Reported evidence</h3><p>Reported parent assertions are retained separately. They do not fill gaps in child-unit evidence.</p>';
        html += periodRows().map(p => `<div class="mv-detail-row"><span>${esc(p.checkpoint.label)}</span><strong>${esc(label(p.reported[unit.id][metric]))}</strong></div>`).join('');
        html += `<h3>Satellites</h3><p>${unit.satellites.length ? unit.satellites.map(esc).join(' · ') : 'No satellites mapped to this unit.'}</p>`;
        const children = Object.values(data.units).filter(child => child.parent_id === unit.id);
        if (children.length) html += `<h3>Sub-units</h3>${children.map(child => `<button type="button" data-unit="${esc(child.key)}">${esc(child.name)}</button>`).join('')}`;
        html += `<h3>Remarks</h3>${unit.remarks.length ? unit.remarks.map(r => `<div class="mv-mini-unit"><strong>${esc(r.checkpoint)}</strong><p>${esc(r.text)}</p></div>`).join('') : '<p>No reported remarks.</p>'}`;
        html += `<h3>Change log</h3>${unit.change_log.length ? unit.change_log.map(r => `<div class="mv-mini-unit"><strong>${esc(dateLabel(r.at))}</strong>${esc(r.text)}</div>`).join('') : '<p>No imported changes recorded.</p>'}`;
        const issues = data.issues.filter(issue => issue.unit === key);
        if (issues.length) html += '<h3>Attention items</h3>' + issues.map(issue => `<div class="mv-mini-unit"><strong>${esc(issue.checkpoint)}</strong>${esc(issue.reason)}${issue.details?.alternatives || issue.alternatives ? `<p>Conflicting source alternatives: ${esc(JSON.stringify(issue.details?.alternatives || issue.alternatives))}</p>` : ''}</div>`).join('');
        openDrawer(unit.name, html);
    };
    const renderKPIs = () => {
        const root=Object.values(data.units).find(u=>data.group?u.type==='group':u.type==='organization');
        const rootMetrics=data.metrics || root?.metrics, c=data.completeness;
        if (!rootMetrics) throw new Error('Analytics response is incomplete.');
        $('verdict-kicker').textContent='MOVEMENT OVERVIEW';
        $('verdict-title').textContent='Showing live member counts for '+c.live+' units, reported counts for '+c.reported+' units. '+c.missing+' units have no data.';
        $('verdict-copy').textContent='Available evidence, with source and coverage. Live records are separate from historical checkpoints.';
        $('completeness').textContent='Data completeness: '+c.reported+' / '+c.total+' units reported ('+percent(c.percent)+'). '+c.live+' additional units have live member records.';
        $('kpis').innerHTML=['dleaders','dgroups','dmembers','campus'].map(key=>{
            const cell=rootMetrics[key];
            return '<article class="mv-kpi"><div class="mv-kpi-label">'+names[key]+'</div><div class="mv-kpi-value">'+esc(label(cell))+'</div>'+(cell.quality==='partial'?'<span class="mv-chip">Partial</span>':'')+'<p class="mv-note" title="Missing: '+esc(cell.coverage.missing.join(', '))+'">'+esc(provenance(cell))+'</p>'+(cell.value===null&&cell.state!=='not_applicable'?'<p class="mv-note">'+(key==='campus'?'Needs leader-reported campus totals.':'Needs reported counts or current registrations.')+' <a href="#mv-attention">Review evidence</a></p>':'')+['baseline','previous'].map(k=>'<div class="mv-change"><span>vs '+k+'</span><strong>'+esc(change(cell.changes[k]))+'</strong></div><p class="mv-note">Compared on '+cell.changes[k].compared+' of '+cell.changes[k].total+' units</p>').join('')+'</article>';
        }).join('');
    };
    const renderGroups = () => {
        $('metrics').innerHTML=Object.entries(names).map(([key,name])=>'<button type="button" data-metric="'+key+'" aria-pressed="'+(key===metric)+'">'+name+'</button>').join('');
        const groups=Object.values(data.units).filter(u=>u.type==='group');
        const periods=data.mode==='live'?[]:[...periodRows()];
        if(data.mode!=='reported') periods.push({checkpoint:{label:'Live ? '+data.live_as_of},units:data.live});
        const max=Math.max(1,...groups.flatMap(u=>periods.map(p=>p.units[u.id][metric].value||0)));
        $('group-chart').innerHTML='<div class="mv-chart-groups">'+groups.map(u=>'<div class="mv-chart-group"><div class="mv-chart-bars">'+periods.map((p,i)=>{
            const c=p.units[u.id][metric], text=u.name+' ? '+p.checkpoint.label+': '+label(c)+' ? '+provenance(c)+' ? Missing: '+c.coverage.missing.join(', ');
            return '<div class="mv-chart-column" tabindex="0" role="img" aria-label="'+esc(text)+'" title="'+esc(text)+'"><b>'+esc(label(c))+'</b>'+(c.value!==null?'<span class="mv-chart-bar" style="height:'+Math.max(0,c.value/max*140)+'px;background:'+colors[i%colors.length]+'"></span>':'<span class="mv-no-data">'+(c.state==='not_applicable'?'N/A':'No data')+'</span>')+'</div>';
        }).join('')+'</div><h3>'+esc(u.name==='ICP'?'Ismael (ICP)':u.name)+'</h3></div>').join('')+'</div><div class="mv-legend">'+periods.map((p,i)=>'<span><i class="mv-swatch" style="background:'+colors[i%colors.length]+'"></i>'+esc(p.checkpoint.label)+'</span>').join('')+'<span>Not reported: absent bar. Live is separate from checkpoint history.</span></div>';
    };
    const sparkline = unit => {
        const cells = periodRows().map(p => p.units[unit.id][metric]);
        const known = cells.filter(c => c.state === 'reported' && c.value !== null);
        const title = periodRows().map((p,i) => `${p.checkpoint.label}: ${label(cells[i])}`).join('; ');
        if (!known.length) return `<svg class="mv-sparkline" role="img" aria-label="${esc(title)}" viewBox="0 0 125 30"><path d="M2 16H123" stroke="#d6d6dd" stroke-dasharray="3 4"/></svg>`;
        const max = Math.max(1,...known.map(c => c.value)), min = Math.min(0,...known.map(c => c.value));
        let last = null, paths = '', dots = '';
        cells.forEach((cell,i) => {
            if (cell.state !== 'reported') { last=null; return; }
            const x=4+i*117/Math.max(1,cells.length-1), y=26-(cell.value-min)/Math.max(1,max-min)*22;
            if (last) paths+=`<path d="M${last[0]} ${last[1]}L${x} ${y}" stroke="#b42318" stroke-width="1.6"/>`;
            dots+=`<circle cx="${x}" cy="${y}" r="2.5" fill="#b42318"/>`; last=[x,y];
        });
        return `<svg class="mv-sparkline" role="img" aria-label="${esc(title)}" viewBox="0 0 125 30">${paths}${dots}</svg>`;
    };
    const renderRanking = () => {
        $('ranked-metric').textContent = names[metric];
        const score = unit => $('sort').value === 'growth' ? unit.metrics[metric].changes[data.compare].pct : unit.metrics[metric].value;
        const units=unitRows().sort((a,b) => (score(b) ?? -Infinity)-(score(a) ?? -Infinity) || a.name.localeCompare(b.name));
        const max=Math.max(1,...units.map(u => u.metrics[metric].value || 0));
        $('more-units').hidden=units.length<=8; $('more-units').textContent=showAllUnits ? 'Show fewer units' : 'Show all '+units.length+' units'; $('more-units').setAttribute('aria-expanded', String(showAllUnits));
        $('ranking').innerHTML=(showAllUnits ? units : units.slice(0,8)).map(unit => `<button class="mv-rank-row" type="button" data-unit="${esc(unit.key)}" aria-label="Explore ${esc(unit.name)}, ${names[metric]} ${esc(label(unit.metrics[metric]))}"><span class="mv-rank-main"><span><strong>${esc(unit.name)}</strong><small>${esc(unit.group)}${$('sort').value === 'growth' ? ` · ${score(unit) == null ? 'No comparison' : `${score(unit)>0?'+':''}${score(unit).toFixed(1)}%`}` : ''}</small></span><span class="mv-track">${unit.metrics[metric].state === 'reported' ? `<span style="width:${unit.metrics[metric].value/max*100}%"></span>` : ''}</span><span class="mv-rank-value">${esc(label(unit.metrics[metric]))}</span></span>${sparkline(unit)}${chip(unit.status)}</button>`).join('') || empty('No units configured', 'Organizational setup is required before ranking units.');
    };
    const annotateRanking = () => $('ranking').querySelectorAll('[data-unit]').forEach(button=>{const c=unitForKey(button.dataset.unit)?.metrics?.[metric];if(!c)return;const note=document.createElement('small');note.className='mv-note';note.textContent=provenance(c);button.title=provenance(c)+'; Missing: '+(c.coverage?.missing || []).join(', ');button.setAttribute('aria-label',button.getAttribute('aria-label')+'; '+button.title);button.append(note);});
    const conflictForm = conflict => {
        const choices=conflict.alternatives.map((a,i)=>'<label><input type="radio" name="alternative" value="'+i+'" required> Option '+(i+1)+': '+Object.entries(a).map(([k,v])=>names[k]+': '+v).join(' ? ')+'</label>').join('<br>');
        if(conflict.resolution) return '<p>Resolved by actor #'+esc(conflict.resolution.actor_id)+' at '+esc(conflict.resolution.created_at)+'. '+esc(conflict.resolution.chosen_values)+'</p>';
        return '<details><summary>Resolve '+esc(conflict.unit)+' ? '+esc(conflict.checkpoint)+'</summary><p>Review both source alternatives. No option is preselected.</p>'+(conflict.closed?'<p>Checkpoint is closed. Use Review checkpoint close to record an audited correction.</p>':'<form class="mv-resolution" data-code="'+esc(conflict.code)+'">'+choices+'<label>Reason<input name="reason" required minlength="10" maxlength="2000"></label><label>Source evidence reference<input name="evidence_reference" required minlength="10" maxlength="2000"></label><button class="mv-button" type="submit">Record resolution</button><p role="status"></p></form>')+'</details>';
    };
    app.addEventListener('submit',async event=>{
        const form=event.target.closest('.mv-resolution');if(!form)return;event.preventDefault();
        const body=Object.fromEntries(new FormData(form));body.code=form.dataset.code;const message=form.querySelector('[role=status]');
        form.querySelector('button').disabled=true;
        try {const response=await fetch(app.dataset.resolutionUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':app.dataset.csrf},body:JSON.stringify(body)});const result=await response.json();if(!response.ok)throw Error(result.errors?Object.values(result.errors).flat().join(' '):result.message);await load();}catch(error){message.textContent=error.message;}finally{form.querySelector('button').disabled=false;}
    });
    const renderIssues = () => {
        const priority = issue => ['conflict','declared_conflict','parent_mismatch'].includes(issue.code) ? 0 : issue.code === 'large_change' ? 1 : 2;
        const issues = [...data.issues].sort((a,b) => priority(a)-priority(b));
        $('issue-count').textContent = `${issues.length} items`;
        const html=issue => `<article class="mv-issue"><span class="mv-issue-mark" aria-hidden="true">!</span><div><strong>${esc(unitForKey(issue.unit)?.name || issue.satellite || 'Movement records')}${issue.checkpoint !== data.checkpoint.key ? ` · ${esc(issue.checkpoint)}` : ''}</strong><p>${esc(issue.reason)}</p>${issue.unit && unitForKey(issue.unit) ? `<button type="button" data-unit="${esc(issue.unit)}">Review unit →</button>` : `<a href="${esc(issue.code === 'unmapped_satellite' ? app.dataset.satellitesUrl : app.dataset.membersUrl)}">Review records →</a>`}</div></article>`;
        $('issues').innerHTML=['conflicts','anomalies','unmapped satellites','missing data'].map(type=>{
            const list=issues.filter(i=>i.type===type); if(!list.length)return '';
            return '<details '+(type==='conflicts'||type==='anomalies'?'open':'')+'><summary>'+esc(type)+' <span class="mv-chip">'+list.length+'</span></summary>'+list.slice(0,5).map(html).join('')+(list.length>5?'<details><summary>Show all '+list.length+'</summary>'+list.slice(5).map(html).join('')+'</details>':'')+'</details>';
        }).join('') || empty('No attention items','No issues found in available evidence.');
        $('issues').innerHTML+=(data.conflicts||[]).filter(c=>!data.group||unitForKey(c.unit)).map(conflictForm).join('');
        $('all-issues').hidden=true; $('more-issues').innerHTML='';
    };
    const renderActivity = () => {
        for(const id of ['pipeline','engagement']) $(id).closest('section').hidden=!!data.sample;
        if(data.sample) return;
        const { pipeline:p, engagement:e, sentiment:s }=data.activity;
        $('current-scope').textContent=data.activity.scope;
        $('pipeline').innerHTML=`<div class="mv-pipeline-numbers"><div><strong>${fmt.format(p.total)}</strong><span>Total seekers</span></div><div><strong>${fmt.format(p.placed)}</strong><span>Joined as members</span></div><div><strong>${fmt.format(p.non_placed)}</strong><span>Not yet joined</span></div></div><div class="mv-meter"><span style="width:${p.rate || 0}%;background:#b42318"></span></div><div class="mv-split-row"><span>Joined-as-member rate</span><strong>${p.rate == null ? '—' : `${p.rate.toFixed(1)}%`}</strong></div><p class="mv-note">${fmt.format(p.active)} active ? ${fmt.format(p.archived)} archived</p><p class="mv-note">Placed means a seeker joined as a member. Includes archived seekers.${p.unknown ? ` ${p.unknown} conversion links need verification.` : ''}</p>`;
        $('pipeline-details').innerHTML=Object.values(p.by_unit).map(u => `<div class="mv-mini-unit"><strong>${esc(u.name)}</strong>${u.placed} joined / ${u.total} seekers${u.unknown ? ` · ${u.unknown} unknown` : ''}</div>`).join('') + '<h3>Dated joining trend</h3><p class="mv-note">First recorded conversions in each interval; historical totals are not inferred from current status.</p>'+p.trend.map(point => `<div class="mv-split-row"><span>${esc(point.label)}</span><strong>${point.value == null ? 'Not reported' : fmt.format(point.value)}</strong></div>`).join('');
        const entries=Object.entries(e.classes);
        $('engagement').innerHTML=e.total ? `<div class="mv-meter">${entries.map(([name,count],i) => `<span title="${esc(name)}: ${count}" style="width:${count/e.total*100}%;background:${colors[i%colors.length]}"></span>`).join('')}</div>${entries.map(([name,count],i) => `<div class="mv-split-row"><span><i class="mv-swatch" style="background:${colors[i%colors.length]}"></i>${esc(name)}</span><strong>${fmt.format(count)} <span class="mv-note">/ ${fmt.format(e.total)}</span></strong></div>`).join('')}` : empty('No member records', 'Engagement counts will appear when member records are available.');
        $('engagement-details').innerHTML=Object.values(e.by_unit).map(u => `<div class="mv-mini-unit"><strong>${esc(u.name)}</strong>${Object.entries(u.classes).map(([name,count]) => `${esc(name)}: ${count}`).join(' · ')}</div>`).join('');
        $('sentiment').hidden=!Object.values(s).some(Boolean);
        $('sentiment').innerHTML=`<h3 style="margin-top:22px">Follow-up sentiment</h3><p class="mv-note">Historical member/seeker follow-up sentiment includes archived satellites. Records remain linked; these are not unique people.</p>${Object.entries(s).map(([name,count]) => `<div class="mv-split-row"><span>${esc(name)}</span><strong>${fmt.format(count)}</strong></div>`).join('')}`;
    };
    const renderForecast = () => {
        const available=unitRows().filter(u => data.forecasts.units[u.key][metric].available);
        $('forecasts').innerHTML=available.length ? `<div class="mv-forecast-grid">${available.map(unit => { const f=data.forecasts.units[unit.key][metric]; return `<article class="mv-forecast-card"><h3>${esc(unit.name)}</h3><strong>${fmt.format(f.low)}–${fmt.format(f.high)}</strong><p>${esc(names[metric])} · ${esc(f.next)} · ${f.points} checkpoints</p></article>`; }).join('')}</div>` : empty('More history, better perspective', `No ${names[metric]} forecast is available yet. Units need at least three reported checkpoints, a measured current checkpoint and a configured next checkpoint.`);
    };
    const forecastProgress = () => {
        const list=unitRows().filter(u=>!data.forecasts.units[u.key][metric].available).sort((a,b)=>data.forecasts.units[b.key][metric].points-data.forecasts.units[a.key][metric].points).slice(0,5);
        $('forecasts').innerHTML+=list.map(u=>{const f=data.forecasts.units[u.key][metric];return '<div class="mv-mini-unit"><strong>'+esc(u.name)+'</strong> '+f.points+' reported checkpoints ? '+(f.points<3?'Needs '+(3-f.points)+' more reported checkpoints':esc(f.reason))+' <button type="button" data-unit="'+esc(u.key)+'">Review history</button></div>';}).join('');
    };
    const renderPivot = () => {
        if (!$('full-data').open || !data) return;
        $('pivot').innerHTML=`<caption class="mv-note">Checkpoint counts and explicit data states. Blank CSV values are displayed as a dash here.</caption><thead><tr>${data.pivot.columns.map(c => `<th scope="col">${esc(c)}</th>`).join('')}</tr></thead><tbody>${data.pivot.rows.map(row => `<tr>${row.map(v => `<td>${v == null ? '—' : esc(v)}</td>`).join('')}</tr>`).join('')}</tbody>`;
    };
    const render = () => {
        const sourceCopy = { live_fallback: 'Showing live records, no reports submitted yet.', mixed: 'Showing reported data with live records where reports are missing.', live: 'Showing live records.', reported: 'Showing reported checkpoint data.', none: 'No reported or live records are available.' }[data.meta?.display_source] || 'Showing available records.';
        $('context').textContent=`${data.checkpoint.label} · ${data.group || 'All groups'} · ${sourceCopy}`;
        if (data.revision) $('context').textContent += ' · revision '+data.revision;
        $('updated').textContent=`Data last updated: ${dateLabel(data.data_last_updated)} (Manila time)`;
        $('unit-scope').textContent=`${data.completeness.total || 0} units (DGroups) across ${app.dataset.activeSatellites || 0} satellites`;
        renderKPIs(); renderGroups(); renderRanking(); annotateRanking(); renderIssues(); renderActivity(); renderForecast(); forecastProgress(); renderPivot();
        const query=params(); query.set('format','csv'); $('export').href=`${app.dataset.endpoint}?${query}`; $('export').removeAttribute('aria-disabled');
        $('sample-banner').hidden=!data.sample;
        document.getElementById('qc-workflow').hidden=!!data.sample;
        if(data.sample){$('export').removeAttribute('href');$('export').setAttribute('aria-disabled','true');}
    };
    const load = async () => {
        if (loading) return;
        loading = true;
        const id=++requestId;
        controller?.abort(); controller=new AbortController();
        $('content').hidden=true; $('export').setAttribute('aria-disabled','true'); $('export').removeAttribute('href'); app.setAttribute('aria-busy','true');
        if (!form.elements.checkpoint.value) { setState('Movement checkpoints have not been configured yet. Complete analytics setup to begin.', 'empty'); app.removeAttribute('aria-busy'); loading=false; return; }
        setState('Loading movement analytics…');
        const activeController=controller;
        let timedOut=false;
        const timeout=setTimeout(()=>{ timedOut=true; activeController.abort(); },20000);
        try {
            const response=await fetch(`${app.dataset.endpoint}?${params()}`, {headers:{Accept:'application/json'},signal:controller.signal});
            const payload=await response.json().catch(() => null);
            if (!response.ok) { const error = new Error('request failed'); error.supportId = payload?.meta?.request_id; throw error; }
            if (!validPayload(payload)) throw new Error('request failed');
            if (id !== requestId) return;
            data=payload;
            if ((data.completeness.reported || 0) === 0 && (data.mode === 'reported' || (data.completeness.live || 0) === 0)) renderEmpty();
            else { render(); stateCard.hidden=true; $('content').hidden=false; }
        } catch (error) {
            if (id !== requestId || (error.name === 'AbortError' && !timedOut)) return;
            setState("We couldn't load the analytics. Please try again.", 'error', '', error.supportId || null);
        } finally { clearTimeout(timeout); if (id === requestId) { app.removeAttribute('aria-busy'); loading=false; $('retry').disabled=false; $('retry').textContent='Try again'; } }
    };
    form.addEventListener('submit', event => { event.preventDefault(); saveUrl(); load(); });
    $('retry').addEventListener('click', load);
    $('more-units').addEventListener('click', () => { showAllUnits=!showAllUnits; renderRanking(); annotateRanking(); });
    $('sort').addEventListener('change', () => { saveUrl(); renderRanking(); annotateRanking(); });
    app.addEventListener('click', event => {
        if (event.target.closest('[data-use-live]')) { form.elements.mode.value='live'; saveUrl(); load(); return; }
        const tab=event.target.closest('[data-metric]');
        if (tab) { metric=tab.dataset.metric; saveUrl(); renderGroups(); renderRanking(); annotateRanking(); renderForecast(); forecastProgress(); $('metrics').querySelector(`[data-metric="${metric}"]`).focus(); }
        const unit=event.target.closest('[data-unit]'); if (unit) unitDetail(unit.dataset.unit);
    });
    $('full-data').addEventListener('toggle', renderPivot);
    $('close').addEventListener('click', () => $('drawer').close());
    $('drawer').addEventListener('close', () => { if (returnFocus?.isConnected) returnFocus.focus(); });
    $('method').addEventListener('click', () => {
        let html=`<h3>Sources and coverage</h3><p>Auto uses a closed snapshot, then an imported report, then current records for each metric. Live membership uses current Active status, includes leaders, and is not proof of historical activity. Partial totals sum available units. Changes compare only units with reported evidence in both periods. Live values never become historical checkpoint observations.</p><h3>Indicative forecasts</h3><p>${esc(data.forecasts.method)}</p><h3>June backtest</h3><p>${esc(data.forecasts.backtest_method)}</p>`;
        const tested=unitRows().filter(u => data.forecasts.units[u.key][metric].backtest);
        html+=tested.length ? tested.map(unit => { const b=data.forecasts.units[unit.key][metric].backtest; return `<div class="mv-mini-unit"><strong>${esc(unit.name)} · ${esc(names[metric])}</strong>Absolute error: ${fmt.format(b.absolute_error)} · No-change error: ${fmt.format(b.no_change_error)}<p>Predicted ${fmt.format(b.predicted)} · Actual ${fmt.format(b.actual)}</p></div>`; }).join('') : '<p class="mv-note">Backtesting awaits measured December, March and June counts. No accuracy result is claimed.</p>';
        html+='<h3>Reading the numbers</h3><p>Null means not reported, never zero. N/A means not applicable. Conflicting reports need resolution. A zero baseline has no percentage comparison. Growing and Declining use member change; Stable means exactly 0%. Anomaly flags a conflict, large change or an unusual members-per-group ratio.</p>';
        openDrawer('How this works',html,'METHOD & LIMITS');
    });
    window.addEventListener('movement-checkpoint-changed', load);
    window.addEventListener('popstate', () => { if ($('drawer').open) $('drawer').close(); adoptUrl(); load(); });
    adoptUrl(); load();
})();
