import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SequenceService } from '../../core/services/sequence.service';
import { Campaign, Sequence } from '../../core/models/sequence.model';

@Component({
  selector: 'app-campaigns',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './campaigns.html',
  styleUrl: './campaigns.css',
})
export class Campaigns implements OnInit {
  campaigns = signal<Campaign[]>([]);
  sequences = signal<Sequence[]>([]);
  loading = signal(false);

  showForm = signal(false);
  name = '';
  sequenceId: number | null = null;
  creating = signal(false);
  actingOn = signal<number | null>(null);
  lastMessage = signal<string | null>(null);

  constructor(private sequenceApi: SequenceService) {}

  ngOnInit() {
    this.load();
    this.sequenceApi.listSequences().subscribe((s) => this.sequences.set(s));
  }

  load() {
    this.loading.set(true);
    this.sequenceApi.listCampaigns().subscribe({
      next: (c) => {
        this.campaigns.set(c);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  create() {
    if (!this.name.trim() || !this.sequenceId) return;
    this.creating.set(true);
    this.sequenceApi.createCampaign({ name: this.name, sequence_id: this.sequenceId }).subscribe({
      next: () => {
        this.creating.set(false);
        this.showForm.set(false);
        this.name = '';
        this.sequenceId = null;
        this.load();
      },
      error: () => this.creating.set(false),
    });
  }

  start(campaign: Campaign) {
    if (!confirm(`Start "${campaign.name}"? This enrolls all contacts currently at stage "approved" into the sequence.`)) return;
    this.actingOn.set(campaign.id);
    this.sequenceApi.startCampaign(campaign.id).subscribe({
      next: (res) => {
        this.actingOn.set(null);
        this.lastMessage.set(res.message);
        this.load();
      },
      error: (err) => {
        this.actingOn.set(null);
        this.lastMessage.set(err.error?.message || 'Failed to start campaign.');
      },
    });
  }

  pause(campaign: Campaign) {
    this.actingOn.set(campaign.id);
    this.sequenceApi.pauseCampaign(campaign.id).subscribe({
      next: (res) => {
        this.actingOn.set(null);
        this.lastMessage.set(res.message);
        this.load();
      },
      error: () => this.actingOn.set(null),
    });
  }

  statusClass(status: string): string {
    return `status status-${status}`;
  }
}
