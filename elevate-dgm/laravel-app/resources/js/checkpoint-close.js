(() => {
    const root=document.getElementById('qc-workflow'); if (!root) return;
    const $=id=>document.getElementById(`qc-${id}`);
    const names={dleaders:'DLeaders',dgroups:'DGroups',dmembers:'DMembers',campus:'Campus',new_leaders:'New Leaders'};
    const esc=v=>String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const value=c=>c.state==='not_applicable' ? 'N/A' : c.value ?? (c.state==='conflict' ? 'Conflict' : 'Not reported');
    let review=null, checkpoint=null, opener=null, busy=false, generation=0;
    const send=async(action,payload=null)=>{
        const response=await fetch(`${root.dataset.base.replace(/\/$/,'')}/${encodeURIComponent(checkpoint)}/${action}${payload ? '' : '?basis='+encodeURIComponent($('basis').value)}`,{
            method:payload?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:payload?JSON.stringify(payload):undefined
        });
        const data=await response.json();
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Checkpoint request failed.');
        return data;
    };
    const correctionValues=()=>{
        const unit=review?.units[$('correction-unit').value];
        $('correction-values').innerHTML=unit ? Object.entries(names).map(([key,name])=>`<label>${name}<input name="${key}" type="number" min="0" max="2147483647" step="1" ${unit.metrics[key].state==='not_applicable' ? 'disabled placeholder="N/A"' : key!=='new_leaders'?'required':''} value="${esc(unit.metrics[key].value ?? '')}"></label>`).join('') : '';
    };
    const render=()=>{
        $('title').textContent=`${review.checkpoint.label} · ${review.checkpoint.status}`;
        const units=Object.entries(review.units), closed=review.checkpoint.status==='closed';
        $('summary').innerHTML=`<h3>${esc(review.checkpoint.key)}${closed ? ` · revision ${review.revision}` : ''}</h3><p>${units.filter(([,u])=>u.confirmed).length} / ${units.length} units confirmed.${closed ? ` Closed by actor #${esc(review.closed_by ?? 'unknown')} at ${esc(review.closed_at ?? 'unrecorded')}.` : review.may_finalize ? ' Administrators finalize the movement.' : ' Your confirmations cover only your assigned units.'}</p>`;
        $('blockers').innerHTML=review.blockers.length ? review.blockers.map(b=>`<p class="mv-mini-unit">${esc(b.reason)}</p>`).join('') : '<p>No blocking issues in this review.</p>';
        $('issues').innerHTML=review.issues.length ? review.issues.map(i=>`<p class="mv-mini-unit"><strong>${esc(i.unit || 'Movement')}</strong> ${esc(i.reason)}</p>`).join('') : '<p>No data-quality issues identified.</p>';
        $('counts').innerHTML=`<thead><tr><th>Unit</th>${Object.values(names).map(n=>`<th>${n}</th>`).join('')}</tr></thead><tbody>${units.map(([,u])=>`<tr><td>${esc(u.name)}</td>${Object.keys(names).map(m=>`<td>${esc(value(u.metrics[m]))}</td>`).join('')}</tr>`).join('')}</tbody>`;
        const options=units.map(([key,u])=>`<option value="${esc(key)}">${esc(u.name)}${u.confirmed ? ' · confirmed' : ''}</option>`).join('');
        const selected=$('unit').value, correctionUnit=$('correction-unit').value;
        $('unit').innerHTML=options; if (review.units[selected]) $('unit').value=selected;
        $('correction-unit').innerHTML=options; if (review.units[correctionUnit]) $('correction-unit').value=correctionUnit;
        $('attest').hidden=closed || !units.length || review.may_finalize;
        $('finalize').hidden=closed || !review.may_finalize;
        $('finalize-submit').disabled=!review.ready;
        $('correction').hidden=!closed || !review.may_finalize;
        $('confirm').placeholder=review.checkpoint.key;
        $('attest-submit').disabled=!review.units[$('unit').value] || review.units[$('unit').value].missing.length>0;
        correctionValues(); $('results').hidden=false;
    };
    const load=async()=>{
        const id=++generation; $('results').hidden=true; $('message').textContent='Checking as-of evidence and reconciliation…';
        try { const data=await send('review'); if (id!==generation) return; review=data; render(); $('message').textContent=review.ready?'Review complete. Confirm the evidence before closing.':'Review complete. Unresolved evidence remains visible below.'; }
        catch(e) { if (id===generation) $('message').textContent=e.message; }
    };
    const mutate=async(action,payload)=>{
        if (busy) return;
        busy=true; $('dialog').setAttribute('aria-busy','true'); $('message').textContent='Saving the reviewed evidence...'; root.querySelectorAll('button,input,select,textarea').forEach(b=>b.disabled=true);
        try {
            await send(action,payload); await load();
            $('message').textContent=action==='close'?'Checkpoint closed. Original snapshots are now immutable.':action==='attest'?'Unit evidence confirmed.':'Correction recorded as a new audited version.';
            window.dispatchEvent(new Event('movement-checkpoint-changed'));
        } catch(e) { const message=e.message; await load(); $('message').textContent=message; }
        finally { busy=false; $('dialog').removeAttribute('aria-busy'); root.querySelectorAll('button,input,select,textarea').forEach(b=>b.disabled=false); if (review) render(); }
    };
    $('open').addEventListener('click',()=>{
        checkpoint=document.getElementById('mv-checkpoint')?.value || $('checkpoint')?.value;
        if (!checkpoint) return;
        opener=document.activeElement; $('dialog').showModal(); $('dismiss').focus(); load();
    });
    $('dismiss').addEventListener('click',()=>{ if (!busy) $('dialog').close(); });
    $('dialog').addEventListener('cancel',e=>{ if (busy) e.preventDefault(); });
    $('dialog').addEventListener('close',()=>{ generation++; opener?.focus(); });
    $('refresh').addEventListener('click',load); $('basis').addEventListener('change',load);
    $('unit').addEventListener('change',()=>{ $('attest-submit').disabled=!review?.units[$('unit').value] || review.units[$('unit').value].missing.length>0; });
    $('correction-unit').addEventListener('change',correctionValues);
    $('attest').addEventListener('submit',e=>{e.preventDefault(); if (!review) return; const unit=review.units[$('unit').value]; mutate('attest',{basis:review.basis,unit:$('unit').value,fingerprint:unit.fingerprint,reason:$('attest-reason').value,evidence_reference:$('attest-reference').value});});
    $('finalize').addEventListener('submit',e=>{e.preventDefault(); if (!review?.ready) return; mutate('close',{basis:review.basis,token:review.token,confirmation:$('confirm').value,acknowledge_issues:$('ack').checked,reason:$('reason').value,evidence_reference:$('reference').value});});
    $('correction').addEventListener('submit',e=>{e.preventDefault(); if (!review) return; const values={}; $('correction-values').querySelectorAll('input').forEach(i=>values[i.name]=i.disabled || i.value===''?null:Number(i.value)); mutate('corrections',{unit:$('correction-unit').value,expected_version:review.revision,values,reason:$('correction-reason').value,evidence_reference:$('correction-reference').value});});
})();
