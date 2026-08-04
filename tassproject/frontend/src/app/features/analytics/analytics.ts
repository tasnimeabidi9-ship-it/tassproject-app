import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AnalyticsService } from '../../core/services/analytics.service';
import { AnalyticsOverview } from '../../core/models/analytics.model';

const STAGE_LABELS: Record<string, string> = {
  lead_found: 'Lead found',
  approved: 'Approved',
  contacted: 'Contacted',
  replied: 'Replied',
  interested: 'Interested',
  won: 'Won',
  lost: 'Lost',
};

const STATUS_LABELS: Record<string, string> = {
  scheduled: 'Scheduled',
  sent: 'Sent',
  delivered: 'Delivered',
  opened: 'Opened',
  clicked: 'Clicked',
  replied: 'Replied',
  failed: 'Failed',
  cancelled: 'Cancelled',
};

const SENTIMENT_LABELS: Record<string, string> = {
  positive: 'Positive',
  interested: 'Interested',
  negative: 'Negative',
  out_of_office: 'Out of office',
  unsubscribe: 'Unsubscribe',
};

const DONUT_COLORS = [
  '#0d8a7c',
  '#12a496',
  '#3dbfae',
  '#6fd3c6',
  '#9be6dc',
  '#065f56',
  '#c5f1eb',
  '#04302b',
];

@Component({
  selector: 'app-analytics',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './analytics.html',
  styleUrl: './analytics.css',
})
export class Analytics implements OnInit {
  overview = signal<AnalyticsOverview | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);

  constructor(private analytics: AnalyticsService) {}

  ngOnInit() {
    this.load();
  }

  load() {
    this.loading.set(true);
    this.error.set(null);
    this.analytics.overview().subscribe({
      next: (data) => {
        this.overview.set(data);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.error.set('Could not load analytics. Please try again.');
      },
    });
  }

  stageLabel(stage: string): string {
    return STAGE_LABELS[stage] ?? stage.replace(/_/g, ' ');
  }

  statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status.replace(/_/g, ' ');
  }

  sentimentLabel(sentiment: string): string {
    return SENTIMENT_LABELS[sentiment] ?? sentiment.replace(/_/g, ' ');
  }

  hasData(): boolean {
    const o = this.overview();
    if (!o) return false;
    const k = o.kpis;
    return k.total_leads > 0 || k.emails_sent > 0 || k.sms_sent > 0 || o.campaigns.length > 0;
  }

  // ── Donut chart (single ring, segments via stroke-dasharray) ────────
  donutSegments(entries: Array<{ label: string; value: number }>): Array<{
    label: string;
    value: number;
    pct: number;
    dasharray: string;
    dashoffset: number;
    color: string;
  }> {
    const total = entries.reduce((sum, e) => sum + e.value, 0);
    if (total <= 0) return [];
    let cum = 0;
    return entries
      .filter((e) => e.value > 0)
      .map((e, i) => {
        const pct = (e.value / total) * 100;
        const seg = {
          label: e.label,
          value: e.value,
          pct,
          dasharray: `${pct.toFixed(2)} ${(100 - pct).toFixed(2)}`,
          dashoffset: -cum,
          color: DONUT_COLORS[i % DONUT_COLORS.length],
        };
        cum += pct;
        return seg;
      });
  }

  channelDonut() {
    const cs = this.overview()?.channel_split;
    if (!cs) return [];
    return this.donutSegments([
      { label: 'Email', value: cs.email.sent },
      { label: 'SMS', value: cs.sms.sent },
    ]);
  }

  statusDonut() {
    const sb = this.overview()?.status_breakdown ?? {};
    const entries = Object.entries(sb).map(([status, value]) => ({
      label: this.statusLabel(status),
      value,
    }));
    return this.donutSegments(entries);
  }

  donutTotal(key: 'channel' | 'status'): number {
    const o = this.overview();
    if (!o) return 0;
    if (key === 'channel') {
      return o.channel_split.email.sent + o.channel_split.sms.sent;
    }
    return Object.values(o.status_breakdown).reduce((a, b) => a + b, 0);
  }

  // ── Timeline line / area chart (SVG polyline) ────────────────────────
  private readonly CHART_W = 720;
  private readonly CHART_H = 240;
  private readonly CHART_PAD = 24;

  timeline() {
    return this.overview()?.timeline ?? [];
  }

  timelineMax(): number {
    const t = this.timeline();
    return Math.max(1, ...t.flatMap((d) => [d.sent, d.opened, d.replied]));
  }

  timelinePoints(key: 'sent' | 'opened' | 'replied'): string {
    const t = this.timeline();
    if (t.length < 2) return '';
    const { CHART_W: w, CHART_H: h, CHART_PAD: pad } = this;
    const max = this.timelineMax();
    const stepX = (w - pad * 2) / (t.length - 1);
    return t
      .map((d, i) => {
        const x = pad + i * stepX;
        const y = h - pad - (d[key] / max) * (h - pad * 2);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
      })
      .join(' ');
  }

  timelineArea(key: 'sent' | 'opened' | 'replied'): string {
    const t = this.timeline();
    if (t.length < 2) return '';
    const { CHART_W: w, CHART_PAD: pad } = this;
    const baseY = this.CHART_H - pad;
    return `M ${pad} ${baseY} ${this.timelinePoints(key)} L ${w - pad} ${baseY} Z`;
  }

  timelineLastValue(key: 'sent' | 'opened' | 'replied'): { x: number; y: number; value: number } | null {
    const t = this.timeline();
    if (t.length < 1) return null;
    const { CHART_W: w, CHART_H: h, CHART_PAD: pad } = this;
    const max = this.timelineMax();
    const stepX = (w - pad * 2) / (t.length - 1);
    const i = t.length - 1;
    const x = pad + i * stepX;
    const y = h - pad - (t[i][key] / max) * (h - pad * 2);
    return { x, y, value: t[i][key] };
  }

  yGridlines(): Array<{ y: string; label: number }> {
    const { CHART_H: h, CHART_PAD: pad } = this;
    const max = this.timelineMax();
    return [0, 0.25, 0.5, 0.75, 1].map((f) => ({
      y: (h - pad - f * (h - pad * 2)).toFixed(1),
      label: Math.round(f * max),
    }));
  }

  xLabels(): Array<{ x: string; label: string }> {
    const t = this.timeline();
    if (!t.length) return [];
    const { CHART_W: w, CHART_PAD: pad } = this;
    const stepX = (w - pad * 2) / Math.max(t.length - 1, 1);
    const step = Math.ceil(t.length / 7);
    return t
      .map((d, i) => ({ d, i }))
      .filter(({ i }) => i % step === 0 || i === t.length - 1)
      .map(({ d, i }) => ({
        x: (pad + i * stepX).toFixed(1),
        label: new Date(d.date + 'T00:00:00').toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
        }),
      }));
  }

  // ── Horizontal bars ──────────────────────────────────────────────────
  maxOf(list: Array<{ count: number } | { value: number }>): number {
    return Math.max(
      1,
      ...list.map((i) => ('count' in i ? i.count : i.value))
    );
  }

  barWidth(count: number, max: number): number {
    return Math.round((count / Math.max(1, max)) * 100);
  }

  pipeline() {
    return this.overview()?.pipeline ?? [];
  }

  pipelineMax(): number {
    return this.maxOf(this.pipeline());
  }

  topCompanies() {
    return this.overview()?.top_companies ?? [];
  }

  companiesMax(): number {
    return this.maxOf(this.topCompanies());
  }

  campaigns() {
    return this.overview()?.campaigns ?? [];
  }

  campaignsMax(): number {
    return Math.max(1, ...this.campaigns().map((c) => c.sent));
  }

  sentimentEntries(): Array<{ label: string; value: number }> {
    const s = this.overview()?.sentiment ?? {};
    return Object.entries(s).map(([key, value]) => ({
      label: this.sentimentLabel(key),
      value,
    }));
  }

  sentimentMax(): number {
    return this.maxOf(this.sentimentEntries());
  }

  bestSendTime(): { day: string | null; hour: number | null; label: string } {
    const bst = this.overview()?.best_send_time;
    if (!bst || bst.hour === null || bst.day === null) {
      return { day: null, hour: null, label: 'Not enough opened messages yet' };
    }
    const h12 = bst.hour % 12 === 0 ? 12 : bst.hour % 12;
    const period = bst.hour < 12 ? 'AM' : 'PM';
    return { day: bst.day, hour: bst.hour, label: `${bst.day} at ${h12}:00 ${period}` };
  }
}
