import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { AnalyticsService } from '../../core/services/analytics.service';
import { AiActionLogEntry } from '../../core/models/analytics.model';

@Component({
  selector: 'app-actions-log',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './actions-log.html',
  styleUrl: './actions-log.css',
})
export class ActionsLog implements OnInit {
  entries = signal<AiActionLogEntry[]>([]);
  loading = signal(false);
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);

  status = '';
  channel = '';
  cancelling = signal<number | null>(null);

  constructor(private analytics: AnalyticsService, private route: ActivatedRoute) {}

  ngOnInit() {
    const qpStatus = this.route.snapshot.queryParamMap.get('status');
    if (qpStatus) this.status = qpStatus;
    this.fetch();
  }

  fetch(page = 1) {
    this.loading.set(true);
    this.analytics
      .actionsLog({ status: this.status || undefined, channel: this.channel || undefined, page })
      .subscribe({
        next: (res) => {
          this.entries.set(res.data);
          this.currentPage.set(res.current_page);
          this.lastPage.set(res.last_page);
          this.total.set(res.total);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }

  applyFilters() {
    this.fetch(1);
  }

  goToPage(page: number) {
    if (page < 1 || page > this.lastPage()) return;
    this.fetch(page);
  }

  cancel(entry: AiActionLogEntry) {
    if (!confirm('Cancel this scheduled action before it fires?')) return;
    this.cancelling.set(entry.id);
    this.analytics.cancelAction(entry.id).subscribe({
      next: () => {
        this.cancelling.set(null);
        this.fetch(this.currentPage());
      },
      error: () => this.cancelling.set(null),
    });
  }

  statusClass(status: string): string {
    return `status status-${status}`;
  }
}
