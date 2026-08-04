import { Component, OnInit, OnDestroy, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AnalyticsService } from '../../core/services/analytics.service';
import { DashboardData } from '../../core/models/analytics.model';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css',
})
export class Dashboard implements OnInit, OnDestroy {
  data = signal<DashboardData | null>(null);
  loading = signal(true);
  private pollHandle: ReturnType<typeof setInterval> | null = null;

  constructor(private analytics: AnalyticsService) {}

  ngOnInit() {
    this.load();
    this.pollHandle = setInterval(() => this.load(), 30000);
  }

  ngOnDestroy() {
    if (this.pollHandle) clearInterval(this.pollHandle);
  }

  load() {
    this.analytics.dashboard().subscribe({
      next: (d) => {
        this.data.set(d);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  get hasAlerts(): boolean {
    const a = this.data()?.alerts;
    return !!a && (a.failed_messages > 0 || a.leads_without_email > 0 || a.unread_replies > 0);
  }
}
