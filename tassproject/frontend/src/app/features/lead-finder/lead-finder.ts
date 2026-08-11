import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LeadService } from '../../core/services/lead.service';
import { SettingsService } from '../../core/services/settings.service';
import { PlacePreviewResult, ScrapeJob, TargetProfile } from '../../core/models/lead.model';
import { IntegrationStatus } from '../../core/models/settings.model';

@Component({
  selector: 'app-lead-finder',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './lead-finder.html',
  styleUrl: './lead-finder.css',
})
export class LeadFinder implements OnInit {
  profiles = signal<TargetProfile[]>([]);
  jobs = signal<ScrapeJob[]>([]);
  integrations = signal<Record<string, IntegrationStatus> | null>(null);
  loadingProfiles = signal(false);
  creatingProfile = signal(false);
  starting = signal<number | null>(null); // profile id currently starting

  showForm = signal(false);
  form = {
    name: '',
    industry: '',
    job_title: '',
    location: '',
    keywordsRaw: '',
  };

  private pollHandle: ReturnType<typeof setInterval> | null = null;

  // Real-company preview panel
  previewQuery = '';
  previewLocation = '';
  previewResults = signal<PlacePreviewResult[]>([]);
  previewLoading = signal(false);
  previewError = signal<string | null>(null);
  previewSearched = signal(false);

  constructor(private leadApi: LeadService, private settingsApi: SettingsService) {}

  ngOnInit() {
    this.loadProfiles();
    this.loadJobs();
    this.loadIntegrations();
    // Light polling so job status updates without a manual refresh.
    this.pollHandle = setInterval(() => this.loadJobs(), 5000);
  }

  ngOnDestroy() {
    if (this.pollHandle) clearInterval(this.pollHandle);
  }

  loadIntegrations() {
    this.settingsApi.integrations().subscribe({
      next: (status) => this.integrations.set(status),
      error: () => this.integrations.set(null),
    });
  }

  hunterStatus(): IntegrationStatus | null {
    return this.integrations()?.['hunter'] ?? null;
  }

  loadProfiles() {
    this.loadingProfiles.set(true);
    this.leadApi.listProfiles().subscribe({
      next: (profiles) => {
        this.profiles.set(profiles);
        this.loadingProfiles.set(false);
      },
      error: () => this.loadingProfiles.set(false),
    });
  }

  loadJobs() {
    this.leadApi.recentJobs().subscribe((jobs) => this.jobs.set(jobs));
  }

  submitProfile() {
    if (!this.form.name.trim()) return;

    this.creatingProfile.set(true);
    const keywords = this.form.keywordsRaw
      .split(',')
      .map((k) => k.trim())
      .filter(Boolean);

    this.leadApi
      .createProfile({
        name: this.form.name,
        industry: this.form.industry || null,
        job_title: this.form.job_title || null,
        location: this.form.location || null,
        keywords,
      })
      .subscribe({
        next: () => {
          this.creatingProfile.set(false);
          this.showForm.set(false);
          this.form = { name: '', industry: '', job_title: '', location: '', keywordsRaw: '' };
          this.loadProfiles();
        },
        error: () => this.creatingProfile.set(false),
      });
  }

  deleteProfile(id: number) {
    if (!confirm('Delete this profile?')) return;
    this.leadApi.deleteProfile(id).subscribe(() => this.loadProfiles());
  }

  startScrape(profile: TargetProfile) {
    this.starting.set(profile.id);
    this.leadApi.startScrape(profile.id).subscribe({
      next: () => {
        this.starting.set(null);
        this.loadJobs();
      },
      error: () => this.starting.set(null),
    });
  }

  statusClass(status: string): string {
    return `status status-${status}`;
  }

  runPreview() {
    if (!this.previewQuery.trim()) return;

    this.previewLoading.set(true);
    this.previewError.set(null);
    this.previewSearched.set(true);

    this.leadApi.previewSearch(this.previewQuery, this.previewLocation || undefined).subscribe({
      next: (res) => {
        this.previewLoading.set(false);
        this.previewResults.set(res.results || []);
        if (res.message) this.previewError.set(res.message);
      },
      error: (err) => {
        this.previewLoading.set(false);
        this.previewResults.set([]);
        this.previewError.set(err.error?.message || 'Preview search failed.');
      },
    });
  }

  useAsProfileSeed(place: PlacePreviewResult) {
    // Quick way to jump from "found a real example" straight into
    // creating a target profile seeded with that category/location.
    this.showForm.set(true);
    if (place.category && !this.form.industry) this.form.industry = place.category;
    if (this.previewLocation && !this.form.location) this.form.location = this.previewLocation;
  }
}
