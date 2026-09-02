<x-app-layout>
<style>
.tp { font-size:12px;padding:5px 14px;border-radius:99px;border:1px solid #e0e0e0;background:#f5f5f3;color:#666;cursor:pointer;white-space:nowrap;transition:all .15s; }
.tp:hover { background:#ebebeb; }
.tp.active { background:#fff;color:#1a1a1a;border-color:#aaa;font-weight:500; }
.ov-stat { background:#f5f5f3;border-radius:10px;padding:14px 16px; }
.pill { font-size:10px;padding:3px 9px;border-radius:99px;font-weight:500;white-space:nowrap; }
.p-in  { background:#FAF9F6;color:#555;border:1px solid #e8e4dc; }
.p-re  { background:#ede9fe;color:#5b21b6; }
.p-le  { background:#efefed;color:#555; }
.p-si  { background:#fde8e8;color:#a02020; }
.p-off { background:#f0f0ee;color:#999; }
.p-hol { background:#ddeeff;color:#1558a0; }
.p-med { background:#fff0d0;color:#7a4800; }
.p-wfh { background:#ede9fe;color:#5b21b6; }
.day-cell { width:32px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.dc-in   { background:#FAF9F6; }   .dc-re  { background:#ede9fe; }
.dc-le   { background:#efefed; }  .dc-si  { background:#fde8e8; }
.dc-off  { background:#f5f5f3; }  .dc-hol { background:#ddeeff; }
.dc-med  { background:#fff0d0; }  .dc-wfh { background:#ede9fe; }
.dcl-in  { color:#555; }          .dcl-re { color:#5b21b6; }
.dcl-le  { color:#555; }          .dcl-si { color:#a02020; }
.dcl-off { color:#bbb; }          .dcl-hol { color:#1558a0; }
.dcl-med { color:#7a4800; }       .dcl-wfh { color:#5b21b6; }
.today-col { outline:1.5px solid #3a8ddd;border-radius:6px; }
.nb-warn { background:#fff0d8;color:#7a4800; }
.nb-info { background:#ddeeff;color:#1558a0; }
.has-tip { position:relative; }
.has-tip::after { content:''; position:absolute; top:0; right:0; width:0; height:0; border-style:solid; border-width:0 7px 7px 0; border-color:transparent #e53e3e transparent transparent; border-top-right-radius:6px; pointer-events:none; }
.cell-tip { position:fixed; background:#1a1a1a; color:#fff; font-size:11px; padding:5px 9px; border-radius:6px; white-space:nowrap; pointer-events:none; z-index:9999; box-shadow:0 2px 8px rgba(0,0,0,.25); transform:translateX(-50%); }
.fbtn { font-size:11px;padding:3px 10px;border-radius:99px;border:1px solid #e0e0e0;background:#f5f5f3;color:#888;cursor:pointer; }
.fbtn.on { background:#fff;color:#1a1a1a;border-color:#aaa;font-weight:500; }
.status-box { min-width:46px;height:26px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:500;white-space:nowrap;padding:0 8px; }
.ov-card { background:#fff;border:1px solid #ebebeb;border-radius:14px;padding:1rem 1.25rem; }
.prow { display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #f0f0ee; }
.prow:last-child { border-bottom:none; }
@media (max-width: 768px) {
  .team-notices-grid { grid-template-columns: 1fr !important; }
  .prow { flex-wrap: wrap; row-gap: 4px; }
  .prow-name { flex: 1; min-width: calc(100% - 48px); }
  .prow-meta { width: 100%; display: flex; align-items: center; gap: 8px; padding-left: 40px; }
  .prow-time { width: auto !important; }
}
</style>

<div class="page" x-data="teamOverview()" x-init="customFrom && customTo && applyCustom()">

    {{-- Shared cell tooltip --}}
    <div class="cell-tip" x-show="cellTip.show" x-text="cellTip.text"
         :style="'top:'+(cellTip.y-38)+'px;left:'+cellTip.x+'px;'" style="display:none;"></div>

    {{-- ── Header ── --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="font-size:20px;font-weight:500;color:#1a1a1a;">Team overview</h2>
            <div style="font-size:12px;color:#888;margin-top:2px;" x-text="periodSub"></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button class="tp" :class="view==='today'?'active':''"  @click="setView('today')">Today</button>
                <button class="tp" :class="view==='week'?'active':''"   @click="setView('week')">This week</button>
                <button class="tp" :class="view==='month'?'active':''"  @click="window.location.href='{{ route('team.index') }}?view=month'">This month</button>
                <button class="tp" :class="view==='custom'?'active':''" @click="setView('custom')">Custom</button>
            </div>
            <template x-if="view==='custom'">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <input type="date" x-model="customFrom" style="font-size:12px;padding:4px 8px;border-radius:8px;border:1px solid #e0e0e0;background:#f5f5f3;color:#1a1a1a;font-family:inherit;">
                    <span style="font-size:11px;color:#aaa;">to</span>
                    <input type="date" x-model="customTo" style="font-size:12px;padding:4px 8px;border-radius:8px;border:1px solid #e0e0e0;background:#f5f5f3;color:#1a1a1a;font-family:inherit;">
                    <button @click="applyCustom()" style="font-size:12px;padding:4px 12px;border-radius:8px;border:1px solid #ccc;background:#fff;cursor:pointer;font-family:inherit;">Apply</button>
                </div>
            </template>
            <span style="font-size:11px;color:#999;background:#f5f5f3;border:1px solid #e0e0e0;border-radius:8px;padding:3px 10px;" x-text="rangeLabel"></span>
        </div>
    </div>

    {{-- ── Stats row ── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:1.25rem;">
        <template x-for="s in stats" :key="s.label">
            <div class="ov-stat">
                <div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;" x-text="s.label"></div>
                <div style="font-size:28px;font-weight:500;" :style="'color:'+s.color" x-text="s.value"></div>
                <div style="font-size:11px;color:#888;margin-top:3px;" x-text="s.sub"></div>
            </div>
        </template>
    </div>

    {{-- ── Segment bar ── --}}
    <div class="ov-card" style="margin-bottom:12px;">
        <div style="font-size:12px;font-weight:500;color:#888;letter-spacing:.02em;margin-bottom:12px;" x-text="segTitle"></div>
        <div style="height:28px;width:100%;border-radius:10px;overflow:hidden;display:flex;margin-bottom:10px;">
            <template x-if="seg.office>0">
                <div :style="'width:'+pct(seg.office)+'%;background:#3a7dcc;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.office"></span>
                </div>
            </template>
            <template x-if="seg.remote>0">
                <div :style="'width:'+pct(seg.remote)+'%;background:#0d7a55;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.remote"></span>
                </div>
            </template>
            <template x-if="seg.wfh>0">
                <div :style="'width:'+pct(seg.wfh)+'%;background:#1d9e75;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.wfh"></span>
                </div>
            </template>
            <template x-if="seg.leave>0">
                <div :style="'width:'+pct(seg.leave)+'%;background:#b4b2a9;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.leave"></span>
                </div>
            </template>
            <template x-if="seg.sick>0">
                <div :style="'width:'+pct(seg.sick)+'%;background:#e24b4a;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.sick"></span>
                </div>
            </template>
            <template x-if="seg.medical>0">
                <div :style="'width:'+pct(seg.medical)+'%;background:#e8a020;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.medical"></span>
                </div>
            </template>
            <template x-if="seg.holiday>0">
                <div :style="'width:'+pct(seg.holiday)+'%;background:#a78bfa;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="seg.holiday"></span>
                </div>
            </template>
            <template x-if="seg.unknown>0">
                <div :style="'width:'+pct(seg.unknown)+'%;background:#e0e0e0;display:flex;align-items:center;justify-content:center;'">
                    <span style="font-size:10px;font-weight:600;color:#aaa;padding:0 5px;" x-text="seg.unknown"></span>
                </div>
            </template>
        </div>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#3a7dcc;display:inline-block;"></span>In office</span>
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#0d7a55;display:inline-block;"></span>Remote</span>
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#1d9e75;display:inline-block;"></span>WFH</span>
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#b4b2a9;display:inline-block;"></span>On leave</span>
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#e8a020;display:inline-block;"></span>Medical</span>
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#e24b4a;display:inline-block;"></span>Sick</span>
            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#a78bfa;display:inline-block;"></span>Public holiday</span>
            <template x-if="view==='today'">
                <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#e0e0e0;display:inline-block;"></span>Not set</span>
            </template>
        </div>
    </div>

    {{-- ── Today / Week: people + notices ── --}}
    <template x-if="view==='today' || view==='week'">
        <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;margin-bottom:12px;" class="team-notices-grid">

            {{-- People card --}}
            <div class="ov-card" style="overflow-x:auto;">
                <div style="font-size:12px;font-weight:500;color:#888;letter-spacing:.02em;margin-bottom:12px;"
                     x-text="view==='today' ? 'Team — today' : 'Team schedule — this week'"></div>

                {{-- Today filters --}}
                <template x-if="view==='today'">
                    <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;">
                        <button class="fbtn" :class="filter==='all'?'on':''"     @click="filter='all'">All</button>
                        <button class="fbtn" :class="filter==='office'?'on':''"  @click="filter='office'">In office</button>
                        <button class="fbtn" :class="filter==='remote'?'on':''"  @click="filter='remote'">Remote</button>
                        <button class="fbtn" :class="filter==='wfh'?'on':''"     @click="filter='wfh'">WFH</button>
                        <button class="fbtn" :class="filter==='leave'?'on':''"   @click="filter='leave'">On leave</button>
                        <button class="fbtn" :class="filter==='sick'?'on':''"    @click="filter='sick'">Sick</button>
                        <button class="fbtn" :class="filter==='medical'?'on':''" @click="filter='medical'">Medical</button>
                        <button class="fbtn" :class="filter==='holiday'?'on':''" @click="filter='holiday'">Public holiday</button>
                    </div>
                </template>

                {{-- Today: person rows --}}
                <template x-if="view==='today'">
                    <div>
                        <template x-for="p in filteredPeople" :key="p.id">
                            <div class="prow">
                                <template x-if="p.photo_url">
                                    <img :src="p.photo_url" :alt="p.name" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                </template>
                                <template x-if="!p.photo_url">
                                    <div :style="'width:32px;height:32px;font-size:11px;font-weight:500;flex-shrink:0;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:'+p.color+'33;color:'+p.color"
                                         x-text="p.initials"></div>
                                </template>
                                <div class="prow-name" style="flex:1;min-width:0;">
                                    <div style="font-size:13px;font-weight:500;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="p.name"></div>
                                    <div style="font-size:11px;color:#888;" x-text="p.role"></div>
                                </div>
                                <div class="prow-meta" style="display:contents;">
                                <span :class="'status-box ' + (p.is_medical && p.statusLoc ? dayCellCls(p.statusLoc) + ' ' + dayLblCls(p.statusLoc) : dayCellCls(p.status) + ' ' + dayLblCls(p.status)) + (p.is_medical && p.statusTip ? ' has-tip' : '')"
                                      :style="(!p.is_medical && p.statusColor) ? 'background:'+p.statusColor+'55' : ''"
                                      @mouseenter="p.is_medical && p.statusTip ? showCellTip($event, p.statusTip) : null"
                                      @mouseleave="p.is_medical ? hideCellTip() : null"
                                      x-text="(p.is_medical && p.statusLoc ? statusLabel(p.statusLoc) : (p.statusTypeName && (p.status === 'leave' || p.status === 'sick') ? p.statusTypeName : statusLabel(p.status))) + (p.is_half_day ? ' ½' : '')"></span>
                                <div class="prow-time" style="width:90px;flex-shrink:0;display:flex;justify-content:center;">
                                    <span x-show="p.signed_in"
                                          style="font-size:10px;padding:2px 8px;border-radius:99px;background:#d8f5ec;color:#0d6648;font-weight:500;white-space:nowrap;">
                                        ✓ <span x-text="p.time"></span>
                                    </span>
                                    <span x-show="!p.signed_in" style="font-size:11px;color:#ccc;white-space:nowrap;">Not signed in</span>
                                </div>
                                </div>{{-- end prow-meta --}}
                            </div>
                        </template>
                        <template x-if="filteredPeople.length===0">
                            <div style="font-size:13px;color:#aaa;padding:16px 0;text-align:center;">No team members match this filter.</div>
                        </template>
                    </div>
                </template>

                {{-- Week: day grid --}}
                <template x-if="view==='week'">
                    <div>
                        <div style="display:grid;grid-template-columns:32px 124px repeat(5,1fr);gap:6px;padding:0 0 8px;border-bottom:1px solid #f0f0ee;margin-bottom:4px;align-items:center;">
                            <div></div>
                            <div></div>
                            <template x-for="(day,i) in weekLabels" :key="i">
                                <div style="font-size:10px;font-weight:500;text-align:center;"
                                     :style="i===todayIdx ? 'color:#3a8ddd;font-weight:600;' : 'color:#aaa'" x-text="day"></div>
                            </template>
                        </div>
                        <template x-for="p in teamData" :key="p.id">
                            <div style="display:grid;grid-template-columns:32px 124px repeat(5,1fr);gap:6px;padding:8px 0;border-bottom:1px solid #f0f0ee;align-items:center;">
                                <template x-if="p.photo_url">
                                    <img :src="p.photo_url" :alt="p.name" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                </template>
                                <template x-if="!p.photo_url">
                                    <div :style="'width:32px;height:32px;font-size:11px;font-weight:500;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:'+p.color+'33;color:'+p.color"
                                         x-text="p.initials"></div>
                                </template>
                                <div style="min-width:0;">
                                    <div style="font-size:12px;font-weight:500;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="p.name"></div>
                                    <div style="font-size:11px;color:#888;" x-text="p.role"></div>
                                </div>
                                <template x-for="(cell,di) in p.week" :key="di">
                                    <div class="day-cell" style="width:auto;" :class="[cellBgCls(cell), di===todayIdx ? 'today-col' : '', cell.is_medical ? 'has-tip' : '']"
                                         :style="(cell.booked && !cell.is_medical && cell.c && cell.s !== 'wfh') ? 'background:'+cell.c+'55' : ''"
                                         @mouseenter="showCellTip($event, cell.tip || dayTitle(cell.s))"
                                         @mouseleave="hideCellTip()">
                                        <span style="font-size:9px;font-weight:500;" :class="cellLblCls(cell)" x-text="cellLbl(cell)"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;">
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ddeeff;border:1px solid #b5d4f4;display:inline-block;"></span>In</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#c6ede0;border:1px solid #7acbab;display:inline-block;"></span>Re</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#d8f5ec;border:1px solid #9fe1cb;display:inline-block;"></span>WFH</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#efefed;border:1px solid #d3d1c7;display:inline-block;"></span>Leave</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fff0d0;border:1px solid #e8c97a;display:inline-block;"></span>Medical</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fde8e8;border:1px solid #f7c1c1;display:inline-block;"></span>Sick</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ede9fe;border:1px solid #c4b5fd;display:inline-block;"></span>Holiday</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#f5f5f3;border:1px solid #e0e0e0;display:inline-block;"></span>—</span>
                            <template x-if="todayIdx !== null">
                                <span style="font-size:10px;color:#3a8ddd;font-weight:500;">| Today</span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Notices card --}}
            <div class="ov-card" x-data="{ showForm: false }">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div style="font-size:12px;font-weight:500;color:#888;letter-spacing:.02em;">Notices</div>
                    <button @click="showForm=!showForm"
                            :style="showForm ? 'font-size:11px;padding:3px 12px;border-radius:99px;border:1px solid #83acdb;background:#83acdb;color:#fff;cursor:pointer;font-family:inherit;' : 'font-size:11px;padding:3px 12px;border-radius:99px;border:1px solid #d5d2cc;background:#f5f5f3;color:#555;cursor:pointer;font-family:inherit;'">
                        + Post notice
                    </button>
                </div>

                {{-- Post notice form --}}
                <div x-show="showForm" x-transition style="margin-bottom:12px;background:#f9f8f6;border-radius:10px;padding:12px;">
                    <form method="POST" action="{{ route('team.notices.store') }}">
                        @csrf
                        <div style="margin-bottom:8px;">
                            <select name="target_user_id" style="width:100%;font-size:12px;padding:6px 10px;border-radius:8px;border:1px solid #e0e0e0;background:#fff;color:#1a1a1a;font-family:inherit;margin-bottom:8px;">
                                <option value="">— Everyone —</option>
                                @foreach($allEmployees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                @endforeach
                            </select>
                            <textarea name="message" rows="3" maxlength="500" required placeholder="Write a notice or reminder…"
                                      style="width:100%;font-size:12px;padding:8px 10px;border-radius:8px;border:1px solid #e0e0e0;background:#fff;color:#1a1a1a;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
                        </div>
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <button type="button" @click="showForm=false" style="font-size:11px;padding:4px 14px;border-radius:99px;border:1px solid #d5d2cc;background:#fff;color:#555;cursor:pointer;font-family:inherit;">Cancel</button>
                            <button type="submit" style="font-size:11px;padding:4px 14px;border-radius:99px;border:1px solid #83acdb;background:#83acdb;color:#fff;cursor:pointer;font-family:inherit;">Post</button>
                        </div>
                    </form>
                </div>

                {{-- Manager-posted notices --}}
                @if($managerNotices->isNotEmpty())
                    <div style="font-size:10px;font-weight:600;color:#aaa;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Posted by managers</div>
                    @foreach($managerNotices as $mn)
                        <div style="display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px solid #f0f0ee;">
                            <span style="font-size:10px;padding:2px 7px;border-radius:99px;font-weight:500;flex-shrink:0;margin-top:1px;background:#ede9fe;color:#5b21b6;">Note</span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:12px;color:#1a1a1a;line-height:1.4;">{{ $mn->message }}</div>
                                <div style="font-size:10px;color:#aaa;margin-top:2px;">
                                    To: <strong style="color:#888;">{{ $mn->targetUser ? $mn->targetUser->name : 'Everyone' }}</strong>
                                    &bull; by {{ $mn->author->name }}
                                    &bull; {{ $mn->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('team.notices.destroy', $mn) }}" style="flex-shrink:0;">
                                @csrf @method('DELETE')
                                <button type="submit" style="font-size:10px;padding:2px 8px;border-radius:99px;border:1px solid #f0c0c0;background:#fff;color:#c03030;cursor:pointer;font-family:inherit;" onclick="return confirm('Delete this notice?')">✕</button>
                            </form>
                        </div>
                    @endforeach
                    @if($notices)
                        <div style="font-size:10px;font-weight:600;color:#aaa;text-transform:uppercase;letter-spacing:.06em;margin:12px 0 8px;">System alerts</div>
                    @endif
                @endif

                {{-- System-generated notices --}}
                <template x-if="notices.length===0 && {{ $managerNotices->isEmpty() ? 'true' : 'false' }}">
                    <div style="font-size:13px;color:#aaa;padding:8px 0;">Nothing to flag today.</div>
                </template>
                <template x-for="n in notices" :key="n.title">
                    <div style="display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px solid #f0f0ee;">
                        <span :class="n.type==='warn' ? 'nb-warn' : 'nb-info'"
                              style="font-size:10px;padding:2px 7px;border-radius:99px;font-weight:500;flex-shrink:0;margin-top:1px;"
                              x-text="n.type==='warn' ? 'Alert' : 'Info'"></span>
                        <div>
                            <div style="font-size:12px;color:#1a1a1a;line-height:1.4;" x-text="n.title"></div>
                            <div style="font-size:10px;color:#aaa;margin-top:1px;" x-text="n.meta"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- ── Custom view ── --}}
    <template x-if="view==='custom'">
        <div>
            <template x-if="customLoading">
                <div style="text-align:center;padding:40px;color:#aaa;font-size:13px;">Loading…</div>
            </template>
            <template x-if="!customLoading && !customData">
                <div class="ov-card" style="text-align:center;padding:32px;color:#aaa;font-size:13px;">
                    Select a date range above and click <strong style="color:#555;">Apply</strong> to view team data.
                </div>
            </template>
            <template x-if="!customLoading && customData">
                <div>
                    <div class="ov-card" style="margin-bottom:12px;">
                        <div style="font-size:12px;font-weight:500;color:#888;letter-spacing:.02em;margin-bottom:12px;" x-text="'Team overview — ' + customFrom + ' to ' + customTo"></div>
                        <div style="height:28px;width:100%;border-radius:10px;overflow:hidden;display:flex;margin-bottom:10px;">
                            <template x-if="customData.totals.office>0">
                                <div :style="'width:'+customPct(customData.totals.office)+'%;background:#3a7dcc;display:flex;align-items:center;justify-content:center;'">
                                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="customData.totals.office+'d'"></span>
                                </div>
                            </template>
                            <template x-if="customData.totals.remote>0">
                                <div :style="'width:'+customPct(customData.totals.remote)+'%;background:#1d9e75;display:flex;align-items:center;justify-content:center;'">
                                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="customData.totals.remote+'d'"></span>
                                </div>
                            </template>
                            <template x-if="customData.totals.leave>0">
                                <div :style="'width:'+customPct(customData.totals.leave)+'%;background:#b4b2a9;display:flex;align-items:center;justify-content:center;'">
                                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="customData.totals.leave+'d'"></span>
                                </div>
                            </template>
                            <template x-if="customData.totals.sick>0">
                                <div :style="'width:'+customPct(customData.totals.sick)+'%;background:#e24b4a;display:flex;align-items:center;justify-content:center;'">
                                    <span style="font-size:10px;font-weight:600;color:#fff;padding:0 5px;" x-text="customData.totals.sick+'d'"></span>
                                </div>
                            </template>
                        </div>
                        <div style="display:flex;gap:14px;flex-wrap:wrap;">
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#3a7dcc;display:inline-block;"></span>In office</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#1d9e75;display:inline-block;"></span>Remote</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#b4b2a9;display:inline-block;"></span>On leave</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:5px;"><span style="width:8px;height:8px;border-radius:50%;background:#e24b4a;display:inline-block;"></span>Sick</span>
                        </div>
                    </div>
                    <div class="ov-card" style="overflow-x:auto;">
                        <table style="border-collapse:collapse;width:100%;">
                            <thead>
                                <tr>
                                    <th style="min-width:160px;text-align:left;font-size:11px;color:#aaa;font-weight:500;padding:0 12px 8px 0;position:sticky;left:0;background:#fff;z-index:2;"></th>
                                    <template x-for="d in customData.dayInfo" :key="d.date">
                                        <th :style="d.weekend ? 'width:26px;min-width:26px;text-align:center;padding:0 1px 8px;' : 'width:26px;min-width:26px;text-align:center;padding:0 1px 8px;'">
                                            <div style="font-size:8px;color:#c4b5fd;font-weight:600;height:10px;line-height:10px;" x-text="d.monthLabel || ''"></div>
                                            <div :style="d.weekend ? 'font-size:10px;color:#ccc;font-weight:600;' : 'font-size:10px;color:#aaa;font-weight:600;'" x-text="d.num"></div>
                                            <div :style="d.weekend ? 'font-size:9px;color:#ddd;' : 'font-size:9px;color:#bbb;'" x-text="d.label"></div>
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="p in customData.teamData" :key="p.id">
                                    <tr>
                                        <td style="padding:3px 12px 3px 0;position:sticky;left:0;background:#fff;z-index:1;border-bottom:1px solid #f5f5f3;">
                                            <div style="display:flex;align-items:center;gap:7px;">
                                                <template x-if="p.photo_url">
                                                    <img :src="p.photo_url" :alt="p.name" style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                                </template>
                                                <template x-if="!p.photo_url">
                                                    <div :style="'width:26px;height:26px;font-size:9px;font-weight:500;flex-shrink:0;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:'+p.color+'33;color:'+p.color"
                                                         x-text="p.initials"></div>
                                                </template>
                                                <div>
                                                    <div style="font-size:12px;font-weight:500;color:#1a1a1a;white-space:nowrap;" x-text="p.name"></div>
                                                    <div style="font-size:10px;color:#aaa;white-space:nowrap;" x-text="p.role"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <template x-for="(cell, i) in p.dayGrid" :key="i">
                                            <td style="padding:3px 1px;border-bottom:1px solid #f5f5f3;">
                                                <div class="day-cell" :class="[cellBgCls(cell), cell.is_medical ? 'has-tip' : '']"
                                                     :style="(cell.booked && !cell.is_medical && cell.c && cell.s !== 'wfh') ? 'background:'+cell.c+'55' : ''"
                                                     @mouseenter="showCellTip($event, cell.tip || dayTitle(cell.s))"
                                                     @mouseleave="hideCellTip()">
                                                    <span style="font-size:9px;font-weight:500;" :class="cellLblCls(cell)" x-text="cellLbl(cell)"></span>
                                                </div>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        {{-- Legend --}}
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;padding-top:10px;border-top:1px solid #f0f0ee;">
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ddeeff;border:1px solid #b5d4f4;display:inline-block;"></span>In</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#c6ede0;border:1px solid #7acbab;display:inline-block;"></span>Re</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#d8f5ec;border:1px solid #9fe1cb;display:inline-block;"></span>WFH</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#efefed;border:1px solid #d3d1c7;display:inline-block;"></span>Leave</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fff0d0;border:1px solid #e8c97a;display:inline-block;"></span>Medical</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fde8e8;border:1px solid #f7c1c1;display:inline-block;"></span>Sick</span>
                            <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ede9fe;border:1px solid #c4b5fd;display:inline-block;"></span>Holiday</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- ── Month view ── --}}
    <template x-if="view==='month'">
        <div>
            <div class="ov-card">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <a href="{{ route('team.index') }}?view=month&month={{ $prevMonth }}"
                       style="font-size:15px;color:#888;text-decoration:none;line-height:1;padding:2px 6px;border-radius:4px;border:1px solid #e0e0e0;background:#f5f5f3;" title="Previous month">‹</a>
                    <span style="font-size:13px;font-weight:600;color:#1a1a1a;min-width:120px;text-align:center;">{{ $viewMonthLabel }}</span>
                    <a href="{{ route('team.index') }}?view=month&month={{ $nextMonth }}"
                       style="font-size:15px;color:#888;text-decoration:none;line-height:1;padding:2px 6px;border-radius:4px;border:1px solid #e0e0e0;background:#f5f5f3;" title="Next month">›</a>
                    @if(!$isCurrentMonth)
                        <a href="{{ route('team.index') }}?view=month"
                           style="font-size:11px;color:#3a8ddd;text-decoration:none;margin-left:4px;">Today's month</a>
                    @endif
                </div>
                <table style="border-collapse:collapse;width:100%;">
                    <thead>
                        <tr>
                            {{-- Sticky name header --}}
                            <th style="min-width:160px;text-align:left;font-size:11px;color:#aaa;font-weight:500;padding:0 12px 8px 0;position:sticky;left:0;background:#fff;z-index:2;"></th>
                            <template x-for="d in monthDayInfo" :key="d.num">
                                <th style="text-align:center;padding:0 1px 8px;">
                                    <div :style="d.weekend ? 'font-size:10px;color:#ccc;font-weight:600;' : 'font-size:10px;color:#aaa;font-weight:600;'" x-text="d.num"></div>
                                    <div :style="d.weekend ? 'font-size:9px;color:#ddd;' : 'font-size:9px;color:#bbb;'" x-text="d.label"></div>
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="p in teamData" :key="p.id">
                            <tr>
                                {{-- Sticky name cell --}}
                                <td style="padding:3px 12px 3px 0;position:sticky;left:0;background:#fff;z-index:1;border-bottom:1px solid #f5f5f3;">
                                    <div style="display:flex;align-items:center;gap:7px;">
                                        <template x-if="p.photo_url">
                                            <img :src="p.photo_url" :alt="p.name" style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                        </template>
                                        <template x-if="!p.photo_url">
                                            <div :style="'width:26px;height:26px;font-size:9px;font-weight:500;flex-shrink:0;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:'+p.color+'33;color:'+p.color"
                                                 x-text="p.initials"></div>
                                        </template>
                                        <div>
                                            <div style="font-size:12px;font-weight:500;color:#1a1a1a;white-space:nowrap;" x-text="p.name"></div>
                                            <div style="font-size:10px;color:#aaa;white-space:nowrap;" x-text="p.role"></div>
                                        </div>
                                    </div>
                                </td>
                                {{-- Day cells --}}
                                <template x-for="(cell, i) in p.monthGrid" :key="i">
                                    <td style="padding:3px 1px;border-bottom:1px solid #f5f5f3;">
                                        <div class="day-cell" style="width:auto;" :class="[cellBgCls(cell), cell.is_medical ? 'has-tip' : '']"
                                             :style="(cell.booked && !cell.is_medical && cell.c && cell.s !== 'wfh') ? 'background:'+cell.c+'55' : ''"
                                             @mouseenter="showCellTip($event, cell.tip || dayTitle(cell.s))"
                                             @mouseleave="hideCellTip()">
                                            <span style="font-size:9px;font-weight:500;" :class="cellLblCls(cell)" x-text="cellLbl(cell)"></span>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>

                {{-- Legend --}}
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;padding-top:10px;border-top:1px solid #f0f0ee;">
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ddeeff;border:1px solid #b5d4f4;display:inline-block;"></span>In</span>
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#d8f5ec;border:1px solid #9fe1cb;display:inline-block;"></span>WFH</span>
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#efefed;border:1px solid #d3d1c7;display:inline-block;"></span>Leave</span>
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fff0d0;border:1px solid #e8c97a;display:inline-block;"></span>Medical</span>
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fde8e8;border:1px solid #f7c1c1;display:inline-block;"></span>Sick</span>
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ede9fe;border:1px solid #c4b5fd;display:inline-block;"></span>Holiday</span>
                    <span style="font-size:11px;color:#888;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#f5f5f3;border:1px solid #e0e0e0;display:inline-block;"></span>Weekend</span>
                </div>
            </div>
        </div>
    </template>

</div>

<script>
function teamOverview() {
    const teamData    = @json($teamData);
    const notices     = @json($notices);
    const weekLabels  = @json($weekLabels);
    const todayIdx    = {{ $todayIdx ?? 'null' }};
    const monthDayInfo = @json($monthDayInfo);

    return {
        view: '{{ $initialView }}',
        filter: 'all',
        teamData,
        notices,
        weekLabels,
        todayIdx,
        monthDayInfo,
        customFrom: '{{ $preloadFrom }}',
        customTo: '{{ $preloadTo }}',
        customData: null,
        customLoading: false,
        cellTip: { show: false, text: '', x: 0, y: 0 },

        setView(v) { this.view = v; this.filter = 'all'; },
        showCellTip(e, text) { this.cellTip = { show: true, text, x: e.clientX, y: e.clientY }; },
        hideCellTip() { this.cellTip.show = false; },
        isLeaveCell(s) { return s === 'leave' || s === 'sick' || s.startsWith('medical'); },
        cellLbl(cell) {
            if (cell.booked && !cell.is_medical && cell.loc) {
                if (cell.is_half_day) return '½';
                if (cell.s === 'wfh') return 'WFH';
                return '';
            }
            if (cell.booked && cell.loc) {
                return {office:'In', wfh:'WFH', remote:'Re'}[cell.loc] || 'In';
            }
            return this.dayLbl(cell.s);
        },
        cellBgCls(cell) {
            if (cell.s === 'wfh') return 'dc-wfh';
            const loc = (cell.booked && cell.loc) ? cell.loc : cell.s;
            if (loc === 'holiday') return 'dc-hol';
            if (loc === 'wfh')     return 'dc-wfh';
            if (loc === 'remote')  return 'dc-re';
            if (loc === 'office')  return 'dc-in';
            return 'dc-off';
        },
        cellLblCls(cell) {
            const loc = (cell.booked && cell.loc) ? cell.loc : cell.s;
            if (loc.startsWith('medical')) return 'dcl-in';
            return ({office:'dcl-in',remote:'dcl-re',wfh:'dcl-wfh',leave:'dcl-le',sick:'dcl-si',holiday:'dcl-hol',unknown:'dcl-off'}[loc]||'dcl-off');
        },

        async applyCustom() {
            if (!this.customFrom || !this.customTo) return;
            this.customLoading = true;
            this.customData = null;
            try {
                const res = await fetch(`/team/custom?from=${this.customFrom}&to=${this.customTo}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.customData = await res.json();
            } finally {
                this.customLoading = false;
            }
        },

        customPct(n) {
            if (!this.customData) return 0;
            const t = this.customData.totals;
            const total = t.office + t.remote + t.leave + t.sick || 1;
            return Math.round(n / total * 100);
        },

        get periodSub() {
            const d = new Date();
            if (this.view === 'today') return d.toLocaleDateString('en-GB', {weekday:'long',day:'numeric',month:'long',year:'numeric'});
            if (this.view === 'week')  return 'Week of ' + this.weekLabels[0] + ' – ' + this.weekLabels[4];
            return d.toLocaleDateString('en-GB', {month:'long',year:'numeric'});
        },

        get rangeLabel() {
            const d = new Date();
            if (this.view === 'today')  return d.toLocaleDateString('en-GB', {weekday:'short',day:'numeric',month:'short',year:'numeric'});
            if (this.view === 'week')   return this.weekLabels[0] + ' – ' + this.weekLabels[4];
            if (this.view === 'custom') return (this.customFrom && this.customTo) ? this.customFrom + ' to ' + this.customTo : 'Select a range';
            return d.toLocaleDateString('en-GB', {month:'long',year:'numeric'});
        },

        get segTitle() {
            if (this.view === 'today') return 'Where is everyone today?';
            if (this.view === 'week')  return 'Location split — this week';
            return 'Location split — this month';
        },

        get seg() {
            const c = {office:0, remote:0, wfh:0, leave:0, sick:0, holiday:0, medical:0, unknown:0};
            const bump = (s) => {
                if (s.startsWith('medical')) { c.medical++; } else { c[s] = (c[s] ?? 0) + 1; }
            };
            if (this.view === 'today') {
                this.teamData.forEach(p => bump(p.status));
            } else if (this.view === 'week') {
                this.teamData.forEach(p => p.week.forEach(cell => bump(cell.s)));
            } else if (this.view === 'custom' && this.customData) {
                const t = this.customData.totals;
                c.office = t.office || 0;
                c.remote = t.remote || 0;
                c.wfh    = t.wfh    || 0;
                c.leave  = t.leave  || 0;
                c.sick   = t.sick   || 0;
            } else {
                this.teamData.forEach(p => {
                    c.office += p.month.office || 0;
                    c.remote += p.month.remote || 0;
                    c.wfh    += p.month.wfh    || 0;
                    c.leave  += p.month.leave  || 0;
                    c.sick   += p.month.sick   || 0;
                });
            }
            return c;
        },

        pct(n) {
            const total = Object.values(this.seg).reduce((a,b) => a+b, 0) || 1;
            return Math.round(n / total * 100);
        },

        monthPct(n) {
            const total = 23; // ~working days in a month
            return Math.min(100, Math.round(n / total * 100));
        },

        get stats() {
            const total = this.teamData.length;
            const c = this.seg;
            if (this.view === 'today') return [
                {label:'Team size', value:total,           sub:'total employees',                         color:'#1a1a1a'},
                {label:'In office', value:c.office,        sub:Math.round(c.office/total*100)+'% of team', color:'#3a7dcc'},
                {label:'Remote',    value:c.remote,        sub:Math.round(c.remote/total*100)+'% of team', color:'#1d9e75'},
                {label:'WFH',       value:c.wfh,           sub:Math.round(c.wfh/total*100)+'% of team',    color:'#1d9e75'},
                {label:'Off today', value:c.leave+c.sick,  sub:c.leave+' leave · '+c.sick+' sick',         color:'#c03030'},
            ];
            if (this.view === 'week') return [
                {label:'Team size',       value:total,          sub:'total employees',                    color:'#1a1a1a'},
                {label:'In office days',  value:c.office,       sub:'across the team',                    color:'#3a7dcc'},
                {label:'Remote days',     value:c.remote,       sub:'across the team',                    color:'#1d9e75'},
                {label:'WFH days',        value:c.wfh,          sub:'across the team',                    color:'#1d9e75'},
                {label:'Days off',        value:c.leave+c.sick, sub:c.leave+' leave · '+c.sick+' sick',   color:'#c03030'},
            ];
            const periodSub = this.view === 'custom' ? 'in date range' : 'total this month';
            return [
                {label:'Team size',   value:total,          sub:'total employees',                        color:'#1a1a1a'},
                {label:'Office days', value:c.office,       sub:periodSub,                                color:'#3a7dcc'},
                {label:'Remote days', value:c.remote,       sub:periodSub,                                color:'#1d9e75'},
                {label:'WFH days',    value:c.wfh,          sub:periodSub,                                color:'#1d9e75'},
                {label:'Days off',    value:c.leave+c.sick, sub:c.leave+' leave · '+c.sick+' sick',       color:'#c03030'},
            ];
        },

        get filteredPeople() {
            if (this.filter === 'all') return this.teamData;
            if (this.filter === 'medical') return this.teamData.filter(p => p.status.startsWith('medical'));
            return this.teamData.filter(p => p.status === this.filter);
        },

        statusLabel(s) {
            if (s === 'medical-morning')   return 'Medical (AM)';
            if (s === 'medical-afternoon') return 'Medical (PM)';
            if (s === 'medical')           return 'Medical appt';
            return {office:'In office',remote:'Remote',wfh:'WFH',leave:'On leave',sick:'Sick',holiday:'Public holiday',unknown:'Not set'}[s] || s;
        },
        pillCls(s) {
            if (s.startsWith('medical')) return 'pill p-med';
            return ({office:'pill p-in',remote:'pill p-re',wfh:'pill p-wfh',leave:'pill p-le',sick:'pill p-si',holiday:'pill p-hol',unknown:'pill p-off'}[s]||'pill p-off');
        },
        dayCellCls(s) {
            if (s.startsWith('medical')) return 'dc-med';
            return ({office:'dc-in',remote:'dc-re',wfh:'dc-wfh',leave:'dc-le',sick:'dc-si',holiday:'dc-hol',unknown:'dc-off'}[s]||'dc-off');
        },
        dayLblCls(s) {
            if (s.startsWith('medical')) return 'dcl-med';
            return ({office:'dcl-in',remote:'dcl-re',wfh:'dcl-wfh',leave:'dcl-le',sick:'dcl-si',holiday:'dcl-hol',unknown:'dcl-off'}[s]||'dcl-off');
        },
        dayLbl(s) {
            if (s === 'medical-morning')   return 'M·AM';
            if (s === 'medical-afternoon') return 'M·PM';
            if (s === 'medical')           return 'M';
            return ({office:'In',remote:'Re',wfh:'WFH',leave:'Lv',sick:'Sick',holiday:'PH',unknown:'—'}[s]||'—');
        },
        dayTitle(s) { return this.statusLabel(s); },
    };
}
</script>
</x-app-layout>
