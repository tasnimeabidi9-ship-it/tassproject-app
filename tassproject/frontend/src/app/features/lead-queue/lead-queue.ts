import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LeadService } from '../../core/services/lead.service';
import { LeadQueueItem } from '../../core/models/lead.model';

@Component({
  selector: 'app-lead-queue',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './lead-queue.html',
  styleUrl: './lead-queue.css',
})
export class LeadQueue implements OnInit {
  leads = signal<LeadQueueItem[]>([]);
  loading = signal(false);
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);

  approvalStatus = 'pending_review';
  search = '';
  minScore: number | null = null;

  bulkThreshold = 80;
  bulkRunning = signal(false);
  actingOn = signal<number | null>(null);

  constructor(private leadApi: LeadService) {}

  ngOnInit() {
    this.fetch();
  }

  fetch(page = 1) {
    this.loading.set(true);
    this.leadApi
      .queue({
        approval_status: this.approvalStatus,
        search: this.search || undefined,
        min_score: this.minScore ?? undefined,
        page,
      })
      .subscribe({
        next: (res) => {
          this.leads.set(res.data);
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

  approve(lead: LeadQueueItem) {
    this.actingOn.set(lead.id);
    this.leadApi.approve(lead.id).subscribe({
      next: () => {
        this.actingOn.set(null);
        this.fetch(this.currentPage());
      },
      error: () => this.actingOn.set(null),
    });
  }

  reject(lead: LeadQueueItem) {
    this.actingOn.set(lead.id);
    this.leadApi.reject(lead.id).subscribe({
      next: () => {
        this.actingOn.set(null);
        this.fetch(this.currentPage());
      },
      error: () => this.actingOn.set(null),
    });
  }

  bulkApprove() {
    if (!confirm(`Approve all pending leads with score >= ${this.bulkThreshold}?`)) return;
    this.bulkRunning.set(true);
    this.leadApi.bulkApprove(this.bulkThreshold).subscribe({
      next: (res) => {
        this.bulkRunning.set(false);
        alert(`${res.approved_count} lead(s) approved.`);
        this.fetch(1);
      },
      error: () => this.bulkRunning.set(false),
    });
  }
}
